<?php

declare(strict_types=1);

require dirname(__DIR__).'/bootstrap.php';

use App\Entity\PlayerUniverse;
use App\Kernel;
use App\Service\PirateCampaign\PirateSurvivorRecoveryService;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Clock\ClockInterface;

$input = json_decode($argv[1], true, flags: JSON_THROW_ON_ERROR);
$kernel = new Kernel('test', true);
$kernel->boot();
$container = $kernel->getContainer()->get('test.service_container');
$container->set('clock', new readonly class ($input['clock_file']) implements ClockInterface {
    public function __construct(private string $path)
    {
    }

    public function now(): DateTimeImmutable
    {
        return new DateTimeImmutable(trim(file_get_contents($this->path)));
    }
});
$em = $container->get(EntityManagerInterface::class);
$db = $em->getConnection();
$params = $db->getParams();
if (($params['host'] ?? '') !== 'database-test' || ($params['dbname'] ?? '') !== 'genesis_v2_test') {
    throw new RuntimeException('Base de test non isolée.');
}

file_put_contents($input['ready'], (string) $db->fetchOne('SELECT CONNECTION_ID()'));
$deadline = microtime(true) + 15;
while (!is_file($input['barrier'])) {
    if (microtime(true) >= $deadline) {
        throw new RuntimeException('Barrière de test non ouverte.');
    }
    usleep(10000);
}

$owner = $em->find(PlayerUniverse::class, $input['owner']);
$recovery = $container->get(PirateSurvivorRecoveryService::class);
$result = $recovery->launch($owner, $input['campaign'], 1, 1, $input['target'], $input['digest'], new \App\Service\PirateCampaign\PiratePilotGate('dev'));
echo json_encode($result, JSON_THROW_ON_ERROR)."\n";
