<?php

declare(strict_types=1);

namespace App\Tests\Unit;

use App\Controller\PiratePilotLocalController;
use App\Repository\{PirateCampaignReadRepository, PirateConvoyReadRepository};
use App\Service\PirateCampaign\{PirateCampaignCatalogue, PirateCampaignWorkspace, PirateConvoyDossierProjection, PiratePilotLocalReader};
use PHPUnit\Framework\TestCase;
use Psr\Clock\ClockInterface;
use Symfony\Bundle\FrameworkBundle\Routing\AttributeRouteControllerLoader;
use Symfony\Bundle\SecurityBundle\Security;

final class PiratePilotLocalRoutingTest extends TestCase
{
    public function testNativeRoutingOnlyImportsTheGetInDev(): void
    {
        $controller = PiratePilotLocalController::class;
        $name = 'app_dev_pirate_pilot';
        $route = (new AttributeRouteControllerLoader('dev'))->load($controller)->get($name);
        self::assertNotNull($route);
        self::assertSame(['GET'], $route->getMethods());
        self::assertTrue($route->hasDefault('missionNumber'));
        self::assertNull($route->getDefault('missionNumber'));

        foreach (['prod', 'alpha', 'test'] as $env) {
            self::assertNull((new AttributeRouteControllerLoader($env))->load($controller)->get($name), $env);
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

        $response = (new PiratePilotLocalController())->dossier($reader, 123);
        self::assertSame(404, $response->getStatusCode());
        self::assertTrue($response->headers->hasCacheControlDirective('private'));
        self::assertTrue($response->headers->hasCacheControlDirective('no-store'));
        self::assertSame('Page introuvable.', $response->getContent());
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
