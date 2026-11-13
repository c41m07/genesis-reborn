<?php
namespace App\Service\Combat;

use App\Entity\{Planet,PlanetBuilding,PlayerTechnology,PlayerUniverse,Fleet,FleetShip};
use App\Repository\GameRepository;
use App\Service\Catalog\GameCatalog;
use App\Service\Fleet\EspionageReportReader;

final class PlayerCombatSimulation
{
    public const MAX_UNITS=500;
    public const MAX_WORK=5000000;
    public function __construct(private CombatSimulator $simulator,private CombatUnitFactory $units,private GameCatalog $catalog,private GameRepository $repo,private EspionageReportReader $reports) {}

    public function choices(PlayerUniverse $owner): array
    {
        $catalog=$this->catalog->forUniverse($owner->universe)['catalog'];
        return ['ships'=>$catalog['ships'],'defenses'=>$catalog['defenses'],'research'=>array_filter($catalog['technologies'],static fn(array $entry)=>in_array($entry['effect']['type']??'', ['stat_bonus','extra_shot_chance'],true))];
    }

    public function own(PlayerUniverse $owner,int $planetId): array
    {
        $planet=$this->repo->find(Planet::class,$planetId);
        if(!$planet || $planet->owner?->id!==$owner->id || $planet->solarSystem->galaxy->universe->id!==$owner->universe->id) throw new \DomainException('Colonie inaccessible.');
        $ships=[];
        foreach($this->repo->all(Fleet::class,['playerUniverse'=>$owner,'homePlanet'=>$planet,'status'=>'idle']) as $fleet) foreach($this->repo->all(FleetShip::class,['fleet'=>$fleet]) as $ship) if($ship->quantity>0) $ships[$ship->slug][$ship->tier]=($ships[$ship->slug][$ship->tier]??0)+$ship->quantity;
        return ['ships'=>$ships,'research'=>$this->repo->levelsByTier(PlayerTechnology::class,['playerUniverse'=>$owner])];
    }

    public function intel(PlayerUniverse $owner,int $reportId): array
    {
        $report=$this->reports->findVisible($owner,$reportId);
        if(!$report || $report->sender->universe->id!==$owner->universe->id) throw new \DomainException('Rapport inaccessible dans cet univers.');
        $forces=[];
        foreach(['fleets'=>'ships','defenses'=>'defenses','research'=>'research'] as $category=>$target) {
            if(!array_key_exists($category,$report->snapshot)) continue;
            $forces[$target]=[];
            foreach($report->snapshot[$category] as $slug=>$entry) {
                if(isset($entry['quantity']) || isset($entry['level'])) {
                    [$type,$tier]=array_pad(explode(':',$slug,2),2,'basic');
                    $forces[$target][$type][$tier]=(int)($entry[$target==='research'?'level':'quantity']??0);
                } else foreach($entry as $tier=>$values) $forces[$target][$slug][$tier]=(int)($values[$target==='research'?'level':'quantity']??0);
            }
        }
        if(array_key_exists('buildings',$report->snapshot)) $forces['fortress']=[];
        if(isset($report->snapshot['buildings']['fortress'])) {
            $fortress=$report->snapshot['buildings']['fortress'];
            if(isset($fortress['level'])) $forces['fortress']['basic']=(int)$fortress['level'];
            else foreach($fortress as $tier=>$entry) $forces['fortress'][$tier]=(int)($entry['level']??0);
        }
        return ['forces'=>$forces,'date'=>$report->createdAt,'id'=>$report->id,'known'=>array_keys($forces)];
    }

    public function provenance(PlayerUniverse $owner,array $forces,array $intel): array
    {
        $result=[];$choices=$this->choices($owner);
        foreach(['ships','research','defenses','fortress'] as $group) {
            if(!array_key_exists($group,$intel['forces'])) {$result[$group]='Inconnu dans le rapport · hypothèse de saisie';continue;}
            $before=$intel['forces'][$group];$after=$forces['defender'][$group]??[];
            if($group==='research') $before=array_intersect_key($before,$choices['research']);
            $result[$group]=$this->canonical($before)===$this->canonical(is_array($after)?$after:[])?'Rapport daté · valeurs reprises':'Hypothèse modifiée depuis le rapport';
        }
        return $result;
    }

    private function canonical(array $values): array
    {
        $result=[];
        foreach($values as $key=>$value) {
            $normalized=is_array($value)?$this->canonical($value):(is_numeric($value)?(int)$value:$value);
            if($normalized!==[] && $normalized!==0 && $normalized!=='0' && $normalized!=='') $result[$key]=$normalized;
        }
        ksort($result);return $result;
    }

    private function values(mixed $input,array $definitions,bool $levels=false): array
    {
        if(!is_array($input)) throw new \DomainException('Forces invalides.');
        $result=[];
        foreach($input as $slug=>$tiers) {
            if(!isset($definitions[$slug]) || !is_array($tiers)) throw new \DomainException('Type ou recherche inconnu.');
            foreach($tiers as $tier=>$value) {
                $n=filter_var($value,FILTER_VALIDATE_INT);
                $max=$levels?(int)($definitions[$slug]['max_level']??25):self::MAX_UNITS;
                if(!in_array($tier,GameCatalog::TIERS,true) || $n===false || $n<0 || $n>$max) throw new \DomainException('Rang, niveau ou quantité invalide. Limite de simulation : 500 unités au total.');
                if($n) $result[$slug][$tier]=$n;
            }
        }
        return $result;
    }

    public function run(PlayerUniverse $owner,array $forces,int $seed): array
    {
        if(!$owner->isPlayerControlled() || $seed<0 || $seed>0xffffffff) throw new \DomainException('Paramètres de simulation invalides.');
        $choices=$this->choices($owner);$participants=[];$defenses=[];$total=0;$weight=0;
        foreach(['attacker','defender'] as $camp) {
            $input=$forces[$camp]??[];
            if(!is_array($input)) throw new \DomainException('Forces invalides.');
            $research=$this->values($input['research']??[],$choices['research'],true);
            $composition=$this->values($input['ships']??[],$choices['ships']);
            $ships=$this->units->ships($owner->universe,$composition,$research);
            $arrival=filter_var($input['arrival']??1,FILTER_VALIDATE_INT);
            $rounds=(int)$this->catalog->rules()['max_combat_rounds'];
            if($arrival===false || $arrival<1 || $arrival>$rounds) throw new \DomainException('Round d’arrivée invalide.');
            if($ships) $participants[]=['id'=>$camp,'camp'=>$camp,'role'=>$camp,'arrival_round'=>$arrival,'units'=>$ships];
            $all=$ships;
            if($camp==='defender') {
                $fortress=$this->values(['fortress'=>$input['fortress']??[]],['fortress'=>$this->catalog->getForUniverse($owner->universe,'buildings','fortress')],true)['fortress']??[];
                foreach($this->values($input['defenses']??[],$choices['defenses']) as $slug=>$tiers) foreach($tiers as $tier=>$count) {
                    $definition=$this->units->defense($owner->universe,$slug,$tier,$research,$fortress)+['quantity'=>$count,'tier'=>$tier,'camp'=>'defender','id'=>$slug.'-'.$tier];
                    $defenses[]=$definition;$all[]=$definition;
                }
            }
            foreach($all as $unit) {$total+=$unit['quantity'];$weight+=$unit['quantity']*(($unit['shots_max']??1)+($unit['secondary_shots_max']??0))*(1+count($unit['extra_shot_chances']??[]));}
        }
        $rules=$this->catalog->rules();$rounds=(int)$rules['max_combat_rounds'];
        if($total<1 || $total>self::MAX_UNITS || $total*$weight*$rounds>self::MAX_WORK) throw new \DomainException('Scénario trop volumineux : au plus 500 unités et un budget de calcul limité. Réduisez les forces ou les tirs supplémentaires ; aucune quantité n’a été tronquée.');
        return $this->simulator->simulate($participants,$defenses,$this->catalog->all('combat_modifiers')['modifiers']??[],$rounds,$seed,null,(float)($rules['shield_recharge_percent_per_round']??5),(bool)($rules['restore_survivor_shields_after_combat']??true));
    }
}
