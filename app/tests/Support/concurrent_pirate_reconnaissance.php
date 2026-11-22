<?php

declare(strict_types=1);

require dirname(__DIR__).'/bootstrap.php';

use App\Entity\{Planet, PlayerUniverse, SolarSystem};
use App\Kernel;
use App\Service\PirateCampaign\{PirateDistressSite, PirateEncounterSnapshot, PirateReconnaissanceService};
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Clock\MockClock;

$input = json_decode($argv[1], true, 512, JSON_THROW_ON_ERROR);
$kernel = new Kernel('test', true);
$kernel->boot();
$container = $kernel->getContainer()->get('test.service_container');
$container->set('clock', new MockClock($input['now']));
$em = $container->get(EntityManagerInterface::class);
$connection = $em->getConnection();
$params = $connection->getParams();
if (($params['host'] ?? '') !== 'database-test' || ($params['dbname'] ?? '') !== 'genesis_v2_test') {
    throw new RuntimeException('Base de test non isolée.');
}

$member = $em->find(PlayerUniverse::class, $input['member']);
$origin = $em->find(Planet::class, $input['origin']);
$target = $em->find(SolarSystem::class, $input['system']);
$site = PirateDistressSite::inExistingSystem($member, $target);
$service = $container->get(PirateReconnaissanceService::class);
file_put_contents($input['ready'], (string) $connection->fetchOne('SELECT CONNECTION_ID()'));
$deadline = microtime(true) + 15;
while (!is_file($input['barrier'])) {
    if (microtime(true) >= $deadline) {
        throw new RuntimeException('Barrière de test non ouverte.');
    }
    usleep(10000);
}

$result = ($input['pilot'] ?? false)
    ? $service->launchDefinedWithPilotFuel($member, $origin, $target, $site, 'test-course', 'balise_17', new \App\Service\PirateCampaign\PiratePilotGate('dev'))
    : $service->launch($member, $origin, $target, $site, 'test-course', PirateEncounterSnapshot::freeze(['fighter' => ['basic' => 3]], ['metal' => 83]));
echo $result ? "launched\n" : "duplicate\n";
