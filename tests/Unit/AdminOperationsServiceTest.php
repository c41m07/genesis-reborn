<?php
namespace App\Tests\Unit;

use App\Service\Admin\AdminOperationsService;
use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Clock\MockClock;

final class AdminOperationsServiceTest extends TestCase
{
    public function testProductionSharesSignalsWithReadOnlyApplication(): void
    {
        $config=\Symfony\Component\Yaml\Yaml::parseFile(dirname(__DIR__,2).'/compose.prod.yaml');
        self::assertArrayHasKey('process_runtime',$config['volumes']);
        foreach(['app','worker','relay','notifier'] as $name) {
            $mount='process_runtime:/app/var/run'.($name==='app'?':ro':'');
            self::assertContains($mount,$config['services'][$name]['volumes']);
        }
    }
    public function testSignalsAreRefreshedAndNeverProveProcessStopped(): void
    {
        $directory=sys_get_temp_dir().'/genesis-signals-'.bin2hex(random_bytes(8));
        mkdir($directory);
        $db=$this->createStub(Connection::class);
        $db->method('fetchOne')->willReturn(1);
        $db->method('fetchAllAssociative')->willReturn([]);
        $clock=new MockClock('2030-01-01T00:00:00Z');
        $service=new AdminOperationsService($db,$clock,$directory);
        try {
            self::assertSame('missing',$service->snapshot()['processes'][0]['status']);
            file_put_contents($directory.'/worker.heartbeat','test');
            touch($directory.'/worker.heartbeat',$clock->now()->getTimestamp());
            self::assertSame('healthy',$service->snapshot()['processes'][0]['status']);
            $clock->sleep(45);
            self::assertSame('stale',$service->snapshot()['processes'][0]['status']);
            chmod($directory.'/worker.heartbeat',0000);
            self::assertSame('unknown',$service->snapshot()['processes'][0]['status']);
            chmod($directory.'/worker.heartbeat',0600);
            touch($directory.'/worker.heartbeat',$clock->now()->getTimestamp()+1);
            self::assertSame('unknown',$service->snapshot()['processes'][0]['status']);
        } finally {
            if(is_file($directory.'/worker.heartbeat')) unlink($directory.'/worker.heartbeat');
            rmdir($directory);
        }
    }
}
