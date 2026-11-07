<?php
namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'player_email_verification_token')]
#[ORM\Index(name: 'email_verification_player_idx', columns: ['player_id'])]
#[ORM\UniqueConstraint(name: 'email_verification_hash_unique', columns: ['token_hash'])]
class EmailVerificationToken
{
    #[ORM\Id, ORM\GeneratedValue, ORM\Column]
    public ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Player::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    public Player $player;

    #[ORM\Column(name: 'token_hash', length: 64)]
    public string $tokenHash;

    #[ORM\Column(name: 'expires_at')]
    public \DateTimeImmutable $expiresAt;
}
