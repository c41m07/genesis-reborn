<?php
namespace App\Entity;
use Doctrine\ORM\Mapping as ORM;
#[ORM\Entity]
#[ORM\UniqueConstraint(name: 'defense_unique', columns: ['planet_id', 'slug'])]
class PlanetDefense
{
    #[ORM\Id, ORM\GeneratedValue, ORM\Column]
    public ?int $id = null;
    #[ORM\ManyToOne, ORM\JoinColumn(nullable: false)]
    public Planet $planet;
    #[ORM\Column(length: 64)]
    public string $slug;
    #[ORM\Column]
    public int $quantity = 0;
}
