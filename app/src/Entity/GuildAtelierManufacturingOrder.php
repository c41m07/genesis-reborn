<?php

declare(strict_types=1);

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

/** La commande et son résultat restent communs ; le payeur ne reçoit aucune coque personnelle. */
#[ORM\Entity]
#[ORM\Index(name: 'guild_atelier_mfg_active', columns: ['hall_id', 'completed_at'])]
#[ORM\UniqueConstraint(name: 'guild_atelier_mfg_request', columns: ['sponsor_id', 'request_key'])]
final class GuildAtelierManufacturingOrder
{
    #[ORM\Id, ORM\GeneratedValue, ORM\Column]
    public ?int $id = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    public GuildHall $hall;

    // Les références du paiement sont historiques : perdre une colonie ne doit pas annuler un lot payé.
    #[ORM\Column]
    public int $sponsorId;

    #[ORM\Column]
    public int $colonyId;

    #[ORM\Column(length: 32)]
    public string $requestKey;

    #[ORM\Column(length: 64)]
    public string $shipSlug;

    #[ORM\Column(length: 16)]
    public string $tier;

    #[ORM\Column]
    public int $quantity;

    /** @var array<string,int> */
    #[ORM\Column(type: 'json')]
    public array $paidMaterials = [];

    #[ORM\Column(type: 'bigint')]
    public int $paidElectricity;

    #[ORM\Column(length: 64)]
    public string $confirmation;

    #[ORM\Column(length: 36)]
    public string $dispatchToken;

    #[ORM\Column]
    public \DateTimeImmutable $startedAt;

    #[ORM\Column]
    public \DateTimeImmutable $dueAt;

    #[ORM\Column(nullable: true)]
    public ?\DateTimeImmutable $completedAt = null;

    /** @return array<string,array<string,int>> */
    public function composition(): array
    {
        return [$this->shipSlug => [$this->tier => $this->quantity]];
    }

    public function isDue(string $token, \DateTimeImmutable $now): bool
    {
        return $this->completedAt === null && hash_equals($this->dispatchToken, $token) && $this->dueAt <= $now;
    }
}
