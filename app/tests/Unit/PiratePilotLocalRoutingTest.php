<?php

declare(strict_types=1);

namespace App\Tests\Unit;

use App\Controller\PiratePilotLocalController;
use App\Repository\{PirateCampaignReadRepository, PirateConvoyReadRepository};
use App\Service\PirateCampaign\{PirateCampaignCatalogue, PirateCampaignWorkspace, PirateConvoyDossierProjection, PiratePilotActiveColony, PiratePilotAttackCommand, PiratePilotEncounterForms, PiratePilotLocalReader, PiratePilotScoutCommand};
use PHPUnit\Framework\TestCase;
use Psr\Clock\ClockInterface;
use Symfony\Bundle\FrameworkBundle\Routing\AttributeRouteControllerLoader;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\Request;

final class PiratePilotLocalRoutingTest extends TestCase
{
    public function testNativeRoutingOnlyImportsTheGetAndExactPostActionsInDev(): void
    {
        $controller = PiratePilotLocalController::class;
        $name = 'app_dev_pirate_pilot';
        $route = (new AttributeRouteControllerLoader('dev'))->load($controller)->get($name);
        self::assertNotNull($route);
        self::assertSame(['GET'], $route->getMethods());
        self::assertTrue($route->hasDefault('missionNumber'));
        self::assertNull($route->getDefault('missionNumber'));

        $expected = [
            $name => ['GET'],
            'app_dev_pirate_pilot_scout' => ['POST'],
            'app_dev_pirate_pilot_attack_preview' => ['POST'],
            'app_dev_pirate_pilot_attack' => ['POST'],
        ];
        $devRoutes = (new AttributeRouteControllerLoader('dev'))->load($controller);
        self::assertCount(4, $devRoutes);
        foreach ($expected as $routeName => $methods) {
            self::assertSame($methods, $devRoutes->get($routeName)?->getMethods(), $routeName);
        }
        foreach (['prod', 'alpha', 'test'] as $env) {
            self::assertCount(0, (new AttributeRouteControllerLoader($env))->load($controller), $env);
        }
    }

    public function testDisabledGetHasANeutralPrivateResponseBeforeIdentityOrQueries(): void
    {
        $security = $this->createMock(Security::class);
        $security->expects(self::never())->method('getUser');
        $clock = $this->createMock(ClockInterface::class);
        $clock->expects(self::never())->method('now');
        // Aucun de ces objets ne doit être utilisé quand l'environnement et l'opt-in ferment la garde.
        $repository = (new \ReflectionClass(PirateCampaignReadRepository::class))->newInstanceWithoutConstructor();
        $catalogue = (new \ReflectionClass(PirateCampaignCatalogue::class))->newInstanceWithoutConstructor();
        $workspace = (new \ReflectionClass(PirateCampaignWorkspace::class))->newInstanceWithoutConstructor();
        $convoys = (new \ReflectionClass(PirateConvoyReadRepository::class))->newInstanceWithoutConstructor();
        $reader = new PiratePilotLocalReader($security, $clock, PiratePilotLocalReader::configuredGate('dev', '0', '0'),
            $repository, $catalogue, $workspace, $convoys, new PirateConvoyDossierProjection());

        $colonies = (new \ReflectionClass(PiratePilotActiveColony::class))->newInstanceWithoutConstructor();
        $forms = (new \ReflectionClass(PiratePilotEncounterForms::class))->newInstanceWithoutConstructor();
        $scout = (new \ReflectionClass(PiratePilotScoutCommand::class))->newInstanceWithoutConstructor();
        $attack = (new \ReflectionClass(PiratePilotAttackCommand::class))->newInstanceWithoutConstructor();
        $controller = new PiratePilotLocalController();
        $get = Request::create('/evenements/campagne-pirate/1');
        $post = Request::create('/evenements/campagne-pirate/1', 'POST');
        $responses = [
            $controller->dossier($get, $reader, $colonies, $forms, 1),
            $controller->scout($post, 1, $reader, $colonies, $scout),
            $controller->attackPreview($post, 1, $reader, $colonies, $forms),
            $controller->attack($post, 1, $reader, $colonies, $attack),
        ];
        foreach ($responses as $response) {
            self::assertSame(404, $response->getStatusCode());
            self::assertTrue($response->headers->hasCacheControlDirective('private'));
            self::assertTrue($response->headers->hasCacheControlDirective('no-store'));
            self::assertSame('Page introuvable.', $response->getContent());
        }
    }

    public function testAbsentOrMalformedLocalOptInKeepsTheReadGateClosed(): void
    {
        foreach ([['0', '18'], ['true', '18'], ['', ''], ['1', '-4'], ['1', '00018'],
            ['1', '4294967299'], ['1', ' 18'], ['1', "18\n"], ['1', '18e0']] as [$enabled, $id]) {
            self::assertFalse(PiratePilotLocalReader::configuredGate('dev', $enabled, $id)->isLocallyEnabled());
        }
        self::assertTrue(PiratePilotLocalReader::configuredGate('dev', '1', '18')->isLocallyEnabled());
        self::assertFalse(PiratePilotLocalReader::configuredGate('prod', '1', '18')->isLocallyEnabled());
    }
}
