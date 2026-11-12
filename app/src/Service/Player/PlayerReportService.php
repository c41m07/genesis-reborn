<?php
namespace App\Service\Player;

use App\Entity\{AdminAuditLog, Player, PlayerReport, PlayerUniverse};
use App\Manager\GameManager;
use App\Repository\GameRepository;
use Psr\Clock\ClockInterface;

final class PlayerReportService
{
    public const REASONS = ['name'=>'Pseudonyme inapproprié', 'harassment'=>'Harcèlement', 'cheating'=>'Suspicion de triche', 'other'=>'Autre'];
    public const CATEGORIES = ['points'=>'Points totaux', 'buildings'=>'Points de bâtiments', 'research'=>'Points de recherche', 'fleet'=>'Points de flotte', 'planets'=>'Nombre de planètes'];

    public function __construct(private GameRepository $repo, private GameManager $manager, private ClockInterface $clock) {}

    public function submit(PlayerUniverse $reporter, PlayerUniverse $target, string $reason, string $comment, array $context, string $requestKey): bool
    {
        $comment=trim($comment);
        if(!isset(self::REASONS[$reason])) throw new \DomainException('Choisissez un motif de signalement.');
        if(mb_strlen($comment)>1000) throw new \DomainException('Le commentaire est limité à 1000 caractères.');
        if(!preg_match('/^[a-f0-9]{32}$/D',$requestKey)) throw new \DomainException('Formulaire expiré. Rouvrez le signalement depuis le classement.');
        if(($context['reporter_id']??null)!==$reporter->id || ($context['target_id']??null)!==$target->id
            || ($context['universe_id']??null)!==$reporter->universe->id
            || !isset(self::CATEGORIES[$context['category']??'']) || !is_int($context['rank']??null) || $context['rank']<1
            || !is_string($context['leaderboard_at']??null)) throw new \DomainException('Contexte du classement invalide. Rouvrez le formulaire.');
        return $this->manager->transaction(function()use($reporter,$target,$reason,$comment,$context,$requestKey): bool {
            foreach([$reporter,$target] as $member) if($member->id===null) throw new \DomainException('Commandant inaccessible.');
            $members=[$reporter,$target];usort($members,static fn($a,$b)=>$a->id<=>$b->id);
            foreach($members as $member) $this->manager->lock($member);
            if(!$reporter->isPlayerControlled() || $reporter->player->lockedAt!==null || !$target->isPlayerControlled() || $reporter->id===$target->id
                || $target->universe->id!==$reporter->universe->id) throw new \DomainException('Commandant inaccessible dans cet univers.');
            if($this->repo->one(PlayerReport::class,['reporter'=>$reporter,'requestKey'=>$requestKey])) return false;
            $report=new PlayerReport();$report->universe=$reporter->universe;$report->reporter=$reporter;$report->target=$target;
            $report->requestKey=$requestKey;$report->reason=$reason;$report->comment=$comment;$report->createdAt=$this->clock->now();
            $report->context=['universe_id'=>$reporter->universe->id,'universe_name'=>$reporter->universe->name,
                'target_id'=>$target->id,'target_username'=>$target->player->username,'reporter_id'=>$reporter->id,
                'reporter_username'=>$reporter->player->username,'category'=>$context['category'],'rank'=>$context['rank'],
                'leaderboard_at'=>$context['leaderboard_at'],'target_username_seen'=>$context['target_username_seen']??$target->player->username];
            $this->manager->save($report);return true;
        });
    }

    public function recordViews(Player $admin,array $reports): void
    {
        if(!$reports) return;
        $this->manager->transaction(function()use($admin,$reports): void {
            $this->authorize($admin);
            foreach($reports as $report) $this->audit($admin,'player_report_view',$report->id,[]);
        });
    }

    public function review(Player $admin,int $id,string $decision): bool
    {
        if(!in_array($decision,['dismiss','actioned'],true)) throw new \DomainException('Décision de modération inconnue.');
        return $this->manager->transaction(function()use($admin,$id,$decision): bool {
            $this->authorize($admin);
            $report=$this->repo->find(PlayerReport::class,$id);
            if(!$report) throw new \DomainException('Signalement introuvable.');
            $this->manager->lock($report);
            if($report->status!=='open') return false;
            $report->status=$decision==='dismiss'?'dismissed':'actioned';$report->reviewedBy=$admin;$report->reviewedAt=$this->clock->now();
            $this->audit($admin,'player_report_'.$decision,$id,['reason'=>$report->reason,'universe_id'=>$report->universe->id]);
            return true;
        });
    }

    private function authorize(Player $admin): void
    {
        $this->manager->lock($admin);
        if(!in_array('ROLE_ADMIN',$admin->getRoles(),true) || $admin->lockedAt!==null) throw new \DomainException('Droits administrateur actifs requis.');
    }

    private function audit(Player $admin,string $action,int $id,array $details): void
    {
        $entry=new AdminAuditLog();$entry->actor=$admin;$entry->action=$action;$entry->targetType='player_report';$entry->targetId=$id;
        $entry->details=$details;$entry->createdAt=$this->clock->now();$this->manager->save($entry);
    }
}
