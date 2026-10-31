<?php
namespace App\Entity;
use Doctrine\ORM\Mapping as ORM;
#[ORM\Entity]
#[ORM\UniqueConstraint(name: 'planet_unique', columns: ['solar_system_id', 'position'])]
class Planet
{
    #[ORM\Id, ORM\GeneratedValue, ORM\Column]
    public ?int $id = null;
    #[ORM\ManyToOne, ORM\JoinColumn(nullable: false)]
    public SolarSystem $solarSystem;
    #[ORM\ManyToOne, ORM\JoinColumn(nullable: true)]
    public ?PlayerUniverse $owner = null;
    #[ORM\Column(length: 80)]
    public string $name;
    #[ORM\Column]
    public int $position;
    #[ORM\Column(nullable: true)]
    public ?\DateTimeImmutable $buildingQueueStartedAt = null;
    #[ORM\Column(nullable: true)]
    public ?\DateTimeImmutable $researchQueueStartedAt = null;
    #[ORM\Column(nullable: true)]
    public ?\DateTimeImmutable $shipyardQueueStartedAt = null;
    public function coordinates(): string
    {
        return $this->solarSystem->galaxy->name.' · ('.$this->solarSystem->coordX.','.$this->solarSystem->coordY.') · '.$this->position;
    }
}
