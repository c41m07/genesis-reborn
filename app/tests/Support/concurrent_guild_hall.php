<?php

declare(strict_types=1);

require dirname(__DIR__).'/bootstrap.php';

use App\Entity\PlayerUniverse;
use App\Kernel;
use App\Tests\Support\GuildHallFixture;
use Doctrine\ORM\EntityManagerInterface;

$input = json_decode($argv[1], true, 512, JSON_THROW_ON_ERROR);
$kernel = new Kernel('test', true);
$kernel->boot();
$container = $kernel->getContainer()->get('test.service_container');
$em = $container->get(EntityManagerInterface::class);
$connection = $em->getConnection();
$params = $connection->getParams();
if (($params['host'] ?? '') !== 'database-test' || ($params['dbname'] ?? '') !== 'genesis_v2_test') {
    throw new RuntimeException('Base de recette non isolée.');
}
$actor = $em->find(PlayerUniverse::class, $input['member']);
$hall = GuildHallFixture::enable($container);
file_put_contents($input['ready'], (string) $connection->fetchOne('SELECT CONNECTION_ID()'));
$deadline = microtime(true) + 15;
while (!is_file($input['barrier'])) {
    if (microtime(true) >= $deadline) {
        throw new RuntimeException('Barrière non libérée.');
    }
    usleep(10000);
}

try {
    match ($input['action']) {
        'contribute' => $hall->contribute($actor, $input['guild'], $input['planet'], 0, ['hydrogen' => 8]),
        'place' => $hall->place($actor, $input['guild'], $input['system'], 1),
    };
    echo "accepted\n";
} catch (DomainException $error) {
    echo $error->getMessage()."\n";
}
