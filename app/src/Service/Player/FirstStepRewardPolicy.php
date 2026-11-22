<?php

declare(strict_types=1);

namespace App\Service\Player;

use App\Entity\Planet;
use App\Entity\QueueItem;

final class FirstStepRewardPolicy
{
    public static function queueStep(string $type, string $slug, string $tier, ?int $level): ?FirstStep
    {
        if ($tier !== 'basic' || $level !== 1) {
            return null;
        }

        return match ([$type, $slug]) {
            ['building', 'mine_metal'] => FirstStep::MetalMine,
            ['research', 'engineering_foundations'] => FirstStep::Research,
            default => null,
        };
    }

    /** @return array{metal: int, nano: int} */
    public static function paidRefund(QueueItem $item): array
    {
        $step = self::queueStep($item->queueType, $item->itemSlug, $item->tier, $item->itemLevel);
        $caps = match ($step) {
            FirstStep::MetalMine => ['metal' => 96, 'nano' => 52],
            FirstStep::Research => ['metal' => 240, 'nano' => 130],
            default => ['metal' => 0, 'nano' => 0],
        };
        $refund = [];
        foreach ($caps as $resource => $cap) {
            $paid = $item->paidCosts[$resource] ?? 0;
            // Jamais de recalcul au prix du catalogue : le paiement conservé fait seule foi.
            $refund[$resource] = is_numeric($paid) && is_finite((float) $paid)
                ? (int) min($cap, floor(max(0.0, (float) $paid) / 4))
                : 0;
        }

        return $refund;
    }

    public static function isNativeNpc(?Planet $planet): bool
    {
        return $planet !== null && $planet->npcPlanet && $planet->owner === null;
    }
}
