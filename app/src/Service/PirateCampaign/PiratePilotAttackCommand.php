<?php

declare(strict_types=1);

namespace App\Service\PirateCampaign;

use App\Entity\{Planet, Player, PlayerUniverse};
use App\Manager\GameManager;
use App\Service\Colony\ActionGuard;
use Doctrine\DBAL\Exception\LockWaitTimeoutException;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Clock\ClockInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\DependencyInjection\Attribute\When;
use Symfony\Component\Security\Csrf\{CsrfToken, CsrfTokenManagerInterface};

/** Préparer puis confirmer strictement la même attaque. Aucune action HTTP n'appelle ce service. */
#[When(env: 'dev')]
#[When(env: 'prod')]
final readonly class PiratePilotAttackCommand
{
    public function __construct(
        private PiratePilotGate $gate,
        private Security $security,
        private ClockInterface $clock,
        private CsrfTokenManagerInterface $csrf,
        private EntityManagerInterface $em,
        private GameManager $manager,
        private ActionGuard $guard,
        private PiratePilotSignalResolver $signals,
        private PirateAttackService $attack,
    ) {
    }

    public function options(Planet $origin, int $mission): ?array
    {
        return $this->withActivePilot($origin, $mission, fn (Player $account, PlayerUniverse $member, PiratePilotSignal $signal): ?array =>
            $this->attack->pilotAttackOptionsLocked($member, $origin, $signal->key, $this->gate));
    }

    public function preview(Planet $origin, int $mission, ?array $submitted): ?array
    {
        return $this->withActivePilot($origin, $mission,
            fn (Player $account, PlayerUniverse $member, PiratePilotSignal $signal): ?array => $this->prepareLocked($account, $member, $origin, $signal, $submitted));
    }

    public function execute(Planet $origin, int $mission, ?array $submitted, string $token): bool
    {
        if ($token === '') {
            return false;
        }

        return $this->withActivePilot($origin, $mission, function (Player $account, PlayerUniverse $member, PiratePilotSignal $signal) use ($origin, $submitted, $token): bool {
            $plan = $this->prepareLocked($account, $member, $origin, $signal, $submitted);
            if ($plan === null || !$this->csrf->isTokenValid(new CsrfToken($plan['intent']->csrfId(), $token))) {
                return false;
            }

            return $this->attack->launchWithPilotFuel(
                $member, $origin, $signal->key, $plan['attempt'], $plan['selection']->composition, $this->gate,
            );
        }) === true;
    }

    private function prepareLocked(Player $account, PlayerUniverse $member, Planet $origin, PiratePilotSignal $signal, ?array $submitted): ?array
    {
        $state = $this->attack->pilotAttackOptionsLocked($member, $origin, $signal->key, $this->gate);
        if ($state === null) {
            return null;
        }
        try {
            $selection = PiratePilotAttackSelection::fromSubmission($submitted, $state['options']);
        } catch (\DomainException) {
            return null;
        }
        if ($selection === null) {
            return null;
        }
        $native = $this->attack->pilotAttackQuoteLocked($member, $origin, $signal->key, $state['attempt'], $selection->composition, $this->gate);
        if ($native === null || !$this->gate->admits($account, $member, $this->clock->now())) {
            return null;
        }
        $intent = $selection->confirmation(
            $account->id, $member->id, $origin->id, $signal->ordinal, $state['attempt'],
            hash('sha256', $signal->progress.$native['world']), $native['quote'],
        );

        return ['selection' => $selection, 'quote' => $native['quote'], 'attempt' => $state['attempt'], 'intent' => $intent];
    }

    private function withActivePilot(Planet $origin, int $mission, \Closure $callback): mixed
    {
        if ($mission < 1 || $origin->id === null || !$this->gate->isAvailable()) {
            return null;
        }
        $account = $this->security->getUser();
        if (!$account instanceof Player || $account->id === null || $account->currentPlayerUniverse?->id === null) {
            return null;
        }
        $member = $account->currentPlayerUniverse;

        return $this->manager->transaction(function () use ($origin, $account, $member, $mission, $callback): mixed {
            try {
                $this->guard->lock($member, $origin, currentReads: true);
                // Même ordre et même relecture sans attente que le scout. Une commande de compte
                // déjà en cours ne peut pas créer un cycle ni être confondue avec cette intention.
                $locked = $this->em->getConnection()->fetchAssociative(
                    'SELECT current_player_universe_id, locked_at, email_verified_at FROM player WHERE id = ? FOR UPDATE NOWAIT',
                    [$account->id],
                );
            } catch (\DomainException|LockWaitTimeoutException) {
                return null;
            }
            $at = $this->clock->now()->setTimezone(new \DateTimeZone('UTC'));
            $verified = is_string($locked['email_verified_at'] ?? null)
                ? \DateTimeImmutable::createFromFormat('!Y-m-d H:i:s', $locked['email_verified_at'], new \DateTimeZone('UTC'))
                : false;
            if (!$locked || $locked['locked_at'] !== null || $verified === false || $verified > $at
                || (int) $locked['current_player_universe_id'] !== $member->id
                || $origin->npcPlanet || !$this->gate->admits($account, $member, $at)) {
                return null;
            }
            $signal = $this->signals->resolveLocked($member, $origin, $at, $mission);
            if ($signal === null || $signal->allowsFirstScout) {
                return null;
            }

            return $callback($account, $member, $signal);
        });
    }
}
