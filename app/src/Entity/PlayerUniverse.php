<?php
namespace App\Entity;
use Doctrine\ORM\Mapping as ORM;
#[ORM\Entity]
#[ORM\UniqueConstraint(name: 'membership_unique', columns: ['player_id', 'universe_id'])]
class PlayerUniverse
{
    #[ORM\Id, ORM\GeneratedValue, ORM\Column]
    public ?int $id = null;
    #[ORM\ManyToOne, ORM\JoinColumn(nullable: false)]
    public Player $player;
    #[ORM\ManyToOne, ORM\JoinColumn(nullable: false)]
    public Universe $universe;
    #[ORM\Column]
    public \DateTimeImmutable $joinedAt;
    #[ORM\Column(nullable: true)]
    public ?\DateTimeImmutable $recoveryProtectedUntil = null;
    #[ORM\Column(nullable: true)]
    public ?\DateTimeImmutable $lastRecoveryProtectionAt = null;
    #[ORM\Column(type: 'json', nullable: true)]
    public ?array $catalogUnlocks = null;
}
