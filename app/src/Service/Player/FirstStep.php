<?php

declare(strict_types=1);

namespace App\Service\Player;

use App\Entity\QueueItem;

enum FirstStep: string
{
    case MetalMine = 'metal_mine';
    case PowerPlant = 'power_plant';
    case NanoWorkshop = 'nano_workshop';
    case Research = 'research';

    /** Ce filtre est réservé au chemin où la file a effectivement appliqué le résultat. */
    public static function forCompletedQueue(QueueItem $item): ?self
    {
        if (($item->itemLevel ?? 0) < 1) {
            return null;
        }
        if ($item->queueType === 'research') {
            return self::Research;
        }
        if ($item->queueType !== 'building' || $item->tier !== 'basic') {
            return null;
        }

        return match ($item->itemSlug) {
            'mine_metal' => self::MetalMine,
            'power_plant' => self::PowerPlant,
            'mine_nano' => self::NanoWorkshop,
            default => null,
        };
    }
}
