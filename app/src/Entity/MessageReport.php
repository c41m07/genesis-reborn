<?php
namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Index(name: 'message_report_status_created_idx', columns: ['status', 'created_at'])]
class MessageReport
{
    #[ORM\Id, ORM\GeneratedValue, ORM\Column]
    public ?int $id = null;
    #[ORM\ManyToOne, ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    public SocialMessage $message;
    #[ORM\ManyToOne, ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    public PlayerUniverse $reporter;
    #[ORM\Column(length: 500)]
    public string $reason = '';
    #[ORM\Column(length: 16)]
    public string $status = 'open';
    #[ORM\ManyToOne, ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    public ?Player $reviewedBy = null;
    #[ORM\Column(nullable: true)]
    public ?\DateTimeImmutable $reviewedAt = null;
    #[ORM\Column]
    public \DateTimeImmutable $createdAt;
}
