<?php
namespace App\Tests\Service;

use App\Entity\PlayerUniverse;
use App\Service\Catalog\GameCatalog;
use App\Service\Player\PlayerProtection;
use App\Service\Player\EmpireActivityService;
use App\Manager\GameManager;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use Psr\Clock\ClockInterface;

final class PlayerProtectionTest extends TestCase
{
    public function testBeginnerThresholdExpiryAndRecovery(): void
    {
        $now=new \DateTimeImmutable('2026-11-05T12:00:00Z');
        $clock=$this->createStub(ClockInterface::class);
        $clock->method('now')->willReturn($now);
        $em=$this->createStub(EntityManagerInterface::class);
        $calendar=new \App\Service\Player\VacationCalendar(new \App\Repository\GameRepository($em),$clock);
        $activity=new EmpireActivityService(new GameManager($em),$clock,$calendar,new \App\Service\Player\VacationPolicy($clock));
        $service=new PlayerProtection(new GameCatalog(dirname(__DIR__,2).'/config/game'),$clock,$activity,$calendar,false);
        $membership=new PlayerUniverse();
        $membership->player=new \App\Entity\Player();
        $membership->joinedAt=$now->modify('-6 days');
        self::assertSame(86400,$service->status($membership,4999)['remaining']);
        self::assertFalse($service->status($membership,5000)['active']);
        $membership->joinedAt=$now->modify('-7 days');
        self::assertFalse($service->status($membership,0)['active']);
        $membership->recoveryProtectedUntil=$now->modify('+2 hours');
        self::assertSame(7200,$service->status($membership,5000)['remaining']);
        self::assertSame('Protection après pertes',$service->status($membership,5000)['reason']);
        $membership->joinedAt=$now->modify('-1 day');
        self::assertSame(6*86400,$service->status($membership,0)['remaining']);
        $membership->recoveryProtectedUntil=$now;
        self::assertFalse($service->status($membership,5000)['active']);
    }
}
