<?php
namespace App\Tests\Integration;

use App\Entity\{Player, PlayerBoost, PlayerUniverse, Universe, Planet, QueueItem, BoostEvent, Fleet};
use App\Service\Universe\{UniverseImporter, UniverseService};
use App\Service\Player\RegistrationService;
use App\Service\Catalog\GameCatalog;
use App\Service\Queue\QueueService;
use App\Service\Boost\{BoostGrantService, BoostUseService, ResourceBoxPolicy, ResourceBoxUseService};
use App\Validator\DTO\RegistrationData;
use App\Service\Admin\AdminEventPlanner;
use App\Service\Boost\BoostEventService;
use App\Service\Event\GameEventService;
use App\Service\Colony\ResourceService;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Clock\MockClock;

final class AdminEventPlannerTest extends KernelTestCase
{
    private EntityManagerInterface $em;
    private MockClock $clock;

    protected function setUp(): void
    {
        self::bootKernel();
        $this->em = self::getContainer()->get(EntityManagerInterface::class);
        $params = $this->em->getConnection()->getParams();
        if (($params['host'] ?? '') !== 'database-test' || ($params['dbname'] ?? '') !== 'genesis_v2_test') {
            throw new \RuntimeException('Base de test non isolée : test refusé.');
        }
        $tool = new SchemaTool($this->em);
        $metadata = $this->em->getMetadataFactory()->getAllMetadata();
        $tool->dropSchema($metadata);
        $tool->createSchema($metadata);
        self::getContainer()->get(UniverseImporter::class)->import();
        $this->clock=new MockClock('2026-11-09T12:00:00Z');self::getContainer()->set('clock',$this->clock);
    }

    private function playerAndPlanet(string $name = 'BoostTester'): array
    {
        $data = new RegistrationData();
        $data->email = strtolower($name) . '@example.test';
        $data->username = $name;
        $data->plainPassword = 'A-test-password-2026';
        $player = self::getContainer()->get(RegistrationService::class)->register($data);
        $universe = $this->em->getRepository(Universe::class)->findOneBy(['slug' => 'omega-1']);
        self::getContainer()->get(UniverseService::class)->select($player, $universe->id);
        $planet = $this->em->getRepository(Planet::class)->findOneBy(['owner' => $player->currentPlayerUniverse]);

        return [$player, $planet];
    }

    private function admin(): array
    {
        [$player,$planet]=$this->playerAndPlanet();$player->roles=['ROLE_ADMIN'];$this->em->flush();return [$player,$planet];
    }
    private function draft(Player $player,array $override=[]): array
    {
        return self::getContainer()->get(AdminEventPlanner::class)->prepare('challenge',array_replace(['name'=>'Défi de découverte','description'=>'Objectif collectif','universe_id'=>$player->currentPlayerUniverse->universe->id,
            'starts_at'=>'2026-11-09T12:05','ends_at'=>'2026-11-09T12:20','objective'=>'exploration','goal'=>3,'minimum_contribution'=>2,'reward_kind'=>'resource_box','box_format'=>'small'],$override));
    }
    private function event(Player $player,array $override=[],string $key='aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa'): BoostEvent
    {
        $result=self::getContainer()->get(AdminEventPlanner::class)->publish($player,$this->draft($player,$override),$key);
        return $this->em->find(BoostEvent::class,$result['id']);
    }

    public function testPreviewDoesNotPublishAndChallengePublicationIsIdempotent(): void
    {
        [$player]=$this->admin();$planner=self::getContainer()->get(AdminEventPlanner::class);$draft=$this->draft($player);
        self::assertSame(0,$this->em->getRepository(BoostEvent::class)->count([]));self::assertSame(0,$this->em->getRepository(\App\Entity\GameEvent::class)->count([]));
        self::assertEquals(4000,$draft['reward']['box']['resources']['metal']);
        $first=$planner->publish($player,$draft,str_repeat('a',32));$second=$planner->publish($player,$draft,str_repeat('a',32));
        self::assertSame($first,$second);self::assertSame(1,$this->em->getRepository(BoostEvent::class)->count([]));self::assertSame(1,$this->em->getRepository(\App\Entity\GameEvent::class)->count([]));
        $event=$this->em->find(BoostEvent::class,$first['id']);self::assertSame('scheduled',$event->status);self::assertSame('2026-11-09T12:20:00+00:00',$event->endsAt->format(DATE_ATOM));
        self::assertCount(1,self::getContainer()->get(GameEventService::class)->visibleFor($player->currentPlayerUniverse));
    }

    public function testCollectiveGoalAndPersonalThresholdGrantOneFrozenBoxAtClosure(): void
    {
        [$player]=$this->admin();[$other]=$this->playerAndPlanet('Contributor');$event=$this->event($player);$service=self::getContainer()->get(BoostEventService::class);
        $this->clock->sleep(360);$service->tick();self::assertSame('active',$event->status);
        $systems=self::getContainer()->get(\App\Repository\GameRepository::class)->systems($event->universe);
        $service->recordExplorationDiscovery($player->currentPlayerUniverse,$systems[1]);$service->recordExplorationDiscovery($player->currentPlayerUniverse,$systems[2]);$service->recordExplorationDiscovery($other->currentPlayerUniverse,$systems[3]);
        $service->recordExplorationDiscovery($player->currentPlayerUniverse,$systems[1]);self::assertEquals(3,$event->totalContribution);
        $policy=self::getContainer()->get(ResourceBoxPolicy::class);$settings=$policy->settings($event->universe);$settings['templates']['small']['metal']=4500;$policy->saveSettings($player,$event->universe,$settings,'Modèle futur');
        $this->clock->sleep(900);$service->tick();$service->tick();self::assertSame('closed',$event->status);
        $reward=$this->em->getRepository(PlayerBoost::class)->findOneBy(['sourceKey'=>'event-'.$event->eventKey.'-player-'.$player->currentPlayerUniverse->id]);
        self::assertNotNull($reward);self::assertSame('resource_box',$reward->kind);self::assertEquals(4000,$reward->payload['resources']['metal']);
        self::assertNull($this->em->getRepository(PlayerBoost::class)->findOneBy(['sourceKey'=>'event-'.$event->eventKey.'-player-'.$other->currentPlayerUniverse->id]));
        self::assertSame(1,$this->em->getRepository(PlayerBoost::class)->count(['sourceType'=>'event']));
    }

    public function testMissedCollectiveGoalDoesNotGrantAnyObject(): void
    {
        [$player]=$this->admin();$event=$this->event($player);$service=self::getContainer()->get(BoostEventService::class);$this->clock->sleep(360);$service->tick();
        $system=self::getContainer()->get(\App\Repository\GameRepository::class)->systems($event->universe)[1];$service->recordExplorationDiscovery($player->currentPlayerUniverse,$system);
        $this->clock->sleep(900);$service->tick();self::assertSame('closed',$event->status);self::assertSame(0,$this->em->getRepository(PlayerBoost::class)->count([]));
    }

    public function testBoostAlternativeIsAwardedWithoutAnExtraBox(): void
    {
        [$player]=$this->admin();$event=$this->event($player,['goal'=>1,'minimum_contribution'=>0,'reward_kind'=>'boost']);$service=self::getContainer()->get(BoostEventService::class);$this->clock->sleep(360);$service->tick();
        $system=self::getContainer()->get(\App\Repository\GameRepository::class)->systems($event->universe)[1];$service->recordExplorationDiscovery($player->currentPlayerUniverse,$system);
        $this->clock->sleep(900);$service->tick();$rewards=$this->em->getRepository(PlayerBoost::class)->findAll();self::assertCount(1,$rewards);self::assertSame('boost',$rewards[0]->kind);self::assertNull($rewards[0]->payload);
    }

    public function testExpiredScheduledChallengeDoesNotStartLate(): void
    {
        [$player]=$this->admin();$event=$this->event($player);$this->clock->sleep(1500);self::getContainer()->get(BoostEventService::class)->tick();
        self::assertSame('cancelled',$event->status);self::assertNull($event->startedAt);self::assertSame(0,$this->em->getRepository(PlayerBoost::class)->count([]));
    }

    public function testChallengeCancellationAlsoCancelsItsAnnouncement(): void
    {
        [$player]=$this->admin();$event=$this->event($player);self::getContainer()->get(AdminEventPlanner::class)->cancelChallenge($player,$event,'Annulation prévue');
        self::assertSame('cancelled',$event->status);self::assertCount(0,self::getContainer()->get(GameEventService::class)->visibleFor($player->currentPlayerUniverse));self::assertSame(0,$this->em->getRepository(PlayerBoost::class)->count([]));
    }

    public function testCancelledProductionBonusPreservesPastProductionForIdlePlayers(): void
    {
        [$player,$planet]=$this->admin();$planner=self::getContainer()->get(AdminEventPlanner::class);$resources=self::getContainer()->get(ResourceService::class);
        $base=$resources->snapshot($planet)['metal'];$stock=$base['stock'];$rate=$base['rate'];
        $draft=$planner->prepare('bonus',['name'=>'Production temporaire','description'=>'','starts_at'=>'2026-11-09T12:05','ends_at'=>'2026-11-09T13:00','universe_id'=>$player->currentPlayerUniverse->universe->id,'percentage'=>100]);
        $result=$planner->publish($player,$draft,str_repeat('b',32));$events=self::getContainer()->get(GameEventService::class);$this->clock->sleep(900);
        $events->cancel($player,$result['id'],'Fin anticipée',true);$this->clock->sleep(900);
        self::assertEqualsWithDelta(2,$events->productionMultiplier($player->currentPlayerUniverse->universe,new \DateTimeImmutable('2026-11-09T12:10:00Z')),.00001);
        self::assertEqualsWithDelta(1,$events->productionMultiplier($player->currentPlayerUniverse->universe),.00001);
        $rows=$resources->materialize($planet);self::assertEqualsWithDelta($stock+2400*$rate,$rows['metal']->currentStock,.001);
    }

    public function testChangedBoxModelsRequireAnotherPreview(): void
    {
        [$player]=$this->admin();$draft=$this->draft($player);$policy=self::getContainer()->get(ResourceBoxPolicy::class);$settings=$policy->settings($player->currentPlayerUniverse->universe);$settings['templates']['small']['metal']=4500;
        $policy->saveSettings($player,$player->currentPlayerUniverse->universe,$settings,'Changement avant confirmation');
        $this->expectException(\DomainException::class);$this->expectExceptionMessage('Les modèles de boîtes ont changé');
        self::getContainer()->get(AdminEventPlanner::class)->publish($player,$draft,str_repeat('a',32));
    }

    public function testNonAdminCannotPublishEvenWithAValidDefinition(): void
    {
        [$player]=$this->playerAndPlanet();$draft=$this->draft($player);$this->expectException(\DomainException::class);
        self::getContainer()->get(AdminEventPlanner::class)->publish($player,$draft,str_repeat('a',32));
    }

    public function testPastStartCannotBeUsedForRetroactiveBonuses(): void
    {
        [$player]=$this->admin();$this->expectException(\DomainException::class);
        self::getContainer()->get(AdminEventPlanner::class)->prepare('bonus',['name'=>'Passé','starts_at'=>'2026-11-09T11:00','ends_at'=>'2026-11-09T13:00','percentage'=>100,'universe_id'=>$player->currentPlayerUniverse->universe->id]);
    }

    public function testGuidedDefinitionCannotBeEditedThroughLegacyForm(): void
    {
        [$player]=$this->admin();$planner=self::getContainer()->get(AdminEventPlanner::class);$draft=$planner->prepare('announcement',['name'=>'Annonce','starts_at'=>'2026-11-09T12:05','ends_at'=>'2026-11-09T13:00','universe_id'=>0]);$result=$planner->publish($player,$draft,str_repeat('a',32));
        $this->expectException(\DomainException::class);self::getContainer()->get(GameEventService::class)->save($player,$result['id'],[]);
    }

    public function testPreparedMigrationKeepsLegacyRotationsUnchanged(): void
    {
        [$player]=$this->admin();$event=new BoostEvent();$event->eventKey='legacy';$event->templateKey='transport';$event->universe=$player->currentPlayerUniverse->universe;$event->format='collective';$event->rewardMode='all_contributors';$event->contributionMetric='freight_delivered';$event->durationSeconds=604800;$event->scheduledAt=$this->clock->now();$this->em->persist($event);$this->em->flush();
        $connection=$this->em->getConnection();$connection->executeStatement('ALTER TABLE boost_event DROP configuration');
        require_once dirname(__DIR__,2).'/migrations/Version20261109040000.php';$migration=new \DoctrineMigrations\Version20261109040000($connection,new \Psr\Log\NullLogger());$migration->up(new \Doctrine\DBAL\Schema\Schema());foreach($migration->getSql() as $query) $connection->executeStatement($query->getStatement());
        self::assertNull($connection->fetchOne('SELECT configuration FROM boost_event WHERE id=?',[$event->id]));self::assertSame([], (new SchemaTool($this->em))->getUpdateSchemaSql($this->em->getMetadataFactory()->getAllMetadata()));
    }
}
