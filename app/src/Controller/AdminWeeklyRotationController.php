<?php
namespace App\Controller;

use App\Entity\{BoostEvent, Universe};
use App\Service\Admin\AdminEventPlanner;
use App\Service\Boost\WeeklyRotationService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\{Request,Response};
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_ADMIN')]
final class AdminWeeklyRotationController extends AbstractController
{
    #[Route('/admin/rotations',name:'app_admin_weekly_rotations',methods:['GET','POST'])]
    public function index(Request $request,EntityManagerInterface $em,WeeklyRotationService $rotations): Response
    {
        $universes=$em->getRepository(Universe::class)->findBy([],['id'=>'ASC']);
        $id=$request->isMethod('POST')?$request->request->getInt('universe'):$request->query->getInt('universe',$universes[0]->id ?? 0);
        $universe=$em->find(Universe::class,$id);if (!$universe) throw $this->createNotFoundException('Univers introuvable.');
        $draft=$request->isMethod('POST')?$request->request->all():($request->getSession()->remove('weekly_rotation_draft_'.$id) ?? ['duration_hours'=>168,'enabled'=>true]);
        if ($request->isMethod('POST')) {
            if (!$this->isCsrfTokenValid('weekly_rotation_create_'.$id,$request->request->get('_token'))) throw $this->createAccessDeniedException('Formulaire expiré.');
            try { $rotations->create($this->getUser(),$universe,$draft);$this->addFlash('success','Modèle créé. Les occurrences existantes restent inchangées.');return $this->redirectToRoute('app_admin_weekly_rotations',['universe'=>$id]); }
            catch (\DomainException $error) { $draft['enabled']=!empty($draft['enabled']);$request->getSession()->set('weekly_rotation_draft_'.$id,$draft);$this->addFlash('error',$error->getMessage());return $this->redirectToRoute('app_admin_weekly_rotations',['universe'=>$id]); }
        }
        return $this->render('admin/weekly_rotations.html.twig',['universes'=>$universes,'universe'=>$universe,'definitions'=>$rotations->definitions($universe),
            'objectives'=>AdminEventPlanner::OBJECTIVES,'formats'=>WeeklyRotationService::FORMATS,'reward_modes'=>WeeklyRotationService::REWARDS,'draft'=>$draft]);
    }

    #[Route('/admin/rotations/{id}/activation',name:'app_admin_weekly_rotation_toggle',requirements:['id'=>'\d+'],methods:['POST'])]
    public function toggle(int $id,Request $request,EntityManagerInterface $em,WeeklyRotationService $rotations): Response
    {
        if (!$this->isCsrfTokenValid('weekly_rotation_toggle_'.$id,$request->request->get('_token'))) throw $this->createAccessDeniedException('Formulaire expiré.');
        $universe=$em->find(Universe::class,$id);if (!$universe) throw $this->createNotFoundException();
        try {
            if (!in_array($request->request->get('enabled'),['0','1'],true)) throw new \DomainException('État d’activation invalide.');
            $rotations->setEnabled($this->getUser(),$universe,(string)$request->request->get('key'),$request->request->getBoolean('enabled'),(string)$request->request->get('reason'));
            $this->addFlash('success','Activation mise à jour. Les occurrences déjà programmées restent inchangées.');
        } catch (\DomainException $error) { $this->addFlash('error',$error->getMessage()); }
        return $this->redirectToRoute('app_admin_weekly_rotations',['universe'=>$id]);
    }

    #[Route('/admin/rotations/occurrences/{id}/annuler',name:'app_admin_weekly_rotation_cancel',requirements:['id'=>'\d+'],methods:['POST'])]
    public function cancel(int $id,Request $request,EntityManagerInterface $em,WeeklyRotationService $rotations): Response
    {
        if (!$this->isCsrfTokenValid('weekly_rotation_cancel_'.$id,$request->request->get('_token'))) throw $this->createAccessDeniedException('Formulaire expiré.');
        $event=$em->find(BoostEvent::class,$id);if (!$event) throw $this->createNotFoundException();
        try {
            if (!$request->request->getBoolean('confirmed')) throw new \DomainException('Confirmez explicitement l’annulation.');
            $rotations->cancel($this->getUser(),$event,(string)$request->request->get('reason'));
            $this->addFlash('success','Rotation annulée sans recréation cette semaine. Historique et récompenses acquises conservés.');
        } catch (\DomainException $error) { $this->addFlash('error',$error->getMessage()); }
        return $this->redirectToRoute('app_admin_challenge',['id'=>$id]);
    }
}
