<?php

declare(strict_types=1);

namespace App\Tests\Unit;

use App\Entity\GuildAtelierShipment;
use PHPUnit\Framework\TestCase;

final class GuildAtelierShipmentTest extends TestCase
{
    public function testOnlyTheCurrentDueLegMayBeSettled(): void
    {
        $shipment = new GuildAtelierShipment();
        $shipment->arrivalAt = new \DateTimeImmutable('2026-11-27 21:40:00 UTC');
        $shipment->arrivalToken = 'outbound-token';

        self::assertFalse($shipment->isDue('outbound-token', $shipment->arrivalAt->modify('-1 second')));
        self::assertFalse($shipment->isDue('', $shipment->arrivalAt));
        self::assertTrue($shipment->isDue('outbound-token', $shipment->arrivalAt));

        $shipment->status = 'returning';
        $shipment->arrivalToken = 'return-token';

        self::assertFalse($shipment->isDue('outbound-token', $shipment->arrivalAt));
        self::assertTrue($shipment->isDue('return-token', $shipment->arrivalAt));

        $shipment->status = 'returned';

        self::assertFalse($shipment->isDue('return-token', $shipment->arrivalAt));
    }
}
