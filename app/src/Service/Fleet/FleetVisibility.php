<?php
namespace App\Service\Fleet;

use App\Entity\{Fleet,FleetShip,Galaxy,Planet,PlayerDiscovery,PlayerUniverse};
use App\Repository\GameRepository;
use App\Service\Catalog\GameCatalog;
use App\Service\Player\{LeaderboardService,RelationResolver};
use Psr\Clock\ClockInterface;

final class FleetVisibility
{
    private const MISSIONS=['explore'=>'Exploration','spy'=>'Espionnage','colonize'=>'Colonisation','transport'=>'Transport','attack'=>'Attaque','conquer'=>'Conquête JcE','defend'=>'Défense','orbit'=>'Mise en orbite','station'=>'Stationnement'];
    private const STATUSES=['traveling'=>'En vol','returning'=>'Retour','attacking'=>'Combat','defending'=>'Défense','orbiting'=>'En orbite','occupying'=>'Occupation JcE','orbit_pending'=>'Demande d’orbite','station_waiting'=>'Stationnement en attente'];

    public function __construct(private GameRepository $repo,private RelationResolver $relations,private AttackDetection $detection,
        private GameCatalog $catalog,private LeaderboardService $leaderboard,private ClockInterface $clock,
        private TransportService $transports, private OrbitPolicy $orbitPolicy, private OrbitRequestState $orbitRequests) {}

    /** @return array{flights:list<array>, guildFilterAvailable:bool} */
    public function forMap(PlayerUniverse $viewer, ?Galaxy $galaxy): array
    {
        if (!$galaxy || !$this->canRead($viewer) || $galaxy->universe->id !== $viewer->universe->id) {
            return ['flights' => [], 'guildFilterAvailable' => false];
        }
        $known = $this->knownSystems($viewer);
        $level = $this->detection->level($viewer);
        $now = $this->clock->now();
        $fleets = $this->repo->activeFleetsInUniverse($viewer->universe);
        $guilds = $this->mapGuilds($viewer, $fleets);
        $viewerGuild = $guilds[$viewer->id] ?? null;
        $rows = [];
        $allies = [];
        foreach ($fleets as $fleet) {
            $origin = $this->origin($fleet)->solarSystem;
            $destination = $fleet->destination?->solarSystem ?? $fleet->destinationSystem;
            if (!$destination || ($origin->galaxy->id !== $galaxy->id && $destination->galaxy->id !== $galaxy->id)) {
                continue;
            }
            $owner = $fleet->playerUniverse;
            $guildPeer = $viewerGuild !== null && $owner->id !== $viewer->id
                && ($guilds[$owner->id] ?? null) === $viewerGuild;
            $guildFlight = $guildPeer && isset($known[$origin->id], $known[$destination->id]);
            $allies[$owner->id] ??= $guildPeer || $this->relations->allied($viewer, $owner);
            if (!$this->visible($viewer, $fleet, $known, $level, $now, $allies[$owner->id], $guildFlight)) {
                continue;
            }

            // Le contact de guilde n'emprunte pas le chargement des effectifs du radar.
            $row = $this->row($viewer, $fleet, $known, shareGuildTrack: $guildFlight);
            // Une attaque détectée ne révèle ni son départ ni sa progression précise.
            $row['localLegs'] = $row['departureAt'] && !($row['recallRoute']['hidden'] ?? false)
                ? $this->localLegs($fleet, $known, $row['ownedByCurrentPlayer']) : [];
            $rows[] = $row;
        }

        return ['flights' => $rows, 'guildFilterAvailable' => $viewerGuild !== null];
    }

    /**
     * Relire les adhésions actuelles à chaque réponse de carte, en une requête.
     * Une alliance personnelle ou entre guildes ne donne pas accès aux sondes.
     * @param list<Fleet> $fleets
     * @return array<int, int>
     */
    private function mapGuilds(PlayerUniverse $viewer, array $fleets): array
    {
        if (!$this->catalog->forUniverse($viewer->universe)['features']['guilds_enabled']) {
            return [];
        }
        $memberships = [$viewer->id => $viewer];
        foreach ($fleets as $fleet) {
            $owner = $fleet->playerUniverse;
            if ($owner->isPlayerControlled() && $owner->player->lockedAt === null) {
                $memberships[$owner->id] = $owner;
            }
        }
        $guilds = [];
        foreach ($this->repo->guildMemberships(array_values($memberships), $viewer->universe) as $member) {
            $guilds[$member->playerUniverse->id] = $member->guild->id;
        }

        return $guilds;
    }

    /**
     * Les durées figées au départ placent les frontières de l’hyperespace sur la progression du vol.
     * La carte ne reçoit que les segments locaux autorisés, sans carburant ni vitesse des vaisseaux.
     * @return list<array{systemId:int, from:int, to:int, start:float, end:float}>
     */
    private function localLegs(Fleet $fleet, array $known, bool $own): array
    {
        $navigation = $fleet->navigation;
        $duration = $navigation['seconds'] ?? 0;
        $segments = [];
        $elapsed = 0.0;
        if ($duration <= 0 || !in_array($fleet->status, ['traveling', 'returning'], true)) {
            return [];
        }
        foreach ($navigation['legs'] ?? [] as $leg) {
            $start = $elapsed / $duration;
            $elapsed += $leg['seconds'];
            $systemId = $leg['from'][0];
            if ($leg['hyper'] || $systemId !== $leg['to'][0] || (!$own && !isset($known[$systemId]))) {
                continue;
            }
            $segments[] = [
                'systemId' => $systemId,
                'from' => $leg['from'][4] ?? 10,
                'to' => $leg['to'][4] ?? 10,
                'start' => $start,
                'end' => $elapsed / $duration,
            ];
        }

        return $segments;
    }

    public function incoming(PlayerUniverse $viewer, bool $includeDetails = true): array
    {
        if (!$this->canRead($viewer)) return [];
        $known = $this->knownSystems($viewer);
        $level = $this->detection->level($viewer);
        $now = $this->clock->now();
        $missions = array_values(array_diff(array_keys(self::MISSIONS), ['spy']));
        $visible = [];
        foreach ($this->repo->incomingFleets($viewer, $missions) as $fleet) {
            // Réutiliser les mêmes protections que la carte : origine découverte,
            // univers, espionnage privé et préavis des attaques restent autoritaires.
            if ($this->visible($viewer, $fleet, $known, $level, $now, false)) $visible[] = $fleet;
        }
        if ($visible === []) return [];
        // Les attaques détectées restent prioritaires quand les transports s’ajoutent au panneau.
        usort($visible, static function (Fleet $left, Fleet $right): int {
            $leftHostile = in_array($left->mission, ['attack', 'conquer'], true);
            $rightHostile = in_array($right->mission, ['attack', 'conquer'], true);
            return ($rightHostile <=> $leftHostile)
                ?: (($left->arrivalAt?->getTimestamp() ?? PHP_INT_MAX) <=> ($right->arrivalAt?->getTimestamp() ?? PHP_INT_MAX))
                ?: ($left->id <=> $right->id);
        });
        // Charger les effectifs seulement pour les flottes visibles, en une lecture ciblée.
        $shipsByFleet = [];
        foreach ($this->repo->all(FleetShip::class, ['fleet'=>$visible]) as $ship) {
            $shipsByFleet[$ship->fleet->id][] = $ship;
        }
        $transportIds = [];
        foreach ($visible as $fleet) {
            if ($fleet->mission === 'transport') {
                $transportIds[] = $fleet->id;
            }
        }
        $reports = $this->repo->incomingTransportReportStates($viewer, $transportIds);
        $detailIds = [];
        $enemies = [];
        if ($includeDetails) {
            foreach ($visible as $fleet) {
                if ($this->canShareDeparture($viewer, $fleet, $known, $now, isset($reports[$fleet->id]), $enemies)) {
                    $detailIds[] = $fleet->id;
                }
            }
        }
        $departures = $this->repo->incomingFleetDepartureDetails($viewer, $detailIds);
        $rows = [];
        foreach ($visible as $fleet) {
            $row = $this->row($viewer, $fleet, $known, $shipsByFleet[$fleet->id] ?? []);
            // Le retrait du rapport masque le lien, pas la flotte ni son manifeste autorisé.
            if (isset($reports[$fleet->id]) && !$reports[$fleet->id]['removed']) {
                $row['transportReportId'] = $reports[$fleet->id]['id'];
            }
            if (isset($departures[$fleet->id])) {
                $detail = $this->departureDetail($viewer, $fleet->mission, $departures[$fleet->id]);
                if ($detail !== null) {
                    $row['incomingDetail'] = $detail;
                }
            }
            $rows[] = $row;
        }
        return $rows;
    }

    /** @param array<int, bool> $enemies Relation relue une fois par expéditeur. */
    private function canShareDeparture(
        PlayerUniverse $viewer,
        Fleet $fleet,
        array $known,
        \DateTimeImmutable $now,
        bool $hasTransportReport,
        array &$enemies,
    ): bool {
        $sender = $fleet->playerUniverse;
        if (!in_array($fleet->mission, ['transport', 'defend', 'orbit'], true)
            || $fleet->status !== 'traveling' || !$fleet->arrivalAt || $fleet->arrivalAt <= $now
            || !$sender->isPlayerControlled() || $sender->player->lockedAt !== null
            || $sender->universe->id !== $viewer->universe->id
            || $fleet->destination?->owner?->id !== $viewer->id
            || !isset($known[$fleet->homePlanet->solarSystem->id])
        ) {
            return false;
        }

        $enemies[$sender->id] ??= $this->relations->enemy($viewer, $sender);
        if ($enemies[$sender->id]) {
            return false;
        }
        if ($fleet->mission === 'transport') {
            return $hasTransportReport && $this->transports->canReceive($sender, $fleet->destination);
        }
        if ($fleet->mission === 'defend') {
            // Un renfort envoyé à un occupant peut viser une colonie dont le
            // propriétaire n’est pas l’hôte de la mission.
            return $fleet->orbitDurationSeconds === 0;
        }

        $request = $this->orbitRequests->read($fleet);
        return ($request['recipient_id'] ?? null) === $viewer->id
            && ($request['planet_id'] ?? null) === $fleet->destination->id
            && in_array($request['status'] ?? null, ['pending', 'accepted_manual', 'accepted_auto'], true)
            && $this->orbitPolicy->canRequest($sender, $fleet->destination);
    }

    /**
     * @param array{ships:array, freight:array} $departure
     * @return array{ships:list<array{label:string,tier:string,quantity:int}>,resources:array<string,array{label:string,stock:float}>}|null
     */
    private function departureDetail(PlayerUniverse $viewer, string $mission, array $departure): ?array
    {
        $definitions = $this->catalog->forUniverse($viewer->universe)['catalog']['ships'];
        $tiers = $this->catalog->tiers();
        $ships = [];
        foreach ($departure['ships'] as $ship) {
            if (!is_array($ship) || !is_string($ship['slug'] ?? null) || !is_string($ship['tier'] ?? null)
                || !is_int($ship['quantity'] ?? null) || $ship['quantity'] <= 0
                || !isset($definitions[$ship['slug']]['label'], $tiers[$ship['tier']]['label'])
            ) {
                return null;
            }
            $key = $ship['slug'].':'.$ship['tier'];
            $ships[$key] ??= [
                'label' => $definitions[$ship['slug']]['label'],
                'tier' => $tiers[$ship['tier']]['label'],
                'quantity' => 0,
            ];
            $ships[$key]['quantity'] += $ship['quantity'];
        }
        if ($ships === []) {
            return null;
        }

        if ($mission !== 'transport') {
            // Les ordres de défense et d’orbite n’autorisent pas de fret.
            return ['ships' => array_values($ships), 'resources' => []];
        }
        $freight = [];
        foreach ($this->catalog->rules()['resources'] as $slug => $label) {
            // Le fret au départ ne contient que la charge choisie. Le stock
            // d’hydrogène brut de la flotte y ajouterait la réserve de carburant.
            $value = $departure['freight'][$slug] ?? 0;
            if ((is_float($value) || is_int($value)) && is_finite((float) $value) && $value > 0) {
                $freight[$slug] = ['label' => $label, 'stock' => (float) $value];
            }
        }
        return ['ships' => array_values($ships), 'resources' => $freight];
    }

    private function canRead(PlayerUniverse $viewer): bool
    {
        return $viewer->isPlayerControlled() && $viewer->player->lockedAt===null && $viewer->universe->isAccessible($this->clock->now());
    }

    private function knownSystems(PlayerUniverse $viewer): array
    {
        $known=[];
        foreach($this->repo->all(PlayerDiscovery::class,['playerUniverse'=>$viewer]) as $discovery)
            if($discovery->solarSystem->galaxy->universe->id===$viewer->universe->id) $known[$discovery->solarSystem->id]=true;
        foreach($this->repo->planets($viewer->universe,$viewer) as $planet) $known[$planet->solarSystem->id]=true;
        return $known;
    }

    private function visible(PlayerUniverse $viewer,Fleet $fleet,array $known,int $level,\DateTimeImmutable $now,bool $allied, bool $guildFlight = false): bool
    {
        $origin=$this->origin($fleet)->solarSystem;$target=$fleet->destination?->solarSystem??$fleet->destinationSystem;
        if(!isset(self::STATUSES[$fleet->status]) || !$target || $fleet->playerUniverse->universe->id!==$viewer->universe->id
            || $origin->galaxy->universe->id!==$viewer->universe->id || $target->galaxy->universe->id!==$viewer->universe->id) return false;
        if($fleet->playerUniverse->id===$viewer->id) return true;
        // Même guilde et destinations déjà connues : seule la carte reçoit ces sondes.
        if($fleet->mission==='spy') return $guildFlight;
        $recipient=$fleet->destination?->owner?->id===$viewer->id;
        if($recipient && $fleet->status==='traveling' && in_array($fleet->mission,['attack','conquer'],true)) return $this->detection->detected($fleet,$now,$level);
        return $recipient || ($allied && isset($known[$origin->id],$known[$target->id]));
    }

    private function origin(Fleet $fleet): Planet
    {
        $id=$fleet->status==='station_waiting'?($fleet->navigation['station']['position_planet_id']??0):($fleet->navigation['station']['route_origin_id']??0);
        $planet=$fleet->mission==='station' && $id?$this->repo->find(Planet::class,$id):null;
        return $planet && $planet->solarSystem->galaxy->universe->id===$fleet->playerUniverse->universe->id?$planet:$fleet->homePlanet;
    }

    /** @param list<FleetShip>|null $preparedShips */
    private function row(PlayerUniverse $m,Fleet $fleet,array $known,?array $preparedShips=null, bool $shareGuildTrack = false): array
    {
        $originPlanet=$this->origin($fleet);$originSystem=$originPlanet->solarSystem;$destinationSystem=$fleet->destination?->solarSystem??$fleet->destinationSystem;
        $isOwnFleet=$fleet->playerUniverse->id===$m->id;$targetsOwnColony=$fleet->destination?->owner?->id===$m->id;
        $ships = $shareGuildTrack ? [] : array_values(array_filter(
            $preparedShips ?? $this->repo->all(FleetShip::class, ['fleet' => $fleet]),
            static fn (FleetShip $ship): bool => $ship->quantity > 0,
        ));
        $shipsSummary = $isOwnFleet ? implode(' · ', array_map(fn (FleetShip $ship): string => $ship->quantity.' × '.str_replace('_', ' ', $ship->slug).' ('.$this->catalog->tiers()[$ship->tier]['label'].')', $ships)) : null;
        $relationLabel = $isOwnFleet ? 'Votre empire' : ($targetsOwnColony ? 'Flotte entrante' : 'Allié');
        $recallRoute=null;
        if(isset($fleet->navigation['recall']) && $fleet->status==='returning') {
            $returnPlanet=$fleet->returnPlanet??$fleet->homePlanet;
            $canSeeRoute=$isOwnFleet || (isset($known[$originSystem->id],$known[$destinationSystem->id],$known[$returnPlanet->solarSystem->id]));
            $recallRoute=$canSeeRoute?['startProgress'=>(float)$fleet->navigation['recall']['progress'],
                'returnSystemId'=>$returnPlanet->solarSystem->id,'returnName'=>$returnPlanet->name,
                'returnPosition'=>$returnPlanet->position]:['hidden'=>true];
        }
        $progressState=($fleet->navigation['active']['status']??null)===$fleet->status?$fleet->navigation['active']:null;

        $seeOrigin=$isOwnFleet || isset($known[$originSystem->id]);
        $incomingAttack=!$isOwnFleet && $targetsOwnColony && in_array($fleet->mission,['attack','conquer'],true) && $fleet->status==='traveling';
        $row = [
            'id' => $fleet->id,
            'mission' => $fleet->mission,
            'missionLabel' => self::MISSIONS[$fleet->mission] ?? 'Mission',
            'status' => $fleet->status,
            'statusLabel' => self::STATUSES[$fleet->status],
            'relationLabel' => $shareGuildTrack ? 'Guilde' : $relationLabel,
            'ownerName' => $fleet->playerUniverse->getDisplayName(),
            'originSystemId' => $seeOrigin?$originSystem->id:null,
            'originGalaxy' => $seeOrigin?$originSystem->galaxy->coordinateCode():null,
            'originX' => $seeOrigin?$originSystem->coordX:null,
            'originY' => $seeOrigin?$originSystem->coordY:null,
            'originName' => $seeOrigin?$originPlanet->name:'Origine non découverte',
            'originCoordinates' => $seeOrigin?$originPlanet->coordinates():null,
            'destinationPlanetId' => $fleet->destination?->id,
            'destinationGalaxyId' => $destinationSystem->galaxy->id,
            'destinationSystemId' => $destinationSystem->id,
            'destinationGalaxy' => $destinationSystem->galaxy->coordinateCode(),
            'destinationX' => $destinationSystem->coordX,
            'destinationY' => $destinationSystem->coordY,
            'destinationName' => $fleet->destination?->name ?? $destinationSystem->name,
            'destinationCoordinates' => $fleet->destination?->coordinates() ?? ($destinationSystem->galaxy->coordinateCode().':'.$destinationSystem->number()),
            'recallRoute'=>$recallRoute,
            'forwardRoute'=>$fleet->mission==='station' && isset($fleet->navigation['station']['route_origin_id']),
            'progressBase'=>$incomingAttack?null:($progressState['progress']??null),
            'progressAt'=>$incomingAttack?null:($progressState['at']??null),
            'departureAt' => $incomingAttack?null:$fleet->departureAt?->format(\DateTimeInterface::ATOM),
            'arrivalAt' => $fleet->arrivalAt?->format(\DateTimeInterface::ATOM),
            'ownedByCurrentPlayer' => $isOwnFleet,
        ];
        if ($shareGuildTrack) {
            return $row + ['guildFlight' => true];
        }

        return $row + [
            'shipCount' => array_sum(array_map(static fn (FleetShip $ship): int => $ship->quantity, $ships)),
            'fleetPoints' => $this->leaderboard->fleetPoints($fleet, $ships),
            'ships' => $shipsSummary,
        ];
    }
}
