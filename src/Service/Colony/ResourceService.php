<?php
namespace App\Service\Colony;
use App\Entity\{Planet,PlanetResource,PlanetBuilding};
use App\Repository\GameRepository;
use App\Manager\GameManager;
use App\Service\Catalog\GameCatalog;
use Psr\Clock\ClockInterface;
final class ResourceService
{
    public function __construct(private GameRepository $repo,private GameManager $manager,private GameCatalog $catalog,private ClockInterface $clock,private \App\Service\Event\GameEventService $events) {}
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
    public function capacity(Planet $planet,string $slug,array $buildings): float
    {
        $storedLevels=$buildings['storage_'.$slug]??0;$added=0.0;
        foreach(GameCatalog::TIERS as $tier)$added+=$this->tierLevel($storedLevels,$tier)*$this->catalog->tierFactor($tier)*$this->catalog->rules()['storage_capacity_per_level'];
        return $this->catalog->rules()['base_storage_capacity']+$added;
    }
    public function rate(Planet $planet,string $slug,int $mineLevel,?array $levels=null):float
    {
        $levels??=['mine_'.$slug=>['basic'=>$mineLevel]];$input=$this->operatingInput($planet,$levels);$rate=(float)($input['base'][$slug]??0.0);
        foreach($input['factories'] as $factory)if($factory['resource']===$slug)$rate+=$factory['rate'];
        return $rate;
    }

    private function tierLevel(int|array $levels,string $tier):int
    {
        if(is_int($levels))return $tier==='basic'?$levels:0;
        return (int)($levels[$tier]??0);
    }

    private function positionFactor(Planet $planet,string $slug): float
    {
        $factors=$this->catalog->rules()['position_production_factors']??[];
        $group=match($slug){'metal','electricity','nano'=>$slug,default=>$slug};
        $definition=$factors[$group]??[];
        if(isset($definition['all_positions'])) return (float)$definition['all_positions'];
        $first=(float)($definition['position_1']??1);
        $last=(float)($definition['position_9']??1);
        $ratio=($planet->position-1)/8;
        return $first+($last-$first)*$ratio;
    }

    private function operatingInput(Planet $planet,array $levels,?\DateTimeImmutable $at=null):array
    {
        $rules=$this->catalog->rules();$universe=$this->catalog->forUniverse($planet->solarSystem->galaxy->universe);$mult=(float)$universe['multipliers']['resource_production'];$base=[];
        foreach($rules['resources'] as $slug=>$label)$base[$slug]=(float)($rules['base_production'][$slug]??0)*$this->positionFactor($planet,$slug)*$mult;
        $factories=[];
        $eventMultiplier=$this->events->productionMultiplier($planet->solarSystem->galaxy->universe,$at);
        foreach($base as $slug=>$rate)$base[$slug]*=$eventMultiplier;
        foreach($universe['catalog']['buildings'] as $building){$resource=$building['production_resource']??null;if(!is_string($resource))continue;$ranked=$levels[$building['slug']]??0;$weighted=0.0;foreach(GameCatalog::TIERS as $tier)$weighted+=$this->tierLevel($ranked,$tier)*$this->catalog->tierFactor($tier);if($weighted<=0)continue;$per=(float)($building['production_per_level']??$rules['mine_production_per_level']??0);$rate=$weighted*$per*$this->positionFactor($planet,$resource)*$mult*$eventMultiplier;$electricity=$resource==='electricity'?0.0:$weighted*(float)($building['electricity_use_per_level']??$rules['factory_electricity_use_per_level']??0)*$this->positionFactor($planet,$resource)*$mult;$hydrogen=$resource==='antimatter'?$rate*(float)($building['hydrogen_per_output']??$rules['antimatter_hydrogen_per_unit']??8):0.0;$factories[]=['slug'=>$building['slug'],'resource'=>$resource,'rate'=>$rate,'electricity'=>$electricity,'hydrogen'=>$hydrogen];}
        return ['base'=>$base,'factories'=>$factories];
    }

    public function factoryConsumption(Planet $planet,string $buildingSlug,array $levels):array
    {
        $input=$this->operatingInput($planet,$levels);$result=['electricity'=>0.0,'hydrogen'=>0.0];
        foreach($input['factories'] as $factory)if($factory['slug']===$buildingSlug){$result['electricity']+=(float)$factory['electricity'];$result['hydrogen']+=(float)$factory['hydrogen'];}
        return $result;
    }

    private function simulate(Planet $planet,array $levels,array $stocks,int $elapsed,?\DateTimeImmutable $at=null):array
    {
        $input=$this->operatingInput($planet,$levels,$at);$capacities=[];foreach($this->catalog->rules()['resources'] as $slug=>$label)$capacities[$slug]=$this->capacity($planet,$slug,$levels);
        return FactoryEconomy::simulate($input['base'],$input['factories'],$stocks,$capacities,$elapsed,(float)($this->catalog->rules()['minimum_factory_efficiency']??.1));
    }

    private function simulateRange(Planet $planet,array $levels,array $stocks,\DateTimeImmutable $from,\DateTimeImmutable $to):array
    {
        $universe=$planet->solarSystem->galaxy->universe;$cursor=$from;
        $points=$this->events->productionChangePoints($universe,$from,$to);$points[]=$to;
        foreach($points as $point){$seconds=max(0,$point->getTimestamp()-$cursor->getTimestamp());if($seconds>0)$stocks=$this->simulate($planet,$levels,$stocks,$seconds,$cursor)['stocks'];$cursor=$point;}
        return $this->simulate($planet,$levels,$stocks,0,$to);
    }

    public function snapshot(Planet $planet): array
    {
        $levels=$this->repo->levelsByTier(PlanetBuilding::class,['planet'=>$planet]);$rows=[];foreach($this->repo->all(PlanetResource::class,['planet'=>$planet]) as $resource)$rows[$resource->resourceType]=$resource;
        $protectedRatio=max(0.0,min(1.0,(float)($this->catalog->rules()['combat_loot_protected_storage_ratio']??0.1)));
        $input=$this->operatingInput($planet,$levels);
        $producedResources=array_column($input['factories'],'resource');
        $result=[];
        foreach($this->catalog->rules()['resources'] as $slug=>$label){
            $capacity=$this->capacity($planet,$slug,$levels);
            $result[$slug]=['label'=>$label,'stock'=>0.0,'rate'=>0.0,'capacity'=>$capacity,'protectedCapacity'=>$capacity*$protectedRatio,'productivity'=>null];
        }
        if($rows===[])return $result;
        $anchor=$rows['electricity']->lastUpdatedAt??array_values($rows)[0]->lastUpdatedAt;$now=$this->clock->now();$stocks=[];foreach($rows as $slug=>$resource)$stocks[$slug]=$resource->currentStock;$simulation=$this->simulateRange($planet,$levels,$stocks,$anchor<$now?$anchor:$now,$now);
        foreach($this->catalog->rules()['resources'] as $slug=>$label){
            $result[$slug]['stock']=min($result[$slug]['capacity'],max(0.0,(float)($simulation['stocks'][$slug]??0)));
            $result[$slug]['rate']=(float)($simulation['rates'][$slug]??0.0);
            if($slug!=='electricity'&&in_array($slug,$producedResources,true)){
                $efficiency=(float)$simulation[$slug==='antimatter'?'antimatter_efficiency':'efficiency'];
                $result[$slug]['productivity']=max(0.0,min(100.0,$efficiency*100));
            }
        }
        return $result;
    }

    public function materialize(Planet $planet,?\DateTimeImmutable $at=null):array
    {
        $at??=$this->clock->now();$levels=$this->repo->levelsByTier(PlanetBuilding::class,['planet'=>$planet]);$rows=[];foreach($this->repo->all(PlanetResource::class,['planet'=>$planet]) as $resource)$rows[$resource->resourceType]=$resource;if($rows===[])return [];
        $anchor=$rows['electricity']->lastUpdatedAt??array_values($rows)[0]->lastUpdatedAt;$effective=$at<$anchor?$anchor:$at;$stocks=[];foreach($rows as $slug=>$resource)$stocks[$slug]=$resource->currentStock;$simulation=$this->simulateRange($planet,$levels,$stocks,$anchor,$effective);
        foreach($rows as $slug=>$resource){$resource->currentStock=min($this->capacity($planet,$slug,$levels),max(0.0,(float)($simulation['stocks'][$slug]??$resource->currentStock)));$resource->lastUpdatedAt=$effective;}return $rows;
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
