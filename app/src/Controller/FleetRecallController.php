<?php
namespace App\Controller;

use App\Entity\Fleet;
use App\Repository\GameRepository;
use App\Service\Catalog\GameCatalog;
use App\Service\Fleet\FleetRecallService;
use App\Service\Universe\UniverseContext;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\{Request,Response};
use Symfony\Component\Routing\Attribute\Route;

final class FleetRecallController extends AbstractController
{
    #[Route('/flottes/{id}/rappel',name:'app_fleet_recall',requirements:['id'=>'\d+'],methods:['GET','POST'])]
    public function recall(int $id,Request $request,UniverseContext $context,GameRepository $repo,FleetRecallService $recalls,GameCatalog $catalog): Response
    {
        $member=$context->membership();$fleet=$repo->find(Fleet::class,$id);
        if(!$fleet || $fleet->playerUniverse->id!==$member->id || $fleet->homePlanet->solarSystem->galaxy->universe->id!==$member->universe->id)
            throw $this->createNotFoundException('Flotte inaccessible.');
        if($request->isMethod('POST')) {
            if(!$this->isCsrfTokenValid('fleet_recall_'.$id,$request->request->get('_token'))) throw $this->createAccessDeniedException('Formulaire expiré.');
            try {
                $changed=$recalls->recall($member,$id,$request->request->getInt('return_planet')?:null,
                    $request->request->getString('version'),$request->request->getString('request_key'));
                $this->addFlash('success',$changed?'Flotte rappelée. Le retour est programmé.':'Ce rappel a déjà été traité.');
            } catch(\DomainException $error) { $this->addFlash('error',$error->getMessage()); }
            return $this->redirectToRoute('app_fleets',['_fragment'=>'fleet-'.$id]);
        }
        $lost=$fleet->homePlanet->owner?->id!==$member->id;
        $returnId=$request->query->getInt('return_planet');$quote=null;$error=null;
        if(!$lost || $returnId>0) try { $quote=$recalls->preview($member,$fleet,$returnId?:null); }
        catch(\DomainException $exception) { $error=$exception->getMessage(); }
        return $this->render('fleet/recall.html.twig',['fleet'=>$fleet,'membership'=>$member,'origin_lost'=>$lost,
            'return_planets'=>$repo->planets($member->universe,$member),'return_id'=>$returnId,'quote'=>$quote,'error'=>$error,
            'resources'=>$catalog->rules()['resources'],'request_key'=>bin2hex(random_bytes(16))],
            new Response(status:$error?422:200));
    }
}
