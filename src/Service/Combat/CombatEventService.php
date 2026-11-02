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
use App\Entity\PlanetBuilding;
use App\Entity\PlanetNpcShip;
use App\Entity\PlayerTechnology;
use App\Service\Universe\PlanetNpcSeeder;
use App\Service\Universe\PlanetNameGenerator;
use App\Entity\PlayerDiscovery;
use App\Entity\PlayerUniverse;
use App\Manager\GameManager;
use App\Repository\GameRepository;
use App\Service\Catalog\GameCatalog;
use App\Service\Boost\BoostGrantService;
use App\Service\Boost\BoostEventService;
use App\Service\Colony\ResourceService;
use App\Service\Notification\NotificationService;
use App\Service\Player\RelationResolver;
use App\Service\Player\LeaderboardService;
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
        private LeaderboardService $leaderboard,
        private NotificationService $notifications,
        private ClockInterface $clock,
        private PlanetNpcSeeder $npcSeeder,
        private PlanetNameGenerator $planetNames,
        private BoostGrantService $boostGrants,
        private BoostEventService $boostEvents,
    ) {}

    public function canAttack(PlayerUniverse $attacker, Planet $target): bool
    {
        $defender = $target->owner;
        if ($defender === null || $defender->universe->id !== $attacker->universe->id || $this->relations->allied($attacker, $defender)) return false;
        $rules = $this->catalog->rules();
        $now = $this->clock->now();
        if ($defender->recoveryProtectedUntil !== null && $defender->recoveryProtectedUntil > $now) return false;
        $scores = [];
        foreach ($this->leaderboard->forUniverse($attacker) as $row) $scores[$row['membership']->id] = (int) $row['total_points'];
        $attackerScore = $scores[$attacker->id] ?? 0;
        $defenderScore = $scores[$defender->id] ?? 0;
        $protection = $rules['new_player_protection'] ?? [];
        $duration = max(0, (int) ($protection['duration_seconds'] ?? 0));
        $scoreLimit = max(0, (int) ($protection['score_limit'] ?? 0));
        $stillNew = $duration > 0 && $defender->joinedAt->modify('+' . $duration . ' seconds') > $now && $defenderScore < $scoreLimit;
        if ($stillNew) return false;
        $ratio = max(1.0, (float) ($protection['attacker_score_ratio'] ?? 5));
        return $attackerScore <= max(1, $defenderScore) * $ratio;
    }

    public function canConquer(PlayerUniverse $attacker, ?Planet $target): bool
    {
        return $target !== null
            && $target->owner === null
            && $target->npcPlanet
            && $target->solarSystem->galaxy->universe->id === $attacker->universe->id
            && (bool) ($this->catalog->forUniverse($attacker->universe)['features']['planet_conquest'] ?? false);
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
        $latestEvent = $this->repo->all(CombatEvent::class, ['planet' => $planet], ['id' => 'DESC'])[0] ?? null;
        if ($events === [] && $latestEvent?->status === 'resolved' && $fleet->departureAt < $latestEvent->startedAt->modify('+' . $latestEvent->currentRound * 60 . ' seconds')) {
            return false;
        }
        $event = null;
        foreach ($events as $candidate) {
            if ($candidate->resolveAt >= $now) {
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
        $latestEvent = $this->repo->all(CombatEvent::class, ['planet' => $planet], ['id' => 'DESC'])[0] ?? null;
        if ($latestEvent?->status === 'resolved' && $fleet->departureAt < $latestEvent->startedAt->modify('+' . $latestEvent->currentRound * 60 . ' seconds')) {
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
        $latestEvent = $this->repo->all(CombatEvent::class, ['planet' => $planet], ['id' => 'DESC'])[0] ?? null;
        if ($events === [] && $latestEvent?->status === 'resolved' && $fleet->departureAt < $latestEvent->startedAt->modify('+' . $latestEvent->currentRound * 60 . ' seconds')) {
            return false;
        }
        $event = null;
        foreach ($events as $candidate) {
            if ($candidate->resolveAt >= $now) {
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
            $outbox->dueAt = $event->startedAt->modify('+60 seconds');
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
            if ($event->status !== 'pending' || !hash_equals($event->dispatchToken, $token) || $event->startedAt->modify('+' . ($event->currentRound + 1) * 60 . ' seconds') > $this->clock->now()) {
                return;
            }
            foreach ($this->repo->all(Fleet::class, ['destination' => $event->planet, 'status' => 'traveling']) as $arrivingFleet) {
                if ($arrivingFleet->arrivalAt !== null && $arrivingFleet->arrivalAt <= $this->clock->now()) {
                    $event->dispatchToken = Uuid::v4()->toRfc4122();
                    $outbox = new OutboxMessage();
                    $outbox->kind = 'combat';
                    $outbox->targetId = $event->id;
                    $outbox->token = $event->dispatchToken;
                    $outbox->dueAt = $this->clock->now()->modify('+1 second');
                    $this->manager->save($outbox);
                    return;
                }
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

            $rules = $this->catalog->rules();
            $maxRounds = max(1, (int) ($rules['max_combat_rounds'] ?? 10));
            $elapsedSeconds = max(0, $this->clock->now()->getTimestamp() - $event->startedAt->getTimestamp());
            $targetRound = min($maxRounds, max($event->currentRound + 1, intdiv($elapsedSeconds, 60)));
            $state = $event->combatSnapshot;
            $snapshot = $state['participants'] ?? [];
            $combatParticipants = $state['combat_participants'] ?? [];
            $snapshotParticipantIds = [];
            foreach ($snapshot as $entry) {
                if (($entry['participant_id'] ?? null) !== null) {
                    $snapshotParticipantIds[(int) $entry['participant_id']] = true;
                }
            }
            $attackerParticipants = array_values(array_filter($participants, static fn (CombatParticipant $p): bool => $p->role === 'attacker'));
            $campByMembership = $this->attackerCamps($attackerParticipants);
            $technologyLevels = [];
            foreach ($participants as $participant) {
                if (isset($snapshotParticipantIds[(int) $participant->id])) {
                    continue;
                }
                $fleet = $participant->fleet;
                $membershipId = $participant->playerUniverse->id;
                $technologyLevels[$membershipId] ??= $this->repo->levelsByTier(PlayerTechnology::class, ['playerUniverse' => $participant->playerUniverse]);
                $units = [];
                foreach ($this->repo->all(FleetShip::class, ['fleet' => $fleet], ['id' => 'ASC']) as $fleetShip) {
                    if ($fleetShip->quantity < 1) {
                        continue;
                    }
                    $units[] = $this->catalog->getTierForUniverse($participant->playerUniverse->universe, 'ships', $fleetShip->slug, $fleetShip->tier) + ['quantity' => $fleetShip->quantity, 'tier' => $fleetShip->tier];
                }
                $units = $this->technologyEffects->applyToShips($units, $technologyLevels[$membershipId], $participant->playerUniverse->universe);
                $role = $participant->role;
                $camp = $role === 'attacker' ? ($campByMembership[$membershipId] ?? 'attacker-' . $membershipId) : 'defenders';
                $arrivalRound = $participant->arrivalRound ?? 1;
                $combatParticipants[] = ['id' => (string) $fleet->id, 'role' => $role, 'camp' => $camp, 'arrival_round' => $arrivalRound, 'units' => $units];
                $snapshot[] = [
                    'participant_id' => $participant->id,
                    'fleet_id' => $fleet->id,
                    'player_universe_id' => $membershipId,
                    'role' => $role,
                    'camp' => $camp,
                    'arrival_at' => $participant->arrivalAt->format(DATE_ATOM),
                    'arrival_round' => $arrivalRound,
                    'units' => $units,
                    'technology_levels' => $technologyLevels[$membershipId],
                ];
                $snapshotParticipantIds[(int) $participant->id] = true;
            }
            $campByMembership = [];
            foreach ($snapshot as $entry) {
                if (($entry['role'] ?? null) === 'attacker' && isset($entry['player_universe_id'])) {
                    $campByMembership[(int) $entry['player_universe_id']] = $entry['camp'];
                }
            }

            $hasNpcSnapshot = (bool) array_filter($snapshot, static fn (array $entry): bool => ($entry['pve_npc'] ?? false) === true);
            if (!$hasNpcSnapshot) {
                $npcUnits = [];
                foreach ($this->repo->all(PlanetNpcShip::class, ['planet' => $event->planet], ['id' => 'ASC']) as $npcShip) {
                    if ($npcShip->quantity > 0) $npcUnits[] = $this->catalog->getForUniverse($event->planet->solarSystem->galaxy->universe, 'ships', $npcShip->slug) + ['quantity' => $npcShip->quantity];
                }
                if ($npcUnits !== []) {
                    $npcResearch = $this->catalog->all('planet_conquest')['levels'][$event->planet->conquestLevel]['research'] ?? [];
                    $npcUnits = $this->technologyEffects->applyToShips($npcUnits, $npcResearch, $event->planet->solarSystem->galaxy->universe);
                    $npcId = 'npc:' . $event->planet->id;
                    $combatParticipants[] = ['id' => $npcId, 'role' => 'defender', 'camp' => 'defenders', 'arrival_round' => 1, 'units' => $npcUnits];
                    $snapshot[] = ['participant_id' => null, 'fleet_id' => null, 'player_universe_id' => null, 'role' => 'defender', 'camp' => 'defenders', 'arrival_at' => $event->startedAt->format(DATE_ATOM), 'arrival_round' => 1, 'units' => $npcUnits, 'technology_levels' => $npcResearch, 'pve_npc' => true, 'planet_id' => $event->planet->id];
                }
            }

            $defenseSnapshots = $state['defenses'] ?? [];
            if (!array_key_exists('defenses', $state)) {
                foreach ($this->repo->all(PlanetDefense::class, ['planet' => $event->planet], ['id' => 'ASC']) as $defense) {
                    if ($defense->quantity < 1) {
                        continue;
                    }
                    $definition = $this->catalog->getForUniverse($event->planet->solarSystem->galaxy->universe, 'defenses', $defense->slug);
                    $defender = $event->planet->owner;
                    $defenderLevels = $defender ? $this->repo->levelsByTier(PlayerTechnology::class, ['playerUniverse' => $defender]) : [];
                    $definition = $this->technologyEffects->applyToDefenses([$definition], $defenderLevels, $event->planet->solarSystem->galaxy->universe)[0];
                    $fortressLevels = $this->repo->levelsByTier(PlanetBuilding::class, ['planet' => $event->planet])['fortress'] ?? [];
                    $fortress = $this->catalog->getForUniverse($event->planet->solarSystem->galaxy->universe, 'buildings', 'fortress')['effect'] ?? [];
                    if (($fortress['targets'] ?? null) === 'ground_defenses' && ($fortress['stat'] ?? null) === 'hp') {
                        $remaining=1.0;foreach(\App\Service\Catalog\GameCatalog::TIERS as $rank){$rankLevel=(int)($fortressLevels[$rank]??0);$rankFactor=\App\Service\Catalog\GameCatalog::tierFactor($rank);$bonus=min((float)($fortress['max_bonus']??0)*$rankFactor,(float)($fortress['value_per_level']??0)*$rankLevel*$rankFactor)/100;$remaining*=1+$bonus;}
                        $definition['hp'] *= $remaining;
                    }
                    $defenseSnapshots[] = $definition + [
                        'id' => (string) $defense->id,
                        'camp' => 'defenders',
                        'quantity' => $defense->quantity,
                    ];
                }
            }
            $modifiers = $state['modifiers'] ?? ($this->catalog->all('combat_modifiers')['modifiers'] ?? []);
            $event->combatSnapshot = ['participants' => $snapshot, 'combat_participants' => $combatParticipants, 'defenses' => $defenseSnapshots, 'modifiers' => $modifiers];
            $result = $this->simulator->simulate(
                $combatParticipants, $defenseSnapshots, $modifiers, $maxRounds, $event->seed, $targetRound,
                (float) ($rules['shield_recharge_percent_per_round'] ?? 5),
                (bool) ($rules['restore_survivor_shields_after_combat'] ?? true),
            );
            $roundHistory = $state['round_history'] ?? [];
            foreach ($result['rounds'] as $round) {
                if ($round['number'] > $event->currentRound) {
                    $roundHistory[] = $round;
                }
            }
            $event->combatSnapshot['round_history'] = $roundHistory;

            $damageByMembership = [];
            $membershipByFleet = [];
            foreach ($participants as $participant) {
                $membershipByFleet[$participant->fleet->id] = $participant->playerUniverse;
            }
            foreach ($result['rounds'] as $round) {
                if ($round['number'] <= $event->currentRound) {
                    continue;
                }
                foreach ($round['shots'] as $shot) {
                    $fleetId = (int) explode(':', (string) $shot['attacker'], 2)[0];
                    $membership = $membershipByFleet[$fleetId] ?? null;
                    if ($membership) {
                        $damageByMembership[$round['number']][$membership->id] ??= ['membership' => $membership, 'damage' => 0.0];
                        $damageByMembership[$round['number']][$membership->id]['damage'] += (float) $shot['damage'];
                    }
                }
            }
            foreach ($damageByMembership as $roundNumber => $scores) {
                foreach ($scores as $score) {
                    $this->boostEvents->recordCombatDamage($score['membership'], $event->id, (int) $roundNumber, $score['damage']);
                }
            }

            if ($result['outcome'] === 'ongoing') {
                $event->currentRound = $result['rounds_played'];
                $event->dispatchToken = Uuid::v4()->toRfc4122();
                $outbox = new OutboxMessage();
                $outbox->kind = 'combat';
                $outbox->targetId = $event->id;
                $outbox->token = $event->dispatchToken;
                $outbox->dueAt = $event->startedAt->modify('+' . ($event->currentRound + 1) * 60 . ' seconds');
                $this->manager->save($outbox);
                return;
            }
            $event->currentRound = $result['rounds_played'];

            $defenseRebuilt = $this->persistLosses($participants, $defenseSnapshots, $result['losses'], $event->planet);
            $recoveryProtection = $this->applyRecoveryProtection($snapshot, $result['losses'], $participants);
            $loot = $this->collectLoot($participants, $result['outcome'], $event);
            $capture = $this->capturePlanet($participants, $result['outcome'], $event, $result['losses']);
            $winningAttackerCamps = [];
            foreach ($result['survivors'] as $survivor) {
                if ($survivor['role'] === 'attacker') {
                    $winningAttackerCamps[$survivor['camp']] = true;
                }
            }
            foreach ($participants as $participant) {
                if ($participant->role === 'attacker' && $participant->fleet->mission === 'attack' && $result['outcome'] === 'attacker_wins') {
                    $camp = $campByMembership[$participant->playerUniverse->id] ?? null;
                    if ($camp !== null && isset($winningAttackerCamps[$camp])) {
                        $this->boostGrants->awardRiskMission($participant->playerUniverse, $participant->fleet);
                    }
                } elseif ($participant->role === 'defender' && $participant->fleet->mission === 'defend' && $result['outcome'] === 'defender_wins') {
                    $this->boostGrants->awardRiskMission($participant->playerUniverse, $participant->fleet);
                } elseif (
                    $participant->role === 'attacker'
                    && $participant->fleet->mission === 'conquer'
                    && ($capture['status'] ?? null) === 'captured'
                    && (int) ($capture['fleet_id'] ?? 0) === $participant->fleet->id
                ) {
                    $this->boostGrants->awardRiskMission($participant->playerUniverse, $participant->fleet);
                }
            }
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
                'defense_rebuilt' => $defenseRebuilt,
                'recovery_protection' => $recoveryProtection,
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
                $type = $this->catalog->getForUniverse($participant->playerUniverse->universe, 'ships', $ship->slug);
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
            $colonyShips=$this->repo->all(FleetShip::class,['fleet'=>$fleet,'slug'=>'colony_ship']);
            $colonyShip=null;foreach($colonyShips as $candidate)if($candidate->quantity>0){$colonyShip=$candidate;break;}
            if(!$colonyShip)continue;
            if ($event->planet->owner !== null) { $reason = 'target_claimed'; continue; }
            $config = $this->catalog->forUniverse($participant->playerUniverse->universe);
            if (count($this->repo->planets($participant->playerUniverse->universe, $participant->playerUniverse)) >= $this->planetLimit($participant->playerUniverse)) {
                $reason = 'planet_limit'; continue;
            }

            --$colonyShip->quantity;
            $lossKey = $fleet->id . ':colony_ship';
            $losses[$lossKey] = ($losses[$lossKey] ?? 0) + 1;
            $event->planet->name = $this->planetNames->generate($participant->playerUniverse);
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

    private function planetLimit(PlayerUniverse $membership): int
    {
        $config=$this->catalog->forUniverse($membership->universe);$definition=$config['catalog']['technologies']['colonization']??null;$effect=$definition['effect']??null;
        if(!is_array($effect)||($effect['type']??null)!=='planet_limit')return (int)$config['limits']['max_planets'];
        $ranks=$this->repo->levelsByTier(PlayerTechnology::class,['playerUniverse'=>$membership])['colonization']??[];$base=(int)($effect['base_planets']??1);$perLevel=(float)($effect['value_per_level']??1);$basicCap=max(0,(int)($effect['max_planets']??5)-$base);$extra=0.0;foreach(\App\Service\Catalog\GameCatalog::TIERS as $tier){$factor=\App\Service\Catalog\GameCatalog::tierFactor($tier);$level=min((int)($definition['max_level']??25),(int)($ranks[$tier]??0));$extra+=min($basicCap*$factor,$perLevel*$level*$factor);}
        return max(1,min((int)$config['limits']['max_planets'],$base+(int)floor($extra)));
    }

    private function arrivalRound(CombatEvent $event, \DateTimeImmutable $arrivalAt): int
    {
        $maxRounds = max(1, (int) ($this->catalog->rules()['max_combat_rounds'] ?? 10));
        $elapsed = max(0, $arrivalAt->getTimestamp() - $event->startedAt->getTimestamp());

        return max(1, min($maxRounds, (int) ceil($elapsed / 60)));
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

    private function persistLosses(array $participants, array $defenseSnapshots, array $losses, Planet $planet): array
    {
        foreach ($participants as $participant) {
            foreach ($this->repo->all(FleetShip::class, ['fleet' => $participant->fleet]) as $ship) {
                $lost = $losses[$participant->fleet->id . ':' . $ship->slug . ($ship->tier==='basic'?'':':'.$ship->tier)] ?? 0;
                $ship->quantity = max(0, $ship->quantity - $lost);
            }
        }
        foreach ($this->repo->all(PlanetNpcShip::class, ['planet' => $planet]) as $npcShip) {
            $lost = $losses['npc:' . $planet->id . ':' . $npcShip->slug] ?? 0;
            $npcShip->quantity = max(0, $npcShip->quantity - $lost);
        }
        $rebuilt = [];
        $robotics = $planet->owner ? ($this->repo->levelsByTier(PlayerTechnology::class, ['playerUniverse' => $planet->owner])['robotics'] ?? []) : [];
        $rules = $this->catalog->rules();
        $remainingFailure=1.0;foreach(\App\Service\Catalog\GameCatalog::TIERS as $rank){$level=(int)($robotics[$rank]??0);$factor=\App\Service\Catalog\GameCatalog::tierFactor($rank);$chance=min((float)($rules['defense_rebuild_max_percent']??50)*$factor,(float)($rules['defense_rebuild_percent_per_robotics_level']??10)*$level*$factor)/100;$remainingFailure*=1-$chance;}
        $rebuildPercent=(1-$remainingFailure)*100;
        foreach ($defenseSnapshots as $snapshot) {
            $id = (int) $snapshot['id'];
            $defense = $this->repo->find(PlanetDefense::class, $id);
            if (!$defense) continue;
            $lost = (int) ($losses['planet:' . $id . ':' . $defense->slug] ?? 0);
            $restored = (int) floor($lost * $rebuildPercent / 100);
            $defense->quantity = max(0, $defense->quantity - $lost) + $restored;
            if ($restored > 0) $rebuilt[$defense->slug] = ($rebuilt[$defense->slug] ?? 0) + $restored;
        }
        ksort($rebuilt);
        return $rebuilt;
    }

    private function applyRecoveryProtection(array $snapshot, array $losses, array $participants): array
    {
        $memberships = [];
        foreach ($participants as $participant) $memberships[$participant->playerUniverse->id] = $participant->playerUniverse;
        $engaged = [];
        $lost = [];
        foreach ($snapshot as $entry) {
            $membershipId = $entry['player_universe_id'] ?? null;
            $fleetId = $entry['fleet_id'] ?? null;
            if ($membershipId === null || $fleetId === null || !isset($memberships[$membershipId])) continue;
            foreach ($entry['units'] ?? [] as $unit) {
                $slug = (string) ($unit['slug'] ?? '');
                $unitTier=(string)($unit['tier']??'basic');$definition = $this->catalog->getTierForUniverse($memberships[$membershipId]->universe, 'ships', $slug, $unitTier);
                if ((float) ($definition['base_attack'] ?? 0) <= 0) continue;
                $quantity = (int) ($unit['quantity'] ?? 0);
                $engaged[$membershipId] = ($engaged[$membershipId] ?? 0) + $quantity;
                $lost[$membershipId] = ($lost[$membershipId] ?? 0) + (int) ($losses[$fleetId . ':' . $slug . ((($unit['tier']??'basic')==='basic')?'':':'.$unit['tier'])] ?? 0);
            }
        }
        $rules = $this->catalog->rules()['recovery_protection'] ?? [];
        $threshold = max(0.0, min(1.0, (float) ($rules['military_loss_ratio'] ?? 0.7)));
        $duration = max(0, (int) ($rules['duration_seconds'] ?? 86400));
        $cooldown = max(0, (int) ($rules['cooldown_seconds'] ?? 604800));
        $now = $this->clock->now();
        $protected = [];
        foreach ($engaged as $id => $quantity) {
            $membership = $memberships[$id];
            if ($quantity < 1 || ($lost[$id] ?? 0) / $quantity < $threshold) continue;
            if ($membership->lastRecoveryProtectionAt !== null && $membership->lastRecoveryProtectionAt->modify('+' . $cooldown . ' seconds') > $now) continue;
            $membership->lastRecoveryProtectionAt = $now;
            $membership->recoveryProtectedUntil = $now->modify('+' . $duration . ' seconds');
            $protected[] = (int) $id;
            $this->notifications->enqueue($membership, 'Protection de récupération activée pendant 24 heures après de lourdes pertes.');
        }
        sort($protected);
        return $protected;
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
