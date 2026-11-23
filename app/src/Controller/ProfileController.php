<?php
namespace App\Controller;
use App\Form\ProfileType;
use App\Entity\Player;
use App\Service\Player\{ProfileCosmetics, ProfileService};
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
    public function index(Request $request,ProfileService $service,Security $security,ProfileCosmetics $cosmetics):Response
    {
        // L’ancien raccourci de suppression devient une vraie page, avant de préparer le formulaire des identifiants.
        if ($request->isMethod('GET') && $request->query->getString('delete') === '1') {
            $parameters = $request->query->all();unset($parameters['delete']);
            return $this->redirectToRoute('app_account_delete_page', $parameters);
        }
        $data=new ProfileData();$data->email=$this->getUser()->email;$data->username=$this->getUser()->username;
        $form=$this->createForm(ProfileType::class,$data);$form->handleRequest($request);
        if($form->isSubmitted() && $form->isValid()) {
            try {
                $service->update($this->getUser(),$data);
                $response=$security->logout(false);$request->getSession()->getFlashBag()->add('success','Profil enregistré. Connectez-vous avec vos nouveaux identifiants.');
                return $response??$this->redirectToRoute('app_login');
            } catch(\DomainException|UniqueConstraintViolationException $e) { $form->addError(new FormError($e instanceof \DomainException?$e->getMessage():'Cette adresse email ou ce pseudo est déjà utilisé.')); }
        }
        return $this->render('player/profile.html.twig',['form'=>$form,'cosmetics'=>$cosmetics->owned($this->getUser())],new Response(status:$form->isSubmitted()?422:200));
    }
    #[Route('/profil/cosmetiques', name: 'app_profile_cosmetics', methods: ['POST'])]
    public function cosmetics(Request $request, ProfileCosmetics $cosmetics): Response
    {
        $player = $this->getUser();
        if (!$player instanceof Player || !$this->isCsrfTokenValid('profile_cosmetics', $request->request->getString('_token'))) {
            throw $this->createAccessDeniedException('Formulaire expiré.');
        }
        try {
            $cosmetics->equip($player, $request->request->all('equipped'));
            $this->addFlash('success', 'Apparence du profil enregistrée.');
        } catch (\DomainException $error) {
            $this->addFlash('error', $error->getMessage());
        }

        return $this->redirectToRoute('app_profile', ['_fragment' => 'profile-cosmetics'], Response::HTTP_SEE_OTHER);
    }

    #[Route('/profil/supprimer', name: 'app_account_delete_page', methods: ['GET'])]
    public function deletePage(): Response
    {
        return $this->render('player/account_delete.html.twig', [], new Response(headers: ['Cache-Control' => 'private, no-store']));
    }

    #[Route('/profil/supprimer',name:'app_account_delete',methods:['POST'])]
    public function delete(Request $request,\App\Service\Player\AccountDeletionService $deletion,Security $security): Response
    {
        if (!$this->isCsrfTokenValid('account_delete',$request->request->get('_token'))) throw $this->createAccessDeniedException('Formulaire expiré.');
        try {
            $deletion->delete($this->getUser(),(string)$request->request->get('password'),$request->request->getBoolean('confirmed'));
        } catch (\DomainException $error) {
            $this->addFlash('error',$error->getMessage());
            return $this->redirectToRoute('app_account_delete_page');
        }
        $response=$security->logout(false);
        $request->getSession()->getFlashBag()->add('success','Votre compte a été supprimé. Vos empires sont devenus des empires oubliés.');
        return $response??$this->redirectToRoute('app_login');
    }

}
