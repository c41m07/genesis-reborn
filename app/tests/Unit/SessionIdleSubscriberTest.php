<?php
namespace App\Tests\Unit;

use App\Security\SessionIdleSubscriber;
use PHPUnit\Framework\TestCase;
use Psr\Clock\ClockInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;

final class SessionIdleSubscriberTest extends TestCase
{
    public function testActivityRenewsTheIdleDeadlineAndExpiryClearsAuthentication(): void
    {
        $clock = $this->createStub(ClockInterface::class);
        $clock->method('now')->willReturn(new \DateTimeImmutable('@200000'));
        $subscriber = new SessionIdleSubscriber($clock);
        $request = Request::create('/univers');
        $session = new Session(new MockArraySessionStorage());
        $request->setSession($session);
        $session->start();
        $request->cookies->set($session->getName(), $session->getId());
        $event = new RequestEvent($this->createStub(HttpKernelInterface::class), $request, HttpKernelInterface::MAIN_REQUEST);
        $session->set('_security_main', 'authenticated');
        $session->set('_last_activity', 200000 - 86399);
        $subscriber->checkIdleTime($event);
        self::assertSame('authenticated', $session->get('_security_main'));
        self::assertSame(200000, $session->get('_last_activity'));
        $session->set('_last_activity', 200000 - 86400);
        $previousId = $session->getId();
        $subscriber->checkIdleTime($event);
        self::assertFalse($session->has('_security_main'));
        self::assertNotSame($previousId, $session->getId());
        self::assertSame(200000, $session->get('_last_activity'));
    }

    public function testAnonymousRequestDoesNotStartASession(): void
    {
        $clock = $this->createMock(ClockInterface::class);
        $clock->expects(self::never())->method('now');
        $request = Request::create('/');
        $session = new Session(new MockArraySessionStorage());
        $request->setSession($session);
        $event = new RequestEvent($this->createStub(HttpKernelInterface::class), $request, HttpKernelInterface::MAIN_REQUEST);
        (new SessionIdleSubscriber($clock))->checkIdleTime($event);
        self::assertFalse($session->isStarted());
    }
}
