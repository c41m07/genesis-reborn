<?php

declare(strict_types=1);

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Index(name: 'fleet_fuel_expense_burned', columns: ['player_universe_id', 'burned_at'])]
final class FleetFuelExpense
{
    /** Identifiant unique du passage métier ; le journal survit au retrait de la flotte. */
    #[ORM\Id]
    #[ORM\Column(length: 64)]
    public string $transitionId;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    public PlayerUniverse $playerUniverse;

    #[ORM\Column]
    public int $fleetId;

    #[ORM\Column(length: 32)]
    public string $phase;

    #[ORM\Column]
    public float $hydrogen;

    #[ORM\Column]
    public \DateTimeImmutable $burnedAt;
}
