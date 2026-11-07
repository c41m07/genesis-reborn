<?php
namespace App\Service\Combat;
use App\Entity\Universe;
use App\Service\Catalog\GameCatalog;

/** Applique une progression commune et décroissante pour les trois rangs technologiques. */
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
            $definition=$this->catalog->getForUniverse($universe,'technologies',$slug);$effect=$definition['effect']??null;
            if(!is_array($effect)||!in_array($effect['targets']??null,$targets,true))continue;
            $maxLevel=max(1,(int)($definition['max_level']??25));
            if(($effect['type']??null)==='extra_shot_chance'){
                $cap=(float)($effect['max_bonus']??0.5);
                $chance=GameCatalog::diminishingBonus($rankLevels,$cap,$maxLevel);
                foreach($units as &$unit){if((float)($unit['base_attack']??0)>0)$unit['extra_shot_chances'][]=max(0.0,min(1.0,$chance));}unset($unit);
                continue;
            }
            if(($effect['type']??null)!=='stat_bonus')continue;
            $stats=$effect['stats']??[$effect['stat']??null];$stats=array_values(array_filter($stats,'is_string'));
            $cap=$effect['max_bonus']??null;$formula=$effect['formula']??'flat';
            if(!is_numeric($cap)||!is_finite((float)$cap)||!in_array($formula,['flat','percent'],true))throw new \DomainException('Effet technologique invalide : '.$slug.'.');
            $bonus=GameCatalog::diminishingBonus($rankLevels,(float)$cap,$maxLevel);
            foreach($units as &$unit){
                if(($effect['targets']??null)==='combat_ships'&&(float)($unit['base_attack']??0)<=0)continue;
                foreach($stats as $stat){
                    if(!array_key_exists($stat,$unit)||!is_numeric($unit[$stat]))throw new \DomainException('Statistique absente pour l’effet technologique : '.$slug.'.');
                    if($formula==='percent'){$direction=($effect['direction']??'increase')==='decrease'?-1:1;$unit[$stat]=(float)$unit[$stat]*(1+$direction*$bonus);}else{$unit[$stat]=(float)$unit[$stat]+$bonus;}
                }
                if(in_array('base_attack',$stats,true)&&isset($unit['secondary_attack']))$unit['secondary_attack']=(float)$unit['secondary_attack']*(1+$bonus);
            }unset($unit);
        }
        return $units;
    }
}
