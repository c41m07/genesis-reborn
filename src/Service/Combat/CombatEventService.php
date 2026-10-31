<?php
namespace App\Service\Combat;

use App\Entity\AllianceContract;
use App\Entity\CombatEvent;
use App\Entity\CombatLog;
use App\Entity\CombatParticipant;
use App\Entity\Fleet;
use App\Entity\FleetShip;
use App\Entity\GuildMember;
use App\Entity\Notification;
use App\Entity\OutboxMessage;
use App\Entity\Planet;
use App\Entity\PlanetDefense;
use App\Entity\PlayerDiscovery;
use App\Entity\PlayerUniverse;
use App\Manager\GameManager;
use App\Repository\GameRepository;
use App\Service\Catalog\GameCatalog;
use App\Service\Notification\NotificationService;
use App\Service\Player\RelationResolver;
use Psr\Clock\ClockInterface;
use Symfony\Component\Uid\Uuid;

final class CombatEventService
{
    public function __construct(
        private GameManager $manager,
        private GameRepository $repo,
        private GameCatalog $catalog,
        private CombatSimulator $simulator,
        private RelationResolver $relations,
        private NotificationService $notifications,
        private ClockInterface $clock,
    ) {}

    public function canAttack(PlayerUniverse $attacker, Planet $target): bool
    {
        return $target->owner !== null
            && $target->owner->universe->id === $attacker->universe->id
            && !$this->relations->allied($attacker, $target->owner);
    }

    /** Enregistre une arrivée d'attaque dans une fenêtre ouverte ou en crée une. */
    public function joinAttack(Fleet $fleet): bool
    {
        $planet = $fleet->destination;
        if (!$planet || !$planet->owner || !$this->canAttack($fleet->playerUniverse, $planet)) {
            return false;
        }

        $this->manager->lock($planet);
        $now = $this->clock->now();
        $events = $this->repo->all(CombatEvent::class, ['planet' => $planet, 'status' => 'pending'], ['id' => 'DESC']);
        $event = null;
        foreach ($events as $candidate) {
            if ($candidate->resolveAt > $now) {
                $event = $candidate;
                break;
            }
        }
        $isNew = $event === null;
        if ($isNew) {
            // Une ancienne fenêtre échue reste à résoudre par son message idempotent.
            if ($events !== []) {
                return false;
            }
            $rules = $this->catalog->rules();
            $window = (int) ($rules['combat_window_seconds'] ?? 600);
            if ($window < 1) {
                throw new \LogicException('Fenêtre de combat invalide.');
            }
            $event = new CombatEvent();
            $event->planet = $planet;
            $event->status = 'pending';
            $event->startedAt = $fleet->arrivalAt ?? $now;
            $event->resolveAt = $event->startedAt->modify('+' . $window . ' seconds');
            $event->seed = random_int(0, 0xffffffff);
            $event->dispatchToken = Uuid::v4()->toRfc4122();
            $this->manager->save($event);
            $this->manager->flush();
        } else {
            $existing = $this->repo->one(CombatParticipant::class, ['combatEvent' => $event, 'fleet' => $fleet]);
            if ($existing) {
                $fleet->status = 'attacking';
                return true;
            }
        }

        $participant = new CombatParticipant();
        $participant->combatEvent = $event;
        $participant->fleet = $fleet;
        $participant->playerUniverse = $fleet->playerUniverse;
        $participant->role = 'attacker';
        $participant->arrivalAt = $fleet->arrivalAt ?? $now;
        $participant->arrivalRound = $isNew ? 1 : $this->arrivalRound($event, $participant->arrivalAt);
        $this->manager->save($participant);
        $fleet->status = 'attacking';

        if ($isNew) {
            $outbox = new OutboxMessage();
            $outbox->kind = 'combat';
            $outbox->targetId = $event->id;
            $outbox->token = $event->dispatchToken;
            $outbox->dueAt = $event->resolveAt;
            $this->manager->save($outbox);
            $this->registerGarrison($event, $planet->owner);
            $this->notifyAttack($planet, $fleet);
        } else {
            $this->notifyReinforcement($planet, $fleet);
        }

        return true;
    }

    public function resolve(int $eventId, string $token): void
    {
        $this->manager->transaction(function () use ($eventId, $token): void {
            $event = $this->repo->find(CombatEvent::class, $eventId);
            if (!$event) {
                return;
            }
            $this->manager->lock($event);
            if ($event->status !== 'pending' || !hash_equals($event->dispatchToken, $token) || $event->resolveAt > $this->clock->now()) {
                return;
            }
            $this->manager->lock($event->planet);
            $participants = $this->repo->all(CombatParticipant::class, ['combatEvent' => $event], ['id' => 'ASC']);
            if ($participants === []) {
                $event->status = 'resolved';
                return;
            }

            $attackerParticipants = array_values(array_filter($participants, static fn (CombatParticipant $p): bool => $p->role === 'attacker'));
            $campByMembership = $this->attackerCamps($attackerParticipants);
            $snapshot = [];
            $combatParticipants = [];
            foreach ($participants as $participant) {
                $fleet = $participant->fleet;
                $units = [];
                foreach ($this->repo->all(FleetShip::class, ['fleet' => $fleet], ['id' => 'ASC']) as $fleetShip) {
                    if ($fleetShip->quantity < 1) {
                        continue;
                    }
                    $units[] = $this->catalog->get('ships', $fleetShip->slug) + ['quantity' => $fleetShip->quantity];
                }
                $id = (string) $fleet->id;
                $role = $participant->role;
                $camp = $role === 'attacker' ? ($campByMembership[$participant->playerUniverse->id] ?? 'attacker-' . $participant->playerUniverse->id) : 'defenders';
                $combatParticipants[] = [
                    'id' => $id,
                    'role' => $role,
                    'camp' => $camp,
                    'arrival_round' => $participant->arrivalRound ?? 1,
                    'units' => $units,
                ];
                $snapshot[] = [
                    'participant_id' => $participant->id,
                    'fleet_id' => $fleet->id,
                    'player_universe_id' => $participant->playerUniverse->id,
                    'role' => $role,
                    'camp' => $camp,
                    'arrival_at' => $participant->arrivalAt->format(DATE_ATOM),
                    'arrival_round' => $participant->arrivalRound,
                    'units' => $units,
                ];
            }

            $defenseSnapshots = [];
            foreach ($this->repo->all(PlanetDefense::class, ['planet' => $event->planet], ['id' => 'ASC']) as $defense) {
                if ($defense->quantity < 1) {
                    continue;
                }
                $defenseSnapshots[] = $this->catalog->get('defenses', $defense->slug) + [
                    'id' => (string) $defense->id,
                    'camp' => 'defenders',
                    'quantity' => $defense->quantity,
                ];
            }
            $rules = $this->catalog->rules();
            $modifiers = $this->catalog->all('combat_modifiers')['modifiers'] ?? [];
            $result = $this->simulator->simulate(
                $combatParticipants,
                $defenseSnapshots,
                $modifiers,
                (int) ($rules['max_combat_rounds'] ?? 10),
                $event->seed,
            );

            $this->persistLosses($participants, $defenseSnapshots, $result['losses']);
            foreach ($participants as $participant) {
                $this->sendFleetHome($participant);
                $this->notifications->enqueue(
                    $participant->playerUniverse,
                    'Combat terminé sur ' . $event->planet->name . ' : ' . $this->outcomeLabel($result['outcome']) . '.',
                );
            }

            $log = new CombatLog();
            $log->combatEvent = $event;
            $log->outcome = $result['outcome'];
            $log->rounds = $result['rounds'];
            $log->snapshot = [
                'participants' => $snapshot,
                'defenses' => $defenseSnapshots,
                'modifiers' => $modifiers,
                'max_rounds' => (int) ($rules['max_combat_rounds'] ?? 10),
                'seed' => $event->seed,
                'survivors' => $result['survivors'],
                'losses' => $result['losses'],
            ];
            $log->resolvedAt = $this->clock->now();
            $this->manager->save($log);
            $event->status = 'resolved';
        });
    }

    private function arrivalRound(CombatEvent $event, \DateTimeImmutable $arrivalAt): int
    {
        $maxRounds = max(1, (int) ($this->catalog->rules()['max_combat_rounds'] ?? 10));
        $window = max(1, $event->resolveAt->getTimestamp() - $event->startedAt->getTimestamp());
        $elapsed = max(0, min($window, $arrivalAt->getTimestamp() - $event->startedAt->getTimestamp()));

        return max(1, min($maxRounds, 1 + (int) round(($elapsed / $window) * ($maxRounds - 1))));
    }

    private function attackerCamps(array $participants): array
    {
        $memberships = [];
        foreach ($participants as $participant) {
            $memberships[$participant->playerUniverse->id] = $participant->playerUniverse;
        }
        $ids = array_keys($memberships);
        $parent = [];
        foreach ($ids as $id) {
            $parent[$id] = $id;
        }
        $find = static function (int $id) use (&$parent, &$find): int {
            if ($parent[$id] !== $id) {
                $parent[$id] = $find($parent[$id]);
            }

            return $parent[$id];
        };
        for ($i = 0; $i < count($ids); ++$i) {
            for ($j = $i + 1; $j < count($ids); ++$j) {
                if ($this->relations->allied($memberships[$ids[$i]], $memberships[$ids[$j]])) {
                    $rootA = $find($ids[$i]);
                    $rootB = $find($ids[$j]);
                    $parent[$rootB] = $rootA;
                }
            }
        }
        $camps = [];
        foreach ($ids as $id) {
            $camps[$id] = 'attacker-' . $find($id);
        }

        return $camps;
    }

    private function persistLosses(array $participants, array $defenseSnapshots, array $losses): void
    {
        foreach ($participants as $participant) {
            foreach ($this->repo->all(FleetShip::class, ['fleet' => $participant->fleet]) as $ship) {
                $lost = $losses[$participant->fleet->id . ':' . $ship->slug] ?? 0;
                $ship->quantity = max(0, $ship->quantity - $lost);
            }
        }
        foreach ($defenseSnapshots as $snapshot) {
            $id = (int) $snapshot['id'];
            $defense = $this->repo->find(PlanetDefense::class, $id);
            if (!$defense) {
                continue;
            }
            $lost = $losses['planet:' . $id . ':' . $defense->slug] ?? 0;
            $defense->quantity = max(0, $defense->quantity - $lost);
        }
    }

    private function sendFleetHome(CombatParticipant $participant): void
    {
        $fleet = $participant->fleet;
        if ($participant->role === 'defender' && $fleet->homePlanet->id === $participant->combatEvent->planet->id) {
            $fleet->status = 'idle';
            $fleet->mission = 'idle';
            $fleet->destination = null;
            $fleet->destinationSystem = null;
            $fleet->departureAt = null;
            $fleet->arrivalAt = null;
            $fleet->cargo = [];
            $fleet->reservedFuel = 0;
            $fleet->dispatchToken = \Symfony\Component\Uid\Uuid::v4()->toRfc4122();

            return;
        }
        $survivors = 0;
        foreach ($this->repo->all(FleetShip::class, ['fleet' => $fleet]) as $ship) {
            $survivors += $ship->quantity;
        }
        if ($survivors === 0) {
            $fleet->status = 'completed';
            $fleet->arrivalAt = null;
            $fleet->cargo = [];
            $fleet->reservedFuel = 0;
            $fleet->dispatchToken = Uuid::v4()->toRfc4122();

            return;
        }

        $fleet->status = 'returning';
        $fleet->departureAt = $this->clock->now();
        $fleet->arrivalAt = $this->clock->now()->modify('+' . max(1, $fleet->travelSeconds) . ' seconds');
        $fleet->dispatchToken = Uuid::v4()->toRfc4122();
        $outbox = new OutboxMessage();
        $outbox->kind = 'fleet';
        $outbox->targetId = $fleet->id;
        $outbox->token = $fleet->dispatchToken;
        $outbox->dueAt = $fleet->arrivalAt;
        $this->manager->save($outbox);
    }

    private function registerGarrison(CombatEvent $event, PlayerUniverse $defender): void
    {
        foreach ($this->repo->all(Fleet::class, ['homePlanet' => $event->planet, 'playerUniverse' => $defender, 'status' => 'idle'], ['id' => 'ASC']) as $fleet) {
            $hasShips = false;
            foreach ($this->repo->all(FleetShip::class, ['fleet' => $fleet]) as $ship) {
                if ($ship->quantity > 0) {
                    $hasShips = true;
                    break;
                }
            }
            if (!$hasShips) {
                continue;
            }
            $participant = new CombatParticipant();
            $participant->combatEvent = $event;
            $participant->fleet = $fleet;
            $participant->playerUniverse = $defender;
            $participant->role = 'defender';
            $participant->arrivalAt = $event->startedAt;
            $participant->arrivalRound = 1;
            $fleet->status = 'defending';
            $fleet->mission = 'defend';
            $this->manager->save($participant);
        }
    }

    private function notifyAttack(Planet $planet, Fleet $fleet): void
    {
        $defender = $planet->owner;
        if (!$defender) {
            return;
        }
        $recipients = [$defender->id => $defender];
        $guildMembership = $this->repo->one(GuildMember::class, ['playerUniverse' => $defender]);
        if ($guildMembership) {
            foreach ($this->repo->all(GuildMember::class, ['guild' => $guildMembership->guild]) as $member) {
                $recipients[$member->playerUniverse->id] = $member->playerUniverse;
            }
        }
        foreach ([
            ...$this->repo->all(AllianceContract::class, ['playerA' => $defender, 'status' => 'accepted']),
            ...$this->repo->all(AllianceContract::class, ['playerB' => $defender, 'status' => 'accepted']),
        ] as $contract) {
            $ally = $contract->playerA->id === $defender->id ? $contract->playerB : $contract->playerA;
            $recipients[$ally->id] = $ally;
        }
        $discovered = (bool) $this->repo->one(PlayerDiscovery::class, [
            'playerUniverse' => $defender,
            'solarSystem' => $fleet->homePlanet->solarSystem,
        ]);
        $origin = $fleet->homePlanet->coordinates();
        $message = $discovered
            ? 'Attaque détectée sur ' . $planet->name . ' par ' . $fleet->playerUniverse->player->username . ' depuis ' . $origin . '.'
            : 'Attaque détectée sur ' . $planet->name . '. Origine repérée : ' . $origin . ' ; identité inconnue.';
        foreach ($recipients as $recipient) {
            if ($this->relations->allied($recipient, $fleet->playerUniverse)) {
                continue;
            }
            $this->notifications->enqueue($recipient, $message);
        }
    }

    private function notifyReinforcement(Planet $planet, Fleet $fleet): void
    {
        $defender = $planet->owner;
        if (!$defender) {
            return;
        }
        $discovered = (bool) $this->repo->one(PlayerDiscovery::class, [
            'playerUniverse' => $defender,
            'solarSystem' => $fleet->homePlanet->solarSystem,
        ]);
        $origin = $fleet->homePlanet->coordinates();
        $message = $discovered
            ? 'Renfort détecté par ' . $fleet->playerUniverse->player->username . ' depuis ' . $origin . '.'
            : 'Renfort détecté depuis ' . $origin . ' ; identité inconnue.';
        $this->notifications->enqueue($defender, $message);
    }

    private function outcomeLabel(string $outcome): string
    {
        return match ($outcome) {
            'attacker_wins' => 'victoire des attaquants',
            'defender_wins' => 'victoire de la défense',
            default => 'égalité',
        };
    }
}
