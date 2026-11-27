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
    #[ORM\Column(options: ['default' => 0])]
    public float $storedFuel = 0;
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

    #[ORM\Column(options: ['default' => false])]
    public bool $firstStepsSpy = false;

    /** Le reliquat d’orbite reste à bord pendant le retour, séparé du fret à livrer. */
    public function releaseOrbitFuel(): void
    {
        if ($this->orbitFuel > 0) {
            $this->navigation['unused_fuel_at_dock'] = ($this->navigation['unused_fuel_at_dock'] ?? 0) + $this->orbitFuel;
            $this->reservedFuel = max(0.0, $this->reservedFuel - $this->orbitFuel);
            $this->orbitFuel = 0;
        }
    }

    /** Le carburant encore utilisable et le reliquat du retour sont embarqués avec le fret. */
    public function freight(): array
    {
        $freight = $this->cargo;
        if (isset($freight['hydrogen'])) {
            $unused = $this->navigation['unused_fuel_at_dock'] ?? 0;
            $freight['hydrogen'] = max(0.0, $freight['hydrogen'] - $this->reservedFuel - $unused);
        }
        return $freight;
    }

    public function discardDockedFuel(): void
    {
        $this->cargo = $this->freight();
        unset($this->navigation['unused_fuel_at_dock']);
        $this->reservedFuel = 0;
        $this->storedFuel = 0;
    }
}
