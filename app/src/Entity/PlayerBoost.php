<?php
namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'player_boost')]
#[ORM\UniqueConstraint(name: 'player_boost_source_unique', columns: ['source_key'])]
#[ORM\Index(name: 'player_boost_inventory_idx', columns: ['player_universe_id', 'status', 'earned_at', 'id'])]
// Le témoin isole déjà les cadeaux ; l'index reste indépendant des colonnes des anciens formats de boîte.
#[ORM\Index(name: 'player_boost_gift_unread_idx', columns: ['player_universe_id', 'gift_unread', 'status', 'earned_at', 'id'])]
#[ORM\Index(name: 'player_boost_daily_risk_idx', columns: ['player_universe_id', 'source_type', 'earned_at'])]
class PlayerBoost
{
    #[ORM\Id, ORM\GeneratedValue, ORM\Column]
    public ?int $id = null;

    #[ORM\ManyToOne, ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    public PlayerUniverse $playerUniverse;

    #[ORM\Column(length: 20)]
    public string $sourceType;

    #[ORM\Column(length: 120)]
    public string $sourceKey;

    #[ORM\Column(length: 16)]
    public string $status = 'available';
    #[ORM\Column(length: 16, options: ['default'=>'boost'])]
    public string $kind = 'boost';
    #[ORM\Column(type: 'json', nullable: true)]
    public ?array $payload = null;

    #[ORM\Column]
    public \DateTimeImmutable $earnedAt;

    #[ORM\Column(nullable: true)]
    public ?\DateTimeImmutable $usedAt = null;

    // Le badge ne se réveille que lorsque la source crée un nouveau cadeau.
    #[ORM\Column(options: ['default' => false])]
    public bool $giftUnread = false;

    #[ORM\Column(length: 16, nullable: true)]
    public ?string $operationType = null;

    #[ORM\Column(nullable: true)]
    public ?int $operationId = null;
}
