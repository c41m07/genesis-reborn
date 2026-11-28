<?php

declare(strict_types=1);

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
final class ResourceMarket
{
    #[ORM\Id]
    #[ORM\OneToOne]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    public Universe $universe;

    #[ORM\Column]
    public bool $enabled = false;

    /** Le stock physique comprend les lots déjà réservés mais pas encore remis. */
    #[ORM\Column(type: 'json')]
    public array $stock = [];

    #[ORM\Column(type: 'json')]
    public array $targets = [];

    /** Dernier prix réellement payé entre joueurs, en centièmes de crédit. */
    #[ORM\Column(type: 'json', nullable: true)]
    public ?array $lastPlayerPrices = null;

    /** Demande nette des transactions réellement exécutées, hors marchands. */
    #[ORM\Column(type: 'json')]
    public array $pressure = [];

    /** Date civile du dernier vendredi résolu (ou antérieur à l’ouverture). */
    #[ORM\Column(length: 10)]
    public string $lastOccurrence = '';
}
