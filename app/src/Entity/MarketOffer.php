<?php

declare(strict_types=1);

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Index(name: 'market_offer_book', columns: ['universe_id', 'resource', 'unit_cents', 'id'])]
final class MarketOffer
{
    #[ORM\Id, ORM\GeneratedValue, ORM\Column]
    public ?int $id = null;

    #[ORM\ManyToOne, ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    public Universe $universe;

    #[ORM\ManyToOne, ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    public PlayerUniverse $seller;

    #[ORM\Column(length: 12)]
    public string $resource;

    #[ORM\Column]
    public int $remaining;

    #[ORM\Column]
    public int $unitCents;
}
