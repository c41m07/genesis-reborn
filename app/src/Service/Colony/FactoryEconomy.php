<?php
namespace App\Service\Colony;

/** Intègre les usines par segments entre épuisements et plafonds de stock. */
final class FactoryEconomy
{
    private const STOCK_EPSILON=1e-7;

    public static function simulate(array $baseRates,array $factories,array $stocks,array $capacities,int $elapsed,float $minimumEfficiency=0.1):array
    {
        if($elapsed<0||!is_finite($minimumEfficiency)||$minimumEfficiency<0||$minimumEfficiency>1)throw new \InvalidArgumentException('Paramètres de production invalides.');
        foreach($baseRates as $rate)if(!is_finite((float)$rate)||$rate<0)throw new \InvalidArgumentException('Débit de production invalide.');
        $stocks=array_map(static fn($v):float=>max(0.0,(float)$v),$stocks);$remaining=(float)$elapsed;$guard=0;
        while($remaining>1e-9&&++$guard<=64){
            $stocks=self::normalizeStocks($stocks,$capacities);
            $point=self::operatingPoint($baseRates,$factories,$stocks,$capacities,$minimumEfficiency);$flows=$point['flows'];$step=$remaining;
            foreach($flows as $resource=>$flow){$stock=$stocks[$resource]??0.0;$cap=(float)($capacities[$resource]??INF);if($flow>1e-12&&$stock<$cap-1e-9)$step=min($step,($cap-$stock)/$flow);elseif($flow< -1e-12&&$stock>1e-9)$step=min($step,$stock/-$flow);}
            if($step<=0)throw new \LogicException('La simulation de production ne progresse plus.');
            foreach($flows as $resource=>$flow){$cap=(float)($capacities[$resource]??INF);$stocks[$resource]=min($cap,max(0.0,($stocks[$resource]??0.0)+$flow*$step));}
            $remaining-=$step;
        }
        if($remaining>1e-9)throw new \LogicException('La simulation de production n’a pas intégré toute la durée.');
        $stocks=self::normalizeStocks($stocks,$capacities);
        $point=self::operatingPoint($baseRates,$factories,$stocks,$capacities,$minimumEfficiency);
        return ['stocks'=>$stocks,'rates'=>$point['flows'],'efficiency'=>$point['efficiency'],'antimatter_efficiency'=>$point['antimatter_efficiency'],'electricity_consumption'=>$point['electricity_consumption'],'hydrogen_consumption'=>$point['hydrogen_consumption']];
    }

    private static function normalizeStocks(array $stocks,array $capacities):array
    {
        foreach($stocks as $resource=>&$stock){
            if($stock<self::STOCK_EPSILON)$stock=0.0;
            $capacity=(float)($capacities[$resource]??INF);
            if($capacity-$stock<=self::STOCK_EPSILON)$stock=$capacity;
        }
        unset($stock);return $stocks;
    }

    private static function operatingPoint(array $base,array $factories,array $stocks,array $capacities,float $floor):array
    {
        // Un stockage plein suspend sa production industrielle ; hydrogène et électricité alimentent encore les consommateurs locaux.
        $factories=array_values(array_filter($factories,static function(array $factory)use($stocks,$capacities):bool{
            $resource=$factory['resource'];
            return in_array($resource,['hydrogen','electricity'],true)||($stocks[$resource]??0)<($capacities[$resource]??INF)-self::STOCK_EPSILON;
        }));
        $hydrogenFull=($stocks['hydrogen']??0)>=($capacities['hydrogen']??INF)-self::STOCK_EPSILON;
        $power=max(0.0,(float)($base['electricity']??0));$nominalPower=0.0;
        foreach($factories as $factory){$nominalPower+=(float)($factory['electricity']??0);if(($factory['resource']??null)==='electricity')$power+=(float)$factory['rate'];}
        $resolve=function(float $poweredFraction)use($base,$factories,$stocks,$floor,$hydrogenFull,$capacities):array{
            $efficiency=$floor+(1-$floor)*$poweredFraction;$hydrogenBase=max(0.0,(float)($base['hydrogen']??0));$hydrogenFactory=0.0;$hydrogenNeed=0.0;
            foreach($factories as $factory){if(($factory['resource']??null)==='hydrogen')$hydrogenFactory+=(float)$factory['rate']*$efficiency;$hydrogenNeed+=(float)($factory['hydrogen']??0);}
            $hydrogenCover=($stocks['hydrogen']??0)>1e-9?1.0:($hydrogenNeed>0?min(1.0,($hydrogenBase+$hydrogenFactory)/$hydrogenNeed):1.0);
            $antimatterEfficiency=min($efficiency,$hydrogenCover);$electricUse=0.0;$hydrogenUse=0.0;$flows=$base;
            foreach($factories as $factory){
                $resource=$factory['resource'];$factor=$resource==='antimatter'?$antimatterEfficiency:($resource==='electricity'?1.0:$efficiency);
                if($resource==='hydrogen'&&$hydrogenFull&&$hydrogenFactory>0)$factor*=min(1.0,max(0.0,$hydrogenNeed*$antimatterEfficiency-$hydrogenBase)/$hydrogenFactory);
                $flows[$resource]=($flows[$resource]??0.0)+(float)$factory['rate']*$factor;
                $draw=(float)($factory['electricity']??0);
                if($resource==='antimatter')$draw*=max(0.0,min(1.0,($factor-$floor)/max(1e-9,1-$floor)));else $draw*=$poweredFraction;
                if($resource==='hydrogen'&&$hydrogenFull&&$efficiency>0)$draw*=$factor/$efficiency;
                $electricUse+=$draw;if($resource==='antimatter')$hydrogenUse+=(float)($factory['hydrogen']??0)*$factor;
            }
            $flows['electricity']=($flows['electricity']??0.0)-$electricUse;$flows['hydrogen']=($flows['hydrogen']??0.0)-$hydrogenUse;
            foreach($flows as $resource=>&$flow)if(($stocks[$resource]??0)>=($capacities[$resource]??INF)-self::STOCK_EPSILON)$flow=min(0.0,(float)$flow);
            unset($flow);
            return ['flows'=>$flows,'electricity_consumption'=>$electricUse,'hydrogen_consumption'=>$hydrogenUse,'efficiency'=>$efficiency,'antimatter_efficiency'=>$antimatterEfficiency];
        };
        $x=1.0;if(($stocks['electricity']??0.0)<=1e-9&&$nominalPower>0){$full=$resolve(1.0);if($full['electricity_consumption']>$power+1e-12){$low=0.0;$high=1.0;for($i=0;$i<48;++$i){$mid=($low+$high)/2;if($resolve($mid)['electricity_consumption']<=$power)$low=$mid;else $high=$mid;}$x=$low;}}
        return $resolve($x);
    }
}
