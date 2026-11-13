<?php
namespace App\Controller;

use App\Service\Combat\PlayerCombatSimulation;
use App\Service\Universe\UniverseContext;
use App\Repository\GameRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\{Request,Response};
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\RateLimiter\RateLimiterFactory;

final class CombatSimulationController extends AbstractController
{
    #[Route('/simulateur',name:'app_combat_simulation',methods:['GET','POST'])]
    public function index(Request $request,UniverseContext $context,PlayerCombatSimulation $simulation,GameRepository $repo,#[Autowire(service:'limiter.combat_simulation')] RateLimiterFactory $limiter): Response
    {
        $owner=$context->membership();$result=null;$error=null;$intel=null;$forces=[];$seed='42';$status=200;
        $reportId=$request->isMethod('POST')?$request->request->getInt('report'):$request->query->getInt('report');
        try {
            if($reportId) {$intel=$simulation->intel($owner,$reportId);$forces['defender']=$intel['forces'];}
            if($request->isMethod('POST')) {
                if(!$this->isCsrfTokenValid('combat_simulation',$request->request->get('_token'))) throw $this->createAccessDeniedException('Formulaire expiré.');
                $forces=$request->request->all('forces');$seed=$request->request->getString('seed');
                if(!$limiter->create((string)$owner->player->id)->consume()->isAccepted()) throw new \DomainException('Limite de calcul atteinte. Attendez avant une nouvelle simulation.');
                if($request->request->get('assumptions')!=='1') throw new \DomainException('Confirmez les hypothèses avant de simuler.');
                $number=filter_var($seed,FILTER_VALIDATE_INT);
                if($number===false) throw new \DomainException('Graine invalide.');
                $result=$simulation->run($owner,$forces,$number);
            } elseif($request->query->getInt('own_planet')) $forces['attacker']=$simulation->own($owner,$request->query->getInt('own_planet'));
        } catch(\DomainException $e) {$error=$e->getMessage();$status=422;}
        $response=$this->render('combat/simulation.html.twig',[
            'membership'=>$owner,'choices'=>$simulation->choices($owner),'forces'=>$forces,'seed'=>$seed,'result'=>$result,'error'=>$error,'intel'=>$intel,
            'sources'=>$intel?$simulation->provenance($owner,$forces,$intel):[],'planets'=>$repo->planets($owner->universe,$owner),'max_units'=>PlayerCombatSimulation::MAX_UNITS,
        ],new Response(status:$status));
        $response->headers->set('Cache-Control','private, no-store');return $response;
    }
}
