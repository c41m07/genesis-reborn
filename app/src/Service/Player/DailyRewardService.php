<?php

declare(strict_types=1);

namespace App\Service\Player;

use App\Entity\{DailyRewardClaim, Planet, Player, PlayerUniverse};
use App\Manager\GameManager;
use App\Service\Catalog\GameCatalog;
use App\Service\Colony\{ActionGuard, ResourceService};
use Doctrine\DBAL\Exception\LockWaitTimeoutException;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Clock\ClockInterface;

final class DailyRewardService
{
    public function __construct(
        private GameManager $manager,
        private EntityManagerInterface $em,
        private ActionGuard $guard,
        private ResourceService $resources,
        private GameCatalog $catalog,
        private ClockInterface $clock,
    ) {}

    /** @return array{date:string,claimed:bool,slot:int,reward:array{metal:int,nano:int}} */
    public function status(Player $player): array
    {
        return $this->statusAt($player, $this->day());
    }

    /** @return array{state:string,slot?:int,required?:array<string,int>,missing?:array<string,int>} */
    public function claim(Player $player, PlayerUniverse $membership, Planet $planet, string $formDate): array
    {
        return $this->manager->transaction(function () use ($player, $membership, $planet, $formDate): array {
            $this->guard->lock($membership, $planet, currentReads: true);
            if ($membership->player?->id !== $player->id) {
                return ['state' => 'stale'];
            }
            // La sélection d’univers et l’effacement peuvent déjà tenir le verrou du compte
            // dans un autre ordre. Ne jamais l’attendre après les verrous de jeu.
            try {
                $lockedAccount = $this->em->getConnection()->fetchAssociative(
                    'SELECT current_player_universe_id, locked_at, email_verified_at FROM player WHERE id = ? FOR UPDATE NOWAIT',
                    [$player->id],
                );
            } catch (LockWaitTimeoutException) {
                return ['state' => 'busy'];
            }
            if (!$lockedAccount || $lockedAccount['locked_at'] !== null || $lockedAccount['email_verified_at'] === null
                || (int) $lockedAccount['current_player_universe_id'] !== $membership->id) {
                return ['state' => 'stale'];
            }

            $day = $this->day();
            if ($formDate !== $day->format('Y-m-d')) {
                return ['state' => 'stale'];
            }
            $status = $this->statusAt($player, $day, forUpdate: true);
            if ($status['claimed']) {
                return ['state' => 'already'];
            }

            $deposit = $this->resources->deposit($planet, $status['reward'], allOrNothing: true);
            $missing = [];
            foreach ($deposit['overflow'] as $resource => $amount) {
                if ($amount > 0.0) {
                    $missing[$resource] = (int) ceil($amount);
                }
            }
            if ($missing !== []) {
                // Aucun reçu n’est créé : ce même jour du cycle reste disponible, même demain.
                return ['state' => 'full', 'required' => $status['reward'], 'missing' => $missing];
            }

            $receipt = new DailyRewardClaim();
            $receipt->player = $player;
            $receipt->claimDate = $day;
            $receipt->slot = $status['slot'];
            // Le lot et son reçu unique par compte et date partagent le même commit.
            $this->manager->save($receipt);

            return ['state' => 'claimed', 'slot' => $status['slot']];
        });
    }

    private function day(): \DateTimeImmutable
    {
        return $this->clock->now()->setTimezone(new \DateTimeZone('Europe/Paris'))->setTime(0, 0);
    }

    /** @return array{date:string,claimed:bool,slot:int,reward:array{metal:int,nano:int}} */
    private function statusAt(Player $player, \DateTimeImmutable $day, bool $forUpdate = false): array
    {
        $schedule = $this->catalog->rules()['daily_reward_experiment'] ?? null;
        if (!is_array($schedule) || !array_is_list($schedule) || count($schedule) !== 7) {
            throw new \LogicException('Le barème quotidien doit couvrir les sept jours du cycle.');
        }
        foreach ($schedule as $amounts) {
            if (!is_array($amounts) || count($amounts) !== 2 || !is_int($amounts['metal'] ?? null)
                || !is_int($amounts['nano'] ?? null) || min($amounts['metal'], $amounts['nano']) <= 0) {
                throw new \LogicException('Le barème quotidien exige des quantités positives de métal et nano.');
            }
        }
        // En écriture, relire le dernier reçu sous verrou après le compte, sans l’instantané
        // de lecture que les vérifications de jeu ont pu ouvrir avant celui-ci.
        $last = $this->em->getConnection()->fetchAssociative(
            'SELECT claim_date, slot FROM daily_reward_claim WHERE player_id = ? ORDER BY claim_date DESC LIMIT 1'.($forUpdate ? ' FOR UPDATE' : ''),
            [$player->id],
        );
        $date = $day->format('Y-m-d');
        // Ne pas avancer si l’horloge civile recule : aucun nouveau reçu avant la prochaine date.
        $claimed = $last !== false && $last['claim_date'] >= $date;
        $previousSlot = $last === false ? 0 : (int) $last['slot'];
        $slot = $claimed ? $previousSlot : $previousSlot % count($schedule) + 1;

        return ['date' => $date, 'claimed' => $claimed, 'slot' => $slot, 'reward' => $schedule[$slot - 1]];
    }
}
