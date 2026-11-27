<?php

declare(strict_types=1);

use App\Entity\{PirateAttackCampaign, PirateConvoyDelivery, PirateReconnaissance, Planet};
use App\Kernel;
use App\Service\Catalog\GameCatalog;
use App\Service\Combat\{CombatUnitFactory, TechnologyEffectCalculator};
use App\Service\PirateCampaign\{PirateCampaignCatalogue, PirateConvoyDispatchToken, PirateScoutFuelReceipt};
use Symfony\Component\Dotenv\Dotenv;

// Fixture CLI du navigateur isolé ; aucune réservation, publication outbox ou exécution de flotte.
if (PHP_SAPI !== 'cli' || !preg_match('/^genesis-browser-test-[0-9]+-[0-9]+$/D', getenv('P22_TEST_PROJECT') ?: '')) {
    throw new RuntimeException('Ce script exige le projet navigateur jetable.');
}
require '/app/vendor/autoload.php';
(new Dotenv())->bootEnv('/app/.env');
$kernel = new Kernel('prod', false);
$kernel->boot();
$em = $kernel->getContainer()->get('doctrine')->getManager();
$origin = $em->find(Planet::class, (int) ($argv[1] ?? 0));
$member = $origin?->owner;
if (!$member || !preg_match('/^p22-[a-f0-9]{8}@example[.]test$/D', $member->player->email)) {
    throw new RuntimeException('Le compte doit avoir été créé par la fixture P22.');
}
$at = new DateTimeImmutable(gmdate(DATE_ATOM));
$key = 'balise_17';
$mode = $argv[2] ?? '';
$token = 'p22-browser-fixture';
$repository = $em->getRepository(PirateReconnaissance::class);
$recon = $repository->findOneBy(['membership' => $member, 'missionKey' => $key]);

if ($mode === 'report' && $recon === null) {
    $catalog = new GameCatalog('/app/config/game');
    $campaign = new PirateCampaignCatalogue($catalog, new CombatUnitFactory($catalog, new TechnologyEffectCalculator($catalog)));
    $prepared = $campaign->prepareForServer($key, $member->universe);
    $proof = PirateScoutFuelReceipt::read(['version' => 1, 'origin' => $origin->id, 'seconds' => 30,
        'reserved' => 2, 'hold' => 0, 'planet' => 2]);
    $recon = new PirateReconnaissance($member, $member->player, $origin->solarSystem, $key, $at->modify('-360 seconds'),
        $at->modify('-330 seconds'), $token, false, $prepared, $proof);
    $em->persist($recon);
    $recon->reportWhenDue($member, $token, $at->modify('-300 seconds'));
} elseif (in_array($mode, ['travelling', 'full'], true) && $recon !== null) {
    $won = $at->modify('-120 seconds');
    $attack = new PirateAttackCampaign();
    $attack->reconnaissance = $recon;
    $attack->originId = $origin->id;
    $attack->travelSeconds = 30;
    $attack->roundSeconds = 60;
    $attack->departedAt = $won->modify('-90 seconds');
    $attack->arrivalAt = $won->modify('-60 seconds');
    $attack->scenario = [];
    $attack->reservedShips = [];
    $attack->report = ['outcome' => 'attacker_wins', 'losses' => []];
    $attack->timeline = ['attempt' => 1, 'state' => 'won', 'arrival_flow' => 2, 'won' => true,
        'arrival_observed' => $attack->arrivalAt->format(DATE_ATOM), 'resolved' => $won->format(DATE_ATOM)];
    $em->persist($attack);
    $em->flush();
    $delivery = PirateConvoyDelivery::forVictory($attack, $member, $won);
    $timeline = $delivery->timeline();
    $system = $origin->solarSystem;
    $destination = [$system->id, $system->galaxy->id, $system->coordX, $system->coordY, $origin->position];
    $due = $mode === 'full' ? $at->modify('-60 seconds') : $at->modify('+20 minutes');
    $phaseToken = PirateConvoyDispatchToken::generate(1);
    $dispatched = $timeline->dispatch($member->id, $member->universe->id, $origin->id, $timeline->attestedPositionForDeparture(),
        $destination, $phaseToken, $won, $due);
    if (!$dispatched) {
        throw new RuntimeException('La fixture de convoi ne satisfait pas le contrat natif.');
    }
    $delivery->replaceState($timeline);
    if ($mode === 'full') {
        // Le manque de capacité est un checkpoint de fixture, sans worker ni tentative de dépôt.
        if (!$timeline->arriveWhenDue($phaseToken, $due)
            || !$timeline->recordCapacityShortage($member->id, $member->universe->id, $origin->id, $due)) {
            throw new RuntimeException('La fixture de capacité ne satisfait pas le contrat natif.');
        }
        $delivery->replaceState($timeline);
    }
    $em->persist($delivery);
} elseif ($mode === 'combat' && $recon !== null) {
    // Le test navigateur part d’une victoire attestée. Le test de persistance exerce les phases du worker.
    $attack = $em->getRepository(PirateAttackCampaign::class)->findOneBy(['reconnaissance' => $recon]);
    $scenario = $recon->battleProofFor($member, $at)['prepared']->battleForServer();
    $attack->scenario = $scenario->snapshot();
    $formation = $attack->scenario['units'];
    foreach ($formation as &$unit) {
        $unit['quantity'] *= 10;
    }
    unset($unit);
    $attack->timeline['units'] = $formation;
    $result = $scenario->resolve(new \App\Service\Combat\CombatSimulator(), $formation, 17);
    $manager = new \App\Manager\GameManager($em);
    $reports = new \App\Service\PirateCampaign\PirateMissionReports($manager, new \App\Repository\GameRepository($em), new GameCatalog('/app/config/game'));
    $manager->transaction(fn () => $reports->battle($attack, $result, new DateTimeImmutable($attack->timeline['resolved'])));
} else {
    throw new RuntimeException('Étape de fixture inattendue.');
}
$em->flush();
