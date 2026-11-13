<?php
namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\UniqueConstraint(name: 'reconstruction_membership_planet', columns: ['membership_id', 'planet_id'])]
class EmpireReconstruction
{
    #[ORM\Id, ORM\GeneratedValue, ORM\Column]
    public ?int $id = null;
    #[ORM\ManyToOne, ORM\JoinColumn(nullable: false)]
    public Planet $planet;
    #[ORM\ManyToOne, ORM\JoinColumn(nullable: false)]
    public PlayerUniverse $membership;
    /** Quantités à la conversion, indexées par groupe, modèle et rang. */
    #[ORM\Column(type: 'json')]
    public array $reference = [];
    #[ORM\OneToOne, ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    public ?QueueItem $queueItem = null;
    #[ORM\Column(nullable: true)]
    public ?\DateTimeImmutable $nextAt = null;
    #[ORM\Column(length: 36)]
    public string $dispatchToken = '';
}
