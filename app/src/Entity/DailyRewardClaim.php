<?php

declare(strict_types=1);

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\UniqueConstraint(name: 'daily_reward_account_day', columns: ['player_id', 'claim_date'])]
class DailyRewardClaim
{
    #[ORM\Id, ORM\GeneratedValue, ORM\Column]
    public ?int $id = null;

    #[ORM\ManyToOne, ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    public Player $player;

    #[ORM\Column(type: 'date_immutable')]
    public \DateTimeImmutable $claimDate;

    #[ORM\Column]
    public int $slot;
}
