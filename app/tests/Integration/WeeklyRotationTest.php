<?php
namespace App\Tests\Integration;

use App\Entity\{BoostEvent, BoostEventContribution, Player, PlayerBoost, Universe};
use App\Repository\GameRepository;
use App\Service\Boost\{BoostEventService, BoostGrantService, WeeklyRotationService};
use App\Service\Event\{ChallengeQueryService, EventPanelService, GameEventService};
use App\Service\Player\RegistrationService;
use App\Service\Universe\{UniverseImporter, UniverseService};
use App\Validator\DTO\RegistrationData;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Clock\MockClock;

final class WeeklyRotationTest extends KernelTestCase
{
    private EntityManagerInterface $em;
    private MockClock $clock;
    private Player $admin;
    private Universe $universe;

    protected function setUp(): void
    {
        self::bootKernel();$this->em=self::getContainer()->get(EntityManagerInterface::class);$params=$this->em->getConnection()->getParams();
        if (($params['host']??'')!=='database-test' || ($params['dbname']??'')!=='genesis_v2_test') throw new \RuntimeException('Base de test non isolée.');
        $tool=new SchemaTool($this->em);$metadata=$this->em->getMetadataFactory()->getAllMetadata();$tool->dropSchema($metadata);$tool->createSchema($metadata);
        $this->clock=new MockClock('2026-11-02T00:00:00Z');self::getContainer()->set('clock',$this->clock);
        self::getContainer()->get(UniverseImporter::class)->import();$this->admin=$this->player('RotationAdmin');
        $this->admin->roles=['ROLE_ADMIN'];$this->em->flush();$this->universe=$this->admin->currentPlayerUniverse->universe;
    }
    private function player(string $name): Player
    {
        $data=new RegistrationData();$data->email=strtolower($name).'@example.test';$data->username=$name;$data->plainPassword='A-test-password-2026';
        $player=self::getContainer()->get(RegistrationService::class)->register($data);
        $universe=$this->em->getRepository(Universe::class)->findOneBy(['slug'=>'omega-1']);self::getContainer()->get(UniverseService::class)->select($player,$universe->id);return $player;
    }
    private function disableDefaults(): void
    {
        $rotations=self::getContainer()->get(WeeklyRotationService::class);
        foreach ($rotations->definitions($this->universe) as $definition) $rotations->setEnabled($this->admin,$this->universe,$definition['key'],false,'Préparer une rotation spécifique');
    }
    private function input(array $override=[]): array
    {
        return array_replace(['name'=>'Défi de combat hebdomadaire','objective'=>'combat','format'=>'individual','reward_mode'=>'threshold','threshold_value'=>7,'global_goal'=>'','duration_hours'=>1,'enabled'=>true,'reason'=>'Animation de l’univers'],$override);
    }
    private function current(): ?BoostEvent
    {
        return self::getContainer()->get(GameRepository::class)->weeklyBoostEventFor($this->universe,$this->clock->now()->modify('monday this week')->setTime(0,0));
    }

    public function testDefaultSequenceIsUnchangedAndTicksAreIdempotent(): void
    {
        $events=self::getContainer()->get(BoostEventService::class);$events->tick();$first=$this->current();self::assertSame('exploration',$first->templateKey);self::assertSame('active',$first->status);
        $events->tick();self::assertSame($first->id,$this->current()->id);
        $this->clock->sleep(604800);$events->tick();self::assertSame('combat',$this->current()->templateKey);
        $this->clock->sleep(604800);$events->tick();self::assertSame('transport',$this->current()->templateKey);
    }

    public function testCreatingAndEnablingTemplateUsesItsObjectiveAndFrozenRewardRules(): void
    {
        $this->disableDefaults();$rotations=self::getContainer()->get(WeeklyRotationService::class);$events=self::getContainer()->get(BoostEventService::class);
        $key=$rotations->create($this->admin,$this->universe,$this->input(['enabled'=>false]));$events->tick();self::assertNull($this->current());
        $rotations->setEnabled($this->admin,$this->universe,$key,true,'Lancement');$events->tick();$event=$this->current();
        self::assertSame('combat',$event->templateKey);self::assertSame(7.0,$event->thresholdValue);self::assertSame($key,$event->configuration['weekly_template_key']);self::assertSame('Défi de combat hebdomadaire',$event->configuration['name']);
        $events->recordCombatDamage($this->admin->currentPlayerUniverse,42,1,7);$rotations->setEnabled($this->admin,$this->universe,$key,false,'Arrêter les prochaines programmations');
        self::assertSame('active',$event->status);$this->clock->sleep(3601);$events->tick();$events->tick();
        self::assertSame('closed',$event->status);self::assertSame(1,$this->em->getRepository(PlayerBoost::class)->count(['playerUniverse'=>$this->admin->currentPlayerUniverse]));
    }

    public function testCancellationKeepsContributionsRewardsAndWeeklySlotEvenAfterTemplateChanges(): void
    {
        $events=self::getContainer()->get(BoostEventService::class);$events->tick();$event=$this->current();$id=$event->id;
        $system=self::getContainer()->get(GameRepository::class)->systems($this->universe)[1];$events->recordExplorationDiscovery($this->admin->currentPlayerUniverse,$system);
        self::getContainer()->get(BoostGrantService::class)->awardEvent($this->admin->currentPlayerUniverse,$event->eventKey);
        $rotations=self::getContainer()->get(WeeklyRotationService::class);$rotations->cancel($this->admin,$event,'Annulation explicite');$closedAt=$event->closedAt;
        $rotations->setEnabled($this->admin,$this->universe,'exploration',false,'Changer les prochains modèles');$rotations->create($this->admin,$this->universe,$this->input());
        $events->tick();$events->tick();$rotations->cancel($this->admin,$event,'Seconde demande');
        self::assertSame($id,$this->current()->id);self::assertSame('cancelled',$event->status);self::assertEquals($closedAt,$event->closedAt);
        self::assertSame(1,$this->em->getRepository(BoostEvent::class)->count(['universe'=>$this->universe]));
        self::assertSame(1,$this->em->getRepository(BoostEventContribution::class)->count(['event'=>$event]));self::assertSame(1,$this->em->getRepository(PlayerBoost::class)->count(['playerUniverse'=>$this->admin->currentPlayerUniverse]));
        self::assertSame([] ,self::getContainer()->get(EventPanelService::class)->forPlayer($this->admin->currentPlayerUniverse));
        $this->clock->sleep(604800);$events->tick();self::assertNotSame($id,$this->current()->id);self::assertSame('cancelled',$this->em->find(BoostEvent::class,$id)->status);
    }

    public function testAllDisabledMeansNoNewOccurrenceAndDoesNotChangeOtherUniverses(): void
    {
        $this->disableDefaults();self::getContainer()->get(BoostEventService::class)->tick();self::assertNull($this->current());
        self::assertGreaterThan(0,$this->em->getRepository(BoostEvent::class)->count([]));
        self::getContainer()->get(WeeklyRotationService::class)->setEnabled($this->admin,$this->universe,'transport',true,'Réactivation');
        self::getContainer()->get(BoostEventService::class)->tick();self::assertSame('transport',$this->current()->templateKey);
    }

    public function testInvalidTopThreeDefinitionDoesNotPersistAnything(): void
    {
        $this->expectException(\DomainException::class);$this->expectExceptionMessage('objectif global');
        self::getContainer()->get(WeeklyRotationService::class)->create($this->admin,$this->universe,$this->input(['reward_mode'=>'top3']));
    }

    public function testNonAdminCannotChangeTemplates(): void
    {
        $player=$this->player('OrdinaryPlayer');$this->expectException(\DomainException::class);$this->expectExceptionMessage('administrateur');
        self::getContainer()->get(WeeklyRotationService::class)->setEnabled($player,$this->universe,'exploration',false,'Interdit');
    }

    public function testPanelShowsOnlyApplicableEventsAndWeeklyPlayerProgressWithTiedRank(): void
    {
        $other=$this->player('OtherContributor');$leader=$this->player('LeadingContributor');$events=self::getContainer()->get(BoostEventService::class);$events->tick();
        $systems=self::getContainer()->get(GameRepository::class)->systems($this->universe);
        foreach ([[$this->admin,2],[$other,2],[$leader,3]] as [$player,$count]) for ($i=1;$i<=$count;++$i) $events->recordExplorationDiscovery($player->currentPlayerUniverse,$systems[$i]);
        $foreign=$this->em->getRepository(Universe::class)->findOneBy(['slug'=>'turbo']);
        if (!$foreign) foreach ($this->em->getRepository(Universe::class)->findAll() as $candidate) if ($candidate->id!==$this->universe->id) { $foreign=$candidate;break; }
        self::assertNotNull($foreign);
        $gameEvents=self::getContainer()->get(GameEventService::class);
        foreach ([['Ici',$this->universe->id],['Global',0],['Ailleurs',$foreign->id]] as [$name,$universe]) $gameEvents->save($this->admin,null,['name'=>$name,'type'=>'announcement','status'=>'active','starts_at'=>'2026-11-02T00:00:00Z','ends_at'=>'2026-11-03T00:00:00Z','universe_id'=>$universe,'visible'=>true]);
        $items=self::getContainer()->get(EventPanelService::class)->forPlayer($this->admin->currentPlayerUniverse);self::assertCount(3,$items);
        self::assertNotContains('Ailleurs',array_column($items,'name'));
        $weekly=array_values(array_filter($items,static fn(array $item):bool=>$item['challenge']!==null))[0];
        self::assertEquals(2,$weekly['challenge']['score']);self::assertSame(2,$weekly['challenge']['rank']);self::assertEquals(70,$weekly['challenge']['progress']);
        self::assertTrue(self::getContainer()->get(ChallengeQueryService::class)->forPlayer($this->admin->currentPlayerUniverse)[0]['weekly']);
    }

    public function testScheduledRotationCanBeCancelledWithoutRecreation(): void
    {
        $events=self::getContainer()->get(BoostEventService::class);$events->tick();$event=$this->current();$event->status='scheduled';$event->startedAt=null;$event->endsAt=null;$this->em->flush();
        $items=self::getContainer()->get(EventPanelService::class)->forPlayer($this->admin->currentPlayerUniverse);self::assertTrue($items[0]['pending']);
        self::getContainer()->get(WeeklyRotationService::class)->cancel($this->admin,$event,'Retirer la programmation');$events->tick();self::assertSame('cancelled',$this->current()->status);
    }
}
