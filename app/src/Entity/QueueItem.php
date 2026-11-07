<?php
namespace App\Entity;
use Doctrine\ORM\Mapping as ORM;
#[ORM\Entity]
#[ORM\Index(name: 'queue_order', columns: ['planet_id', 'queue_type', 'id'])]
class QueueItem
{
    #[ORM\Id, ORM\GeneratedValue, ORM\Column]
    public ?int $id = null;
    #[ORM\ManyToOne, ORM\JoinColumn(nullable: false)]
    public Planet $planet;
    #[ORM\ManyToOne, ORM\JoinColumn(nullable: false)]
    public PlayerUniverse $playerUniverse;
    #[ORM\Column(length: 16)]
    public string $queueType;
    #[ORM\Column(length: 64)]
    public string $itemSlug;
    #[ORM\Column(length: 16, options: ['default' => 'basic'])]
    public string $tier = 'basic';
    #[ORM\Column(nullable: true)]
    public ?int $itemLevel = null;
    #[ORM\Column]
    public int $quantity = 1;
    #[ORM\Column]
    public int $durationSeconds;
    #[ORM\Column(nullable: true)]
    public ?\DateTimeImmutable $startedAt = null;
    #[ORM\Column]
    public \DateTimeImmutable $queuedAt;
    #[ORM\Column(length: 36)]
    public string $dispatchToken;
    #[ORM\ManyToOne(targetEntity: self::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    public ?self $dependsOn = null;
    #[ORM\Column(type: 'json', nullable: true)]
    public ?array $paidCosts = null;
    public function finishAt(): ?\DateTimeImmutable
    {
        return $this->startedAt?->modify('+'.$this->durationSeconds.' seconds');
    }
}
