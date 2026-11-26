<?php
namespace App\Tests\Integration;

use App\Entity\{AdminAuditLog,PlanetBuilding,PlanetResource,Player,Universe};
use App\Service\Admin\CatalogBalanceService;
use App\Service\Catalog\GameCatalog;
use App\Service\Colony\ResourceService;
use App\Service\Player\RegistrationService;
use App\Service\Universe\{UniverseImporter,UniverseService};
use App\Validator\DTO\RegistrationData;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class CatalogBalanceTest extends KernelTestCase
{
    private EntityManagerInterface $em;
    private Player $admin;
    protected function setUp():void
    {
        self::bootKernel();$this->em=self::getContainer()->get(EntityManagerInterface::class);$params=$this->em->getConnection()->getParams();
        if(($params['host']??'')!=='database-test'||($params['dbname']??'')!=='genesis_v2_test')throw new \RuntimeException('Base de test non isolée.');
        $schema=new SchemaTool($this->em);$metadata=$this->em->getMetadataFactory()->getAllMetadata();$schema->dropSchema($metadata);$schema->createSchema($metadata);
        self::getContainer()->get(UniverseImporter::class)->import();$this->admin=new Player();$this->admin->email='balance-admin@example.test';$this->admin->username='BalanceAdmin';$this->admin->password='test';$this->admin->roles=['ROLE_ADMIN'];$this->em->persist($this->admin);$this->em->flush();
    }
    private function universe(string $slug='turbo-1'):Universe{return $this->em->getRepository(Universe::class)->findOneBy(['slug'=>$slug]);}
    private function values(CatalogBalanceService $service,Universe $universe,string $group,string $slug):array
    {foreach($service->entries($universe)[$group] as $item)if($item['slug']===$slug)return array_map(static fn(array $field)=>$field['value'],$item['fields']);throw new \LogicException('Entrée catalogue absente.');}
    public function testOverridesAreScopedResettableAndAudited():void
    {
        $service=static::getContainer()->get(CatalogBalanceService::class);$catalog=static::getContainer()->get(GameCatalog::class);$turbo=$this->universe();$omega=$this->universe('omega-1');
        $values=$this->values($service,$turbo,'ships','fighter');$values['precision']=88;$values['cost_profiles.basic.metal']=4500;$service->save($this->admin,$turbo->id,'ships','fighter',$values,$service->revision($turbo,'ships','fighter'),'Essai isolé du catalogue');
        self::assertSame(88.0,$catalog->getForUniverse($turbo,'ships','fighter')['precision']);self::assertSame(4500,$catalog->getForUniverse($turbo,'ships','fighter')['cost_profiles']['basic']['metal']);
        self::assertSame(55,$catalog->getForUniverse($omega,'ships','fighter')['precision']);self::assertSame(1,$this->em->getRepository(AdminAuditLog::class)->count());
        static::getContainer()->get(\App\Service\Admin\AdminService::class)->setUniverseSettings($this->admin,$turbo->id,['multipliers'=>array_fill_keys(['building_time','research_time','ship_build_time','resource_production','travel_time'],1.0),'limits'=>['max_planets'=>9,'max_fleet_size'=>100],'features'=>['guilds_enabled'=>true,'planet_conquest'=>true]]);self::assertSame(88.0,$catalog->getForUniverse($turbo,'ships','fighter')['precision']);
        $service->reset($this->admin,$turbo->id,'ships','fighter',$service->revision($turbo,'ships','fighter'),'Retour aux références');self::assertSame(55,$catalog->getForUniverse($turbo,'ships','fighter')['precision']);self::assertSame(3,$this->em->getRepository(AdminAuditLog::class)->count());
    }
    public function testTechnologyAndDefenseValuesResolvePerUniverse():void
    {
        $service=static::getContainer()->get(CatalogBalanceService::class);$catalog=static::getContainer()->get(GameCatalog::class);$turbo=$this->universe();$omega=$this->universe('omega-1');
        $tech=$this->values($service,$turbo,'technologies','weapon_calibration');$tech['effect.max_bonus']=0.30;$service->save($this->admin,$turbo->id,'technologies','weapon_calibration',$tech,$service->revision($turbo,'technologies','weapon_calibration'),'Essai isolé du catalogue');
        $ships=[$catalog->getForUniverse($turbo,'ships','fighter')];$boosted=static::getContainer()->get(\App\Service\Combat\TechnologyEffectCalculator::class)->applyToShips($ships,['weapon_calibration'=>1],$turbo);self::assertEqualsWithDelta(55.25022228,$boosted[0]['precision'],.000001);
        self::assertSame(0.15,$catalog->getForUniverse($omega,'technologies','weapon_calibration')['effect']['max_bonus']);
        $defense=$this->values($service,$turbo,'defenses','ground_turret_anti_light');$defense['hp']=900;$service->save($this->admin,$turbo->id,'defenses','ground_turret_anti_light',$defense,$service->revision($turbo,'defenses','ground_turret_anti_light'),'Essai isolé du catalogue');
        self::assertSame(900.0,$catalog->getForUniverse($turbo,'defenses','ground_turret_anti_light')['hp']);self::assertSame(430,$catalog->getForUniverse($omega,'defenses','ground_turret_anti_light')['hp']);
    }
    public function testEditorRejectsOutOfRangeValues():void
    {
        $service=static::getContainer()->get(CatalogBalanceService::class);$universe=$this->universe();$values=$this->values($service,$universe,'ships','fighter');$values['precision']=1001;
        $this->expectException(\DomainException::class);$service->save($this->admin,$universe->id,'ships','fighter',$values,$service->revision($universe,'ships','fighter'),'Essai isolé du catalogue');
    }
    public function testEditorRejectsInconsistentShotRange():void
    {
        $service=static::getContainer()->get(CatalogBalanceService::class);$universe=$this->universe();$values=$this->values($service,$universe,'ships','fighter');$values['shots_min']=4;$values['shots_max']=2;
        $this->expectException(\DomainException::class);$service->save($this->admin,$universe->id,'ships','fighter',$values,$service->revision($universe,'ships','fighter'),'Essai isolé du catalogue');
    }
    public function testEditorRejectsFieldsOutsideTheAllowlist():void
    {
        $service=static::getContainer()->get(CatalogBalanceService::class);$universe=$this->universe();$values=$this->values($service,$universe,'ships','fighter');$values['max_level']=30;
        $this->expectException(\DomainException::class);$service->save($this->admin,$universe->id,'ships','fighter',$values,$service->revision($universe,'ships','fighter'),'Essai isolé du catalogue');
    }
    public function testNewBuildsUseOverridesWhileExistingQueueSnapshotsStayFixed():void
    {
        $data=new RegistrationData();$data->email='queue-builder@example.test';$data->username='QueueBuilder';$data->plainPassword='Long-test-password-2026';$player=static::getContainer()->get(RegistrationService::class)->register($data);$universe=$this->universe();static::getContainer()->get(UniverseService::class)->select($player,$universe->id);$planet=$this->em->getRepository(\App\Entity\Planet::class)->findOneBy(['owner'=>$player->currentPlayerUniverse]);$queues=static::getContainer()->get(\App\Service\Queue\QueueService::class);$queues->add($player->currentPlayerUniverse,$planet,'buildings','mine_metal',1,str_repeat('a',32));$first=$this->em->getRepository(\App\Entity\QueueItem::class)->findOneBy(['itemSlug'=>'mine_metal']);$oldCosts=$first->paidCosts;$oldDuration=$first->durationSeconds;
        $balance=static::getContainer()->get(CatalogBalanceService::class);$values=$this->values($balance,$universe,'buildings','mine_metal');$values['cost_profiles.basic.metal']=5000;$values['base_build_time']=600;$balance->save($this->admin,$universe->id,'buildings','mine_metal',$values,$balance->revision($universe,'buildings','mine_metal'),'Essai isolé du catalogue');
        self::assertSame($oldCosts,$first->paidCosts);self::assertSame($oldDuration,$first->durationSeconds);$queues->add($player->currentPlayerUniverse,$planet,'buildings','mine_metal',1,str_repeat('b',32));$items=$this->em->getRepository(\App\Entity\QueueItem::class)->findBy(['itemSlug'=>'mine_metal'],['id'=>'ASC']);self::assertCount(2,$items);self::assertSame(5500,$items[1]->paidCosts['metal']);self::assertGreaterThan($oldDuration,$items[1]->durationSeconds);
    }
    public function testBuildingProductionChangeMaterializesThenRefreshesExistingColony():void
    {
        $data=new RegistrationData();$data->email='builder@example.test';$data->username='Builder';$data->plainPassword='Long-test-password-2026';$player=static::getContainer()->get(RegistrationService::class)->register($data);$universe=$this->universe();static::getContainer()->get(UniverseService::class)->select($player,$universe->id);$planet=$this->em->getRepository(\App\Entity\Planet::class)->findOneBy(['owner'=>$player->currentPlayerUniverse]);
        $plant=new PlanetBuilding();$plant->planet=$planet;$plant->slug='power_plant';$plant->level=2;$this->em->persist($plant);$this->em->flush();$resources=static::getContainer()->get(ResourceService::class);$resources->refreshProduction($planet);$before=$this->em->getRepository(PlanetResource::class)->findOneBy(['planet'=>$planet,'resourceType'=>'electricity'])->productionRate;
        $balance=static::getContainer()->get(CatalogBalanceService::class);$values=$this->values($balance,$universe,'buildings','power_plant');$values['production_per_level']=2.5;$planetId=$planet->id;$balance->save($this->admin,$universe->id,'buildings','power_plant',$values,$balance->revision($universe,'buildings','power_plant'),'Essai isolé du catalogue');$this->em->clear();$planet=$this->em->find(\App\Entity\Planet::class,$planetId);$after=$this->em->getRepository(PlanetResource::class)->findOneBy(['planet'=>$planet,'resourceType'=>'electricity'])->productionRate;
        self::assertGreaterThan($before,$after);$factor=1.2-(($planet->position-1)*0.05);self::assertEqualsWithDelta(4.4 * 1.0025 ** 2 * $factor,$after-$before,.000001);
    }

    public function testAuditContainsReasonAndEffectiveBeforeAfter(): void
    {
        $service = static::getContainer()->get(CatalogBalanceService::class);
        $universe = $this->universe();
        $item = $service->entries($universe, 'ships', 'fighter')['ships'][0];
        self::assertFalse($item['fields']['precision']['overridden']);
        $values = $this->values($service, $universe, 'ships', 'fighter');
        $values['precision'] = 81;
        $service->save($this->admin, $universe->id, 'ships', 'fighter', $values, $item['revision'], 'Motif de recette');
        $audit = $this->em->getRepository(AdminAuditLog::class)->findOneBy(['action' => 'universe_catalog_balance']);
        self::assertSame('Motif de recette', $audit->details['reason']);
        self::assertSame(55, $audit->details['before']['precision']);
        self::assertSame(81.0, $audit->details['after']['precision']);
        $updated = $service->entries($universe, 'ships', 'fighter')['ships'][0];
        self::assertTrue($updated['fields']['precision']['overridden']);
        self::assertSame(55, $updated['fields']['precision']['reference']);
        self::assertNotSame($item['revision'], $updated['revision']);
        $service->reset($this->admin, $universe->id, 'ships', 'fighter', $updated['revision'], 'Retour au catalogue');
        // Le retour aux mêmes valeurs ne réactive pas les anciens formulaires.
        self::assertNotSame($item['revision'], $service->revision($universe, 'ships', 'fighter'));
    }

    public function testReasonIsMandatory(): void
    {
        $service = static::getContainer()->get(CatalogBalanceService::class);
        $universe = $this->universe();
        $values = $this->values($service, $universe, 'ships', 'fighter');
        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('motif');
        $service->save($this->admin, $universe->id, 'ships', 'fighter', $values, $service->revision($universe, 'ships', 'fighter'), '');
    }

    public function testInactiveGrowthFieldsAreNotEditable(): void
    {
        $service = static::getContainer()->get(CatalogBalanceService::class);
        $item = $service->entries($this->universe(), 'buildings', 'mine_metal')['buildings'][0];
        self::assertArrayNotHasKey('costs_scaling', $item['fields']);
        self::assertArrayNotHasKey('build_time_scaling', $item['fields']);
        self::assertArrayHasKey('costs_scaling', $item['inactive_fields']);
        self::assertArrayHasKey('build_time_scaling', $item['inactive_fields']);
    }

    public function testPreviewUsesGameCalculatorWithoutMutatingOrAuditing(): void
    {
        $service = static::getContainer()->get(CatalogBalanceService::class);
        $universe = $this->universe();
        $before = $universe->gameSettings;
        $revision = $service->revision($universe, 'ships', 'fighter');
        $values = $this->values($service, $universe, 'ships', 'fighter');
        $values['build_time_base'] *= 2;
        $values['cost_profiles.basic.metal'] += 10;
        $preview = $service->preview($universe, 'ships', 'fighter', $values, $revision, 'basic', 1, 3);
        self::assertGreaterThan($preview['before']['duration'], $preview['after']['duration']);
        self::assertSame($preview['before']['costs']['metal'] + 30, $preview['after']['costs']['metal']);
        self::assertSame(100 * (int) ceil($preview['after']['duration'] / 60), $preview['after']['costs']['electricity']);
        self::assertSame($before, $universe->gameSettings);
        self::assertSame($revision, $service->revision($universe, 'ships', 'fighter'));
        $this->em->flush();
        $this->em->refresh($universe);
        self::assertSame($before, $universe->gameSettings);
        self::assertSame(0, $this->em->getRepository(AdminAuditLog::class)->count());
        self::assertSame(0, $this->em->getRepository(\App\Entity\QueueItem::class)->count());
    }

    public function testPreviewRejectsInvalidScenarioAndValues(): void
    {
        $service = static::getContainer()->get(CatalogBalanceService::class);
        $universe = $this->universe();
        $values = $this->values($service, $universe, 'ships', 'fighter');
        $revision = $service->revision($universe, 'ships', 'fighter');
        foreach ([['basic', 2, 1], ['basic', 1, 1001], ['invalid', 1, 1], ['basic', 0, 1]] as [$tier, $level, $quantity]) {
            try {
                $service->preview($universe, 'ships', 'fighter', $values, $revision, $tier, $level, $quantity);
                self::fail('Le scénario invalide devait être refusé.');
            } catch (\DomainException) {
                self::assertSame(0, $this->em->getRepository(AdminAuditLog::class)->count());
            }
        }
        $values['precision'] = 1001;
        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('hors limites');
        $service->preview($universe, 'ships', 'fighter', $values, $revision, 'basic', 1, 1);
    }

    public function testPreviewRefusesObsoleteContext(): void
    {
        $service = static::getContainer()->get(CatalogBalanceService::class);
        $universe = $this->universe();
        $values = $this->values($service, $universe, 'ships', 'fighter');
        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('a changé');
        $service->preview($universe, 'ships', 'fighter', $values, 'obsolete', 'basic', 1, 1);
    }

    public function testPreviewSupportsAllFamiliesAndTiers(): void
    {
        $service = static::getContainer()->get(CatalogBalanceService::class);
        $universe = $this->universe();
        foreach (['buildings' => 'mine_metal', 'technologies' => 'weapon_calibration', 'ships' => 'fighter', 'defenses' => 'ground_turret_anti_light'] as $group => $slug) {
            $values = $this->values($service, $universe, $group, $slug);
            foreach (GameCatalog::TIERS as $tier) {
                $preview = $service->preview($universe, $group, $slug, $values, $service->revision($universe, $group, $slug), $tier, 1, 1);
                self::assertSame($preview['before'], $preview['after']);
                self::assertGreaterThan(0, $preview['after']['duration']);
            }
        }
        self::assertSame(0, $this->em->getRepository(AdminAuditLog::class)->count());
    }

    public function testVersionRestorationIsValidatedAuditedAndRejectsReplay(): void
    {
        $service = self::getContainer()->get(CatalogBalanceService::class);
        $universe = $this->universe();
        $values = $this->values($service, $universe, 'ships', 'fighter');
        $values['precision'] = 80;
        $service->save($this->admin, $universe->id, 'ships', 'fighter', $values, $service->revision($universe, 'ships', 'fighter'), 'Version initiale');
        $source = $this->em->getRepository(AdminAuditLog::class)->findOneBy([], ['id' => 'DESC'])->id;
        $values['precision'] = 90;
        $service->save($this->admin, $universe->id, 'ships', 'fighter', $values, $service->revision($universe, 'ships', 'fighter'), 'Version suivante');
        $before = $universe->gameSettings;
        $preview = $service->previewRestoration($universe, 'ships', 'fighter', $source);
        self::assertSame($before, $universe->gameSettings);
        self::assertSame(2, $this->em->getRepository(AdminAuditLog::class)->count());
        $service->save($this->admin, $universe->id, 'ships', 'fighter', ['precision' => 999], $preview['revision'], 'Restauration vérifiée', $source);
        self::assertSame(80.0, self::getContainer()->get(GameCatalog::class)->getForUniverse($universe, 'ships', 'fighter')['precision']);
        $audit = $this->em->getRepository(AdminAuditLog::class)->findOneBy([], ['id' => 'DESC']);
        self::assertSame($source, $audit->details['restore_source']);
        self::assertSame(90.0, $audit->details['before']['precision']);
        self::assertSame(80.0, $audit->details['after']['precision']);
        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('a changé');
        $service->save($this->admin, $universe->id, 'ships', 'fighter', [], $preview['revision'], 'Rejeu interdit', $source);
    }

    public function testVersionRejectsOldIncompleteForeignAndChangedContext(): void
    {
        $service = self::getContainer()->get(CatalogBalanceService::class);
        $universe = $this->universe();
        $service->save($this->admin, $universe->id, 'ships', 'fighter', $this->values($service, $universe, 'ships', 'fighter'), $service->revision($universe, 'ships', 'fighter'), 'Version complète');
        $audit = $this->em->getRepository(AdminAuditLog::class)->findOneBy([], ['id' => 'DESC']);
        $details = $audit->details;
        foreach (['old', 'incomplete', 'foreign', 'context'] as $case) {
            $audit->details = $details;
            $audit->targetId = $universe->id;
            if ($case === 'old') unset($audit->details['version']);
            if ($case === 'incomplete') unset($audit->details['after']['precision']);
            if ($case === 'foreign') $audit->targetId = $this->universe('omega-1')->id;
            if ($case === 'context') $audit->details['version']['context']['rules'] = [];
            try {
                $service->previewRestoration($universe, 'ships', 'fighter', $audit->id);
                self::fail('Version invalide acceptée : '.$case);
            } catch (\DomainException $error) {
                self::assertNotEmpty($error->getMessage());
            }
        }
    }

    public function testRestorationRechecksPlayerLevelsAfterPreviewAndRollsBack(): void
    {
        $service = self::getContainer()->get(CatalogBalanceService::class);
        $universe = $this->universe();
        $values = $this->values($service, $universe, 'buildings', 'fortress');
        $values['max_level'] = 1;
        $service->save($this->admin, $universe->id, 'buildings', 'fortress', $values, $service->revision($universe, 'buildings', 'fortress'), 'Version niveau un');
        $source = $this->em->getRepository(AdminAuditLog::class)->findOneBy([], ['id' => 'DESC'])->id;
        $values['max_level'] = 3;
        $service->save($this->admin, $universe->id, 'buildings', 'fortress', $values, $service->revision($universe, 'buildings', 'fortress'), 'Version niveau trois');
        $preview = $service->previewRestoration($universe, 'buildings', 'fortress', $source);
        $data = new RegistrationData();
        $data->email = 'restore-builder@example.test';
        $data->username = 'RestoreBuilder';
        $data->plainPassword = 'Aa1!'.bin2hex(random_bytes(16));
        $player = self::getContainer()->get(RegistrationService::class)->register($data);
        self::getContainer()->get(UniverseService::class)->select($player, $universe->id);
        $planet = $this->em->getRepository(\App\Entity\Planet::class)->findOneBy(['owner' => $player->currentPlayerUniverse]);
        $plant = new PlanetBuilding();
        $plant->planet = $planet;
        $plant->slug = 'fortress';
        $plant->level = 2;
        $this->em->persist($plant);
        $this->em->flush();
        $connection = $this->em->getConnection();
        $settings = $connection->fetchOne('SELECT game_settings FROM universe WHERE id = ?', [$universe->id]);
        $audits = $connection->fetchOne('SELECT COUNT(*) FROM admin_audit_log');
        try {
            $service->save($this->admin, $universe->id, 'buildings', 'fortress', [], $preview['revision'], 'Refus après progression', $source);
            self::fail('Le plafond incompatible a été restauré.');
        } catch (\DomainException $error) {
            self::assertStringContainsString('bâtiments existants', $error->getMessage());
        }
        self::assertSame($settings, $connection->fetchOne('SELECT game_settings FROM universe WHERE id = ?', [$universe->id]));
        self::assertSame($audits, $connection->fetchOne('SELECT COUNT(*) FROM admin_audit_log'));
    }
}
