<?php
namespace App\Service\Requirement;

use App\Entity\{PlayerUniverse,Planet,PlanetBuilding,PlayerTechnology,QueueItem,Fleet,FleetShip,PlanetDefense};
use App\Repository\GameRepository;
use App\Service\Catalog\GameCatalog;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/** Le verrou du joueur doit être détenu avant de mémoriser des accès. */
final class CatalogAccess
{
    private array $legacy;
    public function __construct(private GameRepository $repo,private GameCatalog $catalog,private RequirementChecker $requirements,
        #[Autowire('%kernel.project_dir%/config/legacy/catalog-unlocks-2026-11-06.json')] string $legacyPath)
    {
        $this->legacy=json_decode(file_get_contents($legacyPath),true,512,JSON_THROW_ON_ERROR);
    }

    public static function key(Planet $planet,string $group,string $slug,string $tier):string
    {
        return ($group==='technologies'?'universe':'planet:'.$planet->id).':'.$group.':'.$slug.':'.$tier;
    }

    public function acquired(PlayerUniverse $membership,Planet $planet,string $group,string $slug,string $tier):bool
    {
        return isset($membership->catalogUnlocks[self::key($planet,$group,$slug,$tier)]);
    }

    public function failures(PlayerUniverse $membership,Planet $planet,string $group,array $definition,string $tier,array $buildings,array $tech):array
    {
        if($this->acquired($membership,$planet,$group,$definition['slug'],$tier))return [];
        return $this->requirements->check($this->requirements->forTier($definition,$tier),$buildings,$tech,[],
            $this->catalog->forUniverse($membership->universe)['catalog']);
    }

    public function captureLocked(PlayerUniverse $membership):void
    {
        $legacy=$membership->catalogUnlocks===null;
        $membership->catalogUnlocks??=[];
        $definitions=$this->catalog->forUniverse($membership->universe)['catalog'];
        $legacyDefinitions=$this->legacy;
        if($legacy)foreach($legacyDefinitions as $kind=>&$entries)foreach($entries as $slug=>&$entry){
            $entry=array_replace_recursive($entry,$membership->universe->gameSettings['catalog_overrides'][$kind][$slug]??[]);
            $entry+=['label'=>$definitions[$kind][$slug]['label'],'tier_labels'=>$definitions[$kind][$slug]['tier_labels']??[]];
        }
        unset($entries,$entry);
        $tech=$this->repo->levelsByTier(PlayerTechnology::class,['playerUniverse'=>$membership]);
        foreach($this->repo->planets($membership->universe,$membership) as $planet){
            $conquered=$this->repo->one(\App\Entity\PlanetOccupation::class,['planet'=>$planet,'occupant'=>$membership,'status'=>'captured'])!==null;
            $buildings=$this->repo->levelsByTier(PlanetBuilding::class,['planet'=>$planet]);
            $owned=['buildings'=>$buildings,'technologies'=>$tech];
            // Une commande déjà acceptée conserve aussi son accès, même après annulation.
            foreach($this->repo->queue($planet) as $item){
                $group=match($item->queueType){'building'=>'buildings','research'=>'technologies',default=>isset($definitions['defenses'][$item->itemSlug])?'defenses':'ships'};
                $owned[$group][$item->itemSlug][$item->tier]=1;
            }
            if($legacy){
                foreach($this->repo->all(Fleet::class,['playerUniverse'=>$membership,'homePlanet'=>$planet]) as $fleet)
                    foreach($this->repo->all(FleetShip::class,['fleet'=>$fleet]) as $ship)
                        if($ship->quantity>0)$owned['ships'][$ship->slug][$ship->tier]=1;
                foreach($this->repo->all(PlanetDefense::class,['planet'=>$planet]) as $defense)
                    if($defense->quantity>0)$owned['defenses'][$defense->slug][$defense->tier]=1;
            }
            foreach($definitions as $group=>$entries)foreach($entries as $slug=>$definition)foreach(GameCatalog::TIERS as $tier){
                $key=self::key($planet,$group,$slug,$tier);
                if(isset($membership->catalogUnlocks[$key]))continue;
                $preserved=(int)($owned[$group][$slug][$tier]??0)>0 && !($conquered && $group==='buildings');
                if($legacy&&!$preserved&&!$conquered){
                    $old=$legacyDefinitions[$group][$slug]??[];
                    $conditions=$old['unlock_requirements']??[];
                    foreach($old['levels'][1]['unlock_requirements']??[] as $kind=>$items)$conditions[$kind]=[...($conditions[$kind]??[]),...$items];
                    foreach($old['tier_unlock_requirements'][$tier]??[] as $kind=>$items)$conditions[$kind]=[...($conditions[$kind]??[]),...$items];
                    if($group==='ships'&&$slug!=='fighter')$conditions['buildings'][]=['slug'=>'shipyard','level'=>1];
                    $preserved=$this->requirements->check($conditions,$buildings,$tech,[],$legacyDefinitions)===[];
                }
                if($preserved||$this->requirements->check($this->requirements->forTier($definition,$tier),$buildings,$tech,[],$definitions)===[])
                    $membership->catalogUnlocks[$key]=true;
            }
        }
    }
}
