<?php

declare(strict_types=1);

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Index(name: 'queue_expense_paid', columns: ['player_universe_id', 'paid_at'])]
#[ORM\Index(name: 'queue_expense_refunded', columns: ['player_universe_id', 'refunded_at'])]
final class QueueExpense
{
    /** Identité historique, sans clé étrangère : la commande disparaît à sa résolution. */
    #[ORM\Id]
    #[ORM\Column]
    public int $queueItemId;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    public PlayerUniverse $playerUniverse;

    #[ORM\Column]
    public int $planetId;

    #[ORM\Column(length: 16)]
    public string $category;

    #[ORM\Column(length: 64)]
    public string $itemSlug;

    #[ORM\Column(length: 16)]
    public string $tier;

    #[ORM\Column(nullable: true)]
    public ?int $itemLevel;

    #[ORM\Column]
    public int $quantity;

    /** @var array<string, int|float> */
    #[ORM\Column(type: 'json')]
    public array $paid;

    #[ORM\Column]
    public \DateTimeImmutable $paidAt;

    /** @var array<string, int|float>|null */
    #[ORM\Column(type: 'json', nullable: true)]
    public ?array $refunded = null;

    #[ORM\Column(nullable: true)]
    public ?\DateTimeImmutable $refundedAt = null;
}
