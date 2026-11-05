<?php

namespace App\Service\Event;

use App\Entity\{AdminAuditLog, GameEvent, Player, PlayerUniverse};
use App\Manager\GameManager;
use App\Repository\GameRepository;
use Psr\Clock\ClockInterface;

final class GameEventService
{
    public const TYPES = ['production_bonus', 'construction_speed', 'research_speed', 'fleet_speed', 'special', 'reward', 'announcement'];
    public const STATUSES = ['draft', 'planned', 'active', 'finished', 'cancelled'];

    public function __construct(private GameRepository $repo, private GameManager $manager, private ClockInterface $clock) {}

    public function save(Player $actor, ?int $id, array $data): GameEvent
    {
        $event = $id === null ? new GameEvent() : $this->repo->find(GameEvent::class, $id);
        if (!$event instanceof GameEvent) throw new \DomainException('Événement introuvable.');
        $previousStatus = $event->status;
        $name = trim((string) ($data['name'] ?? ''));
        $description = trim((string) ($data['description'] ?? ''));
        $type = (string) ($data['type'] ?? '');
        $status = (string) ($data['status'] ?? 'draft');
        $start = $this->date((string) ($data['starts_at'] ?? ''));
        $end = $this->date((string) ($data['ends_at'] ?? ''));
        if ($name === '' || mb_strlen($name) > 120) throw new \DomainException('Le nom est obligatoire (120 caractères maximum).');
        if (!in_array($type, self::TYPES, true) || !in_array($status, self::STATUSES, true)) throw new \DomainException('Type ou statut invalide.');
        if ($end <= $start) throw new \DomainException('La date de fin doit être après la date de début.');
        $parameters = $data['parameters'] ?? [];
        if (!is_array($parameters)) throw new \DomainException('Paramètres invalides.');
        if ($type === 'production_bonus' && (!isset($parameters['percentage']) || !is_numeric($parameters['percentage']) || (float) $parameters['percentage'] <= 0 || (float) $parameters['percentage'] > 500)) {
            throw new \DomainException('Le bonus de production doit être compris entre 0 et 500 %.');
        }
        $universeId = (int) ($data['universe_id'] ?? 0);
        $universe = $universeId > 0 ? $this->repo->find(\App\Entity\Universe::class, $universeId) : null;
        if ($universeId > 0 && !$universe instanceof \App\Entity\Universe) throw new \DomainException('Univers introuvable.');
        $event->name = $name;
        $event->description = $description;
        $event->type = $type;
        $event->status = $status;
        $event->startsAt = $start;
        $event->endsAt = $end;
        $event->universe = $universe;
        $event->parameters = $parameters;
        $event->visibleToPlayers = (bool) ($data['visible'] ?? false);
        $this->manager->save($event);
        $this->manager->flush();
        $action = $id === null ? 'game_event_created' : ($previousStatus !== 'active' && $status === 'active' ? 'game_event_activated' : 'game_event_updated');
        $this->audit($actor, $action, $event);
        $this->manager->flush();
        return $event;
    }

    public function cancel(Player $actor, int $id): void
    {
        $event = $this->repo->find(GameEvent::class, $id);
        if (!$event instanceof GameEvent) throw new \DomainException('Événement introuvable.');
        $event->status = 'cancelled';
        $this->audit($actor, 'game_event_cancelled', $event);
        $this->manager->flush();
    }

    public function deleteDraft(Player $actor, int $id): void
    {
        $event = $this->repo->find(GameEvent::class, $id);
        if (!$event instanceof GameEvent || $event->status !== 'draft') throw new \DomainException('Seul un brouillon peut être supprimé.');
        $this->audit($actor, 'game_event_deleted', $event);
        $this->manager->remove($event);
        $this->manager->flush();
    }

    /** @return GameEvent[] */
    public function visibleFor(PlayerUniverse $membership): array
    {
        return $this->repo->visibleGameEvents($membership, $this->clock->now());
    }

    public function productionMultiplier(\App\Entity\Universe $universe, ?\DateTimeImmutable $at = null): float
    {
        $at ??= $this->clock->now();
        $multiplier = 1.0;
        foreach ($this->repo->all(GameEvent::class, ['type' => 'production_bonus']) as $event) {
            if (!in_array($event->status, ['active', 'planned'], true) || ($event->universe !== null && $event->universe->id !== $universe->id) || $event->startsAt > $at || $event->endsAt <= $at) continue;
            $multiplier *= 1 + ((float) ($event->parameters['percentage'] ?? 0) / 100);
        }
        return $multiplier;
    }

    /** @return list<\DateTimeImmutable> */
    public function productionChangePoints(\App\Entity\Universe $universe, \DateTimeImmutable $after, \DateTimeImmutable $before): array
    {
        $points = [];
        foreach ($this->repo->all(GameEvent::class, ['type' => 'production_bonus']) as $event) {
            if (!in_array($event->status, ['active', 'planned'], true) || ($event->universe !== null && $event->universe->id !== $universe->id)) continue;
            foreach ([$event->startsAt, $event->endsAt] as $point) if ($point > $after && $point < $before) $points[$point->getTimestamp()] = $point;
        }
        ksort($points);
        return array_values($points);
    }

    private function date(string $raw): \DateTimeImmutable
    {
        if (trim($raw) === '') throw new \DomainException('Les dates de début et de fin sont obligatoires.');
        try { return new \DateTimeImmutable($raw, new \DateTimeZone('UTC')); }
        catch (\Throwable) { throw new \DomainException('Date d’événement invalide.'); }
    }

    private function audit(Player $actor, string $action, GameEvent $event): void
    {
        $log = new AdminAuditLog(); $log->actor = $actor; $log->action = $action; $log->targetType = 'game_event';
        $log->targetId = $event->id ?? 0; $log->details = ['name' => $event->name, 'status' => $event->status]; $log->createdAt = $this->clock->now();
        $this->manager->save($log);
    }
}
