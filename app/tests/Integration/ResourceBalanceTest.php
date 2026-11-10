<?php
namespace App\Tests\Integration;

use App\Entity\{GameEvent,Planet,PlanetBuilding,PlanetResource,Universe};
use App\Service\Catalog\GameCatalog;
use App\Service\Colony\ResourceService;
use App\Service\Player\RegistrationService;
use App\Service\Universe\{UniverseImporter,UniverseService};
use App\Validator\DTO\RegistrationData;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Clock\MockClock;

final class ResourceBalanceTest extends KernelTestCase
{
    private EntityManagerInterface $em;
    private MockClock $clock;

    protected function setUp(): void
    {
        self::bootKernel();
        $this->em=self::getContainer()->get(EntityManagerInterface::class);
        $params=$this->em->getConnection()->getParams();
        if(($params['host']??'')!=='database-test'||($params['dbname']??'')!=='genesis_v2_test') throw new \RuntimeException('Base de test non isolée.');
        $tool=new SchemaTool($this->em);$metadata=$this->em->getMetadataFactory()->getAllMetadata();
        $tool->dropSchema($metadata);$tool->createSchema($metadata);
        $this->clock=new MockClock('2026-11-02T11:00:00Z');
        self::getContainer()->set('clock',$this->clock);
        self::getContainer()->get(UniverseImporter::class)->import();
    }

    private function planet(): Planet
    {
        $data=new RegistrationData();$data->email='balance@example.test';$data->username='Balance';$data->plainPassword='A-test-password-2026';
        $player=self::getContainer()->get(RegistrationService::class)->register($data);
        $universe=$this->em->getRepository(Universe::class)->findOneBy(['slug'=>'omega-1']);
        self::getContainer()->get(UniverseService::class)->select($player,$universe->id);
        $planet=$this->em->getRepository(Planet::class)->findOneBy(['owner'=>$player->currentPlayerUniverse]);
        $this->position($planet,5);return $planet;
    }

    private function position(Planet $planet,int $position): void
    {
        if($planet->position===$position) return;
        $previous=$planet->position;
        $other=$this->em->getRepository(Planet::class)->findOneBy(['solarSystem'=>$planet->solarSystem,'position'=>$position]);
        $planet->position=0;$this->em->flush();
        if($other!==null) {$other->position=$previous;$this->em->flush();}
        $planet->position=$position;$this->em->flush();
    }

    private function rows(Planet $planet): array
    {
        $rows=[];foreach($this->em->getRepository(PlanetResource::class)->findBy(['planet'=>$planet]) as $row) $rows[$row->resourceType]=$row;
        return $rows;
    }

    public function testFutureHomeStocksAndReferenceHourlyProduction(): void
    {
        $planet=$this->planet();$resources=self::getContainer()->get(ResourceService::class);$rows=$this->rows($planet);
        foreach($rows as $row) {
            self::assertSame(10000.0,$row->currentStock);
            self::assertSame(10000.0,$resources->capacity($planet,$row->resourceType,[]));
            $row->currentStock=0;
        }
        $this->em->flush();
        $rates=['metal'=>40,'electricity'=>0,'antimatter'=>10,'hydrogen'=>80,'nano'=>20];
        foreach($resources->snapshot($planet) as $slug=>$row) self::assertEqualsWithDelta($rates[$slug],$row['rate']*3600,1e-8);
        $this->clock->sleep(3600);
        foreach($resources->snapshot($planet) as $slug=>$row) self::assertEqualsWithDelta($rates[$slug],$row['stock'],1e-8);
        // La consultation seule ne réécrit pas les lignes de ressources.
        foreach($rows as $row) {$this->em->refresh($row);self::assertSame(0.0,$row->currentStock);}
        $resources->initialize($planet);
        foreach($rows as $row) self::assertSame(0.0,$row->currentStock);
    }

    public function testOfflinePeriodUsesCurrentRatesWithoutAnActivationDate(): void
    {
        $planet=$this->planet();$resources=self::getContainer()->get(ResourceService::class);
        $rows=$this->rows($planet);
        foreach($rows as $row) $row->currentStock=0;
        $this->em->flush();$this->clock->sleep(7200);
        $expected=['metal'=>80,'electricity'=>0,'antimatter'=>20,'hydrogen'=>160,'nano'=>40];
        $snapshot=$resources->snapshot($planet);
        foreach($expected as $slug=>$stock) {
            self::assertEqualsWithDelta($stock,$snapshot[$slug]['stock'],1e-7,$slug);
            self::assertSame(10000.0,$snapshot[$slug]['capacity']);
        }
        $resources->materialize($planet);$this->em->flush();
        $resources->materialize($planet);
        foreach($expected as $slug=>$stock) self::assertEqualsWithDelta($stock,$rows[$slug]->currentStock,1e-7);
        $this->clock->sleep(3600);
        self::assertEqualsWithDelta(120,$resources->snapshot($planet)['metal']['stock'],1e-7);
    }

    public function testSurplusRemainsSpendableAndCannotBeErasedByRefundsOrDeposits(): void
    {
        $planet=$this->planet();$resources=self::getContainer()->get(ResourceService::class);
        foreach($this->rows($planet) as $row) $row->currentStock=25000;
        $this->em->flush();$this->clock->sleep(7200);
        $snapshot=$resources->snapshot($planet);
        self::assertEqualsWithDelta(25000,$snapshot['metal']['stock'],1e-7);
        foreach($snapshot as $row) {self::assertGreaterThan(10000,$row['stock']);self::assertSame(0.0,$row['rate']);}
        $rows=$resources->materialize($planet);$this->em->flush();
        $resources->consume($planet,['metal'=>1000]);
        self::assertEqualsWithDelta(24000,$rows['metal']->currentStock,1e-7);
        $resources->refund($planet,['metal'=>1000],1);
        self::assertEqualsWithDelta(24000,$rows['metal']->currentStock,1e-7);
        $deposit=$resources->deposit($planet,['metal'=>500]);
        self::assertSame(0.0,$deposit['delivered']['metal']);self::assertSame(500.0,$deposit['overflow']['metal']);
        self::assertEqualsWithDelta(24000,$rows['metal']->currentStock,1e-7);
        $resources->consume($planet,['metal'=>15000]);$this->em->flush();$this->clock->sleep(3600);
        self::assertEqualsWithDelta(9040,$resources->snapshot($planet)['metal']['stock'],1e-7);
    }

    public function testStorageBuildingBonusesKeepTheirExistingTierFactors(): void
    {
        $planet=$this->planet();$resources=self::getContainer()->get(ResourceService::class);
        self::assertSame(491250.0,$resources->capacity($planet,'metal',['storage_metal'=>['basic'=>2,'intermediate'=>1,'superior'=>1]]));
        self::assertSame(10000.0,$resources->capacity($planet,'nano',[]));
    }

    public function testPositionUniverseAndEventMultipliersStillApplyToNewPassiveRates(): void
    {
        $planet=$this->planet();$this->position($planet,1);
        $universe=$planet->owner->universe;$universe->gameSettings['multipliers']['resource_production']=2;
        $event=new GameEvent();$event->name='Production de test';$event->type='production_bonus';$event->status='active';$event->universe=$universe;
        $event->startsAt=$this->clock->now()->modify('-1 hour');$event->endsAt=$this->clock->now()->modify('+1 hour');$event->parameters=['percentage'=>50];
        $this->em->persist($event);$this->em->flush();
        $resources=self::getContainer()->get(ResourceService::class);
        foreach(['metal'=>144,'electricity'=>0,'antimatter'=>30,'hydrogen'=>192,'nano'=>72] as $slug=>$hourly) self::assertEqualsWithDelta($hourly,$resources->rate($planet,$slug,0,[])*3600,1e-7,$slug);
    }

    public function testDefaultRulesApplyImmediatelyAndDoNotResetExistingRows(): void
    {
        $planet=$this->planet();$resources=self::getContainer()->get(ResourceService::class);$rows=$this->rows($planet);
        self::assertSame(10000.0,$rows['metal']->currentStock);
        self::assertSame(10000.0,$resources->capacity($planet,'metal',[]));
        self::assertEqualsWithDelta(40,$resources->rate($planet,'metal',0,[])*3600,1e-8);
        $rows['metal']->currentStock=12345;$this->em->flush();$resources->initialize($planet);
        self::assertSame(12345.0,$rows['metal']->currentStock);
    }
}
