<?php
namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\UniqueConstraint(name: 'guild_rank_name_unique', columns: ['guild_id', 'name'])]
class GuildRank
{
    #[ORM\Id, ORM\GeneratedValue, ORM\Column]
    public ?int $id = null;
    #[ORM\ManyToOne, ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    public Guild $guild;
    #[ORM\Column(length: 60)]
    public string $name;
    #[ORM\Column]
    public int $position = 1;
    #[ORM\Column(type: 'json')]
    public array $permissions = [];
    #[ORM\Version, ORM\Column(type: 'integer')]
    public int $version = 1;
}
