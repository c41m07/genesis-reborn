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
    #[Route('/admin/rotations', name:'app_admin_weekly_rotations', methods:['GET','POST'])]
    #[Route('/admin/rotations/nouveau', name:'app_admin_weekly_rotation_new', methods:['GET','POST'])]
    #[Route('/admin/rotations/{id}/{key}/modifier', name:'app_admin_weekly_rotation_edit', requirements:['id'=>'\d+', 'key'=>'[a-zA-Z0-9_-]+'], methods:['GET','POST'])]
    public function index(Request $request, EntityManagerInterface $em, WeeklyRotationService $rotations, ?int $id = null, ?string $key = null): Response
    {
        $list = $request->attributes->get('_route') === 'app_admin_weekly_rotations' && !$request->isMethod('POST');
        $universes = $list ? $em->getRepository(Universe::class)->findBy([], ['id'=>'ASC']) : [];
        // La fiche impose son univers ; un champ POST ne peut pas déplacer un modèle vers un autre univers.
        if ($id === null) {
            $defaultId = $universes[0]->id ?? 0;
            $id = $request->isMethod('POST') ? $request->request->getInt('universe') : $request->query->getInt('universe', $defaultId);
            if ($id === 0 && !$request->isMethod('POST')) $id = $em->getRepository(Universe::class)->findOneBy([], ['id'=>'ASC'])?->id ?? 0;
        }
        $universe = $em->find(Universe::class, $id);
        if (!$universe) throw $this->createNotFoundException('Univers introuvable.');
        $definitions = $rotations->definitions($universe);
        $definition = null;
        if ($key !== null) {
            foreach ($definitions as $row) if ($row['key'] === $key) { $definition = $row; break; }
            if ($definition === null) throw $this->createNotFoundException('Modèle introuvable dans cet univers.');
        }
        $form = $request->attributes->get('_route') !== 'app_admin_weekly_rotations' || $request->isMethod('POST');
        $draft = $definition === null ? ['duration_hours'=>168, 'enabled'=>true] : $definition + ['duration_hours'=>$definition['duration_seconds']/3600, 'version'=>$rotations->version($definition)];
        if ($request->isMethod('POST')) {
            $token = $key === null ? 'weekly_rotation_create_'.$id : 'weekly_rotation_edit_'.$id.'_'.$key;
            if (!$this->isCsrfTokenValid($token, $request->request->get('_token'))) throw $this->createAccessDeniedException('Formulaire expiré.');
            $draft = $request->request->all();
            $draft['enabled'] = $request->request->getBoolean('enabled');
            try {
                if ($key === null) $rotations->create($this->getUser(), $universe, $draft);
                else $rotations->update($this->getUser(), $universe, $key, $draft, $request->request->getString('version'));
                $this->addFlash('success', $key === null ? 'Modèle créé. Les occurrences existantes restent inchangées.' : 'Modèle modifié. Les occurrences existantes restent inchangées.');
                return $this->redirectToRoute('app_admin_weekly_rotations', ['universe'=>$id]);
            } catch (\DomainException $error) { $this->addFlash('error', $error->getMessage()); }
        }
        return $this->render($form ? 'admin/weekly_rotation_form.html.twig' : 'admin/weekly_rotations.html.twig', [
            'universes'=>$universes, 'universe'=>$universe, 'definitions'=>$form ? [] : $definitions,
            'objectives'=>AdminEventPlanner::OBJECTIVES, 'formats'=>WeeklyRotationService::FORMATS,
            'reward_modes'=>WeeklyRotationService::REWARDS, 'draft'=>$draft, 'editing'=>$definition,
        ]);
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
