<?php
namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\UniqueConstraint(name: 'guild_project_once', columns: ['guild_id','definition_id'])]
class GuildProject
{
    #[ORM\Id, ORM\GeneratedValue, ORM\Column]
    public ?int $id = null;
    #[ORM\ManyToOne, ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    public Universe $universe;
    #[ORM\ManyToOne, ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    public GuildProjectDefinition $definition;
    #[ORM\Column(name: 'guild_id')]
    public int $guildId;
    #[ORM\Column(length: 120)]
    public string $guildName;
    #[ORM\Column(type: 'json')]
    public array $snapshot;
    #[ORM\Column(type: 'json')]
    public array $progress = [];
    #[ORM\Column]
    public int $stageIndex = 0;
    #[ORM\Column(length: 16)]
    public string $status = 'active';
    #[ORM\Version, ORM\Column(type: 'integer')]
    public int $version = 1;
    #[ORM\Column(type: 'datetime_immutable')]
    public \DateTimeImmutable $startedAt;
    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    public ?\DateTimeImmutable $completedAt = null;
    #[ORM\Column(type: 'json')]
    public array $eligibleIds = [];
    #[ORM\Column(length: 36, nullable: true)]
    public ?string $rewardToken = null;
    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    public ?\DateTimeImmutable $rewardsDeliveredAt = null;
}
