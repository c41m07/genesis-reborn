<?php
namespace App\Entity;
use Doctrine\ORM\Mapping as ORM;
#[ORM\Entity]
#[ORM\UniqueConstraint(name: 'tech_unique', columns: ['player_universe_id', 'slug', 'tier'])]
class PlayerTechnology
{
    #[ORM\Id, ORM\GeneratedValue, ORM\Column]
    public ?int $id = null;
    #[ORM\ManyToOne, ORM\JoinColumn(nullable: false)]
    public PlayerUniverse $playerUniverse;
    #[ORM\Column(length: 64)]
    public string $slug;
    #[ORM\Column(length: 16, options: ['default' => 'basic'])]
    public string $tier = 'basic';
    #[ORM\Column]
    public int $level = 0;
}
