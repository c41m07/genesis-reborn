<?php
namespace App\Service\Combat;

use App\Entity\{CombatEvent,Fleet,FleetShip,OccupationDeployment,OccupationEvent,Planet,PlanetOccupation,PlayerTechnology,PlayerUniverse};
use App\Manager\GameManager;
use App\Repository\GameRepository;
use App\Service\Catalog\GameCatalog;
use App\Service\Player\{EmpireActivityService,VacationCalendar,RelationResolver};
use Psr\Clock\ClockInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

final class OccupationService
{
    public function __construct(private GameManager $manager,private GameRepository $repo,private OccupationPolicy $policy,
        private GameCatalog $catalog,private EmpireActivityService $activity,private VacationCalendar $vacations,private RelationResolver $relations,private ClockInterface $clock,private OccupationScheduler $scheduler,
        #[Autowire('%env(bool:FORGOTTEN_EMPIRES_ENABLED)%')] private bool $enabled) {}

    public function isEnabled(): bool { return $this->enabled; }
    public function activeFor(Planet $planet): ?PlanetOccupation
    {
        return $this->repo->one(PlanetOccupation::class,['planet'=>$planet,'status'=>'active']);
    }
    public function reservedSlots(PlayerUniverse $membership): int
    {
        return $this->repo->occupationReservations($membership);
    }
    public function canOccupy(PlayerUniverse $membership): bool
    {
        return $membership->isPlayerControlled() && !$this->vacations->isActive($membership);
    }
    public function canStartOn(PlayerUniverse $membership,Planet $planet): bool
    {
        return $this->enabled && $this->canOccupy($membership) && $this->policy->isConquerable($planet,$membership) && $this->activeFor($planet)===null;
    }
    public function canReinforce(PlayerUniverse $member,Planet $planet): bool
    {
        $occupation=$this->activeFor($planet);
        return $this->enabled && $occupation!==null && $this->canOccupy($member) && $this->canOccupy($occupation->occupant)
            && $member->universe->id===$occupation->occupant->universe->id
            && ($member->id===$occupation->occupant->id || $this->relations->allied($member,$occupation->occupant));
    }
    public function reinforcementHours(Planet $planet): float
    {
        $occupation=$this->activeFor($planet);
        if($occupation===null) return 0;
        return max((int)($this->catalog->rules()['combat_window_seconds']??600),$occupation->captureAt->getTimestamp()-$this->clock->now()->getTimestamp())/3600;
    }
    public function stationReinforcementLocked(Fleet $fleet): bool
    {
        $planet=$fleet->destination;
        if(!$this->acceptsReinforcement($fleet)) return false;
        $occupation=$this->activeFor($planet);
        $deployment=$this->repo->one(OccupationDeployment::class,['fleet'=>$fleet]);
        if($deployment->arrivedAt!==null) return $fleet->status==='occupying';
        $deployment->arrivedAt=$this->clock->now();
        $fleet->status='occupying';$fleet->departureAt=$this->clock->now();$fleet->arrivalAt=$occupation->captureAt;
        $fleet->dispatchToken=\Symfony\Component\Uid\Uuid::v4()->toRfc4122();return true;
    }
    public function recordReinforcementDeparture(Fleet $fleet): void
    {
        if($fleet->mission!=='defend' || $fleet->destination===null || !$this->canReinforce($fleet->playerUniverse,$fleet->destination)) return;
        $deployment=new OccupationDeployment();$deployment->fleet=$fleet;$deployment->occupation=$this->activeFor($fleet->destination);
        $deployment->token=$deployment->occupation->dispatchToken;$deployment->dispatchedAt=$this->clock->now();$this->manager->save($deployment);
    }
    public function acceptsReinforcement(Fleet $fleet): bool
    {
        if($fleet->destination===null || !$this->canReinforce($fleet->playerUniverse,$fleet->destination)) return false;
        $deployment=$this->repo->one(OccupationDeployment::class,['fleet'=>$fleet]);$occupation=$this->activeFor($fleet->destination);
        return $deployment!==null && $deployment->occupation->id===$occupation->id && hash_equals($occupation->dispatchToken,$deployment->token);
    }
    public function retainAfterBattleLocked(PlanetOccupation $occupation,CombatEvent $combat): void
    {
        if($this->repo->one(OccupationEvent::class,['combat'=>$combat])!==null) return;
        $event=$this->policy->begin($occupation,$combat,$occupation->fleet,true,true);
        if($event!==null) {
            $this->manager->save($event);$this->manager->lock($occupation);$this->scheduler->scheduleLocked($occupation);
        }
    }
    public function failAfterBattleLocked(PlanetOccupation $occupation,string $reason): void
    {
        $event=$this->policy->finish($occupation,$reason);if($event!==null) $this->manager->save($event);
    }
    public function colonyLimit(PlayerUniverse $membership): int
    {
        $config=$this->catalog->forUniverse($membership->universe);$effect=$config['catalog']['technologies']['colonial_expansion']['effect']??null;
        if(!is_array($effect)||($effect['type']??null)!=='planet_limit') return (int)$config['limits']['max_planets'];
        $levels=$this->repo->levelsByTier(PlayerTechnology::class,['playerUniverse'=>$membership])['colonial_expansion']??[];
        return GameCatalog::colonyLimit($levels,$effect,(int)$config['limits']['max_planets']);
    }

    /** Le résolveur a validé la victoire et verrouillé les conquérants et la planète. */
    public function beginAfterVictoryLocked(CombatEvent $combat,Fleet $fleet): ?PlanetOccupation
    {
        if(!$this->enabled) throw new \DomainException('Les occupations JcE sont désactivées.');
        $processed=$this->repo->one(OccupationEvent::class,['combat'=>$combat]);
        if($processed!==null) return $processed->occupation;
        if(!$this->canOccupy($fleet->playerUniverse) || !$this->policy->isConquerable($combat->planet,$fleet->playerUniverse)) return null;
        $existing=$this->activeFor($combat->planet);
        if($existing!==null && ($combat->combatSnapshot['occupation']['id']??null)!==$existing->id) return null;
        $colonyShips=$this->repo->all(FleetShip::class,['fleet'=>$fleet,'slug'=>'colony_ship']);
        if(array_sum(array_map(static fn(FleetShip $ship): int=>$ship->quantity,$colonyShips))<1) return null;
        $membership=$fleet->playerUniverse;
        if(count($this->repo->planets($membership->universe,$membership))+$this->reservedSlots($membership)>=$this->colonyLimit($membership)) return null;
        $occupation=$this->repo->one(PlanetOccupation::class,['planet'=>$combat->planet]);
        if($occupation===null) { $occupation=new PlanetOccupation();$occupation->planet=$combat->planet; }
        $event=$this->policy->begin($occupation,$combat,$fleet,true,true);
        $this->manager->save($occupation);
        if($event!==null) $this->manager->save($event);
        $fleet->status='occupying';$fleet->departureAt=$occupation->startedAt;$fleet->arrivalAt=$occupation->captureAt;
        $fleet->dispatchToken=$occupation->dispatchToken;$fleet->orbitDurationSeconds=OccupationPolicy::DURATION_SECONDS;
        $this->manager->flush();
        $this->manager->lock($occupation);$this->scheduler->scheduleLocked($occupation);
        return $occupation;
    }

    /** La conversion J30 libère les réservations ; les flottes sont rappelées par son coordinateur. */
    public function cancelForConversionLocked(PlayerUniverse $membership): array
    {
        $ids=[];
        foreach($this->repo->all(PlanetOccupation::class,['occupant'=>$membership,'status'=>'active']) as $occupation) {
            $this->manager->lock($occupation);
            $event=$this->policy->finish($occupation,'occupant_unavailable');
            if($event!==null) { $this->manager->save($event);$ids[]=$occupation->id; }
        }
        return $ids;
    }
}
