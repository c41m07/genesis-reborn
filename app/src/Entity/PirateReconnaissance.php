<?php

declare(strict_types=1);

namespace App\Entity;

use App\Service\PirateCampaign\PirateEncounterSnapshot;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\UniqueConstraint(name: 'pirate_recon_participation_mission', columns: ['membership_id', 'mission_key'])]
#[ORM\Index(name: 'pirate_recon_owner_idx', columns: ['owner_id'])]
#[ORM\Index(name: 'pirate_recon_system_idx', columns: ['system_id'])]
class PirateReconnaissance
{
    #[ORM\Id, ORM\GeneratedValue, ORM\Column]
    public ?int $id = null;

    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $reportedAt = null;

    #[ORM\Column(type: 'json')]
    private array $composition;

    #[ORM\Column(type: 'json')]
    private array $buildCosts;

    public function __construct(
        #[ORM\ManyToOne, ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
        private PlayerUniverse $membership,
        #[ORM\ManyToOne, ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
        private Player $owner,
        #[ORM\ManyToOne, ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
        private SolarSystem $system,
        #[ORM\Column(length: 64)]
        private string $missionKey,
        #[ORM\Column(type: 'datetime_immutable')]
        private \DateTimeImmutable $departedAt,
        #[ORM\Column(type: 'datetime_immutable')]
        private \DateTimeImmutable $dueAt,
        #[ORM\Column(length: 36)]
        private string $dispatchToken,
        #[ORM\Column(nullable: true)]
        private ?bool $detected,
        PirateEncounterSnapshot $encounter,
    ) {
        $this->composition = $encounter->composition();
        $this->buildCosts = $encounter->buildCosts();
    }

    public function membership(): PlayerUniverse
    {
        return $this->membership;
    }

    private function belongsTo(PlayerUniverse $viewer, \DateTimeImmutable $at): bool
    {
        return $viewer->id !== null && $viewer->id === $this->membership->id
            && $viewer->isPlayerControlled() && $viewer->player?->id === $this->owner->id
            && $viewer->universe->id === $this->system->galaxy->universe->id
            && $viewer->universe->isAccessible($at);
    }

    /** Sous le verrou de l'entité : l'unique rapport clôt aussi la réservation de sonde. */
    public function reportWhenDue(PlayerUniverse $membership, string $token, \DateTimeImmutable $at): void
    {
        if (!$this->belongsTo($membership, $at) || $this->reportedAt !== null
            || $at < $this->dueAt || !hash_equals($this->dispatchToken, $token)) {
            return;
        }

        // Garder l'heure de remise réelle, sans prétendre que le worker a exécuté à l'heure théorique.
        $this->reportedAt = $at;
    }

    /**
     * Ni destination, ni coûts, ni jeton de traitement dans cette projection.
     * @return array{status: string, departed_at: string, due_at: string, reported_at: ?string, composition: ?array<string,array<string,int>>, detected: ?bool}|null
     */
    public function projectFor(PlayerUniverse $viewer, \DateTimeImmutable $at): ?array
    {
        if (!$this->belongsTo($viewer, $at)) {
            return null;
        }

        return [
            'status' => $this->reportedAt !== null ? 'reported' : 'traveling',
            'departed_at' => $this->departedAt->format(DATE_ATOM),
            'due_at' => $this->dueAt->format(DATE_ATOM),
            'reported_at' => $this->reportedAt?->format(DATE_ATOM),
            // Être repéré ne change pas le renseignement de cette rencontre, isolée de l'espionnage courant.
            'composition' => $this->reportedAt !== null ? $this->composition : null,
            'detected' => $this->reportedAt !== null ? $this->detected : null,
        ];
    }
}
