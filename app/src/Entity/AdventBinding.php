<?php

declare(strict_types=1);

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\UniqueConstraint(name: 'advent_binding_account_edition_unique', columns: ['player_id', 'advent_edition_id'])]
#[ORM\Index(name: 'advent_binding_edition_idx', columns: ['advent_edition_id'])]
class AdventBinding
{
    #[ORM\Id, ORM\GeneratedValue, ORM\Column]
    public ?int $id = null;
    #[ORM\ManyToOne, ORM\JoinColumn(nullable: false, onDelete: 'RESTRICT')]
    public AdventEdition $adventEdition;
    #[ORM\ManyToOne, ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    public Player $player;
    // Même après fermeture ou retraite de l’univers, aucun transfert implicite.
    #[ORM\Column]
    public int $universeId;
    #[ORM\Column(length: 20)]
    public string $bracket;
    #[ORM\Column]
    public \DateTimeImmutable $joinedAt;
    #[ORM\Column]
    public \DateTimeImmutable $boundAt;
}
