<?php

declare(strict_types=1);

namespace App\Service\Advent;

use App\Entity\{AdventClaim, Planet, PlayerUniverse, QueueItem};
use Doctrine\DBAL\Exception\LockWaitTimeoutException;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Clock\ClockInterface;

final readonly class AdventQueueCredits
{
    public function __construct(
        private AdventEditions $editions,
        private AdventAllowance $allowance,
        private EntityManagerInterface $em,
        private ClockInterface $clock,
    ) {}

    /**
     * Doit rester dans la transaction d’ajout en file, après les prérequis natifs.
     * @param array<string,int> $costs
     * @return array{claim: AdventClaim, paid: array<string,int>}
     */
    public function paymentLocked(int $id, PlayerUniverse $membership, string $group, string $slug, string $tier, int $level, int $quantity, bool $fromCompletedLevel, array $costs): array
    {
        if (!$this->editions->enabled || $membership->player?->id === null) {
            throw new \DomainException('Remise de l’Avent indisponible.');
        }
        $db = $this->em->getConnection();
        // Les verrous jeu sont déjà détenus. Comme pour la récompense
        // quotidienne, ne pas attendre sur un compte en cours de bascule.
        try {
            $account = $db->fetchAssociative(
                'SELECT current_player_universe_id, locked_at, email_verified_at FROM player WHERE id = ? FOR UPDATE NOWAIT',
                [$membership->player->id],
            );
        } catch (LockWaitTimeoutException $error) {
            throw new \DomainException('Une autre action du compte est en cours. Réessayez.', previous: $error);
        }
        if ($account === false || $account['locked_at'] !== null || $account['email_verified_at'] === null
            || (int) $account['current_player_universe_id'] !== $membership->id) {
            throw new \DomainException('Remise de l’Avent indisponible dans cet univers.');
        }
        $row = $db->fetchAssociative(
            'SELECT c.id, c.award, c.delivered_at FROM advent_claim c
             JOIN advent_binding b ON b.advent_edition_id = c.advent_edition_id AND b.player_id = c.player_id
             WHERE c.id = ? AND c.player_id = ? AND b.universe_id = ? FOR UPDATE',
            [$id, $membership->player->id, $membership->universe->id],
        );
        if ($row === false || $row['delivered_at'] !== null || $row['award'] === null) {
            throw new \DomainException('Remise de l’Avent indisponible ou déjà utilisée.');
        }
        $offer = json_decode($row['award'], true, 512, JSON_THROW_ON_ERROR);
        $research = ($offer['kind'] ?? null) === 'research_discount' && $group === 'technologies'
            && $fromCompletedLevel && !($slug === 'colonial_expansion' && $level === 1);
        $ship = ($offer['kind'] ?? null) === 'ship_voucher' && $group === 'ships'
            && $quantity === 1 && ($offer['slug'] ?? null) === $slug && ($offer['tier'] ?? null) === $tier;
        if ((!$research && !$ship) || !is_int($offer['budget_metal'] ?? null)) {
            throw new \DomainException('Cette commande ne correspond pas à votre remise de l’Avent.');
        }
        $payment = $this->allowance->payment($costs, $offer['budget_metal'], $research);
        if ($payment['discount'] === []) {
            throw new \DomainException('Cette commande ne permet pas d’utiliser votre remise de l’Avent.');
        }

        return ['claim' => $this->em->find(AdventClaim::class, $id), 'paid' => $payment['paid']];
    }

    public function completeLocked(AdventClaim $claim, QueueItem $item, Planet $destination): void
    {
        $claim->redeemedQueueId = $item->id;
        $claim->deliveredAt = $this->clock->now()->setTimezone(new \DateTimeZone('UTC'));
        $claim->deliveredDestinationId = $destination->id;
    }
}
