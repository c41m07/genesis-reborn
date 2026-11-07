<?php
namespace App\Controller;
use App\Form\RegistrationType;
use App\Form\PasswordResetRequestType;
use App\Form\PasswordResetType;
use App\Service\Player\PasswordResetService;
use App\Validator\DTO\PasswordResetRequestData;
use App\Validator\DTO\PasswordResetData;
use App\Service\Player\RegistrationService;
use App\Service\Player\EmailVerificationService;
use App\Validator\DTO\RegistrationData;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\{Request,Response};
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\RateLimiter\RateLimiterFactory;
use Symfony\Component\Security\Http\Authentication\AuthenticationUtils;
use Symfony\Component\Form\FormError;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
final class SecurityController extends AbstractController
{
    #[Route('/acces-refuse', name: 'app_access_denied', methods: ['GET'])]
    public function accessDenied(): Response
    {
        return $this->render('security/access_denied.html.twig', [], new Response(status: 403));
    }

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
    public function register(Request $request,RegistrationService $service,EmailVerificationService $verification): Response
    {
        if($this->getUser()) return $this->redirectToRoute('app_home');
        $form=$this->createForm(RegistrationType::class,new RegistrationData());$form->handleRequest($request);
        if($form->isSubmitted() && $form->isValid()) {
            try { $player=$service->register($form->getData());$verification->sendWelcomeAndVerification($player);$this->addFlash('success','Compte créé. Consultez votre email pour confirmer votre adresse avant de vous connecter.');return $this->redirectToRoute('app_login'); }
            catch(\DomainException|UniqueConstraintViolationException $e) { $form->addError(new FormError('Cette adresse email ou ce pseudo est déjà utilisé.')); }
        }
        return $this->render('security/register.html.twig',['form'=>$form],new Response(status:$form->isSubmitted()?422:200));
    }

    #[Route('/inscription/renvoyer', name: 'app_email_verify_request', methods: ['GET', 'POST'])]
    public function resendVerification(Request $request, EmailVerificationService $service, \App\Repository\GameRepository $repo, #[Autowire(service: 'limiter.password_reset_request')] RateLimiterFactory $limiterFactory): Response
    {
        $form = $this->createForm(PasswordResetRequestType::class, new PasswordResetRequestData());
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            if ($limiterFactory->create('verification:' . ($request->getClientIp() ?? 'unknown'))->consume()->isAccepted()) {
                $player = $repo->one(\App\Entity\Player::class, ['email' => mb_strtolower(trim($form->getData()->email))]);
                if ($player) $service->sendWelcomeAndVerification($player, false);
            }
            $this->addFlash('success', 'Si cette adresse correspond à un compte à confirmer, un nouveau lien va être envoyé.');
            return $this->redirectToRoute('app_email_verify_request');
        }
        return $this->render('security/email_verification_request.html.twig', ['form' => $form], new Response(status: $form->isSubmitted() ? 422 : 200));
    }

    #[Route('/inscription/confirmer/{token}', name: 'app_email_verify', methods: ['GET', 'POST'])]
    public function verifyEmail(Request $request, string $token, EmailVerificationService $service): Response
    {
        $headers = ['Referrer-Policy' => 'no-referrer', 'Cache-Control' => 'no-store'];
        if ($request->isMethod('GET')) return $this->render('security/email_verification.html.twig', ['token' => $token], new Response(headers: $headers));
        if (!$this->isCsrfTokenValid('email_verify', $request->request->get('_token'))) return $this->render('security/email_verification_invalid.html.twig', [], new Response(status: 403, headers: $headers));
        if (!$service->verify($token)) {
            return $this->render('security/email_verification_invalid.html.twig', [], new Response(status: 400, headers: $headers));
        }
        $this->addFlash('success', 'Adresse email confirmée. Vous pouvez vous connecter.');
        $response = $this->redirectToRoute('app_login');
        $response->headers->add($headers);
        return $response;
    }

    #[Route('/mot-de-passe/oublie', name:'app_password_reset_request', methods:['GET','POST'])]
    public function requestPasswordReset(Request $request, PasswordResetService $service, #[Autowire(service:'limiter.password_reset_request')] RateLimiterFactory $limiterFactory): Response
    {
        if($this->getUser()) return $this->redirectToRoute('app_home');
        $form=$this->createForm(PasswordResetRequestType::class,new PasswordResetRequestData());
        $form->handleRequest($request);
        if($form->isSubmitted() && $form->isValid()) {
            $limiter=$limiterFactory->create($request->getClientIp()??'unknown');
            if($limiter->consume()->isAccepted()) $service->request($form->getData()->email);
            $this->addFlash('success','Si cette adresse correspond à un compte, un lien de réinitialisation va être envoyé.');
            return $this->redirectToRoute('app_password_reset_request');
        }
        return $this->render('security/password_reset_request.html.twig',['form'=>$form],new Response(status:$form->isSubmitted()?422:200));
    }

    #[Route('/mot-de-passe/reinitialiser/{token}', name:'app_password_reset', methods:['GET','POST'])]
    public function resetPassword(Request $request,string $token,PasswordResetService $service): Response
    {
        if($this->getUser()) return $this->redirectToRoute('app_home');
        $headers=['Referrer-Policy'=>'no-referrer','Cache-Control'=>'no-store'];
        if(!$service->isValid($token)) return $this->render('security/password_reset_invalid.html.twig',[],new Response(status:400,headers:$headers));
        $form=$this->createForm(PasswordResetType::class,new PasswordResetData());
        $form->handleRequest($request);
        if($form->isSubmitted() && $form->isValid()) {
            if(!$service->reset($token,$form->getData()->plainPassword)) return $this->render('security/password_reset_invalid.html.twig',[],new Response(status:400,headers:$headers));
            $this->addFlash('success','Mot de passe modifié. Vous pouvez vous connecter.');
            $response=$this->redirectToRoute('app_login');
            $response->headers->add($headers);
            return $response;
        }
        return $this->render('security/password_reset.html.twig',['form'=>$form],new Response(status:$form->isSubmitted()?422:200,headers:$headers));
    }

}
