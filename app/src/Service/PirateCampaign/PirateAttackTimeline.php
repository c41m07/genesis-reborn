<?php

declare(strict_types=1);

namespace App\Service\PirateCampaign;

use App\Service\Combat\CombatSimulator;

/**
 * Modèle local, non persisté. La future intégration lie cette chronologie à la reconnaissance réellement remise,
 * puis enregistre chaque transition sous verrou. Ce modèle ne retire, n'installe ni ne crédite quoi que ce soit.
 */
final class PirateAttackTimeline
{
    private int $attemptNumber = 0;
    private string $state = 'ready';
    private ?\DateTimeImmutable $lastSettledAt = null;
    private ?\DateTimeImmutable $combatDueAt = null;
    private ?\DateTimeImmutable $combatResolvedAt = null;
    private ?\DateTimeImmutable $returnDueAt = null;
    private array $playerUnits = [];
    private int $seed = 0;
    private bool $won = false;

    private function __construct(
        private readonly PirateBattleScenario $scenario,
        private readonly \DateTimeImmutable $reconnaissanceReportedAt,
    ) {
    }

    /** L'adaptateur doit prendre cette date du rapport de la mission, jamais d'un formulaire ou de la date prévue. */
    public static function afterReport(PirateBattleScenario $scenario, ?\DateTimeImmutable $reportedAt): self
    {
        if ($reportedAt === null) {
            throw new \DomainException('La reconnaissance doit avoir réellement remis son rapport.');
        }

        return new self($scenario, $reportedAt);
    }

    /** @param list<array<string,mixed>> $playerUnits Instantané de la flotte réservée et des technologies au départ. */
    public function start(
        array $playerUnits,
        int $seed,
        \DateTimeImmutable $departedAt,
        \DateTimeImmutable $arrivalAt,
        \DateTimeImmutable $combatDueAt,
    ): int {
        if (!in_array($this->state, ['ready', 'retryable'], true)) {
            throw new \DomainException('La tentative précédente n’est pas terminée ou la rencontre est déjà gagnée.');
        }
        if ($departedAt < ($this->lastSettledAt ?? $this->reconnaissanceReportedAt)
            || $arrivalAt <= $departedAt || $combatDueAt < $arrivalAt
            || $seed < 0 || $seed > 0xffffffff) {
            throw new \DomainException('Trajet, échéance ou graine de combat pirate invalide.');
        }

        $units = PirateBattleScenario::freezeUnits($playerUnits);
        $this->scenario->assertNativeUnitLimit($units);
        ++$this->attemptNumber;
        $this->playerUnits = $units;
        $this->seed = $seed;
        $this->combatDueAt = $combatDueAt;
        $this->combatResolvedAt = null;
        $this->returnDueAt = null;
        $this->state = 'scheduled';

        return $this->attemptNumber;
    }

    /**
     * L'horloge et le numéro de tentative arrêtent les événements précoces, retardés et rejoués.
     * @param \Closure(list<array<string,mixed>>, \DateTimeImmutable): \DateTimeImmutable $planReturnAt Calcul pur, appelé seulement s'il reste une escorte.
     */
    public function resolveWhenDue(
        int $attemptNumber,
        \DateTimeImmutable $at,
        CombatSimulator $simulator,
        \Closure $planReturnAt,
    ): ?array {
        if ($attemptNumber !== $this->attemptNumber || $this->state !== 'scheduled' || $at < $this->combatDueAt) {
            return null;
        }

        $result = $this->scenario->resolve($simulator, $this->playerUnits, $this->seed);
        $survivors = array_values(array_filter($result['survivors'], static fn (array $unit): bool => $unit['camp'] === 'escort'));
        $returnDueAt = $survivors === [] ? null : $planReturnAt($survivors, $at);
        if ($survivors !== [] && !$returnDueAt instanceof \DateTimeImmutable) {
            throw new \DomainException('Échéance de retour invalide pour les survivants de l’escorte.');
        }
        $this->finish($at, $result['outcome'], $returnDueAt);

        return $result;
    }

    /** Enregistre seulement le résultat préparé dont le dernier round est atteint. */
    public function recordPreparedResolution(
        int $attemptNumber,
        \DateTimeImmutable $at,
        \DateTimeImmutable $lastRoundDue,
        string $outcome,
        ?\DateTimeImmutable $returnDueAt,
    ): void {
        if ($attemptNumber !== $this->attemptNumber || $this->state !== 'scheduled'
            || $at < $lastRoundDue || $lastRoundDue > $this->combatDueAt
            || !in_array($outcome, ['attacker_wins', 'defender_wins', 'draw'], true)) {
            throw new \LogicException('Le résultat préparé ne correspond pas à la tentative ou à son échéance.');
        }
        // Le retour démarre seulement lors de l'application réelle, même si le round était déjà dû.
        if ($returnDueAt !== null && $returnDueAt <= $at) {
            throw new \DomainException('Échéance de retour invalide pour les survivants de l’escorte.');
        }
        $this->combatDueAt = $lastRoundDue;
        $this->finish($at, $outcome, $returnDueAt);
    }

    private function finish(\DateTimeImmutable $at, string $outcome, ?\DateTimeImmutable $returnDueAt): void
    {
        if ($returnDueAt !== null && $returnDueAt <= $this->combatDueAt) {
            throw new \DomainException('Échéance de retour invalide pour les survivants de l’escorte.');
        }
        $this->combatResolvedAt = $at;
        $this->won = $outcome === 'attacker_wins';
        $this->returnDueAt = $returnDueAt;
        if ($returnDueAt !== null) {
            $this->state = 'returning';
        } else {
            $this->close($at);
        }
    }

    /** À appeler avec l'installation atomique future des survivants, pas au moment où leur retour est planifié. */
    public function settleReturnWhenDue(int $attemptNumber, \DateTimeImmutable $at): bool
    {
        if ($attemptNumber !== $this->attemptNumber || $this->state !== 'returning'
            || $at < $this->returnDueAt || $at < $this->combatResolvedAt) {
            return false;
        }

        $this->close($at);

        return true;
    }

    private function close(\DateTimeImmutable $at): void
    {
        $this->lastSettledAt = $at;
        $this->state = $this->won ? 'won' : 'retryable';
    }

    /** État interne de l'agrégat verrouillé, jamais fourni ou produit par un client HTTP. */
    public function checkpoint(): array
    {
        return [
            'attempt' => $this->attemptNumber,
            'state' => $this->state,
            'settled' => $this->lastSettledAt?->format(DATE_ATOM),
            'combatDue' => $this->combatDueAt?->format(DATE_ATOM),
            'resolved' => $this->combatResolvedAt?->format(DATE_ATOM),
            'returnDue' => $this->returnDueAt?->format(DATE_ATOM),
            'units' => $this->playerUnits,
            'seed' => $this->seed,
            'won' => $this->won,
        ];
    }

    public static function fromCheckpoint(PirateBattleScenario $scenario, \DateTimeImmutable $reportedAt, array $data): self
    {
        $timeline = self::afterReport($scenario, $reportedAt);
        $timeline->attemptNumber = $data['attempt'];
        $timeline->state = $data['state'];
        $date = static fn (?string $value): ?\DateTimeImmutable => $value !== null ? new \DateTimeImmutable($value) : null;
        $timeline->lastSettledAt = $date($data['settled']);
        $timeline->combatDueAt = $date($data['combatDue']);
        $timeline->combatResolvedAt = $date($data['resolved']);
        $timeline->returnDueAt = $date($data['returnDue']);
        $timeline->playerUnits = PirateBattleScenario::freezeUnits($data['units']);
        $timeline->seed = $data['seed'];
        $timeline->won = $data['won'];

        return $timeline;
    }

    public function returnDueAt(): ?\DateTimeImmutable
    {
        return $this->state === 'returning' ? $this->returnDueAt : null;
    }

    public function state(): string
    {
        return $this->state;
    }

    public function isVictorious(): bool
    {
        return $this->won;
    }
}
