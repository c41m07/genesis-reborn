<?php
namespace App\EventListener;

use App\Entity\{Player, PlayerUniverse};
use App\Service\Player\EmpireActivityService;
use App\Service\Player\PlayerActivityService;
use Psr\Clock\ClockInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;

final class EmpireActivitySubscriber implements EventSubscriberInterface
{
    public function __construct(
        private TokenStorageInterface $tokens,
        private EmpireActivityService $activity,
        private ClockInterface $clock,
        private PlayerActivityService $presence,
    ) {}

    public static function getSubscribedEvents(): array
    {
        return [KernelEvents::RESPONSE => ['recordVisit', -20]];
    }

    public function recordVisit(ResponseEvent $event): void
    {
        $request = $event->getRequest();
        $response = $event->getResponse();
        if (!$event->isMainRequest() || !$request->isMethod('GET') || $request->isXmlHttpRequest()
            || !$response->isSuccessful() || !str_starts_with((string) $response->headers->get('Content-Type'), 'text/html')) return;

        $destination = $request->headers->get('Sec-Fetch-Dest');
        if ($destination !== null && $destination !== 'document') return;

        $player = $this->tokens->getToken()?->getUser();
        $membership = $request->attributes->get(EmpireActivityService::REQUEST_ATTRIBUTE);
        if (!$player instanceof Player || !$membership instanceof PlayerUniverse || $membership->id === null
            || !$membership->isPlayerControlled() || $membership->player->id !== $player->id || $player->lockedAt !== null
            || $player->currentPlayerUniverse?->id !== $membership->id
            || !$membership->universe->isAccessible($this->clock->now())) return;

        $this->activity->recordVisit($membership);
        $this->presence->record($membership);
    }
}
