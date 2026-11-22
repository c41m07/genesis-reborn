<?php

namespace App\Service\Player;

use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Mime\Email;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Twig\Environment;

/** Les deux messages restent distincts ; la bienvenue ne reçoit jamais le lien. */
final class VerificationEmailMessageFactory
{
    public function __construct(
        private UrlGeneratorInterface $urls,
        private Environment $twig,
        #[Autowire('%env(DEFAULT_URI)%')] private string $baseUrl,
        #[Autowire('%env(MAILER_FROM)%')] private string $sender,
    ) {
    }

    public function confirmation(#[\SensitiveParameter] string $address, #[\SensitiveParameter] string $token, string $username): Email
    {
        $url = rtrim($this->baseUrl, '/').$this->urls->generate('app_email_verify', ['token' => $token]);
        return (new Email())->from($this->sender)->to($address)
            ->subject('Confirmez votre adresse email')
            ->text("Pour activer votre compte Genesis Reborn, utilisez ce lien valable 24 heures :\n$url")
            ->html($this->twig->render('emails/verification.html.twig', ['username' => $username, 'actionUrl' => $url]));
    }

    public function welcome(#[\SensitiveParameter] string $address, string $username): Email
    {
        return (new Email())->from($this->sender)->to($address)
            ->subject('Bienvenue dans Genesis Reborn')
            ->text("Bienvenue, $username ! Votre compte a été créé. Un second message contient le lien de confirmation de votre adresse.")
            ->html($this->twig->render('emails/welcome.html.twig', ['username' => $username]));
    }
}
