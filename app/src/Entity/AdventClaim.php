<?php

declare(strict_types=1);

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\UniqueConstraint(name: 'advent_claim_account_door_unique', columns: ['player_id', 'advent_edition_id', 'door'])]
#[ORM\Index(name: 'advent_claim_edition_idx', columns: ['advent_edition_id'])]
#[ORM\Index(name: 'advent_claim_destination_idx', columns: ['destination_id'])]
class AdventClaim
{
    #[ORM\Id, ORM\GeneratedValue, ORM\Column]
    public ?int $id = null;

    #[ORM\ManyToOne, ORM\JoinColumn(nullable: false, onDelete: 'RESTRICT')]
    public AdventEdition $adventEdition;

    // Ne pas utiliser la participation ou l’univers : le droit appartient au compte.
    #[ORM\ManyToOne, ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    public Player $player;

    #[ORM\ManyToOne, ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    public ?Planet $destination;

    #[ORM\Column]
    public int $door;

    #[ORM\Column]
    public int $choice;

    /** @var array<string, int> */
    #[ORM\Column(type: 'json')]
    public array $resources;

    #[ORM\Column]
    public \DateTimeImmutable $openedAt;

    #[ORM\Column(nullable: true)]
    public ?\DateTimeImmutable $deliveredAt = null;
}
