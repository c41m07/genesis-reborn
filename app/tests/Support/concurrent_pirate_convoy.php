<?php

declare(strict_types=1);

require dirname(__DIR__).'/bootstrap.php';

use App\Kernel;
use App\Entity\PlayerUniverse;
use App\Service\PirateCampaign\PirateConvoyDeliveryService;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Clock\ClockInterface;
use Symfony\Component\Clock\MockClock;

$input = json_decode($argv[1], true, 512, JSON_THROW_ON_ERROR);
$kernel = new Kernel('test', true);
$kernel->boot();
$container = $kernel->getContainer()->get('test.service_container');
$clock = isset($input['clock_file'])
    ? new readonly class ($input['clock_file']) implements ClockInterface {
        public function __construct(private string $path)
        {
        }

        public function now(): DateTimeImmutable
        {
            return new DateTimeImmutable(trim(file_get_contents($this->path)));
        }
    }
    : new MockClock($input['now']);
$container->set('clock', $clock);
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

$convoys = $container->get(PirateConvoyDeliveryService::class);
if ($input['operation'] === 'worker') {
    $convoys->processDue($input['right'], $input['token']);
    echo "processed\n";
} else {
    $owner = $container->get(EntityManagerInterface::class)->find(PlayerUniverse::class, $input['owner']);
    $result = match ($input['operation']) {
        'claim' => $convoys->retryDeposit($owner, $input['right']),
        'retarget' => $convoys->chooseOwnedDestination($owner, $input['right'], $input['destination']),
        default => throw new RuntimeException('Opération de recette inconnue.'),
    };
    echo json_encode($result, JSON_THROW_ON_ERROR)."\n";
}
