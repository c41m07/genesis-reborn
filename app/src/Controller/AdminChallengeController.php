<?php
namespace App\Controller;

use App\Entity\{AdminAuditLog, BoostEvent, Universe};
use App\Service\Admin\AdminEventPlanner;
use App\Service\Event\ChallengeQueryService;
use App\Service\Catalog\GameCatalog;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Clock\ClockInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\{Request, Response};
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_ADMIN')]
final class AdminChallengeController extends AbstractController
{
    #[Route('/admin/defis',name:'app_admin_challenges',methods:['GET'])]
    public function index(Request $request,EntityManagerInterface $em,ChallengeQueryService $query): Response
    {
        $universe=$request->query->get('universe','')==='' ? 0 : $request->query->getInt('universe');
        return $this->render('admin/challenges.html.twig',$query->search($universe,(string)$request->query->get('status',''),$request->query->getInt('page',1))+['universes'=>$em->getRepository(Universe::class)->findBy([],['name'=>'ASC'])]);
    }

    #[Route('/admin/defis/{id}',name:'app_admin_challenge',requirements:['id'=>'\d+'],methods:['GET'])]
    public function show(int $id,Request $request,EntityManagerInterface $em,ChallengeQueryService $query,GameCatalog $catalog,ClockInterface $clock): Response
    {
        $event=$em->find(BoostEvent::class,$id);if (!$event) throw $this->createNotFoundException();
        $entry=new AdminAuditLog();$entry->actor=$this->getUser();$entry->action='admin_challenge_view';$entry->targetType='boost_event';$entry->targetId=$id;$entry->createdAt=$clock->now();$em->persist($entry);$em->flush();
        return $this->render('admin/challenge.html.twig',['challenge'=>$query->view($event),'contributors'=>$query->contributors($event,$request->query->getInt('page',1)),'resources'=>$catalog->rules()['resources']]);
    }

    #[Route('/admin/defis/{id}/annuler',name:'app_admin_challenge_cancel',requirements:['id'=>'\d+'],methods:['POST'])]
    public function cancel(int $id,Request $request,EntityManagerInterface $em,AdminEventPlanner $planner): Response
    {
        if (!$this->isCsrfTokenValid('admin_challenge_cancel_'.$id,$request->request->get('_token'))) throw $this->createAccessDeniedException('Formulaire expiré.');
        $event=$em->find(BoostEvent::class,$id);if (!$event) throw $this->createNotFoundException();
        try {
            if (!$request->request->getBoolean('confirmed')) throw new \DomainException('Confirmez explicitement l’annulation du défi.');
            $planner->cancelChallenge($this->getUser(),$event,(string)$request->request->get('reason'));$this->addFlash('success','Défi et annonce annulés. Les objets déjà gagnés sont conservés.');
        } catch (\DomainException $error) { $this->addFlash('error',$error->getMessage()); }
        return $this->redirectToRoute('app_admin_challenge',['id'=>$id]);
    }
}
