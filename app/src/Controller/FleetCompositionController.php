<?php
namespace App\Controller;

use App\Entity\Planet;
use App\Service\Fleet\FleetCompositionService;
use App\Service\Catalog\GameCatalog;
use App\Service\Universe\UniverseContext;
use App\Repository\GameRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\{Request,Response};
use Symfony\Component\Routing\Attribute\Route;

final class FleetCompositionController extends AbstractController
{
    #[Route('/flottes/compositions/creer',name:'app_fleet_composition_new',methods:['GET','POST'])]
    #[Route('/flottes/compositions/{id}/modifier',name:'app_fleet_composition_edit',requirements:['id'=>'\d+'],methods:['GET','POST'])]
    #[Route('/flottes/compositions',name:'app_fleet_compositions',methods:['GET','POST'])]
    public function index(Request $request,UniverseContext $context,FleetCompositionService $compositions,GameCatalog $catalog): Response
    {
        $owner=$context->membership();$error=null;$editing=null;$draft=[];$status=200;
        $route=$request->attributes->getString('_route');
        $editor=$route!=='app_fleet_compositions';
        if($request->isMethod('GET') && $request->query->getInt('edit')) {
            return $this->redirectToRoute('app_fleet_composition_edit',['id'=>$request->query->getInt('edit')]);
        }
        if($id=$request->attributes->getInt('id')) {
            try {$editing=$compositions->owned($owner,$id);$draft=['name'=>$editing->name,'ships'=>$editing->ships];}
            catch(\DomainException) {throw $this->createNotFoundException('Composition inaccessible.');}
        }
        if($request->isMethod('POST')) {
            if(!$this->isCsrfTokenValid('fleet_compositions',$request->request->get('_token'))) throw $this->createAccessDeniedException('Formulaire expiré.');
            $editor=$request->request->getString('action')!=='delete';
            $draft=['name'=>$request->request->getString('name'),'ships'=>$request->request->all('ships')];
            // Les pages dédiées portent leur cible dans l’URL ; le POST historique garde son identifiant.
            $compositionId=match($route) {
                'app_fleet_composition_new'=>0,
                'app_fleet_composition_edit'=>$request->attributes->getInt('id'),
                default=>$request->request->getInt('id'),
            };
            try {
                if($request->request->get('action')==='delete') $compositions->delete($owner,$compositionId,$request->request->getInt('version'));
                else $compositions->save($owner,$compositionId,$draft['name'],$draft['ships'],$request->request->getInt('version'),$request->request->getString('request_key'));
                $this->addFlash('success','Compositions mises à jour. Aucune flotte n’a été envoyée.');return $this->redirectToRoute('app_fleet_compositions');
            } catch(\DomainException $e) {$error=$e->getMessage();$status=422;}
        }
        return $this->render('fleet/compositions.html.twig',[
            'compositions'=>$editor?[]:$compositions->all($owner),'editor'=>$editor,'editing'=>$editing,'draft'=>$draft,'error'=>$error,'membership'=>$owner,
            'catalog'=>$catalog->forUniverse($owner->universe)['catalog']['ships'],'limit'=>(int)$catalog->forUniverse($owner->universe)['limits']['max_fleet_size'],
        ],new Response(status:$status));
    }

    #[Route('/flottes/compositions/{id}/charger',name:'app_fleet_composition_load',requirements:['id'=>'\d+'],methods:['GET'])]
    public function load(int $id,Request $request,UniverseContext $context,FleetCompositionService $compositions,GameRepository $repo,GameCatalog $catalog): Response
    {
        $owner=$context->membership();
        try {$composition=$compositions->owned($owner,$id);} catch(\DomainException) {throw $this->createNotFoundException('Composition inaccessible.');}
        $planets=$repo->planets($owner->universe,$owner);$planet=$request->query->getInt('origin')?$repo->find(Planet::class,$request->query->getInt('origin')):($planets[0]??null);
        try {$plan=$planet?$compositions->plan($owner,$composition,$planet):null;} catch(\DomainException) {throw $this->createNotFoundException('Colonie inaccessible.');}
        return $this->render('fleet/composition_load.html.twig',['composition'=>$composition,'plan'=>$plan,'planet'=>$planet,'planets'=>$planets,'catalog'=>$catalog->forUniverse($owner->universe)['catalog']['ships']]);
    }
}
