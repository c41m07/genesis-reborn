<?php

declare(strict_types=1);

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\UniqueConstraint(name: 'pirate_report_event', columns: ['reconnaissance_id', 'category', 'sequence_number'])]
#[ORM\Index(name: 'pirate_report_recon', columns: ['reconnaissance_id'])]
class PirateMissionReport
{
    #[ORM\Id, ORM\GeneratedValue, ORM\Column]
    public ?int $id = null;

    public function __construct(
        #[ORM\ManyToOne, ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
        public readonly PirateReconnaissance $reconnaissance,
        #[ORM\Column(length: 12)]
        public readonly string $category,
        #[ORM\Column]
        public readonly int $sequenceNumber,
        #[ORM\Column]
        public readonly \DateTimeImmutable $createdAt,
        #[ORM\Column(length: 255)]
        public readonly string $location,
        #[ORM\Column(length: 24)]
        public string $status,
        #[ORM\Column(type: 'json')]
        public array $snapshot = [],
        #[ORM\Column(nullable: true)]
        public ?\DateTimeImmutable $resolvedAt = null,
    ) {
    }
}
