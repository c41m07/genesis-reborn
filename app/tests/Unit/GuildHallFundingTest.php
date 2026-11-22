<?php

declare(strict_types=1);

namespace App\Tests\Unit;

use App\Service\Catalog\GameCatalog;
use App\Service\Guild\GuildHallFunding;
use App\Service\Player\GuildProjectRules;
use PHPUnit\Framework\TestCase;

final class GuildHallFundingTest extends TestCase
{
    public function testAbsentAndInvalidThresholdsRemainClosed(): void
    {
        foreach ([0, -1, GuildProjectRules::MAX_STAGE_AMOUNT + 1, 149999] as $unapproved) {
            self::assertNull($this->funding($unapproved)->target());
        }
        self::assertNull($this->funding(500000, false)->target());
        self::assertSame(500000, $this->funding(500000)->target());
    }

    public function testTheFourNominalEquivalentsProduceIdenticalStrength(): void
    {
        $funding = $this->funding(500000);
        foreach (['hydrogen' => 8, 'metal' => 4, 'nano' => 2, 'antimatter' => 1] as $resource => $amount) {
            self::assertSame(['cost' => [$resource => $amount], 'strength' => 8], $funding->donation([$resource => $amount], 8));
        }
        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('Ressource inconnue');
        $funding->donation(['electricity' => 1], 32);
    }

    public function testTheReservationKeepsPlacesForThreeDistinctDonorsWithoutOverpaying(): void
    {
        $funding = $this->funding(500000);
        self::assertSame(400000, $funding->maximumDonation(500000, 0, [], 3, 50000));
        self::assertSame(0, $funding->maximumDonation(100000, 400000, [], 3, 50000));
        self::assertSame(50000, $funding->maximumDonation(100000, 0, [400000], 3, 50000));
        self::assertSame(50000, $funding->maximumDonation(50001, 0, [400000, 49999], 3, 50000));
        self::assertSame(0, $funding->maximumDonation(1, 0, [400000, 50000, 49999], 3, 50000));
        self::assertSame(1, $funding->maximumDonation(1, 49999, [400000, 50000], 3, 50000));
        self::assertSame(0, $funding->maximumDonation(0, 50000, [400000, 50000], 3, 50000));
        self::assertSame(0, $funding->fields(1)['antimatter']['maximum']);
        self::assertSame(1, $funding->fields(1)['hydrogen']['maximum']);
    }

    private function funding(int $target, bool $enabled = true): GuildHallFunding
    {
        return new GuildHallFunding(new GuildProjectRules(), new GameCatalog(dirname(__DIR__, 2).'/config/game'), $target, 3, 50000, $enabled);
    }
}
