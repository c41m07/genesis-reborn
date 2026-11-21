<?php

declare(strict_types=1);

namespace App\Service\PirateCampaign;

use App\Entity\PirateAttackCampaign;
use Symfony\Component\DependencyInjection\Attribute\Exclude;

/**
 * Futur instantané privé du trajet. Le worker le relit sous verrou et atteste le point où
 * les porteurs se trouvent, jamais depuis les coordonnées d'une requête ou un drapeau historique.
 */
#[Exclude]
final readonly class PirateReturnAttestation
{
    private array $home;
    private array $battlefield;

    /**
     * À figer au départ : la colonie ou son système pourront disparaître avant le traitement.
     * @param array{int,int,int,int,int} $home
     * @param array{int,int,int,int,null} $battlefield Ancre native du système, pas une case fictive.
     */
    public function __construct(
        private int $campaignId,
        private int $attempt,
        private int $originId,
        private int $universeId,
        array $home,
        array $battlefield,
        private PirateCampaignFuel $fuel,
    ) {
        if ($campaignId < 1 || $attempt < 1 || $originId < 1 || $universeId < 1 || !self::validEndpoint($home, false) || !self::validEndpoint($battlefield, true)
            || $home[1] !== $battlefield[1]
            || (($home[0] === $battlefield[0]) !== ([$home[2], $home[3]] === [$battlefield[2], $battlefield[3]]))) {
            throw new \DomainException('Les points figés du trajet privé sont incohérents.');
        }

        $this->home = array_map(static fn (mixed $part): mixed => $part, $home);
        $this->battlefield = array_map(static fn (mixed $part): mixed => $part, $battlefield);
    }

    /**
     * Le worker a résolu le combat et n’a PAS commencé le retour, après relecture de l’origine.
     * L’argent ou le fret du joueur ne complète jamais le solde retenu.
     * @param list<array<string,mixed>> $survivors Profils retenus pour cette tentative en cours de verrouillage.
     */
    public function heldAtBattlefield(
        PirateAttackCampaign $campaign,
        int $attempt,
        int $carrierRevision,
        \DateTimeImmutable $processedAt,
        bool $originStillOwned,
        array $survivors,
    ): ?PirateBlockedReturnEvidence {
        if ($originStillOwned || !$this->matchesProcessedReturn($campaign, $attempt, $carrierRevision, $processedAt)
            || ($campaign->timeline['returnDue'] ?? null) !== null
            || self::checkpointDate($campaign, 'resolved')?->getTimestamp() !== $processedAt->getTimestamp()) {
            return null;
        }

        return new PirateBlockedReturnEvidence(
            $campaign->id, $attempt, $this->originId, $this->universeId, $carrierRevision, $processedAt,
            $this->battlefield, $this->fuel->afterCombat($campaign->reservedShips, $survivors), PirateBlockedReturnEvidence::HELD_BEFORE_RETURN,
        );
    }

    /**
     * Le vrai retour a été consommé au point géométrique figé ; l'accès à l'ancienne colonie
     * a été réévalué sous verrou. Le trajet terminé ne restitue pas son carburant.
     * @param list<array<string,mixed>> $survivors Profils figés du lot transporté réellement.
     */
    public function blockedAtArrival(
        PirateAttackCampaign $campaign,
        int $attempt,
        int $carrierRevision,
        \DateTimeImmutable $processedAt,
        bool $originStillOwned,
        float $carriedBeforeReturn,
        array $survivors,
    ): ?PirateBlockedReturnEvidence {
        $resolved = self::checkpointDate($campaign, 'resolved');
        $due = self::checkpointDate($campaign, 'returnDue');
        if ($originStillOwned || !$this->matchesProcessedReturn($campaign, $attempt, $carrierRevision, $processedAt)
            || $resolved === null || $due === null || $resolved < $campaign->arrivalAt || $due > $processedAt
            || $due->getTimestamp() - $resolved->getTimestamp() !== $this->fuel->legSeconds) {
            return null;
        }

        $remaining = $this->fuel->arrivalHydrogen($carriedBeforeReturn);
        if ($remaining === null || $carriedBeforeReturn !== $this->fuel->afterCombat($campaign->reservedShips, $survivors)) {
            return null;
        }

        return new PirateBlockedReturnEvidence(
            $campaign->id, $attempt, $this->originId, $this->universeId, $carrierRevision, $processedAt,
            $this->home, $remaining,
        );
    }

    private function matchesProcessedReturn(
        PirateAttackCampaign $campaign,
        int $attempt,
        int $carrierRevision,
        \DateTimeImmutable $processedAt,
    ): bool {
        return $campaign->id === $this->campaignId && $attempt === $this->attempt
            && $campaign->returnBlocked && $campaign->originId === $this->originId
            && $campaign->nextAt === null && $campaign->dispatchToken === null
            && $campaign->reconnaissance->membership()->universe->id === $this->universeId
            && $carrierRevision > 0 && $attempt > 0 && ($campaign->timeline['attempt'] ?? null) === $attempt
            && $campaign->reservedShips !== [] && $campaign->report !== null
            && in_array($campaign->timeline['state'] ?? null, ['retryable', 'won'], true)
            && $processedAt >= $campaign->arrivalAt
            && self::checkpointDate($campaign, 'settled')?->getTimestamp() === $processedAt->getTimestamp();
    }

    public static function checkpointDate(PirateAttackCampaign $campaign, string $key): ?\DateTimeImmutable
    {
        $stamp = $campaign->timeline[$key] ?? null;
        $date = is_string($stamp) ? \DateTimeImmutable::createFromFormat(DATE_ATOM, $stamp) : false;

        return $date !== false && $date->format(DATE_ATOM) === $stamp ? $date : null;
    }

    public static function validEndpoint(array $point, bool $anchored): bool
    {
        return count($point) === 5 && array_is_list($point)
            && is_int($point[0]) && $point[0] > 0 && is_int($point[1]) && $point[1] > 0
            && is_int($point[2]) && is_int($point[3])
            && ($anchored ? $point[4] === null : (is_int($point[4]) && $point[4] >= 1 && $point[4] <= 9));
    }
}
