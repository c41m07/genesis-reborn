<?php

declare(strict_types=1);

namespace App\Service\PirateCampaign;

use App\Entity\{PirateAttackCampaign, PirateReconnaissance, PlayerUniverse};

/** Assemble les preuves déjà persistées ; aucune route, lecture globale ni opération de jeu. */
final class PirateCampaignProgress
{
    public function __construct(private readonly PirateCampaignCatalogue $catalogue)
    {
    }

    /**
     * L'appelant lit la reconnaissance propriétaire. Ne jamais remplacer ces preuves par une phase client.
     *
     * @return array{
     *   key: string, phase: string, narrative: array, composition: ?array, return_narrative: ?array,
     *   tactical_guidance: ?string, dates: array<string,string>, own_losses: ?array<string,array<string,int>>, returning: ?string,
     *   combat_rounds: ?int, may_offer_recovery: bool
     * }|null
     */
    public function forViewer(
        PlayerUniverse $viewer,
        PirateReconnaissance $reconnaissance,
        ?PirateAttackCampaign $attack,
        \DateTimeImmutable $at,
    ): ?array {
        $context = $reconnaissance->narrativeContextFor($viewer, $at);
        $scout = $reconnaissance->projectFor($viewer, $at);
        if ($context === null || $scout === null) {
            return null;
        }

        $phase = PirateCampaignMoment::ReconnaissanceEnRoute;
        $returnNarrative = null;
        $losses = null;
        $combatRounds = null;
        $returning = null;
        $mayOfferRecovery = false;
        $dates = ['scout_departure' => $scout['departed_at'], 'scout_due' => $scout['due_at']];
        if ($scout['status'] === 'reported') {
            $phase = PirateCampaignMoment::RenseignementRecu;
            $dates['scout_reported'] = $scout['reported_at'];
            if ($attack !== null && $this->sameReconnaissance($reconnaissance, $attack) && $attack->departedAt <= $at) {
                $phase = $this->attackMoment($attack, $at);
                $dates['attack_departure'] = $attack->departedAt->format(DATE_ATOM);
                if (isset($attack->arrivalAt)) {
                    // Le trajet donne une heure prévue, sans affirmer qu'un worker a traité l'arrivée.
                    $dates['attack_arrival_due'] = $attack->arrivalAt->format(DATE_ATOM);
                }
                $observed = $attack->timeline['arrival_observed'] ?? null;
                if (is_string($observed) && new \DateTimeImmutable($observed) <= $at) {
                    $dates['attack_arrival_observed'] = $observed;
                }
                if ($phase === PirateCampaignMoment::AttaqueEngagee && !isset($attack->timeline['arrival_flow'])
                    && isset($attack->timeline['combatDue'])) {
                    $dates['combat_due'] = $attack->timeline['combatDue'];
                } elseif ($phase !== PirateCampaignMoment::AttaqueEngagee) {
                    $dates['combat_reported'] = $attack->timeline['resolved'];
                    $losses = $this->escortLosses($attack->report['losses'] ?? null);
                    $combatRounds = $attack->report['rounds_played'] ?? null;
                    if ($attack->returnBlocked && $attack->reservedShips !== [] && isset($attack->timeline['pilot_return'])) {
                        // Le statut privé ne peut pas être confondu avec un retour livré au hangar.
                        $returning = 'blocked';
                    } elseif (($attack->timeline['state'] ?? null) === 'returning' && $attack->reservedShips !== []) {
                        $returning = $attack->returnBlocked ? 'blocked' : 'scheduled';
                        if (!$attack->returnBlocked) {
                            $returnNarrative = $this->catalogue->narrativeFor($context['key'], $context['site'], $viewer, PirateCampaignMoment::RetourDesSurvivants);
                            if (isset($attack->timeline['returnDue'])) {
                                $dates['return_due'] = $attack->timeline['returnDue'];
                            }
                        }
                    } elseif (isset($attack->timeline['returnDue'], $attack->timeline['settled'])
                        && new \DateTimeImmutable($attack->timeline['settled']) <= $at) {
                        // Sans échéance de retour, la clôture de combat n'est pas une arrivée de survivants.
                        $returning = 'returned';
                        $dates['return_settled'] = $attack->timeline['settled'];
                    }
                    $recovery = $attack->timeline['pilot_recovery'] ?? null;
                    if (is_array($recovery)) {
                        $status = $this->visibleRecovery($attack, $recovery, $at);
                        if ($status !== null) {
                            [$returning, $key, $stamp] = $status;
                            $dates[$key] = $stamp;
                        }
                    } elseif ($returning === 'blocked' && is_array($attack->timeline['pilot_blocked_return'] ?? null)) {
                        // Ceci ne prouve qu'une possibilité de formulaire ; le devis relit le vrai reçu.
                        $mayOfferRecovery = true;
                    }
                }
            }
        }
        $narrative = $this->catalogue->narrativeFor($context['key'], $context['site'], $viewer, $phase);
        if ($narrative === null) {
            return null;
        }

        return [
            'key' => $context['key'],
            'phase' => $phase->name,
            'narrative' => $narrative,
            // Le catalogue ne constitue jamais une preuve de reconnaissance remise.
            'composition' => $scout['composition'],
            'return_narrative' => $returnNarrative,
            'tactical_guidance' => $scout['composition'] !== null
                ? $this->catalogue->narrativeFor($context['key'], $context['site'], $viewer, PirateCampaignMoment::RenseignementRecu)['guidance']
                : null,
            'dates' => $dates,
            'own_losses' => $losses,
            'combat_rounds' => $combatRounds,
            'returning' => $returning,
            'may_offer_recovery' => $mayOfferRecovery,
        ];
    }

    /** @return array{string, string, string}|null */
    private function visibleRecovery(PirateAttackCampaign $attack, array $state, \DateTimeImmutable $at): ?array
    {
        $attempt = $attack->timeline['attempt'] ?? null;
        if (($state['version'] ?? null) !== 1 || !is_int($attempt) || $attempt < 1 || $attempt >= PHP_INT_MAX
            || ($state['attempt'] ?? null) !== $attempt || ($state['generation'] ?? null) !== $attempt + 1) {
            return null;
        }
        $departed = self::privateRecoveryDate($state['departure'] ?? null);
        $due = self::privateRecoveryDate($state['due'] ?? null);
        if ($departed === null || $due === null || $departed > $at || $due <= $departed) {
            return null;
        }
        $phase = $state['state'] ?? null;
        if ($phase === PirateSurvivorRecoveryLeg::TRAVELING) {
            return $attack->returnBlocked && $attack->reservedShips !== [] && ($state['observed'] ?? null) === null
                ? ['recovery_travel', 'recovery_due', $state['due']] : null;
        }
        $observed = self::privateRecoveryDate($state['observed'] ?? null);
        if ($observed === null || $observed < $due || $observed > $at) {
            return null;
        }
        if ($phase === PirateSurvivorRecoveryLeg::DELIVERED && !$attack->returnBlocked && $attack->reservedShips === []
            && ($attack->timeline['settled'] ?? null) === $state['observed']) {
            return ['recovery_delivered', 'recovery_observed', $state['observed']];
        }
        if (!$attack->returnBlocked || $attack->reservedShips === []) {
            return null;
        }

        return match ($phase) {
            PirateSurvivorRecoveryLeg::DESTINATION_LOST => ['recovery_lost', 'recovery_observed', $state['observed']],
            PirateSurvivorRecoveryLeg::CAPACITY_BLOCKED => ['recovery_capacity', 'recovery_observed', $state['observed']],
            default => null,
        };
    }

    private static function privateRecoveryDate(mixed $value): ?\DateTimeImmutable
    {
        if (!is_string($value) || preg_match('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}\+00:00$/D', $value) !== 1) {
            return null;
        }
        $at = \DateTimeImmutable::createFromFormat(DATE_ATOM, $value);

        return $at !== false && $at->format(DATE_ATOM) === $value ? $at : null;
    }

    /**
     * Preuve minimale de lecture pour l'ordre de campagne, sans charger de texte ou révéler la flotte.
     * L'appelant fournit des lignes propriétaires lues en base, jamais des preuves envoyées par le client.
     * @return array{key:string, won:bool}|null
     */
    public function orderingProofFor(
        PlayerUniverse $viewer,
        PirateReconnaissance $reconnaissance,
        ?PirateAttackCampaign $attack,
        \DateTimeImmutable $at,
    ): ?array {
        if ($reconnaissance->id === null) {
            return null;
        }
        $context = $reconnaissance->narrativeContextFor($viewer, $at);
        $scout = $reconnaissance->projectFor($viewer, $at);
        if ($context === null || $scout === null) {
            return null;
        }

        // Le retour des survivants n'annule pas une victoire déjà résolue.
        $won = $scout['status'] === 'reported' && $attack?->id !== null
            && $this->sameReconnaissance($reconnaissance, $attack)
            && $attack->departedAt <= $at
            && $this->attackMoment($attack, $at) === PirateCampaignMoment::Victoire;

        return ['key' => $context['key'], 'won' => $won];
    }

    /** Le rapport natif encode le camp avant le type et omet le rang Basique. */
    private function escortLosses(?array $losses): ?array
    {
        if ($losses === null) {
            return null;
        }

        $result = [];
        foreach ($losses as $key => $quantity) {
            $parts = explode(':', $key);
            if (count($parts) >= 2 && count($parts) <= 3 && $parts[0] === 'escort') {
                $result[$parts[1]][$parts[2] ?? 'basic'] = $quantity;
            }
        }

        return $result;
    }

    private function sameReconnaissance(PirateReconnaissance $reconnaissance, PirateAttackCampaign $attack): bool
    {
        return $attack->reconnaissance === $reconnaissance
            || ($reconnaissance->id !== null && $attack->reconnaissance->id === $reconnaissance->id);
    }

    private function attackMoment(PirateAttackCampaign $attack, \DateTimeImmutable $at): PirateCampaignMoment
    {
        $resolved = $attack->timeline['resolved'] ?? null;
        if ($attack->report === null || !is_string($resolved) || new \DateTimeImmutable($resolved) > $at) {
            return PirateCampaignMoment::AttaqueEngagee;
        }

        // Seul le rapport natif fait foi, pas un statut de retour ou une échéance simplement dépassée.
        return match ($attack->report['outcome'] ?? null) {
            'attacker_wins' => PirateCampaignMoment::Victoire,
            'defender_wins' => PirateCampaignMoment::Defaite,
            'draw' => PirateCampaignMoment::IssueIndecise,
            default => PirateCampaignMoment::AttaqueEngagee,
        };
    }
}
