<?php

declare(strict_types=1);

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\UniqueConstraint(name: 'profile_cosmetic_player_code_unique', columns: ['player_id', 'code'])]
class ProfileCosmetic
{
    #[ORM\Id, ORM\GeneratedValue, ORM\Column]
    public ?int $id = null;

    #[ORM\ManyToOne, ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    public Player $player;

    #[ORM\Column(length: 40)]
    public string $code;

    #[ORM\Column]
    public \DateTimeImmutable $awardedAt;

    #[ORM\Column]
    public bool $equipped = false;
}
