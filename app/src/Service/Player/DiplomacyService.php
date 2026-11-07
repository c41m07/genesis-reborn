<?php
namespace App\Service\Player;
use App\Entity\{PlayerUniverse,Guild,GuildMember,GuildRelation,AllianceContract};
use App\Repository\GameRepository;
use App\Manager\GameManager;
use App\Service\Catalog\GameCatalog;
use Psr\Clock\ClockInterface;
final class DiplomacyService
{
    public function __construct(private GameRepository $repo,private GameManager $manager,private RelationResolver $relations,private GameCatalog $catalog,private ClockInterface $clock) {}
    private function lock(PlayerUniverse $m,bool $guildFeature=false): void
    {
        $this->manager->lock($m);$this->manager->lock($m->universe);
        if(!$m->universe->isAccessible($this->clock->now())) throw new \DomainException('Univers inaccessible.');
        if($guildFeature && !$this->catalog->forUniverse($m->universe)['features']['guilds_enabled']) throw new \DomainException('Les guildes sont désactivées dans cet univers.');
    }
    public function create(PlayerUniverse $m,string $name): void
    {
        $this->manager->transaction(function()use($m,$name){
            $this->lock($m,true);$name=trim($name);
            if(mb_strlen($name)<3 || mb_strlen($name)>60) throw new \DomainException('Nom de guilde : 3 à 60 caractères.');
            if($this->relations->guild($m)) throw new \DomainException('Vous êtes déjà membre d’une guilde.');
            if($this->repo->one(Guild::class,['universe'=>$m->universe,'name'=>$name])) throw new \DomainException('Ce nom de guilde existe déjà.');
            $g=new Guild();$g->name=$name;$g->universe=$m->universe;$g->leader=$m;$this->manager->save($g);
            $member=new GuildMember();$member->guild=$g;$member->playerUniverse=$m;$this->manager->save($member);
        });
    }
    public function join(PlayerUniverse $m,int $guildId): void
    {
        $this->manager->transaction(function()use($m,$guildId){
            $this->lock($m,true);$g=$this->repo->find(Guild::class,$guildId);
            if(!$g || $g->universe->id!==$m->universe->id) throw new \DomainException('Guilde inaccessible.');
            if($this->relations->guild($m)) throw new \DomainException('Quittez votre guilde avant d’en rejoindre une autre.');
            // Open recruitment is a documented provisional policy.
            $member=new GuildMember();$member->guild=$g;$member->playerUniverse=$m;$this->manager->save($member);
            $this->breakHostileContracts($m->universe);
        });
    }
    public function leave(PlayerUniverse $m): void
    {
        $this->manager->transaction(function()use($m){
            $this->lock($m,true);$member=$this->repo->one(GuildMember::class,['playerUniverse'=>$m]);
            if(!$member) return;
            if($member->guild->leader->id===$m->id) throw new \DomainException('Transférez le commandement ou dissolvez la guilde avant de la quitter.');
            $this->manager->remove($member);
        });
    }
    public function transfer(PlayerUniverse $m,int $targetId): void
    {
        $this->manager->transaction(function()use($m,$targetId){
            $this->lock($m,true);$g=$this->relations->guild($m);$target=$this->repo->find(PlayerUniverse::class,$targetId);
            if(!$g || $g->leader->id!==$m->id || !$target || $target->id===$m->id || $this->relations->guild($target)?->id!==$g->id) throw new \DomainException('Choisissez un autre membre de votre guilde.');
            $g->leader=$target;
        });
    }
    public function disband(PlayerUniverse $m): void
    {
        $this->manager->transaction(function()use($m){
            $this->lock($m,true);$g=$this->relations->guild($m);
            if(!$g || $g->leader->id!==$m->id) throw new \DomainException('Seul le fondateur peut dissoudre la guilde.');
            foreach($this->repo->all(GuildRelation::class) as $r) if($r->guildA->id===$g->id || $r->guildB->id===$g->id) $this->manager->remove($r);
            foreach($this->repo->all(GuildMember::class,['guild'=>$g]) as $member) $this->manager->remove($member);
            $this->manager->remove($g);
        });
    }
    public function alliance(PlayerUniverse $m,int $targetId,string $action): void
    {
        $this->manager->transaction(function()use($m,$targetId,$action){
            $this->lock($m);$target=$this->repo->find(PlayerUniverse::class,$targetId);
            if(!$target || $target->universe->id!==$m->universe->id || $target->id===$m->id) throw new \DomainException('Commandant inaccessible.');
            $contract=$this->relations->contract($m,$target);
            if($action==='break') { if($contract) $this->manager->remove($contract);return; }
            if($this->relations->guildStatus($this->relations->guild($m),$this->relations->guild($target))==='enemies') throw new \DomainException('Les contrats sont interdits entre guildes ennemies.');
            if($action==='accept') {
                if(!$contract || $contract->status!=='pending' || $contract->proposer->id===$m->id) throw new \DomainException('Aucune proposition reçue.');$contract->status='accepted';return;
            }
            if($action!=='propose' || $contract) throw new \DomainException('Proposition déjà existante ou action invalide.');
            $contract=new AllianceContract();[$a,$b]=$m->id<$target->id?[$m,$target]:[$target,$m];$contract->playerA=$a;$contract->playerB=$b;$contract->proposer=$m;$this->manager->save($contract);
        });
    }
    public function relation(PlayerUniverse $m,int $targetId,string $status): void
    {
        $this->manager->transaction(function()use($m,$targetId,$status){
            $this->lock($m,true);$g=$this->relations->guild($m);$target=$this->repo->find(Guild::class,$targetId);
            if(!$g || $g->leader->id!==$m->id || !$target || $target->id===$g->id || $target->universe->id!==$m->universe->id) throw new \DomainException('Relation inaccessible ou commandement requis.');
            if(!in_array($status,['allies','enemies','neutral'],true)) throw new \DomainException('Relation invalide.');
            [$a,$b]=$g->id<$target->id?[$g,$target]:[$target,$g];
            $r=$this->repo->one(GuildRelation::class,['guildA'=>$a,'guildB'=>$b]);
            if($status==='neutral') { if($r) $this->manager->remove($r);return; }
            if(!$r) { $r=new GuildRelation();$r->guildA=$a;$r->guildB=$b;$r->proposer=$g;$r->status='neutral';$this->manager->save($r); }
            if($status==='allies') {
                if($r->status==='allies') return;
                if($r->status==='pending' && $r->proposer->id!==$g->id) $r->status='allies';else { $r->status='pending';$r->proposer=$g; }
            } else { $r->status='enemies';$r->proposer=$g; }
            $this->manager->flush();$this->breakHostileContracts($m->universe);
        });
    }
    private function breakHostileContracts(\App\Entity\Universe $u): void
    {
        $this->manager->flush();
        foreach($this->repo->all(AllianceContract::class) as $c) if($c->playerA->universe->id===$u->id && $this->relations->guildStatus($this->relations->guild($c->playerA),$this->relations->guild($c->playerB))==='enemies') $this->manager->remove($c);
    }
}
