<?php
namespace App\Entity;
use Doctrine\ORM\Mapping as ORM;
#[ORM\Entity]
#[ORM\UniqueConstraint(name: 'membership_unique', columns: ['player_id', 'universe_id'])]
#[ORM\Index(name: 'membership_activity_idx', columns: ['last_activity_at'])]
#[ORM\Index(name: 'membership_former_player_idx', columns: ['former_player_id'])]
class PlayerUniverse
{
    #[ORM\Id, ORM\GeneratedValue, ORM\Column]
    public ?int $id = null;
    #[ORM\ManyToOne, ORM\JoinColumn(nullable: true)]
    public ?Player $player = null;
    #[ORM\ManyToOne, ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    public ?Player $formerPlayer = null;
    #[ORM\ManyToOne, ORM\JoinColumn(nullable: false)]
    public Universe $universe;
    #[ORM\Column]
    public \DateTimeImmutable $joinedAt;
    #[ORM\Column(nullable: true)]
    public ?\DateTimeImmutable $lastActivityAt = null;
    #[ORM\Column(nullable: true)]
    public ?\DateTimeImmutable $forgottenAt = null;
    #[ORM\Column(nullable: true)]
    public ?\DateTimeImmutable $recoveryProtectedUntil = null;
    #[ORM\Column(nullable: true)]
    public ?\DateTimeImmutable $lastRecoveryProtectionAt = null;
    #[ORM\Column(type: 'json', nullable: true)]
    public ?array $catalogUnlocks = null;

    public function isForgotten(): bool { return $this->forgottenAt !== null; }
    public function isPlayerControlled(): bool { return $this->player !== null && !$this->isForgotten(); }
    public function getDisplayName(): string
    {
        return $this->player?->username ?? ($this->formerPlayer!==null?$this->formerPlayer->username.' · empire oublié':'Empire oublié');
    }

    /** Détachement d'identité ; les autres transitions relèvent du futur service de conversion. */
    public function detachPlayer(\DateTimeImmutable $at): void
    {
        if($this->isForgotten()) return;
        if($this->player===null) throw new \DomainException('Cet empire ne possède pas de joueur à détacher.');
        $player=$this->player;
        $this->formerPlayer=$player;
        $this->player=null;
        $this->forgottenAt=$at->setTimezone(new \DateTimeZone('UTC'));
        if($player->currentPlayerUniverse===$this || ($this->id!==null && $player->currentPlayerUniverse?->id===$this->id)) $player->currentPlayerUniverse=null;
    }
}
