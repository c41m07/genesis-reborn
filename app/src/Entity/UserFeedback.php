<?php
namespace App\Entity;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Index(name: 'feedback_created_idx', columns: ['created_at'])]
#[ORM\Index(name: 'feedback_status_idx', columns: ['status'])]
class UserFeedback
{
    #[ORM\Id, ORM\GeneratedValue, ORM\Column] public ?int $id = null;
    #[ORM\ManyToOne, ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')] public Player $player;
    #[ORM\ManyToOne, ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')] public ?Universe $universe = null;
    #[ORM\Column(length: 20)] public string $type;
    #[ORM\Column(length: 20)] public string $priority;
    #[ORM\Column(length: 120)] public string $title;
    #[ORM\Column(type: 'text')] public string $description;
    #[ORM\Column(length: 500, nullable: true)] public ?string $contextUrl = null;
    #[ORM\Column(length: 120, nullable: true)] public ?string $attachmentFilename = null;
    #[ORM\Column(length: 20)] public string $status = 'open';
    #[ORM\Column(type: 'text', nullable: true)] public ?string $internalNote = null;
    #[ORM\Column] public \DateTimeImmutable $createdAt;
}
