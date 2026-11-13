<?php
namespace App\Service\Fleet;

use App\Entity\{FleetComposition,Fleet,FleetShip,Planet,PlayerUniverse};
use App\Manager\GameManager;
use App\Repository\GameRepository;
use App\Service\Catalog\GameCatalog;

final class FleetCompositionService
{
    public function __construct(private GameRepository $repo,private GameManager $manager,private GameCatalog $catalog) {}

    public function all(PlayerUniverse $owner): array
    {
        return $this->repo->all(FleetComposition::class,['owner'=>$owner],['id'=>'ASC']);
    }

    public function owned(PlayerUniverse $owner,int $id): FleetComposition
    {
        $composition=$this->repo->find(FleetComposition::class,$id);
        if(!$owner->isPlayerControlled() || !$composition || $composition->owner->id!==$owner->id || $composition->owner->universe->id!==$owner->universe->id) throw new \DomainException('Composition inaccessible dans cet univers.');
        return $composition;
    }

    public function normalize(PlayerUniverse $owner,array $ships): array
    {
        $config=$this->catalog->forUniverse($owner->universe);$result=[];$total=0;
        foreach($ships as $slug=>$tiers) {
            if(!isset($config['catalog']['ships'][$slug]) || !is_array($tiers)) throw new \DomainException('Type de vaisseau invalide.');
            foreach($tiers as $tier=>$value) {
                $quantity=filter_var($value,FILTER_VALIDATE_INT);
                if(!in_array($tier,GameCatalog::TIERS,true) || $quantity===false || $quantity<0 || $quantity>(int)$config['limits']['max_fleet_size']) throw new \DomainException('Rang ou quantité de vaisseaux invalide.');
                if($quantity) {$result[$slug][$tier]=$quantity;$total+=$quantity;}
            }
        }
        if($total<1 || $total>(int)$config['limits']['max_fleet_size']) throw new \DomainException('La composition doit contenir des vaisseaux et respecter la limite de flotte de cet univers.');
        ksort($result);foreach($result as &$tiers) ksort($tiers);unset($tiers);
        return $result;
    }

    public function save(PlayerUniverse $owner,int $id,string $name,array $ships,int $version,string $requestKey): FleetComposition
    {
        return $this->manager->transaction(function() use($owner,$id,$name,$ships,$version,$requestKey) {
            $this->manager->lock($owner);
            if(!$owner->isPlayerControlled()) throw new \DomainException('Empire inaccessible.');
            if(!preg_match('/^[a-f0-9]{32}$/D',$requestKey)) throw new \DomainException('Formulaire expiré.');
            if(!$id && ($existing=$this->repo->one(FleetComposition::class,['owner'=>$owner,'requestKey'=>$requestKey]))) return $existing;
            $name=trim($name);
            if(mb_strlen($name)<1 || mb_strlen($name)>80) throw new \DomainException('Le nom doit contenir entre 1 et 80 caractères.');
            $ships=$this->normalize($owner,$ships);
            if($id) {
                $composition=$this->owned($owner,$id);$this->manager->lock($composition);
                if($composition->version!==$version) throw new \DomainException('Cette composition a changé. Rechargez-la avant de modifier.');
            } else {
                if(count($this->all($owner))>=20) throw new \DomainException('Limite de 20 compositions atteinte dans cet univers.');
                $composition=new FleetComposition();$composition->owner=$owner;$composition->requestKey=$requestKey;$this->manager->save($composition);
            }
            $composition->name=$name;$composition->ships=$ships;
            return $composition;
        });
    }

    public function delete(PlayerUniverse $owner,int $id,int $version): void
    {
        $this->manager->transaction(function() use($owner,$id,$version) {
            $this->manager->lock($owner);$composition=$this->owned($owner,$id);$this->manager->lock($composition);
            if($composition->version!==$version) throw new \DomainException('Cette composition a changé. Rechargez-la avant de supprimer.');
            $this->manager->remove($composition);
        });
    }

    public function plan(PlayerUniverse $owner,FleetComposition $composition,Planet $planet): array
    {
        $this->owned($owner,$composition->id);
        if($planet->owner?->id!==$owner->id || $planet->solarSystem->galaxy->universe->id!==$owner->universe->id) throw new \DomainException('Colonie inaccessible dans cet univers.');
        $stock=[];$available=[];$rows=[];$missing=false;
        $fleet=$this->repo->one(Fleet::class,['playerUniverse'=>$owner,'homePlanet'=>$planet,'status'=>'idle']);
        foreach($fleet?$this->repo->all(FleetShip::class,['fleet'=>$fleet]):[] as $ship) $stock[$ship->slug][$ship->tier]=($stock[$ship->slug][$ship->tier]??0)+$ship->quantity;
        foreach($composition->ships as $slug=>$tiers) foreach($tiers as $tier=>$requested) {
            $count=min($requested,max(0,$stock[$slug][$tier]??0));
            if($count) $available[$slug][$tier]=$count;
            $rows[]=['slug'=>$slug,'tier'=>$tier,'requested'=>$requested,'available'=>$count,'missing'=>$requested-$count];
            $missing=$missing || $count!==$requested;
        }
        $fingerprint=hash('sha256',json_encode([$owner->id,$planet->id,$composition->id,$composition->version,$composition->ships,$available],JSON_THROW_ON_ERROR));
        return ['rows'=>$rows,'available'=>$available,'missing'=>$missing,'fingerprint'=>$fingerprint];
    }
}
