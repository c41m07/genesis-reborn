<?php

declare(strict_types=1);

namespace App\Service\Guild;

use App\Entity\{Fleet, GuildHallConvoy, PlayerUniverse};
use App\Manager\GameManager;
use App\Repository\GameRepository;
use App\Service\Colony\ActionGuard;
use App\Service\Fleet\FleetHold;
use App\Service\Player\VacationCalendar;

/** Récupération sur place uniquement, sans détour, conversion de fret ni droit de guilde. */
final readonly class GuildHallConvoyRecovery
{
    public function __construct(
        private GameRepository $repository,
        private GameManager $manager,
        private ActionGuard $guard,
        private VacationCalendar $vacations,
        private FleetHold $hold,
    ) {
    }

    public function land(PlayerUniverse $member, int $fleetId, string $requestKey): bool
    {
        return $this->manager->transaction(function () use ($member, $fleetId, $requestKey): bool {
            $this->guard->lockMembership($member, currentReads: true);
            $this->vacations->assertCanSendFleet($member);
            $fleet = $this->repository->find(Fleet::class, $fleetId);
            if (!$fleet instanceof Fleet || $fleet->playerUniverse->id !== $member->id
                || $fleet->mission !== GuildHallConvoy::MISSION) {
                throw new \DomainException('Convoi inaccessible.');
            }
            $this->manager->lockCurrent($fleet);
            if (!$this->guard->receipt($member, $requestKey)) {
                return false;
            }
            $reached = $fleet->returnPlanet ?? $fleet->homePlanet;
            if ($fleet->status !== 'hall_wait' || $fleet->reservedFuel !== 0.0
                || ($fleet->navigation['hall_recovery']['position_planet_id'] ?? null) !== $reached->id) {
                throw new \DomainException('Ce convoi n’est pas en attente à cette position.');
            }
            $this->guard->lock($member, $reached, currentReads: true);
            $this->hold->install($fleet, $reached);

            return true;
        });
    }
}
