<?php

declare(strict_types=1);

namespace App\Service\PirateCampaign;

use App\Entity\{PirateAttackCampaign, PirateReconnaissance, PlayerUniverse};

/** Modèle d'une future page privée. Aucune route, écriture ou sélection de site. */
final readonly class PirateCampaignWorkspace
{
    public function __construct(
        private PirateCampaignCatalogue $catalogue,
        private PirateCampaignSequence $sequence,
        private PirateCampaignPresenter $presenter,
    ) {
    }

    /**
     * Le futur contrôleur charge au plus une ligne par clé canonique, relit le propriétaire en base,
     * puis passe son contexte courant. Le numéro demandé n'est qu'une sélection de lecture.
     *
     * @param list<array{reconnaissance:PirateReconnaissance, attack:?PirateAttackCampaign}> $rows
     * @return array{mission_count:int,current:?int,selected:int,history:list<array{number:int,won:bool}>,
     *   signal_pending:bool,briefing:?array}|null
     */
    public function forViewer(
        PlayerUniverse $viewer,
        array $rows,
        \DateTimeImmutable $at,
        ?int $requested = null,
    ): ?array {
        $keys = $this->catalogue->keys();
        if (count($rows) > count($keys) || ($requested !== null && ($requested < 1 || !isset($keys[$requested - 1])))) {
            return null;
        }

        $order = $this->sequence->forViewer($viewer, $rows, $at);
        if ($order === null) {
            return null;
        }
        $current = $order['current_mission'];
        $consultable = $order['consultable'];
        $selected = $requested === null ? ($current ?? end($consultable)) : $keys[$requested - 1];
        // Un numéro futur ou un historique d'un autre empire reçoit le même refus neutre.
        if (!is_string($selected) || ($selected !== $order['next_signal'] && !in_array($selected, $consultable, true))) {
            return null;
        }

        $signalPending = $selected === $order['next_signal'];
        $briefing = $signalPending ? null : $this->ownedBriefing($viewer, $rows, $selected, $at);
        if (!$signalPending && $briefing === null) {
            return null;
        }
        $positions = array_flip($keys);
        $history = [];
        foreach ($consultable as $key) {
            $history[] = ['number' => $positions[$key] + 1, 'won' => $key !== $current];
        }

        return [
            'mission_count' => count($keys),
            'current' => $current === null ? null : $positions[$current] + 1,
            'selected' => $positions[$selected] + 1,
            'history' => $history,
            // L'ordre est admissible à la lecture, pas un départ disponible : aucun site n'a été sélectionné.
            'signal_pending' => $signalPending,
            'briefing' => $briefing,
        ];
    }

    private function ownedBriefing(PlayerUniverse $viewer, array $rows, string $key, \DateTimeImmutable $at): ?array
    {
        foreach ($rows as $row) {
            $scout = $row['reconnaissance'];
            if ($scout->id !== null && ($scout->narrativeContextFor($viewer, $at)['key'] ?? null) === $key) {
                return $this->presenter->forProgress($viewer, $scout, $row['attack'], $at);
            }
        }

        return null;
    }
}
