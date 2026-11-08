<?php
namespace App\Tests\Integration;

use App\Entity\{AllianceContract,CombatEvent,Fleet,FleetShip,Galaxy,OccupationEvent,Planet,PlanetDefense,PlanetNpcShip,PlanetOccupation,Player,PlayerTechnology,PlayerUniverse,SolarSystem,Universe};
use App\Repository\GameRepository;
use App\Service\Combat\{OccupationPolicy,OccupationReadiness};
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Clock\MockClock;

final class OccupationReadinessTest extends KernelTestCase
{
    private EntityManagerInterface $em;
    private MockClock $clock;
    private PlanetOccupation $occupation;
    private FleetShip $colony;

    protected function setUp(): void
    {
        self::bootKernel();$this->em=self::getContainer()->get(EntityManagerInterface::class);$params=$this->em->getConnection()->getParams();
        if(($params['host']??'')!=='database-test'||($params['dbname']??'')!=='genesis_v2_test') throw new \RuntimeException('Base de test non isolée.');
        $schema=new SchemaTool($this->em);$metadata=$this->em->getMetadataFactory()->getAllMetadata();$schema->dropSchema($metadata);$schema->createSchema($metadata);
        $this->clock=new MockClock('2026-11-08T12:00:00Z');self::getContainer()->set('clock',$this->clock);
        $universe=new Universe();$universe->slug='omega-1';$universe->name='Test';
        $galaxy=new Galaxy();$galaxy->universe=$universe;$galaxy->name='Test';
        $system=new SolarSystem();$system->galaxy=$galaxy;$system->name='Test';$system->coordX=0;$system->coordY=0;
        foreach([$universe,$galaxy,$system] as $row) $this->em->persist($row);$this->em->flush();
        $member=$this->member('holder',$universe);
        $home=new Planet();$home->solarSystem=$system;$home->owner=$member;$home->name='Home';$home->position=1;
        $planet=new Planet();$planet->solarSystem=$system;$planet->name='PNJ';$planet->position=2;$planet->npcPlanet=true;
        $research=new PlayerTechnology();$research->playerUniverse=$member;$research->slug='colonial_expansion';$research->level=1;
        foreach([$home,$planet,$research] as $row) $this->em->persist($row);$this->em->flush();
        $fleet=$this->fleet($member,$home,$planet,'occupying');$this->colony=$this->ship($fleet,'colony_ship');
        $combat=new CombatEvent();$combat->planet=$planet;$combat->status='resolved';$combat->startedAt=$this->clock->now();$combat->resolveAt=$this->clock->now();$combat->seed=42;$this->em->persist($combat);$this->em->flush();
        $this->occupation=new PlanetOccupation();$this->occupation->planet=$planet;
        $event=self::getContainer()->get(OccupationPolicy::class)->begin($this->occupation,$combat,$fleet,true,true);
        $this->em->persist($this->occupation);$this->em->persist($event);$this->em->flush();$this->clock->sleep(86400);
    }

    public function testClearTargetIsReadyWithoutWritingOrTransferringAnything(): void
    {
        $before=$this->em->getConnection()->fetchAllAssociative('SELECT * FROM planet_occupation');
        $result=$this->inspect();self::assertSame('ready',$result['decision']);self::assertSame([$this->colony->id],$result['colony_ship_ids']);
        self::assertNull($this->occupation->planet->owner);self::assertSame(1,$this->colony->quantity);
        self::assertSame($before,$this->em->getConnection()->fetchAllAssociative('SELECT * FROM planet_occupation'));
        self::assertSame(1,$this->em->getRepository(OccupationEvent::class)->count([]));
    }

    public function testRemainingDefensesAndNpcShipsBlockWithoutResettingDeadline(): void
    {
        $deadline=$this->occupation->captureAt;$token=$this->occupation->dispatchToken;
        $defense=new PlanetDefense();$defense->planet=$this->occupation->planet;$defense->slug='laser_turret';$defense->quantity=1;$this->em->persist($defense);$this->em->flush();
        self::assertSame('hostile_forces',$this->inspect()['decision']);$defense->quantity=0;$this->em->flush();
        self::assertSame('ready',$this->inspect()['decision']);
        $ship=new PlanetNpcShip();$ship->planet=$this->occupation->planet;$ship->slug='fighter';$ship->quantity=1;$this->em->persist($ship);$this->em->flush();
        self::assertSame('hostile_forces',$this->inspect()['decision']);
        self::assertSame($deadline,$this->occupation->captureAt);self::assertSame($token,$this->occupation->dispatchToken);
    }

    public function testHostileGarrisonIsCountedWhileAlliedStationIsNotAnEnemy(): void
    {
        $member=$this->member('visitor',$this->occupation->occupant->universe);
        $fleet=$this->fleet($member,$this->occupation->fleet->homePlanet,$this->occupation->planet,'orbiting');$ship=$this->ship($fleet,'fighter');
        self::assertSame('hostile_forces',$this->inspect()['decision']);
        $alliance=new AllianceContract();$alliance->playerA=$this->occupation->occupant;$alliance->playerB=$member;$alliance->proposer=$this->occupation->occupant;$alliance->status='accepted';
        $this->em->persist($alliance);$this->em->flush();self::assertSame('ready',$this->inspect()['decision']);
        $alliance->status='ended';$fleet->status='idle';$fleet->homePlanet=$this->occupation->planet;$this->em->flush();
        self::assertSame('hostile_forces',$this->inspect()['decision']);$ship->quantity=0;$this->em->flush();self::assertSame('ready',$this->inspect()['decision']);
    }

    public function testPositiveEnemyReturnIsReportedButEmptyFleetDoesNotBlock(): void
    {
        $enemy=$this->member('enemy',$this->occupation->occupant->universe);$enemy->forgottenAt=$this->clock->now();$enemy->formerPlayer=$enemy->player;$enemy->player=null;
        $fleet=$this->fleet($enemy,$this->occupation->planet,$this->occupation->fleet->homePlanet,'returning');$ship=$this->ship($fleet,'fighter');
        $result=$this->inspect();self::assertSame('hostile_returns',$result['decision']);self::assertSame([$fleet->id],$result['pending_return_ids']);
        $ship->quantity=0;$this->em->flush();self::assertSame('ready',$this->inspect()['decision']);
    }

    public function testLossOfColonizerOrDepartureOfOwnFleetPreventsCapture(): void
    {
        $this->colony->quantity=0;$this->ship($this->occupation->fleet,'fighter');
        self::assertSame('colony_ship_destroyed',$this->inspect()['decision']);
        $this->occupation->fleet->status='returning';$this->em->flush();self::assertSame('abandoned',$this->inspect()['decision']);
    }

    public function testOpenCombatTakesPriorityAndEndingItDoesNotRestartTheTimer(): void
    {
        $event=new CombatEvent();$event->planet=$this->occupation->planet;$event->startedAt=$this->clock->now();$event->resolveAt=$this->clock->now()->modify('+60 seconds');$event->seed=42;
        $this->em->persist($event);$this->em->flush();$deadline=$this->occupation->captureAt;
        self::assertSame('combat_pending',$this->inspect()['decision']);$this->clock->sleep(60);$event->status='resolved';$this->em->flush();
        self::assertSame('ready',$this->inspect()['decision']);self::assertSame($deadline,$this->occupation->captureAt);
    }

    public function testHostileDecisionCannotBeUsedToCompleteState(): void
    {
        $this->expectException(\DomainException::class);self::getContainer()->get(OccupationPolicy::class)->finish($this->occupation,'hostile_forces');
    }

    private function inspect(): array
    {
        $readiness=new OccupationReadiness(self::getContainer()->get(GameRepository::class),self::getContainer()->get(OccupationPolicy::class),
            self::getContainer()->get(\App\Service\Combat\OccupationService::class),self::getContainer()->get(\App\Service\Player\RelationResolver::class));
        return $readiness->inspect($this->occupation);
    }
    private function member(string $name,Universe $universe): PlayerUniverse
    {
        $player=new Player();$player->email=$name.'@example.test';$player->username=$name;$player->password='unused';
        $member=new PlayerUniverse();$member->player=$player;$member->universe=$universe;$member->joinedAt=$this->clock->now();$member->lastActivityAt=$this->clock->now();
        $this->em->persist($player);$this->em->persist($member);$this->em->flush();return $member;
    }
    private function fleet(PlayerUniverse $member,Planet $home,Planet $destination,string $status): Fleet
    {
        $fleet=new Fleet();$fleet->playerUniverse=$member;$fleet->homePlanet=$home;$fleet->destination=$destination;$fleet->status=$status;$fleet->mission='conquer';
        $this->em->persist($fleet);$this->em->flush();return $fleet;
    }
    private function ship(Fleet $fleet,string $slug): FleetShip
    {
        $ship=new FleetShip();$ship->fleet=$fleet;$ship->slug=$slug;$ship->quantity=1;$this->em->persist($ship);$this->em->flush();return $ship;
    }
}
