<?php
namespace App\Security;

use App\Entity\Player;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;

final class SuspendedPlayerListener implements EventSubscriberInterface
{
    public function __construct(private TokenStorageInterface $tokens, private UrlGeneratorInterface $urls) {}
    public static function getSubscribedEvents(): array { return [KernelEvents::REQUEST => ['checkSuspension', 7]]; }
    public function checkSuspension(RequestEvent $event): void
    {
        if (!$event->isMainRequest()) { return; }
        $token = $this->tokens->getToken();
        $user = $token?->getUser();
        if (!$user instanceof Player || $user->lockedAt === null) { return; }
        $this->tokens->setToken(null);
        if ($event->getRequest()->hasSession()) { $event->getRequest()->getSession()->invalidate(); }
        $event->setResponse(new RedirectResponse($this->urls->generate('app_login')));
    }
}
