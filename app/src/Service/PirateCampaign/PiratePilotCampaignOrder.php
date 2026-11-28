<?php

declare(strict_types=1);

namespace App\Service\PirateCampaign;

use App\Entity\{PirateAttackCampaign, PirateReconnaissance, PlayerUniverse};

/** Choix de la seule mission commandable ; la consultation historique suit son lecteur séparé. */
final readonly class PiratePilotCampaignOrder
{
    public function __construct(private PirateCampaignCatalogue $catalogue, private PirateCampaignSequence $sequence)
    {
    }

    /**
     * @param list<array{reconnaissance:PirateReconnaissance,attack:?PirateAttackCampaign}> $ownedRows
     * @return array{ordinal:int,key:string,isNew:bool,preceding:?PirateReconnaissance,progress:string}|null
     */
    public function choose(PlayerUniverse $owner, array $ownedRows, int $ordinal, \DateTimeImmutable $at): ?array
    {
        $keys = $this->catalogue->keys($owner->universe);
        if ($ordinal < 1 || !isset($keys[$ordinal - 1])) {
            return null;
        }
        $key = $keys[$ordinal - 1];
        $sequence = $this->sequence->forViewer($owner, $ownedRows, $at);
        if ($sequence === null || $sequence['current_mission'] !== $key) {
            // Y compris catalogue terminé, mission future et dossier historique.
            return null;
        }
        $isNew = $sequence['next_signal'] === $key;
        $previous = null;
        $previousState = null;
        if ($ordinal > 1) {
            $priorKeys = array_intersect(array_slice($keys,0,$ordinal-1),$sequence['consultable']);
            $priorKey = end($priorKeys);
            foreach ($ownedRows as $row) {
                $recon = $row['reconnaissance'];
                $attack = $row['attack'];
                if (($recon->narrativeContextFor($owner, $at)['key'] ?? null) === $priorKey
                    && $attack !== null && $attack->reconnaissance->id === $recon->id
                    && ($attack->report['outcome'] ?? null) === 'attacker_wins') {
                    $previous = $recon;
                    $previousState = [$priorKey, $recon->id, $attack->id, $attack->timeline['attempt'] ?? null, $attack->timeline['resolved'] ?? null];
                    break;
                }
            }
            if ($previous === null) {
                return null;
            }
        }
        $fence = hash('sha256', json_encode([$ordinal, $key, $isNew, $previousState], JSON_THROW_ON_ERROR));

        return ['ordinal' => $ordinal, 'key' => $key, 'isNew' => $isNew, 'preceding' => $previous, 'progress' => $fence];
    }
}
