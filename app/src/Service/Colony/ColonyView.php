<?php
namespace App\Service\Colony;

use App\Entity\{Planet,PlayerUniverse,PlanetBuilding,PlayerTechnology,Fleet,FleetShip,PlanetDefense};
use App\Repository\GameRepository;
use App\Service\Catalog\GameCatalog;
use App\Service\Queue\{OperationCalculator, QueueService};
use App\Service\Requirement\CatalogAccess;

final class ColonyView
{
    public function __construct(
        private GameRepository $repo,
        private GameCatalog $catalog,
        private ResourceService $resources,
        private QueueService $queueService,
        private OperationCalculator $calculator,
        private CatalogueInsights $insights,
        private CatalogueDetails $details,
        private CatalogAccess $access,
        private CatalogFilters $filters,
        private EmpireDetails $empireDetails,
    ) {}

    public function data(PlayerUniverse $membership, Planet $planet, bool $revealLockedItems = false, string $section = 'overview'): array
    {
        // La synchronisation peut terminer des commandes : lire les niveaux seulement après.
        $this->queueService->synchronize($membership);
        $buildings = $this->repo->levelsByTier(PlanetBuilding::class, ['planet'=>$planet]);
        $technologies = $this->repo->levelsByTier(PlayerTechnology::class, ['playerUniverse'=>$membership]);
        $universeConfig = $this->catalog->forUniverse($membership->universe);
        $definitions = $universeConfig['catalog'];
        $cards = [];
        $filterOptions = [];
        $queues = [];
        $showLockedItems = $revealLockedItems || (bool) ($membership->player->preferences['showLockedItems'] ?? false);
        if($section==='tree') {
            return ['membership'=>$membership,'planet'=>$planet,'technology_tree'=>$this->insights->tree($buildings,$technologies,$membership,$planet)];
        }
        $planetQueue=$this->repo->queue($planet);
        $idleFleets=$section==='ships' ? $this->repo->all(Fleet::class,['homePlanet'=>$planet,'playerUniverse'=>$membership,'status'=>'idle']) : [];
        $shipRows=$idleFleets===[] ? [] : $this->repo->all(FleetShip::class,['fleet'=>$idleFleets]);
        $ships = [];
        $cardShipQuantities = [];
        foreach($shipRows as $ship) {
            $ships[$ship->slug][$ship->tier]=($ships[$ship->slug][$ship->tier]??0)+$ship->quantity;
            if($ship->fleet->id===$idleFleets[0]->id) $cardShipQuantities[$ship->slug][$ship->tier]=$ship->quantity;
        }
        $defenses=$section==='defenses' ? $this->repo->all(PlanetDefense::class,['planet'=>$planet]) : [];
        $defenseQuantities=[];
        foreach($defenses as $defense) $defenseQuantities[$defense->slug][$defense->tier]=$defense->quantity;
        foreach(['building','shipyard'] as $type) {
            $sum=0;
            foreach($planetQueue as $queueItem) {
                if($queueItem->queueType!==$type) continue;
                $sum += $queueItem->durationSeconds;
                $field = $type.'QueueStartedAt';
                $start = $planet->$field;
                $group=match($type){'building'=>'buildings','research'=>'technologies',default=>isset($definitions['defenses'][$queueItem->itemSlug])?'defenses':'ships'};
                $base=$definitions[$group][$queueItem->itemSlug]??$this->catalog->get($group,$queueItem->itemSlug);
                $tierDefinition=$this->catalog->getTierForUniverse($membership->universe,$group,$queueItem->itemSlug,$queueItem->tier);
                $queues[$type][]=['item'=>$queueItem,'label'=>$tierDefinition['label'],'finish'=>$start?->modify('+'.$sum.' seconds')];
            }
        }
        $research=$this->repo->researchQueue($membership);
        $researchActive=array_values(array_filter($research,fn(\App\Entity\QueueItem $item)=>$item->startedAt!==null));
        $researchWaiting=array_values(array_filter($research,fn(\App\Entity\QueueItem $item)=>$item->startedAt===null));
        usort($researchActive,fn($a,$b)=>($a->finishAt()<=>$b->finishAt())?:($a->id<=>$b->id));
        $latest=$researchActive?max(array_map(fn($item)=>$item->finishAt()->getTimestamp(),$researchActive)):null;
        foreach([...$researchActive,...$researchWaiting] as $item) {
            if($item->startedAt!==null) $finish=$item->finishAt();
            else {
                $latest=max($latest??$item->queuedAt->getTimestamp(),$item->queuedAt->getTimestamp())+$item->durationSeconds;
                $finish=$item->queuedAt->setTimestamp($latest);
            }
            $definition=$this->catalog->getTierForUniverse($membership->universe,'technologies',$item->itemSlug,$item->tier);
            $queues['research'][]=['item'=>$item,'label'=>$definition['label'],'finish'=>$finish];
        }
        // Aucun état partagé : chaque préparation relit les événements et les vacances.
        $productionContext=$section==='buildings' ? $this->resources->catalogueContext($planet) : null;
        foreach(in_array($section,['buildings','technologies','ships','defenses'],true) ? [$section] : [] as $group) {
            $groupDefinitions = $definitions[$group];
            $filterOptions[$group] = $this->filters->options($group, $groupDefinitions);
            $categoryOrder=array_flip(array_keys($filterOptions[$group]));
            if(in_array($group,['buildings','technologies'],true)) {
                $order=match($group) {
                    'buildings'=>array_flip(['power_plant','mine_metal','mine_nano','mine_hydrogen','mine_antimatter','storage_metal','storage_nano','storage_hydrogen','storage_electricity','storage_antimatter','lab','shipyard','fortress']),
                    'technologies'=>array_flip(['engineering_foundations','naval_engineering','automated_defense_repair','nanomaterial_synthesis','vector_propulsion','weapon_calibration','target_acquisition','structural_armor','shield_harmonics','evasive_navigation','signature_reduction','rapid_fire_cycle','cryogenic_reserves','modular_cargo_bays','tactical_intelligence','intelligence_countermeasures','signature_masking']),
                };
                uksort($groupDefinitions,fn(string $a,string $b):int=>($categoryOrder[$this->filters->category($group,$groupDefinitions[$a])]<=>$categoryOrder[$this->filters->category($group,$groupDefinitions[$b])]) ?: (($order[$a]??PHP_INT_MAX)<=>($order[$b]??PHP_INT_MAX)));
            } elseif($group==='ships') {
                $shipCategory=fn(array $ship):string=>$this->filters->category('ships',$ship);
                $classOrder=['light'=>1,'escort'=>2,'bomber'=>3,'heavy'=>4,'capital'=>5];
                uksort($groupDefinitions,static function(string $a,string $b) use($groupDefinitions,$shipCategory,$classOrder,$categoryOrder): int {
                    $left=$groupDefinitions[$a];$right=$groupDefinitions[$b];$leftType=$shipCategory($left);$rightType=$shipCategory($right);
                    $typeOrder=$categoryOrder;$typeComparison=$typeOrder[$leftType]<=>$typeOrder[$rightType];if($typeComparison!==0)return $typeComparison;
                    if(in_array($leftType,['light','escort','bomber','heavy','capital'],true)) { $classComparison=($classOrder[$left['category']??'']??PHP_INT_MAX)<=>($classOrder[$right['category']??'']??PHP_INT_MAX);if($classComparison!==0)return $classComparison;$attackComparison=($left['base_attack']??0)<=>($right['base_attack']??0);if($attackComparison!==0)return $attackComparison; }
                    if($leftType==='transport') { $cargoComparison=($left['cargo_capacity']??0)<=>($right['cargo_capacity']??0);if($cargoComparison!==0)return $cargoComparison; }
                    $hullComparison=($left['hp']??0)<=>($right['hp']??0);return $hullComparison!==0?$hullComparison:strcmp($a,$b);
                });
            }
            foreach($groupDefinitions as $slug=>$base) {
                $tiers=GameCatalog::TIERS;
                foreach($tiers as $tier) {
                    $definition=$this->catalog->getTierForUniverse($membership->universe,$group,$slug,$tier);
                    $level = match ($group) {
                        'buildings'=>(int) ($buildings[$slug][$tier] ?? 0),
                        'technologies'=>(int) ($technologies[$slug][$tier] ?? 0),
                        default=>0
                    };
                    $target = $level + 1;
                    $queued=null;
                    foreach (($group === 'technologies' ? $research : $planetQueue) as $queueItem) {
                        if ($queueItem->itemSlug === $slug && $queueItem->tier === $tier && $queueItem->itemLevel) {
                            $queued = $queueItem;
                            $target = max($target, $queueItem->itemLevel + 1);
                        }
                    }
                    $failures=$this->access->failures($membership,$planet,$group,$definition,$tier,$buildings,$technologies);
                    if($group==='defenses')$level=0;
                    $operation=$this->calculator->calculate($definition,$group,$target,1,$buildings,$universeConfig,$technologies);
                    $cards[$group][] = [
                        'filter_category'=>$this->filters->category($group, $base),
                        'details'=>$this->details->describe($definition, $group, $target, $planet, $buildings, $technologies, $productionContext),
                        'benefits'=>$this->insights->benefits($definition, $group, $target, $planet, $buildings, $technologies, $productionContext),
                        'unlocks'=>$this->insights->unlocks($group, $slug, $target, $tier, $membership->universe),
                        'definition'=>$definition, 'tier'=>$tier, 'level'=>$level, 'target'=>$target,
                        'quantity'=>$group === 'ships' ? ($cardShipQuantities[$slug][$tier] ?? 0) : ($group === 'defenses' ? ($defenseQuantities[$slug][$tier] ?? 0) : 0),
                        'duration'=>$operation['duration'], 'costs'=>$operation['costs'],
                        'failures'=>array_values(array_unique($failures)),
                        'maximum'=>isset($definition['max_level']) && $target > $definition['max_level'],
                        'queued'=>$queued !== null
                    ];
                }
            }
        }
        $activeFleets=$this->repo->all(Fleet::class,['playerUniverse'=>$membership,'status'=>['traveling','returning','attacking','defending','occupying','station_waiting']],['arrivalAt'=>'ASC']);
        $combatEvents=$this->repo->fleetCombatEvents($membership,$activeFleets);
        return [
            'fleet_details'=>$this->empireDetails->fleets($membership, $activeFleets),
            'catalog_filter_options'=>$filterOptions, 'show_locked_items'=>$showLockedItems,
            'combat_events'=>$combatEvents, 'defense_quantities'=>$defenseQuantities,
            'active_fleets'=>$activeFleets, 'membership'=>$membership, 'planet'=>$planet,
            'planets'=>$this->repo->planets($membership->universe, $membership),
            'resources'=>$this->resources->snapshot($planet, true),
            'cards'=>$cards, 'queues'=>$queues, 'research_parallel_count'=>count($researchActive),
            'ships'=>$ships, 'defenses'=>$defenses,
            'defense_catalog'=>$definitions['defenses'], 'ship_catalog'=>$definitions['ships']
        ];
    }
}
