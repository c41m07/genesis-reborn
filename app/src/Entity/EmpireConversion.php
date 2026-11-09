<?php
namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
class EmpireConversion
{
    #[ORM\Id, ORM\GeneratedValue, ORM\Column]
    public ?int $id=null;
    #[ORM\OneToOne, ORM\JoinColumn(nullable:false)]
    public PlayerUniverse $membership;
    #[ORM\Column]
    public \DateTimeImmutable $activityAt;
    #[ORM\Column]
    public \DateTimeImmutable $dueAt;
    #[ORM\Column]
    public \DateTimeImmutable $convertedAt;
    #[ORM\Column(type:'json')]
    public array $effects=[];
}
