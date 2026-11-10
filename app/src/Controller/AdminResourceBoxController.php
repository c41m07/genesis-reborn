<?php
namespace App\Controller;

use App\Entity\Universe;
use App\Service\Boost\ResourceBoxPolicy;
use App\Service\Catalog\GameCatalog;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\{Request, Response};
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_ADMIN')]
final class AdminResourceBoxController extends AbstractController
{
    #[Route('/admin/universes/{id}/boites', name:'app_admin_resource_boxes', requirements:['id'=>'\d+'], methods:['GET','POST'])]
    public function index(int $id, Request $request, EntityManagerInterface $em, ResourceBoxPolicy $boxes, GameCatalog $catalog): Response
    {
        $universe=$em->find(Universe::class,$id);if (!$universe) throw $this->createNotFoundException();
        $draft=[];
        if ($request->isMethod('POST')) {
            if (!$this->isCsrfTokenValid('admin_boxes_'.$id,$request->request->get('_token'))) throw $this->createAccessDeniedException('Formulaire expiré.');
            $draft=$request->request->all();
            try {
                if (!$request->request->getBoolean('confirmed')) throw new \DomainException('Confirmez les nouveaux modèles et plafonds.');
                $boxes->saveSettings($this->getUser(),$universe,$draft,(string)$request->request->get('reason'));
                $this->addFlash('success','Modèles et plafonds enregistrés pour cet univers.');return $this->redirectToRoute('app_admin_resource_boxes',['id'=>$id]);
            } catch (\DomainException $error) { $this->addFlash('error',$error->getMessage()); }
        }
        return $this->render('admin/resource_boxes.html.twig',['universe'=>$universe,'settings'=>$boxes->settings($universe),'draft'=>$draft,'formats'=>ResourceBoxPolicy::FORMATS,'resources'=>$catalog->rules()['resources']],new Response(status:$draft?422:200));
    }
}
