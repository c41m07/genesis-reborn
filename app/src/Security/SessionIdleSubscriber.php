<?php
namespace App\Security;

use Psr\Clock\ClockInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;

final class SessionIdleSubscriber implements EventSubscriberInterface
{
    public function __construct(private ClockInterface $clock) {}

    public static function getSubscribedEvents(): array
    {
        // Expirer la session avant que le pare-feu ne charge son authentification.
        return [KernelEvents::REQUEST => ['checkIdleTime', 9]];
    }

    public function checkIdleTime(RequestEvent $event): void
    {
        $request = $event->getRequest();
        if (!$event->isMainRequest() || !$request->hasPreviousSession()) return;

        $session = $request->getSession();
        $now = $this->clock->now()->getTimestamp();
        $lastActivity = $session->get('_last_activity', $now);
        if ($now - $lastActivity >= 86400) $session->invalidate();
        $session->set('_last_activity', $now);
    }
}
