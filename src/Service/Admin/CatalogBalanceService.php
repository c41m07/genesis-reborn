<?php
namespace App\Service\Admin;

use App\Entity\{AdminAuditLog,Player,PlayerUniverse,PlanetBuilding,PlayerTechnology,QueueItem,Universe};
use App\Manager\GameManager;
use App\Repository\GameRepository;
use App\Service\Catalog\GameCatalog;
use App\Service\Colony\ResourceService;
use Psr\Clock\ClockInterface;

final class CatalogBalanceService
{
    private const GROUPS=['buildings','technologies','ships','defenses'];

    public function __construct(private GameCatalog $catalog,private GameRepository $repo,private GameManager $manager,private ResourceService $resources,private ClockInterface $clock){}

    public function entries(Universe $universe):array
    {
        $config=$this->catalog->forUniverse($universe);$result=[];
        foreach(self::GROUPS as $group) foreach($config['catalog'][$group] as $slug=>$definition){
            $fields=[];
            foreach($this->fieldSchema($group,$definition) as $path=>$schema){$value=$this->read($definition,$path);$fields[$path]=$schema+['path'=>$path,'value'=>$value];}
            $result[$group][]=['slug'=>$slug,'label'=>$definition['label'],'fields'=>$fields];
        }
        return $result;
    }

    public function save(Player $admin,int $universeId,string $group,string $slug,array $input):void
    {
        if(!in_array($group,self::GROUPS,true))throw new \DomainException('Groupe de catalogue invalide.');
        $this->manager->transaction(function()use($admin,$universeId,$group,$slug,$input):void{
            $universe=$this->repo->find(Universe::class,$universeId);
            if(!$universe instanceof Universe||$universe->status==='closed')throw new \DomainException('Univers introuvable ou fermé.');
            $this->manager->lock($universe);$input=$this->flatten($input);$schema=$this->fieldSchema($group,$this->catalog->get($group,$slug));
            if(array_diff(array_keys($input),array_keys($schema))||array_diff(array_keys($schema),array_keys($input)))throw new \DomainException('Paramètres catalogue incomplets ou inconnus.');
            $values=[];
            foreach($schema as $path=>$rule){$raw=$input[$path]??null;$value=filter_var($raw,FILTER_VALIDATE_FLOAT);if($value===false||!is_finite((float)$value)||$value<$rule['min']||$value>$rule['max'])throw new \DomainException('Valeur hors limites pour '.$rule['label'].'.');$values[$path]=$rule['integer']?(int)$value:(float)$value;if($rule['integer']&&(float)$values[$path]!=(float)$value)throw new \DomainException('Un entier est requis pour '.$rule['label'].'.');}
            if($group==='ships'||$group==='defenses'){if(isset($values['shots_min'],$values['shots_max'])&&$values['shots_min']>$values['shots_max'])throw new \DomainException('Le minimum de tirs ne peut pas dépasser le maximum.');}
            if(isset($values['max_level']))$this->assertMaxLevelCompatible($universe,$group,$slug,$values['max_level']);
            $planets=$this->repo->planets($universe);foreach($planets as $planet){$this->manager->lock($planet);$this->resources->materialize($planet,$this->clock->now());}
            $settings=$universe->gameSettings;$settings['catalog_overrides']??=[];$settings['catalog_overrides'][$group]??=[];$override=$settings['catalog_overrides'][$group][$slug]??[];foreach($values as $path=>$value)$this->write($override,$path,$value);$settings['catalog_overrides'][$group][$slug]=$override;$universe->gameSettings=$settings;$this->manager->flush();
            foreach($planets as $planet)$this->resources->refreshProduction($planet);
            $this->audit($admin,$universeId,$group,$slug,$values);$this->manager->flush();
        });
    }

    public function reset(Player $admin,int $universeId,string $group,string $slug):void
    {
        if(!in_array($group,self::GROUPS,true))throw new \DomainException('Groupe de catalogue invalide.');
        $this->manager->transaction(function()use($admin,$universeId,$group,$slug):void{
            $universe=$this->repo->find(Universe::class,$universeId);if(!$universe instanceof Universe||$universe->status==='closed')throw new \DomainException('Univers introuvable ou fermé.');$this->manager->lock($universe);
            $this->catalog->get($group,$slug);$planets=$this->repo->planets($universe);foreach($planets as $planet){$this->manager->lock($planet);$this->resources->materialize($planet,$this->clock->now());}
            unset($universe->gameSettings['catalog_overrides'][$group][$slug]);if(($universe->gameSettings['catalog_overrides'][$group]??[])===[])unset($universe->gameSettings['catalog_overrides'][$group]);if(($universe->gameSettings['catalog_overrides']??[])===[])unset($universe->gameSettings['catalog_overrides']);foreach($planets as $planet)$this->resources->refreshProduction($planet);
            $this->audit($admin,$universeId,$group,$slug,['reset_to_reference'=>true]);$this->manager->flush();
        });
    }

    private function fieldSchema(string $group,array $definition):array
    {
        $map=match($group){
            'buildings'=>['max_level'=>['Niveau maximum',1,100,true],'base_build_time'=>['Temps de base (s)',1,100000000,false],'build_time_scaling'=>['Croissance de durée',0.01,10,false],'costs_scaling'=>['Croissance des coûts',0.01,10,false],'production_per_level'=>['Production par niveau',0,100000000,false],'build_time_reduction_percent_per_level'=>['Réduction par niveau (%)',0,90,false]],
            'technologies'=>['max_level'=>['Niveau maximum',1,100,true],'research_time_base'=>['Temps de recherche de base (s)',1,100000000,false],'research_time_scaling'=>['Croissance de durée',0.01,10,false],'costs_scaling'=>['Croissance des coûts',0.01,10,false],'effect.value_per_level'=>['Effet par niveau',0,10000,false]],
            default=>['hp'=>['Structure (PV)',1,100000000,false],'precision'=>['Précision',0,1000,false],'tracking'=>['Poursuite',0,1000,false],'evasion'=>['Évasion',0,1000,false],'signature'=>['Signature',0,1000,false],'base_attack'=>['Dégâts de base',0,100000000,false],'shots_min'=>['Tirs minimum',1,100,true],'shots_max'=>['Tirs maximum',1,100,true],'build_time_base'=>['Temps de construction (s)',1,100000000,false],'speed_system'=>['Vitesse système',0,100000000,false],'speed_galaxy'=>['Vitesse galaxie',0,100000000,false],'fuel_travel_system'=>['Carburant système',0,100000000,false],'fuel_travel_galaxy'=>['Carburant galaxie',0,100000000,false],'fuel_orbit'=>['Carburant orbite',0,100000000,false],'cargo_capacity'=>['Capacité de fret',0,100000000,false],'fuel_capacity'=>['Capacité carburant',0,100000000,false]],
        };
        $result=[];foreach($map as $path=>[$label,$min,$max,$integer])if($this->has($definition,$path))$result[$path]=['label'=>$label,'min'=>$min,'max'=>$max,'integer'=>$integer];
        if(isset($definition['cost_profiles'])&&is_array($definition['cost_profiles']))foreach($definition['cost_profiles'] as $tier=>$costs)foreach($costs as $resource=>$amount)$result['cost_profiles.'.$tier.'.'.$resource]=['label'=>'Coût '.$tier.' · '.ucfirst((string)$resource),'min'=>0,'max'=>1000000000,'integer'=>true];
        return $result;
    }

    private function assertMaxLevelCompatible(Universe $universe,string $group,string $slug,int $max):void
    {
        if($group==='buildings'){$planets=$this->repo->planets($universe);foreach($this->repo->all(PlanetBuilding::class,['planet'=>$planets]) as $row)if($row->slug===$slug&&$row->level>$max)throw new \DomainException('Le niveau maximum ne peut pas être inférieur aux bâtiments existants.');$type='building';}
        elseif($group==='technologies'){$members=$this->repo->all(PlayerUniverse::class,['universe'=>$universe]);foreach($this->repo->all(PlayerTechnology::class,['playerUniverse'=>$members]) as $row)if($row->slug===$slug&&$row->level>$max)throw new \DomainException('Le niveau maximum ne peut pas être inférieur aux recherches existantes.');$type='research';}
        else return;
        $members=$this->repo->all(PlayerUniverse::class,['universe'=>$universe]);foreach($this->repo->all(QueueItem::class,['playerUniverse'=>$members,'queueType'=>$type,'itemSlug'=>$slug]) as $row)if(($row->itemLevel??0)>$max)throw new \DomainException('Le niveau maximum ne peut pas être inférieur aux commandes déjà en file.');
    }
    private function audit(Player $admin,int $universeId,string $group,string $slug,array $values):void{$entry=new AdminAuditLog();$entry->actor=$admin;$entry->action='universe_catalog_balance';$entry->targetType='universe';$entry->targetId=$universeId;$entry->details=['group'=>$group,'slug'=>$slug,'values'=>$values];$entry->createdAt=$this->clock->now();$this->manager->save($entry);}
    private function flatten(array $data,string $prefix=''):array{$result=[];foreach($data as $key=>$value){$path=$prefix===''?(string)$key:$prefix.'.'.$key;if(is_array($value))$result+=$this->flatten($value,$path);else$result[$path]=$value;}return $result;}
    private function has(array $data,string $path):bool{$value=$data;foreach(explode('.',$path) as $part){if(!is_array($value)||!array_key_exists($part,$value))return false;$value=$value[$part];}return true;}
    private function read(array $data,string $path):mixed{$value=$data;foreach(explode('.',$path) as $part)$value=$value[$part];return $value;}
    private function write(array &$data,string $path,mixed $value):void{$parts=explode('.',$path);$last=array_pop($parts);$cursor=&$data;foreach($parts as $part){$cursor[$part]??=[];$cursor=&$cursor[$part];}$cursor[$last]=$value;}
}
