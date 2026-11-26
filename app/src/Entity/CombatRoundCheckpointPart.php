<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
class CombatRoundCheckpointPart
{
    #[ORM\Id, ORM\ManyToOne, ORM\JoinColumn(name: 'combat_event_id', nullable: false, onDelete: 'CASCADE')]
    public CombatEvent $combatEvent;

    #[ORM\Id, ORM\Column(name: 'part_number')]
    public int $partNumber;

    #[ORM\Column(type: 'text')]
    public string $payload;
}
