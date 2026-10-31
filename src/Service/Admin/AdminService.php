<?php
namespace App\Service\Admin;

use App\Entity\{AdminAuditLog, MessageReport, Player, PlayerUniverse, SocialMessage, Universe};
use App\Manager\GameManager;
use App\Repository\GameRepository;
use App\Service\Universe\UniverseLifecycleService;
use Psr\Clock\ClockInterface;

final class AdminService
{
    public function __construct(
        private GameManager $manager,
        private GameRepository $repo,
        private UniverseLifecycleService $lifecycle,
        private ClockInterface $clock,
    ) {}

    public function reportMessage(PlayerUniverse $reporter, int $messageId, string $reason): void
    {
        $reason = trim($reason);
        if ($reason === '' || mb_strlen($reason) > 500) {
            throw new \DomainException('Le motif doit contenir entre 1 et 500 caractères.');
        }
        $message = $this->repo->find(SocialMessage::class, $messageId);
        if (!$message) {
            throw new \DomainException('Message introuvable.');
        }
        $canReport = $message->recipient !== null
            ? ($message->sender->id === $reporter->id || $message->recipient->id === $reporter->id)
            : ($message->guild !== null && $message->guild->universe->id === $reporter->universe->id
                && $this->repo->one(\App\Entity\GuildMember::class, ['guild' => $message->guild, 'playerUniverse' => $reporter]) !== null);
        if (!$canReport) {
            throw new \DomainException('Ce message ne peut pas être signalé par ce compte.');
        }
        if ($message->moderatedAt !== null) {
            throw new \DomainException('Ce message a déjà été modéré.');
        }
        if ($this->repo->one(MessageReport::class, ['message' => $message, 'reporter' => $reporter, 'status' => 'open'])) {
            throw new \DomainException('Ce message est déjà signalé.');
        }
        $report = new MessageReport();
        $report->message = $message;
        $report->reporter = $reporter;
        $report->reason = $reason;
        $report->createdAt = $this->clock->now();
        $this->manager->save($report);
        $this->manager->flush();
    }


    public function recordReportViews(Player $admin, array $reports): void
    {
        foreach ($reports as $report) {
            if (!$report instanceof MessageReport || $report->status !== 'open') { continue; }
            $this->audit($admin, 'message_report_view', 'message_report', $report->id, ['message_id' => $report->message->id]);
        }
        if ($reports !== []) { $this->manager->flush(); }
    }

    public function reviewReport(Player $admin, int $reportId, string $decision): void
    {
        if (!in_array($decision, ['dismiss', 'redact'], true)) {
            throw new \DomainException('Décision de modération inconnue.');
        }
        $this->manager->transaction(function () use ($admin, $reportId, $decision): void {
            $report = $this->repo->find(MessageReport::class, $reportId);
            if (!$report || $report->status !== 'open') {
                throw new \DomainException('Signalement déjà traité ou introuvable.');
            }
            $this->manager->lock($report);
            $report->status = $decision === 'redact' ? 'actioned' : 'dismissed';
            $report->reviewedBy = $admin;
            $report->reviewedAt = $this->clock->now();
            if ($decision === 'redact') {
                $report->message->content = '[Message masqué après modération]';
                $report->message->moderatedAt = $this->clock->now();
                $report->message->moderatedBy = $admin;
            }
            $this->audit($admin, 'message_report_'.$decision, 'message_report', $reportId, ['message_id' => $report->message->id]);
            $this->manager->flush();
        });
    }

    public function setPlayerSuspended(Player $admin, int $targetId, bool $suspended): void
    {
        $this->manager->transaction(function () use ($admin, $targetId, $suspended): void {
            $target = $this->repo->find(Player::class, $targetId);
            if (!$target) {
                throw new \DomainException('Joueur introuvable.');
            }
            if ($target->id === $admin->id || in_array('ROLE_ADMIN', $target->getRoles(), true)) {
                throw new \DomainException('Un administrateur ne peut pas suspendre son propre compte ni un autre administrateur.');
            }
            $target->lockedAt = $suspended ? $this->clock->now() : null;
            $this->audit($admin, $suspended ? 'player_suspend' : 'player_reactivate', 'player', $targetId, []);
            $this->manager->flush();
        });
    }

    public function setUniverseSchedule(Player $admin, int $universeId, ?\DateTimeImmutable $startsAt, ?\DateTimeImmutable $endsAt): void
    {
        $this->manager->transaction(function () use ($admin, $universeId, $startsAt, $endsAt): void {
            $universe = $this->repo->find(Universe::class, $universeId);
            if (!$universe instanceof Universe) { throw new \DomainException('Univers introuvable.'); }
            $this->manager->lock($universe);
            if ($universe->status === 'closed') { throw new \DomainException('Un univers fermé ne peut pas être réouvert.'); }
            if ($startsAt !== null && $endsAt !== null && $endsAt <= $startsAt) { throw new \DomainException('La fermeture doit être postérieure à l’ouverture.'); }
            $this->lifecycle->setEndDate($universeId, $endsAt);
            $universe->startsAt = $startsAt;
            $this->audit($admin, 'universe_schedule_change', 'universe', $universeId, ['starts_at' => $startsAt?->format(DATE_ATOM), 'ends_at' => $endsAt?->format(DATE_ATOM)]);
            $this->manager->flush();
        });
    }


    public function setUniverseSettings(Player $admin, int $universeId, array $input): void
    {
        $multiplierKeys = ['building_time', 'research_time', 'ship_build_time', 'resource_production', 'travel_time'];
        $multipliers = [];
        foreach ($multiplierKeys as $key) {
            $value = filter_var($input['multipliers'][$key] ?? null, FILTER_VALIDATE_FLOAT);
            if ($value === false || !is_finite((float) $value) || $value < 0.01 || $value > 10) {
                throw new \DomainException('Chaque multiplicateur doit être compris entre 0,01 et 10.');
            }
            $multipliers[$key] = (float) $value;
        }
        $maxPlanets = filter_var($input['limits']['max_planets'] ?? null, FILTER_VALIDATE_INT);
        $maxFleet = filter_var($input['limits']['max_fleet_size'] ?? null, FILTER_VALIDATE_INT);
        if ($maxPlanets === false || $maxPlanets < 1 || $maxPlanets > 50 || $maxFleet === false || $maxFleet < 1 || $maxFleet > 10000) {
            throw new \DomainException('Les limites doivent rester dans les bornes autorisées.');
        }
        $settings = [
            'multipliers' => $multipliers,
            'limits' => ['max_planets' => $maxPlanets, 'max_fleet_size' => $maxFleet],
            'features' => [
                'guilds_enabled' => filter_var($input['features']['guilds_enabled'] ?? false, FILTER_VALIDATE_BOOL),
                'planet_conquest' => filter_var($input['features']['planet_conquest'] ?? false, FILTER_VALIDATE_BOOL),
            ],
        ];
        $this->manager->transaction(function () use ($admin, $universeId, $settings): void {
            $universe = $this->repo->find(Universe::class, $universeId);
            if (!$universe instanceof Universe || $universe->status === 'closed') {
                throw new \DomainException('Univers introuvable ou fermé.');
            }
            $this->manager->lock($universe);
            $universe->gameSettings = $settings;
            $this->audit($admin, 'universe_settings_change', 'universe', $universeId, $settings);
            $this->manager->flush();
        });
    }

    private function audit(Player $actor, string $action, string $targetType, int $targetId, array $details): void
    {
        $entry = new AdminAuditLog();
        $entry->actor = $actor;
        $entry->action = $action;
        $entry->targetType = $targetType;
        $entry->targetId = $targetId;
        $entry->details = $details;
        $entry->createdAt = $this->clock->now();
        $this->manager->save($entry);
    }
}
