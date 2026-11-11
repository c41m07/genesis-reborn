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
        $config=\Symfony\Component\Yaml\Yaml::parseFile(dirname(__DIR__,3).'/docker/compose.prod.yaml');
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
    public function testFailedOutboxCollectionIsUnknownRatherThanEmpty(): void
    {
        $db=$this->createStub(Connection::class);
        $db->method('fetchOne')->willReturn(1);
        $db->method('fetchAllAssociative')->willThrowException(new \RuntimeException('Table indisponible'));
        $snapshot=(new AdminOperationsService($db,new MockClock('2030-01-01T00:00:00Z'),sys_get_temp_dir().'/absent-signals'))->snapshot();
        self::assertTrue($snapshot['database_healthy']);
        self::assertFalse($snapshot['outbox_available']);
        self::assertNull($snapshot['outbox_total']);
        self::assertNull($snapshot['outbox_overdue']);
    }

    public function testPendingDiagnosticsIncludeUnknownKindsButNeverTokens(): void
    {
        $db=$this->createStub(Connection::class);
        $db->method('fetchOne')->willReturn(1);
        $db->method('fetchAllAssociative')->willReturnCallback(static function(string $sql):array {
            if(str_contains($sql,'GROUP BY')) return [
                ['kind'=>'queue','total'=>2,'overdue'=>1],
                ['kind'=>'unexpected','total'=>1,'overdue'=>1],
            ];
            self::assertStringContainsString('LIMIT 20',$sql);
            self::assertStringNotContainsString('token',$sql);
            return [
                ['id'=>9,'kind'=>'unexpected','target_id'=>42,'due_at'=>'2029-12-31 23:55:30'],
                ['id'=>10,'kind'=>'queue','target_id'=>43,'due_at'=>'2030-01-01 00:00:10'],
            ];
        });
        $snapshot=(new AdminOperationsService($db,new MockClock('2030-01-01T00:00:00Z'),sys_get_temp_dir().'/absent-signals'))->snapshot();
        self::assertTrue($snapshot['outbox_available']);
        self::assertSame(3,$snapshot['outbox_total']);
        self::assertSame(2,$snapshot['outbox_overdue']);
        self::assertSame(1,$snapshot['outbox']['other']);
        self::assertSame(270,$snapshot['outbox_pending'][0]['overdue_seconds']);
        self::assertSame(0,$snapshot['outbox_pending'][1]['overdue_seconds']);
        self::assertSame('unexpected',$snapshot['outbox_pending'][0]['kind']);
        self::assertArrayNotHasKey('token',$snapshot['outbox_pending'][0]);
    }
    public function testFleetCollectionFailureIsUnknownRatherThanZero(): void
    {
        $db=$this->createStub(Connection::class);
        $db->method('fetchOne')->willReturn(1);
        $db->method('fetchAllAssociative')->willReturn([]);
        $db->method('fetchAssociative')->willThrowException(new \RuntimeException('Collecte indisponible'));
        $health=(new AdminOperationsService($db,new MockClock('2030-01-01T00:00:00Z'),sys_get_temp_dir().'/absent-signals'))->snapshot()['fleet_health'];
        self::assertFalse($health['available']);
        self::assertNull($health['total']);
        self::assertNull($health['unscheduled']);
        self::assertNull($health['overdue']);
        self::assertSame([],$health['pending']);
    }

}
