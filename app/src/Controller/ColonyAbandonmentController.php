<?php
namespace App\Controller;

use App\Entity\Planet;
use App\Repository\GameRepository;
use App\Service\Catalog\GameCatalog;
use App\Service\Colony\ColonyAbandonmentService;
use App\Service\Universe\UniverseContext;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\{Request,Response};
use Symfony\Component\Routing\Attribute\Route;

final class ColonyAbandonmentController extends AbstractController
{
    #[Route('/planetes/{id}/abandonner',name:'app_planet_abandon',methods:['GET','POST'],requirements:['id'=>'\d+'])]
    public function abandon(int $id,Request $request,UniverseContext $context,GameRepository $repo,
        ColonyAbandonmentService $service,GameCatalog $catalog): Response
    {
        $member=$context->membership();
        if($request->isMethod('POST') && ((string)$request->request->get('membership')!==(string)$member->id
            || !$this->isCsrfTokenValid('abandon-'.$member->id.'-'.$id,$request->request->get('_token')))) {
            throw $this->createAccessDeniedException('Formulaire expiré ou univers modifié. Rouvrez le bilan d’abandon.');
        }
        $planet=$repo->find(Planet::class,$id);
        if(!$planet || $planet->owner?->id!==$member->id || $planet->solarSystem->galaxy->universe->id!==$member->universe->id) throw $this->createNotFoundException('Planète inaccessible.');
        if($request->isMethod('POST')) {
            try {
                $changed=$service->abandon($member,$planet,(string)$request->request->get('name',''),(string)$request->request->get('request_key',''),(string)$request->request->get('version',''));
            } catch(\DomainException $error) {
                $this->addFlash('error',$error->getMessage());
                return $this->redirectToRoute('app_planet_abandon',['id'=>$id],Response::HTTP_SEE_OTHER);
            }
            $remaining=$repo->planets($member->universe,$member);
            $ids=array_map(static fn(Planet $p):int=>$p->id,$remaining);
            $selected=$request->getSession()->get('selected_planet_'.$member->id,0);
            if(!in_array($selected,$ids,true)) {
                $selected=$ids[0]??0;$request->getSession()->set('selected_planet_'.$member->id,$selected);
            }
            $this->addFlash($changed?'success':'info',$changed?'Colonie abandonnée. Les pertes sont définitives ; les historiques sont conservés.':'Cette demande a déjà été traitée.');
            return $this->redirectToRoute('app_planets',['_fragment'=>'planet-'.$selected],Response::HTTP_SEE_OTHER);
        }
        $preview=$service->preview($member,$planet);
        return $this->render('colony/abandon.html.twig',[
            'planet'=>$planet,'membership'=>$member,'preview'=>$preview,'request_key'=>bin2hex(random_bytes(16)),
            'tiers'=>$catalog->tiers(),'resources'=>$catalog->rules()['resources'],
        ],new Response(headers:['Cache-Control'=>'private, no-store']));
    }
}
