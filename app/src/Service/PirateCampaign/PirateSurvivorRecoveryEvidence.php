<?php

declare(strict_types=1);

namespace App\Service\PirateCampaign;

use App\Entity\{PirateAttackCampaign, PlayerUniverse};

/** Lecteur serveur d'une preuve produite par le vrai worker de retour, jamais d'un formulaire. */
final class PirateSurvivorRecoveryEvidence
{
    /** @return array{PirateBlockedReturnEvidence,PirateAttackFuelReceipt,list<array<string,mixed>>}|null */
    public function read(PirateAttackCampaign $campaign, PlayerUniverse $viewer, \DateTimeImmutable $at): ?array
    {
        $proof = $campaign->timeline['pilot_blocked_return'] ?? null;
        $record = $campaign->timeline['pilot_attack_fuel'] ?? null;
        if (($campaign->id ?? 0) < 1 || ($viewer->id ?? 0) < 1 || ($viewer->player?->id ?? 0) < 1
            || ($viewer->universe->id ?? 0) < 1 || !$campaign->returnBlocked || $campaign->dispatchToken !== null || $campaign->nextAt !== null
            || $campaign->reservedShips === [] || $campaign->report === null
            || ($campaign->timeline['arrival_flow'] ?? null) !== 2
            || !in_array($campaign->timeline['state'] ?? null, ['won', 'retryable'], true)
            || $viewer->universe->slug !== 'omega-1' || $campaign->reconnaissance->battleProofFor($viewer, $at) === null
            || !is_array($proof) || !is_array($record) || ($proof['version'] ?? null) !== 1
            || ($proof['campaign'] ?? null) !== $campaign->id || ($proof['origin'] ?? null) !== $campaign->originId
            || ($proof['universe'] ?? null) !== $viewer->universe->id || ($proof['membership'] ?? null) !== $viewer->id
            || ($proof['account'] ?? null) !== $viewer->player?->id || !is_int($attempt = $proof['attempt'] ?? null)
            || $attempt < 1 || $attempt !== ($campaign->timeline['attempt'] ?? null)
            || ($proof['carrier_revision'] ?? null) !== $attempt) {
            return null;
        }
        try {
            $observed = PirateReturnAttestation::checkpointDate($campaign, 'settled');
            if ($observed === null || $observed > $at || $observed->format(DATE_ATOM) !== ($proof['observed_at'] ?? null)) {
                return null;
            }
            $receipt = PirateAttackFuelReceipt::read($record);
            $profiles = $receipt->survivorMovement($campaign->reservedShips);
            if ($profiles === null || $receipt->attempt !== $attempt || $receipt->originId !== $campaign->originId
                || $receipt->budget->legSeconds !== $campaign->travelSeconds
                || $campaign->arrivalAt->getTimestamp() - $campaign->departedAt->getTimestamp() !== $receipt->budget->legSeconds) {
                return null;
            }
            $attestation = new PirateReturnAttestation(
                $campaign->id, $attempt, $campaign->originId, $viewer->universe->id,
                $receipt->origin, [...$receipt->site, null], $receipt->budget,
            );
            if (($proof['phase'] ?? null) === PirateBlockedReturnEvidence::HELD_BEFORE_RETURN) {
                $evidence = $attestation->heldAtBattlefield($campaign, $attempt, $attempt, $observed, false, $profiles);
            } elseif (($proof['phase'] ?? null) === PirateBlockedReturnEvidence::BLOCKED_ARRIVAL) {
                $evidence = $attestation->blockedAtArrival(
                    $campaign, $attempt, $attempt, $observed, false, $receipt->budget->legHydrogen, $profiles,
                );
            } else {
                return null;
            }
        } catch (\LogicException|\ValueError) {
            return null;
        }
        $amount = $proof['hydrogen'] ?? null;
        if ($evidence === null || $evidence->position !== ($proof['position'] ?? null)
            || (!is_int($amount) && !is_float($amount)) || $evidence->remainingHydrogen !== (float) $amount) {
            return null;
        }

        return [$evidence, $receipt, $profiles];
    }
}
