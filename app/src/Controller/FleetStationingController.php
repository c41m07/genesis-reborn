<?php
namespace App\Controller;

use App\Service\Fleet\{FleetHold,FleetStationing};
use App\Service\Universe\UniverseContext;
use App\Repository\GameRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\{Request,Response};
use Symfony\Component\Routing\Attribute\Route;

final class FleetStationingController extends AbstractController
{
    #[Route('/flottes/reserve/{planet}/decharger',name:'app_fleet_unload',requirements:['planet'=>'\d+'],methods:['POST'])]
    public function unload(int $planet,Request $request,UniverseContext $context,FleetHold $hold): Response
    {
        if(!$this->isCsrfTokenValid('fleet_unload_'.$planet,$request->request->get('_token'))) throw $this->createAccessDeniedException('Formulaire expiré.');
        try {
            $hold->unload($context->membership(),$planet,$request->request->getString('kind'),$request->request->getString('request_key'));
            $this->addFlash('success','Déchargement effectué dans les capacités disponibles. Le surplus reste à bord.');
        } catch(\DomainException $error) {$this->addFlash('error',$error->getMessage());}
        return $this->redirectToRoute('app_fleets');
    }

    #[Route('/flottes/{id}/reprendre',name:'app_fleet_station_resume',requirements:['id'=>'\d+'],methods:['GET','POST'])]
    public function resume(int $id,Request $request,UniverseContext $context,FleetStationing $stationing,GameRepository $repo): Response
    {
        $member=$context->membership();
        try {$fleet=$stationing->owned($member,$id);}
        catch(\DomainException) {throw $this->createNotFoundException('Stationnement inaccessible.');}
        if($request->isMethod('POST')) {
            if(!$this->isCsrfTokenValid('fleet_resume_'.$id,$request->request->get('_token'))) throw $this->createAccessDeniedException('Formulaire expiré.');
            try {
                $stationing->resume($member,$id,$request->request->getInt('planet'),$request->request->getString('version'),$request->request->getString('request_key'));
                $this->addFlash('success','Reprise du stationnement enregistrée.');
            } catch(\DomainException $error) {$this->addFlash('error',$error->getMessage());}
            return $this->redirectToRoute('app_fleets');
        }
        $target=$request->query->getInt('planet');$quote=null;$error=null;
        if($target) try {$quote=$stationing->preview($member,$id,$target);}
        catch(\DomainException $exception) {$error=$exception->getMessage();}
        return $this->render('fleet/resume.html.twig',['fleet'=>$fleet,'planets'=>$repo->planets($member->universe,$member),
            'target_id'=>$target,'quote'=>$quote,'error'=>$error],new Response(status:$error?422:200));
    }
}
