<?php
namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Index(name: 'mission_trace_window_idx', columns: ['universe_id', 'mission', 'departed_at'])]
class FleetMissionTrace
{
    #[ORM\Id, ORM\GeneratedValue, ORM\Column]
    public ?int $id = null;
    #[ORM\OneToOne, ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    public Fleet $fleet;
    #[ORM\ManyToOne, ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    public Universe $universe;
    #[ORM\ManyToOne(targetEntity: PlayerUniverse::class), ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    public ?PlayerUniverse $sender = null;
    #[ORM\ManyToOne(targetEntity: PlayerUniverse::class), ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    public ?PlayerUniverse $recipient = null;
    #[ORM\Column(length: 20)]
    public string $mission;
    #[ORM\Column]
    public bool $targetWasPlayer = false;
    #[ORM\Column]
    public \DateTimeImmutable $departedAt;
    #[ORM\Column(type: 'json')]
    public array $snapshot = [];
}
