<?php
namespace App\Entity;
use Doctrine\ORM\Mapping as ORM;
#[ORM\Entity]
#[ORM\UniqueConstraint(name: 'discovery_unique', columns: ['player_universe_id', 'solar_system_id'])]
class PlayerDiscovery
{
    #[ORM\Id, ORM\GeneratedValue, ORM\Column]
    public ?int $id = null;
    #[ORM\ManyToOne, ORM\JoinColumn(nullable: false)]
    public PlayerUniverse $playerUniverse;
    #[ORM\ManyToOne, ORM\JoinColumn(nullable: false)]
    public SolarSystem $solarSystem;
}
