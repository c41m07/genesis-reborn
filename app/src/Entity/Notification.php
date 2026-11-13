<?php
namespace App\Entity;
use Doctrine\ORM\Mapping as ORM;
#[ORM\Entity]
#[ORM\Index(name: 'notification_journal_idx', columns: ['recipient_id', 'consulted_at', 'journal_available_at'])]

class Notification
{
    #[ORM\Id, ORM\GeneratedValue, ORM\Column]
    public ?int $id = null;
    #[ORM\ManyToOne, ORM\JoinColumn(nullable: false)]
    public PlayerUniverse $recipient;
    #[ORM\Column(length: 240)]
    public string $message;
    #[ORM\Column]
    public \DateTimeImmutable $createdAt;
    #[ORM\Column(length: 20, nullable: true)]
    public ?string $journalKind = null;
    #[ORM\Column(nullable: true)]
    public ?\DateTimeImmutable $journalAvailableAt = null;
    #[ORM\Column(nullable: true)]
    public ?\DateTimeImmutable $consultedAt = null;
}
