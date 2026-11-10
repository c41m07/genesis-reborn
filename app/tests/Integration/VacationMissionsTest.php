<?php
namespace App\Tests\Integration;

use App\Entity\{AllianceContract, CombatEvent, EspionageReport, Fleet, FleetShip, Planet, PlanetResource, Player, PlayerBoost, PlayerDiscovery, TransportReport, Universe, VacationPeriod};
use App\Manager\GameManager;
use App\Repository\GameRepository;
use App\Service\Colony\{ActionGuard, ResourceService};
use App\Service\Combat\CombatEventService;
use App\Service\Fleet\{FleetService, TransportService};
use App\Service\Player\{EmpireActivityService, PlayerProtection, RegistrationService, VacationEconomyService, VacationPolicy};
use App\Service\Queue\QueueService;
use App\Service\Universe\{UniverseImporter, UniverseService};
use App\Validator\DTO\RegistrationData;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Clock\MockClock;

final class VacationMissionsTest extends KernelTestCase
{
    private EntityManagerInterface $em;
    private MockClock $clock;
    private Player $visitor;
    private Player $host;
    private Planet $origin;
    private Planet $target;

    protected function setUp(): void
    {
        self::bootKernel();
        $this->em = self::getContainer()->get(EntityManagerInterface::class);
        $params = $this->em->getConnection()->getParams();
        if (($params['host'] ?? '') !== 'database-test' || ($params['dbname'] ?? '') !== 'genesis_v2_test') throw new \RuntimeException('Base de test non isolée.');
        $schema = new SchemaTool($this->em);
        $metadata = $this->em->getMetadataFactory()->getAllMetadata();
        $schema->dropSchema($metadata);
        $schema->createSchema($metadata);
        $this->clock = new MockClock('2026-11-07T12:00:00Z');
        self::getContainer()->set('clock', $this->clock);
        self::getContainer()->get(UniverseImporter::class)->import();
        [$this->visitor, $this->origin] = $this->player('VacationVisitor');
        [$this->host, $this->target] = $this->player('VacationHost');
        foreach ([[$this->visitor, $this->target], [$this->host, $this->origin]] as [$player, $planet]) {
            if ($this->em->getRepository(PlayerDiscovery::class)->findOneBy(['playerUniverse'=>$player->currentPlayerUniverse, 'solarSystem'=>$planet->solarSystem])) continue;
            $discovery = new PlayerDiscovery();$discovery->playerUniverse = $player->currentPlayerUniverse;$discovery->solarSystem = $planet->solarSystem;$this->em->persist($discovery);
        }
        $this->em->flush();
    }

    #[DataProvider('missions')]
    public function testNoNewMissionCanBeQuotedDuringVacation(string $mission): void
    {
        $this->vacation($this->visitor);
        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('pendant les vacances');
        self::getContainer()->get(FleetService::class)->quote($this->visitor->currentPlayerUniverse, $this->origin, $mission, $this->target->id, ['fighter'=>1], 1);
    }

    public static function missions(): array
    {
        return array_map(static fn (string $mission): array => [$mission], ['explore','colonize','transport','attack','conquer','defend','orbit','spy']);
    }

    #[DataProvider('deployedFleets')]
    public function testEveryOwnDeployedFleetBlocksActivation(string $mission, string $status): void
    {
        $this->transit($mission, $status);
        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('Toutes vos flottes doivent être rentrées');
        $this->economy()->start($this->visitor->currentPlayerUniverse);
    }

    public static function deployedFleets(): array
    {
        return [['explore','traveling'],['colonize','traveling'],['transport','returning'],['spy','traveling'],['defend','defending'],['orbit','orbiting'],['orbit','orbit_pending'],['attack','attacking']];
    }

    public function testVacationIsShownAsProtectionAndExpiresAtItsDeadline(): void
    {
        $combat = self::getContainer()->get(CombatEventService::class);
        self::assertTrue($combat->canAttack($this->visitor->currentPlayerUniverse, $this->target));
        $period = $this->vacation($this->host);
        $status = self::getContainer()->get(PlayerProtection::class)->status($this->host->currentPlayerUniverse, 1000000);
        self::assertTrue($status['active']);
        self::assertSame('Mode vacances', $status['reason']);
        self::assertSame($period->scheduledEndAt->format(DATE_ATOM), $status['until']);
        self::assertFalse($combat->canAttack($this->visitor->currentPlayerUniverse, $this->target));
        self::assertFalse($combat->canDefend($this->visitor->currentPlayerUniverse, $this->target));
        $this->clock->sleep(90 * 86400);
        self::assertTrue($combat->canAttack($this->visitor->currentPlayerUniverse, $this->target));
        self::assertFalse(self::getContainer()->get(PlayerProtection::class)->status($this->host->currentPlayerUniverse, 1000000)['active']);
    }

    public function testNewTransportsAndEspionageCannotTargetAnEmpireOnVacation(): void
    {
        $transports = self::getContainer()->get(TransportService::class);
        self::assertTrue($transports->canReceive($this->visitor->currentPlayerUniverse, $this->target));
        $this->vacation($this->host);
        self::assertFalse($transports->canReceive($this->visitor->currentPlayerUniverse, $this->target));
        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('protégé par le mode vacances');
        self::getContainer()->get(FleetService::class)->quote($this->visitor->currentPlayerUniverse, $this->origin, 'spy', $this->target->id, ['probe'=>1], 1);
    }

    #[DataProvider('militaryArrivals')]
    public function testMilitaryArrivalsReturnWithoutCombatOrLosingTheirShips(string $mission): void
    {
        if ($mission === 'orbit') $this->alliance();
        $slug = $mission === 'spy' ? 'probe' : 'fighter';
        $fleets = self::getContainer()->get(FleetService::class);
        $fleets->send($this->visitor->currentPlayerUniverse, $this->origin, $mission, $this->target->id, [$slug=>1], 1, [], str_repeat('a',32), $mission === 'orbit' ? 1 : 0);
        $fleet = $this->em->getRepository(Fleet::class)->findOneBy(['playerUniverse'=>$this->visitor->currentPlayerUniverse, 'status'=>'traveling']);
        $due = $fleet->arrivalAt;
        $token = $fleet->dispatchToken;
        $this->vacation($this->host);
        $this->clock->sleep($fleet->travelSeconds + 5);
        $fleets->arrive($fleet->id, $token);
        self::assertSame('returning', $fleet->status);
        self::assertEquals($due->modify('+'.$fleet->travelSeconds.' seconds'), $fleet->arrivalAt);
        self::assertSame(0, $this->em->getRepository(CombatEvent::class)->count());
        self::assertSame(0, $this->em->getRepository(EspionageReport::class)->count());
        self::assertSame(1, $this->em->getRepository(FleetShip::class)->findOneBy(['fleet'=>$fleet,'slug'=>$slug])->quantity);
        $returnToken = $fleet->dispatchToken;
        $fleets->arrive($fleet->id, $token);
        self::assertSame($returnToken, $fleet->dispatchToken);
        $this->clock->sleep(max(0, $fleet->arrivalAt->getTimestamp()-$this->clock->now()->getTimestamp()));
        $fleets->arrive($fleet->id, $returnToken);
        self::assertSame('completed', $fleet->status);
        self::assertSame($slug === 'fighter' ? 5 : 1, $this->idleShip($this->visitor, $slug)->quantity);
    }

    public static function militaryArrivals(): array { return [['attack'],['spy'],['defend'],['orbit']]; }

    public function testTransportAlreadySentIsRefusedAndItsCargoReturns(): void
    {
        foreach($this->em->getRepository(\App\Entity\PlanetResource::class)->findBy(['planet'=>$this->origin,'resourceType'=>'metal']) as $row)$row->currentStock=1000;
        $this->em->flush();
        $fleets = self::getContainer()->get(FleetService::class);
        $fleets->send($this->visitor->currentPlayerUniverse, $this->origin, 'transport', $this->target->id, ['transport'=>1], 1, ['metal'=>100], str_repeat('a',32));
        $fleet = $this->em->getRepository(Fleet::class)->findOneBy(['status'=>'traveling']);
        $this->economy()->start($this->host->currentPlayerUniverse);
        $this->clock->sleep($fleet->travelSeconds);
        $fleets->arrive($fleet->id, $fleet->dispatchToken);
        $report = $this->em->getRepository(TransportReport::class)->findOneBy(['fleet'=>$fleet]);
        self::assertSame('refused', $report->status);
        self::assertSame(100, $fleet->cargo['metal']);
        self::assertSame(100, $report->snapshot['returned']['metal']);
        self::assertSame('returning', $fleet->status);
        $this->clock->sleep($fleet->travelSeconds);
        $fleets->arrive($fleet->id, $fleet->dispatchToken);
        self::assertSame('completed', $fleet->status);
        self::assertEquals(100, $report->snapshot['returned_deposited']['metal']);
        self::assertSame(1, $this->idleShip($this->visitor,'transport')->quantity);
    }

    #[DataProvider('acceptedStations')]
    public function testPendingOrbitIsReturnedAtActivationAndAnAcceptedStationKeepsItsDeadline(string $mission,string $status): void
    {
        $this->alliance();
        $pending = $this->transit('orbit','orbit_pending');
        $station = $this->transit($mission,$status);
        $stationEnd = $station->arrivalAt;
        $this->economy()->start($this->host->currentPlayerUniverse);
        self::assertSame('returning', $pending->status);
        self::assertSame($status, $station->status);
        self::assertEquals($stationEnd, $station->arrivalAt);
        self::assertFalse(self::getContainer()->get(CombatEventService::class)->canOrbit($this->visitor->currentPlayerUniverse, $this->target));
        $this->clock->sleep($stationEnd->getTimestamp()-$this->clock->now()->getTimestamp());
        self::getContainer()->get(FleetService::class)->arrive($station->id,$station->dispatchToken);
        self::assertSame('returning',$station->status);
    }

    public static function acceptedStations(): array { return [['defend','defending'],['orbit','orbiting']]; }

    public function testSendingWhileOnVacationDoesNotDebitFuelOrCreateAFleet(): void
    {
        $this->vacation($this->visitor);
        $hydrogen = $this->em->getRepository(PlanetResource::class)->findOneBy(['planet'=>$this->origin,'resourceType'=>'hydrogen']);
        $before = $hydrogen->currentStock;
        try {
            self::getContainer()->get(FleetService::class)->send($this->visitor->currentPlayerUniverse,$this->origin,'transport',$this->target->id,['transport'=>1],1,['metal'=>100],str_repeat('a',32));
            self::fail('Un départ pendant les vacances doit être refusé.');
        } catch (\DomainException $error) { self::assertStringContainsString('vacances',$error->getMessage()); }
        self::assertEqualsWithDelta($before,(float)$this->em->getConnection()->fetchOne('SELECT current_stock FROM planet_resource WHERE id = ?',[$hydrogen->id]),0.000001);
        self::assertSame(0,(int)$this->em->getConnection()->fetchOne("SELECT COUNT(*) FROM fleet WHERE status = 'traveling'"));
        self::assertSame(0,(int)$this->em->getConnection()->fetchOne('SELECT COUNT(*) FROM action_receipt'));
    }

    public function testAnOrbitCannotBeAcceptedDuringVacation(): void
    {
        $this->alliance();
        $pending = $this->transit('orbit','orbit_pending');
        $this->vacation($this->host);
        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('vacances');
        self::getContainer()->get(FleetService::class)->decideOrbit($this->host->currentPlayerUniverse,$pending->id,true);
    }

    public function testFleetBoostIsRejectedBeforeConsumingTheBoost(): void
    {
        $fleet = $this->transit('transport','returning');
        $originalArrival = $fleet->arrivalAt;
        $this->vacation($this->visitor);
        $boost = new PlayerBoost();$boost->playerUniverse=$this->visitor->currentPlayerUniverse;$boost->sourceType='event';$boost->sourceKey='vacation-fleet-boost';$boost->earnedAt=$this->clock->now();
        $this->em->persist($boost);$this->em->flush();
        try {
            self::getContainer()->get(\App\Service\Boost\BoostUseService::class)->useOnFleet($this->visitor->currentPlayerUniverse,$fleet->id,str_repeat('a',32));
            self::fail('Une flotte ne peut pas être accélérée pendant les vacances.');
        } catch (\DomainException $error) { self::assertStringContainsString('vacances',$error->getMessage()); }
        self::assertSame('available',$this->em->getConnection()->fetchOne('SELECT status FROM player_boost WHERE id = ?',[$boost->id]));
        self::assertSame($originalArrival->format('Y-m-d H:i:s'),$this->em->getConnection()->fetchOne('SELECT arrival_at FROM fleet WHERE id = ?',[$fleet->id]));
    }

    private function player(string $name): array
    {
        $data=new RegistrationData();$data->email=strtolower($name).'@example.test';$data->username=$name;$data->plainPassword='A-test-password-2026';
        $player=self::getContainer()->get(RegistrationService::class)->register($data);
        $universe=$this->em->getRepository(Universe::class)->findOneBy(['slug'=>'omega-1']);
        self::getContainer()->get(UniverseService::class)->select($player,$universe->id);
        $player->preferences=['transportReception'=>'all'];
        $player->currentPlayerUniverse->joinedAt=$this->clock->now()->modify('-40 days');
        $planet=$this->em->getRepository(Planet::class)->findOneBy(['owner'=>$player->currentPlayerUniverse]);
        $idle=new Fleet();$idle->playerUniverse=$player->currentPlayerUniverse;$idle->homePlanet=$planet;$this->em->persist($idle);
        foreach(['fighter'=>5,'probe'=>1,'transport'=>1,'colony_ship'=>1] as $slug=>$quantity){$ship=new FleetShip();$ship->fleet=$idle;$ship->slug=$slug;$ship->quantity=$quantity;$this->em->persist($ship);}
        $this->em->getRepository(PlanetResource::class)->findOneBy(['planet'=>$planet,'resourceType'=>'hydrogen'])->currentStock=100000;
        $this->em->flush();return [$player,$planet];
    }

    private function vacation(Player $player): VacationPeriod
    {
        $period=(new VacationPolicy($this->clock))->plan($player->currentPlayerUniverse);
        $this->em->persist($period);$this->em->flush();return $period;
    }

    private function transit(string $mission,string $status): Fleet
    {
        $fleet=new Fleet();$fleet->playerUniverse=$this->visitor->currentPlayerUniverse;$fleet->homePlanet=$this->origin;$fleet->destination=$this->target;$fleet->mission=$mission;$fleet->status=$status;$fleet->travelSeconds=60;$fleet->orbitDurationSeconds=3600;$fleet->orbitFuel=10;$fleet->reservedFuel=100;$fleet->cargo=['hydrogen'=>100];$fleet->departureAt=$this->clock->now();$fleet->arrivalAt=$status==='orbit_pending'?null:$this->clock->now()->modify('+3600 seconds');$fleet->dispatchToken='vacation-mission-fixture';
        $this->em->persist($fleet);$this->idleShip($this->visitor,'fighter')->quantity--;
        $ship=new FleetShip();$ship->fleet=$fleet;$ship->slug='fighter';$ship->quantity=1;$this->em->persist($ship);$this->em->flush();return $fleet;
    }

    private function idleShip(Player $player,string $slug): FleetShip
    {
        $idle=$this->em->getRepository(Fleet::class)->findOneBy(['playerUniverse'=>$player->currentPlayerUniverse,'status'=>'idle']);
        return $this->em->getRepository(FleetShip::class)->findOneBy(['fleet'=>$idle,'slug'=>$slug]);
    }

    private function alliance(): void
    {
        $contract=new AllianceContract();$contract->playerA=$this->visitor->currentPlayerUniverse;$contract->playerB=$this->host->currentPlayerUniverse;$contract->proposer=$contract->playerA;$contract->status='accepted';$this->em->persist($contract);$this->em->flush();
    }

    private function economy(): VacationEconomyService
    {
        return new VacationEconomyService(self::getContainer()->get(GameManager::class),self::getContainer()->get(GameRepository::class),self::getContainer()->get(ActionGuard::class),self::getContainer()->get(ResourceService::class),self::getContainer()->get(QueueService::class),new VacationPolicy($this->clock),self::getContainer()->get(EmpireActivityService::class),self::getContainer()->get(FleetService::class),$this->clock,true);
    }
}
