<?php

declare(strict_types=1);

namespace App\Tests\Unit;

use App\Entity\Player;
use App\Security\DailyRewardLoginPrompt;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Http\Authenticator\AuthenticatorInterface;
use Symfony\Component\Security\Http\Authenticator\FormLoginAuthenticator;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\SelfValidatingPassport;
use Symfony\Component\Security\Http\Event\LoginSuccessEvent;

final class DailyRewardLoginPromptTest extends TestCase
{
    public function testOnlyTheFirstEligibleMainNavigationConsumesARealLogin(): void
    {
        $prompt = new DailyRewardLoginPrompt();
        $session = new Session(new MockArraySessionStorage());
        $this->record($prompt, $session, $this->createStub(FormLoginAuthenticator::class));
        $navigation = $this->gameRequest($session);
        self::assertFalse($prompt->consumeOnGamePage($navigation, 8, true, true));
        self::assertFalse($prompt->consumeOnGamePage($navigation, 7, false, true));
        $fetch = $this->gameRequest($session);
        $fetch->headers->set('Sec-Fetch-Mode', 'cors');
        self::assertFalse($prompt->consumeOnGamePage($fetch, 7, true, true));
        $prefetch = $this->gameRequest($session);
        $prefetch->headers->set('Sec-Purpose', 'prefetch');
        self::assertFalse($prompt->consumeOnGamePage($prefetch, 7, true, true));
        self::assertTrue($prompt->consumeOnGamePage($navigation, 7, true, true));
        self::assertFalse($prompt->consumeOnGamePage($this->gameRequest($session), 7, true, true));
    }

    public function testAlreadyClaimedAndAutomaticAuthenticationDoNotOpenLater(): void
    {
        $prompt = new DailyRewardLoginPrompt();
        $session = new Session(new MockArraySessionStorage());
        $this->record($prompt, $session, $this->createStub(AuthenticatorInterface::class));
        self::assertFalse($prompt->consumeOnGamePage($this->gameRequest($session), 7, true, true));

        $this->record($prompt, $session, $this->createStub(FormLoginAuthenticator::class));
        self::assertFalse($prompt->consumeOnGamePage($this->gameRequest($session), 7, true, false));
        self::assertFalse($prompt->consumeOnGamePage($this->gameRequest($session), 7, true, true));

        $this->record($prompt, $session, $this->createStub(FormLoginAuthenticator::class));
        self::assertTrue($prompt->consumeOnGamePage($this->gameRequest($session), 7, true, true));
    }

    private function gameRequest(Session $session): Request
    {
        $request = Request::create('/planetes/1');
        $request->setSession($session);

        return $request;
    }

    private function record(DailyRewardLoginPrompt $prompt, Session $session, AuthenticatorInterface $authenticator): void
    {
        $request = Request::create('/connexion', 'POST');
        $request->attributes->set('_route', 'app_login');
        $request->setSession($session);
        $player = new Player();
        $player->id = 7;
        $passport = new SelfValidatingPassport(new UserBadge('daily_reward_fixture', static fn (): Player => $player));
        $event = new LoginSuccessEvent($authenticator, $passport, $this->createStub(TokenInterface::class), $request, null, 'main');
        $prompt->recordSuccessfulFormLogin($event);
    }
}
