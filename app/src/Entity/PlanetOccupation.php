<?php
namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Index(name:'occupation_reservations_idx',columns:['occupant_id','status'])]
class PlanetOccupation
{
    #[ORM\Id,ORM\GeneratedValue,ORM\Column]
    public ?int $id=null;
    #[ORM\OneToOne,ORM\JoinColumn(nullable:false)]
    public Planet $planet;
    #[ORM\ManyToOne,ORM\JoinColumn(nullable:false)]
    public PlayerUniverse $occupant;
    #[ORM\ManyToOne,ORM\JoinColumn(nullable:false)]
    public Fleet $fleet;
    #[ORM\ManyToOne,ORM\JoinColumn(nullable:false)]
    public CombatEvent $originCombat;
    #[ORM\Column(length:16)]
    public string $status='active';
    #[ORM\Column]
    public \DateTimeImmutable $startedAt;
    #[ORM\Column]
    public \DateTimeImmutable $captureAt;
    #[ORM\Column(nullable:true)]
    public ?\DateTimeImmutable $endedAt=null;
    #[ORM\Column(length:40,nullable:true)]
    public ?string $endReason=null;
    #[ORM\Column(length:36)]
    public string $dispatchToken;

    public function reservesSlot(): bool { return $this->status==='active'; }
}
