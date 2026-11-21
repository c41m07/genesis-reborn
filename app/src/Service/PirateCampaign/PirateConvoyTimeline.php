<?php

declare(strict_types=1);

namespace App\Service\PirateCampaign;

/** Droit civil en mémoire : aucun transfert, mouvement réel ni dépôt déclenché par ce modèle. */
final class PirateConvoyTimeline
{
    private string $state = 'eligible';
    private ?int $destination = null;
    /** @var array{int,int,int,int,int}|null */
    private ?array $destinationPoint = null;
    private ?\DateTimeImmutable $departedAt = null;
    private ?\DateTimeImmutable $dueAt = null;
    private ?\DateTimeImmutable $arrivedAt = null;
    private ?\DateTimeImmutable $settledAt = null;
    private ?string $dispatchToken = null;
    private \DateTimeImmutable $lastTransitionAt;

    /** @param array{int,int,int,int,?int}|null $position Ancre civile initialement attestée, sans planète. */
    private function __construct(
        public readonly int $winningAttempt,
        public readonly int $membershipId,
        public readonly int $universeId,
        private readonly \DateTimeImmutable $victoryAt,
        public readonly PirateConvoyQuote $quote,
        private ?array $position,
    ) {
        $this->lastTransitionAt = $victoryAt;
    }

    /**
     * Position initiale issue d'une vraie ancre déjà attestée côté serveur ; sans preuve,
     * le droit existe mais ne peut pas créer de trajet fictif.
     * @param array{int,int,int,int,null}|null $civilPosition
     */
    public static function afterVictory(
        int $winningAttempt,
        ?\DateTimeImmutable $effectiveVictoryAt,
        PirateConvoyQuote $quote,
        int $membershipId,
        int $universeId,
        ?array $civilPosition,
    ): self {
        if ($winningAttempt < 1 || $effectiveVictoryAt === null || $membershipId < 1 || $universeId < 1
            || ($civilPosition !== null && !self::validPoint($civilPosition, true))) {
            throw new \DomainException('Le convoi exige une victoire et un périmètre attestés.');
        }

        return new self($winningAttempt, $membershipId, $universeId, $effectiveVictoryAt, $quote, $civilPosition);
    }

    /**
     * Le service appelant contrôle la vraie propriété de cette planète, puis programme un trajet
     * protégé depuis la position attestée, sous verrou. La position fournie dans le devis doit rester la même.
     * @param array{int,int,int,int,?int} $from
     * @param array{int,int,int,int,int} $to
     */
    public function dispatch(
        int $membership,
        int $universe,
        int $destinationId,
        array $from,
        array $to,
        string $token,
        \DateTimeImmutable $departedAt,
        \DateTimeImmutable $dueAt,
    ): bool {
        if (!$this->belongsTo($membership, $universe)
            || !in_array($this->state, ['eligible', 'destination_lost'], true)
            || $this->position === null || $from !== $this->position
            || $destinationId === $this->destination || $token === $this->dispatchToken) {
            return false;
        }
        if ($destinationId < 1 || !self::validPoint($to, false) || $from[1] !== $to[1] || $token === ''
            || $departedAt < $this->lastTransitionAt || $dueAt <= $departedAt) {
            throw new \DomainException('Le convoi exige un trajet protégé daté et joignable.');
        }
        $this->dispatchToken = $token;
        $this->destination = $destinationId;
        $this->destinationPoint = $to;
        $this->departedAt = $departedAt;
        $this->dueAt = $dueAt;
        $this->arrivedAt = null;
        $this->lastTransitionAt = $departedAt;
        $this->state = 'traveling';

        return true;
    }

    public function arriveWhenDue(string $token, \DateTimeImmutable $at): bool
    {
        if ($this->state !== 'traveling' || !hash_equals($this->dispatchToken, $token) || $at < $this->dueAt) {
            return false;
        }

        // L'ancienne position ne suffit plus après départ. Seule cette arrivée confirme la position du lot.
        $this->position = $this->destinationPoint;
        $this->lastTransitionAt = $at;
        $this->arrivedAt = $at;
        $this->state = 'arrived';

        return true;
    }

    /** Ne jamais admettre un dépôt avant arrivée, même si le joueur connaît la destination. */
    public function mayTryDeposit(int $membership, int $universe, int $destination, \DateTimeImmutable $at): bool
    {
        return $this->belongsTo($membership, $universe) && $destination === $this->destination
            && in_array($this->state, ['arrived', 'waiting_capacity'], true) && $at >= $this->lastTransitionAt;
    }

    /** La vraie capacité était insuffisante : le droit entier reste disponible au même endroit. */
    public function recordCapacityShortage(int $membership, int $universe, int $destination, \DateTimeImmutable $at): bool
    {
        if (!$this->mayTryDeposit($membership, $universe, $destination, $at)) {
            return false;
        }
        $this->lastTransitionAt = $at;
        $this->state = 'waiting_capacity';

        return true;
    }

    /** Appeler seulement après constat sous verrou de perte de propriété ; aucun nouveau trajet n'est créé. */
    public function recordDestinationLoss(int $membership, int $universe, int $destination, \DateTimeImmutable $at): bool
    {
        if (!$this->mayTryDeposit($membership, $universe, $destination, $at)) {
            return false;
        }
        $this->lastTransitionAt = $at;
        $this->state = 'destination_lost';

        return true;
    }

    /** Même transaction que le dépôt effectivement crédité, et seulement pour le lot complet. */
    public function recordSettlementAfterCredit(int $membership, int $universe, int $destination, \DateTimeImmutable $at): bool
    {
        if (!$this->mayTryDeposit($membership, $universe, $destination, $at)) {
            return false;
        }
        $this->lastTransitionAt = $at;
        $this->settledAt = $at;
        $this->state = 'settled';

        return true;
    }

    private function belongsTo(int $membership, int $universe): bool
    {
        return $membership === $this->membershipId && $universe === $this->universeId;
    }

    private static function validPoint(array $point, bool $civil): bool
    {
        if (array_keys($point) !== [0, 1, 2, 3, 4] || !is_int($point[0]) || $point[0] < 1 || !is_int($point[1]) || $point[1] < 1
            || !is_int($point[2]) || !is_int($point[3])) {
            return false;
        }

        return $civil ? $point[4] === null : is_int($point[4]) && $point[4] >= 1 && $point[4] <= 9;
    }

    /** En vol, ne pas présenter le dernier point de passage comme une position disponible pour une réexpédition. */
    public function attestedPositionForDeparture(): ?array
    {
        return in_array($this->state, ['eligible', 'destination_lost'], true) ? $this->position : null;
    }

    /** @return array{departed_at:?\DateTimeImmutable,due_at:?\DateTimeImmutable,arrived_at:?\DateTimeImmutable,settled_at:?\DateTimeImmutable} */
    public function chronology(): array
    {
        return [
            'departed_at' => $this->departedAt, 'due_at' => $this->dueAt,
            'arrived_at' => $this->arrivedAt, 'settled_at' => $this->settledAt,
        ];
    }

    public function state(): string
    {
        return $this->state;
    }
}
