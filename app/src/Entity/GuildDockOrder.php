<?php

declare(strict_types=1);

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Index(name: 'guild_dock_order_universe', columns: ['universe_id'])]
#[ORM\UniqueConstraint(name: 'guild_dock_order_request', columns: ['membership_id', 'request_key'])]
#[ORM\Index(name: 'guild_dock_order_due', columns: ['completed_at', 'due_at', 'id'])]
#[ORM\Index(name: 'guild_dock_order_hall', columns: ['hall_id', 'completed_at', 'id'])]
final class GuildDockOrder
{
    #[ORM\Id, ORM\GeneratedValue, ORM\Column]
    public ?int $id = null;

    #[ORM\ManyToOne, ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    public Universe $universe;

    // Ces références ne doivent pas supprimer la commande avec le Hall ou la colonie.
    // Les coques immobilisées restent personnelles ; leur restitution exige une décision explicite.
    #[ORM\Column]
    public int $hallId;

    #[ORM\Column]
    public int $membershipId;

    #[ORM\Column]
    public int $originPlanetId;

    #[ORM\Column(length: 32)]
    public string $requestKey;

    #[ORM\Column(length: 64)]
    public string $shipSlug;

    #[ORM\Column(length: 16)]
    public string $sourceTier;

    #[ORM\Column(length: 16)]
    public string $targetTier;

    #[ORM\Column]
    public int $quantity;

    /** @var array<string, int> Montants réellement payés, figés au départ. */
    #[ORM\Column(type: 'json')]
    public array $paidCosts = [];

    #[ORM\Column(length: 36)]
    public string $dispatchToken;

    #[ORM\Column]
    public \DateTimeImmutable $startedAt;

    #[ORM\Column]
    public \DateTimeImmutable $dueAt;

    #[ORM\Column(nullable: true)]
    public ?\DateTimeImmutable $completedAt = null;

    public function matchesRequest(int $hallId, int $originPlanetId, string $shipSlug, string $sourceTier, int $quantity): bool
    {
        return $this->hallId === $hallId
            && $this->originPlanetId === $originPlanetId
            && $this->shipSlug === $shipSlug
            && $this->sourceTier === $sourceTier
            && $this->quantity === $quantity;
    }

    /** À vérifier sous verrou dans la même transaction que la restitution. */
    public function isDue(string $token, \DateTimeImmutable $now): bool
    {
        return $this->completedAt === null
            && $this->dispatchToken === $token
            && $this->dueAt <= $now;
    }
}
