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
use App\Entity\PlanetNpcShip;
use App\Entity\PlayerTechnology;
use App\Service\Universe\PlanetNpcSeeder;
use App\Entity\PlayerDiscovery;
use App\Entity\PlayerUniverse;
use App\Manager\GameManager;
use App\Repository\GameRepository;
use App\Service\Catalog\GameCatalog;
use App\Service\Colony\ResourceService;
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
        private ResourceService $resources,
        private CombatSimulator $simulator,
        private TechnologyEffectCalculator $technologyEffects,
        private RelationResolver $relations,
        private NotificationService $notifications,
        private ClockInterface $clock,
        private PlanetNpcSeeder $npcSeeder,
    ) {}

    public function canAttack(PlayerUniverse $attacker, Planet $target): bool
    {
        return $target->owner !== null
            && $target->owner->universe->id === $attacker->universe->id
            && !$this->relations->allied($attacker, $target->owner);
    }

    public function canConquer(PlayerUniverse $attacker, ?Planet $target): bool
    {
        return $target !== null
            && $target->owner === null
            && $target->npcPlanet
            && $target->solarSystem->galaxy->universe->id === $attacker->universe->id
            && (bool) ($this->catalog->get('universes', $attacker->universe->slug)['features']['planet_conquest'] ?? false);
    }

    public function canDefend(PlayerUniverse $defender, ?Planet $target): bool
    {
        return $target !== null && $target->owner !== null
            && $target->owner->universe->id === $defender->universe->id;
    }

    /** Place une flotte en défense pour une fenêtre, ou l'ajoute au combat déjà ouvert. */
    public function arriveDefender(Fleet $fleet): bool
    {
        $planet = $fleet->destination;
        if (!$planet || !$planet->owner || !$this->canDefend($fleet->playerUniverse, $planet)) {
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
        if ($event) {
            $participant = $this->repo->one(CombatParticipant::class, ['combatEvent' => $event, 'fleet' => $fleet]);
            if (!$participant) {
                $participant = new CombatParticipant();
                $participant->combatEvent = $event;
                $participant->fleet = $fleet;
                $participant->playerUniverse = $fleet->playerUniverse;
                $participant->role = 'defender';
                $participant->arrivalAt = $now;
                $participant->arrivalRound = $this->arrivalRound($event, $now);
                $this->manager->save($participant);
                $this->notifyReinforcement($planet, $fleet);
            }
            $fleet->status = 'defending';
            $fleet->arrivalAt = null;
            $fleet->dispatchToken = Uuid::v4()->toRfc4122();
            return true;
        }
        if ($events !== []) {
            return false;
        }
        $window = max(1, (int) ($this->catalog->rules()['combat_window_seconds'] ?? 600));
        $fleet->status = 'defending';
        $fleet->departureAt = $now;
        $fleet->arrivalAt = $now->modify('+' . $window . ' seconds');
        $fleet->dispatchToken = Uuid::v4()->toRfc4122();
        $outbox = new OutboxMessage();
        $outbox->kind = 'fleet';
        $outbox->targetId = $fleet->id;
        $outbox->token = $fleet->dispatchToken;
        $outbox->dueAt = $fleet->arrivalAt;
        $this->manager->save($outbox);
        return true;
    }

    public function canOrbit(PlayerUniverse $sender, ?Planet $target): bool
    {
        return $target !== null && $target->owner !== null
            && $target->owner->id !== $sender->id
            && $target->owner->universe->id === $sender->universe->id
            && $this->relations->allied($sender, $target->owner);
    }

    public function orbitAutoAccepted(PlayerUniverse $sender, ?Planet $target): bool
    {
        if (!$this->canOrbit($sender, $target)) {
            return false;
        }
        $contract = $this->relations->contract($sender, $target->owner);
        return $contract?->status === 'accepted';
    }

    public function notifyOrbitRequest(Fleet $fleet): void
    {
        $planet = $fleet->destination;
        if (!$planet?->owner) {
            return;
        }
        $host = $planet->owner;
        $discovered = (bool) $this->repo->one(PlayerDiscovery::class, [
            'playerUniverse' => $host,
            'solarSystem' => $fleet->homePlanet->solarSystem,
        ]);
        $origin = $fleet->homePlanet->coordinates();
        $message = $discovered
            ? 'Demande d’orbite sur ' . $planet->name . ' par ' . $fleet->playerUniverse->player->username . ' depuis ' . $origin . '.'
            : 'Demande d’orbite sur ' . $planet->name . ' depuis ' . $origin . ' ; identité à confirmer.';
        $this->notifications->enqueue($host, $message);
    }

    public function joinOpenDefense(Fleet $fleet): bool
    {
        $planet = $fleet->destination;
        if (!$planet) {
            return false;
        }
        foreach ($this->repo->all(CombatEvent::class, ['planet' => $planet, 'status' => 'pending'], ['id' => 'DESC']) as $event) {
            if ($event->resolveAt <= $this->clock->now()) {
                continue;
            }
            $existing = $this->repo->one(CombatParticipant::class, ['combatEvent' => $event, 'fleet' => $fleet]);
            if (!$existing) {
                $participant = new CombatParticipant();
                $participant->combatEvent = $event;
                $participant->fleet = $fleet;
                $participant->playerUniverse = $fleet->playerUniverse;
                $participant->role = 'defender';
                $participant->arrivalAt = $this->clock->now();
                $participant->arrivalRound = $this->arrivalRound($event, $participant->arrivalAt);
                $this->manager->save($participant);
                $this->notifyReinforcement($planet, $fleet);
            }
            $fleet->status = 'defending';
            $fleet->arrivalAt = null;
            $fleet->dispatchToken = Uuid::v4()->toRfc4122();
            return true;
        }
        return false;
    }

    /** Enregistre une arrivée d'attaque dans une fenêtre ouverte ou en crée une. */
    public function joinAttack(Fleet $fleet): bool
    {
        $planet = $fleet->destination;
        if (!$planet) {
            return false;
        }
        if ($fleet->mission === 'conquer') {
            if (!$this->canConquer($fleet->playerUniverse, $planet)) return false;
        } elseif (!$planet->owner || !$this->canAttack($fleet->playerUniverse, $planet)) {
            return false;
        }

        $this->manager->lock($planet);
        if ($fleet->mission === 'conquer') {
            if (!$this->canConquer($fleet->playerUniverse, $planet)) return false;
            $this->npcSeeder->seed($planet);
        }
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
            if ($planet->owner) {
                $this->registerGarrison($event, $planet->owner);
                $this->notifyAttack($planet, $fleet);
            }
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
            $participants = $this->repo->all(CombatParticipant::class, ['combatEvent' => $event], ['id' => 'ASC']);
            $conquestOwners = [];
            foreach ($participants as $participant) {
                if ($participant->role === 'attacker' && $participant->fleet->mission === 'conquer') {
                    $conquestOwners[$participant->playerUniverse->id] = $participant->playerUniverse;
                }
            }
            ksort($conquestOwners, SORT_NUMERIC);
            foreach ($conquestOwners as $owner) {
                $this->manager->lock($owner);
            }
            $this->manager->lock($event->planet);
            if ($participants === []) {
                $event->status = 'resolved';
                return;
            }

            $attackerParticipants = array_values(array_filter($participants, static fn (CombatParticipant $p): bool => $p->role === 'attacker'));
            $campByMembership = $this->attackerCamps($attackerParticipants);
            $snapshot = [];
            $combatParticipants = [];
            $technologyLevels = [];
            foreach ($participants as $participant) {
                $fleet = $participant->fleet;
                $membershipId = $participant->playerUniverse->id;
                $technologyLevels[$membershipId] ??= $this->repo->levels(PlayerTechnology::class, ['playerUniverse' => $participant->playerUniverse]);
                $units = [];
                foreach ($this->repo->all(FleetShip::class, ['fleet' => $fleet], ['id' => 'ASC']) as $fleetShip) {
                    if ($fleetShip->quantity < 1) {
                        continue;
                    }
                    $units[] = $this->catalog->get('ships', $fleetShip->slug) + ['quantity' => $fleetShip->quantity];
                }
                $units = $this->technologyEffects->applyToShips($units, $technologyLevels[$membershipId]);
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
                    'technology_levels' => $technologyLevels[$membershipId],
                ];
            }

            $npcShips = $this->repo->all(PlanetNpcShip::class, ['planet' => $event->planet], ['id' => 'ASC']);
            $npcUnits = [];
            foreach ($npcShips as $npcShip) {
                if ($npcShip->quantity > 0) $npcUnits[] = $this->catalog->get('ships', $npcShip->slug) + ['quantity' => $npcShip->quantity];
            }
            if ($npcUnits !== []) {
                $npcResearch = $this->catalog->all('planet_conquest')['levels'][$event->planet->conquestLevel]['research'] ?? [];
                $npcUnits = $this->technologyEffects->applyToShips($npcUnits, $npcResearch);
                $npcId = 'npc:' . $event->planet->id;
                $combatParticipants[] = ['id' => $npcId, 'role' => 'defender', 'camp' => 'defenders', 'arrival_round' => 1, 'units' => $npcUnits];
                $snapshot[] = ['participant_id' => null, 'fleet_id' => null, 'player_universe_id' => null, 'role' => 'defender', 'camp' => 'defenders', 'arrival_at' => $event->startedAt->format(DATE_ATOM), 'arrival_round' => 1, 'units' => $npcUnits, 'technology_levels' => $npcResearch, 'pve_npc' => true, 'planet_id' => $event->planet->id];
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

            $this->persistLosses($participants, $defenseSnapshots, $result['losses'], $event->planet);
            $loot = $this->collectLoot($participants, $result['outcome'], $event);
            $capture = $this->capturePlanet($participants, $result['outcome'], $event, $result['losses']);
            foreach ($participants as $participant) {
                $this->consumeOrbitFuel($participant->fleet);
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
                'loot' => $loot,
                'capture' => $capture,
            ];
            $log->resolvedAt = $this->clock->now();
            $this->manager->save($log);
            $event->status = 'resolved';
        });
    }


    /** Répartit le butin de façon déterministe dans les soutes encore libres des attaquants survivants. */
    private function collectLoot(array $participants, string $outcome, CombatEvent $event): array
    {
        $rules = $this->catalog->rules();
        $ratio = max(0.0, min(1.0, (float) ($rules['combat_loot_ratio'] ?? 1.0)));
        $protectedRatio = max(0.0, min(1.0, (float) ($rules['combat_loot_protected_storage_ratio'] ?? 0.1)));
        $result = ['ratio' => $ratio, 'protected_storage_ratio' => $protectedRatio, 'fleets' => [], 'total' => []];
        if ($outcome !== 'attacker_wins' || !$event->planet->owner) {
            return $result;
        }

        $fleets = [];
        foreach ($participants as $participant) {
            if ($participant->role !== 'attacker') {
                continue;
            }
            $capacity = 0.0;
            foreach ($this->repo->all(FleetShip::class, ['fleet' => $participant->fleet], ['id' => 'ASC']) as $ship) {
                if ($ship->quantity < 1) {
                    continue;
                }
                $type = $this->catalog->get('ships', $ship->slug);
                $capacity += (($type['cargo_capacity'] ?? 0) + ($type['fuel_capacity'] ?? 0)) * $ship->quantity;
            }
            $occupied = array_sum(array_map(static fn ($amount): float => max(0.0, (float) $amount), $participant->fleet->cargo));
            $space = max(0, (int) floor($capacity - $occupied));
            if ($space > 0) {
                $fleets[(int) $participant->fleet->id] = ['fleet' => $participant->fleet, 'space' => $space];
            }
        }
        if ($fleets === []) {
            return $result;
        }

        ksort($fleets, SORT_NUMERIC);
        $rows = $this->resources->materialize($event->planet, $this->clock->now());
        $storage = $this->resources->snapshot($event->planet);
        $available = [];
        foreach ($rows as $slug => $resource) {
            $protected = (float) ($storage[$slug]['capacity'] ?? 0) * $protectedRatio;
            $available[$slug] = max(0, (int) floor(max(0.0, $resource->currentStock - $protected) * $ratio));
        }
        $capacity = array_sum(array_column($fleets, 'space'));
        $availableTotal = array_sum($available);
        if ($availableTotal < 1 || $capacity < 1) {
            return $result;
        }

        $loadTotal = min($capacity, $availableTotal);
        $exact = [];
        $loaded = 0;
        foreach ($available as $slug => $amount) {
            $exact[$slug] = $availableTotal > $loadTotal ? $amount * $loadTotal / $availableTotal : (float) $amount;
            $available[$slug] = (int) floor($exact[$slug]);
            $loaded += $available[$slug];
        }
        $fractions = [];
        foreach ($available as $slug => $amount) {
            $fractions[$slug] = $exact[$slug] - $amount;
        }
        uksort($fractions, static fn (string $a, string $b): int => ($fractions[$b] <=> $fractions[$a]) ?: strcmp($a, $b));
        $left = $loadTotal - $loaded;
        foreach ($fractions as $slug => $_fraction) {
            if ($left < 1) {
                break;
            }
            if ($available[$slug] < (int) floor(max(0.0, $rows[$slug]->currentStock - (float) ($storage[$slug]['capacity'] ?? 0) * $protectedRatio) * $ratio)) {
                ++$available[$slug];
                --$left;
            }
        }

        foreach ($available as $slug => $amount) {
            if ($amount < 1) {
                continue;
            }
            $fractionsByFleet = [];
            $remainingSpace = array_sum(array_column($fleets, 'space'));
            if ($remainingSpace < 1) {
                break;
            }
            $assigned = 0;
            foreach ($fleets as $fleetId => $entry) {
                $share = $amount * $entry['space'] / $remainingSpace;
                $share = min($entry['space'], (int) floor($share));
                if ($share > 0) {
                    $fleets[$fleetId]['space'] -= $share;
                    $entry['fleet']->cargo[$slug] = ($entry['fleet']->cargo[$slug] ?? 0) + $share;
                    $result['fleets'][$fleetId][$slug] = ($result['fleets'][$fleetId][$slug] ?? 0) + $share;
                    $assigned += $share;
                }
                $fractionsByFleet[$fleetId] = $share < $entry['space'] ? ($amount * $entry['space'] / $remainingSpace - floor($amount * $entry['space'] / $remainingSpace)) : -1;
            }
            $remainder = $amount - $assigned;
            uksort($fractionsByFleet, static fn (int $a, int $b): int => ($fractionsByFleet[$b] <=> $fractionsByFleet[$a]) ?: ($a <=> $b));
            while ($remainder > 0) {
                $progress = false;
                foreach ($fractionsByFleet as $fleetId => $_fraction) {
                    if ($remainder < 1) {
                        break;
                    }
                    if ($fleets[$fleetId]['space'] < 1) {
                        continue;
                    }
                    --$fleets[$fleetId]['space'];
                    $fleets[$fleetId]['fleet']->cargo[$slug] = ($fleets[$fleetId]['fleet']->cargo[$slug] ?? 0) + 1;
                    $result['fleets'][$fleetId][$slug] = ($result['fleets'][$fleetId][$slug] ?? 0) + 1;
                    --$remainder;
                    $progress = true;
                }
                if (!$progress) {
                    break;
                }
            }
            $taken = $amount - $remainder;
            if ($taken > 0) {
                $rows[$slug]->currentStock = max(0, $rows[$slug]->currentStock - $taken);
                $result['total'][$slug] = $taken;
            }
        }

        return $result;
    }

    /** Résout le transfert de planète à partir des vaisseaux colonie survivants. */
    /** Prise d'une planète neutre après une victoire JcE et avec un vaisseau colonie survivant. */
    private function capturePlanet(array $participants, string $outcome, CombatEvent $event, array &$losses): ?array
    {
        $candidates = array_values(array_filter($participants, static fn (CombatParticipant $participant): bool =>
            $participant->role === 'attacker' && $participant->fleet->mission === 'conquer'
        ));
        if ($candidates === []) return null;
        if ($outcome !== 'attacker_wins') return ['status' => 'failed', 'reason' => 'attack_lost'];

        usort($candidates, static fn (CombatParticipant $a, CombatParticipant $b): int =>
            ($a->arrivalAt <=> $b->arrivalAt) ?: ($a->fleet->id <=> $b->fleet->id)
        );
        $reason = 'colony_ship_destroyed';
        foreach ($candidates as $participant) {
            $fleet = $participant->fleet;
            $colonyShip = $this->repo->one(FleetShip::class, ['fleet' => $fleet, 'slug' => 'colony_ship']);
            if (!$colonyShip || $colonyShip->quantity < 1) continue;
            if ($event->planet->owner !== null) { $reason = 'target_claimed'; continue; }
            $config = $this->catalog->get('universes', $participant->playerUniverse->universe->slug);
            if (count($this->repo->planets($participant->playerUniverse->universe, $participant->playerUniverse)) >= $config['limits']['max_planets']) {
                $reason = 'planet_limit'; continue;
            }

            --$colonyShip->quantity;
            $lossKey = $fleet->id . ':colony_ship';
            $losses[$lossKey] = ($losses[$lossKey] ?? 0) + 1;
            $event->planet->owner = $participant->playerUniverse;
            foreach ($this->repo->all(PlanetNpcShip::class, ['planet' => $event->planet]) as $npcShip) $this->manager->remove($npcShip);
            foreach ($this->repo->all(PlanetDefense::class, ['planet' => $event->planet]) as $defense) $this->manager->remove($defense);
            if ($this->repo->all(\App\Entity\PlanetResource::class, ['planet' => $event->planet]) === []) $this->resources->initialize($event->planet, true);
            $this->notifications->enqueue($participant->playerUniverse, 'Planète PNJ conquise : ' . $event->planet->name . '. Ses bâtiments et ressources sont conservés.');

            return ['status' => 'captured', 'reason' => null, 'fleet_id' => $fleet->id, 'captor_player_universe_id' => $participant->playerUniverse->id];
        }

        foreach ($candidates as $participant) {
            $message = match ($reason) {
                'planet_limit' => 'Combat gagné, mais la limite de colonies empêche la conquête.',
                'target_claimed' => 'Combat gagné, mais la planète a déjà été revendiquée.',
                default => 'Combat gagné, mais aucun vaisseau colonie n’a survécu pour prendre la planète.',
            };
            $this->notifications->enqueue($participant->playerUniverse, $message);
        }
        return ['status' => 'failed', 'reason' => $reason];
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

    private function persistLosses(array $participants, array $defenseSnapshots, array $losses, Planet $planet): void
    {
        foreach ($participants as $participant) {
            foreach ($this->repo->all(FleetShip::class, ['fleet' => $participant->fleet]) as $ship) {
                $lost = $losses[$participant->fleet->id . ':' . $ship->slug] ?? 0;
                $ship->quantity = max(0, $ship->quantity - $lost);
            }
        }
        foreach ($this->repo->all(PlanetNpcShip::class, ['planet' => $planet]) as $npcShip) {
            $lost = $losses['npc:' . $planet->id . ':' . $npcShip->slug] ?? 0;
            $npcShip->quantity = max(0, $npcShip->quantity - $lost);
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

    private function consumeOrbitFuel(Fleet $fleet): void
    {
        if ($fleet->orbitFuel <= 0) {
            return;
        }
        $fleet->cargo['hydrogen'] = max(0, ($fleet->cargo['hydrogen'] ?? 0) - $fleet->orbitFuel);
        $fleet->reservedFuel = max(0, $fleet->reservedFuel - $fleet->orbitFuel);
        $fleet->orbitFuel = 0;
    }

    private function sendFleetHome(CombatParticipant $participant): void
    {
        $fleet = $participant->fleet;
        if ($participant->role === 'defender' && $fleet->homePlanet->id === $participant->combatEvent->planet->id) {
            $capturedByAnotherCommander = $fleet->homePlanet->owner?->id !== $participant->playerUniverse->id;
            $fleet->status = $capturedByAnotherCommander ? 'completed' : 'idle';
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
        $waitingDefenders = [
            ...$this->repo->all(Fleet::class, ['destination' => $event->planet, 'status' => 'defending', 'mission' => 'defend'], ['id' => 'ASC']),
            ...$this->repo->all(Fleet::class, ['destination' => $event->planet, 'status' => 'orbiting', 'mission' => 'orbit'], ['id' => 'ASC']),
        ];
        foreach ($waitingDefenders as $fleet) {
            if (!$fleet->arrivalAt || $fleet->arrivalAt <= $this->clock->now()) {
                continue;
            }
            if ($this->repo->one(CombatParticipant::class, ['combatEvent' => $event, 'fleet' => $fleet])) {
                continue;
            }
            $participant = new CombatParticipant();
            $participant->combatEvent = $event;
            $participant->fleet = $fleet;
            $participant->playerUniverse = $fleet->playerUniverse;
            $participant->role = 'defender';
            $participant->arrivalAt = $this->clock->now();
            $participant->arrivalRound = $this->arrivalRound($event, $participant->arrivalAt);
            $this->manager->save($participant);
            $fleet->status = 'defending';
            $fleet->arrivalAt = null;
            $fleet->dispatchToken = Uuid::v4()->toRfc4122();
            $this->notifyReinforcement($event->planet, $fleet);
        }
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
