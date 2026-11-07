<?php
namespace App\Service\Boost;

use App\Entity\BoostEvent;
use App\Entity\BoostEventContribution;
use App\Entity\PlayerUniverse;
use App\Entity\SolarSystem;
use App\Entity\Universe;
use App\Manager\GameManager;
use App\Repository\GameRepository;
use App\Service\Catalog\GameCatalog;
use Psr\Clock\ClockInterface;

final class BoostEventService
{
    public function __construct(
        private GameManager $manager,
        private GameRepository $repo,
        private GameCatalog $catalog,
        private BoostGrantService $grants,
        private ClockInterface $clock,
    ) {}

    public function tick(): array
    {
        return $this->manager->transaction(function (): array {
            $now = $this->clock->now()->setTimezone(new \DateTimeZone('UTC'));
            $this->scheduleWeeklyInstances($now);
            $closed = 0;
            foreach ($this->repo->boostEvents('active') as $event) {
                if ($event->endsAt && $event->endsAt <= $now) {
                    $this->manager->lock($event);
                    if ($event->status === 'active' && $event->endsAt && $event->endsAt <= $now) {
                        $this->close($event, $now);
                        $this->manager->flush();
                        ++$closed;
                    }
                }
            }

            $started = 0;
            $settings = $this->eventSettings();
            $limit = max(1, min(20, (int) ($settings['max_concurrent'] ?? 2)));
            foreach ($this->repo->activeUniverses() as $universe) {
                foreach ($this->repo->boostEventsForUniverse('scheduled', $universe) as $event) {
                    if ($event->scheduledAt > $now) {
                        break;
                    }
                    if (count($this->repo->boostEventsForUniverse('active', $universe)) >= $limit) {
                        break;
                    }
                    $this->manager->lock($event);
                    if ($event->status !== 'scheduled' || $event->scheduledAt > $now) {
                        continue;
                    }
                    $event->status = 'active';
                    $event->startedAt = $now;
                    $event->endsAt = $now->modify('+' . $event->durationSeconds . ' seconds');
                    $this->manager->flush();
                    ++$started;
                }
            }

            return ['started' => $started, 'closed' => $closed];
        });
    }

    public function recordExplorationDiscovery(PlayerUniverse $membership, SolarSystem $system): int
    {
        if ($system->galaxy->universe->id !== $membership->universe->id) {
            return 0;
        }

        return $this->recordForActiveTemplate($membership, 'exploration', 'new_systems_discovered', 'discovery-' . $system->id, 1.0);
    }

    public function recordCombatDamage(PlayerUniverse $membership, int $combatEventId, int $roundNumber, float $damage): int
    {
        return $this->recordForActiveTemplate($membership, 'combat', 'damage_dealt', 'combat-' . $combatEventId . '-round-' . $roundNumber, $damage);
    }

    public function recordTransportDelivery(PlayerUniverse $membership, int $fleetId, float $deliveredAmount): int
    {
        return $this->recordForActiveTemplate($membership, 'transport', 'freight_delivered', 'fleet-' . $fleetId, $deliveredAmount);
    }

    private function recordForActiveTemplate(PlayerUniverse $membership, string $templateKey, string $metric, string $sourceKey, float $value): int
    {
        if (!is_finite($value) || $value <= 0 || $value > 1.0e12) {
            return 0;
        }

        return $this->manager->transaction(function () use ($membership, $templateKey, $metric, $sourceKey, $value): int {
            $count = 0;
            foreach ($this->repo->activeBoostEventsFor($membership, $templateKey, $this->clock->now()) as $event) {
                $this->manager->lock($event);
                if ($event->status !== 'active' || $event->contributionMetric !== $metric || !$event->startedAt || !$event->endsAt || $this->clock->now() < $event->startedAt || $this->clock->now() >= $event->endsAt) {
                    continue;
                }
                $contributionKey = 'm' . $membership->id . '-' . $sourceKey;
                if ($this->repo->boostEventContributionExists($event, $contributionKey)) {
                    continue;
                }
                $contribution = new BoostEventContribution();
                $contribution->event = $event;
                $contribution->playerUniverse = $membership;
                $contribution->sourceKey = $contributionKey;
                $contribution->value = $value;
                $event->totalContribution += $value;
                $this->manager->save($contribution);
                $this->manager->flush();
                ++$count;
            }

            return $count;
        });
    }

    private function scheduleWeeklyInstances(\DateTimeImmutable $now): void
    {
        $settings = $this->eventSettings();
        $templates = array_values(array_filter($settings['weekly_rotation'] ?? [], static fn (mixed $row): bool => is_array($row) && !empty($row['key'])));
        if ($templates === []) {
            return;
        }
        $anchor = (new \DateTimeImmutable((string) ($settings['first_occurrence_at'] ?? 'next monday 00:00 UTC'), new \DateTimeZone('UTC')))->setTimezone(new \DateTimeZone('UTC'))->setTime(0, 0);
        $monday = $now->modify('monday this week')->setTime(0, 0);
        if ($anchor > $now || $monday < $anchor) {
            return;
        }
        $weekIndex = intdiv($monday->getTimestamp() - $anchor->getTimestamp(), 604800);
        $template = $templates[$weekIndex % count($templates)];
        foreach ($this->repo->activeUniverses() as $universe) {
            $key = 'weekly-' . $monday->format('Ymd') . '-u' . $universe->id . '-' . $template['key'];
            if ($this->repo->boostEventByKey($key)) {
                continue;
            }
            $duration = max(60, min(2592000, (int) ($template['duration_seconds'] ?? $settings['default_duration_seconds'] ?? 604800)));
            $event = new BoostEvent();
            $event->eventKey = $key;
            $event->templateKey = (string) $template['key'];
            $event->universe = $universe;
            $event->status = 'scheduled';
            $event->format = (string) ($template['format'] ?? 'individual');
            $event->rewardMode = (string) ($template['reward_mode'] ?? 'all_contributors');
            $event->contributionMetric = (string) ($template['contribution_metric'] ?? '');
            $event->durationSeconds = $duration;
            $event->scheduledAt = $monday;
            $event->thresholdValue = isset($template['threshold_value']) ? (float) $template['threshold_value'] : null;
            $event->globalGoal = isset($template['global_goal']) ? (float) $template['global_goal'] : null;
            $this->validateDefinition($event);
            $this->manager->save($event);
        }
        $this->manager->flush();
    }

    private function close(BoostEvent $event, \DateTimeImmutable $closedAt): void
    {
        $event->status = 'closed';
        $event->closedAt = $closedAt;
        $rows = $this->repo->boostEventContributions($event);
        $scores = [];
        foreach ($rows as $row) {
            $membershipId = $row->playerUniverse->id;
            $scores[$membershipId] ??= ['membership' => $row->playerUniverse, 'value' => 0.0];
            $scores[$membershipId]['value'] += $row->value;
        }

        $winners = match ($event->rewardMode) {
            'all_contributors' => array_values(array_filter($scores, static fn (array $score): bool => $score['value'] > 0)),
            'threshold' => array_values(array_filter($scores, static fn (array $score): bool => $score['value'] >= (float) $event->thresholdValue)),
            'top3' => $this->topThree($scores, $event),
            default => [],
        };
        foreach ($winners as $winner) {
            $this->grants->awardEvent($winner['membership'], $event->eventKey);
        }
    }

    private function topThree(array $scores, BoostEvent $event): array
    {
        if ($scores === [] || $event->globalGoal === null || $event->totalContribution < $event->globalGoal) {
            return [];
        }
        usort($scores, static fn (array $a, array $b): int => $b['value'] <=> $a['value']);
        $cutoff = $scores[min(2, count($scores) - 1)]['value'];

        return array_values(array_filter($scores, static fn (array $score): bool => $score['value'] >= $cutoff));
    }

    private function validateDefinition(BoostEvent $event): void
    {
        if (!in_array($event->format, ['individual', 'collective', 'competitive'], true)
            || !in_array($event->rewardMode, ['top3', 'threshold', 'all_contributors'], true)
            || $event->contributionMetric === ''
            || ($event->rewardMode === 'threshold' && ($event->thresholdValue === null || $event->thresholdValue <= 0))
            || ($event->rewardMode === 'top3' && ($event->globalGoal === null || $event->globalGoal <= 0))) {
            throw new LogicException('Définition d’événement de boost invalide : ' . $event->templateKey);
        }
    }

    private function eventSettings(): array
    {
        $settings = $this->catalog->all('boosts')['events'] ?? [];
        if (($settings['timezone'] ?? 'UTC') !== 'UTC' || strtolower((string) ($settings['weekday'] ?? 'monday')) !== 'monday' || ($settings['start_time'] ?? '00:00') !== '00:00') {
            throw new LogicException('Les événements de boost doivent démarrer le lundi à 00:00 UTC.');
        }

        return $settings;
    }
}
