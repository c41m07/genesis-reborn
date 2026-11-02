<?php
namespace App\Service\Colony;
use App\Entity\{Planet,PlayerUniverse,PlanetBuilding,PlayerTechnology,Fleet,FleetShip,PlanetDefense,CombatParticipant};
use App\Repository\GameRepository;
use App\Service\Catalog\GameCatalog;
use App\Service\Requirement\RequirementChecker;
final class ColonyView
{
    public function __construct(private GameRepository $repo,private GameCatalog $catalog,private RequirementChecker $requirements,private ResourceService $resources,private \App\Service\Queue\QueueService $queueService,private \App\Service\Queue\OperationCalculator $calculator,private CatalogueInsights $insights) {}
    public function data(PlayerUniverse $m,Planet $planet): array
    {
        $this->queueService->synchronize($m);
        $buildings=$this->repo->levels(PlanetBuilding::class,['planet'=>$planet]);$tech=$this->repo->levels(PlayerTechnology::class,['playerUniverse'=>$m]);
        $universeConfig=$this->catalog->forUniverse($m->universe);$definitions=$universeConfig['catalog'];$cards=[];$queues=[];$showLockedItems=(bool)($m->player->preferences['showLockedItems']??false);
        foreach(['building','research','shipyard'] as $type) {
            $sum=0;
            foreach($this->repo->queue($planet,$type) as $q) {
                $sum+=$q->durationSeconds;$field=$type.'QueueStartedAt';$start=$planet->$field;
                $group=match($type){'building'=>'buildings','research'=>'technologies',default=>isset($definitions['defenses'][$q->itemSlug])?'defenses':'ships'};
                $queues[$type][]=['item'=>$q,'label'=>($definitions[$group][$q->itemSlug]['label']??$this->catalog->get($group,$q->itemSlug)['label']),'finish'=>$start?->modify('+'.$sum.' seconds')];
            }
        }
        foreach(['buildings','technologies','ships','defenses'] as $group) {
            $groupDefinitions=$definitions[$group];
            if(in_array($group,['buildings','technologies'],true)) {
                $order=match($group) {
                    'buildings'=>array_flip(['power_plant','mine_metal','mine_carbon','mine_hydrogen','mine_antimatter','storage_metal','storage_carbon','storage_hydrogen','storage_energy','storage_antimatter','lab','shipyard','fortress']),
                    'technologies'=>array_flip(['basic_engineering','advanced_engineering','robotics','nano_construction','advanced_propulsion','precision_fire','advanced_targeting','espionage_technology','counter_espionage','stealth_technology']),
                };
                uksort($groupDefinitions,static fn(string $a,string $b): int => ($order[$a]??PHP_INT_MAX)<=>($order[$b]??PHP_INT_MAX));
            } elseif($group==='ships') {
                $shipCategory=static fn(array $ship): string => (float)($ship['cargo_capacity']??0)>0?'transport':(($ship['category']??'')==='support'?'utility':'combat');
                $classOrder=['light'=>1,'escort'=>2,'bomber'=>3,'heavy'=>4,'capital'=>5];
                uksort($groupDefinitions,static function(string $a,string $b) use($groupDefinitions,$shipCategory,$classOrder): int {
                    $left=$groupDefinitions[$a];$right=$groupDefinitions[$b];$leftType=$shipCategory($left);$rightType=$shipCategory($right);
                    $typeOrder=['combat'=>0,'transport'=>1,'utility'=>2];$typeComparison=$typeOrder[$leftType]<=>$typeOrder[$rightType];if($typeComparison!==0)return $typeComparison;
                    if($leftType==='combat') { $classComparison=($classOrder[$left['category']??'']??PHP_INT_MAX)<=>($classOrder[$right['category']??'']??PHP_INT_MAX);if($classComparison!==0)return $classComparison;$attackComparison=($left['base_attack']??0)<=>($right['base_attack']??0);if($attackComparison!==0)return $attackComparison; }
                    if($leftType==='transport') { $cargoComparison=($left['cargo_capacity']??0)<=>($right['cargo_capacity']??0);if($cargoComparison!==0)return $cargoComparison; }
                    $hullComparison=($left['hp']??0)<=>($right['hp']??0);return $hullComparison!==0?$hullComparison:strcmp($a,$b);
                });
            }
            foreach($groupDefinitions as $slug=>$d) {
                $level=match($group){'buildings'=>$buildings[$slug]??0,'technologies'=>$tech[$slug]??0,default=>0};$target=$level+1;
                foreach($this->repo->queue($planet) as $q) if($q->itemSlug===$slug && $q->itemLevel) $target=max($target,$q->itemLevel+1);
                $failures=$this->requirements->check($this->requirements->forLevel($d,$target),$buildings,$tech);
                if($group==='ships' && ($buildings['shipyard']??0)<1) $failures[]='Chantier spatial niveau 1';
                if($group==='technologies' && ($buildings['lab']??0)<1) $failures[]='Laboratoire niveau 1';
                $cards[$group][]= ['benefits'=>$this->insights->benefits($d,$group,$target,$planet,$buildings,$tech),'unlocks'=>$this->insights->unlocks($group,$slug,$target),'definition'=>$d,'level'=>$level,'target'=>$target,'duration'=>$this->calculator->calculate($d,$group,$target,1,$buildings,$universeConfig)['duration'],'costs'=>$this->catalog->costs($d,$target),'failures'=>array_unique($failures),'maximum'=>isset($d['max_level']) && $target>$d['max_level']];
            }
        }
        if(!$showLockedItems) foreach($cards as $group=>$items) $cards[$group]=array_values(array_filter($items,static fn(array $card): bool => $card['failures']===[] || $card['maximum']));
        $technologyTree=$this->insights->tree($buildings,$tech);
        if(!$showLockedItems) { foreach($technologyTree as $tier=>$nodes) { $technologyTree[$tier]=array_values(array_filter($nodes,static fn(array $node): bool => $node['available'])); if($technologyTree[$tier]===[]) unset($technologyTree[$tier]); } }
        $activeFleets=$this->repo->all(Fleet::class,['playerUniverse'=>$m,'status'=>['traveling','returning','attacking','defending']],['arrivalAt'=>'ASC']);
        $combatEvents=[];
        foreach($activeFleets as $activeFleet) { $participant=$this->repo->one(CombatParticipant::class,['fleet'=>$activeFleet]); if($participant && $participant->combatEvent->status==='pending') $combatEvents[$activeFleet->id]=$participant->combatEvent; }
        $ships=[];
        foreach($this->repo->all(Fleet::class,['homePlanet'=>$planet,'playerUniverse'=>$m,'status'=>'idle']) as $f) foreach($this->repo->all(FleetShip::class,['fleet'=>$f]) as $s) $ships[$s->slug]=($ships[$s->slug]??0)+$s->quantity;
        $defenses=$this->repo->all(PlanetDefense::class,['planet'=>$planet]);$defenseQuantities=[];foreach($defenses as $defense) $defenseQuantities[$defense->slug]=$defense->quantity;
        return ['technology_tree'=>$technologyTree,'show_locked_items'=>$showLockedItems,'combat_events'=>$combatEvents,'defense_quantities'=>$defenseQuantities,'active_fleets'=>$activeFleets,'membership'=>$m,'planet'=>$planet,'planets'=>$this->repo->planets($m->universe,$m),'resources'=>$this->resources->snapshot($planet),'cards'=>$cards,'queues'=>$queues,'ships'=>$ships,'defenses'=>$defenses,'defense_catalog'=>$definitions['defenses'],'ship_catalog'=>$definitions['ships']];
    }
}
