<?php

require dirname(__DIR__) . '/bootstrap.php';

use App\Entity\{Planet, PlayerUniverse};
use App\Kernel;
use App\Message\QueueItemFinished;
use App\MessageHandler\QueueItemFinishedHandler;
use App\Service\Queue\QueueService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Clock\MockClock;

$input = json_decode($argv[1], true, 512, JSON_THROW_ON_ERROR);
$kernel = new Kernel('test', true);
$kernel->boot();
$container = $kernel->getContainer()->get('test.service_container');
$container->set('clock', new MockClock($input['now']));
$entityManager = $container->get(EntityManagerInterface::class);
$connection = $entityManager->getConnection();
$params = $connection->getParams();
if (($params['host'] ?? '') !== 'database-test' || ($params['dbname'] ?? '') !== 'genesis_v2_test') {
    throw new RuntimeException('Base de test non isolée.');
}

// La connexion est identifiable avant de libérer ensemble les deux processus.
file_put_contents($input['ready'], (string) $connection->fetchOne('SELECT CONNECTION_ID()'));
$deadline = microtime(true) + 15;
while (!is_file($input['barrier'])) {
    if (microtime(true) > $deadline) {
        throw new RuntimeException('Barrière de test non libérée.');
    }
    usleep(10000);
}

try {
    if ($input['action'] === 'complete') {
        $container->get(QueueItemFinishedHandler::class)(new QueueItemFinished($input['queueId'], $input['token']));
    } else {
        $membership = $entityManager->find(PlayerUniverse::class, $input['membershipId']);
        $planet = $entityManager->find(Planet::class, $input['planetId']);
        if ($input['action'] === 'station') {
            $container->get(\App\Service\Fleet\FleetService::class)->send($membership, $planet, 'station', $input['targetId'], ['hydrogen_collector'=>1], 1, [], $input['key']);
        } else {
            $container->get(QueueService::class)->add($membership, $planet, $input['group'] ?? 'buildings', $input['slug'] ?? 'mine_metal', $input['quantity'] ?? 1, $input['key'], $input['tier'] ?? 'basic');
        }
    }
    exit(0);
} catch (DomainException) {
    exit(10);
}
