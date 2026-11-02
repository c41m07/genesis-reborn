<?php
namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name:'player_password_reset_token')]
#[ORM\Index(name:'password_reset_player_idx', columns:['player_id'])]
#[ORM\UniqueConstraint(name:'password_reset_hash_unique', columns:['token_hash'])]
class PasswordResetToken
{
    #[ORM\Id, ORM\GeneratedValue, ORM\Column]
    public ?int $id = null;

    #[ORM\ManyToOne(targetEntity:Player::class)]
    #[ORM\JoinColumn(nullable:false, onDelete:'CASCADE')]
    public Player $player;

    #[ORM\Column(name:'token_hash', length:64)]
    public string $tokenHash;

    #[ORM\Column(name:'created_at')]
    public \DateTimeImmutable $createdAt;

    #[ORM\Column(name:'expires_at')]
    public \DateTimeImmutable $expiresAt;
}
