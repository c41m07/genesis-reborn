<?php

declare(strict_types=1);

namespace App\Service\PirateCampaign;

use Symfony\Component\DependencyInjection\Attribute\Exclude;
use Symfony\Component\Uid\Uuid;

/** Un seul tronçon privé : la preuve d'origine du lot devient inéligible quand l'ordre est inscrit. */
#[Exclude]
final readonly class PirateSurvivorRecoveryLeg
{
    public const TRAVELING = 'traveling';
    public const DELIVERED = 'delivered';
    public const DESTINATION_LOST = 'destination_lost';
    public const CAPACITY_BLOCKED = 'capacity_blocked';

    private function __construct(
        public int $campaignId,
        public int $attempt,
        public int $universeId,
        public int $generation,
        public string $token,
        public int $targetId,
        public array $destination,
        public \DateTimeImmutable $departedAt,
        public \DateTimeImmutable $dueAt,
        public float $initialHydrogen,
        public float $legHydrogen,
        public string $state,
        public ?\DateTimeImmutable $observedAt = null,
    ) {
    }

    /**
     * Reçoit exclusivement une attestation persistée et un devis natif validé par le futur
     * service sous verrou. Une colonie n'est jamais une origine ou destination « par défaut ».
     * @param array{target_id:int,carrier_revision:int,seconds:int,hydrogen:float,arrival_at:\DateTimeImmutable} $quote
     * @param array{int,int,int,int,int} $destination Coordonnées obtenues de la colonie effectivement validée.
     */
    public static function depart(PirateBlockedReturnEvidence $proof, array $quote, array $destination, string $token, \DateTimeImmutable $now): self
    {
        $seconds = $quote['seconds'] ?? null;
        $cutoff = $quote['arrival_at'] ?? null;
        $cost = $quote['hydrogen'] ?? null;
        $target = $quote['target_id'] ?? null;
        $sourceIsAnchor = $proof->phase === PirateBlockedReturnEvidence::HELD_BEFORE_RETURN;
        if ($proof->campaignId < 1 || $proof->attempt < 1 || $proof->originId < 1
            || $proof->universeId < 1 || $proof->carrierRevision < 1 || $proof->carrierRevision === PHP_INT_MAX
            || ($quote['carrier_revision'] ?? null) !== $proof->carrierRevision || $proof->arrivedAt > $now
            || !PirateReturnAttestation::validEndpoint($proof->position, $sourceIsAnchor)
            || !PirateReturnAttestation::validEndpoint($destination, false) || $destination[1] !== $proof->position[1]
            || (($destination[0] === $proof->position[0]) !== ([$destination[2], $destination[3]] === [$proof->position[2], $proof->position[3]]))
            || $destination === $proof->position
            || !is_int($target) || $target < 1 || $target === $proof->originId
            || !is_string($token) || !Uuid::isValid($token) || strlen($token) !== 36
            || !is_int($seconds) || $seconds < 1 || $seconds > 2147483647
            || !$cutoff instanceof \DateTimeImmutable || $cutoff->getTimestamp() - $now->getTimestamp() !== $seconds
            || $now->getOffset() !== 0 || $cutoff->getOffset() !== 0
            || $now->format('u') !== '000000' || $cutoff->format('u') !== '000000'
            || !is_finite($proof->remainingHydrogen) || $proof->remainingHydrogen < 0
            || (!is_int($cost) && !is_float($cost)) || !is_finite($cost) || $cost < 0 || $cost > $proof->remainingHydrogen) {
            throw new \DomainException('La preuve ou le devis de repli ne correspond pas au lot survivant.');
        }

        return new self(
            $proof->campaignId, $proof->attempt, $proof->universeId, $proof->carrierRevision + 1, $token,
            $target, $destination, $now, $cutoff, $proof->remainingHydrogen, (float) $cost, self::TRAVELING,
        );
    }

    /** Aucun effet autorisé pour un autre jeton, une autre révision ou une échéance déjà close. */
    public function isCurrent(int $campaignId, int $attempt, int $generation, string $token): bool
    {
        return $this->state === self::TRAVELING && $campaignId === $this->campaignId
            && $attempt === $this->attempt && $generation === $this->generation && hash_equals($this->token, $token);
    }

    /** Résolution après la relecture réelle du point/accès et de la capacité dans le futur worker. */
    public function arrive(
        int $campaignId,
        int $attempt,
        int $generation,
        string $token,
        \DateTimeImmutable $now,
        bool $ownFrozenDestination,
        bool $capacityAvailable,
    ): ?self {
        if (!$this->isCurrent($campaignId, $attempt, $generation, $token) || $now < $this->dueAt) {
            return null;
        }
        $state = !$ownFrozenDestination ? self::DESTINATION_LOST
            : ($capacityAvailable ? self::DELIVERED : self::CAPACITY_BLOCKED);

        return new self(
            $this->campaignId, $this->attempt, $this->universeId, $this->generation, $this->token,
            $this->targetId, $this->destination, $this->departedAt, $this->dueAt,
            $this->initialHydrogen, $this->legHydrogen, $state, $now,
        );
    }

    /** Reste embarqué, sans crédit planétaire ni accès à l'ancien solde. */
    public function remainingHydrogen(): float
    {
        return $this->initialHydrogen - $this->legHydrogen;
    }
}
