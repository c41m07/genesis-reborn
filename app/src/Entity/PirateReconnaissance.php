<?php

declare(strict_types=1);

namespace App\Entity;

use App\Service\PirateCampaign\{PirateConvoyQuote, PirateDistressSite, PirateEncounterSnapshot, PiratePreparedEncounter, PirateScoutFuelReceipt};
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

    // Nullable : un ancien rapport ne permet pas de reconstituer les anciennes statistiques de combat.
    #[ORM\Column(type: 'json', nullable: true)]
    private ?array $preparedEncounter = null;

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
        PirateEncounterSnapshot|PiratePreparedEncounter $encounter,
        ?PirateScoutFuelReceipt $scoutFuel = null,
    ) {
        if ($encounter instanceof PiratePreparedEncounter) {
            $this->preparedEncounter = $encounter->checkpoint();
            $encounter = $encounter->encounterForServer();
        }
        if ($scoutFuel !== null) {
            if ($this->preparedEncounter === null || $dueAt->getTimestamp() - $departedAt->getTimestamp() !== $scoutFuel->seconds) {
                throw new \DomainException('La preuve de carburant ne correspond pas au trajet privé.');
            }
            // Extension additive du JSON : les rapports plus anciens restent lisibles, mais ne deviennent pas des départs payés.
            $this->preparedEncounter['pilot_scout_fuel'] = $scoutFuel->checkpoint();
        }
        $this->composition = $encounter->composition();
        $this->buildCosts = $encounter->buildCosts();
    }

    public function membership(): PlayerUniverse
    {
        return $this->membership;
    }

    public function reportedMissionKeyFor(PlayerUniverse $viewer, \DateTimeImmutable $at): ?string
    {
        return $this->belongsTo($viewer, $at) && $this->hasReportedAt($at) ? $this->missionKey : null;
    }

    /**
     * Preuve d’ancre interne ; le résolveur doit encore vérifier découverte, possession et trajet.
     * Ne pas en sérialiser les coordonnées dans un dossier ou une réponse de commande.
     */
    public function existingSystemFor(PlayerUniverse $viewer, \DateTimeImmutable $at): ?SolarSystem
    {
        return $this->belongsTo($viewer, $at) ? $this->system : null;
    }

    private function belongsTo(PlayerUniverse $viewer, \DateTimeImmutable $at): bool
    {
        return $viewer->id !== null && $viewer->id === $this->membership->id
            && $viewer->isPlayerControlled() && $viewer->player?->id === $this->owner->id
            && $viewer->universe->id === $this->system->galaxy->universe->id
            && $viewer->universe->isAccessible($at);
    }

    /** Renseignement effectivement remis. Réservé à la commande privée, jamais à une vue. */
    public function battleProofFor(PlayerUniverse $membership, \DateTimeImmutable $at): ?array
    {
        if (!$this->belongsTo($membership, $at) || !$this->hasReportedAt($at)) {
            return null;
        }

        if ($this->preparedEncounter === null) {
            return null;
        }
        $encounter = PirateEncounterSnapshot::freeze($this->composition, $this->buildCosts);

        return [
            'reportedAt' => $this->reportedAt,
            'system' => $this->system,
            'prepared' => PiratePreparedEncounter::restore($encounter, $this->preparedEncounter),
        ];
    }

    /**
     * Seul le départ du joueur peut être repéré avant remise. Ne sortir de cet accesseur
     * ni le reçu complet, ni le système de destination, ni une origine historique non attestée.
     */
    public function pilotScoutOriginFor(PlayerUniverse $viewer, \DateTimeImmutable $at): ?int
    {
        return $this->pilotScoutDepartureFor($viewer, $at)['origin'] ?? null;
    }

    /** @return array{origin:int,tier:string}|null */
    public function pilotScoutDepartureFor(PlayerUniverse $viewer, \DateTimeImmutable $at): ?array
    {
        if (!$this->belongsTo($viewer, $at) || $this->departedAt > $at
            || !is_array($this->preparedEncounter['pilot_scout_fuel'] ?? null)) {
            return null;
        }
        try {
            $receipt = PirateScoutFuelReceipt::read($this->preparedEncounter['pilot_scout_fuel']);
        } catch (\DomainException) {
            return null;
        }

        return $this->dueAt->getTimestamp() - $this->departedAt->getTimestamp() === $receipt->seconds
            ? ['origin' => $receipt->originId, 'tier' => $receipt->tier] : null;
    }

    /** Preuve serveur, utile au prochain départ pilote, jamais incluse dans la projection de renseignement. */
    public function pilotScoutFuelFor(PlayerUniverse $membership, \DateTimeImmutable $at): ?PirateScoutFuelReceipt
    {
        if (!$this->belongsTo($membership, $at) || !$this->hasReportedAt($at)
            || !isset($this->preparedEncounter['pilot_scout_fuel'])) {
            return null;
        }
        $receipt = PirateScoutFuelReceipt::read($this->preparedEncounter['pilot_scout_fuel']);
        if ($this->dueAt->getTimestamp() - $this->departedAt->getTimestamp() !== $receipt->seconds) {
            throw new \DomainException('La durée du prélèvement pilote est devenue incohérente.');
        }

        return $receipt;
    }

    /** Le devis n'est visible qu'avec le rapport effectivement remis, jamais par simple échéance. */
    public function convoyQuoteFor(PlayerUniverse $viewer, \DateTimeImmutable $at): ?PirateConvoyQuote
    {
        if (!$this->belongsTo($viewer, $at) || !$this->hasReportedAt($at) || $this->preparedEncounter === null) {
            return null;
        }

        return PirateConvoyQuote::halfOfFrozenCatalogue(PirateEncounterSnapshot::freeze($this->composition, $this->buildCosts));
    }

    private function hasReportedAt(\DateTimeImmutable $at): bool
    {
        return $this->reportedAt !== null && $at >= $this->dueAt && $at >= $this->reportedAt;
    }

    /** @return array{key: string, site: PirateDistressSite}|null Récit interne, sans effectifs, coût ou coordonnées. */
    public function narrativeContextFor(PlayerUniverse $membership, \DateTimeImmutable $at): ?array
    {
        if (!$this->belongsTo($membership, $at) || $at < $this->departedAt || $this->preparedEncounter === null) {
            return null;
        }

        return [
            'key' => $this->preparedEncounter['definition'],
            'site' => PirateDistressSite::inExistingSystem($membership, $this->system),
        ];
    }

    /** Sous verrou : renvoie le délai de rattrapage seulement pour un jeton valide reçu trop tôt. */
    public function reportWhenDue(PlayerUniverse $membership, string $token, \DateTimeImmutable $at): ?int
    {
        if (!$this->belongsTo($membership, $at) || $this->reportedAt !== null
            || !hash_equals($this->dispatchToken, $token)) {
            return null;
        }
        if ($at < $this->dueAt) {
            // Une seconde au minimum évite la boucle immédiate ; les échéances en base sont à la seconde.
            return max(1000, ($this->dueAt->getTimestamp() - $at->getTimestamp()) * 1000);
        }

        // Garder l'heure de remise réelle, sans prétendre que le worker a exécuté à l'heure théorique.
        $this->reportedAt = $at;

        return null;
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

        $reported = $this->hasReportedAt($at);

        return [
            'status' => $reported ? 'reported' : 'traveling',
            'departed_at' => $this->departedAt->format(DATE_ATOM),
            'due_at' => $this->dueAt->format(DATE_ATOM),
            'reported_at' => $reported ? $this->reportedAt->format(DATE_ATOM) : null,
            // Être repéré ne change pas le renseignement de cette rencontre, isolée de l'espionnage courant.
            'composition' => $reported ? $this->composition : null,
            'detected' => $reported ? $this->detected : null,
        ];
    }
}
