<?php

declare(strict_types=1);

namespace App\Service\Advent;

use App\Entity\{AdventClaim, AdventEdition, Planet, Player, PlayerUniverse};
use App\Manager\GameManager;
use App\Service\Colony\{ActionGuard, ResourceService};
use Doctrine\DBAL\Exception\LockWaitTimeoutException;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Clock\ClockInterface;

final readonly class AdventRewards
{
    public function __construct(
        private AdventCalendar $calendar,
        private AdventEditions $editions,
        private ActionGuard $guard,
        private ResourceService $resources,
        private GameManager $manager,
        private EntityManagerInterface $em,
        private ClockInterface $clock,
    ) {}

    /** @return array<int, AdventClaim> */
    public function claims(Player $player, AdventEdition $edition): array
    {
        $claims = [];
        foreach ($this->em->getRepository(AdventClaim::class)->findBy(['player' => $player, 'adventEdition' => $edition]) as $claim) {
            $claims[$claim->door] = $claim;
        }

        return $claims;
    }

    /** @return array{state: string, missing?: array<string, int>} */
    public function open(Player $player, PlayerUniverse $membership, Planet $planet, AdventEdition $edition, int $door, int $choice): array
    {
        if (!$this->editions->enabled) {
            return ['state' => 'unavailable'];
        }

        return $this->manager->transaction(function () use ($player, $membership, $planet, $edition, $door, $choice): array {
            $this->guard->lock($membership, $planet, currentReads: true);
            if ($membership->player?->id !== $player->id) {
                return ['state' => 'stale'];
            }

            // Même ordre que la récompense quotidienne : après la participation
            // et la planète, ne pas attendre un verrou du compte déjà détenu
            // par un changement d’univers ou une suppression de compte.
            try {
                $account = $this->em->getConnection()->fetchAssociative(
                    'SELECT current_player_universe_id, locked_at, email_verified_at FROM player WHERE id = ? FOR UPDATE NOWAIT',
                    [$player->id],
                );
            } catch (LockWaitTimeoutException) {
                return ['state' => 'busy'];
            }
            if (!$account || $account['locked_at'] !== null || $account['email_verified_at'] === null
                || (int) $account['current_player_universe_id'] !== $membership->id) {
                return ['state' => 'stale'];
            }

            $now = $this->clock->now()->setTimezone(new \DateTimeZone('UTC'));

            // Une lecture courante du droit voit la décision d’un autre onglet
            // même si une lecture précédente avait ouvert un instantané SQL.
            $existing = $this->em->getConnection()->fetchAssociative(
                'SELECT id, delivered_at FROM advent_claim WHERE player_id = ? AND advent_edition_id = ? AND door = ? FOR UPDATE',
                [$player->id, $edition->id, $door],
            );
            if ($existing !== false && $existing['delivered_at'] !== null) {
                return ['state' => 'already'];
            }
            $claim = $existing === false ? null : $this->em->find(AdventClaim::class, (int) $existing['id']);
            if ($claim !== null && $claim->choice !== $choice) {
                return ['state' => 'reserved'];
            }
            if ($claim === null) {
                $definition = $edition->doors[$door] ?? null;
                // Après la clôture, les portes non ouvertes expirent ; aucun
                // nouveau droit n’est recréé sur la base d’une ancienne page.
                if ($definition === null || !isset($definition['options'][$choice])
                    || !$this->calendar->canOpen($now, new \DateTimeImmutable($definition['opens_at']), $edition->closesAt)) {
                    return ['state' => 'unavailable'];
                }
                $claim = new AdventClaim();
                $claim->adventEdition = $edition;
                $claim->player = $player;
                $claim->originalDestinationId = $planet->id;
                $claim->door = $door;
                $claim->choice = $choice;
                $claim->resources = $definition['options'][$choice];
                $claim->openedAt = $now;
                $this->manager->save($claim);
            }

            $deposit = $this->resources->deposit($planet, $claim->resources, allOrNothing: true);
            $missing = [];
            foreach ($deposit['overflow'] as $resource => $amount) {
                if ($amount > 0.0) {
                    $missing[$resource] = (int) ceil($amount);
                }
            }
            if ($missing !== []) {
                // La porte et son choix sont enregistrés sans crédit partiel.
                // La reprise est volontaire et le même lot reste réservé.
                return ['state' => 'full', 'missing' => $missing];
            }

            $claim->deliveredDestinationId = $planet->id;
            $claim->deliveredAt = $now;

            return ['state' => 'delivered'];
        });
    }
}
