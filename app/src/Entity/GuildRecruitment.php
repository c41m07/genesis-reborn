<?php

declare(strict_types=1);

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\UniqueConstraint(name: 'guild_recruitment_pair', columns: ['guild_id', 'candidate_id'])]
final class GuildRecruitment
{
    public const APPLICATION = 'application';
    public const INVITATION = 'invitation';

    #[ORM\Id, ORM\GeneratedValue, ORM\Column]
    public ?int $id = null;
    #[ORM\ManyToOne, ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    public Guild $guild;
    #[ORM\ManyToOne, ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    public PlayerUniverse $candidate;
    #[ORM\Column(length: 12)]
    public string $kind;
}
