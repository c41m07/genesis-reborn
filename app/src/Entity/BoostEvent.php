<?php
namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\UniqueConstraint(name: 'boost_event_key_unique', columns: ['event_key'])]
#[ORM\Index(name: 'boost_event_schedule_idx', columns: ['status', 'scheduled_at', 'id'])]
class BoostEvent
{
    #[ORM\Id, ORM\GeneratedValue, ORM\Column]
    public ?int $id = null;

    #[ORM\Column(name: 'event_key', length: 120)]
    public string $eventKey;

    #[ORM\Column(length: 40)]
    public string $templateKey;

    #[ORM\ManyToOne, ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    public Universe $universe;

    #[ORM\Column(length: 16)]
    public string $status = 'scheduled';

    #[ORM\Column(length: 16)]
    public string $format;

    #[ORM\Column(length: 20)]
    public string $rewardMode;

    #[ORM\Column(length: 40)]
    public string $contributionMetric;

    #[ORM\Column]
    public int $durationSeconds;

    #[ORM\Column]
    public \DateTimeImmutable $scheduledAt;

    #[ORM\Column(nullable: true)]
    public ?\DateTimeImmutable $startedAt = null;

    #[ORM\Column(nullable: true)]
    public ?\DateTimeImmutable $endsAt = null;

    #[ORM\Column(nullable: true)]
    public ?\DateTimeImmutable $closedAt = null;

    #[ORM\Column(nullable: true)]
    public ?float $thresholdValue = null;

    #[ORM\Column(nullable: true)]
    public ?float $globalGoal = null;

    #[ORM\Column]
    public float $totalContribution = 0.0;
}
