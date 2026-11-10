<?php
namespace App\Controller;

use App\Entity\Universe;
use App\Service\Admin\AdminEventPlanner;
use App\Service\Boost\ResourceBoxPolicy;
use App\Service\Catalog\GameCatalog;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Clock\ClockInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\{Request, Response};
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_ADMIN')]
final class AdminEventWizardController extends AbstractController
{
    #[Route('/admin/events/assistant',name:'app_admin_event_wizard',methods:['GET','POST'])]
    public function index(Request $request,EntityManagerInterface $em,AdminEventPlanner $planner,ClockInterface $clock): Response
    {
        $model=(string)$request->query->get('model','');$draft=[];
        if ($model!=='' && !isset(AdminEventPlanner::MODELS[$model])) throw $this->createNotFoundException();
        if ($request->isMethod('GET') && $request->query->has('preview')) {
            $saved=$request->getSession()->get('admin_event_previews',[])[(string)$request->query->get('preview')] ?? null;
            if ($saved && $saved['model']===$model) $draft=$saved;
        }
        if ($request->isMethod('POST')) {
            if (!$this->isCsrfTokenValid('admin_event_preview',$request->request->get('_token'))) throw $this->createAccessDeniedException('Formulaire expiré.');
            $draft=$request->request->all();
            try {
                $prepared=$planner->prepare($model,$draft);$key=bin2hex(random_bytes(16));$previews=$request->getSession()->get('admin_event_previews',[]);
                $previews[$key]=$prepared;$request->getSession()->set('admin_event_previews',array_slice($previews,-10,null,true));
                return $this->redirectToRoute('app_admin_event_preview',['key'=>$key]);
            } catch (\DomainException $error) { $this->addFlash('error',$error->getMessage()); }
        }
        return $this->render('admin/event_wizard.html.twig',['models'=>AdminEventPlanner::MODELS,'model'=>$model,'draft'=>$draft,
            'universes'=>$em->getRepository(Universe::class)->findBy([],['name'=>'ASC']),'objectives'=>AdminEventPlanner::OBJECTIVES,'formats'=>ResourceBoxPolicy::FORMATS,
            'default_start'=>$clock->now()->modify('+1 hour')->setTimezone(new \DateTimeZone('UTC'))->format('Y-m-d\TH:i'),
            'default_end'=>$clock->now()->modify('+1 day')->setTimezone(new \DateTimeZone('UTC'))->format('Y-m-d\TH:i')],new Response(status:$request->isMethod('POST')?422:200));
    }

    #[Route('/admin/events/assistant/apercu/{key}',name:'app_admin_event_preview',requirements:['key'=>'[a-f0-9]{32}'],methods:['GET'])]
    public function preview(string $key,Request $request,GameCatalog $catalog): Response
    {
        $draft=$request->getSession()->get('admin_event_previews',[])[$key] ?? null;
        if (!$draft) { $this->addFlash('error','Cet aperçu a expiré. Préparez un nouvel événement.');return $this->redirectToRoute('app_admin_event_wizard'); }
        return $this->render('admin/event_preview.html.twig',['draft'=>$draft,'key'=>$key,'models'=>AdminEventPlanner::MODELS,'objectives'=>AdminEventPlanner::OBJECTIVES,'resources'=>$catalog->rules()['resources']]);
    }

    #[Route('/admin/events/assistant/planifier/{key}',name:'app_admin_event_publish',requirements:['key'=>'[a-f0-9]{32}'],methods:['POST'])]
    public function publish(string $key,Request $request,AdminEventPlanner $planner): Response
    {
        if (!$this->isCsrfTokenValid('admin_event_publish_'.$key,$request->request->get('_token'))) throw $this->createAccessDeniedException('Formulaire expiré.');
        $session=$request->getSession();$result=$session->get('admin_event_results',[])[$key] ?? null;
        if (!$result) {
            $previews=$session->get('admin_event_previews',[]);$draft=$previews[$key] ?? null;
            if (!$draft) { $this->addFlash('error','Aperçu introuvable : consultez les événements avant de recommencer.');return $this->redirectToRoute('app_admin_game_events'); }
            try { $result=$planner->publish($this->getUser(),$draft,$key); }
            catch (\DomainException $error) { $this->addFlash('error',$error->getMessage());return $this->redirectToRoute('app_admin_event_preview',['key'=>$key]); }
            unset($previews[$key]);$session->set('admin_event_previews',$previews);$results=$session->get('admin_event_results',[]);$results[$key]=$result;$session->set('admin_event_results',array_slice($results,-20,null,true));
        }
        $this->addFlash('success','Événement planifié.');
        return $result['kind']==='challenge' ? $this->redirectToRoute('app_admin_challenge',['id'=>$result['id']]) : $this->redirectToRoute('app_admin_game_events');
    }
}
