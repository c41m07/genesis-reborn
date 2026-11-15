<?php
namespace App\Service\Fleet;

use App\Entity\Fleet;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/** Conserve le barème d'une mission lorsqu'un rappel ou un repli crée un nouveau tronçon. */
final class FleetFuel
{
    private const FIELDS = ['fuel_travel_system', 'fuel_travel_galaxy', 'fuel_orbit'];
    private array $legacyProfiles;

    public function __construct(
        #[Autowire('%kernel.project_dir%/config/legacy/progression-operations-2026-11-15.json')] string $referencePath,
    ) {
        $reference = json_decode(file_get_contents($referencePath), true, 512, JSON_THROW_ON_ERROR);
        $this->legacyProfiles = $reference['fuel_profiles'];
    }

    /** @return array<string, array<string, float>> Coefficients réellement employés au départ. */
    public static function snapshot(array $ships): array
    {
        $profiles = [];
        foreach ($ships as $ship) {
            $profiles[self::key($ship)] = self::profile($ship);
        }
        return $profiles;
    }

    public function forFleet(Fleet $fleet, array $ships): array
    {
        $navigation = $fleet->navigation ?? [];
        $hasSnapshot = array_key_exists('fuel_profiles', $navigation);
        if ($hasSnapshot && !is_array($navigation['fuel_profiles'])) {
            throw self::unavailable();
        }
        foreach ($ships as &$ship) {
            if ($hasSnapshot) {
                $profile = $navigation['fuel_profiles'][self::key($ship)] ?? null;
            } else {
                // Les anciennes missions n'ont pas de profil unitaire figé : reprendre
                // leur catalogue de référence, avec les personnalisations d'univers conservées.
                // Une ancienne surcharge modifiée depuis le départ nécessite un diagnostic opérateur.
                $profile = $this->legacyProfiles[$ship['slug']] ?? null;
                $override = $fleet->playerUniverse->universe->gameSettings['catalog_overrides']['ships'][$ship['slug']] ?? [];
                if (is_array($profile)) {
                    foreach (self::FIELDS as $field) {
                        if (array_key_exists($field, $override)) {
                            $profile[$field] = $override[$field];
                        }
                    }
                }
            }
            if (!is_array($profile)) {
                throw self::unavailable();
            }
            // Ne modifier ni les quantités ni les vitesses : leurs règles restent celles du trajet existant.
            $ship = array_replace($ship, self::profile($profile));
        }
        unset($ship);
        return $ships;
    }

    private static function key(array $ship): string
    {
        return $ship['slug'].':'.($ship['tier'] ?? $ship['_tier'] ?? 'basic');
    }

    /** @return array<string, float> */
    private static function profile(array $source): array
    {
        $profile = [];
        foreach (self::FIELDS as $field) {
            $value = $source[$field] ?? null;
            if (!is_numeric($value) || !is_finite((float) $value) || $value < 0) {
                throw self::unavailable();
            }
            $profile[$field] = (float) $value;
        }
        return $profile;
    }

    private static function unavailable(): \DomainException
    {
        return new \DomainException('Les données de carburant de cette flotte ne permettent pas de préparer un trajet fiable.');
    }
}
