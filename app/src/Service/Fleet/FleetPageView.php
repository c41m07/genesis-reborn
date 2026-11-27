<?php

namespace App\Service\Fleet;

use App\Service\Player\FirstStepRewardPolicy;
use App\Entity\{Fleet, FleetShip, GuildHallConvoy, Planet, PlayerUniverse, SolarSystem};
use App\Repository\GameRepository;
use App\Service\Catalog\GameCatalog;
use App\Service\Colony\EmpireDetails;

/** Lectures propres à chaque page ; les envois et leurs validations restent dans FleetService. */
final class FleetPageView
{
    public function __construct(
        private GameRepository $repo,
        private GameCatalog $catalog,
        private EmpireDetails $details,
        private DestinationCatalogue $destinations,
        private OrbitRequestView $orbits,
        private \App\Service\Market\MarketTraffic $marketTraffic,
        private FleetCompositionService $compositions,
    ) {
    }

    /**
     * @param array{mission:string,system:int,planet:int,composition:int,origin:int,confirmation:string,adjust:bool,page:int,colony:int,focus:int,tutorial_spy?:bool} $parameters
     * @return array<string, mixed> Données privées de la destination, ou composition_review si une nouvelle confirmation est nécessaire.
     */
    public function prepare(PlayerUniverse $member, int $selectedId, string $page, array $parameters): array
    {
        $config = $this->catalog->forUniverse($member->universe);
        $planets = $this->repo->planets($member->universe, $member);
        // origin est aussi utilisé par les raccourcis historiques de la liste des colonies.
        $colonyId = $parameters['colony'] ?: $parameters['origin'];
        if ($colonyId) {
            if (!array_filter($planets, fn(Planet $planet): bool => $planet->id === $colonyId)) {
                throw new \DomainException('Colonie inaccessible dans cet univers.');
            }
            $selectedId = $colonyId;
        }
        $selectedPlanet = $planets[0] ?? null;
        foreach ($planets as $candidate) {
            if ($candidate->id === $selectedId) {
                $selectedPlanet = $candidate;
            }
        }
        $tutorialSpy = $page === 'prepare' && $parameters['mission'] === 'spy' && ($parameters['tutorial_spy'] ?? false);
        $data = [
            'tutorial_spy' => $tutorialSpy,
            'membership' => $member, 'fleet_page' => $page, 'fleet_planet' => $selectedPlanet,
            'fleet_colony_scope' => $page !== 'missions', 'idle' => [], 'holds' => [], 'held_fleets' => [], 'held_details' => [], 'held_hall_convoys' => [],
            'active_fleets' => [], 'fleet_details' => [], 'combat_events' => [], 'orbit_requests' => [],
            'active_fleet_count' => 0, 'missions_page' => 1, 'missions_pages' => 1,
            'catalog' => [], 'resources' => [], 'systems' => [], 'destination_choices' => [],
            'loaded_composition' => [], 'loaded_origin' => 0,
            'prefill' => ['mission' => 'explore', 'system' => 0, 'planet' => 0, 'coordinates' => '', 'mode' => 'custom'],
            'fuel_pending' => $this->catalog->rules()['fuel_ratio_policy'] === 'pending',
            'fleet_limit' => (int) $config['limits']['max_fleet_size'],
            'conquest_enabled' => (bool) ($config['features']['planet_conquest'] ?? false),
        ];
        if ($page === 'prepare') {
            $mission = $parameters['mission'];
            if (!in_array($mission, DestinationCatalogue::MISSIONS, true)) {
                throw new \DomainException('Mission indisponible.');
            }
            $system = $parameters['system'] ? $this->repo->find(SolarSystem::class, $parameters['system']) : null;
            if ($parameters['system'] && (!$system || $system->galaxy->universe->id !== $member->universe->id)) {
                throw new \DomainException('Système inaccessible.');
            }
            $target = $parameters['planet'] ? $this->repo->find(Planet::class, $parameters['planet']) : null;
            if ($parameters['planet'] && (!$target || !$this->destinations->canPrefill($member, $target, $mission)
                || ($tutorialSpy && !FirstStepRewardPolicy::isNativeNpc($target)))) {
                throw new \DomainException('Destination inaccessible.');
            }
            $choices = $this->destinations->choices($member, $mission, tutorialSpy: $tutorialSpy);
            $coordinates = '';
            $mode = $choices['modes'][0];
            if ($mission === 'explore' && $parameters['system']) {
                $coordinates = $system->galaxy->coordinateCode().':'.$system->number();
                $mode = 'custom';
            } elseif ($target) {
                $coordinates = $mission === 'explore' ? $target->solarSystem->galaxy->coordinateCode().':'.$target->solarSystem->number() : $target->coordinates();
                $category = $this->destinations->category($member, $target, $mission);
                $mode = $this->destinations->allows($member, $target, $mission) && in_array($category, $choices['modes'], true) ? $category : 'custom';
            }
            if (!$tutorialSpy && $parameters['composition']) {
                $saved = $this->compositions->owned($member, $parameters['composition']);
                $source = $this->repo->find(Planet::class, $parameters['origin']);
                if (!$source) {
                    throw new \DomainException('Colonie inaccessible.');
                }
                $plan = $this->compositions->plan($member, $saved, $source);
                // Le chargement reste explicite : une disponibilité modifiée impose une nouvelle vérification.
                if (!hash_equals($plan['fingerprint'], $parameters['confirmation']) || ($plan['missing'] && !$parameters['adjust'])) {
                    return ['composition_review' => ['id' => $saved->id, 'origin' => $source->id]];
                }
                $data['loaded_composition'] = $plan['missing'] ? $plan['available'] : $saved->ships;
                $data['loaded_origin'] = $source->id;
                $selectedPlanet = $source;
                $data['fleet_planet'] = $source;
            }
            $data['destination_choices'] = $choices;
            $data['prefill'] = ['mission' => $mission, 'system' => $parameters['system'], 'planet' => $parameters['planet'], 'coordinates' => $coordinates, 'mode' => $mode];
        }
        if ($selectedPlanet && in_array($page, ['prepare', 'holds'], true)) {
            $data['holds'] = $this->repo->all(Fleet::class, ['playerUniverse' => $member, 'homePlanet' => $selectedPlanet, 'status' => 'idle']);
            $shipsByFleet = [];
            foreach ($data['holds'] ? $this->repo->all(FleetShip::class, ['fleet' => $data['holds']]) : [] as $ship) {
                if ($ship->quantity > 0 && ($config['catalog']['ships'][$ship->slug]['mobile'] ?? true)
                    && (!$tutorialSpy || ($ship->slug === 'probe' && $ship->tier === 'basic'))) {
                    $shipsByFleet[$ship->fleet->id][] = $ship;
                }
            }
            foreach ($data['holds'] as $fleet) {
                if (isset($shipsByFleet[$fleet->id])) {
                    $data['idle'][] = ['fleet' => $fleet, 'ships' => $shipsByFleet[$fleet->id]];
                }
            }
            $data['catalog'] = $this->catalog->all('ships');
            $data['resources'] = $this->catalog->rules()['resources'];
        }
        if (in_array($page, ['missions', 'prepare'], true)) {
            // La liste suit la participation même lorsque l’ancienne origine n’est plus possédée.
            // La préparation garde trois missions de la colonie, sans détail de composition, fret ou combat.
            $missions = $page === 'missions'
                ? $this->repo->fleetMissionPage($member, null, $parameters['page'], focusId: $parameters['focus'], allColonies: true)
                : $this->repo->fleetMissionPage($member, $selectedPlanet, 1, limit: 3);
            $data['active_fleets'] = $missions['fleets'];
            $data['active_fleet_count'] = $missions['count'];
            $data['missions_page'] = $missions['page'];
            $data['missions_pages'] = $missions['pages'];
            if ($page === 'missions') {
                $data['market_flights'] = $this->marketTraffic->forViewer($member);
                $data['fleet_details'] = $this->details->fleets($member, $data['active_fleets']);
                $data['combat_events'] = $this->repo->fleetCombatEvents($member, $data['active_fleets']);
            }
        }
        if ($page === 'orbits' && $selectedPlanet) {
            $data['orbit_requests'] = $this->orbits->forMember($member, $selectedPlanet);
        }
        if ($page === 'holds') {
            // Une origine perdue ne doit pas faire disparaître les stationnements en attente de l’empire.
            $data['held_fleets'] = $this->repo->all(Fleet::class, ['playerUniverse' => $member, 'mission' => 'station', 'status' => 'station_waiting'], ['id' => 'ASC']);
            $data['held_hall_convoys'] = $this->repo->all(Fleet::class, [
                'playerUniverse' => $member, 'mission' => GuildHallConvoy::MISSION, 'status' => 'hall_wait',
            ], ['id' => 'ASC']);
            $data['held_details'] = $this->details->fleets($member, [...$data['held_fleets'], ...$data['held_hall_convoys']]);
        }
        return $data;
    }
}
