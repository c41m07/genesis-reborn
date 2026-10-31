<?php
namespace App\Entity;
use Doctrine\ORM\Mapping as ORM;
#[ORM\Entity]
#[ORM\UniqueConstraint(name: 'system_unique', columns: ['galaxy_id', 'coord_x', 'coord_y'])]
class SolarSystem
{
    #[ORM\Id, ORM\GeneratedValue, ORM\Column]
    public ?int $id = null;
    #[ORM\ManyToOne, ORM\JoinColumn(nullable: false)]
    public Galaxy $galaxy;
    #[ORM\Column(length: 80)]
    public string $name;
    #[ORM\Column]
    public int $coordX;
    #[ORM\Column]
    public int $coordY;
}
