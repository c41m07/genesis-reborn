<?php
namespace App\Entity;
use Doctrine\ORM\Mapping as ORM;
#[ORM\Entity]
#[ORM\Index(name: 'planet_acquisition_idx', columns: ['owner_id', 'acquired_at'])]
#[ORM\UniqueConstraint(name: 'planet_unique', columns: ['solar_system_id', 'position'])]
class Planet
{
    #[ORM\Id, ORM\GeneratedValue, ORM\Column]
    public ?int $id = null;
    #[ORM\ManyToOne, ORM\JoinColumn(nullable: false)]
    public SolarSystem $solarSystem;
    #[ORM\ManyToOne, ORM\JoinColumn(nullable: true)]
    public ?PlayerUniverse $owner = null;
    #[ORM\Column(nullable: true)]
    public ?\DateTimeImmutable $acquiredAt = null;
    #[ORM\Column(length: 80)]
    public string $name;
    #[ORM\Column]
    public int $position;
    #[ORM\Column(options: ['default' => false])]
    public bool $npcPlanet = false;
    #[ORM\Column(options: ['default' => 1])]
    public int $conquestLevel = 1;
    #[ORM\Column(options: ['default' => false])]
    public bool $npcGarrisonInitialized = false;
    #[ORM\Column(nullable: true)]
    public ?\DateTimeImmutable $buildingQueueStartedAt = null;
    #[ORM\Column(nullable: true)]
    public ?\DateTimeImmutable $researchQueueStartedAt = null;
    #[ORM\Column(nullable: true)]
    public ?\DateTimeImmutable $shipyardQueueStartedAt = null;
    public function acquireBy(PlayerUniverse $owner, \DateTimeImmutable $at): void
    {
        if($this->owner===$owner || ($this->owner?->id!==null && $this->owner->id===$owner->id)) return;
        $this->owner=$owner;
        $this->acquiredAt=$at->setTimezone(new \DateTimeZone('UTC'));
    }

    public function coordinates(): string
    {
        return $this->solarSystem->galaxy->coordinateCode().':'.$this->solarSystem->number().':'.$this->position;
    }
}
