<?php
namespace App\Service\Fleet;

use App\Entity\{Fleet,Planet,PlayerUniverse};
use App\Manager\GameManager;
use App\Repository\GameRepository;
use App\Service\Colony\ActionGuard;
use Psr\Clock\ClockInterface;

final class FleetRecallService
{
    public function __construct(private GameRepository $repo,private GameManager $manager,private ActionGuard $guard,
        private FleetService $fleets,private FleetRecallPlanner $planner,private ClockInterface $clock) {}

    public function preview(PlayerUniverse $member,Fleet $fleet,?int $returnPlanetId=null): array
    {
        $this->authorizeFleet($member,$fleet);
        return $this->planner->plan($member,$fleet,$returnPlanetId,$this->clock->now());
    }

    public function recall(PlayerUniverse $member,int $fleetId,?int $returnPlanetId,string $version,string $requestKey): bool
    {
        return $this->manager->transaction(function()use($member,$fleetId,$returnPlanetId,$version,$requestKey): bool {
            $this->guard->lockMembership($member);
            $fleet=$this->repo->find(Fleet::class,$fleetId);
            if(!$fleet) throw new \DomainException('Flotte inaccessible.');
            $this->authorizeFleet($member,$fleet);
            $this->manager->lock($fleet->homePlanet);$this->manager->lock($fleet);
            $this->authorizeFleet($member,$fleet);
            if(!$this->guard->receipt($member,$requestKey)) return false;
            if(!hash_equals(hash('sha256',$fleet->dispatchToken),$version)) throw new \DomainException('La mission a changé. Rouvrez le formulaire de rappel.');
            $returnPlanet=$this->planner->destination($member,$fleet,$returnPlanetId);
            $this->manager->lock($returnPlanet);
            if($returnPlanet->owner?->id!==$member->id || $returnPlanet->solarSystem->galaxy->universe->id!==$member->universe->id) throw new \DomainException('La colonie de retour ne vous appartient plus.');
            $quote=$this->planner->plan($member,$fleet,$returnPlanet->id,$this->clock->now());
            $this->fleets->startPlayerRecallLocked($fleet,$returnPlanet,$quote);
            return true;
        });
    }

    private function authorizeFleet(PlayerUniverse $member,Fleet $fleet): void
    {
        if(!$member->isPlayerControlled() || $member->player->lockedAt!==null || $fleet->playerUniverse->id!==$member->id
            || $fleet->homePlanet->solarSystem->galaxy->universe->id!==$member->universe->id) throw new \DomainException('Flotte inaccessible dans cet univers.');
    }

}
