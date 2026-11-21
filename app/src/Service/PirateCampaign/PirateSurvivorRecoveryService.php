<?php

declare(strict_types=1);

namespace App\Service\PirateCampaign;

use App\Entity\{OutboxMessage, PirateAttackCampaign, Planet, PlayerUniverse};
use App\Manager\GameManager;
use App\Message\PirateSurvivorsDue;
use App\Repository\GameRepository;
use App\Service\Catalog\GameCatalog;
use App\Service\Colony\ActionGuard;
use App\Service\Player\VacationCalendar;
use Psr\Clock\ClockInterface;
use Symfony\Component\Uid\Uuid;

/** Commandes privées sans route. Le futur contrôleur devra appliquer le garde pilote local et le CSRF. */
final readonly class PirateSurvivorRecoveryService
{
    public function __construct(
        private GameManager $manager,
        private GameRepository $repository,
        private ActionGuard $guard,
        private VacationCalendar $vacations,
        private GameCatalog $catalog,
        private PirateSurvivorRecoveryEvidence $evidence,
        private PirateSurvivorFallbackQuote $quotes,
        private PirateRecoveryHangar $hangar,
        private ClockInterface $clock,
    ) {
    }

    /** @return array{target_id:int,seconds:int,hydrogen:float,remaining:float,digest:string}|null */
    public function preview(PlayerUniverse $owner, int $campaignId, int $attempt, int $revision, int $targetId): ?array
    {
        return $this->manager->transaction(function () use ($owner, $campaignId, $attempt, $revision, $targetId): ?array {
            return $this->plan($owner, $campaignId, $attempt, $revision, $targetId)['summary'] ?? null;
        });
    }

    /** Le clic n'envoie qu'une sélection et le digest autorisé ; coût, ETA et profils sont recalculés. */
    public function launch(PlayerUniverse $owner, int $campaignId, int $attempt, int $revision, int $targetId, string $approvedDigest): bool
    {
        if (preg_match('/^[a-f0-9]{64}$/D', $approvedDigest) !== 1) {
            return false;
        }

        return $this->manager->transaction(function () use ($owner, $campaignId, $attempt, $revision, $targetId, $approvedDigest): bool {
            $plan = $this->plan($owner, $campaignId, $attempt, $revision, $targetId);
            if ($plan === null || !hash_equals($plan['summary']['digest'], $approvedDigest)) {
                return false;
            }
            $leg = PirateSurvivorRecoveryLeg::depart($plan['evidence'], $plan['quote'], $plan['coords'], Uuid::v4()->toRfc4122(), $plan['at']);
            // L'existence de ce checkpoint consomme irréversiblement l'ancienne preuve pour tout clic ultérieur.
            // La preuve d'origine reste comme témoin d'audit et comme source de vérification du même job.
            $plan['campaign']->timeline['pilot_recovery'] = $this->checkpoint($leg);
            $event = new OutboxMessage();
            $event->kind = PirateSurvivorsDue::KIND;
            $event->targetId = $campaignId;
            $event->token = $leg->token;
            $event->dueAt = $leg->dueAt;
            $this->manager->save($event);

            return true;
        });
    }

    private function plan(PlayerUniverse $owner, int $campaignId, int $attempt, int $revision, int $targetId): ?array
    {
        if ($campaignId < 1 || $attempt < 1 || $targetId < 1 || $revision < 1 || $owner->id === null) {
            return null;
        }
        // Tant que la participation commune n'est pas verrouillée, ne pas déréférencer
        // une relation paresseuse : MariaDB pourrait figer son instantané avant le premier départ.
        $this->guard->lockMembership($owner, true);
        if ($owner->universe->slug !== 'omega-1' || $owner->player?->id === null
            || $owner->player->lockedAt !== null || $owner->player->emailVerifiedAt === null) {
            return null;
        }
        $campaign = $this->repository->lockPirateAttack($campaignId, $owner);
        $this->vacations->assertCanSendFleet($owner);
        if ($campaign === null || isset($campaign->timeline['pilot_recovery']) || $campaign->originId === $targetId) {
            return null;
        }
        $origin = $this->lockPlanet($campaign->originId);
        $target = $this->lockPlanet($targetId);
        $at = $this->now(true);
        if (!$owner->universe->isAccessible($at) || !$this->isOwned($target, $owner)
            || ($origin !== null && $origin->owner?->id === $owner->id)) {
            return null;
        }
        $read = $this->evidence->read($campaign, $owner, $at);
        if ($read === null || $read[0]->attempt !== $attempt || $read[0]->carrierRevision !== $revision) {
            return null;
        }
        [$evidence, , $profiles] = $read;
        $timeMultiplier = $this->catalog->forUniverse($owner->universe)['multipliers']['travel_time'];
        $fuelPolicy = $this->catalog->rules()['fuel_ratio_policy'];
        $quote = $this->quotes->propose($campaign, $owner, $origin, $target, $evidence, $revision, $profiles, $at, 1.0, $timeMultiplier, $fuelPolicy);
        if ($quote === null) {
            return null;
        }
        $coords = $this->coords($target);
        // Pas d'horloge du clic dans le digest : une validation quelques secondes après le devis
        // conserve le prix et la durée exacts mais démarre l'horloge uniquement au vrai départ.
        $digest = hash('sha256', json_encode([
            'version' => 1, 'account' => $owner->player->id, 'membership' => $owner->id,
            'universe' => $owner->universe->id, 'campaign' => $campaignId, 'attempt' => $attempt, 'revision' => $revision,
            'evidence' => $campaign->timeline['pilot_blocked_return'], 'profiles' => $profiles,
            'target' => $targetId, 'coordinates' => $coords, 'duration' => $quote['seconds'], 'cost' => $quote['hydrogen'],
        ], JSON_THROW_ON_ERROR | JSON_PRESERVE_ZERO_FRACTION));

        return [
            'campaign' => $campaign, 'evidence' => $evidence, 'quote' => $quote, 'coords' => $coords, 'at' => $at,
            'summary' => [
                'target_id' => $targetId, 'seconds' => $quote['seconds'], 'hydrogen' => $quote['hydrogen'],
                'remaining' => $evidence->remainingHydrogen - $quote['hydrogen'], 'digest' => $digest,
            ],
        ];
    }

    public function processDue(int $campaignId, string $token): ?int
    {
        $candidate = $this->repository->find(PirateAttackCampaign::class, $campaignId);
        if (!$candidate instanceof PirateAttackCampaign) {
            return null;
        }
        $owner = $candidate->reconnaissance->membership();

        return $this->manager->transaction(function () use ($campaignId, $token, $owner): ?int {
            $this->manager->lockCurrent($owner);
            $this->manager->lockCurrent($owner->universe);
            $campaign = $this->repository->lockPirateAttack($campaignId, $owner);
            $stored = $campaign?->timeline['pilot_recovery'] ?? null;
            if (!is_array($stored) || ($stored['state'] ?? null) !== PirateSurvivorRecoveryLeg::TRAVELING
                || !is_string($stored['token'] ?? null) || !hash_equals($stored['token'], $token)) {
                return null;
            }
            $now = $this->now(false);
            $read = $this->evidence->read($campaign, $owner, $now);
            if ($read === null) {
                return null; // Empire disparu ou preuve non cohérente : aucune destination implicite.
            }
            $leg = $this->restore($stored, $read[0]);
            if ($leg === null || !$leg->isCurrent($campaignId, $read[0]->attempt, $read[0]->carrierRevision + 1, $token)) {
                return null;
            }
            if ($now < $leg->dueAt) {
                return ($leg->dueAt->getTimestamp() - $now->getTimestamp()) * 1000;
            }
            $target = $this->lockPlanet($leg->targetId);
            $arrivedAtOwned = $this->isOwned($target, $owner) && $this->coords($target) === $leg->destination;
            $installed = $arrivedAtOwned && $this->hangar->install($owner, $target, $campaign->reservedShips, $leg->remainingHydrogen());
            $observed = $this->now(false);
            if (!$owner->universe->isAccessible($observed)) {
                throw new \DomainException('L’univers a fermé avant le traitement du repli.');
            }
            $outcome = $leg->arrive($campaignId, $read[0]->attempt, $read[0]->carrierRevision + 1, $token, $observed, $arrivedAtOwned, $installed);
            if ($outcome === null) {
                throw new \LogicException('L’arrivée ne correspond plus au job de repli.');
            }
            $campaign->timeline['pilot_recovery'] = $this->checkpoint($outcome);
            if ($outcome->state === PirateSurvivorRecoveryLeg::DELIVERED) {
                $campaign->reservedShips = [];
                $campaign->returnBlocked = false;
                $campaign->timeline['settled'] = $observed->format(DATE_ATOM);
            }
            // Si non livré, le lot/reliquat reste au point atteint, dans le checkpoint terminal.
            // Il n'est pas éligible à une seconde commande avec le certificat initial.

            return null;
        });
    }

    private function checkpoint(PirateSurvivorRecoveryLeg $leg): array
    {
        return [
            'version' => 1, 'generation' => $leg->generation, 'token' => $leg->token, 'state' => $leg->state,
            'attempt' => $leg->attempt, 'target' => $leg->targetId, 'destination' => $leg->destination,
            'departure' => $leg->departedAt->format(DATE_ATOM), 'due' => $leg->dueAt->format(DATE_ATOM),
            'observed' => $leg->observedAt?->format(DATE_ATOM),
            'initial' => $leg->initialHydrogen, 'cost' => $leg->legHydrogen,
        ];
    }

    private function restore(array $saved, PirateBlockedReturnEvidence $evidence): ?PirateSurvivorRecoveryLeg
    {
        try {
            $start = $this->readUtcSecond($saved['departure'] ?? null);
            $due = $this->readUtcSecond($saved['due'] ?? null);
            $initial = $saved['initial'] ?? null;
            if (($saved['version'] ?? null) !== 1 || ($saved['attempt'] ?? null) !== $evidence->attempt
                || ($saved['generation'] ?? null) !== $evidence->carrierRevision + 1
                || !array_key_exists('observed', $saved) || $saved['observed'] !== null
                || $start === null || $due === null || !is_array($saved['destination'] ?? null)
                || (!is_int($initial) && !is_float($initial)) || (float) $initial !== $evidence->remainingHydrogen) {
                return null;
            }

            return PirateSurvivorRecoveryLeg::depart($evidence, [
                'target_id' => $saved['target'] ?? null, 'carrier_revision' => $evidence->carrierRevision,
                'seconds' => $due->getTimestamp() - $start->getTimestamp(), 'hydrogen' => $saved['cost'] ?? null, 'arrival_at' => $due,
            ], $saved['destination'], $saved['token'], $start);
        } catch (\LogicException|\ValueError) {
            return null;
        }
    }

    private function readUtcSecond(mixed $raw): ?\DateTimeImmutable
    {
        $time = is_string($raw) ? \DateTimeImmutable::createFromFormat(DATE_ATOM, $raw) : false;

        return $time !== false && $time->getOffset() === 0 && $time->format(DATE_ATOM) === $raw ? $time : null;
    }

    private function lockPlanet(int $id): ?Planet
    {
        $planet = $this->repository->find(Planet::class, $id);
        if ($planet !== null) {
            $this->manager->lockCurrent($planet);
        }

        return $planet;
    }

    private function isOwned(?Planet $planet, PlayerUniverse $owner): bool
    {
        return $planet !== null && $planet->owner?->id === $owner->id
            && $planet->owner->player?->id === $owner->player?->id
            && $planet->solarSystem->galaxy->universe->id === $owner->universe->id;
    }

    private function coords(Planet $planet): array
    {
        $system = $planet->solarSystem;

        return [$system->id, $system->galaxy->id, $system->coordX, $system->coordY, $planet->position];
    }

    private function now(bool $ceil): \DateTimeImmutable
    {
        $now = $this->clock->now()->setTimezone(new \DateTimeZone('UTC'));

        // La date SQL est à la seconde. Ne jamais raccourcir le vol en arrondissant le départ en arrière.
        return $now->setTimestamp($now->getTimestamp() + ($ceil && $now->format('u') !== '000000' ? 1 : 0));
    }
}
