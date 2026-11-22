<?php

declare(strict_types=1);

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\UniqueConstraint(name: 'pirate_attack_reconnaissance', columns: ['reconnaissance_id'])]
class PirateAttackCampaign
{
    #[ORM\Id, ORM\GeneratedValue, ORM\Column]
    public ?int $id = null;

    #[ORM\ManyToOne, ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    public PirateReconnaissance $reconnaissance;

    // Identifiant gardé même si la colonie disparaît : aucune redirection implicite du retour.
    #[ORM\Column]
    public int $originId;

    #[ORM\Column]
    public int $travelSeconds;

    #[ORM\Column]
    public int $roundSeconds;

    #[ORM\Column(type: 'json')]
    public array $scenario;

    #[ORM\Column(type: 'json')]
    public array $timeline;

    /** Effectifs temporairement retirés du hangar, puis seuls les survivants à l'issue du combat. */
    #[ORM\Column(type: 'json')]
    public array $reservedShips;

    #[ORM\Column(type: 'json', nullable: true)]
    public ?array $report = null;

    #[ORM\Column(type: 'datetime_immutable')]
    public \DateTimeImmutable $departedAt;

    #[ORM\Column(type: 'datetime_immutable')]
    public \DateTimeImmutable $arrivalAt;

    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    public ?\DateTimeImmutable $nextAt = null;

    #[ORM\Column(length: 36, nullable: true)]
    public ?string $dispatchToken = null;

    #[ORM\Column]
    public bool $returnBlocked = false;
}
