<?php

declare(strict_types=1);

namespace App\Service\PirateCampaign;

use App\Entity\{Galaxy, PlayerDiscovery, PlayerUniverse, SolarSystem};
use App\Repository\{GameRepository, PirateCampaignReadRepository, PirateConvoyReadRepository};
use App\Service\Catalog\GameCatalog;
use Psr\Clock\ClockInterface;
use Symfony\Component\HttpFoundation\RequestStack;

/** Une lecture privée des trajets attestés, sans création de Fleet ni possibilité de commande. */
final readonly class PirateCampaignTraffic
{
    private const EMPTY = ['outbound' => [], 'incoming' => [], 'presence' => []];

    public function __construct(
        private PiratePilotGate $gate,
        private ClockInterface $clock,
        private RequestStack $requests,
        private GameRepository $games,
        private PirateCampaignReadRepository $campaigns,
        private PirateConvoyReadRepository $convoys,
        private PirateCampaignCatalogue $catalogue,
        private PirateCampaignSequence $sequence,
        private PirateCampaignProgress $progress,
        private GameCatalog $gameCatalog,
    ) {
    }

    /**
     * L'appelant fournit la participation issue du contexte authentifié. Les dépôts relisent
     * sa sélection et sa propriété en base ; ne jamais prendre une participation de la requête.
     *
     * @return array{outbound: list<array>, incoming: list<array>, presence: list<array>}
     */
    public function forViewer(?PlayerUniverse $viewer): array
    {
        $at = $this->clock->now()->setTimezone(new \DateTimeZone('UTC'));
        if (!$this->gate->admits($viewer?->player, $viewer, $at)) {
            return self::EMPTY;
        }

        // Les menus, fragments et la carte d'une même réponse partagent la même observation.
        $request = $this->requests->getCurrentRequest();
        $cacheKey = '_pirate_traffic_'.$viewer->player->id.'_'.$viewer->id.'_'.$viewer->universe->id;
        if ($request?->attributes->has($cacheKey)) {
            return $request->attributes->get($cacheKey);
        }
        $rows = $this->read($viewer, $at);
        $request?->attributes->set($cacheKey, $rows);

        return $rows;
    }

    /** @return list<array> En carte, ne pas publier les ancres d'une autre galaxie. */
    public function forGalaxy(PlayerUniverse $viewer, ?Galaxy $galaxy): array
    {
        if ($galaxy === null || $galaxy->universe->id !== $viewer->universe->id) {
            return [];
        }
        $snapshot = $this->forViewer($viewer);
        $visible = [];
        foreach ([...$snapshot['outbound'], ...$snapshot['incoming'], ...$snapshot['presence']] as $row) {
            $systems = array_values(array_filter($row['systems'], static fn (array $system): bool => $system['galaxy'] === $galaxy->id));
            if ($systems === []) {
                continue;
            }
            $row['systems'] = array_column($systems, 'id');
            unset($row['destination'], $row['ships'], $row['cargo']);
            $visible[] = $row;
        }

        return $visible;
    }

    private function read(PlayerUniverse $viewer, \DateTimeImmutable $at): array
    {
        $keys = $this->catalogue->keys($viewer->universe);
        $rows = $this->campaigns->ownedRows($viewer, $keys, $at);
        $order = $this->sequence->forViewer($viewer, $rows, $at);
        if ($rows === [] || $order === null || $order['consultable'] === []) {
            return self::EMPTY;
        }

        $planets = [];
        foreach ($this->games->planets($viewer->universe, $viewer) as $planet) {
            // Une ancienne colonie cédée ne donne plus son nom ni une destination au client.
            if (!$planet->npcPlanet && $planet->owner?->id === $viewer->id && $planet->solarSystem->galaxy->universe->id === $viewer->universe->id) {
                $planets[$planet->id] = $planet;
            }
        }
        $known = [];
        foreach ($this->games->all(PlayerDiscovery::class, ['playerUniverse' => $viewer]) as $discovery) {
            $known[$discovery->solarSystem->id] = true;
        }

        $visible = array_fill_keys($order['consultable'], true);
        $ordinals = array_flip($keys);
        $shipDefinitions = $this->gameCatalog->forUniverse($viewer->universe)['catalog']['ships'];
        $snapshot = self::EMPTY;
        $ownedAnchors = [];
        foreach ($planets as $planet) {
            $ownedAnchors[$planet->solarSystem->id] = $planet->solarSystem;
            $known[$planet->solarSystem->id] = true;
        }
        foreach ($rows as $row) {
            $recon = $row['reconnaissance'];
            $attack = $row['attack'];
            $progress = $this->progress->forViewer($viewer, $recon, $attack, $at);
            if ($progress === null || !isset($visible[$progress['key']])) {
                continue;
            }
            $number = $ordinals[$progress['key']] + 1;
            $dates = $progress['dates'];
            $phase = $progress['phase'];
            if ($phase === PirateCampaignMoment::ReconnaissanceEnRoute->name) {
                $departure = $recon->pilotScoutDepartureFor($viewer, $at);
                $origin = $departure['origin'] ?? null;
                // Le site n'entre pas dans la carte avant remise, même si sa position est déjà connue.
                $snapshot['outbound'][] = $this->movement('scout', $number, 'Sonde de reconnaissance',
                    $dates['scout_due'], $at, $this->systems($known, $planets[$origin]->solarSystem ?? null),
                    $dates['scout_departure'], $departure !== null ? $this->shipDetails($shipDefinitions, ['probe' => [$departure['tier'] => 1]]) : []);
                continue;
            }

            $site = $recon->existingSystemFor($viewer, $at);
            if ($site !== null) {
                $ownedAnchors[$site->id] = $site;
            }
            if ($phase !== PirateCampaignMoment::Victoire->name && $progress['key'] === $order['current_mission']) {
                $systems = $this->systems($known, $site);
                if ($systems !== []) {
                    $snapshot['presence'][] = $this->movement('pirates', $number, 'Pirates repérés · en station', null, $at, $systems, ships: $this->shipDetails($shipDefinitions, $progress['composition'] ?? []));
                }
            }
            if ($attack === null) {
                continue;
            }
            $origin = $planets[$attack->originId ?? 0]->solarSystem ?? null;
            if ($phase === PirateCampaignMoment::AttaqueEngagee->name
                && isset($dates['attack_arrival_due']) && !isset($dates['attack_arrival_observed'])) {
                $snapshot['outbound'][] = $this->movement('escort', $number, 'Escorte d’attaque de campagne',
                    $dates['attack_arrival_due'], $at, $this->systems($known, $origin, $site),
                    $dates['attack_departure'], $this->shipDetails($shipDefinitions, $attack->reservedShips));
            } elseif ($progress['returning'] === 'scheduled' && isset($dates['return_due'])) {
                $snapshot['outbound'][] = $this->movement('return', $number, 'Retour des survivants',
                    $dates['return_due'], $at, $this->systems($known, $site, $origin),
                    $dates['combat_reported'], $this->shipDetails($shipDefinitions, $attack->reservedShips));
            } elseif ($progress['returning'] === 'recovery_travel' && isset($dates['recovery_due'])) {
                $destination = $attack->timeline['pilot_recovery']['target'] ?? null;
                // Un repli depuis une ancienne colonie n’est pas un nouveau départ du site pirate.
                $fromId = $attack->timeline['pilot_blocked_return']['position'][0] ?? null;
                $from = is_int($fromId) ? ($ownedAnchors[$fromId] ?? null) : null;
                $snapshot['outbound'][] = $this->movement('return', $number, 'Repli des survivants',
                    $dates['recovery_due'], $at, $this->systems($known, $from, is_int($destination) ? ($planets[$destination]->solarSystem ?? null) : null),
                    $attack->timeline['pilot_recovery']['departure'], $this->shipDetails($shipDefinitions, $attack->reservedShips));
            }
        }

        foreach ($this->convoys->forOwnedDossier($viewer, array_keys($visible), $at) as $row) {
            try {
                $timeline = $row['right']->timeline();
                $travel = $timeline->travelFor($viewer->id, $viewer->universe->id, $at);
            } catch (\DomainException) {
                continue; // Un ancien droit illisible n'est pas la preuve d'un convoi en route.
            }
            if ($travel === null || !isset($planets[$travel['destination']])) {
                continue;
            }
            $planet = $planets[$travel['destination']];
            $snapshot['incoming'][] = $this->movement('convoy', $ordinals[$row['missionKey']] + 1, 'Convoi de remerciement protégé',
                $travel['due']->format(DATE_ATOM), $at, $this->systems($known, $ownedAnchors[$travel['origin_system']] ?? null, $planet->solarSystem),
                $travel['departed']->format(DATE_ATOM)) + [
                'destination' => $planet->name,
                'cargo' => array_filter($timeline->quote->exactParts(), static fn (array $part): bool => $part['whole'] > 0 || $part['extra_half']),
            ];
        }

        return $snapshot;
    }

    /** @return list<array{id:int,galaxy:int}> */
    private function systems(array $known, ?SolarSystem ...$systems): array
    {
        $out = [];
        foreach ($systems as $system) {
            if ($system !== null && isset($known[$system->id])) {
                $out[$system->id] = ['id' => $system->id, 'galaxy' => $system->galaxy->id];
            }
        }

        return array_values($out);
    }

    private function movement(
        string $kind, int $mission, string $label, ?string $eta, \DateTimeImmutable $at,
        array $systems, ?string $departure = null, array $ships = [],
    ): array {
        return ['kind' => $kind, 'mission' => $mission, 'label' => $label, 'eta' => $eta,
            'overdue' => $eta !== null && new \DateTimeImmutable($eta) <= $at, 'systems' => $systems,
            'departure' => $departure, 'ships' => $ships];
    }

    /** Les escortes utilisent la réserve constatée, jamais les choix du formulaire ou la flotte adverse. */
    private function shipDetails(array $definitions, array $composition): array
    {
        $rows = [];
        foreach ($composition as $slug => $tiers) {
            foreach ($tiers as $tier => $quantity) {
                // Une ancienne coque retirée du catalogue reste lisible avec son identifiant.
                $definition = $definitions[$slug] ?? [];
                $rows[] = [
                    'label' => $definition['tier_labels'][$tier] ?? $definition['label'] ?? $slug,
                    'tier' => $this->gameCatalog->tiers()[$tier]['label'] ?? $tier,
                    'quantity' => $quantity,
                ];
            }
        }

        return $rows;
    }
}
