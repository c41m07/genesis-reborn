<?php

declare(strict_types=1);

namespace App\Service\Player;

use App\Entity\QueueItem;

enum FirstStep: string
{
    case MetalMine = 'metal_mine';
    case PowerPlant = 'power_plant';
    case NanoWorkshop = 'nano_workshop';
    case MetalStorage = 'metal_storage';
    case Laboratory = 'laboratory';
    case Research = 'research';
    case AssemblyYard = 'assembly_yard';
    case Ship = 'ship';
    case Exploration = 'exploration';
    case Espionage = 'espionage';
    case PirateExercise = 'pirate_exercise';
    case Colonization = 'colonization';

    public function buildingSlug(): ?string
    {
        return match ($this) {
            self::MetalMine => 'mine_metal',
            self::PowerPlant => 'power_plant',
            self::NanoWorkshop => 'mine_nano',
            self::MetalStorage => 'storage_metal',
            self::Laboratory => 'lab',
            self::AssemblyYard => 'shipyard',
            default => null,
        };
    }

    /** Ce filtre est réservé au chemin où la file a effectivement appliqué le résultat. */
    public static function forCompletedQueue(QueueItem $item, bool $shipConstructed = false): ?self
    {
        if ($item->queueType === 'shipyard') {
            // Le chantier traite aussi des défenses : seule la branche qui a crédité FleetShip fait preuve.
            return $shipConstructed && $item->itemSlug === 'probe' && $item->tier === 'basic' && $item->quantity > 0 ? self::Ship : null;
        }
        if (($item->itemLevel ?? 0) < 1 || $item->tier !== 'basic') {
            return null;
        }
        if ($item->queueType === 'research') {
            return self::Research;
        }
        if ($item->queueType !== 'building') {
            return null;
        }

        foreach (self::cases() as $step) {
            if ($step->buildingSlug() === $item->itemSlug) {
                return $step;
            }
        }

        return null;
    }
}
