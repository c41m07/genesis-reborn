<?php

declare(strict_types=1);

namespace App\Entity;

use App\Service\PirateCampaign\{PirateConvoyDispatchToken, PirateConvoyQuote, PirateConvoyTimeline};
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\UniqueConstraint(name: 'pirate_convoy_attack_unique', columns: ['attack_id'])]
#[ORM\UniqueConstraint(name: 'pirate_convoy_phase_token_unique', columns: ['dispatch_token'])]
class PirateConvoyDelivery
{
    #[ORM\Id, ORM\GeneratedValue, ORM\Column]
    public ?int $id = null;

    /** Scope dérivé à la victoire et jamais réattribué, même si la destination a changé de propriétaire. */
    #[ORM\Column]
    public readonly int $membershipId;
    #[ORM\Column]
    public readonly int $universeId;
    #[ORM\Column]
    public readonly int $ownerId;
    #[ORM\Column]
    public readonly int $winningAttempt;
    #[ORM\Column(type: 'datetime_immutable')]
    // Les valeurs objet sont privées plutôt que readonly : Doctrine les réhydrate sous verrou.
    private \DateTimeImmutable $victoryAt;

    #[ORM\Column(type: 'json')]
    private array $quoteParts;
    #[ORM\Column(type: 'json')]
    private array $checkpoint;

    #[ORM\Column(length: 36, nullable: true)]
    private ?string $dispatchToken = null;
    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $nextAt = null;
    // Reçu intrinsèque du droit unique : à enregistrer dans la transaction du dépôt entier.
    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $settledAt = null;

    private function __construct(
        #[ORM\ManyToOne, ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
        private PirateAttackCampaign $attack,
        PlayerUniverse $owner,
        PirateConvoyTimeline $timeline,
        \DateTimeImmutable $wonAt,
    ) {
        $this->membershipId = $timeline->membershipId;
        $this->universeId = $timeline->universeId;
        $this->ownerId = $owner->player->id;
        $this->winningAttempt = $timeline->winningAttempt;
        $this->victoryAt = $wonAt;
        $this->quoteParts = $timeline->quote->exactParts();
        $this->checkpoint = $timeline->checkpoint();
    }

    /**
     * Réservé au futur orchestrateur de publication de la victoire V2, qui doit tenir les verrous
     * de participation, d'univers et de tentative. Aucune génération depuis le dossier joueur.
     */
    public static function forVictory(PirateAttackCampaign $attack, PlayerUniverse $owner, \DateTimeImmutable $now): self
    {
        $time = $attack->timeline['resolved'] ?? null;
        $wonAt = is_string($time) ? \DateTimeImmutable::createFromFormat(DATE_ATOM, $time) : false;
        $arrival = $attack->timeline['arrival_observed'] ?? null;
        $observedAt = is_string($arrival) ? \DateTimeImmutable::createFromFormat(DATE_ATOM, $arrival) : false;
        if ($attack->id === null || ($attack->timeline['arrival_flow'] ?? null) !== 2
            || ($attack->report['outcome'] ?? null) !== 'attacker_wins' || ($attack->timeline['won'] ?? null) !== true
            || !is_int($attempt = $attack->timeline['attempt'] ?? null) || $attempt < 1
            || $wonAt === false || $wonAt->format(DATE_ATOM) !== $time || $wonAt != $now
            || $observedAt === false || $observedAt->format(DATE_ATOM) !== $arrival || $observedAt > $wonAt
            || !in_array($attack->timeline['state'] ?? null, ['returning', 'won'], true)
            || $owner->id === null || $owner->player?->id === null
            || $owner->universe->id === null || !$owner->universe->isAccessible($now)) {
            throw new \DomainException('Le convoi exige une victoire V2 réelle et un propriétaire actif.');
        }

        // Le même instant que la publication écarte la création rétroactive d'un gain au GET ou par un rattrapage tardif.
        $proof = $attack->reconnaissance->battleProofFor($owner, $wonAt);
        $quote = $attack->reconnaissance->convoyQuoteFor($owner, $wonAt);
        if ($proof === null || $quote === null) {
            throw new \DomainException('Le renseignement et le devis de cette victoire ne sont pas attestés.');
        }
        $system = $proof['system'];
        $point = [$system->id, $system->galaxy->id, $system->coordX, $system->coordY, null];
        $timeline = PirateConvoyTimeline::afterVictory($attempt, $wonAt, $quote, $owner->id, $owner->universe->id, $point);

        return new self($attack, $owner, $timeline, $wonAt);
    }

    public function timeline(): PirateConvoyTimeline
    {
        $timeline = PirateConvoyTimeline::fromCheckpoint($this->winningAttempt, $this->victoryAt,
            PirateConvoyQuote::fromFrozenParts($this->quoteParts), $this->membershipId, $this->universeId, $this->checkpoint);
        $activeToken = $timeline->state() === 'traveling' ? $this->checkpoint['token'] : null;
        $due = $timeline->state() === 'traveling' ? $timeline->chronology()['due_at'] : null;
        if ($this->dispatchToken !== $activeToken || $this->nextAt != $due || $this->settledAt != $timeline->chronology()['settled_at']
            || ($activeToken !== null && !PirateConvoyDispatchToken::matches($activeToken, $this->checkpoint['generation']))) {
            throw new \DomainException('Les preuves colonne et checkpoint du convoi ne concordent pas.');
        }

        return $timeline;
    }

    /** L'appelant doit appliquer et sauvegarder sous le verrou du droit, dans la transaction du dépôt/outbox. */
    public function replaceState(PirateConvoyTimeline $timeline): void
    {
        $checkpoint = $timeline->checkpoint();
        if ($timeline->winningAttempt !== $this->winningAttempt
            || $timeline->membershipId !== $this->membershipId || $timeline->universeId !== $this->universeId
            || $timeline->quote->exactParts() !== $this->quoteParts || $this->settledAt !== null
            || !$this->isNextCheckpoint($checkpoint)
            || ($checkpoint['token'] !== null && !PirateConvoyDispatchToken::matches($checkpoint['token'], $checkpoint['generation']))) {
            throw new \DomainException('Mise à jour du droit incompatible ou déjà consommée.');
        }
        $dates = $timeline->chronology();
        $this->checkpoint = $checkpoint;
        $this->settledAt = $dates['settled_at'];
        $this->nextAt = $timeline->state() === 'traveling' ? $dates['due_at'] : null;
        $this->dispatchToken = $this->nextAt === null ? null : $checkpoint['token'];
    }

    private function isNextCheckpoint(array $next): bool
    {
        $current = $this->checkpoint;
        $transitions = [
            'eligible' => ['traveling'],
            'traveling' => ['arrived', 'waiting_capacity', 'destination_lost', 'settled'],
            'arrived' => ['waiting_capacity', 'destination_lost', 'settled'],
            'waiting_capacity' => ['waiting_capacity', 'destination_lost', 'settled'],
            'destination_lost' => ['traveling'],
        ];
        if (!in_array($next['state'], $transitions[$current['state']] ?? [], true)
            || new \DateTimeImmutable($next['transition_at']) < new \DateTimeImmutable($current['transition_at'])) {
            return false;
        }
        if ($next['state'] === 'traveling') {
            return $next['generation'] === $current['generation'] + 1;
        }

        // On peut arriver et déposer dans la même transaction, mais jamais revenir à un ancien
        // jeton ou déplacer une arrivée déjà enregistrée pendant un réessai de stock.
        return $next['generation'] === $current['generation'] && $next['token'] === $current['token']
            && $next['destination'] === $current['destination'] && $next['due_at'] === $current['due_at']
            && ($current['arrived_at'] === null || $next['arrived_at'] === $current['arrived_at']);
    }
}
