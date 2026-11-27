<?php

declare(strict_types=1);

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

/** Vaisseaux donnés et livrés, propriété du Hall, sans flotte personnelle stationnée. */
#[ORM\Entity]
final class GuildAtelierStock
{
    #[ORM\Id]
    #[ORM\OneToOne]
    #[ORM\JoinColumn(name: 'hall_id', nullable: false, onDelete: 'CASCADE')]
    public GuildHall $hall;

    /** @var array<string, array<string, int>> */
    #[ORM\Column(type: 'json')]
    public array $ships = [];

    /** @var array<string,array<string,int>> Places gardées pour les livraisons en vol. */
    #[ORM\Column(type: 'json')]
    public array $returnCapacity = [];
}
