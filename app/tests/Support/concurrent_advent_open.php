<?php

declare(strict_types=1);

require dirname(__DIR__).'/bootstrap.php';

use App\Entity\{AdventEdition, Planet, Player, PlayerUniverse};
use App\Kernel;
use App\Service\Advent\AdventRewards;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Clock\MockClock;

$_SERVER['ADVENT_ENABLED'] = $_ENV['ADVENT_ENABLED'] = '1';
$input = json_decode($argv[1], true, 512, JSON_THROW_ON_ERROR);
$kernel = new Kernel('test', true);
$kernel->boot();
$container = $kernel->getContainer()->get('test.service_container');
$container->set('clock', new MockClock($input['when'].'Z'));
$em = $container->get(EntityManagerInterface::class);
$connection = $em->getConnection();
$params = $connection->getParams();
if (($params['host'] ?? '') !== 'database-test' || ($params['dbname'] ?? '') !== 'genesis_v2_test') {
    throw new RuntimeException('Base de recette non isolée.');
}
$player = $em->find(Player::class, $input['player']);
$membership = $em->find(PlayerUniverse::class, $input['membership']);
$planet = $em->find(Planet::class, $input['planet']);
$edition = $em->find(AdventEdition::class, $input['edition']);
$service = $container->get(AdventRewards::class);
file_put_contents($input['ready'], (string) $connection->fetchOne('SELECT CONNECTION_ID()'));
$deadline = microtime(true) + 10;
while (!is_file($input['go'])) {
    if (microtime(true) >= $deadline) {
        throw new RuntimeException('Barrière de recette non libérée.');
    }
    usleep(10000);
}

echo $service->open($player, $membership, $planet, $edition, 1, $input['choice'])['state']."\n";
