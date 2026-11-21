<?php

declare(strict_types=1);

namespace App\Tests\Unit;

use App\Entity\QueueItem;
use App\Service\Player\FirstStepRewardPolicy;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class FirstStepRewardPolicyTest extends TestCase
{
    #[DataProvider('payments')]
    public function testUsesOnlyActualPaidCostsAtAllowedRankAndLevel(string $type, string $slug, string $rank, ?int $level, ?array $paid, array $expected): void
    {
        $order = new QueueItem();
        $order->queueType = $type;
        $order->itemSlug = $slug;
        $order->tier = $rank;
        $order->itemLevel = $level;
        $order->paidCosts = $paid;

        self::assertSame($expected, FirstStepRewardPolicy::paidRefund($order));
    }

    public static function payments(): iterable
    {
        yield 'division entière et aucune autre ressource' => ['building', 'mine_metal', 'basic', 1, ['metal' => 203, 'nano' => 87, 'antimatter' => 999], ['metal' => 50, 'nano' => 21]];
        yield 'plafonds mine' => ['building', 'mine_metal', 'basic', 1, ['metal' => 10000, 'nano' => 10000], ['metal' => 96, 'nano' => 52]];
        yield 'plafonds recherche' => ['research', 'engineering_foundations', 'basic', 1, ['metal' => 10000, 'nano' => 10000], ['metal' => 240, 'nano' => 130]];
        yield 'paiement absent' => ['building', 'mine_metal', 'basic', 1, null, ['metal' => 0, 'nano' => 0]];
        yield 'niveau supérieur' => ['building', 'mine_metal', 'basic', 2, ['metal' => 1000], ['metal' => 0, 'nano' => 0]];
        yield 'rang supérieur' => ['research', 'engineering_foundations', 'intermediate', 1, ['metal' => 1000], ['metal' => 0, 'nano' => 0]];
        yield 'autre recherche' => ['research', 'vector_propulsion', 'basic', 1, ['metal' => 1000], ['metal' => 0, 'nano' => 0]];
        yield 'pas de remboursement du dépôt' => ['building', 'storage_metal', 'basic', 1, ['metal' => 1000], ['metal' => 0, 'nano' => 0]];
    }
}
