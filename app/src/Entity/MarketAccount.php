<?php

declare(strict_types=1);

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
final class MarketAccount
{
    #[ORM\Id, ORM\OneToOne, ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    public PlayerUniverse $membership;

    #[ORM\Column(type: 'bigint')]
    public int $cents = 0;

    /** Marchandises libres à A:0:0, hors offres et rapatriements réservés. */
    #[ORM\Column(type: 'json')]
    public array $goods = [];

    public function moveGoods(string $resource, int $delta): void
    {
        $quantity = ($this->goods[$resource] ?? 0) + $delta;
        if ($quantity < 0 || !is_int($quantity)) {
            throw new \DomainException('Quantité indisponible au dépôt de la station.');
        }
        $this->goods[$resource] = $quantity;
    }

    public function moveCredits(int $delta): void
    {
        $balance = $this->cents + $delta;
        if (!is_int($balance) || $balance < 0) {
            throw new \DomainException('Solde de crédits insuffisant ou hors limite.');
        }
        $this->cents = $balance;
    }
}
