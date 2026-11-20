<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

/** Une ligne par compte ; la version reste avant le remplacement suivant. */
#[ORM\Entity]
#[ORM\Table(name: 'player_verification_generation')]
#[ORM\UniqueConstraint(name: 'verification_generation_fingerprint_unique', columns: ['fingerprint'])]
class PlayerVerificationGeneration
{
    #[ORM\Id, ORM\OneToOne(targetEntity: Player::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    public Player $player;

    #[ORM\Column]
    public int $version;

    #[ORM\Column]
    public int $algorithm;

    #[ORM\Column(length: 32)]
    public string $keyId;

    #[ORM\Column(length: 64)]
    public string $nonce;

    #[ORM\Column(type: 'bigint')]
    public int $expiresAt;

    #[ORM\Column(length: 64)]
    public string $fingerprint;

    #[ORM\Column(length: 16)]
    public string $state;
}
