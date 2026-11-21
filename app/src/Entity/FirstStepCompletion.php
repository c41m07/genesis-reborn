<?php

declare(strict_types=1);

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\UniqueConstraint(name: 'first_step_membership_key', columns: ['player_universe_id', 'step_key'])]
final class FirstStepCompletion
{
    #[ORM\Id, ORM\GeneratedValue, ORM\Column]
    public ?int $id = null;

    #[ORM\ManyToOne, ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    public PlayerUniverse $playerUniverse;

    #[ORM\Column(length: 24)]
    public string $stepKey;

    #[ORM\Column]
    public \DateTimeImmutable $completedAt;
}
