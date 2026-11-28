<?php
declare(strict_types=1);
namespace App\Controller;

use App\Entity\{EditorialRevision,Universe};
use App\Service\Admin\PirateMissionEditor;
use App\Service\Catalog\GameCatalog;
use App\Service\PirateCampaign\PirateCampaignCatalogue;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\{Request,Response};
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_ADMIN')]
final class AdminPirateMissionController extends AbstractController
{
    #[Route('/admin/univers/{id}/campagne',name:'app_admin_pirate_missions',requirements:['id'=>'\d+'],methods:['GET','POST'])]
    public function index(Universe $universe, Request $request, EntityManagerInterface $em, PirateCampaignCatalogue $campaign, PirateMissionEditor $editor, GameCatalog $catalog): Response
    {
        $keys = $campaign->keys($universe);
        $key = $request->query->getString('model',$keys[0]);
        if (!in_array($key,$keys,true)) throw $this->createNotFoundException();
        $model = $campaign->definition($key,$universe);
        $version = $request->query->getInt('version');
        $revision = $version ? $em->getRepository(EditorialRevision::class)->findOneBy(['id'=>$version,'kind'=>'pirate','universe'=>$universe]) : null;
        if ($version && !$revision) throw $this->createNotFoundException();
        $fields = $model;
        if ($request->isMethod('POST')) {
            if (!$this->isCsrfTokenValid('pirate_editor_'.$universe->id.'_'.$version,$request->request->getString('_token'))) throw $this->createAccessDeniedException();
            try {
                if ($revision && $request->request->getString('action')==='publish') $editor->publish($this->getUser(),$universe,$revision);
                else {
                    $fields = $request->request->all();
                    $revision = $editor->prepare($this->getUser(),$universe,$fields);
                }
                return $this->redirectToRoute('app_admin_pirate_missions',['id'=>$universe->id,'version'=>$revision->id],303);
            } catch (\DomainException $e) { $this->addFlash('error',$e->getMessage()); }
        }
        $models = [];
        foreach ($keys as $code) $models[$code] = $campaign->definition($code,$universe)['title'];
        return $this->render('admin/pirate_editor.html.twig',['world'=>$universe,'models'=>$models,'key'=>$key,'m'=>$fields,'version'=>$revision,
            'art'=>$editor->illustrations($universe),'ships'=>$catalog->all('ships'),'tiers'=>$catalog->tiers()]);
    }
}
