<?php

declare(strict_types=1);

namespace App\Service\Guild;

use App\Entity\{Fleet, Guild, GuildHall, GuildHallConvoy};
use App\Manager\GameManager;
use App\Repository\GameRepository;
use App\Service\Fleet\{FleetDispatch, FleetNavigation};
use App\Service\Notification\NotificationService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Uid\Uuid;

/** Traitement de la navette depuis le worker de flotte existant. */
final readonly class GuildHallConvoyWorker
{
    public function __construct(
        private GameManager $manager,
        private GameRepository $repository,
        private EntityManagerInterface $em,
        private GuildHallScheduleProvider $schedules,
        private GuildHallConvoyArrival $arrival,
        private GuildHallConvoyReturn $return,
        private FleetDispatch $dispatch,
        private FleetNavigation $navigation,
        private NotificationService $notifications,
    ) {
    }

    /** La participation, l’univers et la flotte ont été verrouillés par FleetService. */
    public function processLocked(Fleet $fleet, string $token, \DateTimeImmutable $now): void
    {
        if (!in_array($fleet->status, ['traveling', 'returning'], true)
            || $fleet->arrivalAt === null || $fleet->arrivalAt > $now
            || !hash_equals($fleet->dispatchToken, $token)) {
            return;
        }

        $receipt = $this->repository->one(GuildHallConvoy::class, ['fleet' => $fleet]);
        if (!$receipt instanceof GuildHallConvoy) {
            throw new \LogicException('Reçu de convoi absent.');
        }
        $this->manager->lockCurrent($receipt);
        if ($fleet->status === 'returning') {
            $home = $fleet->returnPlanet ?? $fleet->homePlanet;
            $this->manager->lockCurrent($home);
            if ($this->return->settleLocked($fleet, $token, $now)) {
                return;
            }
            // Le convoi a rejoint les coordonnées du retour : le trajet a
            // consommé sa réserve, qui ne peut pas payer un second trajet.
            // Conserver la position atteinte et le fret, sans crédit au tiers.
            $this->return->consumeReturnReserveLocked($fleet);
            $fleet->navigation['hall_recovery']['position_planet_id'] = $home->id;
            $fleet->status = 'hall_wait';
            $fleet->arrivalAt = null;
            $fleet->dispatchToken = Uuid::v4()->toRfc4122();
            $this->notifications->enqueue($fleet->playerUniverse, 'Convoi du Hall en attente : votre colonie de retour ne vous appartient plus.', journalKind: 'arrival');

            return;
        }
        if (!$receipt->canSettle($token)) {
            return;
        }

        $hall = $this->attachedHall($receipt);
        $schedule = $hall === null ? null : $this->schedules->schedule();
        $oneWay = $fleet->reservedFuel / 2;
        $fleet->cargo['hydrogen'] = max(0.0, ($fleet->cargo['hydrogen'] ?? 0) - $oneWay);
        $fleet->reservedFuel -= $oneWay;
        if ($schedule === null) {
            // Pas de dépôt si la guilde a disparu, si le membre l’a quittée ou
            // si les opérations ne sont plus configurées. Le fret revient intact.
            $receipt->settle($token, $now, []);
        } elseif (!$this->arrival->settleLocked($receipt, $token, $now, $hall, $schedule)) {
            throw new \LogicException('Impossible d’acquitter le convoi courant.');
        }

        $fleet->status = 'returning';
        $fleet->departureAt = $fleet->arrivalAt;
        $fleet->arrivalAt = $fleet->departureAt->modify('+'.$fleet->travelSeconds.' seconds');
        $this->navigation->beginLeg($fleet, $fleet->departureAt);
        $this->dispatch->schedule($fleet);
    }

    private function attachedHall(GuildHallConvoy $receipt): ?GuildHall
    {
        $membership = $receipt->fleet->playerUniverse;
        if ($membership->isForgotten()) {
            return null;
        }
        // Les changements de guilde partagent le verrou d’univers déjà détenu.
        $guildId = $this->em->getConnection()->fetchOne(
            'SELECT guild_id FROM guild_member WHERE player_universe_id = ? FOR UPDATE',
            [$membership->id],
        );
        if ((int) $guildId !== $receipt->guildId) {
            return null;
        }
        $guild = $this->repository->find(Guild::class, $receipt->guildId);
        if (!$guild instanceof Guild || $guild->universe->id !== $membership->universe->id) {
            return null;
        }
        $this->manager->lockCurrent($guild);
        if ($guild->archivedAt !== null) {
            return null;
        }
        $hall = $this->repository->find(GuildHall::class, $receipt->hallId);
        if (!$hall instanceof GuildHall) {
            return null;
        }
        $this->manager->lockCurrent($hall);

        return $hall;
    }
}
