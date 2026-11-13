<?php
namespace App\Service\Combat;

use App\Entity\Universe;
use App\Service\Catalog\GameCatalog;

final class CombatUnitFactory
{
    public function __construct(private GameCatalog $catalog,private TechnologyEffectCalculator $technologies) {}

    public function ships(Universe $universe,array $composition,array $levels): array
    {
        $units=[];
        foreach($composition as $slug=>$tiers) foreach($tiers as $tier=>$quantity) if($quantity>0) $units[]=$this->catalog->getTierForUniverse($universe,'ships',$slug,$tier)+['quantity'=>$quantity,'tier'=>$tier];
        return $this->technologies->applyToShips($units,$levels,$universe);
    }

    public function defense(Universe $universe,string $slug,string $tier,array $levels,array $fortressLevels): array
    {
        $definition=$this->catalog->getTierForUniverse($universe,'defenses',$slug,$tier);
        $definition=$this->technologies->applyToDefenses([$definition],$levels,$universe)[0];
        $fortress=$this->catalog->getForUniverse($universe,'buildings','fortress')['effect']??[];
        if(($fortress['targets']??null)==='ground_defenses' && ($fortress['stat']??null)==='hp') {
            $base=$this->catalog->get('buildings','fortress');
            $bonus=GameCatalog::diminishingBonus($fortressLevels,(float)($fortress['max_bonus']??25),(int)($base['max_level']??25));
            $definition['hp']*=1+$bonus/100;
        }
        return $definition;
    }
}
