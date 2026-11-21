<?php

declare(strict_types=1);

namespace App\Service\PirateCampaign;

use App\Entity\{Fleet, FleetShip, OutboxMessage, PirateReconnaissance, Planet, PlayerDiscovery, PlayerTechnology, PlayerUniverse, SolarSystem};
use App\Manager\GameManager;
use App\Message\PirateReconnaissanceDue;
use App\Repository\GameRepository;
use App\Service\Catalog\GameCatalog;
use App\Service\Colony\ActionGuard;
use App\Service\Combat\TechnologyEffectCalculator;
use App\Service\Fleet\{FleetHold, MovementCalculator};
use App\Service\Player\VacationCalendar;
use Psr\Clock\ClockInterface;
use Symfony\Component\Uid\Uuid;

final class PirateReconnaissanceService
{
    public function __construct(
        private GameManager $manager,
        private GameRepository $repository,
        private ActionGuard $guard,
        private GameCatalog $catalog,
        private TechnologyEffectCalculator $technologyEffects,
        private MovementCalculator $movement,
        private FleetHold $hold,
        private VacationCalendar $vacations,
        private ClockInterface $clock,
        private PirateCampaignCatalogue $campaign,
    ) {
    }

    /** Une définition serveur explicite, pas un choix ou une autorisation de joueur. */
    public function launchDefined(
        PlayerUniverse $membership,
        Planet $origin,
        SolarSystem $system,
        PirateDistressSite $site,
        string $missionKey,
        string $definitionKey,
        ?bool $detected = null,
    ): bool {
        return $this->start(
            $membership, $origin, $system, $site, $missionKey,
            fn (): PiratePreparedEncounter => $this->campaign->prepareForServer($definitionKey, $membership->universe),
            $detected,
        );
    }

    /** Source interne synthétique ; une preuve ancienne sans profil ne permet qu'un rapport. */
    public function launch(
        PlayerUniverse $membership,
        Planet $origin,
        SolarSystem $system,
        PirateDistressSite $site,
        string $missionKey,
        PirateEncounterSnapshot|PiratePreparedEncounter $encounter,
        ?bool $detected = null,
    ): bool {
        return $this->start($membership, $origin, $system, $site, $missionKey, static fn () => $encounter, $detected);
    }

    /** @param \Closure(): (PirateEncounterSnapshot|PiratePreparedEncounter) $capture */
    private function start(
        PlayerUniverse $membership,
        Planet $origin,
        SolarSystem $system,
        PirateDistressSite $site,
        string $missionKey,
        \Closure $capture,
        ?bool $detected,
    ): bool {
        if (!preg_match('/^[a-z0-9][a-z0-9_-]{0,63}$/D', $missionKey)) {
            throw new \DomainException('Identifiant de mission pirate invalide.');
        }

        return $this->manager->transaction(function () use ($membership, $origin, $system, $site, $missionKey, $capture, $detected): bool {
            // Même ordre que les commandes de flotte ; la participation sérialise aussi deux origines différentes.
            $this->guard->lock($membership, $origin, true);
            if ($membership->player->lockedAt !== null || $site->signalFor($membership) === null || !$site->matchesSystem($system)) {
                throw new \DomainException('Mission de reconnaissance privée inaccessible.');
            }
            $this->vacations->assertCanSendFleet($membership);

            $key = ['membership' => $membership, 'missionKey' => $missionKey];
            if ($this->repository->one(PirateReconnaissance::class, $key) !== null) {
                return false;
            }
            if (!$this->repository->one(PlayerDiscovery::class, ['playerUniverse' => $membership, 'solarSystem' => $system])) {
                throw new \DomainException('Le système du poste civil doit être découvert avant le départ.');
            }

            $probe = $this->lockProbe($membership, $origin);
            $idle = $probe->fleet;
            // Ne jamais libérer un vaisseau qui porte déjà une réserve ou du fret du hangar.
            // Le futur parcours public devra définir et afficher le carburant de ce vol sans retour.
            $this->hold->departure($idle, ['probe' => ['basic' => 1]], 0.0);
            $now = $this->clock->now()->setTimezone(new \DateTimeZone('UTC'));
            // Doctrine et le relais travaillent à la seconde ; ne pas créer deux échéances différentes.
            $departedAt = $now->setTimestamp($now->getTimestamp());
            $seconds = $this->flightSeconds($membership, $origin, $system);
            $dueAt = $departedAt->modify('+'.$seconds.' seconds');
            $token = Uuid::v4()->toRfc4122();

            // Le prix, l'ennemi et le profil de combat prennent le même contexte d'univers
            // et sont persistés ensemble, avant toute modification du hangar ou remise du rapport.
            $encounter = $capture();

            // Le hangar n'a plus la sonde durant le vol. Sa perte est actée par le rapport, sans retour ni restitution.
            --$probe->quantity;
            $mission = new PirateReconnaissance(
                $membership, $membership->player, $system, $missionKey, $departedAt, $dueAt, $token, $detected, $encounter,
            );
            $this->manager->save($mission);
            $this->manager->flush();

            // La sonde et l'ordre différé doivent être enregistrés ou annulés ensemble, même si le relais redémarre.
            $outbox = new OutboxMessage();
            $outbox->kind = PirateReconnaissanceDue::KIND;
            $outbox->targetId = $mission->id;
            $outbox->token = $token;
            $outbox->dueAt = $dueAt;
            $this->manager->save($outbox);

            return true;
        });
    }

    private function lockProbe(PlayerUniverse $membership, Planet $origin): FleetShip
    {
        $idle = $this->repository->one(Fleet::class, ['playerUniverse' => $membership, 'homePlanet' => $origin, 'status' => 'idle']);
        if ($idle === null) {
            throw new \DomainException('Une Sonde de reconnaissance basique disponible est requise.');
        }
        $this->manager->lockCurrent($idle);
        $probe = $this->repository->one(FleetShip::class, ['fleet' => $idle, 'slug' => 'probe', 'tier' => 'basic']);
        if ($probe === null) {
            throw new \DomainException('Une Sonde de reconnaissance basique disponible est requise.');
        }
        $this->manager->lockCurrent($probe);
        if ($probe->quantity < 1) {
            throw new \DomainException('Une Sonde de reconnaissance basique disponible est requise.');
        }

        return $probe;
    }

    private function flightSeconds(PlayerUniverse $membership, Planet $origin, SolarSystem $system): int
    {
        $universe = $membership->universe;
        $config = $this->catalog->forUniverse($universe);
        $ship = $this->catalog->getTierForUniverse($universe, 'ships', 'probe', 'basic');
        $ship['quantity'] = 1;
        $ship['tier'] = 'basic';
        $levels = $this->repository->levelsByTier(PlayerTechnology::class, ['playerUniverse' => $membership]);
        $ships = $this->technologyEffects->applyToShips([$ship], $levels, $universe);

        $home = $origin->solarSystem;
        $quote = $this->movement->quote(
            [$home->id, $home->galaxy->id, $home->coordX, $home->coordY, $origin->position],
            [$system->id, $system->galaxy->id, $system->coordX, $system->coordY, null],
            $ships,
            1.0,
            $config['multipliers']['travel_time'],
            $this->catalog->rules()['fuel_ratio_policy'],
        );

        return $quote['seconds'];
    }

    /** Retourne des millisecondes seulement si Messenger doit reprendre ce même jeton. */
    public function deliver(int $missionId, string $token): ?int
    {
        $membership = $this->repository->find(PirateReconnaissance::class, $missionId)?->membership();
        if ($membership === null) {
            return null;
        }

        return $this->manager->transaction(function () use ($missionId, $membership, $token): ?int {
            // Le verrou de participation précède la lecture courante ; ne pas créer un instantané
            // paresseux dans la transaction avant ce verrou, ni relire le hangar en arrivant.
            $this->manager->lockCurrent($membership);
            $this->manager->lockCurrent($membership->universe);
            $mission = $this->repository->lockPirateReconnaissance($missionId, $membership);

            return $mission?->reportWhenDue($membership, $token, $this->clock->now());
        });
    }

    public function statusFor(PlayerUniverse $membership, string $missionKey): ?array
    {
        $owner = $membership->player;
        if ($membership->id === null || $owner?->id === null || !$membership->isPlayerControlled()) {
            return null;
        }

        // Filtrer avant chargement. Aucune recherche générale par mission, destination ou alliance.
        $now = $this->clock->now();
        $mission = $this->repository->ownPirateReconnaissance($membership, $missionKey, $now);

        return $mission?->projectFor($membership, $now);
    }
}
