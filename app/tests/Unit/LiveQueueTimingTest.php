<?php

declare(strict_types=1);

namespace App\Tests\Unit;

use App\Entity\{QueueItem, Universe};
use App\Service\Catalog\GameCatalog;
use App\Service\Queue\{LiveQueueTiming, OperationCalculator};
use PHPUnit\Framework\TestCase;

final class LiveQueueTimingTest extends TestCase
{
    public function testRetimeKeepsTheWorkAndBoostAffectsOnlyWhatRemains(): void
    {
        $item = new QueueItem();
        $item->startedAt = new \DateTimeImmutable('2026-11-24T12:00:00Z');
        $start = $item->startedAt->getTimestamp();
        $item->durationSeconds = 100;
        $item->liveTiming = ['base' => 100.0, 'cap' => 70.0, 'max' => 25, 'checkpoint' => $start, 'work' => 1.0, 'full' => 100];
        LiveQueueTiming::checkpoint($item, $start + 40, 50);
        self::assertEqualsWithDelta(40.0, LiveQueueTiming::progress($item)['percent'], 0.000001);
        self::assertSame($start + 70, $item->finishAt()->getTimestamp());

        // 40 % restent à t+50 ; le boost en supprime le quart, pas le quart de la durée d’achat.
        self::assertSame(15, LiveQueueTiming::boostedRemainingSeconds($item, $start + 50, 4 / 3));
        LiveQueueTiming::checkpoint($item, $start + 50, 50, 4 / 3);
        self::assertEqualsWithDelta(0.3, $item->liveTiming['work'], 0.000001);
        self::assertSame($start + 65, $item->finishAt()->getTimestamp());
        LiveQueueTiming::checkpoint($item, $start + 55, 20);
        self::assertSame($start + 59, $item->finishAt()->getTimestamp());
    }

    public function testQuoteAndLiveRateAgreeAndDefensesHaveNoPassiveModifier(): void
    {
        $catalog = new GameCatalog(dirname(__DIR__, 2) . '/config/game');
        $calc = new OperationCalculator($catalog);
        $universe = new Universe();
        $universe->slug = 'live-queue-x1';
        $universe->name = 'Rythmes';
        $config = $catalog->forUniverse($universe);
        foreach (['buildings' => 'mine_metal', 'technologies' => 'engineering_foundations',
            'ships' => 'fighter', 'defenses' => 'ground_turret_anti_light'] as $group => $slug) {
            $levels = ['basic' => 8, 'intermediate' => 5, 'superior' => 3];
            $definition = $catalog->getTierForUniverse($universe, $group, $slug, 'basic');
            $plan = $calc->calculateQueuePlan($definition, $group, 2, 3, ['lab' => $levels, 'shipyard' => $levels],
                $config, ['engineering_foundations' => $levels]);
            if ($group === 'defenses') {
                self::assertNull($plan['live_timing']);
            } else {
                self::assertSame($plan['duration'], LiveQueueTiming::fullDuration($plan['live_timing'], $levels));
            }
        }
    }
}
