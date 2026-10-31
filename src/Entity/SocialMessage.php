<?php
namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Index(name: 'social_private_thread_idx', columns: ['sender_id', 'recipient_id', 'id'])]
#[ORM\Index(name: 'social_guild_created_idx', columns: ['guild_id', 'created_at'])]
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

    #[ORM\Column]
    public \DateTimeImmutable $createdAt;

    #[ORM\Column(nullable: true)]
    public ?\DateTimeImmutable $readAt = null;
}
