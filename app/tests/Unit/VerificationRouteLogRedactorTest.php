<?php

namespace App\Tests\Unit;

use App\Security\VerificationRouteLogRedactor;
use PHPUnit\Framework\TestCase;
use Psr\Log\AbstractLogger;
use Symfony\Component\HttpFoundation\{Request, RequestStack};
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\EventListener\RouterListener;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\Routing\{RequestContext, Route, RouteCollection};
use Symfony\Component\Routing\Matcher\UrlMatcher;

final class VerificationRouteLogRedactorTest extends TestCase
{
    public function testRealRouterOmitsConfirmationCredentialsButKeepsOtherRouteDiagnostics(): void
    {
        $capture = new class extends AbstractLogger {
            public array $records = [];

            public function log($level, string|\Stringable $message, array $context = []): void
            {
                $this->records[] = [(string) $message, $context];
            }
        };
        $routes = new RouteCollection();
        $routes->add('app_email_verify', new Route('/inscription/confirmer/{token}'));
        $routes->add('app_other', new Route('/other/{id}'));
        $matcher = new UrlMatcher($routes, new RequestContext());
        $listener = new RouterListener($matcher, new RequestStack(), logger: new VerificationRouteLogRedactor($capture));
        $kernel = $this->createStub(HttpKernelInterface::class);
        $link = str_repeat('x', 43);
        $confirmation = Request::create('https://example.test/inscription/confirmer/'.$link, 'POST');
        $listener->onKernelRequest(new RequestEvent($kernel, $confirmation, HttpKernelInterface::MAIN_REQUEST));
        self::assertSame($link, $confirmation->attributes->get('token')); // Pas de changement de routage.
        self::assertSame(['route' => 'app_email_verify', 'method' => 'POST'], $capture->records[0][1]);
        self::assertStringNotContainsString($link, json_encode($capture->records, JSON_THROW_ON_ERROR));

        $listener->onKernelRequest(new RequestEvent($kernel, Request::create('https://example.test/other/42'), HttpKernelInterface::MAIN_REQUEST));
        self::assertSame('app_other', $capture->records[1][1]['route']);
        self::assertSame('42', $capture->records[1][1]['route_parameters']['id']);
        self::assertStringContainsString('/other/42', $capture->records[1][1]['request_uri']);
    }
}
