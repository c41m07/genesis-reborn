<?php
namespace App\Tests\Service;

use App\Entity\Fleet;
use App\Repository\GameRepository;
use App\Service\Catalog\GameCatalog;
use App\Service\Fleet\AttackDetection;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class AttackDetectionTest extends TestCase
{
    #[DataProvider('windows')]
    public function testWindowKeepsBothCapsAndUsesWholeSeconds(int $level,int $duration,int $expected): void
    {
        self::assertSame($expected,AttackDetection::warningSeconds($level,$duration));
    }
    public static function windows(): array
    {
        return [[0,10800,300],[15,21600,4800],[23,21600,7200],[PHP_INT_MAX,21600,7200],
            [-1,10800,300],[5,1200,600],[15,61,30],[0,1,0],[15,0,0]];
    }
    public function testDetectionBoundaryBoostAndArrivalLag(): void
    {
        $detection=new AttackDetection(new GameRepository($this->createStub(EntityManagerInterface::class)),new GameCatalog(dirname(__DIR__,2).'/config/game'));
        $fleet=new Fleet();$fleet->mission='attack';$fleet->status='traveling';
        $fleet->departureAt=new \DateTimeImmutable('2026-11-12T12:00:00Z');$fleet->arrivalAt=$fleet->departureAt->modify('+2 hours');
        self::assertFalse($detection->detected($fleet,new \DateTimeImmutable('2026-11-12T13:54:59Z'),0));
        self::assertTrue($detection->detected($fleet,new \DateTimeImmutable('2026-11-12T13:55:00Z'),0));
        self::assertTrue($detection->detected($fleet,new \DateTimeImmutable('2026-11-12T14:00:10Z'),0));
        $now=new \DateTimeImmutable('2026-11-12T12:06:00Z');self::assertFalse($detection->detected($fleet,$now,0));
        $fleet->arrivalAt=$fleet->departureAt->modify('+10 minutes');self::assertTrue($detection->detected($fleet,$now,0));
        foreach(['returning','attacking','occupying'] as $status) { $fleet->status=$status;self::assertFalse($detection->detected($fleet,$now,15)); }
        $fleet->status='traveling';$fleet->mission='spy';self::assertFalse($detection->detected($fleet,$now,15));
        $fleet->mission='attack';$fleet->arrivalAt=null;self::assertFalse($detection->detected($fleet,$now,15));
    }
}
