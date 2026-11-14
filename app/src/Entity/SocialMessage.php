<?php
namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Index(name: 'social_private_thread_idx', columns: ['sender_id', 'recipient_id', 'id'])]
#[ORM\Index(name: 'social_guild_created_idx', columns: ['guild_id', 'created_at'])]
#[ORM\UniqueConstraint(name: 'social_submission_unique', columns: ['sender_id', 'submission_id'])]
class SocialMessage
{
    #[ORM\Id, ORM\GeneratedValue, ORM\Column]
    public ?int $id = null;

    #[ORM\ManyToOne, ORM\JoinColumn(nullable: false)]
    public PlayerUniverse $sender;

    #[ORM\ManyToOne, ORM\JoinColumn(nullable: true, onDelete: 'CASCADE')]
    public ?PlayerUniverse $recipient = null;

    #[ORM\ManyToOne, ORM\JoinColumn(nullable: true, onDelete: 'CASCADE')]
    public ?Guild $guild = null;

    #[ORM\Column(type: 'text')]
    public string $content = '';

    #[ORM\Column(length: 16, options: ['default' => 'plain'])]
    public string $contentFormat = 'plain';

    #[ORM\Column(length: 36, nullable: true)]
    public ?string $submissionId = null;

    #[ORM\Column]
    public \DateTimeImmutable $createdAt;

    #[ORM\Column(nullable: true)]
    public ?\DateTimeImmutable $readAt = null;

    #[ORM\Column(nullable: true)]
    public ?\DateTimeImmutable $moderatedAt = null;

    #[ORM\ManyToOne, ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    public ?Player $moderatedBy = null;
}
