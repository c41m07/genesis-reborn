<?php
namespace App\Entity;
use Doctrine\ORM\Mapping as ORM;
#[ORM\Entity]
#[ORM\UniqueConstraint(name: 'tech_unique', columns: ['player_universe_id', 'slug'])]
class PlayerTechnology
{
    #[ORM\Id, ORM\GeneratedValue, ORM\Column]
    public ?int $id = null;
    #[ORM\ManyToOne, ORM\JoinColumn(nullable: false)]
    public PlayerUniverse $playerUniverse;
    #[ORM\Column(length: 64)]
    public string $slug;
    #[ORM\Column]
    public int $level = 0;
}
