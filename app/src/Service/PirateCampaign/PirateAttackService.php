<?php

declare(strict_types=1);

namespace App\Service\PirateCampaign;

use App\Entity\{Fleet, FleetShip, OutboxMessage, PirateAttackCampaign, Planet, PlayerTechnology, PlayerUniverse, SolarSystem};
use App\Manager\GameManager;
use App\Message\PirateAttackDue;
use App\Repository\GameRepository;
use App\Service\Catalog\GameCatalog;
use App\Service\Colony\{ActionGuard, ResourceService};
use App\Service\Combat\{CombatSimulator, CombatUnitFactory};
use App\Service\Fleet\{FleetHold, MovementCalculator};
use App\Service\Player\{ProfileCosmetics, VacationCalendar};
use Psr\Clock\ClockInterface;
use Symfony\Component\Uid\Uuid;

/** Orchestration interne, sans route existante ni création de flotte publique. */
final class PirateAttackService
{
    private const ARRIVAL_FLOW = 2;

    public function __construct(
        private GameManager $manager,
        private GameRepository $repository,
        private ActionGuard $guard,
        private GameCatalog $catalog,
        private CombatUnitFactory $units,
        private CombatSimulator $simulator,
        private MovementCalculator $movement,
        private FleetHold $hold,
        private PirateFlightQuote $flightQuote,
        private ResourceService $resources,
        private VacationCalendar $vacations,
        private ClockInterface $clock,
        private PirateConvoyDeliveryService $convoys,
        private ProfileCosmetics $cosmetics,
    ) {
    }

    /**
     * Le scénario ne vient que du rapport figé ; la cadence interne peut être fournie au premier départ.
     * Le N° précédent attendu rend une commande obsolète incapable de relancer après une défaite.
     * @param array<string,array<string,int>> $composition forces réellement disponibles
     */
    public function launch(
        PlayerUniverse $membership,
        Planet $origin,
        string $missionKey,
        int $expectedPreviousAttempt,
        array $composition,
        ?int $initialRoundSeconds = null,
    ): bool {
        return $this->start($membership, $origin, $missionKey, $expectedPreviousAttempt, $composition, $initialRoundSeconds, false, false);
    }

    /** Entrée serveur privée et explicite ; seul le nouveau flux utilise l'arrivée et le dernier round réel. */
    public function launchWithPrivateArrival(
        PlayerUniverse $membership,
        Planet $origin,
        string $missionKey,
        int $expectedPreviousAttempt,
        array $composition,
    ): bool {
        return $this->start($membership, $origin, $missionKey, $expectedPreviousAttempt, $composition, null, true, false);
    }

    /** Entrée interne explicite, que ni le dossier ni aucune route ne peuvent appeler aujourd'hui. */
    public function launchWithPilotConvoy(
        PlayerUniverse $membership,
        Planet $origin,
        string $missionKey,
        int $expectedPreviousAttempt,
        array $composition,
        PiratePilotGate $admission,
    ): bool {
        return $this->start($membership, $origin, $missionKey, $expectedPreviousAttempt, $composition, null, true, true, admission: $admission);
    }

    /**
     * Départ du futur pilote : rapport scout payé, aucune flotte publique ni fret de mission.
     * Le dossier et le routeur n'exposent pas cette commande.
     */
    public function launchWithPilotFuel(
        PlayerUniverse $membership,
        Planet $origin,
        string $missionKey,
        int $expectedPreviousAttempt,
        array $composition,
        PiratePilotGate $admission,
    ): bool {
        return $this->start($membership, $origin, $missionKey, $expectedPreviousAttempt, $composition, null, true, true, true, $admission);
    }

    /** @return array{attempt:int, options:PiratePilotCommandOptions}|null */
    public function pilotAttackOptionsLocked(PlayerUniverse $member, Planet $origin, string $key, PiratePilotGate $admission): ?array
    {
        $state = $this->pilotAttackReadyLocked($member, $origin, $key, $admission);
        if ($state === null) {
            return null;
        }
        $idle = $this->idleFleet($member, $origin);
        if ($idle === null) {
            return null;
        }
        $this->manager->lockCurrent($idle);
        $availability = [];
        foreach ($this->repository->all(FleetShip::class, ['fleet' => $idle]) as $row) {
            $this->manager->lockCurrent($row);
            if ($row->quantity < 1) {
                continue;
            }
            try {
                $ship = $this->catalog->getTierForUniverse($member->universe, 'ships', $row->slug, $row->tier);
            } catch (\DomainException) {
                continue; // Une ancienne coque inconnue n’est pas une option de départ.
            }
            if (($ship['mobile'] ?? true) && (!isset($ship['allowed_missions']) || in_array('attack', $ship['allowed_missions'], true))) {
                $availability[$row->slug.':'.$row->tier] = $row->quantity;
            }
        }
        $limit = (int) $this->catalog->forUniverse($member->universe)['limits']['max_fleet_size'];
        if ($availability === [] || $limit < 1 || $limit > PiratePilotCommandOptions::MAX_QUANTITY
            || count($availability) > PiratePilotCommandOptions::MAX_OPTIONS) {
            return null;
        }

        return ['attempt' => $state['attempt'], 'options' => PiratePilotCommandOptions::escort($availability, $limit)];
    }

    /**
     * Aucun départ/stock : lisible seulement dans un contexte propriétaire verrouillé de ce pilote.
     * Le devis et le futur débit utilisent exactement le même constructeur natif des profils et du trajet.
     * @return array{quote:array{seconds:int,hydrogen:float,orbit:float,hold:float,planet:float},world:string}|null
     */
    public function pilotAttackQuoteLocked(PlayerUniverse $member, Planet $origin, string $key, int $expectedAttempt, array $composition, PiratePilotGate $admission): ?array
    {
        $state = $this->pilotAttackReadyLocked($member, $origin, $key, $admission);
        if ($state === null || $state['attempt'] !== $expectedAttempt) {
            return null;
        }
        try {
            [, , $units, $seconds, $debit] = $this->lockedLaunchInput($member, $origin, $state['proof']['system'], $composition, true);
            [$budget, $payment, $originCoords, $targetCoords] = $debit;
        } catch (\DomainException) {
            return null;
        }
        if ($payment['planet_fuel'] > 0.0) {
            $this->repository->lockedPlanetResources($origin);
            $stocks = $this->resources->snapshot($origin);
            if (($stocks['hydrogen']['stock'] ?? 0.0) < $payment['planet_fuel']) {
                return null;
            }
        }
        $world = hash('sha256', json_encode([
            'version' => 1, 'universe' => $member->universe->id, 'mission' => $key,
            'report_id' => $state['id'], 'reported_at' => $state['proof']['reportedAt']->format(DATE_ATOM),
            'origin' => $originCoords, 'target' => $targetCoords, 'attempt' => $expectedAttempt,
            'scenario' => $state['scenario'], 'escort_profiles' => $units,
        ], JSON_THROW_ON_ERROR | JSON_PRESERVE_ZERO_FRACTION));

        return ['quote' => [
            'seconds' => $seconds, 'hydrogen' => $budget->initialHydrogen, 'orbit' => $budget->orbitHydrogen,
            'hold' => $payment['stored_fuel_used'], 'planet' => $payment['planet_fuel'],
        ], 'world' => $world];
    }

    private function pilotAttackReadyLocked(PlayerUniverse $member, Planet $origin, string $key, PiratePilotGate $admission): ?array
    {
        $at = $this->now();
        if (!$admission->matchesWorld($member->universe, $at) || $member->id === null
            || !$member->isPlayerControlled() || !$member->universe->isAccessible($at) || $origin->owner?->id !== $member->id
            || $origin->solarSystem->galaxy->universe->id !== $member->universe->id || $member->player->lockedAt !== null) {
            return null;
        }
        try {
            $this->vacations->assertCanSendFleet($member);
            $recon = $this->repository->ownPirateReconnaissance($member, $key, $at);
            $proof = $recon?->battleProofFor($member, $at);
            if ($proof === null || ($recon->narrativeContextFor($member, $at)['key'] ?? null) !== $key
                || $recon->pilotScoutFuelFor($member, $at) === null) {
                return null; // Aucun aperçu ni sélection d’escorte sur un rapport absent ou non payé.
            }
            $previous = $this->repository->one(PirateAttackCampaign::class, ['reconnaissance' => $recon]);
            $attempt = 0;
            $scenario = $proof['prepared']->battleForServer()->snapshot();
            if ($previous !== null) {
                $this->manager->lockCurrent($previous);
                if (!self::usesPrivateArrival($previous) || ($previous->timeline['convoy_pilot'] ?? null) !== true
                    || !isset($previous->timeline['pilot_attack_fuel']) || $previous->returnBlocked || $previous->reservedShips !== []
                    || ($previous->timeline['state'] ?? null) !== 'retryable' || ($previous->timeline['won'] ?? null) !== false
                    || !is_int($attempt = $previous->timeline['attempt'] ?? null) || $attempt < 1
                    || $attempt >= PiratePilotCommandOptions::MAX_QUANTITY) {
                    return null;
                }
                $scenario = $this->scenarioFromReport($proof['prepared'], $previous)->snapshot();
            }
        } catch (\LogicException) {
            return null;
        }

        return ['id' => $recon->id, 'attempt' => $attempt, 'proof' => $proof, 'scenario' => $scenario];
    }

    private function start(
        PlayerUniverse $membership,
        Planet $origin,
        string $missionKey,
        int $expectedPreviousAttempt,
        array $composition,
        ?int $initialRoundSeconds,
        bool $arrivalFlow,
        bool $convoyPilot,
        bool $fundedPilot = false,
        ?PiratePilotGate $admission = null,
    ): bool {
        return $this->manager->transaction(function () use ($membership, $origin, $missionKey, $expectedPreviousAttempt, $composition, $initialRoundSeconds, $arrivalFlow, $convoyPilot, $fundedPilot, $admission): bool {
            // Verrous conquis dans le même ordre pour les commandes natales : empire, univers, planète.
            $this->guard->lock($membership, $origin, true);
            if ($convoyPilot && !($admission?->matchesWorld($membership->universe, $this->now()) ?? false)) {
                throw new \DomainException('La campagne n’admet pas cet univers.');
            }
            if ($membership->player->lockedAt !== null) {
                throw new \DomainException('Compte verrouillé.');
            }
            $this->vacations->assertCanSendFleet($membership);
            $now = $this->now();
            $recon = $this->repository->ownPirateReconnaissance($membership, $missionKey, $now);
            $proof = $recon?->battleProofFor($membership, $now);
            if ($proof === null) {
                throw new \DomainException('Le rapport de reconnaissance privé et son scénario figé doivent être disponibles.');
            }

            if ($fundedPilot && $recon->pilotScoutFuelFor($membership, $now) === null) {
                throw new \DomainException('Le rapport de la sonde avec prélèvement natif doit être disponible.');
            }

            $campaign = $this->repository->one(PirateAttackCampaign::class, ['reconnaissance' => $recon]);
            if ($campaign !== null) {
                $this->manager->lockCurrent($campaign);
                if ($campaign->timeline['attempt'] !== $expectedPreviousAttempt) {
                    return false;
                }
                if ($fundedPilot && $campaign->returnBlocked && $campaign->reservedShips !== []) {
                    throw new \DomainException('Le sort des survivants doit être réglé avant une nouvelle tentative.');
                }
                if ($initialRoundSeconds !== null || $arrivalFlow !== self::usesPrivateArrival($campaign)
                    || $convoyPilot !== (($campaign->timeline['convoy_pilot'] ?? null) === true)
                    || $fundedPilot !== isset($campaign->timeline['pilot_attack_fuel'])) {
                    throw new \DomainException('Une nouvelle tentative ne change pas la cadence figée.');
                }
                $scenario = $this->scenarioFromReport($proof['prepared'], $campaign);
                $timeline = PirateAttackTimeline::fromCheckpoint($scenario, $proof['reportedAt'], $campaign->timeline);
            } else {
                if ($expectedPreviousAttempt !== 0) {
                    return false;
                }
                if (!$arrivalFlow && ($initialRoundSeconds === null || $initialRoundSeconds < 1)) {
                    throw new \DomainException('Paramètres internes de campagne absents.');
                }
                $scenario = $proof['prepared']->battleForServer();
                $timeline = PirateAttackTimeline::afterReport($scenario, $proof['reportedAt']);
                $campaign = new PirateAttackCampaign();
                $campaign->reconnaissance = $recon;
                $campaign->scenario = $scenario->snapshot();
                $campaign->roundSeconds = $arrivalFlow ? PirateCombatSchedule::ROUND_SECONDS : $initialRoundSeconds;
            }

            [$idle, $stock, $combatUnits, $seconds, $debit] = $this->lockedLaunchInput($membership, $origin, $proof['system'], $composition, $fundedPilot);
            $fuelReceipt = null;
            if ($debit !== null) {
                [$budget, $payment, $originCoords, $targetCoords] = $debit;
                $fuelReceipt = PirateAttackFuelReceipt::forDeparture(
                    $expectedPreviousAttempt + 1, $origin->id, $originCoords, $targetCoords, $combatUnits, $budget,
                    $payment['stored_fuel_used'], $payment['planet_fuel'],
                );
                if ($fuelReceipt->fromPlanet > 0.0) {
                    $this->repository->lockedPlanetResources($origin);
                    $this->resources->consume($origin, ['hydrogen' => $fuelReceipt->fromPlanet]);
                }
                $now = $this->now();
                if (!$membership->universe->isAccessible($now)) {
                    throw new \DomainException('L’univers s’est fermé avant le départ de l’attaque.');
                }
                $idle->storedFuel -= $fuelReceipt->fromHold;
            }
            $arrivalAt = $now->modify('+'.$seconds.' seconds');
            // Pour le flux V2, cette borne est indépendante du résultat ; seule l’arrivée est ordonnée au départ.
            $rounds = $campaign->scenario['rounds'];
            if (($arrivalFlow && $rounds > PirateCombatSchedule::MAX_ROUNDS)
                || $campaign->roundSeconds > intdiv(2147483647, $rounds)) {
                throw new \DomainException('Durée interne du combat trop grande.');
            }
            $combatDueAt = $arrivalAt->modify('+'.($campaign->roundSeconds * $rounds).' seconds');
            $timeline->start($combatUnits, random_int(0, 0xffffffff), $now, $arrivalAt, $combatDueAt);

            foreach ($stock as [$ship, $count]) {
                $ship->quantity -= $count;
            }
            $campaign->originId = $origin->id;
            $campaign->travelSeconds = $seconds;
            $campaign->departedAt = $now;
            $campaign->arrivalAt = $arrivalAt;
            $campaign->timeline = $timeline->checkpoint();
            if ($arrivalFlow) {
                $campaign->timeline['arrival_flow'] = self::ARRIVAL_FLOW;
            }
            if ($convoyPilot) {
                $campaign->timeline['convoy_pilot'] = true;
            }
            if ($fuelReceipt !== null) {
                $campaign->timeline['pilot_attack_fuel'] = $fuelReceipt->checkpoint();
            }
            $campaign->reservedShips = $composition;
            $campaign->report = null;
            $campaign->returnBlocked = false;
            $this->manager->save($campaign);
            $this->manager->flush();
            $this->schedule($campaign, $arrivalFlow ? $arrivalAt : $combatDueAt);

            return true;
        });
    }

    private function scenarioFromReport(PiratePreparedEncounter $prepared, PirateAttackCampaign $campaign): PirateBattleScenario
    {
        $scenario = $prepared->battleForServer();
        if ($scenario->snapshot() !== $campaign->scenario) {
            // Ne jamais substituer un adversaire en recomposant depuis les prix ou le catalogue courant.
            throw new \LogicException('La tentative ne correspond pas au scénario de reconnaissance figé.');
        }

        return $scenario;
    }

    private function lockedLaunchInput(PlayerUniverse $membership, Planet $origin, SolarSystem $system, array $composition, bool $fundedPilot): array
    {
        if ($composition === []) {
            throw new \DomainException('Une flotte doit être choisie.');
        }
        $idle = $this->idleFleet($membership, $origin);
        if ($idle === null) {
            throw new \DomainException('Aucune flotte locale disponible.');
        }
        $this->manager->lockCurrent($idle);
        $stock = [];
        $config = $this->catalog->forUniverse($membership->universe);
        $sizeAvailable = (int) $config['limits']['max_fleet_size'];
        foreach ($composition as $slug => $ranks) {
            if (!is_string($slug) || !is_array($ranks) || $ranks === []) {
                throw new \DomainException('Composition d’attaque invalide.');
            }
            foreach ($ranks as $tier => $count) {
                if (!in_array($tier, GameCatalog::TIERS, true) || !is_int($count) || $count < 1) {
                    throw new \DomainException('Quantité ou rang de vaisseau invalide.');
                }
                $ship = $this->repository->one(FleetShip::class, ['fleet' => $idle, 'slug' => $slug, 'tier' => $tier]);
                if ($ship === null) {
                    throw new \DomainException('Vaisseaux indisponibles dans le hangar.');
                }
                $this->manager->lockCurrent($ship);
                if ($ship->quantity < $count) {
                    throw new \DomainException('Vaisseaux indisponibles dans le hangar.');
                }
                if ($fundedPilot) {
                    if ($count > $sizeAvailable) {
                        throw new \DomainException('Composition trop grande pour un départ pilote.');
                    }
                    $sizeAvailable -= $count;
                }
                $stock[] = [$ship, $count];
            }
        }
        $levels = $this->repository->levelsByTier(PlayerTechnology::class, ['playerUniverse' => $membership]);
        $ships = $this->units->ships($membership->universe, $composition, $levels);
        foreach ($ships as $unit) {
            if (($unit['mobile'] ?? true) === false || (isset($unit['allowed_missions']) && !in_array('attack', $unit['allowed_missions'], true))) {
                throw new \DomainException('Ce vaisseau ne peut pas prendre part à une attaque.');
            }
        }

        $home = $origin->solarSystem;
        $originCoords = [$home->id, $home->galaxy->id, $home->coordX, $home->coordY, $origin->position];
        $targetCoords = [$system->id, $system->galaxy->id, $system->coordX, $system->coordY];
        $rules = $this->catalog->rules();
        $timeMultiplier = $config['multipliers']['travel_time'];
        if ($fundedPilot) {
            // Même fenêtre d'orbite maximale que FleetService::quote pour une attaque ; jamais la latence du worker.
            $windowHours = (int) ($rules['combat_window_seconds'] ?? 600) / 3600;
            $budget = PirateCampaignFuel::expedition($this->flightQuote->compare(
                $originCoords, $targetCoords, $ships, 1.0, $timeMultiplier, $rules['fuel_ratio_policy'], $windowHours,
            ));
            $payment = $this->hold->departure($idle, $composition, $budget->amountForDeparture());

            return [$idle, $stock, $ships, $budget->legSeconds, [$budget, $payment, $originCoords, $targetCoords]];
        }
        // Compatibilité des anciens agrégats privés ; leur fret doit toujours tenir dans les coques restantes.
        $this->hold->departure($idle, $composition, 0.0);
        $quote = $this->movement->quote($originCoords, [...$targetCoords, null], $ships, 1.0, $timeMultiplier, $rules['fuel_ratio_policy']);

        return [$idle, $stock, $ships, $quote['seconds'], null];
    }

    /** Retourne uniquement un délai de rejeu si le bon message a été reçu trop tôt. */
    public function processDue(int $campaignId, string $dispatchToken): ?int
    {
        $campaign = $this->repository->find(PirateAttackCampaign::class, $campaignId);
        if ($campaign === null) {
            return null;
        }

        // Résoudre la relation non mutable avant la transaction : la lecture paresseuse du rapport
        // ouvrirait un instantané MariaDB avant le verrou commun et ferait échouer un worker concurrent.
        $membership = $campaign->reconnaissance->membership();

        $isFinalMission = $campaign->reconnaissance->reportedMissionKeyFor($membership, $this->now()) === PirateCampaignCatalogue::FINAL_MISSION;

        return $this->manager->transaction(function () use ($campaignId, $membership, $dispatchToken, $isFinalMission): ?int {
            $player = $isFinalMission ? $membership->player : null;
            // Le cosmétique est un droit du compte. Le verrouiller avant sa participation
            // respecte l’ordre des changements d’univers et évite une attribution liée à un effacement.
            if ($player !== null) {
                $this->manager->lockCurrent($player);
            }
            $this->manager->lockCurrent($membership);
            $this->manager->lockCurrent($membership->universe);
            $campaign = $this->repository->lockPirateAttack($campaignId, $membership);
            if ($campaign === null || $campaign->dispatchToken === null || !hash_equals($campaign->dispatchToken, $dispatchToken)) {
                return null;
            }
            $now = $this->now();
            $proof = $campaign->reconnaissance->battleProofFor($membership, $now);
            if ($proof === null || $campaign->nextAt === null) {
                return null;
            }
            if ($campaign->nextAt > $now) {
                return ($campaign->nextAt->getTimestamp() - $now->getTimestamp()) * 1000;
            }
            $scenario = $this->scenarioFromReport($proof['prepared'], $campaign);
            $timeline = PirateAttackTimeline::fromCheckpoint($scenario, $proof['reportedAt'], $campaign->timeline);
            $attempt = $campaign->timeline['attempt'];

            $wasScheduled = $timeline->state() === 'scheduled';
            if ($wasScheduled && self::usesPrivateArrival($campaign)) {
                $this->processPrivateArrivalOrBattle($campaign, $timeline, $attempt, $scenario, $membership, $now);
            } elseif ($wasScheduled) {
                $this->resolveBattle($campaign, $timeline, $attempt, $now);
            } elseif ($timeline->state() === 'returning') {
                if (isset($campaign->timeline['pilot_attack_fuel'])) {
                    $this->settleFundedReturn($campaign, $timeline, $membership, $attempt);
                } else {
                    $this->settleReturn($campaign, $timeline, $membership, $attempt, $now);
                }
            }

            if ($wasScheduled && $player !== null && $player->lockedAt === null
                && ($campaign->report['outcome'] ?? null) === 'attacker_wins') {
                // Le rapport, le titre et le retour (ou le convoi) sont validés ensemble.
                $this->cosmetics->grantLocked($player, [ProfileCosmetics::PIRATE_HUNTER_TITLE], $now);
            }

            return null;
        });
    }

    private function processPrivateArrivalOrBattle(
        PirateAttackCampaign $campaign,
        PirateAttackTimeline $timeline,
        int $attempt,
        PirateBattleScenario $scenario,
        PlayerUniverse $membership,
        \DateTimeImmutable $now,
    ): void {
        if (!isset($campaign->timeline['opaque_battle'])) {
            if ($campaign->nextAt != $campaign->arrivalAt || $now < $campaign->arrivalAt
                || isset($campaign->timeline['arrival_observed'])) {
                throw new \LogicException('L’ordre d’arrivée privée ne correspond pas à cette tentative.');
            }
            // Le calcul appartient à l'arrivée effectivement traitée ; une arrivée tardive rattrape la
            // cadence nominale sans antidater une écriture. Ni stock ni rapport ne change à cette phase.
            $pending = PiratePendingBattle::prepareAfterArrival($attempt, $now, $now, $scenario,
                $campaign->timeline['units'], $campaign->timeline['seed'], $this->simulator, $campaign->arrivalAt);
            $campaign->timeline['arrival_observed'] = $now->format(DATE_ATOM);
            $campaign->timeline['opaque_battle'] = $pending->checkpointForPersistence();
            $this->schedule($campaign, $pending->workerDueAt());

            return;
        }

        $pending = PiratePendingBattle::fromPrivateCheckpoint($campaign->timeline['opaque_battle']);
        if ($campaign->nextAt != $pending->workerDueAt()) {
            throw new \LogicException('L’ordre de résolution ne correspond pas à son échéance figée.');
        }
        $result = $pending->takeWhenDue($attempt, $now);
        if ($result === null) {
            throw new \LogicException('L’ordre de résolution ne correspond pas au résultat privé de cette tentative.');
        }
        $survivors = $result['survivors'];
        $funded = null;
        $attestation = null;
        if (isset($campaign->timeline['pilot_attack_fuel'])) {
            [$receipt, $attestation] = $this->fundedProof($campaign, $membership, $attempt);
            if ($survivors !== []) {
                $origin = $this->lockFundedOrigin($campaign, $membership, $receipt);
                $now = $this->now(); // Ne pas décompter la durée dans l'attente du verrou de la planète.
                if (!$membership->universe->isAccessible($now)) {
                    throw new \DomainException('Univers inaccessible lors de la résolution du retour.');
                }
                $funded = PirateFundedReturn::atResolution($receipt, $survivors, $now, $origin !== null);
            }
            $return = $funded?->dueAt;
        } else {
            $return = $survivors === [] ? null : $now->modify('+'.$campaign->travelSeconds.' seconds');
        }
        $timeline->recordPreparedResolution($attempt, $now, $pending->workerDueAt(), $result['outcome'], $return);
        // Retirer le checkpoint ET changer de jeton dans la transaction du rapport.
        // Si l'outbox/stock échoue, le checkpoint initial reste intact et reprenable.
        $this->publishBattle($campaign, $timeline, $result, $survivors);
        if ($funded !== null) {
            $campaign->timeline['pilot_return'] = $funded->checkpoint($attempt, $now);
            if ($funded->dueAt === null) {
                $campaign->returnBlocked = true;
                if ($funded->decision === PirateFundedReturn::ORIGIN_UNAVAILABLE) {
                    $evidence = $attestation->heldAtBattlefield(
                        $campaign, $attempt, $attempt, $now, false, $receipt->survivorPorters($survivors),
                    );
                    $this->recordBlockedEvidence($campaign, $membership, $evidence);
                }
            }
        }
        if ($result['outcome'] === 'attacker_wins') {
            $this->convoys->awardInVictoryTransaction($campaign, $membership, $now);
        }
    }

    /** @return array{PirateAttackFuelReceipt,PirateReturnAttestation} */
    private function fundedProof(PirateAttackCampaign $campaign, PlayerUniverse $membership, int $attempt): array
    {
        // L'opt-in protège les nouveaux ordres. Les jobs déjà reçus persistent leur preuve économique et
        // doivent finir même si la page est fermée entre le départ et le retour.
        if (!self::usesPrivateArrival($campaign) || ($campaign->timeline['convoy_pilot'] ?? null) !== true
            || $campaign->reconnaissance->pilotScoutFuelFor($membership, $this->now()) === null) {
            throw new \LogicException('Cette tentative n’utilise pas le retour du pilote privé.');
        }
        $receipt = PirateAttackFuelReceipt::read($campaign->timeline['pilot_attack_fuel']);
        if ($receipt->attempt !== $attempt || $receipt->originId !== $campaign->originId
            || $receipt->budget->legSeconds !== $campaign->travelSeconds
            || $campaign->arrivalAt->getTimestamp() - $campaign->departedAt->getTimestamp() !== $receipt->budget->legSeconds) {
            throw new \LogicException('Le trajet et la réserve de la tentative ne correspondent pas au départ.');
        }

        return [$receipt, new PirateReturnAttestation(
            $campaign->id, $attempt, $campaign->originId, $membership->universe->id,
            $receipt->origin, [...$receipt->site, null], $receipt->budget,
        )];
    }

    private function lockFundedOrigin(PirateAttackCampaign $campaign, PlayerUniverse $membership, PirateAttackFuelReceipt $receipt): ?Planet
    {
        $origin = $this->repository->find(Planet::class, $campaign->originId);
        if ($origin === null) {
            return null;
        }
        $this->manager->lockCurrent($origin);
        $system = $origin->solarSystem;

        return $origin->owner?->id === $membership->id && $origin->owner->player?->id === $membership->player->id
            && $system->galaxy->universe->id === $membership->universe->id
            && [$system->id, $system->galaxy->id, $system->coordX, $system->coordY, $origin->position] === $receipt->origin
            ? $origin : null;
    }

    private function settleFundedReturn(PirateAttackCampaign $campaign, PirateAttackTimeline $timeline, PlayerUniverse $membership, int $attempt): void
    {
        [$receipt, $attestation] = $this->fundedProof($campaign, $membership, $attempt);
        $resolvedAt = PirateReturnAttestation::checkpointDate($campaign, 'resolved');
        $due = $timeline->returnDueAt();
        if ($resolvedAt === null || $due === null || $due != $campaign->nextAt) {
            throw new \LogicException('L’échéance du retour privé est incohérente.');
        }
        $return = PirateFundedReturn::atResolution($receipt, $campaign->reservedShips, $resolvedAt, true);
        $stored = $campaign->timeline['pilot_return'] ?? null;
        // JSON n'impose ni l'ordre des clés ni un type distinct pour un montant entier.
        if (!is_array($stored) || (!is_int($stored['carried'] ?? null) && !is_float($stored['carried'] ?? null))) {
            throw new \LogicException('La preuve du retour prévu est absente.');
        }
        $stored['carried'] = (float) $stored['carried'];
        $expected = $return->checkpoint($attempt, $resolvedAt);
        ksort($stored);
        ksort($expected);
        if ($stored !== $expected || $return->dueAt != $due || $receipt->budget->arrivalHydrogen($return->hydrogen) !== 0.0) {
            throw new \LogicException('Le solde figé ne prouve pas un trajet retour financé.');
        }
        $origin = $this->lockFundedOrigin($campaign, $membership, $receipt);
        if ($origin !== null) {
            // Attendre aussi le verrou hangar/coques avant la preuve d'arrivée effective.
            $this->installSurvivors($campaign, $membership, $origin);
        }
        $now = $this->now();
        if (!$membership->universe->isAccessible($now) || !$timeline->settleReturnWhenDue($attempt, $now)) {
            throw new \DomainException('Le retour ne peut pas être attesté maintenant.');
        }
        $campaign->timeline = $timeline->checkpoint() + array_intersect_key($campaign->timeline,
            ['arrival_flow' => true, 'arrival_observed' => true, 'convoy_pilot' => true, 'pilot_attack_fuel' => true]);
        $campaign->timeline['pilot_return'] = [
            'version' => 1, 'attempt' => $attempt, 'state' => $origin === null ? 'blocked_arrival' : 'arrived',
            'observed_at' => $now->format(DATE_ATOM), 'due_at' => $due->format(DATE_ATOM), 'carried' => 0.0,
        ];
        $this->schedule($campaign, null);
        if ($origin === null) {
            $campaign->returnBlocked = true;
            $evidence = $attestation->blockedAtArrival(
                $campaign, $attempt, $attempt, $now, false, $return->hydrogen, $receipt->survivorPorters($campaign->reservedShips),
            );
            $this->recordBlockedEvidence($campaign, $membership, $evidence);
        }
    }

    private function recordBlockedEvidence(PirateAttackCampaign $campaign, PlayerUniverse $membership, ?PirateBlockedReturnEvidence $evidence): void
    {
        if ($evidence === null) {
            throw new \LogicException('Le worker ne peut pas attester la position des porteurs.');
        }
        // L'identité vient du rapport verrouillé, jamais d'un paramètre client. Aucun lecteur public.
        $campaign->timeline['pilot_blocked_return'] = [
            'version' => 1, 'campaign' => $evidence->campaignId, 'attempt' => $evidence->attempt,
            'origin' => $evidence->originId, 'universe' => $evidence->universeId,
            'membership' => $membership->id, 'account' => $membership->player->id,
            'carrier_revision' => $evidence->carrierRevision, 'observed_at' => $evidence->arrivedAt->format(DATE_ATOM),
            'position' => $evidence->position, 'hydrogen' => $evidence->remainingHydrogen, 'phase' => $evidence->phase,
        ];
    }

    private static function usesPrivateArrival(PirateAttackCampaign $campaign): bool
    {
        return ($campaign->timeline['arrival_flow'] ?? null) === self::ARRIVAL_FLOW;
    }

    private function resolveBattle(PirateAttackCampaign $campaign, PirateAttackTimeline $timeline, int $attempt, \DateTimeImmutable $now): void
    {
        $result = $timeline->resolveWhenDue(
            $attempt,
            $now,
            $this->simulator,
            static fn (array $survivors, \DateTimeImmutable $at): \DateTimeImmutable => $at->modify('+'.$campaign->travelSeconds.' seconds'),
        );
        if ($result === null) {
            return;
        }

        $survivors = [];
        foreach ($result['survivors'] as $unit) {
            if ($unit['camp'] !== 'escort') {
                continue;
            }
            $slug = $unit['slug'];
            $tier = $unit['tier'];
            $survivors[$slug][$tier] = ($survivors[$slug][$tier] ?? 0) + ($unit['quantity'] ?? 1);
        }
        $this->publishBattle($campaign, $timeline, $result, $survivors);
    }

    private function publishBattle(PirateAttackCampaign $campaign, PirateAttackTimeline $timeline, array $result, array $survivors): void
    {
        // En remplaçant la réserve initiale par les survivants, les pertes deviennent définitives avec le rapport.
        foreach ($survivors as $slug => $tiers) {
            foreach ($tiers as $tier => $quantity) {
                if ($quantity > ($campaign->reservedShips[$slug][$tier] ?? 0)) {
                    throw new \LogicException('Les survivants dépassent la réserve privée de départ.');
                }
            }
        }
        $campaign->reservedShips = $survivors;
        $campaign->report = [
            'outcome' => $result['outcome'],
            'rounds_played' => $result['rounds_played'],
            'losses' => $result['losses'],
        ];
        $campaign->timeline = $timeline->checkpoint() + array_intersect_key($campaign->timeline,
            ['arrival_flow' => true, 'arrival_observed' => true, 'convoy_pilot' => true, 'pilot_attack_fuel' => true]);
        $this->schedule($campaign, $timeline->returnDueAt());
    }

    private function settleReturn(PirateAttackCampaign $campaign, PirateAttackTimeline $timeline, PlayerUniverse $membership, int $attempt, \DateTimeImmutable $now): void
    {
        if (!$timeline->settleReturnWhenDue($attempt, $now)) {
            return;
        }
        $origin = $this->repository->find(Planet::class, $campaign->originId);
        if ($origin !== null) {
            $this->manager->lockCurrent($origin);
        }
        if ($origin === null || $origin->owner?->id !== $membership->id
            || $origin->solarSystem->galaxy->universe->id !== $membership->universe->id) {
            // Ne pas consommer ou rediriger les survivants si la colonie ne correspond plus.
            $campaign->returnBlocked = true;
            $this->schedule($campaign, null);

            return;
        }
        $this->installSurvivors($campaign, $membership, $origin);
        $campaign->timeline = $timeline->checkpoint() + array_intersect_key($campaign->timeline,
            ['arrival_flow' => true, 'arrival_observed' => true, 'convoy_pilot' => true, 'pilot_attack_fuel' => true]);
        $this->schedule($campaign, null);
    }

    private function installSurvivors(PirateAttackCampaign $campaign, PlayerUniverse $membership, Planet $origin): void
    {
        $idle = $this->idleFleet($membership, $origin);
        if ($idle === null) {
            // Ce hangar local est la seule flotte que la campagne peut créer, jamais une flotte active.
            $idle = new Fleet();
            $idle->homePlanet = $origin;
            $idle->playerUniverse = $membership;
            $this->manager->save($idle);
            $this->manager->flush();
        } else {
            $this->manager->lockCurrent($idle);
        }
        foreach ($campaign->reservedShips as $slug => $ranks) {
            foreach ($ranks as $tier => $count) {
                $ship = $this->repository->one(FleetShip::class, ['fleet' => $idle, 'slug' => $slug, 'tier' => $tier]);
                if ($ship === null) {
                    $ship = new FleetShip();
                    $ship->fleet = $idle;
                    $ship->slug = $slug;
                    $ship->tier = $tier;
                    $this->manager->save($ship);
                } else {
                    $this->manager->lockCurrent($ship);
                }
                $ship->quantity += $count;
            }
        }
        $campaign->reservedShips = [];
    }

    private function schedule(PirateAttackCampaign $campaign, ?\DateTimeImmutable $dueAt): void
    {
        $campaign->nextAt = $dueAt;
        $campaign->dispatchToken = null;
        if ($dueAt !== null) {
            $campaign->dispatchToken = Uuid::v4()->toRfc4122();
            $event = new OutboxMessage();
            $event->kind = PirateAttackDue::KIND;
            $event->targetId = $campaign->id;
            $event->token = $campaign->dispatchToken;
            $event->dueAt = $dueAt;
            $this->manager->save($event);
        }
    }

    private function idleFleet(PlayerUniverse $membership, Planet $origin): ?Fleet
    {
        return $this->repository->one(Fleet::class, ['playerUniverse' => $membership, 'homePlanet' => $origin, 'status' => 'idle']);
    }

    private function now(): \DateTimeImmutable
    {
        $now = $this->clock->now()->setTimezone(new \DateTimeZone('UTC'));

        return $now->setTimestamp($now->getTimestamp());
    }

    public function statusFor(PlayerUniverse $membership, string $missionKey): ?array
    {
        if ($membership->id === null || $membership->player?->id === null || !$membership->isPlayerControlled()) {
            return null;
        }
        $recon = $this->repository->ownPirateReconnaissance($membership, $missionKey, $this->now());
        $campaign = $recon === null ? null : $this->repository->one(PirateAttackCampaign::class, ['reconnaissance' => $recon]);
        if ($campaign === null) {
            return null;
        }

        return [
            'attempt' => $campaign->timeline['attempt'],
            'state' => $campaign->returnBlocked
                ? (($campaign->timeline['pilot_return']['state'] ?? null) === PirateFundedReturn::INSUFFICIENT_HYDROGEN
                    ? 'return_fuel_insufficient' : 'return_blocked')
                : ($campaign->report === null && self::usesPrivateArrival($campaign) && isset($campaign->timeline['arrival_observed'])
                    ? 'combat_in_progress' : $campaign->timeline['state']),
            'arrival_at' => $campaign->arrivalAt->format(DATE_ATOM),
            // En V2, nextAt varie avec le résultat opaque. Même une date seule informerait sur l'issue.
            'next_at' => self::usesPrivateArrival($campaign) && $campaign->report === null
                ? null : $campaign->nextAt?->format(DATE_ATOM),
            'report' => $campaign->report,
        ];
    }
}
