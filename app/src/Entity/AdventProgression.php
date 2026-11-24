<?php

declare(strict_types=1);

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\UniqueConstraint(name: 'advent_progression_account_universe_unique', columns: ['player_id', 'advent_edition_id', 'universe_id'])]
#[ORM\Index(name: 'advent_progression_edition_idx', columns: ['advent_edition_id'])]
class AdventProgression
{
    #[ORM\Id, ORM\GeneratedValue, ORM\Column]
    public ?int $id = null;
    #[ORM\ManyToOne, ORM\JoinColumn(nullable: false, onDelete: 'RESTRICT')]
    public AdventEdition $adventEdition;
    #[ORM\ManyToOne, ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    public Player $player;
    #[ORM\Column]
    public int $universeId;
    #[ORM\Column(length: 20)]
    public string $bracket;
    #[ORM\Column]
    public \DateTimeImmutable $observedAt;
    #[ORM\Column(length: 16)]
    public string $origin;
}
