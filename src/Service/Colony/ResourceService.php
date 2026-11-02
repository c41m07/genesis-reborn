<?php
namespace App\Service\Colony;
use App\Entity\{Planet,PlanetResource,PlanetBuilding};
use App\Repository\GameRepository;
use App\Manager\GameManager;
use App\Service\Catalog\GameCatalog;
use Psr\Clock\ClockInterface;
final class ResourceService
{
    public function __construct(private GameRepository $repo,private GameManager $manager,private GameCatalog $catalog,private ClockInterface $clock) {}
    public static function stock(float $stock,float $rate,\DateTimeImmutable $last,\DateTimeImmutable $now,float $cap): float
    {
        return min($cap,max(0,$stock)+max(0,$now->getTimestamp()-$last->getTimestamp())*$rate);
    }
    public function initialize(Planet $planet,bool $colony=false,?array $initialLevels=null,float $stockMultiplier=1): void
    {
        $levels=$initialLevels??$this->repo->levelsByTier(PlanetBuilding::class,['planet'=>$planet]);
        foreach ($this->catalog->rules()['resources'] as $slug=>$label) {
            if($this->repo->one(PlanetResource::class,['planet'=>$planet,'resourceType'=>$slug])) continue;
            $capacity=$this->capacity($planet,$slug,$levels);
            $stock=$this->catalog->rules()[$colony?'colony_initial_stock':'initial_stock'][$slug];
            $r=new PlanetResource();$r->planet=$planet;$r->resourceType=$slug;
            $r->currentStock=min($capacity,$stock*$stockMultiplier);
            $r->lastUpdatedAt=$this->clock->now();
            $r->productionRate=$this->rate($planet,$slug,0,$levels);
            $this->manager->save($r);
        }
    }
    public static function miningWindow(float $energyProductionRate,float $mineUpkeepRate,float $storedEnergy,int $elapsedSeconds): array
    {
        if(!is_finite($energyProductionRate)||!is_finite($mineUpkeepRate)||!is_finite($storedEnergy)||$energyProductionRate<0||$mineUpkeepRate<0||$storedEnergy<0||$elapsedSeconds<0) {
            throw new \InvalidArgumentException('Fenêtre de production énergétique invalide.');
        }
        $net=$energyProductionRate-$mineUpkeepRate;
        $fullSeconds=(float)$elapsedSeconds;
        if($net<0&&$mineUpkeepRate>0) $fullSeconds=min($fullSeconds,$storedEnergy/(-$net));
        $throttledSeconds=max(0,$elapsedSeconds-$fullSeconds);
        $throttle=$mineUpkeepRate>0&&($throttledSeconds>0||($net<0&&$storedEnergy<=0))?min(1,$energyProductionRate/$mineUpkeepRate):1;
        return [
            'full_mining_seconds'=>$fullSeconds,
            'throttled_mining_seconds'=>$throttledSeconds,
            'throttle'=>$throttle,
            'energy_stock'=>max(0.0,$storedEnergy+$net*$elapsedSeconds),
        ];
    }

    public function capacity(Planet $planet,string $slug,array $buildings): float
    {
        $storedLevels=$buildings['storage_'.$slug]??0;$added=0.0;
        foreach(GameCatalog::TIERS as $tier)$added+=$this->tierLevel($storedLevels,$tier)*$this->catalog->tierFactor($tier)*$this->catalog->rules()['storage_capacity_per_level'];
        return $this->catalog->rules()['base_storage_capacity']+$added;
    }
    public function rate(Planet $planet,string $slug,int $mineLevel,?array $levels=null): float
    {
        $rules=$this->catalog->rules();$levels??=['mine_'.$slug=>['basic'=>$mineLevel]];$bonus=0.0;
        $definitions=$this->catalog->forUniverse($planet->solarSystem->galaxy->universe)['catalog']['buildings'];
        foreach($definitions as $building)if(($building['production_resource']??null)===$slug){
            $ranked=$levels[$building['slug']]??0;$per=(float)($building['production_per_level']??$rules['mine_production_per_level']);
            foreach(GameCatalog::TIERS as $tier)$bonus+=$this->tierLevel($ranked,$tier)*$per*$this->catalog->tierFactor($tier);
        }
        $mult=$this->catalog->forUniverse($planet->solarSystem->galaxy->universe)['multipliers']['resource_production'];
        return ((float)$rules['base_production'][$slug]+$bonus)*$this->positionFactor($planet,$slug)*$mult;
    }
    private function tierLevel(int|array $levels,string $tier):int
    {
        if(is_int($levels))return $tier==='basic'?$levels:0;
        return (int)($levels[$tier]??0);
    }

    private function positionFactor(Planet $planet,string $slug): float
    {
        $factors=$this->catalog->rules()['position_production_factors']??[];
        $group=match($slug){'metal','energy','carbon'=>'metal_energy_carbon',default=>$slug};
        $definition=$factors[$group]??[];
        if(isset($definition['all_positions'])) return (float)$definition['all_positions'];
        $first=(float)($definition['position_1']??1);
        $last=(float)($definition['position_9']??1);
        $ratio=($planet->position-1)/8;
        return $first+($last-$first)*$ratio;
    }

    private function mineUpkeep(array $levels): float
    {
        $rules=$this->catalog->rules();
        $definitions=$this->catalog->all('buildings');
        $weightedExtractorLevels=0.0;
        foreach($definitions as $building) {
            $resource=$building['production_resource']??null;
            if(is_string($resource)&&$resource!=='energy'){
                $ranked=$levels[$building['slug']]??0;
                foreach(GameCatalog::TIERS as $tier)$weightedExtractorLevels+=$this->tierLevel($ranked,$tier)*$this->catalog->tierFactor($tier);
            }
        }
        return $weightedExtractorLevels*(float)($rules['mine_energy_upkeep_per_level']??0);
    }

    public function snapshot(Planet $planet): array
    {
        $levels=$this->repo->levelsByTier(PlanetBuilding::class,['planet'=>$planet]);
        $rows=[];
        foreach($this->repo->all(PlanetResource::class,['planet'=>$planet]) as $resource) $rows[$resource->resourceType]=$resource;
        $rates=[];
        foreach($this->catalog->rules()['resources'] as $slug=>$label) $rates[$slug]=(float)($rows[$slug]->productionRate??$this->rate($planet,$slug,0,$levels));
        if($rows===[]) {
            $empty=[];foreach($this->catalog->rules()['resources'] as $slug=>$label)$empty[$slug]=['label'=>$label,'stock'=>0.0,'rate'=>0.0,'capacity'=>(float)$this->catalog->rules()['base_storage_capacity']];
            return $empty;
        }
        $upkeep=$this->mineUpkeep($levels);
        $energyNet=($rates['energy']??0)-$upkeep;
        $anchor=$rows['energy']->lastUpdatedAt??array_values($rows)[0]->lastUpdatedAt;
        $elapsed=max(0,$this->clock->now()->getTimestamp()-$anchor->getTimestamp());
        $window=self::miningWindow((float)($rates['energy']??0),$upkeep,(float)($rows['energy']->currentStock??0),$elapsed);
        $fullSeconds=$window['full_mining_seconds'];$throttledSeconds=$window['throttled_mining_seconds'];$throttle=$window['throttle'];
        foreach($rows as $slug=>$resource) {
            $capacity=$this->capacity($planet,$slug,$levels);
            $rate=$slug==='energy'?$energyNet:(($rates[$slug]??0)*$throttle);
            $stock=$slug==='energy'?$window['energy_stock']: $resource->currentStock+($rates[$slug]??0)*($fullSeconds+$throttledSeconds*$throttle);
            $rows[$slug]=['label'=>$this->catalog->rules()['resources'][$slug],'stock'=>min($capacity,max(0,$stock)),'rate'=>$rate,'capacity'=>$capacity];
        }
        return $rows;
    }
    public function materialize(Planet $planet,?\DateTimeImmutable $at=null): array
    {
        $at??=$this->clock->now();
        $levels=$this->repo->levelsByTier(PlanetBuilding::class,['planet'=>$planet]);
        $rows=[];
        foreach($this->repo->all(PlanetResource::class,['planet'=>$planet]) as $resource) $rows[$resource->resourceType]=$resource;
        if($rows===[]) return [];
        $anchor=$rows['energy']->lastUpdatedAt??array_values($rows)[0]->lastUpdatedAt;
        $effective=$at<$anchor?$anchor:$at;
        $elapsed=max(0,$effective->getTimestamp()-$anchor->getTimestamp());
        $rates=[];
        foreach($this->catalog->rules()['resources'] as $slug=>$label) $rates[$slug]=(float)($rows[$slug]->productionRate??$this->rate($planet,$slug,0,$levels));
        $upkeep=$this->mineUpkeep($levels);
        $energyRate=($rates['energy']??0)-$upkeep;
        $energyStart=(float)($rows['energy']->currentStock??0);
        $window=self::miningWindow((float)($rates['energy']??0),$upkeep,$energyStart,$elapsed);
        $fullMiningSeconds=$window['full_mining_seconds'];$throttledSeconds=$window['throttled_mining_seconds'];$throttle=$window['throttle'];
        foreach($rows as $slug=>$resource) {
            $capacity=$this->capacity($planet,$slug,$levels);
            if($slug==='energy') {
                $stock=$window['energy_stock'];
            } else {
                $productiveSeconds=$fullMiningSeconds+$throttledSeconds*$throttle;
                $stock=$resource->currentStock+($rates[$slug]??0)*$productiveSeconds;
            }
            $resource->currentStock=min($capacity,max(0,$stock));
            $resource->lastUpdatedAt=$effective;
        }
        return $rows;
    }
    public function consume(Planet $planet,array $costs): void
    {
        $rows=$this->materialize($planet);
        foreach ($costs as $slug=>$amount) {
            if (!isset($rows[$slug]) || !is_finite((float)$amount) || $amount<0) throw new \DomainException('Coût invalide.');
            if ($rows[$slug]->currentStock+0.000001<$amount) throw new \DomainException('Ressources insuffisantes : '.$this->catalog->rules()['resources'][$slug].'.');
        }
        foreach ($costs as $slug=>$amount) $rows[$slug]->currentStock-=$amount;
    }
    public function refund(Planet $planet,array $paid,float $ratio): void
    {
        $rows=$this->materialize($planet);$levels=$this->repo->levelsByTier(PlanetBuilding::class,['planet'=>$planet]);
        foreach($paid as $slug=>$amount) {
            if(!isset($rows[$slug]) || $amount<0 || !is_finite((float)$amount)) throw new \DomainException('Coût payé invalide.');
            $rows[$slug]->currentStock=min($this->capacity($planet,$slug,$levels),$rows[$slug]->currentStock+$amount*$ratio);
        }
    }
    /** Décharge le fret sans dépasser le stockage de la planète et renvoie la part perdue. */
    public function deposit(Planet $planet, array $cargo): array
    {
        $rows = $this->materialize($planet);
        $levels = $this->repo->levelsByTier(PlanetBuilding::class, ['planet' => $planet]);
        $delivered = [];
        $overflow = [];
        foreach ($cargo as $slug => $amount) {
            if (!isset($rows[$slug]) || !is_finite((float) $amount) || $amount < 0) {
                throw new \DomainException('Fret de retour invalide.');
            }
            $room = max(0.0, $this->capacity($planet, $slug, $levels) - $rows[$slug]->currentStock);
            $delivered[$slug] = min((float) $amount, $room);
            $overflow[$slug] = max(0.0, (float) $amount - $delivered[$slug]);
            $rows[$slug]->currentStock += $delivered[$slug];
        }

        return ['delivered' => $delivered, 'overflow' => $overflow];
    }

    public function refreshProduction(Planet $planet): void
    {
        $levels=$this->repo->levelsByTier(PlanetBuilding::class,['planet'=>$planet]);
        foreach ($this->repo->all(PlanetResource::class,['planet'=>$planet]) as $r) $r->productionRate=$this->rate($planet,$r->resourceType,0,$levels);
    }
}
