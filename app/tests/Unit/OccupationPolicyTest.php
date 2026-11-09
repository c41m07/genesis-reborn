<?php
namespace App\Tests\Unit;

use App\Entity\{CombatEvent,Fleet,Galaxy,Planet,PlanetOccupation,Player,PlayerUniverse,SolarSystem,Universe};
use App\Service\Combat\OccupationPolicy;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Clock\MockClock;

final class OccupationPolicyTest extends TestCase
{
    private MockClock $clock;
    private OccupationPolicy $policy;
    private PlanetOccupation $occupation;
    private Fleet $fleet;
    private CombatEvent $combat;

    protected function setUp(): void
    {
        $this->clock=new MockClock('2026-11-08T12:00:00Z');$this->policy=new OccupationPolicy($this->clock);
        $universe=new Universe();$universe->id=1;
        $member=new PlayerUniverse();$member->id=1;$member->universe=$universe;$member->player=new Player();
        $galaxy=new Galaxy();$galaxy->universe=$universe;$system=new SolarSystem();$system->galaxy=$galaxy;
        $planet=new Planet();$planet->id=1;$planet->solarSystem=$system;$planet->npcPlanet=true;
        $this->fleet=new Fleet();$this->fleet->id=1;$this->fleet->playerUniverse=$member;$this->fleet->destination=$planet;$this->fleet->mission='conquer';
        $this->combat=new CombatEvent();$this->combat->id=1;$this->combat->planet=$planet;
        $this->occupation=new PlanetOccupation();$this->occupation->planet=$planet;
    }

    public function testExactTwentyFourHoursAndNoImmediateOwnershipTransfer(): void
    {
        $event=$this->policy->begin($this->occupation,$this->combat,$this->fleet,true,true);
        self::assertSame('started',$event->action);self::assertTrue($this->occupation->reservesSlot());
        self::assertSame(86400,$this->occupation->captureAt->getTimestamp()-$this->occupation->startedAt->getTimestamp());
        self::assertNull($this->occupation->planet->owner);
        $this->clock->sleep(86399);self::assertSame('waiting',$this->decision());
        $this->clock->sleep(1);self::assertSame('ready',$this->decision());
        $token=$this->occupation->dispatchToken;$event=$this->policy->finish($this->occupation,'ready');
        self::assertSame('captured',$event->action);self::assertFalse($this->occupation->reservesSlot());
        self::assertNotSame($token,$this->occupation->dispatchToken);
        self::assertNull($this->occupation->planet->owner);
        self::assertNull($this->policy->finish($this->occupation,'ready'));
    }

    public function testSameCombatCannotRestartTheTimerEvenAfterFinishing(): void
    {
        $this->policy->begin($this->occupation,$this->combat,$this->fleet,true,true);$token=$this->occupation->dispatchToken;$deadline=$this->occupation->captureAt;
        $this->clock->sleep(120);self::assertNull($this->policy->begin($this->occupation,$this->combat,$this->fleet,true,true));
        self::assertSame($token,$this->occupation->dispatchToken);self::assertSame($deadline,$this->occupation->captureAt);
        $this->policy->finish($this->occupation,'abandoned');
        self::assertNull($this->policy->begin($this->occupation,$this->combat,$this->fleet,true,true));
        self::assertSame('failed',$this->occupation->status);
    }

    public function testDefendingVictoryAndReinforcementDoNotResetExistingTime(): void
    {
        $this->policy->begin($this->occupation,$this->combat,$this->fleet,true,true);$deadline=$this->occupation->captureAt;$token=$this->occupation->dispatchToken;
        $this->clock->sleep(86400);self::assertSame('combat_pending',$this->decision(combatOpen:true));
        $combat=clone $this->combat;$combat->id=2;
        $event=$this->policy->begin($this->occupation,$combat,$this->fleet,true,false);
        self::assertSame('retained',$event->action);self::assertSame($deadline,$this->occupation->captureAt);self::assertSame($token,$this->occupation->dispatchToken);
        self::assertSame('ready',$this->decision());
    }

    public function testNewOccupantRestartsFullDurationAndHistoryPreservesPreviousHolder(): void
    {
        $this->policy->begin($this->occupation,$this->combat,$this->fleet,true,true);$oldToken=$this->occupation->dispatchToken;
        $this->clock->sleep(86399);$fleet=clone $this->fleet;$fleet->id=2;$fleet->playerUniverse=clone $this->fleet->playerUniverse;$fleet->playerUniverse->id=2;
        $combat=clone $this->combat;$combat->id=2;
        $event=$this->policy->begin($this->occupation,$combat,$fleet,true,true);
        self::assertSame('replaced',$event->action);self::assertSame(1,$event->snapshot['before']['occupant_id']);self::assertSame(2,$event->snapshot['after']['occupant_id']);
        self::assertSame(86400,$this->occupation->captureAt->getTimestamp()-$this->clock->now()->getTimestamp());
        self::assertNotSame($oldToken,$this->occupation->dispatchToken);self::assertNull($this->occupation->planet->owner);
    }

    public function testHumanAndSimplyInactiveEmpiresAreNotConquerable(): void
    {
        $owner=clone $this->fleet->playerUniverse;$owner->id=2;$owner->lastActivityAt=$this->clock->now()->modify('-20 days');
        $this->occupation->planet->owner=$owner;
        self::assertFalse($this->policy->isConquerable($this->occupation->planet,$this->fleet->playerUniverse));
        $owner->forgottenAt=$this->clock->now();$owner->player=null;
        self::assertTrue($this->policy->isConquerable($this->occupation->planet,$this->fleet->playerUniverse));
    }

    public function testOtherUniverseAndOrdinaryEmptyPlanetAreExcluded(): void
    {
        $this->occupation->planet->npcPlanet=false;
        self::assertFalse($this->policy->isConquerable($this->occupation->planet,$this->fleet->playerUniverse));
        $this->occupation->planet->npcPlanet=true;$this->occupation->planet->solarSystem->galaxy->universe=clone $this->fleet->playerUniverse->universe;
        $this->occupation->planet->solarSystem->galaxy->universe->id=2;
        self::assertFalse($this->policy->isConquerable($this->occupation->planet,$this->fleet->playerUniverse));
    }

    public function testColonyShipAndCapacityAreRequiredBeforeAnyNewReservation(): void
    {
        try { $this->policy->begin($this->occupation,$this->combat,$this->fleet,false,true);self::fail('Vaisseau absent.'); }
        catch(\DomainException) { self::assertFalse(isset($this->occupation->occupant)); }
        $this->expectException(\DomainException::class);$this->policy->begin($this->occupation,$this->combat,$this->fleet,true,false);
    }

    public function testLossOfFleetShipOrCapacityEndsReservationWithoutClaimingPlanet(): void
    {
        foreach(['abandoned'=>[false,true,true],'colony_ship_destroyed'=>[true,false,true],'planet_limit'=>[true,true,false]] as $reason=>$facts) {
            $occupation=clone $this->occupation;$this->policy->begin($occupation,$this->combat,$this->fleet,true,true);
            $decision=$this->policy->decision($occupation,false,...$facts);self::assertSame($reason,$decision);
            $event=$this->policy->finish($occupation,$decision);self::assertSame('failed',$event->action);self::assertFalse($occupation->reservesSlot());
            self::assertNull($occupation->planet->owner);
        }
    }

    public function testChangedOwnerOrUnavailableOccupantPreventsCapture(): void
    {
        $this->policy->begin($this->occupation,$this->combat,$this->fleet,true,true);
        $this->occupation->planet->owner=clone $this->fleet->playerUniverse;$this->occupation->planet->owner->id=2;
        self::assertSame('target_claimed',$this->decision());$this->occupation->planet->owner=null;
        $this->occupation->occupant->forgottenAt=$this->clock->now();
        self::assertSame('occupant_unavailable',$this->decision());
    }

    public function testEarlyCaptureAndPendingCombatCannotBeForcedThroughFinish(): void
    {
        $this->policy->begin($this->occupation,$this->combat,$this->fleet,true,true);
        try { $this->policy->finish($this->occupation,'ready');self::fail('Capture prématurée.'); }
        catch(\DomainException) { self::assertTrue($this->occupation->reservesSlot()); }
        $this->expectException(\DomainException::class);$this->policy->finish($this->occupation,'combat_pending');
    }

    private function decision(bool $combatOpen=false): string
    {
        return $this->policy->decision($this->occupation,$combatOpen,true,true,true);
    }
}
