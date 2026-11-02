<?php
namespace App\Security;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;

final class ResponseSecurityHeadersSubscriber implements EventSubscriberInterface
{
    private const HEADERS = [
        'X-Content-Type-Options' => 'nosniff',
        'X-Frame-Options' => 'DENY',
        'Referrer-Policy' => 'strict-origin-when-cross-origin',
        'Permissions-Policy' => 'camera=(), geolocation=(), microphone=()',
    ];

    public static function getSubscribedEvents(): array
    {
        return [KernelEvents::RESPONSE => ['addSecurityHeaders', -10]];
    }

    public function addSecurityHeaders(ResponseEvent $event): void
    {
        foreach (self::HEADERS as $name => $value) {
            if (!$event->getResponse()->headers->has($name)) {
                $event->getResponse()->headers->set($name, $value);
            }
        }
    }
}
