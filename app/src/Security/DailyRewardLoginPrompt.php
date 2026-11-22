<?php

declare(strict_types=1);

namespace App\Security;

use App\Entity\Player;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Security\Http\Authenticator\FormLoginAuthenticator;
use Symfony\Component\Security\Http\Event\LoginSuccessEvent;

final class DailyRewardLoginPrompt implements EventSubscriberInterface
{
    private const SESSION_KEY = '_daily_reward_login_prompt';

    public static function getSubscribedEvents(): array
    {
        // Le repère appartient à la session recréée par Symfony et non à un appareil.
        return [LoginSuccessEvent::class => ['recordSuccessfulFormLogin', -10]];
    }

    public function recordSuccessfulFormLogin(LoginSuccessEvent $event): void
    {
        $request = $event->getRequest();
        $player = $event->getUser();
        if ($event->getFirewallName() !== 'main' || !$event->getAuthenticator() instanceof FormLoginAuthenticator
            || !$request->isMethod('POST') || $request->attributes->get('_route') !== 'app_login'
            || !$request->hasSession() || !$player instanceof Player || $player->id === null) {
            return;
        }

        // Un cookie « se souvenir de moi » ne correspond pas à une nouvelle connexion volontaire.
        $request->getSession()->set(self::SESSION_KEY, $player->id);
    }

    /**
     * À appeler uniquement pendant le rendu principal d'une page de jeu.
     * Une visite éligible consomme le repère même si aucun lot n'est disponible ce jour-là.
     */
    public function consumeOnGamePage(Request $request, int $playerId, bool $eligible, bool $available): bool
    {
        if (!$eligible || !$request->isMethod('GET') || !$request->hasSession()
            || $request->isXmlHttpRequest()
            || $request->headers->has('Sec-Fetch-Mode') && $request->headers->get('Sec-Fetch-Mode') !== 'navigate'
            || str_contains((string) $request->headers->get('Sec-Purpose'), 'prefetch')) {
            return false;
        }

        $session = $request->getSession();
        if ($session->get(self::SESSION_KEY) !== $playerId) {
            return false;
        }

        // Un seul usage dans la session serveur : pas de copie dans un cookie, l’URL ou le stockage de l’onglet.
        $session->remove(self::SESSION_KEY);

        return $available;
    }
}
