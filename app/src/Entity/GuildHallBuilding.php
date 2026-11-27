<?php

declare(strict_types=1);

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\UniqueConstraint(name: 'guild_hall_building_slot', columns: ['hall_id', 'slug'])]
final class GuildHallBuilding
{
    public const ATELIER = 'atelier';
    public const DOCK = 'dock';
    public const WAREHOUSE = 'warehouse';

    #[ORM\Id, ORM\GeneratedValue, ORM\Column]
    public ?int $id = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    public GuildHall $hall;

    #[ORM\Column(length: 32)]
    public string $slug;

    // La pose du Hall ne crée aucun bâtiment. Seule une construction terminée ouvre ses opérations.
    #[ORM\Column]
    public int $level = 0;

    // Le niveau acquis ne change qu’à la fin du chantier, pas au paiement.
    #[ORM\Column(nullable: true)]
    public ?\DateTimeImmutable $finishesAt = null;

    #[ORM\Column(length: 36, nullable: true)]
    public ?string $dispatchToken = null;
}
