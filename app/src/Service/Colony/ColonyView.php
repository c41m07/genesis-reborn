<?php
namespace App\Service\Colony;

use App\Entity\{Planet,PlayerUniverse,PlanetBuilding,PlayerTechnology,Fleet,FleetShip,PlanetDefense,CombatParticipant};
use App\Repository\GameRepository;
use App\Service\Catalog\GameCatalog;
use App\Service\Queue\{OperationCalculator, QueueService};
use App\Service\Requirement\{CatalogAccess, RequirementChecker};

final class ColonyView
{
    public function __construct(
        private GameRepository $repo,
        private GameCatalog $catalog,
        private RequirementChecker $requirements,
        private ResourceService $resources,
        private QueueService $queueService,
        private OperationCalculator $calculator,
        private CatalogueInsights $insights,
        private CatalogueDetails $details,
        private CatalogAccess $access,
        private CatalogFilters $filters,
        private EmpireDetails $empireDetails,
    ) {}

    public function data(PlayerUniverse $m,Planet $planet,bool $revealLockedItems=false,string $section='overview'): array
    {
        // La synchronisation peut terminer des commandes : lire les niveaux seulement après.
        $this->queueService->synchronize($m);
        $buildings=$this->repo->levelsByTier(PlanetBuilding::class,['planet'=>$planet]);$tech=$this->repo->levelsByTier(PlayerTechnology::class,['playerUniverse'=>$m]);
        $universeConfig=$this->catalog->forUniverse($m->universe);$definitions=$universeConfig['catalog'];$cards=[];$filterOptions=[];$queues=[];$showLockedItems=$revealLockedItems||(bool)($m->player->preferences['showLockedItems']??false);
        if($section==='tree') {
            return ['membership'=>$m,'planet'=>$planet,'technology_tree'=>$this->insights->tree($buildings,$tech,$m,$planet)];
        }
        $planetQueue=$this->repo->queue($planet);
        $idleFleets=$section==='ships' ? $this->repo->all(Fleet::class,['homePlanet'=>$planet,'playerUniverse'=>$m,'status'=>'idle']) : [];
        $shipRows=$idleFleets===[] ? [] : $this->repo->all(FleetShip::class,['fleet'=>$idleFleets]);
        $ships=[];$cardShipQuantities=[];
        foreach($shipRows as $ship) {
            $ships[$ship->slug][$ship->tier]=($ships[$ship->slug][$ship->tier]??0)+$ship->quantity;
            if($ship->fleet->id===$idleFleets[0]->id) $cardShipQuantities[$ship->slug][$ship->tier]=$ship->quantity;
        }
        $defenses=$section==='defenses' ? $this->repo->all(PlanetDefense::class,['planet'=>$planet]) : [];
        $defenseQuantities=[];
        foreach($defenses as $defense) $defenseQuantities[$defense->slug][$defense->tier]=$defense->quantity;
        foreach(['building','shipyard'] as $type) {
            $sum=0;
            foreach($planetQueue as $q) {
                if($q->queueType!==$type) continue;
                $sum+=$q->durationSeconds;$field=$type.'QueueStartedAt';$start=$planet->$field;
                $group=match($type){'building'=>'buildings','research'=>'technologies',default=>isset($definitions['defenses'][$q->itemSlug])?'defenses':'ships'};
                $base=$definitions[$group][$q->itemSlug]??$this->catalog->get($group,$q->itemSlug);
                $tierDefinition=$this->catalog->getTierForUniverse($m->universe,$group,$q->itemSlug,$q->tier);
                $queues[$type][]=['item'=>$q,'label'=>$tierDefinition['label'],'finish'=>$start?->modify('+'.$sum.' seconds')];
            }
        }
        $research=$this->repo->researchQueue($m);
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
            $definition=$this->catalog->getTierForUniverse($m->universe,'technologies',$item->itemSlug,$item->tier);
            $queues['research'][]=['item'=>$item,'label'=>$definition['label'],'finish'=>$finish];
        }
        foreach(in_array($section,['buildings','technologies','ships','defenses'],true) ? [$section] : [] as $group) {
            $groupDefinitions=$definitions[$group];$filterOptions[$group]=$this->filters->options($group,$groupDefinitions);
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
                    $d=$this->catalog->getTierForUniverse($m->universe,$group,$slug,$tier);
                    $level=match($group){'buildings'=>(int)($buildings[$slug][$tier]??0),'technologies'=>(int)($tech[$slug][$tier]??0),default=>0};$target=$level+1;
                    $queued=null;
                    foreach(($group==='technologies'?$research:$planetQueue) as $q)if($q->itemSlug===$slug&&$q->tier===$tier&&$q->itemLevel){$queued=$q;$target=max($target,$q->itemLevel+1);}
                    $failures=$this->access->failures($m,$planet,$group,$d,$tier,$buildings,$tech);
                    if($group==='defenses')$level=0;
                    $operation=$this->calculator->calculate($d,$group,$target,1,$buildings,$universeConfig,$tech);
                    $cards[$group][]=['filter_category'=>$this->filters->category($group,$base),'details'=>$this->details->describe($d,$group,$target,$planet,$buildings,$tech),'benefits'=>$this->insights->benefits($d,$group,$target,$planet,$buildings,$tech),'unlocks'=>$this->insights->unlocks($group,$slug,$target,$tier,$m->universe),'definition'=>$d,'tier'=>$tier,'level'=>$level,'target'=>$target,'quantity'=>$group==='ships'?($cardShipQuantities[$slug][$tier]??0):($group==='defenses'?($defenseQuantities[$slug][$tier]??0):0),'duration'=>$operation['duration'],'costs'=>$operation['costs'],'failures'=>array_values(array_unique($failures)),'maximum'=>isset($d['max_level'])&&$target>$d['max_level'],'queued'=>$queued!==null];
                }
            }
        }
        $activeFleets=$this->repo->all(Fleet::class,['playerUniverse'=>$m,'status'=>['traveling','returning','attacking','defending','occupying','station_waiting']],['arrivalAt'=>'ASC']);
        $combatEvents=[];
        foreach($activeFleets as $activeFleet) { $participant=$this->repo->one(CombatParticipant::class,['fleet'=>$activeFleet]); if($participant && $participant->combatEvent->status==='pending') $combatEvents[$activeFleet->id]=$participant->combatEvent; }
        return ['fleet_details'=>$this->empireDetails->fleets($m,$activeFleets),'catalog_filter_options'=>$filterOptions,'show_locked_items'=>$showLockedItems,'combat_events'=>$combatEvents,'defense_quantities'=>$defenseQuantities,'active_fleets'=>$activeFleets,'membership'=>$m,'planet'=>$planet,'planets'=>$this->repo->planets($m->universe,$m),'resources'=>$this->resources->snapshot($planet,true),'cards'=>$cards,'queues'=>$queues,'research_parallel_count'=>count($researchActive),'ships'=>$ships,'defenses'=>$defenses,'defense_catalog'=>$definitions['defenses'],'ship_catalog'=>$definitions['ships']];
    }
}
