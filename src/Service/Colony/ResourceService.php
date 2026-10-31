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
        $levels=$initialLevels??$this->repo->levels(PlanetBuilding::class,['planet'=>$planet]);
        foreach ($this->catalog->rules()['resources'] as $slug=>$label) {
            if($this->repo->one(PlanetResource::class,['planet'=>$planet,'resourceType'=>$slug])) continue;
            $capacity=$this->capacity($planet,$slug,$levels);
            $stock=$this->catalog->rules()[$colony?'colony_initial_stock':'initial_stock'][$slug];
            $r=new PlanetResource();$r->planet=$planet;$r->resourceType=$slug;
            $r->currentStock=min($capacity,$stock*$stockMultiplier);
            $r->lastUpdatedAt=$this->clock->now();
            $r->productionRate=$this->rate($planet,$slug,$levels['mine_'.$slug]??0,$levels);
            $this->manager->save($r);
        }
    }
    public function capacity(Planet $planet,string $slug,array $buildings): float
    {
        return $this->catalog->rules()['base_storage_capacity']+($buildings['storage_'.$slug]??0)*$this->catalog->rules()['storage_capacity_per_level'];
    }
    public function rate(Planet $planet,string $slug,int $mineLevel,?array $levels=null): float
    {
        // Position bonus curve is an explicit provisional balancing parameter.
        $positionFactor=$slug==='hydrogen' ? .5+$planet->position/10 : ($slug==='antimatter' ? 1 : 1.5-$planet->position/10);
        $mult=$this->catalog->get('universes',$planet->solarSystem->galaxy->universe->slug)['multipliers']['resource_production'];
        $levels??=['mine_'.$slug=>$mineLevel];$bonus=0;
        foreach($this->catalog->all('buildings') as $building) {
            if(($building['production_resource']??null)===$slug) $bonus+=($levels[$building['slug']]??0)*($building['production_per_level']??$this->catalog->rules()['mine_production_per_level']);
        }
        return ($this->catalog->rules()['base_production'][$slug]+$bonus)*$positionFactor*$mult;
    }
    public function snapshot(Planet $planet): array
    {
        $levels=$this->repo->levels(PlanetBuilding::class,['planet'=>$planet]);$result=[];
        foreach ($this->repo->all(PlanetResource::class,['planet'=>$planet]) as $r) {
            $cap=$this->capacity($planet,$r->resourceType,$levels);
            $result[$r->resourceType]=['label'=>$this->catalog->rules()['resources'][$r->resourceType],'stock'=>self::stock($r->currentStock,$r->productionRate,$r->lastUpdatedAt,$this->clock->now(),$cap),'rate'=>$r->productionRate,'capacity'=>$cap];
        }
        return $result;
    }
    public function materialize(Planet $planet,?\DateTimeImmutable $at=null): array
    {
        $at??=$this->clock->now();$levels=$this->repo->levels(PlanetBuilding::class,['planet'=>$planet]);$rows=[];
        foreach ($this->repo->all(PlanetResource::class,['planet'=>$planet]) as $r) {
            // Never rewind an existing stock. Legacy pre-fix snapshots are preserved.
            $effective=$at<$r->lastUpdatedAt?$r->lastUpdatedAt:$at;
            $r->currentStock=self::stock($r->currentStock,$r->productionRate,$r->lastUpdatedAt,$effective,$this->capacity($planet,$r->resourceType,$levels));
            $r->lastUpdatedAt=$effective;$rows[$r->resourceType]=$r;
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
        $rows=$this->materialize($planet);$levels=$this->repo->levels(PlanetBuilding::class,['planet'=>$planet]);
        foreach($paid as $slug=>$amount) {
            if(!isset($rows[$slug]) || $amount<0 || !is_finite((float)$amount)) throw new \DomainException('Coût payé invalide.');
            $rows[$slug]->currentStock=min($this->capacity($planet,$slug,$levels),$rows[$slug]->currentStock+$amount*$ratio);
        }
    }
    /** Décharge le fret sans dépasser le stockage de la planète et renvoie la part perdue. */
    public function deposit(Planet $planet, array $cargo): array
    {
        $rows = $this->materialize($planet);
        $levels = $this->repo->levels(PlanetBuilding::class, ['planet' => $planet]);
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
        $levels=$this->repo->levels(PlanetBuilding::class,['planet'=>$planet]);
        foreach ($this->repo->all(PlanetResource::class,['planet'=>$planet]) as $r) $r->productionRate=$this->rate($planet,$r->resourceType,$levels['mine_'.$r->resourceType]??0,$levels);
    }
}
