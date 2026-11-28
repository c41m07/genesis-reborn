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
            if ($group === 'technologies') {
                foreach (['basic', 'intermediate', 'superior'] as $tier) {
                    $effects = $crawler->filter('#item-tactical_intelligence-'.$tier.' [data-catalogue-main-gain]');
                    self::assertStringContainsString('Score de renseignement des sondes adverses', $effects->text());
                    self::assertStringContainsString('Préavis théorique des attaques entrantes', $effects->text());
                }
            }
            $card = $crawler->filter('#item-'.$slug.'-basic .card-body');
            $paymentPanel = $card->filter('form[method="post"] > .catalog-payment');
            self::assertCount(1, $paymentPanel);
            $button = $paymentPanel->filter('button.catalog-build-action');
            self::assertCount(1,$button);
            self::assertSame('submit', $button->attr('type') ?? 'submit');
            self::assertNotNull($button->attr('disabled'));
            self::assertStringStartsWith('Il manque :',$button->text());
            self::assertGreaterThan(0, $button->filter('.catalog-missing-resource')->count());
            $wait = $paymentPanel->filter('.catalog-wait:not([hidden]) > [data-resource-eta-target="timer"]');
            self::assertCount(1, $wait);
            self::assertMatchesRegularExpression('/^Délai (estimé|indisponible) : .+\.$/u', $wait->text());
            self::assertCount(1, $card->filter('[data-resource-eta-target="timer"]'));
            self::assertStringNotContainsString('Manque', $card->filter('.building-costs')->text());
        }
        $em->getConnection()->executeStatement('UPDATE planet_resource SET current_stock=10000 WHERE planet_id=?',[$planetId]);
        $em->clear();
        $client->request('GET','/planetes/'.$planetId.'?section=buildings');
        self::assertResponseIsSuccessful();
        $payment = '#item-mine_metal-basic .card-body form[method="post"] > .catalog-payment';
        self::assertSelectorTextContains($payment.' > button.catalog-build-action', 'Améliorer au niveau 1');
        self::assertSelectorNotExists($payment.' > button.catalog-build-action[disabled]');
        self::assertSelectorNotExists($payment.' .catalog-wait:not([hidden])');
    }
}
