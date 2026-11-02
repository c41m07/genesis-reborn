<?php
namespace App\Service\Combat;
use App\Entity\Universe;
use App\Service\Catalog\GameCatalog;

/** Applique les bonus des technologies actives de tous les rangs aux statistiques des unités. */
final class TechnologyEffectCalculator
{
    public function __construct(private GameCatalog $catalog) {}
    public function applyToShips(array $ships,array $levels,Universe $universe):array
    {
        return $this->apply($ships,$levels,$universe,['all_ships','all_combat_units','combat_ships']);
    }
    public function applyToDefenses(array $defenses,array $levels,Universe $universe):array
    {
        return $this->apply($defenses,$levels,$universe,['all_combat_units']);
    }
    private function apply(array $units,array $levels,Universe $universe,array $targets):array
    {
        foreach($levels as $slug=>$rankLevels){
            if(!is_string($slug))continue;
            if(is_numeric($rankLevels))$rankLevels=['basic'=>(int)$rankLevels];
            if(!is_array($rankLevels))continue;
            foreach(GameCatalog::TIERS as $tier){
                $level=(int)($rankLevels[$tier]??0);if($level<1)continue;
                $definition=$this->catalog->getTierForUniverse($universe,'technologies',$slug,$tier);$effect=$definition['effect']??null;
                if(!is_array($effect)||!in_array($effect['targets']??null,$targets,true))continue;
                $factor=GameCatalog::tierFactor($tier);$maxLevel=max(1,(int)($definition['max_level']??$level));$effectiveLevel=min($level,$maxLevel);
                if(($effect['type']??null)==='extra_shot_chance'){
                    $chance=min((float)($effect['max_bonus']??1)*$factor,(float)($effect['value_per_level']??0)*$effectiveLevel*$factor);
                    foreach($units as &$unit){if((float)($unit['base_attack']??0)<=0)continue;$unit['extra_shot_chances'][]=max(0.0,min(1.0,$chance));}unset($unit);continue;
                }
                if(($effect['type']??null)!=='stat_bonus')continue;
                $stats=$effect['stats']??[$effect['stat']??null];$stats=array_values(array_filter($stats,'is_string'));
                $per=$effect['value_per_level']??null;$formula=$effect['formula']??'flat';
                if(!is_numeric($per)||!is_finite((float)$per)||!in_array($formula,['flat','percent'],true))throw new \DomainException('Effet technologique invalide : '.$slug.'.');
                $bonus=(float)$per*$effectiveLevel*$factor;
                if(isset($effect['max_bonus']))$bonus=min($bonus,(float)$effect['max_bonus']*$factor);
                foreach($units as &$unit){
                    if(($effect['targets']??null)==='combat_ships'&&(float)($unit['base_attack']??0)<=0)continue;
                    foreach($stats as $stat){
                        if(!array_key_exists($stat,$unit)||!is_numeric($unit[$stat]))throw new \DomainException('Statistique absente pour l’effet technologique : '.$slug.'.');
                        if($formula==='percent'){$direction=($effect['direction']??'increase')==='decrease'?-1:1;$unit[$stat]=(float)$unit[$stat]*(1+$direction*$bonus);}else{$unit[$stat]=(float)$unit[$stat]+$bonus;}
                    }
                }unset($unit);
            }
        }
        return $units;
    }
}
