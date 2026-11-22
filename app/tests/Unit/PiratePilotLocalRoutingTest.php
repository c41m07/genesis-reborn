<?php

declare(strict_types=1);

namespace App\Tests\Unit;

use App\Controller\PiratePilotLocalController;
use App\Repository\{PirateCampaignReadRepository, PirateConvoyReadRepository};
use App\Service\PirateCampaign\{PirateCampaignCatalogue, PirateCampaignWorkspace, PirateConvoyDossierProjection, PiratePilotActiveColony, PiratePilotAttackCommand, PiratePilotConvoyDepositCommand, PiratePilotConvoyRouteCommand, PiratePilotEncounterForms, PiratePilotGate, PiratePilotLocalReader, PiratePilotScoutCommand, PiratePilotSurvivorsCommand};
use PHPUnit\Framework\TestCase;
use Psr\Clock\ClockInterface;
use Symfony\Bundle\FrameworkBundle\Routing\AttributeRouteControllerLoader;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\Request;

final class PiratePilotLocalRoutingTest extends TestCase
{
    public function testNativeRoutingOnlyImportsTheGetAndExactPostActionsInDevAndProd(): void
    {
        $controller = PiratePilotLocalController::class;
        $name = 'app_pirate_campaign';
        $route = (new AttributeRouteControllerLoader('dev'))->load($controller)->get($name);
        self::assertNotNull($route);
        self::assertSame(['GET'], $route->getMethods());
        self::assertTrue($route->hasDefault('missionNumber'));
        self::assertNull($route->getDefault('missionNumber'));

        $expected = [
            $name => ['GET'],
            'app_pirate_campaign_scout' => ['POST'],
            'app_pirate_campaign_attack_preview' => ['POST'],
            'app_pirate_campaign_attack' => ['POST'],
            'app_pirate_campaign_convoy_deposit' => ['POST'],
            'app_pirate_campaign_convoy_route_preview' => ['POST'],
            'app_pirate_campaign_convoy_route' => ['POST'],
            'app_pirate_campaign_survivors_preview' => ['POST'],
            'app_pirate_campaign_survivors' => ['POST'],
        ];
        $devRoutes = (new AttributeRouteControllerLoader('dev'))->load($controller);
        self::assertCount(9, $devRoutes);
        foreach ($expected as $routeName => $methods) {
            self::assertSame($methods, $devRoutes->get($routeName)?->getMethods(), $routeName);
        }
        $prodRoutes = (new AttributeRouteControllerLoader('prod'))->load($controller);
        self::assertCount(count($expected), $prodRoutes);
        foreach ($expected as $routeName => $methods) {
            self::assertSame($methods, $prodRoutes->get($routeName)?->getMethods(), $routeName);
        }
        foreach (['alpha', 'test'] as $env) {
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
        $reader = new PiratePilotLocalReader($security, $clock, new PiratePilotGate('alpha'),
            $repository, $catalogue, $workspace, $convoys, new PirateConvoyDossierProjection());

        $colonies = (new \ReflectionClass(PiratePilotActiveColony::class))->newInstanceWithoutConstructor();
        $forms = (new \ReflectionClass(PiratePilotEncounterForms::class))->newInstanceWithoutConstructor();
        $scout = (new \ReflectionClass(PiratePilotScoutCommand::class))->newInstanceWithoutConstructor();
        $attack = (new \ReflectionClass(PiratePilotAttackCommand::class))->newInstanceWithoutConstructor();
        $deposit = (new \ReflectionClass(PiratePilotConvoyDepositCommand::class))->newInstanceWithoutConstructor();
        $route = (new \ReflectionClass(PiratePilotConvoyRouteCommand::class))->newInstanceWithoutConstructor();
        $survivors = (new \ReflectionClass(PiratePilotSurvivorsCommand::class))->newInstanceWithoutConstructor();
        $controller = new PiratePilotLocalController();
        $get = Request::create('/evenements/campagne-pirate/1');
        $post = Request::create('/evenements/campagne-pirate/1', 'POST');
        $responses = [
            $controller->dossier($get, $reader, $colonies, $forms, 1),
            $controller->scout($post, 1, $reader, $colonies, $scout),
            $controller->attackPreview($post, 1, $reader, $colonies, $forms),
            $controller->attack($post, 1, $reader, $colonies, $attack),
            $controller->convoyDeposit($post, 1, $reader, $deposit),
            $controller->convoyRoutePreview($post, 1, $reader, $route),
            $controller->convoyRoute($post, 1, $reader, $route),
            $controller->survivorsPreview($post, 1, $reader, $survivors),
            $controller->survivors($post, 1, $reader, $survivors),
        ];
        foreach ($responses as $response) {
            self::assertSame(404, $response->getStatusCode());
            self::assertTrue($response->headers->hasCacheControlDirective('private'));
            self::assertTrue($response->headers->hasCacheControlDirective('no-store'));
            self::assertSame('Page introuvable.', $response->getContent());
        }
    }
}
