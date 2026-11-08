<?php
namespace App\Service\Combat;

use App\Entity\{CombatEvent,Fleet,OccupationEvent,Planet,PlanetOccupation,PlayerUniverse};
use Psr\Clock\ClockInterface;
use Symfony\Component\Uid\Uuid;

/** Transitions en mémoire ; le futur coordinateur vérifiera combat, forces et capacité sous verrou. */
final class OccupationPolicy
{
    public const DURATION_SECONDS=24*3600;
    public const FAILURE_REASONS=['abandoned','colony_ship_destroyed','planet_limit','occupant_unavailable','target_claimed'];

    public function __construct(private ClockInterface $clock) {}

    public function isConquerable(Planet $planet,PlayerUniverse $attacker): bool
    {
        return $attacker->isPlayerControlled() && $planet->solarSystem->galaxy->universe->id===$attacker->universe->id
            && (($planet->owner===null && $planet->npcPlanet) || $planet->owner?->isForgotten());
    }

    /** À appeler uniquement après une victoire validée ; les booléens proviennent de l'état verrouillé. */
    public function begin(PlanetOccupation $occupation,CombatEvent $combat,Fleet $fleet,bool $hasColonyShip,bool $hasCapacity): ?OccupationEvent
    {
        if(isset($occupation->originCombat) && $this->same($occupation->originCombat,$combat)) return null;
        if(!$this->same($occupation->planet,$combat->planet) || !$this->same($occupation->planet,$fleet->destination)
            || $fleet->mission!=='conquer' || !$this->isConquerable($occupation->planet,$fleet->playerUniverse)) {
            throw new \DomainException('Cette victoire ne permet pas d’occuper cette planète JcE.');
        }
        if(!$hasColonyShip) throw new \DomainException('Un vaisseau de colonisation survivant est nécessaire.');
        if($occupation->reservesSlot() && isset($occupation->occupant) && $this->same($occupation->occupant,$fleet->playerUniverse)) {
            $event=$this->event($occupation,'retained',$this->snapshot($occupation));$event->combat=$combat;return $event;
        }
        if(!$hasCapacity) throw new \DomainException('Aucune place de colonie disponible pour cette occupation.');
        $before=isset($occupation->occupant)?$this->snapshot($occupation):null;
        $action=$before!==null && $occupation->reservesSlot()?'replaced':'started';
        $now=$this->clock->now()->setTimezone(new \DateTimeZone('UTC'));
        $occupation->occupant=$fleet->playerUniverse;$occupation->fleet=$fleet;$occupation->originCombat=$combat;
        $occupation->startedAt=$now;$occupation->captureAt=$now->modify('+'.self::DURATION_SECONDS.' seconds');
        $occupation->status='active';$occupation->endedAt=null;$occupation->endReason=null;
        $occupation->dispatchToken=Uuid::v4()->toRfc4122();
        $event=$this->event($occupation,$action,$before);$event->combat=$combat;return $event;
    }

    public function decision(PlanetOccupation $occupation,bool $combatOpen,bool $fleetPresent,bool $hasColonyShip,bool $hasCapacity,bool $hostileForcesPresent=false,bool $hostileReturnPending=false): string
    {
        if(!$occupation->reservesSlot()) return 'inactive';
        if(!$occupation->occupant->isPlayerControlled()) return 'occupant_unavailable';
        if(!$this->isConquerable($occupation->planet,$occupation->occupant)) return 'target_claimed';
        if($combatOpen) return 'combat_pending';
        if(!$fleetPresent) return 'abandoned';
        if(!$hasColonyShip) return 'colony_ship_destroyed';
        if(!$hasCapacity) return 'planet_limit';
        if($hostileForcesPresent) return 'hostile_forces';
        if($hostileReturnPending) return 'hostile_returns';
        return $this->clock->now()<$occupation->captureAt?'waiting':'ready';
    }

    /** La capture physique et la consommation du vaisseau seront coordonnées dans la même transaction. */
    public function finish(PlanetOccupation $occupation,string $decision): ?OccupationEvent
    {
        if(!$occupation->reservesSlot()) return null;
        if($decision!=='ready' && !in_array($decision,self::FAILURE_REASONS,true)) throw new \DomainException('L’occupation ne peut pas encore se terminer.');
        if($decision==='ready' && $this->clock->now()<$occupation->captureAt) throw new \DomainException('Les 24 heures d’occupation ne sont pas écoulées.');
        $before=$this->snapshot($occupation);
        $occupation->status=$decision==='ready'?'captured':'failed';$occupation->endReason=$decision==='ready'?null:$decision;
        $occupation->endedAt=$this->clock->now()->setTimezone(new \DateTimeZone('UTC'));
        $occupation->dispatchToken=Uuid::v4()->toRfc4122();
        return $this->event($occupation,$occupation->status,$before);
    }

    private function same(?object $first,?object $second): bool
    {
        return $first!==null && $second!==null && ($first===$second || ($first->id!==null && $first->id===$second->id));
    }
    private function snapshot(PlanetOccupation $occupation): array
    {
        return ['occupant_id'=>$occupation->occupant->id,'fleet_id'=>$occupation->fleet->id,'combat_id'=>$occupation->originCombat->id,
            'status'=>$occupation->status,'started_at'=>$occupation->startedAt->format(DATE_ATOM),
            'capture_at'=>$occupation->captureAt->format(DATE_ATOM),'end_reason'=>$occupation->endReason,'token'=>$occupation->dispatchToken];
    }
    private function event(PlanetOccupation $occupation,string $action,?array $before): OccupationEvent
    {
        $event=new OccupationEvent();$event->occupation=$occupation;$event->action=$action;
        $event->occurredAt=$this->clock->now()->setTimezone(new \DateTimeZone('UTC'));
        $event->snapshot=['before'=>$before,'after'=>$this->snapshot($occupation)];return $event;
    }
}
