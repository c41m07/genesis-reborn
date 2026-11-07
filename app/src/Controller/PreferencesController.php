<?php
namespace App\Controller;
use App\Form\PreferencesType;
use App\Service\Player\PreferencesService;
use App\Validator\DTO\PreferencesData;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\{Request,Response};
use Psr\Log\LoggerInterface;
use Symfony\Component\Routing\Attribute\Route;
final class PreferencesController extends AbstractController
{
    #[Route('/profil/preferences',name:'app_preferences',methods:['GET','POST'])]
    public function index(Request $request,PreferencesService $service,LoggerInterface $logger):Response
    {
        $data=new PreferencesData();$service->hydrate($this->getUser(),$data);$form=$this->createForm(PreferencesType::class,$data);$form->handleRequest($request);
        if($form->isSubmitted()){if($form->isValid()){$service->save($this->getUser(),$data);$this->addFlash('success','Préférences enregistrées.');return $this->redirectToRoute('app_preferences');}$errors=[];foreach($form->getErrors(true) as $error){$field=$error->getOrigin()?->getName()??'form';$errors[$field][]=$error->getMessage();}$logger->warning('Préférences refusées par la validation du formulaire.',['fields'=>array_keys($errors),'messages'=>$errors]);}
        return $this->render('player/preferences.html.twig',['form'=>$form],new Response(status:$form->isSubmitted()?422:200));
    }
}
