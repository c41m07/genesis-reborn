<?php
namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Index(name: 'player_report_status_created_idx', columns: ['status', 'created_at'])]
#[ORM\UniqueConstraint(name: 'player_report_request_unique', columns: ['reporter_id', 'request_key'])]
class PlayerReport
{
    #[ORM\Id, ORM\GeneratedValue, ORM\Column]
    public ?int $id = null;
    #[ORM\ManyToOne, ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    public Universe $universe;
    #[ORM\ManyToOne, ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    public ?PlayerUniverse $reporter = null;
    #[ORM\ManyToOne, ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    public ?PlayerUniverse $target = null;
    #[ORM\Column(length: 32)]
    public string $requestKey;
    #[ORM\Column(length: 32)]
    public string $reason;
    #[ORM\Column(length: 1000)]
    public string $comment = '';
    #[ORM\Column(type: 'json')]
    public array $context = [];
    #[ORM\Column(length: 16)]
    public string $status = 'open';
    #[ORM\Column]
    public \DateTimeImmutable $createdAt;
    #[ORM\ManyToOne, ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    public ?Player $reviewedBy = null;
    #[ORM\Column(nullable: true)]
    public ?\DateTimeImmutable $reviewedAt = null;
}
