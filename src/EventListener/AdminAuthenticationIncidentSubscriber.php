<?php

declare(strict_types=1);

namespace App\EventListener;

use App\Service\Admin\AdminAccessIncidentLogger;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

final class AdminAuthenticationIncidentSubscriber implements EventSubscriberInterface
{
    public function __construct(private readonly AdminAccessIncidentLogger $incidentLogger)
    {
    }

    public static function getSubscribedEvents(): array
    {
        return [KernelEvents::EXCEPTION => ['onKernelException', 100]];
    }

    public function onKernelException(ExceptionEvent $event): void
    {
        if (!$event->getThrowable() instanceof AuthenticationException) {
            return;
        }

        $this->incidentLogger->log($event->getRequest(), null);
        if ($event->getRequest()->hasSession()) {
            $event->getRequest()->getSession()->getFlashBag()->add('error', 'Connecte-toi pour continuer. La tentative d’accès à la console a été consignée.');
        }
    }
}
