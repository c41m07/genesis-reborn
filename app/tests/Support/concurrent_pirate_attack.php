<?php

declare(strict_types=1);

require dirname(__DIR__).'/bootstrap.php';

use App\Kernel;
use App\Service\PirateCampaign\PirateAttackService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Clock\MockClock;

$input = json_decode($argv[1], true, 512, JSON_THROW_ON_ERROR);
$kernel = new Kernel('test', true);
$kernel->boot();
$container = $kernel->getContainer()->get('test.service_container');
$container->set('clock', new MockClock($input['now']));
$connection = $container->get(EntityManagerInterface::class)->getConnection();
$params = $connection->getParams();
if (($params['host'] ?? '') !== 'database-test' || ($params['dbname'] ?? '') !== 'genesis_v2_test') {
    throw new RuntimeException('Base de test non isolée.');
}

file_put_contents($input['ready'], (string) $connection->fetchOne('SELECT CONNECTION_ID()'));
$deadline = microtime(true) + 15;
while (!is_file($input['barrier'])) {
    if (microtime(true) >= $deadline) {
        throw new RuntimeException('Barrière de test non ouverte.');
    }
    usleep(10000);
}

$container->get(PirateAttackService::class)->processDue($input['campaign'], $input['token']);
echo "processed\n";
