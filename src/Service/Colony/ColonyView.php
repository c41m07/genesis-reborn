<?php
namespace App\Service\Colony;
use App\Entity\{Planet,PlayerUniverse,PlanetBuilding,PlayerTechnology,Fleet,FleetShip,PlanetDefense};
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
        $cards=[];$queues=[];
        foreach(['building','research','shipyard'] as $type) {
            $sum=0;
            foreach($this->repo->queue($planet,$type) as $q) {
                $sum+=$q->durationSeconds;$field=$type.'QueueStartedAt';$start=$planet->$field;
                $group=match($type){'building'=>'buildings','research'=>'technologies',default=>isset($this->catalog->all('defenses')[$q->itemSlug])?'defenses':'ships'};
                $queues[$type][]=['item'=>$q,'label'=>$this->catalog->get($group,$q->itemSlug)['label'],'finish'=>$start?->modify('+'.$sum.' seconds')];
            }
        }
        foreach(['buildings','technologies','ships','defenses'] as $group) {
            foreach($this->catalog->all($group) as $slug=>$d) {
                $level=match($group){'buildings'=>$buildings[$slug]??0,'technologies'=>$tech[$slug]??0,default=>0};$target=$level+1;
                foreach($this->repo->queue($planet) as $q) if($q->itemSlug===$slug && $q->itemLevel) $target=max($target,$q->itemLevel+1);
                $failures=$this->requirements->check($this->requirements->forLevel($d,$target),$buildings,$tech);
                if($group==='ships' && ($buildings['shipyard']??0)<1) $failures[]='Chantier spatial niveau 1';
                if($group==='technologies' && ($buildings['lab']??0)<1) $failures[]='Laboratoire niveau 1';
                $cards[$group][]= ['benefits'=>$this->insights->benefits($d,$group,$target,$planet,$buildings,$tech),'unlocks'=>$this->insights->unlocks($group,$slug,$target),'definition'=>$d,'level'=>$level,'target'=>$target,'duration'=>$this->calculator->calculate($d,$group,$target,1,$buildings,$this->catalog->forUniverse($m->universe))['duration'],'costs'=>$this->catalog->costs($d,$target),'failures'=>array_unique($failures),'maximum'=>isset($d['max_level']) && $target>$d['max_level']];
            }
        }
        $ships=[];
        foreach($this->repo->all(Fleet::class,['homePlanet'=>$planet,'playerUniverse'=>$m,'status'=>'idle']) as $f) foreach($this->repo->all(FleetShip::class,['fleet'=>$f]) as $s) $ships[$s->slug]=($ships[$s->slug]??0)+$s->quantity;
        $defenses=$this->repo->all(PlanetDefense::class,['planet'=>$planet]);$defenseQuantities=[];foreach($defenses as $defense) $defenseQuantities[$defense->slug]=$defense->quantity;
        return ['technology_tree'=>$this->insights->tree($buildings,$tech),'defense_quantities'=>$defenseQuantities,'active_fleets'=>$this->repo->all(Fleet::class,['playerUniverse'=>$m,'status'=>['traveling','returning']],['arrivalAt'=>'ASC']),'membership'=>$m,'planet'=>$planet,'planets'=>$this->repo->planets($m->universe,$m),'resources'=>$this->resources->snapshot($planet),'cards'=>$cards,'queues'=>$queues,'ships'=>$ships,'defenses'=>$defenses,'defense_catalog'=>$this->catalog->all('defenses'),'ship_catalog'=>$this->catalog->all('ships')];
    }
}
