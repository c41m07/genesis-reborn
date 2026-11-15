<?php
namespace App\Tests\Support;

use App\Service\Catalog\GameCatalog;

/** Rejoue les études de coûts et de combat avec leurs durées et carburants historiques. */
final class ProgressionOperationsReference
{
    public static function historicalCatalog(?GameCatalog $catalog = null): GameCatalog
    {
        $catalog ??= new GameCatalog(dirname(__DIR__, 2).'/config/game');
        $reference = json_decode(file_get_contents(dirname(__DIR__, 2).'/config/legacy/progression-operations-2026-11-15.json'), true, 512, JSON_THROW_ON_ERROR);
        $property = new \ReflectionProperty(GameCatalog::class, 'data');
        $data = $property->getValue($catalog);
        foreach ($reference['durations'] as $group => $entries) {
            $field = match ($group) {
                'buildings' => 'base_build_time',
                'technologies' => 'research_time_base',
                default => 'build_time_base',
            };
            foreach ($entries as $slug => $seconds) {
                $data[$group][$slug][$field] = $seconds;
            }
        }
        foreach ($reference['fuel_profiles'] as $slug => $profile) {
            $data['ships'][$slug] = array_replace($data['ships'][$slug], $profile);
        }
        $property->setValue($catalog, $data);
        return $catalog;
    }
}
