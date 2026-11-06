<?php
namespace App\Tests\Unit;

use App\Command\OperationsCheckCommand;
use App\Service\Operations\{AlertEvaluator,AlertNotifier};
use PHPUnit\Framework\TestCase;
use Symfony\Component\Clock\MockClock;
use Symfony\Component\Mailer\MailerInterface;

final class OperationsAlertsTest extends TestCase
{
    private string $directory;
    protected function setUp(): void { $this->directory=sys_get_temp_dir().'/genesis-alerts-'.bin2hex(random_bytes(8)); }
    protected function tearDown(): void
    {
        if (is_dir($this->directory)) { foreach(glob($this->directory.'/*') as $file) unlink($file);rmdir($this->directory); }
    }
    private function snapshot(): array
    {
        return ['processes'=>array_fill(0,3,['status'=>'healthy','age_seconds'=>0]),'database_healthy'=>true,'outbox_available'=>true,'outbox_pending'=>[]];
    }
    public function testThresholdsAndSupervisionAreDistinct(): void
    {
        $evaluator=new AlertEvaluator();$snapshot=$this->snapshot();$states=['worker'=>'running','relay'=>'running','notifier'=>'running'];
        self::assertSame(['ok'],array_values(array_unique(array_column($evaluator->evaluate($snapshot,$states,0),'status'))));
        $snapshot['processes'][0]=['status'=>'stale','age_seconds'=>119];
        self::assertSame('ok',$evaluator->evaluate($snapshot,$states,0)['worker']['status']);
        $snapshot['processes'][0]['age_seconds']=120;
        self::assertSame('alert',$evaluator->evaluate($snapshot,$states,0)['worker']['status']);
        $snapshot['outbox_pending']=[['overdue_seconds'=>299]];
        self::assertSame('ok',$evaluator->evaluate($snapshot,$states,0)['outbox']['status']);
        $snapshot['outbox_pending'][0]['overdue_seconds']=300;
        self::assertSame('alert',$evaluator->evaluate($snapshot,$states,1)['outbox']['status']);
        self::assertSame('alert',$evaluator->evaluate($snapshot,$states,1)['failed']['status']);
        $snapshot['processes'][0]=['status'=>'missing','age_seconds'=>null];
        self::assertSame(120,$evaluator->evaluate($snapshot,$states,0)['worker']['grace']);
        $states['worker']='exited';
        self::assertSame(0,$evaluator->evaluate($snapshot,$states,0)['worker']['grace']);
        self::assertStringContainsString('arrêté',$evaluator->evaluate($snapshot,$states,0)['worker']['message']);
        unset($states['worker']);
        self::assertSame('unknown',$evaluator->evaluate($snapshot,$states,0)['worker']['status']);
        self::assertSame('alert',$evaluator->evaluate($snapshot,$states,0)['collection']['status']);
        $snapshot['outbox_available']=false;
        self::assertSame('unknown',$evaluator->evaluate($snapshot,$states,null)['outbox']['status']);
        self::assertSame('unknown',$evaluator->evaluate($snapshot,$states,null)['failed']['status']);
    }
    public function testGraceReminderRestartUnknownAndRecovery(): void
    {
        $clock=new MockClock('2030-01-01T00:00:00Z');$subjects=[];
        $mailer=$this->createMock(MailerInterface::class);
        $mailer->expects(self::exactly(3))->method('send')->willReturnCallback(static function($mail)use(&$subjects){$subjects[]=$mail->getSubject();});
        $notifier=new AlertNotifier($mailer,$clock,$this->directory,true,'ops@example.test','app@example.test','https://game.example.test');
        $alert=['worker'=>['status'=>'alert','message'=>'Signal absent','grace'=>120]];
        self::assertSame(0,$notifier->run($alert,true)['sent']);
        $clock->sleep(119);self::assertSame(0,$notifier->run($alert,true)['sent']);
        $clock->sleep(1);self::assertSame(1,$notifier->run($alert,true)['sent']);
        $notifier=new AlertNotifier($mailer,$clock,$this->directory,true,'ops@example.test','app@example.test','https://game.example.test');
        self::assertSame(0,$notifier->run(['worker'=>['status'=>'unknown','message'=>'Collecte indisponible']],true)['sent']);
        $clock->sleep(3599);self::assertSame(0,$notifier->run($alert,true)['sent']);
        $clock->sleep(1);self::assertSame(1,$notifier->run($alert,true)['sent']);
        $clear=['worker'=>['status'=>'ok','message'=>'Signal revenu']];
        self::assertSame(1,$notifier->run($clear,true)['sent']);
        self::assertSame(0,$notifier->run($clear,true)['sent']);
        self::assertStringContainsString('Rappel',$subjects[1]);self::assertStringContainsString('Rétablissement',$subjects[2]);
    }
    public function testSimulationAndDisabledConfigurationNeverSendOrWriteState(): void
    {
        $mailer=$this->createMock(MailerInterface::class);$mailer->expects(self::never())->method('send');
        foreach([[false,'ops@example.test',true],[true,'',true],[true,'ops@example.test',false]] as [$enabled,$to,$send]) {
            $notifier=new AlertNotifier($mailer,new MockClock(),$this->directory,$enabled,$to,'app@example.test','https://game.example.test');
            self::assertSame('simulation',$notifier->run(['failed'=>['status'=>'alert','message'=>'Échec','grace'=>0]],$send)['mode']);
            self::assertDirectoryDoesNotExist($this->directory);
        }
    }
    public function testTransportFailureDoesNotMarkAlertDelivered(): void
    {
        $mailer=$this->createMock(MailerInterface::class);$calls=0;
        $mailer->expects(self::exactly(2))->method('send')->willReturnCallback(static function()use(&$calls){if(++$calls===1) throw new \RuntimeException('Transport indisponible');});
        $notifier=new AlertNotifier($mailer,new MockClock(),$this->directory,true,'ops@example.test','app@example.test','https://game.example.test');
        $alert=['failed'=>['status'=>'alert','message'=>'Échec','grace'=>0]];
        try { $notifier->run($alert,true);self::fail('Échec attendu'); } catch(\RuntimeException $error) { self::assertSame('Transport indisponible',$error->getMessage()); }
        self::assertSame(1,$notifier->run($alert,true)['sent']);
    }
    public function testCorruptStateFailsWithoutResetOrMail(): void
    {
        mkdir($this->directory);file_put_contents($this->directory.'/alerts.json','invalid-json');
        $mailer=$this->createMock(MailerInterface::class);$mailer->expects(self::never())->method('send');
        $notifier=new AlertNotifier($mailer,new MockClock(),$this->directory,true,'ops@example.test','app@example.test','https://game.example.test');
        $this->expectException(\JsonException::class);$notifier->run([],true);
    }
    public function testConcurrentMonitorCannotSend(): void
    {
        mkdir($this->directory);$lock=fopen($this->directory.'/alerts.lock','c');flock($lock,LOCK_EX);
        $mailer=$this->createMock(MailerInterface::class);$mailer->expects(self::never())->method('send');
        $notifier=new AlertNotifier($mailer,new MockClock(),$this->directory,true,'ops@example.test','app@example.test','https://game.example.test');
        try { $notifier->run(['failed'=>['status'=>'alert','message'=>'Échec','grace'=>0]],true);self::fail('Verrou attendu'); }
        catch(\RuntimeException $error) { self::assertStringContainsString('déjà en cours',$error->getMessage()); }
        finally { flock($lock,LOCK_UN);fclose($lock); }
    }
    public function testEarlierSuccessfulMailSurvivesLaterFailure(): void
    {
        $mailer=$this->createMock(MailerInterface::class);$calls=0;
        $mailer->expects(self::exactly(3))->method('send')->willReturnCallback(static function()use(&$calls){if(++$calls===2) throw new \RuntimeException('Transport indisponible');});
        $notifier=new AlertNotifier($mailer,new MockClock(),$this->directory,true,'ops@example.test','app@example.test','https://game.example.test');
        $conditions=['worker'=>['status'=>'alert','message'=>'Arrêt','grace'=>0],'failed'=>['status'=>'alert','message'=>'Échec','grace'=>0]];
        try { $notifier->run($conditions,true);self::fail('Échec attendu'); }catch(\RuntimeException) {}
        self::assertSame(1,$notifier->run($conditions,true)['sent']);
    }
    public function testDockerArrayAndJsonLinesAreAccepted(): void
    {
        $rows=[['Service'=>'worker','State'=>'exited'],['Service'=>'relay','State'=>'running'],['Service'=>'app','State'=>'running']];
        self::assertSame(['worker'=>'exited','relay'=>'running'],OperationsCheckCommand::parseProcesses(json_encode($rows)));
        self::assertSame(['worker'=>'exited','relay'=>'running'],OperationsCheckCommand::parseProcesses(implode("\n",array_map('json_encode',$rows))));
    }
    public function testDuplicateProcessesAreRejectedInsteadOfAssumedHealthy(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        OperationsCheckCommand::parseProcesses('[{"Service":"worker","State":"running"},{"Service":"worker","State":"exited"}]');
    }
}
