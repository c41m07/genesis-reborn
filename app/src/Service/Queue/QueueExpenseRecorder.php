<?php

declare(strict_types=1);

namespace App\Service\Queue;

use App\Entity\{QueueExpense, QueueItem};
use App\Manager\GameManager;
use App\Repository\GameRepository;

/** Écrit les paiements dans la même transaction que le stock et la file. */
final readonly class QueueExpenseRecorder
{
    public function __construct(private GameManager $manager, private GameRepository $repo)
    {
    }

    public function recordPaid(QueueItem $item, string $category): void
    {
        if ($item->paidCosts === null || !in_array($category, ['buildings', 'technologies', 'ships', 'defenses'], true)) {
            throw new \LogicException('Cette commande ne décrit pas une dépense de catalogue.');
        }
        $expense = new QueueExpense();
        $expense->queueItemId = $item->id ?? throw new \LogicException('La commande doit être enregistrée avant le suivi.');
        $expense->playerUniverse = $item->playerUniverse;
        $expense->planetId = $item->planet->id ?? throw new \LogicException('Colonie non enregistrée.');
        $expense->category = $category;
        $expense->itemSlug = $item->itemSlug;
        $expense->tier = $item->tier;
        $expense->itemLevel = $item->itemLevel;
        $expense->quantity = $item->quantity;
        $expense->paid = array_filter($item->paidCosts, static fn ($amount): bool => $amount > 0);
        $expense->paidAt = $item->queuedAt->setTimezone(new \DateTimeZone('UTC'));
        $this->manager->save($expense);
    }

    /** Un remboursement d’avant la collecte ne doit pas fabriquer une dépense historique. */
    public function recordRefund(QueueItem $item, array $amounts, \DateTimeImmutable $at): void
    {
        $expense = $item->id === null ? null : $this->repo->find(QueueExpense::class, $item->id);
        if ($expense !== null && $expense->refundedAt === null) {
            $expense->refunded = array_filter($amounts, static fn ($amount): bool => $amount > 0);
            $expense->refundedAt = $at->setTimezone(new \DateTimeZone('UTC'));
        }
    }
}
