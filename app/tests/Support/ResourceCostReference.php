<?php
namespace App\Tests\Support;

use App\Service\Catalog\GameCatalog;

/** Restaure les seuls prix antérieurs pour les études historiques, sans réécrire leurs fixtures. */
final class ResourceCostReference
{
    public static function historicalCatalog(?GameCatalog $catalog = null): GameCatalog
    {
        $catalog ??= new GameCatalog(dirname(__DIR__, 2).'/config/game');
        $fixture = json_decode(file_get_contents(dirname(__DIR__).'/Fixtures/resource-costs-2026-11-16.json'), true, 512, JSON_THROW_ON_ERROR);
        $property = new \ReflectionProperty(GameCatalog::class, 'data');
        $data = $property->getValue($catalog);
        $data['rules']['progression_curves'] = ProducerBalanceReference::fixture()['historical_rules']['progression_curves'];
        unset($data['rules']['building_level_growth']);
        foreach ($fixture['entries'] as $group => $entries) {
            foreach ($entries as $slug => $entry) {
                if (isset($data[$group][$slug])) {
                    $data[$group][$slug]['cost_profiles'] = $entry['old_profiles'];
                }
            }
        }
        $property->setValue($catalog, $data);
        return $catalog;
    }
}
