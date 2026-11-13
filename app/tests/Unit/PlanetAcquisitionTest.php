<?php
namespace App\Tests\Unit;
use App\Entity\{Planet,PlayerUniverse};
use PHPUnit\Framework\TestCase;

final class PlanetAcquisitionTest extends TestCase
{
    public function testTransferRecordsRealAcquisitionAndReplayKeepsItWithoutDatingLegacyOwnership(): void
    {
        $former=new PlayerUniverse();$former->id=1;$owner=new PlayerUniverse();$owner->id=2;
        $planet=new Planet();$planet->owner=$former;
        $at=new \DateTimeImmutable('2026-11-12T14:00:00+02:00');
        $planet->acquireBy($former,$at);self::assertNull($planet->acquiredAt);
        $planet->acquireBy($owner,$at);
        self::assertSame($owner,$planet->owner);self::assertSame('2026-11-12T12:00:00+00:00',$planet->acquiredAt->format(DATE_ATOM));
        $planet->acquireBy($owner,$at->modify('+1 hour'));
        self::assertSame('2026-11-12T12:00:00+00:00',$planet->acquiredAt->format(DATE_ATOM));
    }
}
