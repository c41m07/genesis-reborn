<?php

declare(strict_types=1);

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

/** Matériaux communs déjà livrés, jamais les réserves d’une flotte en route. */
#[ORM\Entity]
final class GuildHallStock
{
    #[ORM\Id]
    #[ORM\OneToOne]
    #[ORM\JoinColumn(name: 'hall_id', nullable: false, onDelete: 'CASCADE')]
    public GuildHall $hall;

    /** @var array<string, int> */
    #[ORM\Column(type: 'json')]
    public array $materials = [];
}
