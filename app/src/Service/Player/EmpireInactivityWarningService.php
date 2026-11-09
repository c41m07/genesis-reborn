<?php
namespace App\Service\Player;

use App\Entity\{EmpireInactivityWarning, PlayerUniverse};
use App\Manager\GameManager;
use App\Repository\GameRepository;
use Psr\Clock\ClockInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

final class EmpireInactivityWarningService
{
    public const REMINDER_AFTER_SECONDS = 27 * 86400;

    public function __construct(
        private GameManager $manager,
        private GameRepository $repo,
        private MailerInterface $mailer,
        private UrlGeneratorInterface $urls,
        private ClockInterface $clock,
        private LoggerInterface $logger,
        private EmpireActivityService $activity,
        #[Autowire('%env(DEFAULT_URI)%')] private string $baseUrl,
        #[Autowire('%env(MAILER_FROM)%')] private string $sender,
        #[Autowire('%env(bool:EMPIRE_INACTIVITY_WARNINGS_ENABLED)%')] private bool $enabled,
    ) {}

    /** @return array{preview: bool, eligible: int, sent: int, skipped: int, failed: int} */
    public function run(bool $send = false, int $limit = 100): array
    {
        if ($limit < 1 || $limit > 500) throw new \InvalidArgumentException('La limite doit être comprise entre 1 et 500.');
        if ($send && !$this->enabled) throw new \DomainException('Les courriels d’inactivité sont désactivés en configuration.');
        $now = $this->clock->now()->setTimezone(new \DateTimeZone('UTC'));
        $candidates = $this->repo->inactivityWarningCandidates(
            $now,
            EmpireActivityService::INACTIVE_AFTER_SECONDS,
            self::REMINDER_AFTER_SECONDS,
            EmpireActivityService::CONVERSION_AFTER_SECONDS,
            $limit,
        );
        $result = ['preview' => !$send, 'eligible' => count($candidates), 'sent' => 0, 'skipped' => 0, 'failed' => 0];
        if (!$send) return $result;

        foreach ($candidates as $membership) {
            ++$result[$this->sendWarning($membership)];
        }
        return $result;
    }

    private function sendWarning(PlayerUniverse $membership): string
    {
        return $this->manager->transaction(function () use ($membership): string {
            $this->manager->lock($membership);
            $now = $this->clock->now()->setTimezone(new \DateTimeZone('UTC'));
            $stage = $this->stage($membership, $now);
            if ($stage === null) return 'skipped';

            $activityAt = $membership->lastActivityAt->setTimezone(new \DateTimeZone('UTC'));
            $warning = $this->repo->one(EmpireInactivityWarning::class, [
                'membership' => $membership, 'activityAt' => $activityAt, 'thresholdDays' => $stage,
            ]);
            if ($warning !== null) $this->manager->lock($warning);
            if ($warning?->sentAt !== null) return 'skipped';
            if ($warning === null) {
                $warning = new EmpireInactivityWarning();
                $warning->membership = $membership;
                $warning->activityAt = $activityAt;
                $warning->thresholdDays = $stage;
                $warning->createdAt = $now;
                $this->manager->save($warning);
                $this->manager->flush();
            }

            $deadline = (new \DateTimeImmutable($this->activity->snapshot($membership, $now)['conversion_due_at']))->format('d/m/Y à H:i') . ' UTC';
            $url = rtrim($this->baseUrl, '/') . $this->urls->generate('app_universes');
            $subject = $stage === 27 ? 'Dernier avertissement : votre empire Genesis Reborn est inactif' : 'Votre empire Genesis Reborn est inactif';
            $message = (new Email())->from($this->sender)->to($membership->player->email)->subject($subject)
                ->text("Bonjour {$membership->player->username},\n\nVotre empire dans l’univers « {$membership->universe->name} » est inactif depuis au moins $stage jours.\n\nPendant cette inactivité, les protections contre le pillage liées au statut de votre empire sont suspendues.\n\nSans visite de cet univers avant le $deadline, votre empire pourra être transformé définitivement en empire oublié contrôlé par le jeu et conquérable par les autres joueurs. Votre compte sera conservé, mais vous devrez recommencer de zéro dans cet univers si vous revenez après cette transformation.\n\nPour conserver votre empire, reconnectez-vous puis ouvrez cet univers avant cette date :\n$url");
            try {
                $this->mailer->send($message);
            } catch (TransportExceptionInterface $exception) {
                $this->logger->warning('Envoi du courriel d’inactivité impossible.', [
                    'membership_id' => $membership->id, 'threshold_days' => $stage, 'exception' => $exception::class,
                ]);
                return 'failed';
            }
            $warning->sentAt = $now;
            return 'sent';
        });
    }

    private function stage(PlayerUniverse $membership, \DateTimeImmutable $now): ?int
    {
        if (!$membership->isPlayerControlled() || $membership->lastActivityAt === null || $membership->player->emailVerifiedAt === null
            || $membership->player->lockedAt !== null || !$membership->universe->isAccessible($now)) return null;
        if ($this->activity->snapshot($membership, $now)['status'] === 'vacation') return null;
        $elapsed = $this->activity->inactiveSeconds($membership, $now);
        if ($elapsed < EmpireActivityService::INACTIVE_AFTER_SECONDS || $elapsed >= EmpireActivityService::CONVERSION_AFTER_SECONDS) return null;
        return $elapsed >= self::REMINDER_AFTER_SECONDS ? 27 : 14;
    }
}
