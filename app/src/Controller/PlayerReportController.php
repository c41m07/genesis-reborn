<?php
namespace App\Controller;

use App\Entity\PlayerUniverse;
use App\Repository\GameRepository;
use App\Service\Player\{DailyLeaderboard, PlayerReportService};
use App\Service\Universe\UniverseContext;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\{Request,Response};
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

final class PlayerReportController extends AbstractController
{
    #[Route('/classement/signaler/{id}',name:'app_player_report',requirements:['id'=>'\d+'],methods:['GET','POST'])]
    public function report(int $id,Request $request,UniverseContext $context,GameRepository $repo,DailyLeaderboard $daily,PlayerReportService $reports): Response
    {
        $member=$context->membership();$target=$repo->find(PlayerUniverse::class,$id);
        if(!$target || !$target->isPlayerControlled() || !$member->isPlayerControlled() || $target->id===$member->id
            || $target->universe->id!==$member->universe->id) throw $this->createNotFoundException('Commandant inaccessible.');
        $session=$request->getSession();$contexts=$session->get('player_report_contexts',[]);
        $category=$request->query->getString('category','points');
        if(!isset(PlayerReportService::CATEGORIES[$category])) $category='points';
        $page=max(1,$request->query->getInt('page',1));$error=null;$draft=['reason'=>'','comment'=>''];$saved=null;$key='';
        if($request->isMethod('POST')) {
            if(!$this->isCsrfTokenValid('player_report_'.$id,$request->request->get('_token'))) throw $this->createAccessDeniedException('Formulaire expiré.');
            $key=$request->request->getString('request_key');$saved=$contexts[$key]??null;
            $draft=['reason'=>$request->request->getString('reason'),'comment'=>$request->request->getString('comment')];
            try {
                if(!$saved || $saved['reporter_id']!==$member->id || $saved['target_id']!==$id || $saved['universe_id']!==$member->universe->id) throw new \DomainException('Formulaire expiré. Rouvrez le signalement depuis le classement.');
                $changed=$reports->submit($member,$target,$draft['reason'],$draft['comment'],$saved,$key);
                $this->addFlash('success',$changed?'Signalement transmis à la modération.':'Ce signalement a déjà été transmis.');
                return $this->redirectToRoute('app_leaderboard',['category'=>$saved['category'],'page'=>$saved['page']]);
            } catch(\DomainException $exception) { $error=$exception->getMessage(); }
        }
        if(!$saved || $saved['reporter_id']!==$member->id || $saved['target_id']!==$id || $saved['universe_id']!==$member->universe->id) {
            $snapshot=$daily->forUniverse($member);$row=null;
            foreach($snapshot['players'] as $candidate) if($candidate['membership']->id===$id) { $row=$candidate;break; }
            if(!$row) throw $this->createNotFoundException('Ce commandant ne figure pas dans ce classement.');
            $saved=['reporter_id'=>$member->id,'target_id'=>$id,'universe_id'=>$member->universe->id,'category'=>$category,
                'rank'=>(int)$row['ranks'][$category],'leaderboard_at'=>$snapshot['updated_at']->format(DATE_ATOM),
                'target_username_seen'=>$target->player->username,'page'=>$page];
            $key=bin2hex(random_bytes(16));$contexts[$key]=$saved;
            $session->set('player_report_contexts',array_slice($contexts,-20,null,true));
        }
        return $this->render('player/report.html.twig',['membership'=>$member,'target'=>$target,'context'=>$saved,
            'request_key'=>$key,'draft'=>$draft,'error'=>$error,'reasons'=>PlayerReportService::REASONS,'categories'=>PlayerReportService::CATEGORIES],
            new Response(status:$error?422:200));
    }

    #[IsGranted('ROLE_ADMIN')]
    #[Route('/admin/signalements-joueurs/{id}',name:'app_admin_player_report_review',requirements:['id'=>'\d+'],methods:['POST'])]
    public function review(int $id,Request $request,PlayerReportService $reports): Response
    {
        if(!$this->isCsrfTokenValid('admin_player_report_'.$id,$request->request->get('_token'))) throw $this->createAccessDeniedException('Formulaire expiré.');
        try {
            $changed=$reports->review($this->getUser(),$id,$request->request->getString('decision'));
            $this->addFlash('success',$changed?'Signalement joueur traité. Aucune sanction automatique appliquée.':'Ce signalement a déjà été traité.');
        } catch(\DomainException $error) { $this->addFlash('error',$error->getMessage()); }
        return $this->redirectToRoute('app_admin_reports',['_fragment'=>'admin-player-reports']);
    }
}
