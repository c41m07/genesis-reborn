<?php
namespace App\Service\Player;
use App\Entity\{PlayerUniverse,Guild,GuildMember,GuildRelation,AllianceContract};
use App\Repository\GameRepository;
use App\Manager\GameManager;
use App\Service\Catalog\GameCatalog;
use Psr\Clock\ClockInterface;
final class DiplomacyService
{
    public function __construct(private GameRepository $repo,private GameManager $manager,private RelationResolver $relations,private GameCatalog $catalog,private ClockInterface $clock,private EmpireActivityService $activity,private DiplomacyAccessPolicy $access,private GuildProjectService $projects) {}
    private function lock(PlayerUniverse $m,bool $guildFeature=false): void
    {
        $this->manager->lock($m);$this->manager->lock($m->universe);
        $this->activity->assertCanControl($m);
        if(!$m->universe->isAccessible($this->clock->now())) throw new \DomainException('Univers inaccessible.');
        if($guildFeature && !$this->catalog->forUniverse($m->universe)['features']['guilds_enabled']) throw new \DomainException('Les guildes sont désactivées dans cet univers.');
    }
    public function create(PlayerUniverse $m,string $name,string $tag = ''): void
    {
        $tag=$this->normalizeTag($tag);
        $this->manager->transaction(function()use($m,$name,$tag){
            $this->lock($m,true);$name=trim($name);
            if(mb_strlen($name)<3 || mb_strlen($name)>60) throw new \DomainException('Nom de guilde : 3 à 60 caractères.');
            if($this->relations->guild($m)) throw new \DomainException('Vous êtes déjà membre d’une guilde.');
            if($this->repo->one(Guild::class,['universe'=>$m->universe,'name'=>$name])) throw new \DomainException('Ce nom de guilde existe déjà.');
            if($this->repo->one(Guild::class,['universe'=>$m->universe,'tag'=>$tag])) throw new \DomainException('Ce tag est déjà utilisé dans cet univers.');
            $g=new Guild();$g->tag=$tag;$g->name=$name;$g->universe=$m->universe;$g->leader=$m;$this->manager->save($g);
            $member=new GuildMember();$member->guild=$g;$member->playerUniverse=$m;$this->manager->save($member);
        });
    }
    public function setTag(PlayerUniverse $m,string $tag): void
    {
        $tag=$this->normalizeTag($tag);
        $this->manager->transaction(function()use($m,$tag){
            $this->lock($m,true);$guild=$this->relations->guild($m);
            if(!$guild) throw new \DomainException('Guilde inaccessible.');
            $this->manager->lock($guild);
            if(!$this->access->canCommand($m,$guild)) throw new \DomainException('Seul le commandant de la guilde peut choisir son tag.');
            $existing=$this->repo->one(Guild::class,['universe'=>$m->universe,'tag'=>$tag]);
            if($existing && $existing->id!==$guild->id) throw new \DomainException('Ce tag est déjà utilisé dans cet univers.');
            $guild->tag=$tag;
        });
    }

    private function normalizeTag(string $tag): string
    {
        $tag=strtoupper(trim($tag));
        if(!preg_match('/^[A-Z0-9]{2,5}$/D',$tag)) throw new \DomainException('Tag obligatoire : 2 à 5 lettres ou chiffres, affichés en majuscules.');
        return $tag;
    }

    public function join(PlayerUniverse $m,int $guildId): void
    {
        $this->manager->transaction(function()use($m,$guildId){
            $this->lock($m,true);$g=$this->repo->find(Guild::class,$guildId);
            if(!$g || $g->archivedAt!==null || $g->universe->id!==$m->universe->id) throw new \DomainException('Guilde inaccessible.');
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
            if(!$this->access->canCommand($m,$g) || !$target || !$target->isPlayerControlled() || $target->id===$m->id || $this->relations->guild($target)?->id!==$g->id) throw new \DomainException('Choisissez un autre membre de votre guilde.');
            $g->leader=$target;
        });
    }
    public function disband(PlayerUniverse $m): void
    {
        $this->manager->transaction(function()use($m){
            $this->lock($m,true);$g=$this->relations->guild($m);
            if(!$this->access->canCommand($m,$g)) throw new \DomainException('Seul le fondateur peut dissoudre la guilde.');
            foreach($this->repo->all(GuildRelation::class) as $r) if($r->guildA->id===$g->id || $r->guildB->id===$g->id) $this->manager->remove($r);
            foreach($this->repo->all(GuildMember::class,['guild'=>$g]) as $member) $this->manager->remove($member);
            $this->projects->abandonForGuild($g);
            $this->manager->remove($g);
        });
    }
    public function alliance(PlayerUniverse $actor, int $targetId, string $action): void
    {
        $this->manager->transaction(function () use ($actor, $targetId, $action): void {
            $this->lock($actor);
            $target = $this->repo->find(PlayerUniverse::class, $targetId);
            if (!$target || !$this->access->canRelateToPlayer($actor, $target)) throw new \DomainException('Commandant inaccessible.');
            if (!in_array($action, ['propose','accept','break','rival','peace_propose','peace_accept','peace_reject'], true)) {
                throw new \DomainException('Action personnelle inconnue.');
            }
            $contract = $this->relations->contract($actor, $target);
            if ($contract !== null) $this->manager->lock($contract);
            if ($action === 'rival') {
                if ($contract !== null && in_array($contract->status, AllianceContract::HOSTILE_STATUSES, true)) {
                    throw new \DomainException('Cette rivalité existe déjà.');
                }
                $contract ??= $this->newPersonalContract($actor, $target);
                $contract->status = 'enemies';
                $contract->proposer = $actor;
                return;
            }
            if (in_array($action, ['peace_propose','peace_accept','peace_reject'], true)) {
                if ($contract === null || !in_array($contract->status, AllianceContract::HOSTILE_STATUSES, true)) {
                    throw new \DomainException('Aucune rivalité personnelle active.');
                }
                if ($action === 'peace_propose') {
                    if ($contract->status === 'peace_pending') throw new \DomainException('Une proposition de paix est déjà en attente.');
                    $contract->status = 'peace_pending';
                    $contract->proposer = $actor;
                } else {
                    if ($contract->status !== 'peace_pending') throw new \DomainException('Aucune proposition de paix en attente.');
                    if ($action === 'peace_accept' && $contract->proposer->id === $actor->id) {
                        throw new \DomainException('L’autre joueur doit accepter votre proposition de paix.');
                    }
                    $contract->status = $action === 'peace_accept' ? 'ended' : 'enemies';
                }
                return;
            }
            if ($action === 'break') {
                if ($contract !== null && in_array($contract->status, AllianceContract::HOSTILE_STATUSES, true)) {
                    throw new \DomainException('La rivalité ne peut se terminer que par une paix acceptée par les deux joueurs.');
                }
                if ($contract !== null && in_array($contract->status, ['pending','accepted'], true)) $this->manager->remove($contract);
                return;
            }
            if ($this->relations->guildStatus($this->relations->guild($actor), $this->relations->guild($target)) === 'enemies') {
                throw new \DomainException('Les contrats sont interdits entre guildes ennemies.');
            }
            if ($action === 'accept') {
                if ($contract === null || $contract->status !== 'pending' || $contract->proposer->id === $actor->id) {
                    throw new \DomainException('Aucune proposition reçue.');
                }
                $contract->status = 'accepted';
                return;
            }
            if ($contract !== null && $contract->status !== 'ended') throw new \DomainException('Terminez la relation actuelle avant de proposer une alliance.');
            $contract ??= $this->newPersonalContract($actor, $target);
            $contract->status = 'pending';
            $contract->proposer = $actor;
        });
    }

    private function newPersonalContract(PlayerUniverse $actor, PlayerUniverse $target): AllianceContract
    {
        $contract = new AllianceContract();
        [$contract->playerA, $contract->playerB] = $actor->id < $target->id ? [$actor, $target] : [$target, $actor];
        $contract->proposer = $actor;
        $this->manager->save($contract);

        return $contract;
    }

    public function relation(PlayerUniverse $actor,int $targetId,string $status,?int $expectedId=null,?int $expectedVersion=null,string $action='legacy'): void
    {
        $this->manager->transaction(function()use($actor,$targetId,$status,$expectedId,$expectedVersion,$action): void {
            $this->lock($actor,true);
            $member=$this->repo->one(GuildMember::class,['playerUniverse'=>$actor]);
            if(!$member) throw new \DomainException('Vous devez appartenir à une guilde.');
            $this->manager->lock($member);
            $guild=$member->guild;
            $this->manager->lock($guild);
            if($member->rank) $this->manager->lock($member->rank);
            $target=$this->repo->find(Guild::class,$targetId);
            if(!$target || $target->id===$guild->id || $target->universe->id!==$actor->universe->id) throw new \DomainException('Guilde inaccessible.');
            $this->manager->lock($target);
            if($guild->archivedAt!==null || $target->archivedAt!==null) throw new \DomainException('Guilde archivée.');
            [$a,$b]=$guild->id<$target->id?[$guild,$target]:[$target,$guild];
            $relation=$this->repo->one(GuildRelation::class,['guildA'=>$a,'guildB'=>$b]);
            if($relation) $this->manager->lock($relation);
            if(($expectedId!==null && $expectedId!==($relation?->id??0)) || ($expectedVersion!==null && $expectedVersion!==($relation?->version??0))) {
                throw new \DomainException('La relation a changé. Rechargez la page avant de confirmer.');
            }
            $proposal=$relation?->proposal ?? ($relation?->status==='pending'?'allies':null);
            if($action==='legacy') {
                $action=match($status) {
                    'enemies'=>'hostile',
                    'neutral'=>$relation?->status==='enemies' ? ($proposal==='neutral' && $relation->proposer->id!==$guild->id?'accept':'propose') : ($proposal!==null?'decline':'break'),
                    'allies','non_aggression','defense'=>$proposal===$status && $relation->proposer->id!==$guild->id?'accept':'propose',
                    default=>throw new \DomainException('Relation invalide.'),
                };
            }
            if(!in_array($action,['propose','accept','decline','break','hostile'],true)) throw new \DomainException('Action de traité inconnue.');
            $permission=match($action) {
                'hostile'=>'diplomacy.hostility',
                'break'=>'diplomacy.neutral',
                default=>match(in_array($action,['accept','decline'],true)?$proposal:$status) {
                    'allies'=>'diplomacy.alliance',
                    'non_aggression'=>'diplomacy.non_aggression',
                    'defense'=>'diplomacy.defense',
                    'neutral'=>'diplomacy.neutral',
                    default=>'',
                },
            };
            if(!$this->access->allows($member,$permission)) throw new \DomainException('Permission de traité insuffisante.');
            if(!$relation) {
                if(!in_array($action,['propose','hostile'],true)) throw new \DomainException('Aucun traité à modifier.');
                $relation=new GuildRelation();$relation->guildA=$a;$relation->guildB=$b;
                $relation->status='neutral';$relation->proposer=$guild;
                $this->manager->save($relation);
            }
            $before=$relation->status;
            if($action==='propose') {
                if($proposal!==null) throw new \DomainException('Une proposition existe déjà. Acceptez-la, refusez-la ou retirez-la.');
                if($status==='neutral' && $relation->status!=='enemies') throw new \DomainException('La paix concerne une hostilité active.');
                if($relation->status==='enemies' && $status!=='neutral') throw new \DomainException('Signez la paix avant de proposer un nouveau traité.');
                if($relation->status===$status) throw new \DomainException('Ce traité est déjà actif.');
                $relation->proposal=$status;$relation->proposer=$guild;
                if($relation->status==='neutral' && $status==='allies') $relation->status='pending';
            } elseif($action==='accept') {
                if($proposal===null || $relation->proposer->id===$guild->id || $status!==$proposal) throw new \DomainException('Seule l’autre guilde peut accepter la proposition affichée.');
                $relation->status=$proposal;$relation->proposal=null;
            } elseif($action==='decline') {
                if($proposal===null) throw new \DomainException('Aucune proposition en attente.');
                $relation->proposal=null;
                if($relation->status==='pending') $relation->status='neutral';
            } elseif($action==='break') {
                if($relation->status==='enemies') throw new \DomainException('La paix exige l’accord des deux guildes.');
                if(!in_array($relation->status,['allies','non_aggression','defense'],true)) throw new \DomainException('Aucun traité actif à rompre.');
                $relation->status='neutral';$relation->proposal=null;
            } else {
                if($relation->status==='enemies' && $proposal===null) throw new \DomainException('Cette hostilité existe déjà.');
                $relation->status='enemies';$relation->proposal=null;$relation->proposer=$guild;
            }
            $event=new \App\Entity\GuildTreatyEvent();
            $event->universeId=$actor->universe->id;$event->guildAId=$a->id;$event->guildBId=$b->id;
            $event->actorId=$actor->id;$event->actorGuildId=$guild->id;$event->action=$action;
            $event->previousStatus=$before;$event->status=$relation->status;$event->proposal=$relation->proposal;
            $event->occurredAt=$this->clock->now();
            $this->manager->save($event);
            $this->manager->flush();
            if($relation->status==='enemies') $this->breakHostileContracts($actor->universe);
        });
    }
    private function breakHostileContracts(\App\Entity\Universe $u): void
    {
        $this->manager->flush();
        foreach($this->repo->all(AllianceContract::class) as $c) if(in_array($c->status,['pending','accepted'],true) && $c->playerA->universe->id===$u->id && $this->relations->guildStatus($this->relations->guild($c->playerA),$this->relations->guild($c->playerB))==='enemies') $this->manager->remove($c);
    }
}
