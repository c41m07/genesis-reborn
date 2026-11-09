<?php
namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Index(name: 'vacation_membership_start_idx', columns: ['membership_id', 'started_at'])]
class VacationPeriod
{
    #[ORM\Id, ORM\GeneratedValue, ORM\Column]
    public ?int $id = null;
    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    public PlayerUniverse $membership;
    #[ORM\Column]
    public \DateTimeImmutable $startedAt;
    #[ORM\Column]
    public \DateTimeImmutable $scheduledEndAt;
    #[ORM\Column(nullable: true)]
    public ?\DateTimeImmutable $endedAt = null;

    public function effectiveEndAt(): \DateTimeImmutable
    {
        return $this->endedAt !== null && $this->endedAt < $this->scheduledEndAt ? $this->endedAt : $this->scheduledEndAt;
    }

    public function isActiveAt(\DateTimeImmutable $at): bool
    {
        return $this->startedAt <= $at && $at < $this->effectiveEndAt();
    }
}
