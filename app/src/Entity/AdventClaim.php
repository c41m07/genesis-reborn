<?php

declare(strict_types=1);

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\UniqueConstraint(name: 'advent_claim_account_door_unique', columns: ['player_id', 'advent_edition_id', 'door'])]
#[ORM\Index(name: 'advent_claim_edition_idx', columns: ['advent_edition_id'])]
class AdventClaim
{
    #[ORM\Id, ORM\GeneratedValue, ORM\Column]
    public ?int $id = null;

    #[ORM\ManyToOne, ORM\JoinColumn(nullable: false, onDelete: 'RESTRICT')]
    public AdventEdition $adventEdition;

    // Ne pas utiliser la participation ou l’univers : le droit appartient au compte.
    #[ORM\ManyToOne, ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    public Player $player;

    // Identifiants historiques, pas de nom ou d’association vers une colonie
    // potentiellement abandonnée. Ne jamais les utiliser comme autorisation.
    #[ORM\Column]
    public int $originalDestinationId;

    #[ORM\Column(nullable: true)]
    public ?int $deliveredDestinationId = null;

    #[ORM\Column]
    public int $door;

    #[ORM\Column]
    public int $choice;

    /** @var array<string, int> */
    #[ORM\Column(type: 'json')]
    public array $resources;

    /** Lot figé pour les compositions et avoirs de l’édition consolidée. */
    #[ORM\Column(type: 'json', nullable: true)]
    public ?array $award = null;

    /** @var array<string,int>|null Montants déjà versés, séparés du droit initial. */
    #[ORM\Column(type: 'json', nullable: true)]
    public ?array $creditedResources = null;

    // L’absence d’échéance distingue les réservations historiques et les
    // avoirs, qui ne doivent pas hériter de la clôture des nouvelles boîtes.
    #[ORM\Column(nullable: true)]
    public ?\DateTimeImmutable $boxExpiresAt = null;

    #[ORM\Column(nullable: true)]
    public ?int $boxUniverseId = null;

    #[ORM\Column(options: ['default' => false])]
    public bool $boxUnread = false;

    #[ORM\Column(nullable: true)]
    public ?int $redeemedQueueId = null;

    #[ORM\Column]
    public \DateTimeImmutable $openedAt;

    #[ORM\Column(nullable: true)]
    public ?\DateTimeImmutable $deliveredAt = null;
}
