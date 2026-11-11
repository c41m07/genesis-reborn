<?php
namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\UniqueConstraint(name: 'guild_project_contributor', columns: ['project_id','player_universe_id'])]
class GuildProjectContribution
{
    #[ORM\Id, ORM\GeneratedValue, ORM\Column]
    public ?int $id = null;
    #[ORM\ManyToOne, ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    public GuildProject $project;
    #[ORM\Column(name: 'player_universe_id')]
    public int $playerUniverseId;
    #[ORM\Column(name: 'planet_id')]
    public int $planetId;
    #[ORM\Column(type: 'json')]
    public array $resources = [];
    #[ORM\Column(type: 'datetime_immutable')]
    public \DateTimeImmutable $updatedAt;
}
