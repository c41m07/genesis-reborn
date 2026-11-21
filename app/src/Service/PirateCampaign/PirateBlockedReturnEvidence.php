<?php

declare(strict_types=1);

namespace App\Service\PirateCampaign;

use Symfony\Component\DependencyInjection\Attribute\Exclude;

/**
 * Données privées d'un futur retour bloqué : à hydrater uniquement depuis l'attestation
 * persistée par le worker une fois le trajet effectivement traité, jamais depuis la requête.
 */
#[Exclude]
final readonly class PirateBlockedReturnEvidence
{
    public const BLOCKED_ARRIVAL = 'blocked_arrival';
    public const HELD_BEFORE_RETURN = 'held_before_return';

    public array $position;

    /** @param array{int,int,int,int,int|null} $position Case lors d'une arrivée réelle ou ancre attestée d'un retour jamais parti. */
    public function __construct(
        public int $campaignId,
        public int $attempt,
        public int $originId,
        public int $universeId,
        public int $carrierRevision,
        public \DateTimeImmutable $arrivedAt,
        array $position,
        public float $remainingHydrogen,
        public string $phase = self::BLOCKED_ARRIVAL,
    ) {
        // Détacher les valeurs du conteneur d’hydratation, y compris ses références éventuelles.
        $this->position = array_map(static fn (mixed $value): mixed => $value, $position);
    }
}
