<?php

declare(strict_types=1);

require dirname(__DIR__).'/bootstrap.php';

use App\Kernel;
use App\Service\Boost\BoostEventService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Clock\MockClock;

$kernel = new Kernel('test', true);
$kernel->boot();
$container = $kernel->getContainer()->get('test.service_container');
$connection = $container->get(EntityManagerInterface::class)->getConnection();
$params = $connection->getParams();
if (($params['host'] ?? '') !== 'database-test' || ($params['dbname'] ?? '') !== 'genesis_v2_test') {
    throw new RuntimeException('Base de recette non isolée.');
}
$container->set('clock', new MockClock($argv[1]));
$events = $container->get(BoostEventService::class);
echo 'connection='.$connection->fetchOne('SELECT CONNECTION_ID()')."\n";
flush();
$events->tick();
echo "tick-completed\n";
