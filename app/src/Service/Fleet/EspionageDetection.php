<?php
namespace App\Service\Fleet;

final class EspionageDetection
{
    /** Les rangs des sondes ne modifient que la détection, jamais le renseignement. */
    public static function chance(array $probesByTier,int $counterLevel,int $stealthLevel,int $defensiveProbes,array $settings): int
    {
        $count=0;$weightedBase=0.0;
        foreach($probesByTier as $tier=>$quantity) {
            if($quantity<=0) continue;
            $count+=$quantity;
            $weightedBase+=$quantity*$settings['base_detection_by_tier'][$tier];
        }
        if($count<1) throw new \LogicException('Une mission d’espionnage doit transporter une sonde.');
        $defensiveBonus=min($settings['local_probe_bonus_cap'],intdiv(max(0,$defensiveProbes),$settings['local_probe_batch_size'])*$settings['local_probe_weight']);
        $raw=$weightedBase/$count
            +$settings['intelligence_countermeasures_weight']*$counterLevel
            -$settings['stealth_weight']*$stealthLevel
            +$settings['extra_probe_weight']*($count-1)
            +$defensiveBonus;
        return (int)round(max($settings['min_detection_chance'],min($settings['max_detection_chance'],$raw)));
    }
}
