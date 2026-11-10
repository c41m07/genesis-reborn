<?php
namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\UniqueConstraint(name: 'UNIQ_LEADERBOARD_UNIVERSE', columns: ['universe_id'])]
class LeaderboardSnapshot
{
    #[ORM\Id, ORM\GeneratedValue, ORM\Column]
    public ?int $id = null;
    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    public Universe $universe;
    #[ORM\Column]
    public \DateTimeImmutable $updatedAt;
    #[ORM\Column(nullable: true)]
    public ?\DateTimeImmutable $comparisonAt = null;
    #[ORM\Column(type: 'json')]
    public array $players = [];
    #[ORM\Column(type: 'json')]
    public array $guilds = [];
}
