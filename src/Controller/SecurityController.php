<?php
namespace App\Controller;
use App\Form\RegistrationType;
use App\Service\Player\RegistrationService;
use App\Validator\DTO\RegistrationData;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\{Request,Response};
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Authentication\AuthenticationUtils;
use Symfony\Component\Form\FormError;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
final class SecurityController extends AbstractController
{
    #[Route('/health',name:'app_health',methods:['GET'])]
    public function health(): Response { return new Response('ok',200,['Content-Type'=>'text/plain']); }
    #[Route('/connexion',name:'app_login',methods:['GET','POST'])]
    public function login(AuthenticationUtils $auth): Response
    {
        if($this->getUser()) return $this->redirectToRoute('app_home');
        return $this->render('security/login.html.twig',['last_email'=>$auth->getLastUsername(),'error'=>$auth->getLastAuthenticationError()]);
    }
    #[Route('/deconnexion',name:'app_logout',methods:['POST'])]
    public function logout(): never { throw new \LogicException('Intercepté par Symfony Security.'); }
    #[Route('/inscription',name:'app_register',methods:['GET','POST'])]
    public function register(Request $request,RegistrationService $service): Response
    {
        if($this->getUser()) return $this->redirectToRoute('app_home');
        $form=$this->createForm(RegistrationType::class,new RegistrationData());$form->handleRequest($request);
        if($form->isSubmitted() && $form->isValid()) {
            try { $service->register($form->getData());$this->addFlash('success','Compte créé. Connectez-vous pour choisir votre univers.');return $this->redirectToRoute('app_login'); }
            catch(\DomainException|UniqueConstraintViolationException $e) { $form->addError(new FormError('Cette adresse email ou ce pseudo est déjà utilisé.')); }
        }
        return $this->render('security/register.html.twig',['form'=>$form],new Response(status:$form->isSubmitted()?422:200));
    }
}
