<?php

declare(strict_types=1);

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

/** Accord portant sur une version précise d'une flotte privée ; ne déplace aucun vaisseau. */
#[ORM\Entity]
#[ORM\UniqueConstraint(name: 'guild_command_consent_plan_fleet', columns: ['plan_id', 'fleet_id'])]
#[ORM\Index(name: 'guild_command_consent_fleet', columns: ['fleet_id', 'withdrawn_at'])]
final class GuildCommandConsent
{
    #[ORM\Id, ORM\GeneratedValue, ORM\Column]
    public ?int $id = null;

    #[ORM\Column(nullable: true)]
    public ?\DateTimeImmutable $withdrawnAt = null;

    public function __construct(
        #[ORM\ManyToOne]
        #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
        public readonly GuildCommandPlan $plan,
        #[ORM\Column] public readonly int $fleetId,
        #[ORM\Column] public readonly int $ownerId,
        #[ORM\Column] public readonly int $homeColonyId,
        #[ORM\Column(length: 36)] public readonly string $berthToken,
        #[ORM\Column] public readonly \DateTimeImmutable $consentedAt,
    ) {
        if (!$plan->acceptsConsent() || min($fleetId, $ownerId, $homeColonyId) < 1
            || $berthToken === '' || strlen($berthToken) > 36) {
            throw new \InvalidArgumentException('Accord du Centre invalide.');
        }
    }

    /** Vérification d’identité seulement ; l’appelant vérifie sous verrou la guilde, la composition et la route. */
    public function matchesBerth(Fleet $fleet): bool
    {
        return $this->withdrawnAt === null
            && $fleet->status === 'hall_stationed'
            && $fleet->mission === GuildHallConvoy::MISSION
            && $this->fleetId === $fleet->id
            && $this->ownerId === $fleet->playerUniverse->id
            && $this->homeColonyId === $fleet->homePlanet->id
            && hash_equals($this->berthToken, $fleet->dispatchToken);
    }

    public function withdraw(int $ownerId, \DateTimeImmutable $now): bool
    {
        if ($this->ownerId !== $ownerId || !$this->plan->acceptsConsent() || $this->withdrawnAt !== null) {
            return false;
        }

        $this->withdrawnAt = $now;

        return true;
    }
}
