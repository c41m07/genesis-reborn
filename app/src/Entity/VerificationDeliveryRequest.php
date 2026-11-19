<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

/** Reçu sans adresse ni jeton ; aucun transport n'est encore branché dessus. */
#[ORM\Entity]
#[ORM\Table(name: 'verification_delivery_request')]
#[ORM\UniqueConstraint(name: 'verification_request_key_unique', columns: ['player_id', 'request_key_hash'])]
#[ORM\Index(name: 'verification_request_player_idx', columns: ['player_id', 'state'])]
class VerificationDeliveryRequest
{
    #[ORM\Id, ORM\GeneratedValue, ORM\Column]
    public ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Player::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    public Player $player;

    #[ORM\Column(length: 64)]
    public string $requestKeyHash;

    #[ORM\Column(length: 24)]
    public string $state = 'reserved';

    #[ORM\Column(length: 64, nullable: true)]
    public ?string $claimHash = null;

    #[ORM\Column(nullable: true)]
    public ?\DateTimeImmutable $leaseExpiresAt = null;

    #[ORM\Column(nullable: true)]
    public ?\DateTimeImmutable $resolvedAt = null;

    #[ORM\Column]
    public \DateTimeImmutable $requestedAt;

    #[ORM\Column]
    public \DateTimeImmutable $cooldownUntil;
}
