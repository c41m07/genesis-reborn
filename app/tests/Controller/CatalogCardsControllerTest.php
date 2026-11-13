<?php
namespace App\Tests\Controller;

use App\Entity\{Planet,PlanetBuilding,PlanetResource,Universe};
use App\Service\Player\RegistrationService;
use App\Service\Universe\{UniverseImporter,UniverseService};
use App\Validator\DTO\RegistrationData;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class CatalogCardsControllerTest extends WebTestCase
{
    public function testStrictRenderingShowsDisabledShortageButtonsAndRestoresTheAction(): void
    {
        $client=self::createClient([],['HTTP_HOST'=>'genesis-reborn.localhost']);
        $client->disableReboot();
        $em=self::getContainer()->get(EntityManagerInterface::class);
        $params=$em->getConnection()->getParams();
        if(($params['host']??'')!=='database-test'||($params['dbname']??'')!=='genesis_v2_test') throw new \RuntimeException('Base de test non isolée.');
        $schema=new SchemaTool($em);$metadata=$em->getMetadataFactory()->getAllMetadata();
        $schema->dropSchema($metadata);$schema->createSchema($metadata);
        self::getContainer()->get(UniverseImporter::class)->import();
        $registration=new RegistrationData();$registration->email='catalog-cards@example.test';$registration->username='CatalogCards';$registration->plainPassword='Browser-test-2026!';
        $player=self::getContainer()->get(RegistrationService::class)->register($registration);
        $universe=$em->getRepository(Universe::class)->findOneBy(['slug'=>'omega-1']);
        self::getContainer()->get(UniverseService::class)->select($player,$universe->id);
        $planet=$em->getRepository(Planet::class)->findOneBy(['owner'=>$player->currentPlayerUniverse]);
        foreach(['lab','shipyard','fortress'] as $slug) {
            $building=new PlanetBuilding();$building->planet=$planet;$building->slug=$slug;$building->level=1;$em->persist($building);
        }
        foreach($em->getRepository(PlanetResource::class)->findBy(['planet'=>$planet]) as $row) $row->currentStock=0;
        $em->flush();$planetId=$planet->id;$client->loginUser($player);
        self::assertTrue(self::getContainer()->get('twig')->isStrictVariables());
        foreach(['buildings'=>'mine_metal','technologies'=>'engineering_foundations','ships'=>'probe','defenses'=>'ground_turret_anti_light'] as $group=>$slug) {
            $crawler=$client->request('GET','/planetes/'.$planetId.'?section='.$group);
            self::assertResponseIsSuccessful();
            $button=$crawler->filter('#item-'.$slug.'-basic .card-body>form>button');
            self::assertCount(1,$button);
            self::assertNotNull($button->attr('disabled'));
            self::assertStringStartsWith('Manque ',$button->text());
            self::assertStringNotContainsString('Manque',$crawler->filter('#item-'.$slug.'-basic .building-costs')->text());
        }
        $em->getConnection()->executeStatement('UPDATE planet_resource SET current_stock=10000 WHERE planet_id=?',[$planetId]);
        $em->clear();
        $client->request('GET','/planetes/'.$planetId.'?section=buildings');
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('#item-mine_metal-basic .card-body>form>button','Améliorer au niveau 1');
        self::assertSelectorNotExists('#item-mine_metal-basic .card-body>form>button[disabled]');
    }
}
