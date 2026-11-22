<?php

declare(strict_types=1);

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\UniqueConstraint(name: 'guild_hall_donor_unique', columns: ['hall_id', 'member_id'])]
#[ORM\Index(name: 'guild_hall_donor_strength_idx', columns: ['hall_id', 'strength'])]
class GuildHallContribution
{
    #[ORM\Id, ORM\GeneratedValue, ORM\Column]
    public ?int $id = null;

    #[ORM\ManyToOne, ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    public GuildHall $hall;

    // La participation est attestée au débit ; son identifiant demeure stable
    // si le membre quitte puis rejoint la guilde. Pas de nom ni d'identité de compte.
    #[ORM\Column]
    public int $memberId;

    #[ORM\Column]
    public int $strength = 0;
}
