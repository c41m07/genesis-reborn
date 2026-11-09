<?php
namespace App\Controller;
use App\Form\PreferencesType;
use App\Service\Player\PreferencesService;
use App\Service\Player\VacationEconomyService;
use App\Entity\{Player,PlayerUniverse};
use Psr\Clock\ClockInterface;
use App\Validator\DTO\PreferencesData;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\{Request,Response};
use Psr\Log\LoggerInterface;
use Symfony\Component\Routing\Attribute\Route;
final class PreferencesController extends AbstractController
{
    #[Route('/profil/preferences',name:'app_preferences',methods:['GET','POST'])]
    public function index(Request $request,PreferencesService $service,LoggerInterface $logger,VacationEconomyService $vacations,ClockInterface $clock):Response
    {
        $data=new PreferencesData();$service->hydrate($this->getUser(),$data);$form=$this->createForm(PreferencesType::class,$data);$form->handleRequest($request);
        if($form->isSubmitted()){if($form->isValid()){$service->save($this->getUser(),$data);$this->addFlash('success','Préférences enregistrées.');return $this->redirectToRoute('app_preferences');}$errors=[];foreach($form->getErrors(true) as $error){$field=$error->getOrigin()?->getName()??'form';$errors[$field][]=$error->getMessage();}$logger->warning('Préférences refusées par la validation du formulaire.',['fields'=>array_keys($errors),'messages'=>$errors]);}
        $membership=$this->accessibleMembership($clock);
        $vacationState=$membership!==null?$vacations->settings($membership):null;
        return $this->render('player/preferences.html.twig',['form'=>$form,'vacation_membership'=>$membership,'vacation_state'=>$vacationState],new Response(status:$form->isSubmitted()?422:200));
    }

    #[Route('/profil/preferences/vacances/activer',name:'app_vacation_start',methods:['POST'])]
    public function startVacation(Request $request,VacationEconomyService $vacations,ClockInterface $clock):Response
    {
        $membership=$this->vacationMembership($request,$clock,'start');
        if($request->request->get('confirm')!=='1') {
            $this->addFlash('error','Confirmez avoir lu les conséquences du mode vacances avant de l’activer.');
            return $this->redirectToRoute('app_preferences',['_fragment'=>'vacances']);
        }
        try {
            $vacations->start($membership);
            $this->addFlash('success','Mode vacances activé pour cet univers. Votre production et vos files sont suspendues.');
        } catch(\DomainException $error) { $this->addFlash('error',$error->getMessage()); }
        return $this->redirectToRoute('app_preferences',['_fragment'=>'vacances']);
    }

    #[Route('/profil/preferences/vacances/terminer',name:'app_vacation_finish',methods:['POST'])]
    public function finishVacation(Request $request,VacationEconomyService $vacations,ClockInterface $clock):Response
    {
        $membership=$this->vacationMembership($request,$clock,'finish');
        try {
            $vacations->finish($membership);
            $this->addFlash('success','Vacances terminées dans cet univers. La production et les files ont repris.');
        } catch(\DomainException $error) { $this->addFlash('error',$error->getMessage()); }
        return $this->redirectToRoute('app_preferences',['_fragment'=>'vacances']);
    }

    private function accessibleMembership(ClockInterface $clock):?PlayerUniverse
    {
        $player=$this->getUser();
        $membership=$player instanceof Player?$player->currentPlayerUniverse:null;
        return $membership!==null && $membership->isPlayerControlled() && $membership->player->id===$player->id && $membership->universe->isAccessible($clock->now())?$membership:null;
    }

    private function vacationMembership(Request $request,ClockInterface $clock,string $action):PlayerUniverse
    {
        $membership=$this->accessibleMembership($clock);
        if($membership===null || $request->request->getInt('membership_id')!==$membership->id) throw $this->createAccessDeniedException('L’univers sélectionné a changé ou est inaccessible. Ouvrez de nouveau les paramètres.');
        if(!$this->isCsrfTokenValid('vacation-'.$action.'-'.$membership->id,$request->request->get('_token'))) throw $this->createAccessDeniedException('Formulaire expiré. Ouvrez de nouveau les paramètres.');
        return $membership;
    }
}
