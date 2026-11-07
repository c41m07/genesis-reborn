<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Index(name: 'game_event_schedule_idx', columns: ['status', 'starts_at', 'ends_at'])]
#[ORM\Index(name: 'IDX_GAME_EVENT_UNIVERSE', columns: ['universe_id'])]
class GameEvent
{
    #[ORM\Id, ORM\GeneratedValue, ORM\Column]
    public ?int $id = null;

    #[ORM\Column(length: 120)]
    public string $name;

    #[ORM\Column(type: 'text')]
    public string $description = '';

    #[ORM\Column(length: 40)]
    public string $type;

    #[ORM\Column(length: 20)]
    public string $status = 'draft';

    #[ORM\Column]
    public \DateTimeImmutable $startsAt;

    #[ORM\Column]
    public \DateTimeImmutable $endsAt;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: true, onDelete: 'CASCADE')]
    public ?Universe $universe = null;

    #[ORM\Column(type: 'json')]
    public array $parameters = [];

    #[ORM\Column]
    public bool $visibleToPlayers = false;
}
