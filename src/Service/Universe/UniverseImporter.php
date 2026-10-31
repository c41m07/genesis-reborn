<?php
namespace App\Service\Universe;
use App\Entity\{Universe,Galaxy,SolarSystem,Planet};
use App\Repository\GameRepository;
use App\Manager\GameManager;
use App\Service\Catalog\GameCatalog;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
final class UniverseImporter
{
    public function __construct(private GameRepository $repo,private GameManager $manager,private GameCatalog $catalog,#[Autowire('%kernel.project_dir%/config/universes')] private string $dir) {}
    public function import(): int
    {
        $count=0;
        foreach(glob($this->dir.'/*.json') as $file) {
            $data=json_decode(file_get_contents($file),true,512,JSON_THROW_ON_ERROR);
            $config=$this->catalog->get('universes',$data['slug']);
            $this->manager->transaction(function()use($data,$config,&$count){
                $u=$this->repo->one(Universe::class,['slug'=>$data['slug']]);
                if (!$u) { $u=new Universe();$u->slug=$data['slug'];$u->name=$config['name'];$this->manager->save($u);$this->manager->flush(); }
                $this->manager->lock($u);
                foreach($data['galaxies'] as $gd) {
                    $existingGalaxy=$this->repo->one(Galaxy::class,['universe'=>$u,'name'=>$gd['name']]);
                    if($existingGalaxy) {
                        foreach($gd['solar_systems'] as $sd) {
                            $existingSystem=$this->repo->one(SolarSystem::class,['galaxy'=>$existingGalaxy,'coordX'=>(int)$sd['coord_x'],'coordY'=>(int)$sd['coord_y']]);
                            if(!$existingSystem) continue;
                            foreach($sd['planets'] as $pd) {
                                $existingPlanet=$this->repo->one(Planet::class,['solarSystem'=>$existingSystem,'position'=>(int)$pd['position']]);
                                if($existingPlanet && !$existingPlanet->npcGarrisonInitialized && $existingPlanet->owner===null) {
                                    $existingPlanet->npcPlanet=(bool)($pd['npc_planet']??false);
                                    $existingPlanet->conquestLevel=(int)($pd['conquest_level']??1);
                                    if($existingPlanet->conquestLevel<1||$existingPlanet->conquestLevel>5) throw new \DomainException('Niveau de conquête invalide.');
                                }
                            }
                        }
                        continue;
                    }
                    $g=new Galaxy();$g->universe=$u;$g->name=$gd['name'];$this->manager->save($g);
                    $seen=[];
                    foreach($gd['solar_systems'] as $sd) {
                        $key=$sd['coord_x'].':'.$sd['coord_y'];
                        if(isset($seen[$key])) throw new \DomainException('Coordonnées de système dupliquées.');$seen[$key]=true;
                        $s=new SolarSystem();$s->galaxy=$g;$s->name=$sd['name']??('Système '.$key);$s->coordX=(int)$sd['coord_x'];$s->coordY=(int)$sd['coord_y'];$this->manager->save($s);
                        if(count($sd['planets'])>9) throw new \DomainException('Plus de neuf planètes.');
                        $positions=[];
                        foreach($sd['planets'] as $pd) {
                            $pos=filter_var($pd['position'],FILTER_VALIDATE_INT);
                            if($pos===false || $pos<1 || $pos>9 || isset($positions[$pos])) throw new \DomainException('Position de planète invalide ou dupliquée.');$positions[$pos]=true;
                            $p=new Planet();$p->solarSystem=$s;$p->position=$pos;$p->name=$s->name.' · '.$pos;$p->npcPlanet=(bool)($pd['npc_planet']??false);$p->conquestLevel=(int)($pd['conquest_level']??1);if($p->conquestLevel<1||$p->conquestLevel>5) throw new \DomainException('Niveau de conquête invalide.');$this->manager->save($p);++$count;
                        }
                    }
                }
            });
        }
        return $count;
    }
}
