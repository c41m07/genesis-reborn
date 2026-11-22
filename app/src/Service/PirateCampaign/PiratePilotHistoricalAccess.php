<?php

declare(strict_types=1);

namespace App\Service\PirateCampaign;

use App\Entity\Player;
use App\Manager\GameManager;
use App\Service\Colony\ActionGuard;
use Doctrine\DBAL\Exception\LockWaitTimeoutException;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Clock\ClockInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\DependencyInjection\Attribute\When;

/** Les commandes d'une ancienne mission ne sont pas des ordres depuis la colonie active. */
#[When(env: 'dev')]
final readonly class PiratePilotHistoricalAccess
{
    public function __construct(
        private PiratePilotGate $gate,
        private Security $security,
        private GameManager $manager,
        private ActionGuard $guard,
        private EntityManagerInterface $em,
        private ClockInterface $clock,
    ) {
    }

    public function whileCurrent(\Closure $operation): mixed
    {
        if (!$this->gate->isLocallyEnabled()) {
            return null;
        }
        $account = $this->security->getUser();
        if (!$account instanceof Player || $account->id === null || $account->currentPlayerUniverse?->id === null) {
            return null;
        }

        $member = $account->currentPlayerUniverse;

        return $this->manager->transaction(function () use ($account, $member, $operation): mixed {
            try {
                $this->guard->lockMembership($member, currentReads: true);
                // Même ordre que les départs pilotes, sans attendre une mutation du compte.
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
                || (int) $locked['current_player_universe_id'] !== $member->id || !$this->gate->admits($account, $member, $at)) {
                return null;
            }

            return $operation($member, $at);
        });
    }
}
