<?php

declare(strict_types=1);

namespace App\Service\PirateCampaign;

/** Droit civil en mémoire : aucun transfert, mouvement réel ni dépôt déclenché par ce modèle. */
final class PirateConvoyTimeline
{
    private string $state = 'eligible';
    private int $generation = 0;
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
        if ($this->generation >= 2147483647) {
            throw new \DomainException('La génération du trajet de convoi ne peut plus avancer.');
        }
        ++$this->generation;
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

    /** Tentative/participation/victoire et montant sont portés séparément par le droit SQL. */
    public function checkpoint(): array
    {
        return [
            'version' => 1,
            'state' => $this->state,
            'generation' => $this->generation,
            'token' => $this->dispatchToken,
            'position' => $this->position,
            'destination' => $this->destination,
            'destination_point' => $this->destinationPoint,
            'departed_at' => $this->departedAt?->format(DATE_ATOM),
            'due_at' => $this->dueAt?->format(DATE_ATOM),
            'arrived_at' => $this->arrivedAt?->format(DATE_ATOM),
            'settled_at' => $this->settledAt?->format(DATE_ATOM),
            'transition_at' => $this->lastTransitionAt->format(DATE_ATOM),
        ];
    }

    public static function fromCheckpoint(
        int $attempt,
        \DateTimeImmutable $victoryAt,
        PirateConvoyQuote $quote,
        int $membershipId,
        int $universeId,
        array $checkpoint,
    ): self {
        if (count($checkpoint) !== 12 || ($checkpoint['version'] ?? null) !== 1
            || (isset($checkpoint['destination']) && !is_int($checkpoint['destination']))
            || (isset($checkpoint['token']) && !is_string($checkpoint['token']))
            || !is_int($generation = $checkpoint['generation'] ?? null) || $generation < 0 || $generation > 2147483647
            || !in_array($state = $checkpoint['state'] ?? null, ['eligible', 'traveling', 'arrived', 'waiting_capacity', 'destination_lost', 'settled'], true)) {
            throw new \DomainException('Version ou état du droit de convoi invalide.');
        }

        $timeline = self::afterVictory($attempt, $victoryAt, $quote, $membershipId, $universeId, null);
        // Ne jamais inventer la géométrie d'une jambe dont le checkpoint a été perdu ou corrompu.
        $timeline->position = self::readPoint($checkpoint['position'] ?? null);
        $timeline->destinationPoint = self::readPoint($checkpoint['destination_point'] ?? null, false);
        $timeline->destination = $checkpoint['destination'] ?? null;
        $timeline->dispatchToken = $checkpoint['token'] ?? null;
        $timeline->departedAt = self::readTime($checkpoint['departed_at'] ?? null);
        $timeline->dueAt = self::readTime($checkpoint['due_at'] ?? null);
        $timeline->arrivedAt = self::readTime($checkpoint['arrived_at'] ?? null);
        $timeline->settledAt = self::readTime($checkpoint['settled_at'] ?? null);
        $timeline->lastTransitionAt = self::readTime($checkpoint['transition_at'] ?? null)
            ?? throw new \DomainException('Le droit ne possède pas de dernière transition.');
        $timeline->generation = $generation;
        $timeline->state = $state;
        $timeline->assertCheckpoint();

        return $timeline;
    }

    private function assertCheckpoint(): void
    {
        if ($this->state === 'eligible') {
            if ($this->generation !== 0 || $this->lastTransitionAt != $this->victoryAt
                || $this->departedAt !== null || $this->dueAt !== null || $this->arrivedAt !== null
                || $this->destination !== null || $this->destinationPoint !== null
                || $this->settledAt !== null || $this->dispatchToken !== null
                || ($this->position !== null && $this->position[4] !== null)) {
                throw new \DomainException('Un droit non expédié contient une preuve de trajet incompatible.');
            }

            return;
        }
        if ($this->generation < 1 || ($this->destination ?? 0) < 1 || $this->destinationPoint === null
            || $this->position === null || $this->position[1] !== $this->destinationPoint[1]
            || $this->dispatchToken === null || $this->dispatchToken === ''
            || $this->departedAt === null || $this->dueAt === null
            || $this->departedAt < $this->victoryAt || $this->dueAt <= $this->departedAt) {
            throw new \DomainException('Le droit ne possède pas de trajet complet.');
        }
        if ($this->state === 'traveling') {
            if ($this->arrivedAt !== null || $this->settledAt !== null || $this->lastTransitionAt != $this->departedAt) {
                throw new \DomainException('Un trajet en cours ne peut pas attester une arrivée.');
            }

            return;
        }

        if ($this->arrivedAt === null || $this->arrivedAt < $this->dueAt || $this->position !== $this->destinationPoint
            || $this->lastTransitionAt < $this->arrivedAt
            || ($this->state === 'settled' && ($this->settledAt === null || $this->settledAt != $this->lastTransitionAt))
            || ($this->state !== 'settled' && $this->settledAt !== null)) {
            throw new \DomainException('L’arrivée ou le reçu du convoi ne correspond pas au trajet.');
        }
    }

    private static function readTime(mixed $value): ?\DateTimeImmutable
    {
        if ($value === null) {
            return null;
        }
        $date = is_string($value) ? \DateTimeImmutable::createFromFormat(DATE_ATOM, $value) : false;
        if ($date === false || $date->format(DATE_ATOM) !== $value) {
            throw new \DomainException('Date du droit de convoi invalide.');
        }

        return $date;
    }

    private static function readPoint(mixed $value, ?bool $civil = null): ?array
    {
        if ($value === null) {
            return null;
        }
        if (!is_array($value) || !self::validPoint($value, $civil ?? (($value[4] ?? null) === null))) {
            throw new \DomainException('Position du convoi invalide.');
        }

        return $value;
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
