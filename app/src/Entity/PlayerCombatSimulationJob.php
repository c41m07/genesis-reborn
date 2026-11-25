<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

/** Dernière simulation différée privée du joueur, indépendante de l’état des flottes. */
#[ORM\Entity]
#[ORM\UniqueConstraint(name: 'combat_sim_job_player', columns: ['player_id'])]
#[ORM\UniqueConstraint(name: 'combat_sim_job_key', columns: ['public_key'])]
#[ORM\Index(name: 'combat_sim_job_expiry', columns: ['expires_at'])]
#[ORM\Index(name: 'combat_sim_job_membership', columns: ['membership_id'])]
class PlayerCombatSimulationJob
{
    #[ORM\Id, ORM\GeneratedValue, ORM\Column]
    public ?int $id = null;
    #[ORM\ManyToOne, ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    public Player $player;
    #[ORM\ManyToOne, ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    public PlayerUniverse $membership;
    #[ORM\Column(length: 36)]
    public string $publicKey;
    #[ORM\Column(length: 36)]
    public string $dispatchToken;
    #[ORM\Column(length: 12)]
    public string $status = 'pending';
    #[ORM\Column(type: 'json')]
    public array $scenario = [];
    #[ORM\Column(type: 'json')]
    public array $trials = [];
    #[ORM\Column]
    public \DateTimeImmutable $expiresAt;
}
