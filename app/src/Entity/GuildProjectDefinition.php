<?php
namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\UniqueConstraint(name: 'guild_project_definition_name', columns: ['universe_id','name'])]
class GuildProjectDefinition
{
    #[ORM\Id, ORM\GeneratedValue, ORM\Column]
    public ?int $id = null;
    #[ORM\ManyToOne, ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    public Universe $universe;
    #[ORM\Column(length: 120)]
    public string $name;
    #[ORM\Column(type: 'text')]
    public string $description = '';
    #[ORM\Column(type: 'json')]
    public array $stages = [];
    #[ORM\Column(type: 'json')]
    public array $minimums = [];
    #[ORM\Column]
    public bool $enabled = false;
    #[ORM\Version, ORM\Column(type: 'integer')]
    public int $version = 1;
    #[ORM\Column(type: 'datetime_immutable')]
    public \DateTimeImmutable $createdAt;
    #[ORM\Column(type: 'datetime_immutable')]
    public \DateTimeImmutable $updatedAt;
}
