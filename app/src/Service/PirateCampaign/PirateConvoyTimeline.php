<?php

declare(strict_types=1);

namespace App\Service\PirateCampaign;

/** Modèle en mémoire seulement ; aucune mission de transport ni écriture de ressources. */
final class PirateConvoyTimeline
{
    private ?\DateTimeImmutable $departedAt = null;
    private ?\DateTimeImmutable $dueAt = null;
    private ?\DateTimeImmutable $arrivedAt = null;
    private ?\DateTimeImmutable $settledAt = null;
    private ?string $dispatchToken = null;

    private function __construct(
        public readonly int $winningAttempt,
        private readonly \DateTimeImmutable $victoryAt,
        public readonly PirateConvoyQuote $quote,
    ) {
    }

    /** L'adaptateur futur lit la victoire persistée dans la rencontre du propriétaire sous verrou. */
    public static function afterVictory(int $winningAttempt, ?\DateTimeImmutable $effectiveVictoryAt, PirateConvoyQuote $quote): self
    {
        if ($winningAttempt < 1 || $effectiveVictoryAt === null) {
            throw new \DomainException('Le convoi exige une victoire réellement enregistrée.');
        }

        return new self($winningAttempt, $effectiveVictoryAt, $quote);
    }

    public function dispatch(string $token, \DateTimeImmutable $departedAt, \DateTimeImmutable $dueAt): bool
    {
        if ($this->dispatchToken !== null) {
            return false;
        }
        if ($token === '' || $departedAt < $this->victoryAt || $dueAt <= $departedAt) {
            throw new \DomainException('Le convoi exige un départ et une échéance valides.');
        }
        $this->dispatchToken = $token;
        $this->departedAt = $departedAt;
        $this->dueAt = $dueAt;

        return true;
    }

    public function arriveWhenDue(string $token, \DateTimeImmutable $at): bool
    {
        if ($this->dispatchToken === null || !hash_equals($this->dispatchToken, $token)
            || $this->arrivedAt !== null || $at < $this->dueAt) {
            return false;
        }
        $this->arrivedAt = $at;

        return true;
    }

    /** Marque le droit consommé après le crédit, à sauvegarder dans la même transaction que celui-ci. */
    public function recordSettlementAfterCredit(\DateTimeImmutable $at): bool
    {
        if ($this->arrivedAt === null || $at < $this->arrivedAt || $this->settledAt !== null) {
            return false;
        }
        $this->settledAt = $at;

        return true;
    }

    /** @return array{departed_at: ?\DateTimeImmutable, due_at: ?\DateTimeImmutable, arrived_at: ?\DateTimeImmutable, settled_at: ?\DateTimeImmutable} */
    public function chronology(): array
    {
        return [
            'departed_at' => $this->departedAt,
            'due_at' => $this->dueAt,
            'arrived_at' => $this->arrivedAt,
            'settled_at' => $this->settledAt,
        ];
    }

    public function state(): string
    {
        return match (true) {
            $this->settledAt !== null => 'settled',
            $this->arrivedAt !== null => 'arrived',
            $this->dispatchToken !== null => 'traveling',
            default => 'eligible',
        };
    }
}
