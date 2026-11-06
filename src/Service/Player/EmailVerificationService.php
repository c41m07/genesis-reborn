<?php
namespace App\Service\Player;

use App\Entity\{EmailVerificationToken, Player};
use App\Manager\GameManager;
use App\Repository\GameRepository;
use Psr\Clock\ClockInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

final class EmailVerificationService
{
    public function __construct(private GameManager $manager, private GameRepository $repo, private MailerInterface $mailer, private UrlGeneratorInterface $urls, private ClockInterface $clock, private LoggerInterface $logger, #[Autowire('%env(DEFAULT_URI)%')] private string $baseUrl, #[Autowire('%env(MAILER_FROM)%')] private string $sender) {}

    public function sendWelcomeAndVerification(Player $player, bool $welcome = true): void
    {
        if ($player->emailVerifiedAt !== null) return;
        $plainToken = rtrim(strtr(base64_encode(random_bytes(32)), '+/', '-_'), '=');
        $token = new EmailVerificationToken();
        $token->player = $player;
        $token->tokenHash = hash('sha256', $plainToken);
        $token->expiresAt = $this->clock->now()->modify('+24 hours');
        $this->manager->transaction(function () use ($player, $token): void {
            $this->manager->lock($player);
            if ($player->emailVerifiedAt !== null) return;
            foreach ($this->repo->all(EmailVerificationToken::class, ['player' => $player]) as $oldToken) $this->manager->remove($oldToken);
            $this->manager->save($token);
        });
        $url = rtrim($this->baseUrl, '/') . $this->urls->generate('app_email_verify', ['token' => $plainToken]);
        $messages = [['Confirmez votre adresse email', "Pour activer votre compte Genesis Reborn, utilisez ce lien valable 24 heures :\n$url"]];
        if ($welcome) $messages[] = ['Bienvenue dans Genesis Reborn', "Bienvenue, {$player->username} ! Votre compte a été créé. Un second message contient le lien de confirmation de votre adresse."];
        foreach ($messages as [$subject, $body]) {
            try {
                $this->mailer->send((new Email())->from($this->sender)->to($player->email)->subject($subject)->text($body));
            } catch (TransportExceptionInterface $exception) {
                $this->logger->warning('Envoi du mail de création de compte impossible.', ['exception' => $exception::class]);
            }
        }
    }

    public function verify(string $plainToken): bool
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
