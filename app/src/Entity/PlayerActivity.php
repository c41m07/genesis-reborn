<?php
namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
class PlayerActivity
{
    #[ORM\Id, ORM\OneToOne, ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    public PlayerUniverse $membership;
    #[ORM\Column]
    public \DateTimeImmutable $trackedSince;
    #[ORM\Column]
    public \DateTimeImmutable $sampledAt;
    #[ORM\Column]
    public \DateTimeImmutable $lastSeenAt;
    #[ORM\Column]
    public int $totalSeconds = 0;
    #[ORM\Column(type: 'json')]
    public array $dailySeconds = [];
}
