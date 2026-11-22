<?php

declare(strict_types=1);

namespace App\Service\PirateCampaign;

use App\Entity\{Galaxy, Planet, PlayerDiscovery, PlayerUniverse};
use App\Repository\{GameRepository, PirateCampaignReadRepository, PirateConvoyReadRepository};
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
            unset($row['destination']);
            $visible[] = $row;
        }

        return $visible;
    }

    private function read(PlayerUniverse $viewer, \DateTimeImmutable $at): array
    {
        $keys = $this->catalogue->keys();
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
        $snapshot = self::EMPTY;
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
                $origin = $recon->pirateScoutFuelFor($viewer, $at)?->originId;
                // Le site n'entre pas dans la carte avant remise, même si sa position est déjà connue.
                $snapshot['outbound'][] = $this->movement('scout', $number, 'Sonde de reconnaissance',
                    $dates['scout_due'], $at, $this->systems($known, $planets[$origin]->solarSystem ?? null));
                continue;
            }

            $site = $recon->existingSystemFor($viewer, $at);
            if ($phase !== PirateCampaignMoment::Victoire->name && $progress['key'] === $order['current_mission']) {
                $systems = $this->systems($known, $site);
                if ($systems !== []) {
                    $snapshot['presence'][] = $this->movement('pirates', $number, 'Pirates repérés · en station', null, $at, $systems);
                }
            }
            if ($attack === null) {
                continue;
            }
            $origin = $planets[$attack->originId ?? 0]->solarSystem ?? null;
            if ($phase === PirateCampaignMoment::AttaqueEngagee->name
                && isset($dates['attack_arrival_due']) && !isset($dates['attack_arrival_observed'])) {
                $snapshot['outbound'][] = $this->movement('escort', $number, 'Escorte d’attaque de campagne',
                    $dates['attack_arrival_due'], $at, $this->systems($known, $origin, $site));
            } elseif ($progress['returning'] === 'scheduled' && isset($dates['return_due'])) {
                $snapshot['outbound'][] = $this->movement('return', $number, 'Retour des survivants',
                    $dates['return_due'], $at, $this->systems($known, $site, $origin));
            } elseif ($progress['returning'] === 'recovery_travel' && isset($dates['recovery_due'])) {
                $destination = $attack->timeline['pilot_recovery']['target'] ?? null;
                $snapshot['outbound'][] = $this->movement('return', $number, 'Repli des survivants',
                    $dates['recovery_due'], $at, $this->systems($known, $site, is_int($destination) ? ($planets[$destination]->solarSystem ?? null) : null));
            }
        }

        foreach ($this->convoys->forOwnedDossier($viewer, array_keys($visible), $at) as $row) {
            try {
                $travel = $row['right']->timeline()->travelFor($viewer->id, $viewer->universe->id, $at);
            } catch (\DomainException) {
                continue; // Un ancien droit illisible n'est pas la preuve d'un convoi en route.
            }
            if ($travel === null || !isset($planets[$travel['destination']])) {
                continue;
            }
            $planet = $planets[$travel['destination']];
            $snapshot['incoming'][] = $this->movement('convoy', $ordinals[$row['missionKey']] + 1, 'Convoi de remerciement protégé',
                $travel['due']->format(DATE_ATOM), $at, $this->systems($known, $planet->solarSystem)) + ['destination' => $planet->name];
        }

        return $snapshot;
    }

    /** @return list<array{id:int,galaxy:int}> */
    private function systems(array $known, ?\App\Entity\SolarSystem ...$systems): array
    {
        $out = [];
        foreach ($systems as $system) {
            if ($system !== null && isset($known[$system->id])) {
                $out[$system->id] = ['id' => $system->id, 'galaxy' => $system->galaxy->id];
            }
        }

        return array_values($out);
    }

    private function movement(string $kind, int $mission, string $label, ?string $eta, \DateTimeImmutable $at, array $systems): array
    {
        return ['kind' => $kind, 'mission' => $mission, 'label' => $label, 'eta' => $eta,
            'overdue' => $eta !== null && new \DateTimeImmutable($eta) <= $at, 'systems' => $systems];
    }
}
