<?php

namespace App\Service\Queue;

use App\Entity\{PlayerUniverse, Planet, PlanetBuilding, PlayerTechnology, QueueItem, Fleet, FleetShip, OutboxMessage, PlanetDefense};
use App\Manager\GameManager;
use App\Repository\GameRepository;
use App\Service\Catalog\GameCatalog;
use App\Service\Requirement\RequirementChecker;
use App\Service\Colony\{ResourceService, ActionGuard, ResourceWaitEstimate};
use Psr\Clock\ClockInterface;
use Symfony\Component\Uid\Uuid;
use App\Service\Player\FirstSteps;
use App\Service\Player\VacationCalendar;

/**
 * Gère les commandes payées, de leur ajout jusqu’à leur résolution.
 * Le rattrapage lors d’une lecture et les messages du worker passent par les mêmes traitements.
 */
final class QueueService
{
    public function __construct(
        private GameManager $manager,
        private GameRepository $repo,
        private GameCatalog $catalog,
        private RequirementChecker $requirements,
        private ResourceService $resources,
        private ActionGuard $guard,
        private ClockInterface $clock,
        private \App\Service\Notification\NotificationService $notifications,
        private OperationCalculator $calculator,
        private \App\Service\Requirement\CatalogAccess $access,
        private VacationCalendar $vacations,
        private \App\Service\Player\EmpireReconstructionScheduler $reconstruction,
        private \App\Service\Colony\EconomicUnitCapacity $economicUnits,
        private \App\Service\Combat\LocalGarrison $garrison,
        private FirstSteps $firstSteps,
    )
    {
    }

    public function add(
        PlayerUniverse $membership,
        Planet $planet,
        string $group,
        string $slug,
        int $quantity,
        string $requestKey,
        string $tier = 'basic',
    ): int
    {
        return $this->manager->transaction(function () use ($membership, $planet, $group, $slug, $quantity, $requestKey, $tier) {
            $this->guard->lock($membership, $planet);
            $this->vacations->assertCanManageQueues($membership);
            // Les niveaux et les stocks doivent tenir compte des commandes déjà échues avant tout nouvel achat.
            $this->settleMembershipLocked($membership);
            if (!$this->guard->receipt($membership, $requestKey)) {
                return 0;
            }
            if (!in_array($group, [
                'buildings',
                'technologies',
                'ships',
                'defenses',
            ], true)) {
                throw new \DomainException('Type de construction invalide.');
            }
            if (!in_array($tier, GameCatalog::TIERS, true)) {
                throw new \DomainException('Rang de progression invalide.');
            }
            if ($quantity < 1) {
                throw new \DomainException('La quantité doit être un entier positif.');
            }
            if (!isset($this->catalog->all($group)[$slug])) {
                throw new \DomainException('Élément indisponible.');
            }
            $config = $this->catalog->forUniverse($membership->universe);
            $baseDefinition = $config['catalog'][$group][$slug] ?? $this->catalog->get($group, $slug);
            $definition = $this->catalog->withTier($baseDefinition, $tier);
            $queueType = match ($group) {
                'buildings' => 'building',
                'technologies' => 'research',
                default => 'shipyard',
            };
            $queue = $queueType === 'research' ? $this->repo->researchQueue($membership) : $this->repo->queue($planet, $queueType);
            if (count($queue) >= 30) {
                throw new \DomainException('File pleine (30 commandes maximum).');
            }
            $buildings = $this->repo->levelsByTier(PlanetBuilding::class, ['planet' => $planet]);
            $technologies = $this->repo->levelsByTier(PlayerTechnology::class, ['playerUniverse' => $membership]);
            if ($group === 'technologies' && !$this->access->hasLocalResearchLaboratory($buildings, $tier)) {
                $laboratory = $this->catalog->getTierForUniverse($membership->universe, 'buildings', 'lab', $tier);
                throw new \DomainException($laboratory['label'] . ' de niveau 1 requis sur cette colonie pour lancer cette recherche.');
            }
            $level = 1;
            $previousLevel = null;
            if (in_array($group, ['buildings', 'technologies'], true)) {
                // Les niveaux en attente comptent pour le niveau demandé, sans satisfaire les prérequis à l’avance.
                $quantity = 1;
                $level = (int) ($group === 'buildings' ? $buildings[$slug][$tier] ?? 0 : $technologies[$slug][$tier] ?? 0) + 1;
                foreach ($queue as $item) {
                    if ($item->itemSlug === $slug && $item->tier === $tier) {
                        $level = ($item->itemLevel ?? 0) + 1;
                        $previousLevel = $item;
                    }
                }
                $maxLevel = (int) ($definition['effect']['tier_max_levels'][$tier] ?? $definition['max_level'] ?? PHP_INT_MAX);
                if ($level > $maxLevel) {
                    throw new \DomainException('Niveau maximum atteint.');
                }
            }
            $failures = $this->access->failures($membership, $planet, $group, $definition, $tier, $buildings, $technologies);
            if ($failures) {
                throw new \DomainException('Déblocage initial : ' . implode(', ', array_unique($failures)) . '.');
            }
            if (in_array($group, ['ships', 'defenses'], true)) {
                $maximum = $this->unitMaximum($membership, $planet, $group, $slug, $tier, $definition, $buildings, $config, $technologies);
                if ($maximum < 1) {
                    if ($this->economicUnits->constructionLimit($planet, $definition, $buildings) === 0) {
                        throw new \DomainException('Aucune place de satellite disponible dans la centrale de ce rang.');
                    }
                    throw new \DomainException('Ressources insuffisantes pour construire une unité.');
                }
                $quantity = min($quantity, $maximum);
            }
            $plan = $this->calculator->calculate($definition, $group, $level, $quantity, $buildings, $config, $technologies);
            $duration = $plan['duration'];
            $this->resources->consume($planet, $plan['costs']);
            $item = new QueueItem();
            $item->planet = $planet;
            $item->playerUniverse = $membership;
            $item->queueType = $queueType;
            $item->itemSlug = $slug;
            $item->tier = $tier;
            $item->itemLevel = in_array($group, ['buildings', 'technologies'], true) ? $level : null;
            $item->quantity = $quantity;
            $item->paidCosts = $plan['costs'];
            $item->durationSeconds = $duration;
            $item->queuedAt = $this->clock->now();
            $item->dependsOn = $previousLevel;
            $item->dispatchToken = Uuid::v4()->toRfc4122();
            $this->manager->save($item);
            $this->manager->flush();
            if (!$queue) {
                $this->start($item, $this->clock->now());
            }
            return $quantity;
        });
    }

    /**
     * Ajoute une commande de reconstruction pour un empire oublié.
     * La participation et la planète doivent déjà être verrouillées par l’appelant.
     */
    public function addReconstructionLocked(
        PlayerUniverse $membership,
        Planet $planet,
        string $group,
        string $slug,
        string $tier,
    ): QueueItem
    {
        if (!$membership->isForgotten() || $planet->owner?->id !== $membership->id || !in_array($group, ['ships', 'defenses'], true) || $this->repo->queue($planet, 'shipyard') !== []) {
            throw new \DomainException('Reconstruction indisponible.');
        }
        $definition = $this->catalog->getTierForUniverse($membership->universe, $group, $slug, $tier);
        $buildings = $this->repo->levelsByTier(PlanetBuilding::class, ['planet' => $planet]);
        $technologies = $this->repo->levelsByTier(PlayerTechnology::class, ['playerUniverse' => $membership]);
        $plan = $this->calculator->calculate($definition, $group, 1, 1, $buildings, $this->catalog->forUniverse($membership->universe), $technologies);
        $this->resources->consume($planet, $plan['costs']);
        $item = new QueueItem();
        $item->planet = $planet;
        $item->playerUniverse = $membership;
        $item->queueType = 'shipyard';
        $item->itemSlug = $slug;
        $item->tier = $tier;
        $item->quantity = 1;
        $item->paidCosts = $plan['costs'];
        $item->durationSeconds = $plan['duration'];
        $item->queuedAt = $this->clock->now();
        $item->dispatchToken = Uuid::v4()->toRfc4122();
        $this->manager->save($item);
        $this->manager->flush();
        $this->start($item, $this->clock->now());
        return $item;
    }

    public function resumeReconstructionLocked(QueueItem $item): void
    {
        if ($item->startedAt === null) {
            $this->start($item, $this->clock->now());
        }
    }

    public function maximum(
        PlayerUniverse $membership,
        Planet $planet,
        string $group,
        string $slug,
        string $tier,
    ): int
    {
        return $this->manager->transaction(function () use ($membership, $planet, $group, $slug, $tier) {
            $this->guard->lock($membership, $planet);
            $this->vacations->assertCanManageQueues($membership);
            $this->settleMembershipLocked($membership);
            if (!in_array($group, ['ships', 'defenses'], true) || !in_array($tier, GameCatalog::TIERS, true) || !isset($this->catalog->all($group)[$slug])) {
                throw new \DomainException('Construction indisponible.');
            }
            $definition = $this->catalog->getTierForUniverse($membership->universe, $group, $slug, $tier);
            $buildings = $this->repo->levelsByTier(PlanetBuilding::class, ['planet' => $planet]);
            $technologies = $this->repo->levelsByTier(PlayerTechnology::class, ['playerUniverse' => $membership]);
            if (count($this->repo->queue($planet, 'shipyard')) >= 30 || $this->access->failures($membership, $planet, $group, $definition, $tier, $buildings, $technologies)) {
                return 0;
            }
            return $this->unitMaximum(
                $membership,
                $planet,
                $group,
                $slug,
                $tier,
                $definition,
                $buildings,
                $this->catalog->forUniverse($membership->universe),
                $technologies
            );
        });
    }

    public function constructionPreview(
        PlayerUniverse $membership,
        Planet $planet,
        string $group,
        string $slug,
        string $tier,
        int $requested,
    ): array
    {
        $maximum = $this->maximum($membership, $planet, $group, $slug, $tier);
        $quantity = min(max(1, $requested), $maximum);
        $config = $this->catalog->forUniverse($membership->universe);
        $definition = $this->catalog->getTierForUniverse($membership->universe, $group, $slug, $tier);
        $buildings = $this->repo->levelsByTier(PlanetBuilding::class, ['planet' => $planet]);
        $technologies = $this->repo->levelsByTier(PlayerTechnology::class, ['playerUniverse' => $membership]);
        $plan = $this->calculator->calculate($definition, $group, 1, max(1, $quantity), $buildings, $config, $technologies);
        $reason = '';
        $missing = [];
        $missingResources = [];
        $paymentWait = null;
        $paymentEstimate = null;
        if ($maximum === 0) {
            $failures = $this->access->failures($membership, $planet, $group, $definition, $tier, $buildings, $technologies);
            if (count($this->repo->queue($planet, 'shipyard')) >= 30) {
                $reason = 'File pleine (30 commandes maximum).';
            } elseif ($failures) {
                $reason = 'Prérequis manquants : ' . implode(', ', $failures) . '.';
            } elseif ($this->economicUnits->constructionLimit($planet, $definition, $buildings) === 0) {
                $reason = 'Aucune place de satellite disponible dans la centrale de ce rang.';
            } else {
                $observedAt = $this->clock->now();
                $payment = ResourceWaitEstimate::forCosts($plan['costs'], $this->resources->snapshot($planet, observedAt: $observedAt), $observedAt);
                $missingResources = $payment['missing'];
                $paymentWait = $payment['paymentWait'];
                $paymentEstimate = $payment['paymentEstimate'];
                foreach ($missingResources as $resource) {
                    $missing[] = number_format($resource['amount'], 0, ',', ' ') . ' ' . $resource['label'];
                }
                $reason = $missing ? 'Ressources manquantes : ' . implode(', ', $missing) . '.' : 'Limite de quantité atteinte.';
            }
        }
        return [
            'maximum' => $maximum,
            'quantity' => $quantity,
            'costs' => $quantity > 0 ? $plan['costs'] : [],
            'duration' => $quantity > 0 ? $plan['duration'] : 0,
            // Même indisponible, le catalogue explique le prix d’une unité sans changer le devis exécutable.
            'displayCosts' => $plan['costs'],
            'displayDuration' => $plan['duration'],
            'missingResources' => $missingResources,
            'paymentWait' => $paymentWait,
            'paymentEstimate' => $paymentEstimate,
            'reason' => $reason,
            'unavailableLabel' => $maximum === 0 ? $missing ? 'Manque ' . implode(' · ', $missing) : $reason : '',
        ];
    }

    private function unitMaximum(
        PlayerUniverse $membership,
        Planet $planet,
        string $group,
        string $slug,
        string $tier,
        array $definition,
        array $buildings,
        array $config,
        array $technologies,
    ): int
    {
        $owned = $group === 'defenses' ? $this->repo->one(PlanetDefense::class, ['planet' => $planet, 'slug' => $slug, 'tier' => $tier])?->quantity ?? 0 : $this->repo->one(FleetShip::class, ['fleet' => $this->repo->one(Fleet::class, ['homePlanet' => $planet, 'playerUniverse' => $membership, 'status' => 'idle']), 'slug' => $slug, 'tier' => $tier])?->quantity ?? 0;
        $limit = 2147483647 - $owned;
        foreach ($this->repo->queue($planet, 'shipyard') as $item) {
            if ($item->itemSlug === $slug && $item->tier === $tier) {
                $limit -= $item->quantity;
            }
        }
        $limit = min($limit, $this->economicUnits->constructionLimit($planet, $definition, $buildings));
        return $this->calculator->maximum($definition, $group, $this->resources->snapshot($planet), $buildings, $config, $technologies, max(0, $limit));
    }

    private function start(QueueItem $item, \DateTimeImmutable $at): void
    {
        $item->startedAt = $at;
        $startedAtField = $item->queueType . 'QueueStartedAt';
        $item->planet->{$startedAtField} = $at;
        // Un nouveau jeton rend les anciens messages sans effet après un boost ou une reprise.
        $item->dispatchToken = Uuid::v4()->toRfc4122();
        $message = new OutboxMessage();
        $message->kind = 'queue';
        $message->targetId = $item->id;
        $message->token = $item->dispatchToken;
        $message->dueAt = $item->finishAt();
        $this->manager->save($message);
    }

    /** Rattrape les commandes échues selon l’horloge et les vacances, avant une lecture ou une dépense. */
    public function synchronize(PlayerUniverse $membership, ?\DateTimeImmutable $until = null): void
    {
        $this->manager->transaction(function () use ($membership, $until) {
            $this->guard->lockMembershipForProcessing($membership);
            $this->settleMembershipLocked($membership, $until);
        });
    }

    private function settleMembershipLocked(PlayerUniverse $membership, ?\DateTimeImmutable $until = null): void
    {
        $this->access->captureLocked($membership);
        $planets = [];
        foreach ($this->repo->planets($membership->universe, $membership) as $planet) {
            $this->manager->lock($planet);
            $planets[$planet->id] = $planet;
        }
        $until ??= $this->clock->now();
        if (!$this->vacations->isActive($membership, $until)) {
            $this->startNextResearch($membership, $until);
            $items = [];
            foreach ($this->repo->all(QueueItem::class, ['playerUniverse' => $membership], ['id' => 'ASC']) as $item) {
                if (isset($planets[$item->planet->id])) {
                    $items[$item->id] = $item;
                }
            }
            // Chaque fin peut lancer la suivante, y compris une recherche sur une autre colonie.
            // Recalculer les échéances à chaque tour conserve leur ordre et la production correspondante.
            while (true) {
                $dueItems = [];
                $queueHeads = [];
                foreach ($items as $item) {
                    if (!in_array($item->queueType, ['building', 'research', 'shipyard'], true)) {
                        continue;
                    }
                    if ($item->queueType !== 'research') {
                        $key = $item->planet->id . ':' . $item->queueType;
                        if (isset($queueHeads[$key])) {
                            continue;
                        }
                        $queueHeads[$key] = true;
                    }
                    if ($item->startedAt !== null && $item->finishAt() <= $until) {
                        $dueItems[] = $item;
                    }
                }
                if (!$dueItems) {
                    break;
                }
                usort($dueItems, fn($a, $b) => $a->finishAt() <=> $b->finishAt() ?: $a->id <=> $b->id);
                $item = $dueItems[0];
                $id = $item->id;
                $this->applyCompletion($item);
                unset($items[$id]);
            }
        }
        $this->access->captureLocked($membership);
    }

    private function startNextResearch(PlayerUniverse $membership, \DateTimeImmutable $at): void
    {
        if ($this->vacations->isActive($membership, $at)) {
            return;
        }
        $queue = $this->repo->researchQueue($membership);
        foreach ($queue as $item) {
            if ($item->startedAt !== null) {
                return;
            }
        }
        $next = $queue[0] ?? null;
        if ($next !== null && $next->planet->owner?->id === $membership->id) {
            $this->start($next, $at < $next->queuedAt ? $next->queuedAt : $at);
        }
    }

    private function applyCompletion(QueueItem $item): void
    {
        $planet = $item->planet;
        $membership = $item->playerUniverse;
        // Le worker peut être en retard : appliquer la fin prévue, pas l’heure à laquelle le message est reçu.
        $finish = $item->finishAt();
        $shipConstruction = $item->queueType === 'shipyard' && isset($this->catalog->all('ships')[$item->itemSlug]);
        $economic = $shipConstruction && isset($this->catalog->all('ships')[$item->itemSlug]['economic_unit']);
        $shipCredited = false;
        if ($economic) {
            // Créditer uniquement l'ancien effectif jusqu'à la fin payée, même après une longue absence.
            $this->resources->materialize($planet, $finish);
        }
        if ($item->queueType === 'building') {
            // Calculer les stocks à la date de fin avec les anciens niveaux, avant d’appliquer le nouveau gain.
            $this->resources->materialize($planet, $finish);
            $building = $this->repo->one(PlanetBuilding::class, ['planet' => $planet, 'slug' => $item->itemSlug, 'tier' => $item->tier]) ?? new PlanetBuilding();
            $building->planet = $planet;
            $building->slug = $item->itemSlug;
            $building->tier = $item->tier;
            $building->level = $item->itemLevel;
            $this->manager->save($building);
            $this->manager->flush();
            $this->resources->refreshProduction($planet);
        } elseif ($item->queueType === 'research') {
            $technology = $this->repo->one(PlayerTechnology::class, ['playerUniverse' => $membership, 'slug' => $item->itemSlug, 'tier' => $item->tier]) ?? new PlayerTechnology();
            $technology->playerUniverse = $membership;
            $technology->slug = $item->itemSlug;
            $technology->tier = $item->tier;
            $technology->level = $item->itemLevel;
            $this->manager->save($technology);
        } elseif (isset($this->catalog->all('defenses')[$item->itemSlug])) {
            $defense = $this->repo->one(PlanetDefense::class, ['planet' => $planet, 'slug' => $item->itemSlug, 'tier' => $item->tier]) ?? new PlanetDefense();
            $defense->planet = $planet;
            $defense->slug = $item->itemSlug;
            $defense->tier = $item->tier;
            $defense->quantity += $item->quantity;
            $this->manager->save($defense);
        } else {
            $fleet = $this->repo->one(Fleet::class, ['homePlanet' => $planet, 'playerUniverse' => $membership, 'status' => 'idle']);
            if (!$fleet) {
                $fleet = new Fleet();
                $fleet->playerUniverse = $membership;
                $fleet->homePlanet = $planet;
                $this->manager->save($fleet);
                $this->manager->flush();
            }
            $ship = $this->repo->one(FleetShip::class, ['fleet' => $fleet, 'slug' => $item->itemSlug, 'tier' => $item->tier]) ?? new FleetShip();
            $ship->fleet = $fleet;
            $ship->slug = $item->itemSlug;
            $ship->tier = $item->tier;
            $ship->quantity += $item->quantity;
            $this->manager->save($ship);
            $shipCredited = $shipConstruction;
        }
        foreach ($this->repo->all(QueueItem::class, ['dependsOn' => $item]) as $dependent) {
            $dependent->dependsOn = null;
        }
        $group = match ($item->queueType) {
            'building' => 'buildings',
            'research' => 'technologies',
            default => isset($this->catalog->all('defenses')[$item->itemSlug]) ? 'defenses' : 'ships',
        };
        $this->notifications->enqueue($membership, 'Terminé : ' . $this->catalog->getTierForUniverse($membership->universe, $group, $item->itemSlug, $item->tier)['label'] . ' sur ' . $planet->name . '.', journalKind: 'queue');
        $this->firstSteps->recordQueueCompletion($item, $finish, shipConstructed: $shipCredited);
        $this->reconstruction->afterCompletionLocked($item);
        $queueType = $item->queueType;
        $this->manager->remove($item);
        $this->manager->flush();
        if ($shipConstruction) $this->garrison->enlist($planet,$finish);
        if ($economic) $this->resources->refreshProduction($planet);
        if ($queueType === 'research') {
            $planet->researchQueueStartedAt = null;
            $this->startNextResearch($membership, $finish);
        } else {
            $next = $this->repo->queue($planet, $queueType)[0] ?? null;
            if ($next) {
                $this->start($next, $finish);
            } else {
                $field = $queueType . 'QueueStartedAt';
                $planet->{$field} = null;
            }
        }
    }

    public function complete(int $id, string $token): void
    {
        // Ce contexte choisit seulement les verrous ; la commande et ses droits seront revérifiés sous verrou.
        $context = $this->repo->queueContext($id);
        if ($context === null) {
            return;
        }
        $membership = $this->repo->find(PlayerUniverse::class, $context['membershipId']);
        $planet = $this->repo->find(Planet::class, $context['planetId']);
        if (!$membership || !$planet) {
            return;
        }
        // Identifier les verrous avant la transaction évite un snapshot périmé pendant leur attente.
        $this->manager->transaction(function () use ($id, $token, $membership, $planet) {
            $this->guard->lockForCompletion($membership, $planet);
            $item = $this->repo->find(QueueItem::class, $id);
            if (!$item || $item->playerUniverse->id !== $membership->id || $item->planet->id !== $planet->id) {
                return;
            }
            $head = $item->queueType === 'research' ? $item : $this->repo->queue($planet, $item->queueType)[0] ?? null;
            if (!$head || $head->id !== $id || !hash_equals($head->dispatchToken, $token) || !$head->startedAt) {
                return;
            }
            $this->settleMembershipLocked($membership);
        });
    }

    public function applyPlayerBoost(QueueItem $item, float $divisor): void
    {
        $this->vacations->assertCanManageQueues($item->playerUniverse);
        if (!$item->startedAt || $divisor <= 1) {
            throw new \DomainException('Opération active introuvable.');
        }
        $elapsed = max(0, $this->clock->now()->getTimestamp() - $item->startedAt->getTimestamp());
        $item->durationSeconds = QueueTiming::boostedDuration($item->durationSeconds, $elapsed, $divisor);
        $this->start($item, $item->startedAt);
    }

    /** Accélération administrative ; les boosts consommés par un joueur passent par applyPlayerBoost(). */
    public function boost(
        PlayerUniverse $membership,
        int $id,
        float $divisor,
        string $key,
    ): void
    {
        $this->manager->transaction(function () use ($membership, $id, $divisor, $key) {
            $this->guard->lockMembership($membership);
            $this->vacations->assertCanManageQueues($membership);
            if (!$this->guard->receipt($membership, $key)) {
                return;
            }
            $this->settleMembershipLocked($membership);
            $item = $this->repo->find(QueueItem::class, $id);
            if (!$item || $item->playerUniverse->id !== $membership->id || !$item->startedAt) {
                throw new \DomainException('Opération active introuvable.');
            }
            $elapsed = max(0, $this->clock->now()->getTimestamp() - $item->startedAt->getTimestamp());
            $item->durationSeconds = QueueTiming::boostedDuration($item->durationSeconds, $elapsed, $divisor);
            $this->start($item, $item->startedAt);
        });
    }

    public function cancellationPreview(PlayerUniverse $membership, int $id): array
    {
        return $this->manager->transaction(function () use ($membership, $id) {
            $item = $this->repo->find(QueueItem::class, $id);
            if (!$item || $item->playerUniverse->id !== $membership->id || $item->playerUniverse->universe->id !== $membership->universe->id) {
                throw new \DomainException('Commande inaccessible.');
            }
            $this->guard->lock($membership, $item->planet);
            $this->vacations->assertCanManageQueues($membership);
            $this->settleMembershipLocked($membership);
            $item = $this->repo->find(QueueItem::class, $id);
            if (!$item) {
                throw new \DomainException('Cette commande est déjà terminée.');
            }
            $rows = [];
            // Plusieurs remboursements utilisent le même stockage : réserver sa place au fil de l’aperçu.
            $rooms = [];
            $collect = function (QueueItem $command) use (&$collect, &$rows, &$rooms, $membership): void {
                if ($command->playerUniverse->id !== $membership->id || $command->planet->owner?->id !== $membership->id) {
                    throw new \DomainException('Commande inaccessible.');
                }
                foreach ($this->repo->all(QueueItem::class, ['dependsOn' => $command]) as $dependent) {
                    $collect($dependent);
                }
                $group = match ($command->queueType) {
                    'building' => 'buildings',
                    'research' => 'technologies',
                    default => isset($this->catalog->all('defenses')[$command->itemSlug]) ? 'defenses' : 'ships',
                };
                $definition = $this->catalog->getTierForUniverse($membership->universe, $group, $command->itemSlug, $command->tier);
                // Ne recalculer les coûts que pour les anciennes commandes qui ne les ont pas enregistrés.
                $paid = $command->paidCosts ?? $this->catalog->costs($definition, $command->itemLevel ?? 1, $command->quantity, $group);
                if (!isset($rooms[$command->planet->id])) {
                    foreach ($this->resources->snapshot($command->planet) as $slug => $resource) {
                        $rooms[$command->planet->id][$slug] = max(0.0, $resource['capacity'] - $resource['stock']);
                    }
                }
                $refund = [];
                foreach ($paid as $slug => $amount) {
                    $refund[$slug] = min($rooms[$command->planet->id][$slug] ?? 0, $amount * $this->catalog->rules()['cancellation_refund_ratio']);
                    $rooms[$command->planet->id][$slug] -= $refund[$slug];
                }
                $rows[] = ['item' => $command, 'label' => $definition['label'], 'refund' => $refund];
            };
            $collect($item);
            return ['item' => $item, 'rows' => $rows, 'resource_labels' => $this->catalog->rules()['resources']];
        });
    }

    public function cancel(PlayerUniverse $membership, int $id, string $key): void
    {
        $this->manager->transaction(function () use ($membership, $id, $key) {
            $item = $this->repo->find(QueueItem::class, $id);
            if (!$item) {
                return;
            }
            $this->guard->lock($membership, $item->planet);
            $this->vacations->assertCanManageQueues($membership);
            $this->settleMembershipLocked($membership);
            // Le rattrapage a pu terminer cette commande : la relire avant de l’annuler.
            $item = $this->repo->find(QueueItem::class, $id);
            if (!$item) {
                return;
            }
            if (!$this->guard->receipt($membership, $key)) {
                return;
            }
            if ($item->playerUniverse->id !== $membership->id) {
                throw new \DomainException('Commande inaccessible.');
            }
            $planet = $item->planet;
            $queueType = $item->queueType;
            $wasActive = $item->startedAt !== null;
            $this->removeTree($item);
            $this->manager->flush();
            if ($queueType === 'research') {
                if ($wasActive) {
                    $planet->researchQueueStartedAt = null;
                }
                $this->startNextResearch($membership, $this->clock->now());
            } elseif ($wasActive) {
                $next = $this->repo->queue($planet, $queueType)[0] ?? null;
                if ($next) {
                    $this->start($next, $this->clock->now());
                } else {
                    $field = $queueType . 'QueueStartedAt';
                    $planet->{$field} = null;
                }
            }
        });
    }

    /** Appelé avec la participation verrouillée par le service de vacances. */
    public function pauseForVacationLocked(PlayerUniverse $membership, \DateTimeImmutable $at, \DateTimeImmutable $until): void
    {
        $this->settleMembershipLocked($membership, $at);
        $this->shiftVacationHeadsLocked($membership, $until->getTimestamp() - $at->getTimestamp());
    }

    /** Au retour anticipé, retire seulement le report de durée qui n’a pas été consommé. */
    public function resumeAfterVacationLocked(PlayerUniverse $membership, int $unusedSeconds): void
    {
        if ($unusedSeconds > 0) {
            $this->shiftVacationHeadsLocked($membership, -$unusedSeconds);
        }
    }

    private function shiftVacationHeadsLocked(PlayerUniverse $membership, int $seconds): void
    {
        foreach ($this->repo->all(QueueItem::class, ['playerUniverse' => $membership]) as $item) {
            if ($item->startedAt === null) {
                continue;
            }
            $this->start($item, $item->startedAt->setTimestamp($item->startedAt->getTimestamp() + $seconds));
        }
    }

    private function removeTree(QueueItem $item): void
    {
        // Retirer d’abord les niveaux dépendants pour ne laisser aucune commande orpheline.
        foreach ($this->repo->all(QueueItem::class, ['dependsOn' => $item]) as $dependent) {
            $this->removeTree($dependent);
        }
        $group = match ($item->queueType) {
            'building' => 'buildings',
            'research' => 'technologies',
            default => isset($this->catalog->all('defenses')[$item->itemSlug]) ? 'defenses' : 'ships',
        };
        $paid = $item->paidCosts ?? $this->catalog->costs(
            $this->catalog->getTierForUniverse($item->playerUniverse->universe, $group, $item->itemSlug, $item->tier),
            $item->itemLevel ?? 1,
            $item->quantity,
            $group
        );
        $this->resources->refund($item->planet, $paid, $this->catalog->rules()['cancellation_refund_ratio']);
        $this->manager->remove($item);
    }

    /** Annulation de capture sans remboursement ; les commandes échues ont déjà été traitées sous verrou. */
    public function cancelForCaptureLocked(Planet $planet, ?PlayerUniverse $formerOwner): array
    {
        $this->reconstruction->stopLocked($planet);
        $items = [];
        $collect = function (QueueItem $item) use (&$collect, &$items, $formerOwner): void {
            if (isset($items[$item->id])) {
                return;
            }
            if ($formerOwner === null || $item->playerUniverse->id !== $formerOwner->id) {
                throw new \DomainException('Une commande de cette planète appartient à un autre empire.');
            }
            $items[$item->id] = $item;
            foreach ($this->repo->all(QueueItem::class, ['dependsOn' => $item]) as $dependent) {
                $collect($dependent);
            }
        };
        foreach ($this->repo->queue($planet) as $item) {
            $collect($item);
        }
        $journal = [];
        $planets = [$planet->id => $planet];
        foreach ($items as $item) {
            $journal[] = [
                'id' => $item->id,
                'planet_id' => $item->planet->id,
                'type' => $item->queueType,
                'slug' => $item->itemSlug,
                'tier' => $item->tier,
                'level' => $item->itemLevel,
                'quantity' => $item->quantity,
                'paid_costs' => $item->paidCosts,
            ];
            $planets[$item->planet->id] = $item->planet;
            $this->manager->remove($item);
        }
        $this->manager->flush();
        foreach ($planets as $affected) {
            foreach (['building', 'shipyard'] as $type) {
                $head = $this->repo->queue($affected, $type)[0] ?? null;
                if ($head !== null && $head->startedAt === null) {
                    $this->start($head, $this->clock->now());
                }
                $field = $type . 'QueueStartedAt';
                $affected->{$field} = $head?->startedAt;
            }
            $affected->researchQueueStartedAt = null;
            foreach ($this->repo->queue($affected, 'research') as $research) {
                if ($research->startedAt !== null) {
                    $affected->researchQueueStartedAt = $research->startedAt;
                    break;
                }
            }
        }
        if ($formerOwner !== null) {
            $this->startNextResearch($formerOwner, $this->clock->now());
        }
        return $journal;
    }
}
