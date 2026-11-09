<?php
namespace App\Service\Combat;

/** Présente les données enregistrées, sans rejouer ni modifier le combat. */
final class CombatReportPresenter
{
    public function present(array $snapshot, array $rounds, int $membershipId): array
    {
        $viewerCamp = 'defenders';
        $viewerDefends=true;$participates=false;
        foreach ($snapshot['participants'] ?? [] as $participant) {
            if ((int) ($participant['player_universe_id'] ?? 0) === $membershipId) {
                $viewerCamp = $participant['camp'] ?? (($participant['role'] ?? '') === 'attacker' ? 'attackers' : 'defenders');
                $viewerDefends=($participant['role']??null)==='defender';$participates=true;
                break;
            }
        }
        $viewerVerdict=null;$occupationResult=$snapshot['occupation_result']??null;
        if($participates && is_array($occupationResult)) {
            $winner=$occupationResult['winner_camp']??null;
            $viewerVerdict=$winner!==null?($viewerCamp===$winner?'win':'loss'):
                (($occupationResult['holding_camp']??null)===$viewerCamp?'hold':(($occupationResult['outcome']??null)==='draw'?'draw':'loss'));
        }
        $groups = [];
        $units = [];
        foreach ($snapshot['participants'] ?? [] as $participant) {
            $npc = (bool) ($participant['pve_npc'] ?? false);
            $owner = $npc ? 'npc:'.$participant['planet_id'] : (string) $participant['fleet_id'];
            $role = $participant['role'];
            $camp = $participant['camp'] ?? ($role === 'attacker' ? 'attackers' : 'defenders');
            $label = $npc ? 'Flotte PNJ' : ((int) ($participant['player_universe_id'] ?? 0) === $membershipId ? 'Votre flotte' : ($camp === $viewerCamp ? 'Flotte alliée' : 'Flotte adverse'));
            foreach ($participant['units'] ?? [] as $unit) {
                $this->addGroup($groups, $units, $unit, $owner, $role, $camp === $viewerCamp ? 'own' : 'enemy', $label, (int) ($participant['arrival_round'] ?? 1), $snapshot);
            }
        }
        foreach ($snapshot['defenses'] ?? [] as $defense) {
            $this->addGroup($groups, $units, $defense, 'planet:'.$defense['id'], 'static_defense', $viewerCamp === 'defenders' ? 'own' : 'enemy', 'Défense planétaire', 1, $snapshot);
        }

        $details = [];
        $destroyed = [];
        $deadUnits = [];
        foreach ($rounds as $round) {
            $number = (int) $round['number'];
            $rows = [];
            foreach ($groups as $key => $group) {
                if ($group['round'] > $number) continue;
                $alive = $group['initial'] - ($destroyed[$key] ?? 0);
                if ($alive <= 0) continue;
                $rows[$key] = $group + ['start' => $alive, 'round_lost' => 0, 'shots' => 0, 'hits' => 0];
            }
            foreach ($round['shots'] ?? [] as $shot) {
                $source = $units[$shot['attacker'] ?? ''] ?? null;
                if ($source !== null && isset($rows[$source])) {
                    ++$rows[$source]['shots'];
                    $rows[$source]['hits'] += !empty($shot['hit']) ? 1 : 0;
                }
                $target = (string) ($shot['target'] ?? '');
                $targetGroup = $units[$target] ?? null;
                if (!empty($shot['destroyed']) && $targetGroup !== null && !isset($deadUnits[$target])) {
                    $deadUnits[$target] = true;
                    $destroyed[$targetGroup] = ($destroyed[$targetGroup] ?? 0) + 1;
                    if (isset($rows[$targetGroup])) ++$rows[$targetGroup]['round_lost'];
                }
            }
            $sides = ['own' => [], 'enemy' => []];
            foreach ($rows as $row) {
                $row['end'] = max(0, $row['start'] - $row['round_lost']);
                $row['misses'] = $row['shots'] - $row['hits'];
                $row['accuracy'] = $row['shots'] > 0 ? round(100 * $row['hits'] / $row['shots']) : null;
                $sides[$row['side']][] = $row;
            }
            $details[] = ['number' => $number] + $sides;
        }

        $reconstruction = [];
        foreach ($groups as $group) {
            if ($group['role'] !== 'static_defense') continue;
            $key = $group['slug'].':'.$group['tier'];
            if (!isset($reconstruction[$key])) {
                $reconstruction[$key] = ['unit' => $group['unit'], 'tier' => $group['tier'], 'side' => $group['side'], 'lost' => 0, 'rebuilt' => (int) ($snapshot['defense_rebuilt'][$key] ?? 0)];
            }
            $reconstruction[$key]['lost'] += $group['lost'];
        }
        foreach ($reconstruction as &$row) {
            $row['permanent'] = max(0, $row['lost'] - $row['rebuilt']);
        }
        unset($row);

        return ['rows' => array_values($groups), 'round_details' => $details, 'reconstruction' => array_values($reconstruction), 'viewer_defends' => $viewerDefends, 'viewer_verdict'=>$viewerVerdict];
    }

    private function addGroup(array &$groups, array &$units, array $unit, string $owner, string $role, string $side, string $label, int $round, array $snapshot): void
    {
        $tier = (string) ($unit['tier'] ?? 'basic');
        $suffix = $tier === 'basic' ? '' : ':'.$tier;
        $slug = (string) $unit['slug'];
        $key = $owner.':'.$slug.$suffix;
        $groups[$key] = ['unit' => (string) ($unit['label'] ?? $slug), 'slug' => $slug, 'tier' => $tier, 'label' => $label, 'role' => $role, 'side' => $side, 'round' => $round, 'initial' => (int) $unit['quantity'], 'lost' => (int) ($snapshot['losses'][$key] ?? 0)];
        for ($index = 1; $index <= (int) $unit['quantity']; ++$index) {
            $units[$owner.':'.$role.':'.$slug.$suffix.':'.$index] = $key;
        }
    }
}
