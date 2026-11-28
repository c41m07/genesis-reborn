<?php
namespace App\Service\Colony;
use App\Entity\{Planet,PlanetResource,PlanetBuilding};
use App\Repository\GameRepository;
use App\Manager\GameManager;
use App\Service\Catalog\GameCatalog;
use Psr\Clock\ClockInterface;
use App\Service\Player\VacationCalendar;
use App\Service\Boost\TimedBoostEffects;

final class ResourceService
{
    /** Ordre du bandeau et des champs de ressources, sans incidence sur les calculs. */
    public const DISPLAY_ORDER = ['metal', 'nano', 'hydrogen', 'antimatter', 'electricity'];


    public function __construct(private GameRepository $repo,private GameManager $manager,private GameCatalog $catalog,private ClockInterface $clock,private \App\Service\Event\GameEventService $events,private VacationCalendar $vacations,private EconomicUnitCapacity $economicUnits, private TimedBoostEffects $timedBoosts) {}
    public static function stock(float $stock,float $rate,\DateTimeImmutable $last,\DateTimeImmutable $now,float $cap): float
    {
        return min(max($cap,$stock),max(0,$stock)+max(0,$now->getTimestamp()-$last->getTimestamp())*$rate);
    }
    public function initialize(Planet $planet,bool $colony=false,?array $initialLevels=null,float $stockMultiplier=1,bool $firstAccountPlanet=false): void
    {
        $levels=$initialLevels??$this->repo->levelsByTier(PlanetBuilding::class,['planet'=>$planet]);
        $context=$this->catalogueContext($planet);
        $stockProfile = $colony ? 'colony_initial_stock' : ($firstAccountPlanet ? 'new_account_first_planet_stock' : 'initial_stock');
        foreach ($this->catalog->rules()['resources'] as $slug=>$label) {
            if($this->repo->one(PlanetResource::class,['planet'=>$planet,'resourceType'=>$slug])) continue;
            $capacity=$this->capacity($planet,$slug,$levels);
            $stock=$this->catalog->rules()[$stockProfile][$slug];
            $r=new PlanetResource();$r->planet=$planet;$r->resourceType=$slug;
            $r->currentStock=min($capacity,$stock*$stockMultiplier);
            $r->lastUpdatedAt=$this->clock->now();
            $r->productionRate=$this->rate($planet,$slug,0,$levels,$context);
            $this->manager->save($r);
        }
    }
    public function capacity(Planet $planet,string $slug,array $buildings,?\DateTimeImmutable $at=null): float
    {
        $rules = $this->catalog->rules();
        $storedLevels = $buildings['storage_'.$slug] ?? 0;
        $added = 0.0;
        foreach (GameCatalog::TIERS as $tier) {
            $level = $this->tierLevel($storedLevels, $tier);
            $added += GameCatalog::compoundedLevels($level, $tier, $rules['building_level_growth'] ?? []) * GameCatalog::tierFactor($tier) * $rules['storage_capacity_per_level'];
        }
        if (!is_finite($added)) {
            throw new \DomainException('La capacité du dépôt dépasse la précision numérique disponible.');
        }
        return $rules['base_storage_capacity'] + $added;
    }
    public function catalogueContext(Planet $planet): CatalogueProductionContext
    {
        $now = $this->clock->now();
        return new CatalogueProductionContext(
            $planet,
            $planet->owner !== null && $this->vacations->isActive($planet->owner, $now),
            $this->productionMultiplier($planet, $now),
            $this->economicUnits->inventory($planet),
        );
    }

    public function rate(Planet $planet,string $slug,int $mineLevel,?array $levels=null,?CatalogueProductionContext $context=null):float
    {
        $context?->assertPlanet($planet);
        if ($context?->onVacation ?? ($planet->owner !== null && $this->vacations->isActive($planet->owner))) return 0.0;
        $levels??=['mine_'.$slug=>['basic'=>$mineLevel]];$input=$this->operatingInput($planet,$levels,productionMultiplier:$context?->eventMultiplier,economicInventory:$context?->economicInventory);$rate=(float)($input['base'][$slug]??0.0);
        foreach($input['factories'] as $factory)if($factory['resource']===$slug)$rate+=$factory['rate'];
        return $rate;
    }

    /** Taux nominal d’une unité civile active sur cette colonie, en ressources par seconde. */
    public function economicUnitRate(Planet $planet, array $definition, ?CatalogueProductionContext $context = null): float
    {
        $context?->assertPlanet($planet);
        if ($context?->onVacation ?? ($planet->owner !== null && $this->vacations->isActive($planet->owner))) {
            return 0.0;
        }
        $economic = $definition['economic_unit'];
        $tier = $definition['_tier'];
        $universe = $this->catalog->forUniverse($planet->solarSystem->galaxy->universe);
        $eventMultiplier = $context?->eventMultiplier
            ?? $this->productionMultiplier($planet, $this->clock->now());
        // Même rendement local que la source civile d’operatingInput, sans multiplier
        // par les effectifs ni déduire les pertes de stockage d’un aperçu par unité active.
        return (float) $economic['production_per_hour'][$tier] / 3600
            * $this->positionFactor($planet, $economic['resource'])
            * (float) $universe['multipliers']['resource_production'] * $eventMultiplier;
    }

    private function tierLevel(int|array $levels,string $tier):int
    {
        if(is_int($levels))return $tier==='basic'?$levels:0;
        return (int)($levels[$tier]??0);
    }

    public function positionProductionModifiers(Planet $planet): array
    {
        $modifiers=[];
        foreach($this->catalog->rules()['resources'] as $slug=>$label) {
            $modifiers[]=['label'=>$label,'percent'=>round(($this->positionFactor($planet,$slug)-1)*100,2)];
        }
        return $modifiers;
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

    private function operatingInput(Planet $planet,array $levels,?\DateTimeImmutable $at=null,?float $productionMultiplier=null,?array $economicInventory=null):array
    {
        $rules=$this->catalog->rules();$universe=$this->catalog->forUniverse($planet->solarSystem->galaxy->universe);$mult=(float)$universe['multipliers']['resource_production'];$base=[];
        foreach($rules['resources'] as $slug=>$label)$base[$slug]=(float)($rules['base_production'][$slug]??0)*$this->positionFactor($planet,$slug)*$mult;
        // Les unités civiles n'ont aucun entretien : leur apport est une source autonome,
        // hors du rendement électrique des usines, mais dans la même simulation de stocks.
        $civilSources=[];
        foreach($this->economicUnits->state($planet,$levels,$economicInventory) as $slug=>$tiers) {
            foreach($tiers as $state) {
                $resource=$state['resource'];
                $rate=$state['production_per_hour']/3600*$this->positionFactor($planet,$resource)*$mult;
                $civilSources[$resource][$slug]=($civilSources[$resource][$slug]??0.0)+$rate;
                $base[$resource]=($base[$resource]??0.0)+$rate;
            }
        }
        $factories=[];
        $eventMultiplier=$productionMultiplier??$this->productionMultiplier($planet,$at??$this->clock->now());
        foreach($base as $slug=>$rate)$base[$slug]*=$eventMultiplier;
        foreach($civilSources as &$sources)foreach($sources as &$rate)$rate*=$eventMultiplier;
        unset($sources,$rate);
        foreach ($universe['catalog']['buildings'] as $building) {
            $resource = $building['production_resource'] ?? null;
            if (!is_string($resource)) {
                continue;
            }
            $outputLevels = 0.0;
            $powerLevels = 0.0;
            foreach (GameCatalog::TIERS as $tier) {
                $level = $this->tierLevel($levels[$building['slug']] ?? 0, $tier);
                $factor = $this->catalog->productionTierFactor($tier);
                $outputLevels += GameCatalog::compoundedLevels($level, $tier, $rules['building_level_growth'] ?? []) * $factor;
                $powerLevels += GameCatalog::compoundedLevels($level, $tier, $rules['extractor_electricity_level_growth'] ?? []) * $factor;
            }
            if ($outputLevels <= 0) {
                continue;
            }
            // L’électricité dépend des niveaux de chaque rang. Le réacteur prélève son hydrogène selon le débit produit.
            $location = $this->positionFactor($planet, $resource) * $mult;
            $rate = $outputLevels * ($building['production_per_level'] ?? $rules['mine_production_per_level']) * $location * $eventMultiplier;
            $electricity = $resource === 'electricity' ? 0.0 : $powerLevels * ($building['electricity_use_per_level'] ?? $rules['factory_electricity_use_per_level']) * $location;
            $hydrogen = $resource === 'antimatter' ? $rate * ($building['hydrogen_per_output'] ?? $rules['antimatter_hydrogen_per_unit']) : 0.0;
            $factories[] = ['slug' => $building['slug'], 'resource' => $resource, 'rate' => $rate, 'electricity' => $electricity, 'hydrogen' => $hydrogen];
        }
        return ['base'=>$base,'factories'=>$factories,'civil_sources'=>$civilSources];
    }

    public function factoryConsumption(Planet $planet,string $buildingSlug,array $levels,?CatalogueProductionContext $context=null):array
    {
        $context?->assertPlanet($planet);
        if ($context?->onVacation ?? ($planet->owner !== null && $this->vacations->isActive($planet->owner))) return ['electricity'=>0.0,'hydrogen'=>0.0];
        $input=$this->operatingInput($planet,$levels,productionMultiplier:$context?->eventMultiplier,economicInventory:$context?->economicInventory);$result=['electricity'=>0.0,'hydrogen'=>0.0];
        foreach($input['factories'] as $factory)if($factory['slug']===$buildingSlug){$result['electricity']+=(float)$factory['electricity'];$result['hydrogen']+=(float)$factory['hydrogen'];}
        return $result;
    }

    private function simulate(Planet $planet,array $levels,array $stocks,int $elapsed,?\DateTimeImmutable $at=null,?array $economicInventory=null,?float $productionMultiplier=null,bool $includeTotals=false):array
    {
        $input=$this->operatingInput($planet,$levels,$at,productionMultiplier:$productionMultiplier,economicInventory:$economicInventory);$capacities=[];foreach($this->catalog->rules()['resources'] as $slug=>$label)$capacities[$slug]=$this->capacity($planet,$slug,$levels,$at);
        $result=FactoryEconomy::simulate($input['base'],$input['factories'],$stocks,$capacities,$elapsed,(float)($this->catalog->rules()['minimum_factory_efficiency']??.1),$includeTotals);
        // Distinguer les sources civiles du socle passif sans ajouter de consommation fictive.
        foreach($input['civil_sources'] as $resource=>$sources) {
            $result['sources'][$resource]['base']-=array_sum($sources);
            foreach($sources as $slug=>$rate)$result['sources'][$resource][$slug]=$rate;
        }
        return $result;
    }

    /**
     * Projection à état fourni pour les études hors base. Le contexte complet évite toute lecture
     * d'effectifs, d'événements ou de vacances persistés ; aucune écriture ni progression de compte.
     * @param array<string,array<string,int>> $levels
     * @param array<string,float|int> $stocks
     */
    public function project(Planet $planet, array $levels, array $stocks, int $elapsed, CatalogueProductionContext $context): array
    {
        $context->assertPlanet($planet);
        if ($elapsed < 0) throw new \InvalidArgumentException('Durée de projection invalide.');
        if ($context->economicInventory === null || !is_finite($context->eventMultiplier) || $context->eventMultiplier < 0) {
            throw new \InvalidArgumentException('La projection exige un inventaire et un multiplicateur explicites.');
        }
        $result = $this->simulate($planet,$levels,$stocks,$context->onVacation?0:$elapsed,
            economicInventory:$context->economicInventory,productionMultiplier:$context->eventMultiplier,includeTotals:true);
        return $context->onVacation ? $this->pause($result) : $result;
    }

    private function pause(array $result): array
    {
        $result['rates'] = array_fill_keys(array_keys($result['rates']),0.0);
        foreach (['production','consumption','uncredited'] as $key) {
            $result[$key] = array_fill_keys(array_keys($result['rates']),0.0);
        }
        foreach (['sources','consumption_sources'] as $key) {
            foreach ($result[$key] as &$sources) {
                $sources = array_fill_keys(array_keys($sources),0.0);
            }
        }
        unset($sources);
        foreach (['efficiency','antimatter_efficiency','electricity_consumption','hydrogen_consumption'] as $key) {
            $result[$key] = 0.0;
        }
        return $result;
    }

    private function productionMultiplier(Planet $planet, \DateTimeImmutable $at): float
    {
        return $this->events->productionMultiplier($planet->solarSystem->galaxy->universe, $at)
            * $this->timedBoosts->factor($planet->owner, TimedBoostEffects::PRODUCTION, $at);
    }

    private function simulateRange(Planet $planet, array $levels, array $stocks, \DateTimeImmutable $from, \DateTimeImmutable $to, ?array $economicInventory = null, ?array $windows = null): array
    {
        $universe = $planet->solarSystem->galaxy->universe;
        $cursor = $from;
        $economicInventory ??= $this->economicUnits->inventory($planet);
        $points = $this->events->productionChangePoints($universe, $from, $to);
        $points[] = $to;
        $periods = $planet->owner !== null ? $this->vacations->periods($planet->owner) : [];
        $windows ??= $this->timedBoosts->windows($planet->owner, $from, $to, TimedBoostEffects::PRODUCTION);
        foreach ($periods as $period) {
            foreach ([$period->startedAt, $period->effectiveEndAt()] as $boundary) {
                if ($boundary > $from && $boundary < $to) {
                    $points[] = $boundary;
                }
            }
        }
        // Le stock peut ne pas avoir été lu pendant les cinq heures. Rejouer
        // les coupures dans l’ordre sans appliquer le multiplicateur à tout l’intervalle.
        foreach ($windows as $window) {
            if ($window->operationType !== TimedBoostEffects::PRODUCTION) {
                continue;
            }
            foreach ([$window->usedAt, TimedBoostEffects::endsAt($window)] as $boundary) {
                if ($boundary > $from && $boundary < $to) {
                    $points[] = $boundary;
                }
            }
        }
        usort($points, static fn (\DateTimeImmutable $a, \DateTimeImmutable $b): int => $a <=> $b);
        $multiplier = fn (\DateTimeImmutable $at): float => $this->events->productionMultiplier($universe, $at)
            * TimedBoostEffects::factorInWindows($windows, TimedBoostEffects::PRODUCTION, $at);
        foreach ($points as $point) {
            $seconds = max(0, $point->getTimestamp() - $cursor->getTimestamp());
            if ($seconds > 0 && !$this->vacations->activeAt($periods, $cursor)) {
                $stocks = $this->simulate($planet, $levels, $stocks, $seconds, $cursor, $economicInventory, $multiplier($cursor))['stocks'];
            }
            $cursor = $point;
        }
        $result = $this->simulate($planet, $levels, $stocks, 0, $to, $economicInventory, $multiplier($to));

        return $this->vacations->activeAt($periods, $to) ? $this->pause($result) : $result;
    }

    public function snapshot(Planet $planet,bool $includeEconomy=false,?\DateTimeImmutable $observedAt=null): array
    {
        $levels=$this->repo->levelsByTier(PlanetBuilding::class,['planet'=>$planet]);$rows=[];foreach($this->repo->all(PlanetResource::class,['planet'=>$planet]) as $resource)$rows[$resource->resourceType]=$resource;
        $now = $observedAt ?? $this->clock->now();
        $anchor = $rows['electricity']->lastUpdatedAt ?? (array_values($rows)[0]->lastUpdatedAt ?? $now);
        $from = min($anchor, $now);
        // Une seule lecture d’effets pour tous les taux, capacités et stocks de cet instantané.
        $windows = $this->timedBoosts->windows($planet->owner, $from, $now);
        $baseProtection = (float) ($this->catalog->rules()['combat_loot_protected_storage_ratio'] ?? 0.1);
        $protectedRatio = max(0.0, min(1.0, $baseProtection * TimedBoostEffects::factorInWindows($windows, TimedBoostEffects::LOOT_SHIELD, $now)));
        $economicInventory = $this->economicUnits->inventory($planet);
        $multiplier = $this->events->productionMultiplier($planet->solarSystem->galaxy->universe, $now)
            * TimedBoostEffects::factorInWindows($windows, TimedBoostEffects::PRODUCTION, $now);
        $input = $this->operatingInput($planet, $levels, $now, $multiplier, $economicInventory);
        $producedResources=array_column($input['factories'],'resource');
        $result=[];
        foreach($this->catalog->rules()['resources'] as $slug=>$label){
            $capacity=$this->capacity($planet,$slug,$levels);
            $result[$slug]=['label'=>$label,'stock'=>0.0,'rate'=>0.0,'capacity'=>$capacity,'protectedCapacity'=>$capacity*$protectedRatio,'productivity'=>null];
            if($includeEconomy) $result[$slug]+= ['gross'=>0.0,'consumption'=>0.0,'uncredited'=>0.0,'sources'=>[],'consumptionSources'=>[],'estimateSeconds'=>null,'potentialGross'=>0.0,'potentialSources'=>[],'productionInputs'=>[],'consumptionPotential'=>0.0,'consumerSources'=>[]];
        }
        if($rows===[])return $result;
        $stocks = [];
        foreach ($rows as $slug => $resource) {
            $stocks[$slug] = $resource->currentStock;
        }
        $simulation = $this->simulateRange($planet, $levels, $stocks, $from, $now, $economicInventory, $windows);
        foreach($this->catalog->rules()['resources'] as $slug=>$label){
            $result[$slug]['stock']=max(0.0,(float)($simulation['stocks'][$slug]??0));
            $result[$slug]['rate']=(float)($simulation['rates'][$slug]??0.0);
            if($includeEconomy) {
                foreach(['gross'=>'production','consumption'=>'consumption','uncredited'=>'uncredited'] as $field=>$key) $result[$slug][$field]=(float)($simulation[$key][$slug]??0.0);
                $result[$slug]['sources']=$simulation['sources'][$slug]??[];
                $result[$slug]['consumptionSources']=$simulation['consumption_sources'][$slug]??[];
                $civil=$input['civil_sources'][$slug]??[];
                $potentialSources=['base'=>(float)($input['base'][$slug]??0.0)-array_sum($civil)]+$civil;$productionInputs=[];
                foreach($input['factories'] as $factory) if($factory['resource']===$slug) {
                    $potentialSources[$factory['slug']]=($potentialSources[$factory['slug']]??0.0)+(float)$factory['rate'];
                    foreach(['electricity','hydrogen'] as $fuel) if($factory[$fuel]>0) {
                        $productionInputs[$fuel]??=['label'=>$this->catalog->rules()['resources'][$fuel],'gross'=>0.0,'actual'=>0.0];
                        $productionInputs[$fuel]['gross']+=(float)$factory[$fuel];
                        $productionInputs[$fuel]['actual']+=(float)($simulation['consumption_sources'][$fuel][$factory['slug']]??0.0);
                    }
                }
                $result[$slug]['potentialGross']=array_sum($potentialSources);
                $result[$slug]['potentialSources']=$potentialSources;
                $result[$slug]['productionInputs']=$productionInputs;
                $consumerSources=[];
                if(in_array($slug,['electricity','hydrogen'],true)) foreach($input['factories'] as $factory) if($factory[$slug]>0) {
                    $consumerSources[$factory['slug']]=['gross'=>(float)$factory[$slug],'actual'=>(float)($simulation['consumption_sources'][$slug][$factory['slug']]??0.0)];
                }
                $result[$slug]['consumerSources']=$consumerSources;
                $result[$slug]['consumptionPotential']=array_sum(array_column($consumerSources,'gross'));
                $rate=$result[$slug]['rate'];$stock=$result[$slug]['stock'];
                if(abs($rate)>1e-9) $result[$slug]['estimateSeconds']=(int)ceil(($rate>0?max(0.0,$result[$slug]['capacity']-$stock):$stock)/abs($rate));
            }
            if($slug!=='electricity'&&in_array($slug,$producedResources,true)){
                $efficiency=(float)$simulation[$slug==='antimatter'?'antimatter_efficiency':'efficiency'];
                $result[$slug]['productivity']=max(0.0,min(100.0,$efficiency*100));
            }
        }
        return $result;
    }

    public function materialize(Planet $planet,?\DateTimeImmutable $at=null,bool $lockStocks=false):array
    {
        $at??=$this->clock->now();$levels=$this->repo->levelsByTier(PlanetBuilding::class,['planet'=>$planet]);$rows=[];
        $resources = $lockStocks ? $this->repo->lockedPlanetResources($planet) : $this->repo->all(PlanetResource::class, ['planet' => $planet]);
        foreach($resources as $resource)$rows[$resource->resourceType]=$resource;if($rows===[])return [];
        $anchor=$rows['electricity']->lastUpdatedAt??array_values($rows)[0]->lastUpdatedAt;$effective=$at<$anchor?$anchor:$at;$stocks=[];foreach($rows as $slug=>$resource)$stocks[$slug]=$resource->currentStock;$simulation=$this->simulateRange($planet,$levels,$stocks,$anchor,$effective);
        foreach($rows as $slug=>$resource){$resource->currentStock=max(0.0,(float)($simulation['stocks'][$slug]??$resource->currentStock));$resource->lastUpdatedAt=$effective;}return $rows;
    }

    public function consume(Planet $planet,array $costs,bool $lockStocks=false): void
    {
        $rows=$this->materialize($planet,lockStocks:$lockStocks);
        foreach ($costs as $slug=>$amount) {
            if (!isset($rows[$slug]) || !is_finite((float)$amount) || $amount<0) throw new \DomainException('Coût invalide.');
            if ($rows[$slug]->currentStock+0.000001<$amount) throw new \DomainException('Ressources insuffisantes : '.$this->catalog->rules()['resources'][$slug].'.');
        }
        foreach ($costs as $slug=>$amount) $rows[$slug]->currentStock-=$amount;
    }
    /** @return array<string, float> Montants effectivement crédités, plafonnés à la capacité. */
    public function refund(Planet $planet,array $paid,float $ratio): array
    {
        $rows=$this->materialize($planet);$levels=$this->repo->levelsByTier(PlanetBuilding::class,['planet'=>$planet]);
        $credited = [];
        foreach($paid as $slug=>$amount) {
            if(!isset($rows[$slug]) || $amount<0 || !is_finite((float)$amount)) throw new \DomainException('Coût payé invalide.');
            $credited[$slug]=min(max(0.0,$this->capacity($planet,$slug,$levels)-$rows[$slug]->currentStock),$amount*$ratio);
            $rows[$slug]->currentStock+=$credited[$slug];
        }
        return $credited;
    }
    /**
     * Dépose les ressources dans la capacité existante. Pour un dépôt indivisible, overflow
     * indique la place manquante et aucune ressource du lot n’est créditée si elle est insuffisante.
     */
    public function deposit(Planet $planet, array $cargo, bool $allOrNothing = false, ?array $acceptedOverflow = null): array
    {
        // Le retrait indivisible est transactionnel et doit s’appuyer sur les stocks courants.
        $rows = $this->materialize($planet, lockStocks: $allOrNothing || $acceptedOverflow !== null);
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
        }
        if ($allOrNothing || $acceptedOverflow !== null) {
            foreach ($overflow as $slug => $amount) {
                $limit = $allOrNothing ? 0.0 : ($acceptedOverflow[$slug] ?? 0.0);
                if ($amount > $limit) {
                    return ['delivered' => array_fill_keys(array_keys($delivered), 0.0), 'overflow' => $overflow];
                }
            }
        }
        foreach ($delivered as $slug => $amount) {
            $rows[$slug]->currentStock += $amount;
        }

        return ['delivered' => $delivered, 'overflow' => $overflow];
    }

    /**
     * Le cadeau de l’Avent déroge à la capacité, pas à la production ni aux
     * dépôts habituels. Le reçu de la boîte et ce versement partagent la transaction.
     *
     * @param array<string,int> $contents
     */
    public function creditAdventBox(Planet $planet, array $contents, \DateTimeImmutable $at): void
    {
        $rows = $this->materialize($planet, $at, lockStocks: true);
        foreach ($contents as $resource => $amount) {
            if (!isset($rows[$resource]) || !is_int($amount) || $amount < 0 || !is_finite($rows[$resource]->currentStock + $amount)) {
                throw new \DomainException('Contenu de la boîte de l’Avent invalide.');
            }
            $rows[$resource]->currentStock += $amount;
        }
    }

    public function refreshProduction(Planet $planet): void
    {
        $levels=$this->repo->levelsByTier(PlanetBuilding::class,['planet'=>$planet]);
        $context=$this->catalogueContext($planet);
        foreach ($this->repo->all(PlanetResource::class,['planet'=>$planet]) as $r) $r->productionRate=$this->rate($planet,$r->resourceType,0,$levels,$context);
    }
}
