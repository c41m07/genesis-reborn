<?php

declare(strict_types=1);

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Index(name: 'market_trade_outbound', columns: ['universe_id', 'status'])]
#[ORM\Index(name: 'market_trade_week', columns: ['universe_id', 'exchanged_at'])]
#[ORM\Index(name: 'market_trade_owner', columns: ['membership_id', 'id'])]
#[ORM\UniqueConstraint(name: 'market_trade_order', columns: ['membership_id', 'request_key'])]
final class MarketTrade
{
    #[ORM\Id, ORM\GeneratedValue, ORM\Column]
    public ?int $id = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    public Universe $universe;

    // Les récapitulatifs gardent les volumes même après disparition du compte.
    #[ORM\Column]
    public int $membershipId;

    #[ORM\Column]
    public int $originId;

    #[ORM\Column]
    public int $returnPlanetId;

    #[ORM\Column(length: 32)]
    public string $requestKey;

    #[ORM\Column(length: 18)]
    public string $status = 'outbound';

    #[ORM\Column(length: 12)]
    public string $payResource;

    #[ORM\Column]
    public int $payQuantity;

    #[ORM\Column(length: 12)]
    public string $receiveResource;

    #[ORM\Column]
    public int $receiveQuantity;

    #[ORM\Column(type: 'json')]
    public array $ships = [];

    #[ORM\Column]
    public float $oneWayFuel;

    /** Réserve non consommée ; le fret d’hydrogène est porté par pay/receiveQuantity. */
    #[ORM\Column]
    public float $reservedFuel;

    #[ORM\Column]
    public bool $recalled = false;

    #[ORM\Column]
    public \DateTimeImmutable $departedAt;

    #[ORM\Column(nullable: true)]
    public ?\DateTimeImmutable $exchangedAt = null;

    #[ORM\Column(nullable: true)]
    public ?\DateTimeImmutable $dueAt = null;

    #[ORM\Column(length: 36)]
    public string $token = '';
}
