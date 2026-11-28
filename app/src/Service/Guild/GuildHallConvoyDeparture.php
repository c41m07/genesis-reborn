<?php

declare(strict_types=1);

namespace App\Service\Guild;

use App\Entity\{Fleet, FleetShip, GuildHall, GuildHallConvoy, Planet, PlayerTechnology, PlayerUniverse};
use App\Manager\GameManager;
use App\Repository\GameRepository;
use App\Service\Catalog\GameCatalog;
use App\Service\Colony\{ActionGuard, ResourceService};
use App\Service\Combat\TechnologyEffectCalculator;
use App\Service\Fleet\{FleetDispatch, FleetHold};
use Psr\Clock\ClockInterface;

/** Départ dédié au stockage de guilde, sans ajouter de destination au formulaire de flotte général. */
final readonly class GuildHallConvoyDeparture
{
    public function __construct(
        private GameManager $manager,
        private GameRepository $repository,
        private ActionGuard $guard,
        private GuildHallAccess $access,
        private GuildHallFlight $flight,
        private GuildAtelierDonations $donations,
        private GuildHallScheduleProvider $schedules,
        private GameCatalog $catalog,
        private TechnologyEffectCalculator $effects,
        private FleetHold $hold,
        private ResourceService $resources,
        private FleetDispatch $dispatch,
        private ClockInterface $clock,
    ) {
    }

    /** @param array<string,array<string,int>> $composition @param array<string,int> $freight */
    public function send(
        PlayerUniverse $member,
        int $guildId,
        int $hallId,
        int $originId,
        array $composition,
        float $ratio,
        array $freight,
        string $requestKey,
        ?string $reviewedQuote = null,
        bool $shipDonation = false,
        bool $commandStation = false,
    ): ?GuildHallConvoy {
        return $this->manager->transaction(function () use ($member, $guildId, $hallId, $originId, $composition, $ratio, $freight, $requestKey, $reviewedQuote, $shipDonation, $commandStation): ?GuildHallConvoy {
            $hall = $this->access->lockPlaced($member, $guildId, $hallId);
            if ($this->schedules->schedule() === null) {
                throw new \DomainException('Les opérations du Hall ne sont pas encore configurées.');
            }
            $origin = $this->repository->find(Planet::class, $originId);
            if (!$origin instanceof Planet || $origin->owner?->id !== $member->id
                || $origin->solarSystem->galaxy->universe->id !== $member->universe->id) {
                throw new \DomainException('Choisissez une colonie de votre empire.');
            }
            $this->guard->lock($member, $origin, currentReads: true);
            if (!$this->guard->receipt($member, $requestKey)) {
                return null;
            }
            [$idle, $orders, $now, $route, $fuel, $costs] = $this->prepare($member, $origin, $hall, $composition, $ratio, $freight, $shipDonation, $commandStation);
            $quote = $route['movement'];
            if ($reviewedQuote !== null && !hash_equals($reviewedQuote, self::fingerprint($quote, $fuel))) {
                throw new \DomainException('Le trajet ou le carburant a changé. Demandez un nouveau devis.');
            }
            $this->resources->consume($origin, $costs, lockStocks: true);
            $idle->storedFuel = 0;

            $fleet = new Fleet();
            $fleet->playerUniverse = $member;
            $fleet->homePlanet = $origin;
            $fleet->destinationSystem = $hall->solarSystem;
            $fleet->status = 'traveling';
            $fleet->mission = GuildHallConvoy::MISSION;
            $fleet->speedRatio = $ratio;
            $fleet->cargo = $freight;
            $fleet->cargo['hydrogen'] = ($freight['hydrogen'] ?? 0) + $quote['total_fuel'];
            $fleet->reservedFuel = $quote['total_fuel'];
            $fleet->travelSeconds = $quote['seconds'];
            $fleet->departureAt = $now;
            $fleet->arrivalAt = $now->modify('+'.$fleet->travelSeconds.' seconds');
            $fleet->navigation = $route['navigation'];
            $this->manager->save($fleet);

            foreach ($orders as [$stock, $amount]) {
                $stock->quantity -= $amount;
                $ship = new FleetShip();
                $ship->fleet = $fleet;
                $ship->slug = $stock->slug;
                $ship->tier = $stock->tier;
                $ship->quantity = $amount;
                $this->manager->save($ship);
            }

            // Le reçu voit le jeton réellement publié, pas un jeton généré par le formulaire.
            $this->manager->flush();
            $receipt = new GuildHallConvoy();
            $receipt->fleet = $fleet;
            $receipt->hallId = $hallId;
            $receipt->guildId = $guildId;
            $receipt->shipDonation = $shipDonation;
            $receipt->commandStation = $commandStation;
            $this->dispatch->schedule($fleet, $receipt);
            $this->manager->save($receipt);

            return $receipt;
        });
    }

    /**
     * Devis sans engagement : aucun reçu, aucun débit ni message de départ.
     * @param array<string,array<string,int>> $composition
     * @param array<string,int> $freight
     * @return array{seconds:int,total_fuel:float,planet_fuel:float,freight_capacity:int,fingerprint:string}
     */
    public function preview(PlayerUniverse $member, int $guildId, int $hallId, int $originId, array $composition, float $ratio, array $freight, bool $shipDonation = false, bool $commandStation = false): array
    {
        return $this->manager->transaction(function () use ($member, $guildId, $hallId, $originId, $composition, $ratio, $freight, $shipDonation, $commandStation): array {
            $hall = $this->access->lockPlaced($member, $guildId, $hallId);
            if ($this->schedules->schedule() === null) {
                throw new \DomainException('Les opérations du Hall ne sont pas encore configurées.');
            }
            $origin = $this->repository->find(Planet::class, $originId);
            if (!$origin instanceof Planet || $origin->owner?->id !== $member->id
                || $origin->solarSystem->galaxy->universe->id !== $member->universe->id) {
                throw new \DomainException('Choisissez une colonie de votre empire.');
            }
            $this->guard->lock($member, $origin, currentReads: true);

            [, , $now, $route, $fuel, $costs] = $this->prepare($member, $origin, $hall, $composition, $ratio, $freight, $shipDonation, $commandStation);
            $stocks = $this->resources->snapshot($origin, observedAt: $now);
            foreach ($costs as $slug => $amount) {
                if (($stocks[$slug]['stock'] ?? 0) + 0.000001 < $amount) {
                    throw new \DomainException('Stock insuffisant : '.($stocks[$slug]['label'] ?? $slug).'.');
                }
            }
            $quote = $route['movement'];

            return [
                'seconds' => $quote['seconds'],
                'total_fuel' => $quote['total_fuel'],
                'planet_fuel' => $fuel['planet_fuel'],
                'freight_capacity' => (int) floor($quote['freight_capacity']),
                'fingerprint' => self::fingerprint($quote, $fuel),
            ];
        });
    }

    /** L’appelant détient les verrous du Hall et de la colonie jusqu’à la fin de sa transaction. */
    private function prepare(
        PlayerUniverse $member,
        Planet $origin,
        GuildHall $hall,
        array $composition,
        float $ratio,
        array $freight,
        bool $shipDonation,
        bool $commandStation,
    ): array {
        if ($commandStation && ($shipDonation || $freight !== [] || !GuildCommandBerth::built($this->repository, $hall))) {
            throw new \DomainException('Le stationnement nécessite un Centre terminé et ne transporte pas de fret.');
        }
        if ($shipDonation && ($freight !== [] || !$this->donations->isBuiltLocked($hall))) {
            throw new \DomainException('Le don nécessite un Atelier terminé et ne transporte pas de fret.');
        }
        $idle = $this->repository->one(Fleet::class, ['homePlanet' => $origin, 'playerUniverse' => $member, 'status' => 'idle']);
        if (!$idle instanceof Fleet) {
            throw new \DomainException('Aucun vaisseau au sol sur cette colonie.');
        }
        $this->manager->lockCurrent($idle);
        $config = $this->catalog->forUniverse($member->universe);
        $remaining = (int) $config['limits']['max_fleet_size'];
        $ships = $orders = [];
        foreach ($composition as $slug => $ranks) {
            foreach ($ranks as $tier => $amount) {
                if ($amount === 0) {
                    continue;
                }
                if (!is_int($amount) || $amount < 1 || $amount > $remaining
                    || !isset($config['catalog']['ships'][$slug]) || !in_array($tier, GameCatalog::TIERS, true)) {
                    throw new \DomainException('Composition du convoi invalide.');
                }
                $stock = $this->repository->one(FleetShip::class, ['fleet' => $idle, 'slug' => $slug, 'tier' => $tier]);
                if (!$stock instanceof FleetShip) {
                    throw new \DomainException('Vaisseaux indisponibles.');
                }
                $this->manager->lockCurrent($stock);
                if ($stock->quantity < $amount) {
                    throw new \DomainException('Vaisseaux indisponibles.');
                }
                $ship = $this->catalog->getTierForUniverse($member->universe, 'ships', $slug, $tier);
                if (($ship['mobile'] ?? true) === false
                    || (($shipDonation || $commandStation) && isset($ship['economic_unit']))
                    || (!$shipDonation && !$commandStation && isset($ship['allowed_missions']) && !in_array('transport', $ship['allowed_missions'], true))) {
                    throw new \DomainException(($shipDonation || $commandStation)
                        ? 'Cette opération nécessite des vaisseaux mobiles non plafonnés.'
                        : 'Ce vaisseau ne peut pas transporter de fret.');
                }
                $ships[] = array_replace($ship, ['slug' => $slug, 'tier' => $tier, 'quantity' => $amount]);
                $orders[] = [$stock, $amount];
                $remaining -= $amount;
            }
        }
        foreach ($freight as $material => $amount) {
            if (!array_key_exists($material, GameCatalog::MATERIAL_WEIGHTS) || !is_int($amount) || $amount < 0 || $amount > 100000000) {
                throw new \DomainException('Le convoi n’accepte que des quantités entières de matériaux.');
            }
        }
        if (!$shipDonation && !$commandStation && array_sum($freight) <= 0) {
            throw new \DomainException('Chargez au moins un matériau.');
        }

        $tech = $this->repository->levelsByTier(PlayerTechnology::class, ['playerUniverse' => $member]);
        $ships = $this->effects->applyToShips($ships, $tech, $member->universe);
        $now = $this->clock->now()->setTimezone(new \DateTimeZone('UTC'));
        $route = $this->flight->quote(
            $origin, $hall, $ships, $ratio, $config['multipliers']['travel_time'], $this->catalog->rules()['fuel_ratio_policy'], $now,
        );
        $quote = $route['movement'];
        if ($quote['total_fuel'] > $quote['capacity'] || array_sum($freight) > $quote['freight_capacity']) {
            throw new \DomainException('La soute ne peut pas contenir le fret et le carburant aller-retour.');
        }
        $fuel = $this->hold->departure($idle, $composition, $quote['total_fuel']);
        $costs = $freight;
        $costs['hydrogen'] = ($costs['hydrogen'] ?? 0) + $fuel['planet_fuel'];

        return [$idle, $orders, $now, $route, $fuel, $costs];
    }

    private static function fingerprint(array $quote, array $fuel): string
    {
        return hash('sha256', json_encode([
            $quote['seconds'], $quote['total_fuel'], $quote['freight_capacity'], $fuel['planet_fuel'],
        ], JSON_THROW_ON_ERROR));
    }
}
