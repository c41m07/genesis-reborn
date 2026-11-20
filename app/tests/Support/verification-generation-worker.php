<?php

require dirname(__DIR__).'/bootstrap.php';

use App\Service\Player\{VerificationGenerationStore, VerificationLinkKeyResolver, VersionedVerificationLink};
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Clock\MockClock;

$kernel = new App\Kernel('test', true);
$kernel->boot();
$em = $kernel->getContainer()->get('test.service_container')->get(EntityManagerInterface::class);
$db = $em->getConnection();
$params = $db->getParams();
if (($params['host'] ?? '') !== 'database-test' || ($params['dbname'] ?? '') !== 'genesis_v2_test') {
    throw new RuntimeException('Base non isolée.');
}
$db->executeQuery('SELECT 1');
touch($argv[3].'/ready-'.$argv[4]);
$deadline = microtime(true) + 15;
while (!is_file($argv[3].'/go')) {
    if (microtime(true) > $deadline) {
        throw new RuntimeException('Barrière de test expirée.');
    }
    usleep(10000);
}
$clock = new MockClock('2030-01-01T00:00:00Z');
$codec = new VersionedVerificationLink($clock);
$store = new VerificationGenerationStore($db, $clock, $codec);
$keys = new class implements VerificationLinkKeyResolver {
    public function resolve(string $keyId): ?string
    {
        return $keyId === 'fixture' ? str_repeat('k', 32) : null;
    }
};
$player = (int) $argv[1];
$id = (int) $argv[5];
$result = match ($argv[2]) {
    'prepare' => $store->prepare($player, 'fixture', $clock->now()->modify('+'.($argv[4] === 'a' ? '60' : '120').' minutes'), $keys)?->version,
    'writer' => $store->prepareForEmission($player, 'fixture', $clock->now()->modify('+'.($argv[4] === 'a' ? '60' : '120').' minutes'), $keys)['result'],
    'revoke' => $store->revoke($player, $id),
    'consume' => $store->consume($player, $id, stream_get_contents(STDIN)),
    'prepare-emission' => (new App\Service\Player\VerificationRequestLifecycle($em, new App\Manager\GameManager($em), $clock))->prepareVersionedEmission($id, stream_get_contents(STDIN), $keys) !== null,
    'prepare-handoff' => (new App\Service\Player\VerificationRequestLifecycle($em, new App\Manager\GameManager($em), $clock))->prepareRecoveryDelivery($id, stream_get_contents(STDIN), $keys)['result'],
    'claim-recovery' => (new App\Service\Player\VerificationRequestLifecycle($em, new App\Manager\GameManager($em), $clock))->claimRecovery($id, 120)['result'],
    'mixed' => (new App\Service\Player\MixedVerificationLinkReader($em, new App\Manager\GameManager($em), $codec, $clock))->confirm(stream_get_contents(STDIN)),
    'reserve-recovery-admin' => (function () use ($db, $clock, $codec, $player, $id, $keys, $argv): array {
        try {
            return (new App\Service\Player\VerificationRecoveryOrchestrator($db, $clock, $codec))->reserveForAdmin((int) $argv[6], $player, $id, str_repeat('a', 64), 300, $keys);
        } catch (DomainException $e) {
            return ['result' => 'denied'];
        }
    })(),
    'reserve-recovery' => (new App\Service\Player\VerificationRecoveryOrchestrator($db, $clock, $codec))->reserve($player, $id, str_repeat($argv[4], 64), 300, $keys),
    'recover' => $store->recordRecovery($player, $id, str_repeat($argv[4], 64), 300),
};
echo json_encode($result, JSON_THROW_ON_ERROR);
$kernel->shutdown();
