<?php
namespace App\Entity;
use Doctrine\ORM\Mapping as ORM;
#[ORM\Entity]

class Fleet
{
    #[ORM\Id, ORM\GeneratedValue, ORM\Column]
    public ?int $id = null;
    #[ORM\ManyToOne, ORM\JoinColumn(nullable: false)]
    public PlayerUniverse $playerUniverse;
    #[ORM\ManyToOne, ORM\JoinColumn(nullable: false)]
    public Planet $homePlanet;
    #[ORM\ManyToOne, ORM\JoinColumn(nullable: true)]
    public ?Planet $destination = null;
    #[ORM\ManyToOne, ORM\JoinColumn(nullable: true)]
    public ?SolarSystem $destinationSystem = null;
    #[ORM\ManyToOne, ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    public ?Planet $returnPlanet = null;
    #[ORM\Column(type: 'json', nullable: true)]
    public ?array $navigation = null;
    #[ORM\Column(length: 20)]
    public string $status = 'idle';
    #[ORM\Column(length: 20)]
    public string $mission = 'idle';
    #[ORM\Column]
    public float $speedRatio = 1;
    #[ORM\Column(type: 'json')]
    public array $cargo = [];
    #[ORM\Column]
    public float $reservedFuel = 0;
    #[ORM\Column]
    public float $orbitFuel = 0;
    #[ORM\Column]
    public int $orbitDurationSeconds = 0;
    #[ORM\Column(nullable: true)]
    public ?\DateTimeImmutable $departureAt = null;
    #[ORM\Column(nullable: true)]
    public ?\DateTimeImmutable $arrivalAt = null;
    #[ORM\Column]
    public int $travelSeconds = 0;
    #[ORM\Column(length: 36)]
    public string $dispatchToken = '';
}
