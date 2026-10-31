<?php
namespace App\Entity;
use Doctrine\ORM\Mapping as ORM;
#[ORM\Entity]
#[ORM\UniqueConstraint(name: 'resource_unique', columns: ['planet_id', 'resource_type'])]
class PlanetResource
{
    #[ORM\Id, ORM\GeneratedValue, ORM\Column]
    public ?int $id = null;
    #[ORM\ManyToOne, ORM\JoinColumn(nullable: false)]
    public Planet $planet;
    #[ORM\Column(length: 20)]
    public string $resourceType;
    #[ORM\Column]
    public float $currentStock = 0;
    #[ORM\Column]
    public float $productionRate = 0;
    #[ORM\Column]
    public \DateTimeImmutable $lastUpdatedAt;
}
