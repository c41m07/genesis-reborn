<?php
namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Index(name: 'combat_event_planet_status_idx', columns: ['planet_id', 'status'])]
class CombatEvent
{
    #[ORM\Id, ORM\GeneratedValue, ORM\Column]
    public ?int $id = null;

    #[ORM\ManyToOne, ORM\JoinColumn(nullable: false)]
    public Planet $planet;

    #[ORM\Column(length: 20)]
    public string $status = 'pending';

    #[ORM\Column]
    public \DateTimeImmutable $startedAt;

    #[ORM\Column]
    public \DateTimeImmutable $resolveAt;

    #[ORM\Column(type: 'bigint')]
    public int $seed;

    #[ORM\Column(length: 36)]
    public string $dispatchToken = '';

    #[ORM\Column(options: ['default' => 0])]
    public int $currentRound = 0;

    #[ORM\Column(type: 'json')]
    public array $combatSnapshot = [];
}
