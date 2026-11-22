<?php

namespace App\Service\Notification;

use App\Entity\{Notification, Planet, Player, PlayerUniverse};
use App\Manager\GameManager;
use App\Service\Colony\ActionGuard;
use Doctrine\DBAL\Exception\LockWaitTimeoutException;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\QueryBuilder;
use Psr\Clock\ClockInterface;
use Symfony\Component\HttpFoundation\UriSigner;

final class JournalService
{
    public const KINDS = ['queue', 'arrival', 'combat', 'transport', 'spy', 'conquest'];
    private const VISIBLE_LIMIT = 5;
    private const TICKET_PATH = '/journal-snapshot-v1';

    public function __construct(
        private EntityManagerInterface $em,
        private GameManager $manager,
        private ClockInterface $clock,
        private UriSigner $signer,
        private ActionGuard $guard,
    ) {
    }

    public function open(PlayerUniverse $membership): void
    {
        $this->manager->transaction(function () use ($membership): void {
            $this->manager->lockCurrent($membership);
            $this->assertAccessible($membership);
            $now = $this->clock->now();
            if ($membership->journalStartedAt !== null) {
                $unavailable = $this->pending($membership)->andWhere('n.journalAvailableAt IS NULL');
                $waiting = (int) (clone $unavailable)->select('COUNT(n.id)')->getQuery()->getSingleScalarResult();
                if ($waiting > 0) {
                    // Sous le verrou du journal, chaque nouveau lot reçoit une borne strictement
                    // supérieure aux précédents, même si la seconde ou les IDs se recouvrent.
                    $last = $this->em->createQueryBuilder()
                        ->select('MAX(n.journalAvailableAt)')->from(Notification::class, 'n')
                        ->where('n.recipient = :member')->setParameter('member', $membership)
                        ->getQuery()->getSingleScalarResult();
                    $batchAt = new \DateTimeImmutable($now->format('Y-m-d H:i:s'), new \DateTimeZone('UTC'));
                    if (is_string($last) && $last >= $batchAt->format('Y-m-d H:i:s')) {
                        $batchAt = (new \DateTimeImmutable($last, new \DateTimeZone('UTC')))->modify('+1 second');
                    }
                    $unavailable->update(Notification::class, 'n')->set('n.journalAvailableAt', ':batch')
                        ->setParameter('batch', $batchAt)->getQuery()->execute();
                }
            } else {
                // La reprise exclut les notifications déjà présentes, sans les consulter ni les supprimer.
                $this->em->createQueryBuilder()->update(Notification::class, 'n')->set('n.journalKind', 'NULL')
                    ->where('n.recipient = :member')->andWhere('n.journalAvailableAt IS NULL')
                    ->setParameter('member', $membership)->getQuery()->execute();
                $membership->journalStartedAt = $now;
            }
            $membership->overviewVisitedAt = $now;
        });
    }

    public function unreadCount(PlayerUniverse $membership): int
    {
        return $membership->journalStartedAt === null ? 0
            : (int) $this->pending($membership)->select('COUNT(n.id)')->getQuery()->getSingleScalarResult();
    }

    /** @return array{notifications: list<Notification>, availableCount: int, ticket: ?string} */
    public function data(PlayerUniverse $membership, int $planetId): array
    {
        if ($membership->journalStartedAt === null) {
            return ['notifications' => [], 'availableCount' => 0, 'ticket' => null];
        }

        $available = $this->pending($membership)->andWhere('n.journalAvailableAt IS NOT NULL');
        $snapshot = (clone $available)->select('COUNT(n.id) AS total', 'MAX(n.journalAvailableAt) AS through')
            ->getQuery()->getSingleResult();
        if ($snapshot['through'] === null) {
            return ['notifications' => [], 'availableCount' => 0, 'ticket' => null];
        }

        $notifications = $available->andWhere('n.journalAvailableAt <= :through')
            ->setParameter('through', $snapshot['through'])->orderBy('n.id', 'DESC')
            ->setMaxResults(self::VISIBLE_LIMIT)->getQuery()->getResult();
        // Le navigateur ne reçoit ni les IDs masqués ni le droit d’élargir cette borne.
        $ticket = $this->signer->sign(self::TICKET_PATH . '?' . http_build_query([
            'member' => $membership->id,
            'planet' => $planetId,
            'through' => $snapshot['through'],
        ]), new \DateInterval('PT30M'));

        return ['notifications' => $notifications, 'availableCount' => (int) $snapshot['total'], 'ticket' => $ticket];
    }

    public function consult(Player $player, PlayerUniverse $membership, Planet $planet, string $ticket): int
    {
        if (strlen($ticket) > 300 || !str_starts_with($ticket, self::TICKET_PATH . '?') || !$this->signer->check($ticket)) {
            throw new \DomainException('Consultation expirée. Rouvrez la Vue générale.');
        }
        parse_str((string) parse_url($ticket, PHP_URL_QUERY), $claims);
        if (($claims['member'] ?? null) !== (string) $membership->id || ($claims['planet'] ?? null) !== (string) $planet->id) {
            throw new \DomainException('Consultation inaccessible.');
        }

        $unread = $this->manager->transaction(function () use ($player, $membership, $planet, $claims): ?int {
            try {
                $this->guard->lock($membership, $planet, currentReads: true);
                // Même ordre que la récompense quotidienne : ne pas attendre un compte
                // qui tient déjà son verrou lors d’un changement d’univers ou d’un effacement.
                $account = $this->em->getConnection()->fetchAssociative(
                    'SELECT current_player_universe_id, locked_at FROM player WHERE id = ? FOR UPDATE NOWAIT',
                    [$player->id],
                );
            } catch (\DomainException | LockWaitTimeoutException) {
                return null;
            }
            if ($membership->player?->id !== $player->id || !$account || $account['locked_at'] !== null
                || (int) $account['current_player_universe_id'] !== $membership->id) {
                return null;
            }

            // Les écritures portent sur le lot affiché, pas sur ce qui est arrivé depuis.
            $this->pending($membership)->update(Notification::class, 'n')->set('n.consultedAt', ':now')
                ->andWhere('n.journalAvailableAt <= :through')->setParameter('through', $claims['through'])
                ->setParameter('now', $this->clock->now())->getQuery()->execute();

            return $this->unreadCount($membership);
        });
        if ($unread === null) {
            throw new \DomainException('Consultation inaccessible. Rouvrez la Vue générale.');
        }
        return $unread;
    }

    private function pending(PlayerUniverse $membership): QueryBuilder
    {
        return $this->em->createQueryBuilder()->select('n')->from(Notification::class, 'n')
            ->where('n.recipient = :member')->andWhere('n.journalKind IN (:kinds)')->andWhere('n.consultedAt IS NULL')
            ->setParameter('member', $membership)->setParameter('kinds', self::KINDS);
    }

    private function assertAccessible(PlayerUniverse $membership): void
    {
        if (!$membership->isPlayerControlled() || !$membership->universe->isAccessible($this->clock->now())) {
            throw new \DomainException('Journal inaccessible.');
        }
    }
}
