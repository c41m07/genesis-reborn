<?php
namespace App\Controller;
use App\Form\ProfileType;
use App\Service\Player\ProfileService;
use App\Validator\DTO\ProfileData;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\{Request,Response};
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Form\FormError;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
final class ProfileController extends AbstractController
{
    #[Route('/profil',name:'app_profile',methods:['GET','POST'])]
    public function index(Request $request,ProfileService $service,Security $security):Response
    {
        $data=new ProfileData();$data->email=$this->getUser()->email;$data->username=$this->getUser()->username;
        $form=$this->createForm(ProfileType::class,$data);$form->handleRequest($request);
        if($form->isSubmitted() && $form->isValid()) {
            try {
                $service->update($this->getUser(),$data);
                $response=$security->logout(false);$request->getSession()->getFlashBag()->add('success','Profil enregistré. Connectez-vous avec vos nouveaux identifiants.');
                return $response??$this->redirectToRoute('app_login');
            } catch(\DomainException|UniqueConstraintViolationException $e) { $form->addError(new FormError($e instanceof \DomainException?$e->getMessage():'Cette adresse email ou ce pseudo est déjà utilisé.')); }
        }
        return $this->render('player/profile.html.twig',['form'=>$form],new Response(status:$form->isSubmitted()?422:200));
    }
    #[Route('/profil/supprimer',name:'app_account_delete',methods:['POST'])]
    public function delete(Request $request,\App\Service\Player\AccountDeletionService $deletion,Security $security): Response
    {
        if (!$this->isCsrfTokenValid('account_delete',$request->request->get('_token'))) throw $this->createAccessDeniedException('Formulaire expiré.');
        try {
            $deletion->delete($this->getUser(),(string)$request->request->get('password'),$request->request->getBoolean('confirmed'));
        } catch (\DomainException $error) {
            $this->addFlash('error',$error->getMessage());
            return $this->redirectToRoute('app_profile');
        }
        $response=$security->logout(false);
        $request->getSession()->getFlashBag()->add('success','Votre compte a été supprimé. Vos empires sont devenus des empires oubliés.');
        return $response??$this->redirectToRoute('app_login');
    }

}
