<?php
namespace App\Service\Player;
use App\Entity\{PlayerUniverse,Guild,GuildMember,GuildRelation,AllianceContract,GuildRecruitment,GuildJumpPoint};
use App\Repository\{GameRepository,GuildHallRepository};
use App\Manager\GameManager;
use App\Service\Catalog\GameCatalog;
use App\Service\Guild\GuildCommandReturn;
use Psr\Clock\ClockInterface;
use App\Service\Report\DiplomaticReportWriter;
use App\Service\Notification\NotificationService;
final class DiplomacyService
{
    public function __construct(private GameRepository $repo,private GameManager $manager,private RelationResolver $relations,private GameCatalog $catalog,private ClockInterface $clock,private EmpireActivityService $activity,private DiplomacyAccessPolicy $access,private GuildProjectService $projects,private GuildHallRepository $guildHalls, private DiplomaticReportWriter $diplomaticReports, private NotificationService $notifications, private GuildCommandReturn $hallReturns) {}
    private function lock(PlayerUniverse $m,bool $guildFeature=false): void
    {
        // Verrouiller avec des lectures courantes : une lecture ordinaire avant l’univers
        // risquerait de figer un instantané antérieur au dernier recrutement validé.
        $this->manager->lockCurrent($m);
        $this->manager->lockCurrent($m->universe);
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
            $this->closeRecruitmentFor($m);
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

    public function applyToGuild(PlayerUniverse $candidate, int $guildId): void
    {
        $this->manager->transaction(function () use ($candidate, $guildId): void {
            $this->lock($candidate, true);
            $guild = $this->recruitingGuild($candidate, $guildId);
            $this->assertRecruitable($candidate);
            if (!$this->startRecruitment($guild, $candidate, GuildRecruitment::APPLICATION)) {
                return;
            }
            foreach ($this->repo->all(GuildMember::class, ['guild' => $guild]) as $member) {
                if ($this->access->allows($member, DiplomacyAccessPolicy::RECRUIT)) {
                    $this->notifications->enqueue($member->playerUniverse, 'Une nouvelle candidature est en attente dans votre guilde.');
                }
            }
        });
    }

    public function inviteToGuild(PlayerUniverse $actor, int $candidateId): void
    {
        $this->manager->transaction(function () use ($actor, $candidateId): void {
            $this->lock($actor, true);
            $member = $this->repo->one(GuildMember::class, ['playerUniverse' => $actor]);
            $this->assertRecruiter($member);
            $candidate = $this->repo->find(PlayerUniverse::class, $candidateId);
            if (!$candidate || !$this->access->canRelateToPlayer($actor, $candidate)) {
                throw new \DomainException('Commandant inaccessible.');
            }
            $this->assertRecruitable($candidate);
            if ($this->startRecruitment($member->guild, $candidate, GuildRecruitment::INVITATION)) {
                $this->notifications->enqueue($candidate, 'Invitation à rejoindre la guilde « '.$member->guild->name.' ».');
            }
        });
    }

    public function answerRecruitment(PlayerUniverse $actor, int $id, string $answer): void
    {
        $this->manager->transaction(function () use ($actor, $id, $answer): void {
            $this->lock($actor, true);
            $request = $this->repo->find(GuildRecruitment::class, $id);
            if (!$request || !in_array($answer, ['accept', 'decline', 'withdraw'], true)) {
                throw new \DomainException('Demande de recrutement inaccessible.');
            }
            $guild = $this->recruitingGuild($actor, $request->guild->id);
            $candidate = $request->candidate;
            if ($candidate->universe->id !== $actor->universe->id || !$candidate->isPlayerControlled()) {
                throw new \DomainException('Demande de recrutement inaccessible.');
            }

            // Le côté qui a pris l’initiative ne peut que retirer sa proposition.
            $candidateInitiated = $request->kind === GuildRecruitment::APPLICATION;
            $requiresCandidate = $answer === 'withdraw' ? $candidateInitiated : !$candidateInitiated;
            if ($requiresCandidate) {
                if ($candidate->id !== $actor->id) {
                    throw new \DomainException('Seul le joueur concerné peut effectuer cette action.');
                }
            } else {
                $member = $this->repo->one(GuildMember::class, ['playerUniverse' => $actor]);
                $this->assertRecruiter($member, $guild);
            }

            if ($answer !== 'accept') {
                $this->manager->remove($request);
                return;
            }
            $this->assertRecruitable($candidate);
            $member = new GuildMember();
            $member->guild = $guild;
            $member->playerUniverse = $candidate;
            $this->manager->save($member);
            $this->closeRecruitmentFor($candidate);
            $this->breakHostileContracts($actor->universe);
            if ($request->kind === GuildRecruitment::APPLICATION) {
                $this->notifications->enqueue($candidate, 'Votre candidature auprès de « '.$guild->name.' » a été acceptée.');
            }
        });
    }

    private function recruitingGuild(PlayerUniverse $actor, int $id): Guild
    {
        $guild = $this->repo->find(Guild::class, $id);
        if (!$guild || $guild->universe->id !== $actor->universe->id || $guild->archivedAt !== null) {
            throw new \DomainException('Guilde inaccessible.');
        }
        return $guild;
    }

    private function assertRecruiter(?GuildMember $member, ?Guild $guild = null): void
    {
        if (!$this->access->allows($member, DiplomacyAccessPolicy::RECRUIT)
            || ($guild !== null && $member->guild->id !== $guild->id)) {
            throw new \DomainException('Votre grade ne permet pas de gérer ce recrutement.');
        }
    }

    private function assertRecruitable(PlayerUniverse $candidate): void
    {
        if (!$candidate->isPlayerControlled() || $this->repo->one(GuildMember::class, ['playerUniverse' => $candidate])) {
            throw new \DomainException('Seul un commandant sans guilde peut être recruté.');
        }
    }

    // Le verrou partagé de l’univers sérialise dépôts, consentements et création de guilde.
    private function startRecruitment(Guild $guild, PlayerUniverse $candidate, string $kind): bool
    {
        $existing = $this->repo->one(GuildRecruitment::class, ['guild' => $guild, 'candidate' => $candidate]);
        if ($existing !== null) {
            if ($existing->kind !== $kind) {
                throw new \DomainException('Une demande existe déjà. Répondez à cette demande pour poursuivre.');
            }
            return false;
        }
        $request = new GuildRecruitment();
        $request->guild = $guild;
        $request->candidate = $candidate;
        $request->kind = $kind;
        $this->manager->save($request);
        return true;
    }

    private function closeRecruitmentFor(PlayerUniverse $candidate): void
    {
        foreach ($this->repo->all(GuildRecruitment::class, ['candidate' => $candidate]) as $request) {
            $this->manager->remove($request);
        }
    }

    public function leave(PlayerUniverse $m): void
    {
        $this->manager->transaction(function()use($m){
            $this->lock($m,true);$member=$this->repo->one(GuildMember::class,['playerUniverse'=>$m]);
            if(!$member) return;
            if($member->guild->leader->id===$m->id) throw new \DomainException('Transférez le commandement ou dissolvez la guilde avant de la quitter.');
            // L’effet cesse immédiatement ; garder la trace sur la colonie jusqu’à son échéance.
            $now = $this->clock->now();
            foreach ($this->repo->all(GuildJumpPoint::class, ['member' => $member]) as $point) {
                $point->destabilize($now);
            }
            $this->hallReturns->evacuateLocked($member->guild, $m, $now);
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
            $this->hallReturns->evacuateLocked($g, null, $this->clock->now());
            foreach($this->repo->all(GuildRelation::class) as $r) if($r->guildA->id===$g->id || $r->guildB->id===$g->id) $this->manager->remove($r);
            foreach($this->repo->all(GuildMember::class,['guild'=>$g]) as $member) $this->manager->remove($member);
            $this->projects->abandonForGuild($g);
            $this->guildHalls->removeWithGuild($g);
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
                    $this->diplomaticReports->record($target, $actor, 'personal_peace', 'requested');
                } else {
                    if ($contract->status !== 'peace_pending') throw new \DomainException('Aucune proposition de paix en attente.');
                    if ($action === 'peace_accept' && $contract->proposer->id === $actor->id) {
                        throw new \DomainException('L’autre joueur doit accepter votre proposition de paix.');
                    }
                    $contract->status = $action === 'peace_accept' ? 'ended' : 'enemies';
                    if ($contract->proposer->id !== $actor->id) {
                        $this->diplomaticReports->record($contract->proposer, $actor, 'personal_peace', $action === 'peace_accept' ? 'accepted' : 'refused');
                    }
                }
                return;
            }
            if ($action === 'break') {
                if ($contract !== null && in_array($contract->status, AllianceContract::HOSTILE_STATUSES, true)) {
                    throw new \DomainException('La rivalité ne peut se terminer que par une paix acceptée par les deux joueurs.');
                }
                if ($contract !== null && in_array($contract->status, ['pending','accepted'], true)) {
                    // Le proposant annule sa demande ; seul le destinataire peut la refuser.
                    if ($contract->status === 'pending' && $contract->proposer->id !== $actor->id) {
                        $this->diplomaticReports->record($contract->proposer, $actor, 'personal_alliance', 'refused');
                    }
                    $this->manager->remove($contract);
                }
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
                $this->diplomaticReports->record($contract->proposer, $actor, 'personal_alliance', 'accepted');
                return;
            }
            if ($contract !== null && $contract->status !== 'ended') throw new \DomainException('Terminez la relation actuelle avant de proposer une alliance.');
            $contract ??= $this->newPersonalContract($actor, $target);
            $contract->status = 'pending';
            $contract->proposer = $actor;
            $this->diplomaticReports->record($target, $actor, 'personal_alliance', 'requested');
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
            if ($action === 'propose') {
                $this->diplomaticReports->record($target->leader, $actor, $this->guildReportSubject($status), 'requested', guildName: $guild->name);
            } elseif ($proposal !== null && in_array($action, ['accept', 'decline'], true) && $relation->proposer->id !== $guild->id) {
                // Un traité identifie la guilde, pas le délégué qui l’a proposé. Seul le
                // dernier événement concordant atteste l’expéditeur ; aucun repli vers tous les membres.
                $opened = $this->repo->latestGuildTreatyEvent($actor->universe->id, $a->id, $b->id);
                if ($opened !== null && $opened['action'] === 'propose' && $opened['proposal'] === $proposal && $opened['actorGuildId'] === $relation->proposer->id) {
                    $proposer = $this->repo->find(PlayerUniverse::class, $opened['actorId']);
                    if ($proposer !== null && $this->repo->one(GuildMember::class, ['playerUniverse' => $proposer, 'guild' => $relation->proposer])) {
                        $this->diplomaticReports->record($proposer, $actor, $this->guildReportSubject($proposal), $action === 'accept' ? 'accepted' : 'refused', guildName: $guild->name);
                    }
                }
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
    private function guildReportSubject(string $proposal): string
    {
        return match ($proposal) {
            'allies' => 'guild_alliance',
            'non_aggression' => 'guild_non_aggression',
            'defense' => 'guild_defense',
            'neutral' => 'guild_peace',
        };
    }

    private function breakHostileContracts(\App\Entity\Universe $u): void
    {
        $this->manager->flush();
        foreach($this->repo->all(AllianceContract::class) as $c) if(in_array($c->status,['pending','accepted'],true) && $c->playerA->universe->id===$u->id && $this->relations->guildStatus($this->relations->guild($c->playerA),$this->relations->guild($c->playerB))==='enemies') $this->manager->remove($c);
    }
}
