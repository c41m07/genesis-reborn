<?php

declare(strict_types=1);

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\UniqueConstraint(name: 'market_merchant_occurrence', columns: ['universe_id', 'occurrence'])]
final class MarketMerchantDelivery
{
    #[ORM\Id, ORM\GeneratedValue, ORM\Column]
    public ?int $id = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    public Universe $universe;

    #[ORM\Column(length: 10)]
    public string $occurrence;

    #[ORM\Column(type: 'json')]
    public array $quantities = [];

    #[ORM\Column]
    public \DateTimeImmutable $appliedAt;
}
