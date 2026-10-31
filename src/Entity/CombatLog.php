<?php
namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\UniqueConstraint(name: 'combat_log_event_unique', columns: ['combat_event_id'])]
class CombatLog
{
    #[ORM\Id, ORM\GeneratedValue, ORM\Column]
    public ?int $id = null;

    #[ORM\OneToOne, ORM\JoinColumn(nullable: false)]
    public CombatEvent $combatEvent;

    #[ORM\Column(length: 30)]
    public string $outcome;

    #[ORM\Column(type: 'json')]
    public array $rounds = [];

    #[ORM\Column(type: 'json')]
    public array $snapshot = [];

    #[ORM\Column]
    public \DateTimeImmutable $resolvedAt;
}
