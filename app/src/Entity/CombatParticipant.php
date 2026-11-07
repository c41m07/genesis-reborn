<?php
namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\UniqueConstraint(name: 'combat_event_fleet_unique', columns: ['combat_event_id', 'fleet_id'])]
#[ORM\Index(name: 'combat_participant_event_role_idx', columns: ['combat_event_id', 'role'])]
class CombatParticipant
{
    #[ORM\Id, ORM\GeneratedValue, ORM\Column]
    public ?int $id = null;

    #[ORM\ManyToOne, ORM\JoinColumn(nullable: false)]
    public CombatEvent $combatEvent;

    #[ORM\ManyToOne, ORM\JoinColumn(nullable: false)]
    public Fleet $fleet;

    #[ORM\ManyToOne, ORM\JoinColumn(nullable: false)]
    public PlayerUniverse $playerUniverse;

    #[ORM\Column(length: 20)]
    public string $role;

    #[ORM\Column]
    public \DateTimeImmutable $arrivalAt;

    #[ORM\Column(nullable: true)]
    public ?int $arrivalRound = null;
}
