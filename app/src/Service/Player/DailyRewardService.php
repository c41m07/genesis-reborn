<?php

declare(strict_types=1);

namespace App\Service\Player;

use App\Entity\{DailyRewardClaim, Planet, Player, PlayerUniverse};
use App\Manager\GameManager;
use App\Service\Boost\{BoostGrantService, TimedBoostEffects};
use App\Service\Colony\ActionGuard;
use Doctrine\DBAL\Exception\LockWaitTimeoutException;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Clock\ClockInterface;

final class DailyRewardService
{
    public function __construct(
        private GameManager $manager,
        private EntityManagerInterface $em,
        private ActionGuard $guard,
        private BoostGrantService $grants,
        private ClockInterface $clock,
    ) {}

    /** @return array{date:string,claimed:bool,slot:int,reward:array{effect:string,label:string,description:string,factor:float}} */
    public function status(Player $player): array
    {
        return $this->statusAt($player, $this->day());
    }

    /** @return array{state:string,slot?:int} */
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

            // Le reçu quotidien et l’objet sont atomiques. Une répétition ne doit ni doubler
            // l’objet, ni créditer des ressources comme le faisait l’ancien parcours.
            if (!$this->grants->awardDailyLocked($membership, $player->id, $formDate, $status['reward']['effect'])) {
                return ['state' => 'already'];
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

    /** @return list<array{effect:string,label:string,description:string,factor:float}> */
    public function schedule(): array
    {
        return TimedBoostEffects::schedule();
    }

    private function day(): \DateTimeImmutable
    {
        return $this->clock->now()->setTimezone(new \DateTimeZone('Europe/Paris'))->setTime(0, 0);
    }

    /** @return array{date:string,claimed:bool,slot:int,reward:array{effect:string,label:string,description:string,factor:float}} */
    private function statusAt(Player $player, \DateTimeImmutable $day, bool $forUpdate = false): array
    {
        $schedule = $this->schedule();
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
