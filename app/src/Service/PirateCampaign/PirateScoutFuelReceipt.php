<?php

declare(strict_types=1);

namespace App\Service\PirateCampaign;

use App\Service\Catalog\GameCatalog;
use Symfony\Component\DependencyInjection\Attribute\Exclude;

/** Preuve privée du prélèvement natif AR ; elle ne crée jamais un crédit de retour. */
#[Exclude]
final readonly class PirateScoutFuelReceipt
{
    private function __construct(
        public int $originId,
        public int $seconds,
        public float $reserved,
        public float $fromHold,
        public float $fromPlanet,
        public string $tier,
    ) {
    }

    public static function forDeparture(int $originId, PirateCampaignFuel $fuel, float $fromHold, float $fromPlanet, string $tier = 'basic'): self
    {
        return self::read([
            'version' => 1, 'origin' => $originId, 'tier' => $tier, 'seconds' => $fuel->legSeconds,
            'reserved' => $fuel->scoutsForfeitedHydrogen(), 'hold' => $fromHold, 'planet' => $fromPlanet,
        ]);
    }

    public static function read(array $record): self
    {
        $reserved = self::amount($record['reserved'] ?? null);
        $hold = self::amount($record['hold'] ?? null);
        $planet = self::amount($record['planet'] ?? null);
        // Les reçus antérieurs à la sélection par rang concernaient exclusivement des sondes T1.
        $tier = array_key_exists('tier', $record) ? $record['tier'] : 'basic';
        if (!in_array($tier, GameCatalog::TIERS, true) || ($record['version'] ?? null) !== 1 || !is_int($record['origin'] ?? null) || $record['origin'] < 1
            || !is_int($record['seconds'] ?? null) || $record['seconds'] < 30 || $record['seconds'] > 2147483647
            || $reserved === null || $hold === null || $planet === null
            || $hold > $reserved || $planet !== $reserved - $hold) {
            throw new \DomainException('Le prélèvement de la sonde pilote n’est pas attesté.');
        }

        return new self($record['origin'], $record['seconds'], $reserved, $hold, $planet, $tier);
    }

    public function checkpoint(): array
    {
        return ['version' => 1, 'origin' => $this->originId, 'tier' => $this->tier, 'seconds' => $this->seconds,
            'reserved' => $this->reserved, 'hold' => $this->fromHold, 'planet' => $this->fromPlanet];
    }

    private static function amount(mixed $value): ?float
    {
        return (is_int($value) || is_float($value)) && is_finite($value) && $value >= 0 ? (float) $value : null;
    }
}
