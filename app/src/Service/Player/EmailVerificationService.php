<?php
namespace App\Service\Player;

use App\Entity\{EmailVerificationToken, Player};
use App\Manager\GameManager;
use App\Repository\GameRepository;
use Psr\Clock\ClockInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Twig\Environment;

final class EmailVerificationService
{
    public function __construct(private GameManager $manager, private GameRepository $repo, private MailerInterface $mailer, private UrlGeneratorInterface $urls, private ClockInterface $clock, private LoggerInterface $logger, #[Autowire('%env(DEFAULT_URI)%')] private string $baseUrl, #[Autowire('%env(MAILER_FROM)%')] private string $sender, private Environment $twig) {}

    public function sendWelcomeAndVerification(Player $player, bool $welcome = true): bool
    {
        $prepared = $this->prepareVerification($player);
        if ($prepared === null) {
            // Le booléen historique reste vrai pour un compte déjà confirmé,
            // mais aucune acceptation de transport n'est revendiquée ici.
            return true;
        }
        $plainToken = $prepared['token'];
        $url = rtrim($this->baseUrl, '/') . $this->urls->generate('app_email_verify', ['token' => $plainToken]);
        $messages = [['Confirmez votre adresse email', "Pour activer votre compte Genesis Reborn, utilisez ce lien valable 24 heures :\n$url", 'emails/verification.html.twig']];
        if ($welcome) $messages[] = ['Bienvenue dans Genesis Reborn', "Bienvenue, {$prepared['username']} ! Votre compte a été créé. Un second message contient le lien de confirmation de votre adresse.", 'emails/welcome.html.twig'];
        $delivery = new AccountMailDelivery($this->mailer, $this->logger);
        $verificationSent = false;
        foreach ($messages as [$subject, $body, $template]) {
            $sent = $delivery->send((new Email())->from($this->sender)->to($prepared['email'])->subject($subject)->text($body)
                ->html($this->twig->render($template, ['username' => $prepared['username'], 'actionUrl' => $url])), $template === 'emails/verification.html.twig' ? 'verification' : 'welcome');
            if ($template === 'emails/verification.html.twig') $verificationSent = $sent;
        }
        return $verificationSent;
    }

    /**
     * Prépare le lien sous le verrou commun du compte. La valeur retournée
     * n'est retournée qu'après la transaction locale : un refus ne doit jamais envoyer
     * un jeton qui n'a pas été enregistré. Aucun transport dans la transaction.
     *
     * @return array{token: string, email: string, username: string}|null
     */
    private function prepareVerification(Player $player): ?array
    {
        return $this->manager->transaction(function () use ($player): ?array {
            $this->manager->lock($player);
            if ($player->emailVerifiedAt !== null) {
                return null;
            }

            $plainToken = rtrim(strtr(base64_encode(random_bytes(32)), '+/', '-_'), '=');
            $token = new EmailVerificationToken();
            $token->player = $player;
            $token->tokenHash = hash('sha256', $plainToken);
            $token->expiresAt = $this->clock->now()->modify('+24 hours');
            foreach ($this->repo->all(EmailVerificationToken::class, ['player' => $player]) as $oldToken) {
                $this->manager->remove($oldToken);
            }
            $this->manager->save($token);

            // Instantané privé de transport, jamais persisté dans un journal.
            // Ce n'est pas un reçu idempotent et il ne règle pas deux renvois.
            return ['token' => $plainToken, 'email' => $player->email, 'username' => $player->username];
        });
    }

    public function verify(#[\SensitiveParameter] string $plainToken): bool
    {
        if (!preg_match('/^[A-Za-z0-9_-]{40,50}$/', $plainToken)) return false;
        return $this->manager->transaction(function () use ($plainToken): bool {
            $token = $this->repo->one(EmailVerificationToken::class, ['tokenHash' => hash('sha256', $plainToken)]);
            if (!$token instanceof EmailVerificationToken) return false;
            $this->manager->lock($token->player);
            $token = $this->repo->one(EmailVerificationToken::class, ['tokenHash' => hash('sha256', $plainToken)]);
            if (!$token instanceof EmailVerificationToken) return false;
            if ($token->expiresAt <= $this->clock->now() || $token->player->emailVerifiedAt !== null) return false;
            $token->player->emailVerifiedAt = $this->clock->now();
            $this->manager->remove($token);
            return true;
        });
    }
}
