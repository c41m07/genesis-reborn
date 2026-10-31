<?php
namespace App\Entity;
use Doctrine\ORM\Mapping as ORM;
#[ORM\Entity]

class GuildMember
{
    #[ORM\Id, ORM\GeneratedValue, ORM\Column]
    public ?int $id = null;
    #[ORM\ManyToOne, ORM\JoinColumn(nullable: false)]
    public Guild $guild;
    #[ORM\ManyToOne, ORM\JoinColumn(nullable: false, unique: true)]
    public PlayerUniverse $playerUniverse;
}
