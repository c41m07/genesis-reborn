<?php

declare(strict_types=1);

namespace App\Tests\Unit;

use App\Service\Guild\GuildHallFunding;
use App\Service\Player\GuildProjectRules;
use PHPUnit\Framework\TestCase;

final class GuildHallFundingTest extends TestCase
{
    public function testAbsentAndInvalidThresholdsRemainClosed(): void
    {
        $rules = new GuildProjectRules();
        foreach ([0, -1, GuildProjectRules::MAX_STAGE_AMOUNT + 1] as $unapproved) {
            self::assertNull((new GuildHallFunding($rules, $unapproved))->target());
        }
        self::assertSame(32, (new GuildHallFunding($rules, 32))->target());
    }

    public function testTheFourNominalEquivalentsProduceIdenticalStrength(): void
    {
        $funding = new GuildHallFunding(new GuildProjectRules(), 32);
        foreach (['hydrogen' => 8, 'metal' => 4, 'nano' => 2, 'antimatter' => 1] as $resource => $amount) {
            self::assertSame(['cost' => [$resource => $amount], 'strength' => 8], $funding->donation([$resource => $amount], 8));
        }
        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('Ressource inconnue');
        $funding->donation(['electricity' => 1], 32);
    }
}
