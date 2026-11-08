<?php
namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
class OccupationEvent
{
    #[ORM\Id,ORM\GeneratedValue,ORM\Column]
    public ?int $id=null;
    #[ORM\ManyToOne,ORM\JoinColumn(nullable:false)]
    public PlanetOccupation $occupation;
    #[ORM\OneToOne,ORM\JoinColumn(nullable:true)]
    public ?CombatEvent $combat=null;
    #[ORM\Column(length:16)]
    public string $action;
    #[ORM\Column]
    public \DateTimeImmutable $occurredAt;
    #[ORM\Column(type:'json')]
    public array $snapshot=[];
}
