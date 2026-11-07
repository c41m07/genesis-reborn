<?php
namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Index(name: 'transport_created_idx', columns: ['created_at'])]
class TransportReport
{
    #[ORM\Id, ORM\GeneratedValue, ORM\Column]
    public ?int $id = null;
    #[ORM\OneToOne, ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    public Fleet $fleet;
    #[ORM\ManyToOne, ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    public PlayerUniverse $sender;
    #[ORM\ManyToOne, ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    public PlayerUniverse $recipient;
    #[ORM\Column(length: 20)]
    public string $status = 'traveling';
    #[ORM\Column(type: 'json')]
    public array $snapshot = [];
    #[ORM\Column]
    public \DateTimeImmutable $createdAt;
    #[ORM\Column(nullable: true)]
    public ?\DateTimeImmutable $resolvedAt = null;
}
