<?php

namespace App\Controller;

use App\Entity\{Player, PlayerUniverse};
use App\Service\Player\VacationEconomyService;
use Psr\Clock\ClockInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\{Request, Response};
use Symfony\Component\Routing\Attribute\Route;

final class VacationController extends AbstractController
{
    #[Route('/profil/vacances', name: 'app_vacation', methods: ['GET'])]
    public function index(VacationEconomyService $vacations, ClockInterface $clock): Response
    {
        $membership = $this->accessibleMembership($clock);
        return $this->render('player/vacation.html.twig', [
            'vacation_membership' => $membership,
            'vacation_state' => $membership !== null ? $vacations->settings($membership) : null,
        ], new Response(headers: ['Cache-Control' => 'private, no-store']));
    }

    #[Route('/profil/preferences/vacances/activer',name:'app_vacation_start',methods:['POST'])]
    public function startVacation(Request $request,VacationEconomyService $vacations,ClockInterface $clock):Response
    {
        $membership=$this->vacationMembership($request,$clock,'start');
        if($request->request->get('confirm')!=='1') {
            $this->addFlash('error','Confirmez avoir lu les conséquences du mode vacances avant de l’activer.');
            return $this->redirectToRoute('app_vacation',['_fragment'=>'vacances']);
        }
        try {
            $vacations->start($membership);
            $this->addFlash('success','Mode vacances activé pour cet univers. Votre production et vos files sont suspendues.');
        } catch(\DomainException $error) { $this->addFlash('error',$error->getMessage()); }
        return $this->redirectToRoute('app_vacation',['_fragment'=>'vacances']);
    }

    #[Route('/profil/preferences/vacances/terminer',name:'app_vacation_finish',methods:['POST'])]
    public function finishVacation(Request $request,VacationEconomyService $vacations,ClockInterface $clock):Response
    {
        $membership=$this->vacationMembership($request,$clock,'finish');
        try {
            $vacations->finish($membership);
            $this->addFlash('success','Vacances terminées dans cet univers. La production et les files ont repris.');
        } catch(\DomainException $error) { $this->addFlash('error',$error->getMessage()); }
        return $this->redirectToRoute('app_vacation',['_fragment'=>'vacances']);
    }

    private function accessibleMembership(ClockInterface $clock):?PlayerUniverse
    {
        $player=$this->getUser();
        $membership=$player instanceof Player?$player->currentPlayerUniverse:null;
        return $membership!==null && $membership->isPlayerControlled() && $membership->player->id===$player->id && $membership->universe->isAccessible($clock->now())?$membership:null;
    }

    private function vacationMembership(Request $request,ClockInterface $clock,string $action):PlayerUniverse
    {
        // L’identifiant et le jeton empêchent un ancien formulaire d’agir sur un autre empire après changement d’univers.
        $membership=$this->accessibleMembership($clock);
        if($membership===null || $request->request->getInt('membership_id')!==$membership->id) throw $this->createAccessDeniedException('L’univers sélectionné a changé ou est inaccessible. Ouvrez de nouveau les paramètres.');
        if(!$this->isCsrfTokenValid('vacation-'.$action.'-'.$membership->id,$request->request->get('_token'))) throw $this->createAccessDeniedException('Formulaire expiré. Ouvrez de nouveau les paramètres.');
        return $membership;
    }
}
