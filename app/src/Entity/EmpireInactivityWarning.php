<?php
namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\UniqueConstraint(name: 'inactivity_warning_cycle_unique', columns: ['membership_id', 'activity_at', 'threshold_days'])]
class EmpireInactivityWarning
{
    #[ORM\Id, ORM\GeneratedValue, ORM\Column]
    public ?int $id = null;
    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    public PlayerUniverse $membership;
    #[ORM\Column]
    public \DateTimeImmutable $activityAt;
    #[ORM\Column(type: 'smallint')]
    public int $thresholdDays;
    #[ORM\Column]
    public \DateTimeImmutable $createdAt;
    #[ORM\Column(nullable: true)]
    public ?\DateTimeImmutable $sentAt = null;
}
