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
     * @return array{phase: string, narrative: array, composition: ?array, return_narrative: ?array}|null
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
        if ($scout['status'] === 'reported') {
            $phase = PirateCampaignMoment::RenseignementRecu;
            if ($attack !== null && $this->sameReconnaissance($reconnaissance, $attack) && $attack->departedAt <= $at) {
                $phase = $this->attackMoment($attack, $at);
                if ($phase !== PirateCampaignMoment::AttaqueEngagee && ($attack->timeline['state'] ?? null) === 'returning'
                    && !$attack->returnBlocked && $attack->reservedShips !== []) {
                    $returnNarrative = $this->catalogue->narrativeFor($context['key'], $context['site'], $viewer, PirateCampaignMoment::RetourDesSurvivants);
                }
            }
        }
        $narrative = $this->catalogue->narrativeFor($context['key'], $context['site'], $viewer, $phase);
        if ($narrative === null) {
            return null;
        }

        return [
            'phase' => $phase->name,
            'narrative' => $narrative,
            // Le catalogue ne constitue jamais une preuve de reconnaissance remise.
            'composition' => $scout['composition'],
            'return_narrative' => $returnNarrative,
        ];
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
