<?php

declare(strict_types=1);

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\UniqueConstraint(name: 'guild_hall_guild_unique', columns: ['guild_id'])]
#[ORM\UniqueConstraint(name: 'guild_hall_system_unique', columns: ['solar_system_id'])]
class GuildHall
{
    #[ORM\Id, ORM\GeneratedValue, ORM\Column]
    public ?int $id = null;

    #[ORM\ManyToOne, ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    public Guild $guild;

    #[ORM\ManyToOne, ORM\JoinColumn(onDelete: 'CASCADE')]
    public ?SolarSystem $solarSystem = null;

    #[ORM\Column]
    public int $requiredStrength;

    #[ORM\Column]
    public int $requiredDonors;

    #[ORM\Column]
    public int $minimumDonorStrength;

    #[ORM\Column]
    public int $strength = 0;

    #[ORM\Column]
    public int $revision = 0;

    #[ORM\Column]
    public \DateTimeImmutable $startedAt;

    #[ORM\Column(nullable: true)]
    public ?\DateTimeImmutable $placedAt = null;
}
