<?php

declare(strict_types=1);

namespace App\Service\PirateCampaign;

use App\Entity\{PirateAttackCampaign, Planet, PlayerUniverse};
use App\Service\Catalog\GameCatalog;
use App\Service\Fleet\MovementCalculator;

/** Proposition privée sans réservation, livraison, repli automatique ou mise à jour du stock. */
final readonly class PirateSurvivorFallbackQuote
{
    private const MAX_SECONDS = 2147483647;
    private const LAST_STORAGE_TIMESTAMP = 253402300799; // Fin de l'année 9999 en UTC.

    public function __construct(private MovementCalculator $movement)
    {
    }

    /**
     * La future commande devra relire ces mêmes entrées sous verrou, avec départ explicite.
     * L'origine nullable est le résultat d'une lecture serveur par originId (pas un choix du joueur).
     * Le profil et sa version viennent du lot survivant courant ; ni le fret H₂ ni un convoi ne constituent cette réserve.
     * @param list<array<string,mixed>> $ships Profils natifs du lot survivant, préparés côté serveur.
     * @return array{target_id:int,carrier_revision:int,seconds:int,hydrogen:float,arrival_at:\DateTimeImmutable}|null
     */
    public function propose(
        PirateAttackCampaign $campaign,
        PlayerUniverse $membership,
        ?Planet $origin,
        Planet $target,
        ?PirateBlockedReturnEvidence $evidence,
        int $carrierRevision,
        array $ships,
        \DateTimeImmutable $now,
        float $ratio,
        float $timeMultiplier,
        string $fuelPolicy,
    ): ?array {
        $universeId = $membership->universe->id;
        if ($evidence === null || $carrierRevision < 1
            || $campaign->id !== $evidence->campaignId || $evidence->campaignId < 1
            || $campaign->originId !== $evidence->originId || $evidence->originId < 1
            || $carrierRevision !== $evidence->carrierRevision
            || $universeId !== $evidence->universeId || $universeId < 1
            || !$campaign->returnBlocked || $campaign->reservedShips === [] || $campaign->report === null
            || ($campaign->timeline['attempt'] ?? null) !== $evidence->attempt || $evidence->attempt < 1
            || !in_array($campaign->timeline['state'] ?? null, ['retryable', 'won'], true)
            || $evidence->arrivedAt > $now || !$this->matchesArrival($campaign, $evidence)
            || ($membership->id ?? 0) < 1 || ($membership->player?->id ?? 0) < 1
            || $membership->player->lockedAt !== null
            || $campaign->reconnaissance->projectFor($membership, $now) === null
            || ($origin !== null && ($origin->id !== $campaign->originId || $origin->owner?->id === $membership->id))
            || ($target->id ?? 0) < 1 || $target->id === $campaign->originId
            || $target->owner?->id !== $membership->id || $target->owner->player?->id !== $membership->player->id
            || $target->owner->universe->id !== $universeId || !$target->owner->isPlayerControlled()
            || $target->solarSystem->galaxy->universe->id !== $universeId
            || !is_finite($evidence->remainingHydrogen) || $evidence->remainingHydrogen < 0
            || !$this->matchesSurvivors($campaign->reservedShips, $ships)) {
            return null;
        }

        $from = $evidence->position;
        $system = $target->solarSystem;
        $to = [$system->id, $system->galaxy->id, $system->coordX, $system->coordY, $target->position];
        $held = $evidence->phase === PirateBlockedReturnEvidence::HELD_BEFORE_RETURN;
        if (!PirateReturnAttestation::validEndpoint($from, $held) || !PirateReturnAttestation::validEndpoint($to, false) || $from[1] !== $to[1]
            || ($from[0] === $to[0] && $from[4] === $to[4])
            || (($from[0] === $to[0]) !== ([$from[2], $from[3]] === [$to[2], $to[3]]))
            || !is_finite($ratio) || $ratio < 0.1 || $ratio > 1
            || !is_finite($timeMultiplier) || $timeMultiplier <= 0
            || !in_array($fuelPolicy, ['proportional', 'inverse'], true)) {
            return null;
        }

        // MovementCalculator ne traite l’ancre sans case que côté destination. Les distances
        // natives de la vraie colonie vers l'ancre sont symétriques, puis le devis est bien un aller simple.
        $distance = $held ? $this->movement->distances($to, $from) : $this->movement->distances($from, $to);
        $systemSpeed = min(array_column($ships, 'speed_system')) * $ratio;
        $galaxySpeed = min(array_column($ships, 'speed_galaxy')) * $ratio;
        if ($systemSpeed <= 0 || $galaxySpeed <= 0 || ($distance['normal'] === 0 && $distance['hyper'] == 0)) {
            return null;
        }

        // Le calculateur natif convertit la durée flottante en int avant d'appliquer le minimum.
        // Vérifier sa borne avant cet appel : un dépassement ne doit pas devenir un court trajet.
        $rawSeconds = ($distance['normal'] / $systemSpeed + $distance['hyper'] / $galaxySpeed) * 3600 * $timeMultiplier;
        if (!is_finite($rawSeconds) || $rawSeconds > self::MAX_SECONDS) {
            return null;
        }
        $quote = $this->movement->quoteDistances($distance['normal'], $distance['hyper'], $distance['same'],
            $ships, $ratio, $timeMultiplier, $fuelPolicy);
        $seconds = $quote['seconds'];
        $hydrogen = (float) $quote['one_way_fuel'];
        $capacity = $quote['capacity'];
        $timestamp = $now->getTimestamp();
        // Même unité flottante que les trajets du jeu. Pas d'arrondi, de tolérance qui accorde du H₂,
        // ni de complément depuis le fret ; l'attestation porte déjà sur la réserve encore embarquée.
        if (!is_finite($hydrogen) || $hydrogen < 0 || !is_finite($capacity)
            || $hydrogen > $evidence->remainingHydrogen || $evidence->remainingHydrogen > $capacity
            || $seconds < 1 || $timestamp > self::LAST_STORAGE_TIMESTAMP - $seconds) {
            return null;
        }

        return [
            'target_id' => $target->id,
            'carrier_revision' => $carrierRevision,
            'seconds' => $seconds,
            'hydrogen' => $hydrogen,
            // Un trajet en secondes écoulées ne change pas au passage d'heure locale.
            'arrival_at' => $now->setTimestamp($timestamp + $seconds),
        ];
    }

    private function matchesArrival(PirateAttackCampaign $campaign, PirateBlockedReturnEvidence $evidence): bool
    {
        // Le drapeau seul ne localise rien : le point doit correspondre au dernier traitement exact.
        $settledAt = PirateReturnAttestation::checkpointDate($campaign, 'settled');
        if ($settledAt === null || $evidence->arrivedAt->getTimestamp() !== $settledAt->getTimestamp()) {
            return false;
        }
        if ($evidence->phase === PirateBlockedReturnEvidence::HELD_BEFORE_RETURN) {
            $resolved = PirateReturnAttestation::checkpointDate($campaign, 'resolved');

            return ($campaign->timeline['returnDue'] ?? null) === null && $resolved !== null
                && $resolved->getTimestamp() === $settledAt->getTimestamp() && $resolved >= $campaign->arrivalAt;
        }

        $dueAt = PirateReturnAttestation::checkpointDate($campaign, 'returnDue');

        return $evidence->phase === PirateBlockedReturnEvidence::BLOCKED_ARRIVAL && $dueAt !== null
            && $settledAt >= $dueAt && $dueAt > $campaign->arrivalAt;
    }

    private function matchesSurvivors(array $survivors, array $ships): bool
    {
        $seen = [];
        foreach ($ships as $ship) {
            if (!is_array($ship)) {
                return false;
            }
            $slug = $ship['slug'] ?? null;
            $tier = $ship['tier'] ?? null;
            $quantity = $ship['quantity'] ?? null;
            if (!is_string($slug) || !is_string($tier) || !in_array($tier, GameCatalog::TIERS, true)
                || !is_int($quantity) || $quantity < 1 || ($survivors[$slug][$tier] ?? null) !== $quantity
                || isset($seen[$slug][$tier]) || ($ship['mobile'] ?? true) !== true) {
                return false;
            }
            foreach (['speed_system','speed_galaxy','fuel_travel_system','fuel_travel_galaxy','fuel_orbit','cargo_capacity','fuel_capacity'] as $field) {
                $value = $ship[$field] ?? ($field === 'fuel_capacity' ? 0 : null);
                if ((!is_int($value) && !is_float($value)) || !is_finite($value) || $value < 0
                    || (str_starts_with($field, 'speed_') && $value == 0)) {
                    return false;
                }
            }
            $seen[$slug][$tier] = $quantity;
        }

        // Comparaison indépendante de l'ordre catalogue, avec quantités strictes contrôlées ci-dessus.
        return $seen !== [] && $seen == $survivors;
    }
}
