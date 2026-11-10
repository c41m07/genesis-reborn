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
        if (!empty($event->parameters['archived_at'])) throw new \DomainException('Cet événement est archivé et conservé en lecture seule.');
        if ($id !== null && !empty($event->parameters['guided_key'])) throw new \DomainException('Un événement guidé publié garde sa définition annoncée. Annulez-le puis préparez un nouvel aperçu pour le remplacer.');
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
        unset($parameters['production_history']);
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

    public function cancel(Player $actor, int $id, string $reason = '', bool $confirmed = false): void
    {
        $event = $this->repo->find(GameEvent::class, $id);
        if (!$event instanceof GameEvent) throw new \DomainException('Événement introuvable.');
        if (!empty($event->parameters['guided_key'])) {
            if (!$confirmed) throw new \DomainException('Confirmez explicitement l’annulation de cet événement.');
            if (!in_array('ROLE_ADMIN',$actor->getRoles(),true) || $actor->lockedAt) throw new \DomainException('Droits administrateur requis.');
            $reason=trim($reason);
            if ($reason==='' || mb_strlen($reason)>1000) throw new \DomainException('Motif d’annulation obligatoire, limité à 1 000 caractères.');
            $this->manager->transaction(function () use ($actor,$event,$reason): void {
                $universes=$event->universe ? [$event->universe] : $this->repo->all(\App\Entity\Universe::class,[],['id'=>'ASC']);
                foreach ($universes as $universe) $this->manager->lock($universe);
                $this->manager->lock($event);
                if ($event->status==='cancelled') return;
                if ($event->endsAt<=$this->clock->now()) throw new \DomainException('L’événement est déjà terminé.');
                if ($event->type==='production_bonus' && in_array($event->status,['planned','active'],true) && $event->startsAt<$this->clock->now()) {
                    $event->parameters['production_history']=[['from'=>$event->startsAt->format(DATE_ATOM),'until'=>$this->clock->now()->format(DATE_ATOM),
                        'percentage'=>(float)$event->parameters['percentage'],'universe_id'=>$event->universe?->id]];
                }
                $event->status='cancelled';$this->audit($actor,'game_event_cancelled',$event,['reason'=>$reason]);
            });
            return;
        }
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
            foreach ($this->history($event) as $segment) if (($segment['universe_id']===null || $segment['universe_id']===$universe->id) && $segment['from']<=$at && $segment['until']>$at) $multiplier*=1+$segment['percentage']/100;
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
            foreach ($this->history($event) as $segment) {
                if ($segment['universe_id']!==null && $segment['universe_id']!==$universe->id) continue;
                foreach ([$segment['from'],$segment['until']] as $point) if ($point>$after && $point<$before) $points[$point->getTimestamp()]=$point;
            }
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

    private function history(GameEvent $event): array
    {
        if (empty($event->parameters['guided_key'])) return [];
        $segments=[];
        foreach ($event->parameters['production_history'] ?? [] as $row) {
            try {
                $from=new \DateTimeImmutable($row['from']);$until=new \DateTimeImmutable($row['until']);$percentage=(float)$row['percentage'];
                if ($until>$from && is_finite($percentage) && $percentage>0 && $percentage<=500) $segments[]=['from'=>$from,'until'=>$until,'percentage'=>$percentage,'universe_id'=>isset($row['universe_id'])?(int)$row['universe_id']:null];
            } catch (\Throwable) { continue; }
        }
        return $segments;
    }

    private function audit(Player $actor, string $action, GameEvent $event, array $details = []): void
    {
        $log = new AdminAuditLog(); $log->actor = $actor; $log->action = $action; $log->targetType = 'game_event';
        $log->targetId = $event->id ?? 0; $log->details = ['name' => $event->name, 'status' => $event->status]+$details; $log->createdAt = $this->clock->now();
        $this->manager->save($log);
    }
}
