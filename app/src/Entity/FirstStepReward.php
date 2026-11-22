<?php

declare(strict_types=1);

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Index(name: 'first_step_reward_source_idx', columns: ['source_membership_id'])]
#[ORM\UniqueConstraint(name: 'first_step_reward_player_key', columns: ['player_id', 'step_key'])]
final class FirstStepReward
{
    #[ORM\Id, ORM\GeneratedValue, ORM\Column]
    public ?int $id = null;

    #[ORM\ManyToOne, ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    public Player $player;

    #[ORM\ManyToOne, ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    public ?PlayerUniverse $sourceMembership = null;

    #[ORM\Column(length: 24)]
    public string $stepKey;

    #[ORM\Column]
    public int $metal = 0;

    #[ORM\Column]
    public int $nano = 0;

    #[ORM\Column(nullable: true)]
    public ?\DateTimeImmutable $claimedAt = null;
}
