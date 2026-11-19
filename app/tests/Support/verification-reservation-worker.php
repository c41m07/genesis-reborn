<?php

require dirname(__DIR__).'/bootstrap.php';
$kernel = new App\Kernel('test', true);
$kernel->boot();
$container = $kernel->getContainer()->get('test.service_container');
$connection = $container->get(Doctrine\ORM\EntityManagerInterface::class)->getConnection();
$params = $connection->getParams();
if (($params['host'] ?? '') !== 'database-test' || ($params['dbname'] ?? '') !== 'genesis_v2_test') {
    throw new RuntimeException('Base non isolée.');
}
$connection->executeQuery('SELECT 1');
touch($argv[3].'/ready-'.$argv[4]);
$deadline = microtime(true) + 15;
while (!is_file($argv[3].'/go')) {
    if (microtime(true) > $deadline) {
        throw new RuntimeException('Signal de départ absent.');
    }
    usleep(10000);
}
$em = $container->get(Doctrine\ORM\EntityManagerInterface::class);
$service = new App\Service\Player\VerificationRequestReservation($em, new App\Manager\GameManager($em), new App\Repository\VerificationDeliveryRequestRepository($em), $container->get(Psr\Clock\ClockInterface::class));
if (($argv[5] ?? '') === 'claim') {
    $lifecycle = new App\Service\Player\VerificationRequestLifecycle($em, new App\Manager\GameManager($em), $container->get(Psr\Clock\ClockInterface::class));
    $result = $lifecycle->claim((int) $argv[1], 60);
    // Le test de compétition n'a besoin ni d'afficher ni de conserver le secret.
    unset($result['claim_token']);
} else {
    $result = $service->reserve((int) $argv[1], $argv[2], 300);
}
echo json_encode($result, JSON_THROW_ON_ERROR);
$kernel->shutdown();
