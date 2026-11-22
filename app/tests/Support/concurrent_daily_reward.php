<?php

declare(strict_types=1);

require dirname(__DIR__).'/bootstrap.php';

use App\Entity\{Planet, Player, PlayerUniverse};
use App\Kernel;
use App\Service\Player\DailyRewardService;
use App\Service\Universe\UniverseService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Clock\MockClock;

$input = json_decode($argv[1], true, 512, JSON_THROW_ON_ERROR);
$kernel = new Kernel('test', true);
$kernel->boot();
$container = $kernel->getContainer()->get('test.service_container');
$container->set('clock', new MockClock($input['now']));
$em = $container->get(EntityManagerInterface::class);
$conn = $em->getConnection();
$params = $conn->getParams();
if (($params['host'] ?? '') !== 'database-test' || ($params['dbname'] ?? '') !== 'genesis_v2_test') {
    throw new RuntimeException('Base de test non isolée.');
}

file_put_contents($input['ready'], (string) $conn->fetchOne('SELECT CONNECTION_ID()'));
$deadline = microtime(true) + 15;
while (!is_file($input['barrier'])) {
    if (microtime(true) >= $deadline) {
        throw new RuntimeException('Barrière de test non ouverte.');
    }
    usleep(10000);
}

try {
    $player = $em->find(Player::class, $input['player']);
    if ($input['action'] === 'select') {
        $container->get(UniverseService::class)->select($player, $input['universe']);
        echo "selected\n";
    } else {
        $membership = $em->find(PlayerUniverse::class, $input['membership']);
        $planet = $em->find(Planet::class, $input['planet']);
        $result = $container->get(DailyRewardService::class)->claim($player, $membership, $planet, $input['date']);
        echo $result['state']."\n";
    }
} catch (\Throwable $error) {
    fwrite(STDERR, $error::class.": ".$error->getMessage()."\n");
    exit(1);
}
