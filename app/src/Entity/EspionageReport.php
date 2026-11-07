<?php
namespace App\Entity;
use Doctrine\ORM\Mapping as ORM;
#[ORM\Entity]
#[ORM\Index(name: 'espionage_sender_created_idx', columns: ['sender_id', 'created_at'])]
class EspionageReport
{
    #[ORM\Id, ORM\GeneratedValue, ORM\Column]
    public ?int $id = null;
    #[ORM\ManyToOne, ORM\JoinColumn(nullable: false)]
    public PlayerUniverse $sender;
    #[ORM\ManyToOne, ORM\JoinColumn(nullable: false)]
    public Planet $target;
    #[ORM\Column(length: 16)]
    public string $result = 'success';
    #[ORM\Column]
    public \DateTimeImmutable $createdAt;
    #[ORM\Column]
    public int $probesSent = 1;
    #[ORM\Column]
    public int $intelCategories = 0;
    #[ORM\Column]
    public int $detectionChance = 0;
    #[ORM\Column]
    public int $senderEspionageLevel = 0;
    #[ORM\Column]
    public int $targetEspionageLevel = 0;
    #[ORM\Column]
    public int $targetCounterEspionageLevel = 0;
    #[ORM\Column(type: 'json')]
    public array $snapshot = [];
}
