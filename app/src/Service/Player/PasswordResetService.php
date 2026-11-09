<?php
namespace App\Service\Player;

use App\Entity\{PasswordResetToken,Player};
use App\Manager\GameManager;
use App\Repository\GameRepository;
use Psr\Clock\ClockInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Twig\Environment;

final class PasswordResetService
{
    public function __construct(
        private GameManager $manager,
        private GameRepository $repo,
        private MailerInterface $mailer,
        private UrlGeneratorInterface $urls,
        private UserPasswordHasherInterface $hasher,
        private ClockInterface $clock,
        private LoggerInterface $logger,
        #[Autowire('%env(DEFAULT_URI)%')] private string $baseUrl,
        #[Autowire('%env(MAILER_FROM)%')] private string $sender,
        private Environment $twig,
    ) {}

    public function request(string $email):void
    {
        $player=$this->repo->one(Player::class,['email'=>mb_strtolower(trim($email))]);
        if(!$player) return;

        $plainToken=rtrim(strtr(base64_encode(random_bytes(32)),'+/','-_'),'=');
        $this->manager->transaction(function()use($player,$plainToken):void{
            $this->manager->lock($player);
            foreach($this->repo->all(PasswordResetToken::class,['player'=>$player]) as $oldToken) $this->manager->remove($oldToken);
            $token=new PasswordResetToken();
            $token->player=$player;
            $token->tokenHash=hash('sha256',$plainToken);
            $token->createdAt=$this->clock->now();
            $token->expiresAt=$token->createdAt->modify('+1 hour');
            $this->manager->save($token);
        });

        $url=rtrim($this->baseUrl,'/').$this->urls->generate('app_password_reset',['token'=>$plainToken]);
        $message=(new Email())
            ->from($this->sender)
            ->to($player->email)
            ->subject('Réinitialisation de votre mot de passe Genesis Reborn')
            ->text("Une demande de réinitialisation de mot de passe a été effectuée pour votre compte Genesis Reborn.\n\nCe lien est valable une heure et ne peut être utilisé qu'une fois :\n$url\n\nSi vous n'êtes pas à l'origine de cette demande, ignorez ce message.")
            ->html($this->twig->render('emails/password_reset.html.twig', ['username' => $player->username, 'actionUrl' => $url]));
        try {
            $this->mailer->send($message);
        } catch(TransportExceptionInterface $exception) {
            $this->logger->warning('Envoi du lien de réinitialisation impossible.', ['exception'=>$exception::class]);
        }
    }

    public function isValid(string $plainToken):bool
    {
        if(!preg_match('/^[A-Za-z0-9_-]{40,50}$/',$plainToken)) return false;
        $token=$this->repo->one(PasswordResetToken::class,['tokenHash'=>hash('sha256',$plainToken)]);
        return $token instanceof PasswordResetToken && $token->expiresAt>$this->clock->now();
    }

    public function reset(string $plainToken,string $newPassword):bool
    {
        if(!preg_match('/^[A-Za-z0-9_-]{40,50}$/',$plainToken)) return false;
        $hash=hash('sha256',$plainToken);
        return $this->manager->transaction(function()use($hash,$newPassword):bool{
            $token=$this->repo->one(PasswordResetToken::class,['tokenHash'=>$hash]);
            if(!$token instanceof PasswordResetToken) return false;
            $player=$token->player;
            $this->manager->lock($player);
            $token=$this->repo->passwordResetTokenForUpdate($hash);
            if(!$token instanceof PasswordResetToken) return false;
            if($token->expiresAt<=$this->clock->now()) return false;
            $player->password=$this->hasher->hashPassword($player,$newPassword);
            foreach($this->repo->all(PasswordResetToken::class,['player'=>$player]) as $playerToken) $this->manager->remove($playerToken);
            return true;
        });
    }
}
