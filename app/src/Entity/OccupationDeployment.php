<?php
namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
class OccupationDeployment
{
    #[ORM\Id,ORM\GeneratedValue,ORM\Column]
    public ?int $id=null;
    #[ORM\OneToOne,ORM\JoinColumn(nullable:false)]
    public Fleet $fleet;
    #[ORM\ManyToOne,ORM\JoinColumn(nullable:false)]
    public PlanetOccupation $occupation;
    #[ORM\Column(length:36)]
    public string $token;
    #[ORM\Column]
    public \DateTimeImmutable $dispatchedAt;
    #[ORM\Column(nullable:true)]
    public ?\DateTimeImmutable $arrivedAt=null;
}
