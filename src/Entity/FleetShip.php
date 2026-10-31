<?php
namespace App\Entity;
use Doctrine\ORM\Mapping as ORM;
#[ORM\Entity]
#[ORM\UniqueConstraint(name: 'fleet_ship_unique', columns: ['fleet_id', 'slug'])]
class FleetShip
{
    #[ORM\Id, ORM\GeneratedValue, ORM\Column]
    public ?int $id = null;
    #[ORM\ManyToOne, ORM\JoinColumn(nullable: false)]
    public Fleet $fleet;
    #[ORM\Column(length: 64)]
    public string $slug;
    #[ORM\Column]
    public int $quantity = 0;
}
