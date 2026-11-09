<?php
namespace App\Tests\Support;

use App\Service\Catalog\GameCatalog;

/** Rejoue les anciennes études avec leurs paramètres, sans modifier les fixtures historiques. */
final class ProducerBalanceReference
{
    public static function fixture():array
    {
        return json_decode(file_get_contents(dirname(__DIR__).'/Fixtures/producer-balance-2026-11-09.json'),true,512,JSON_THROW_ON_ERROR);
    }

    public static function historicalCatalog():GameCatalog
    {
        $catalog=new GameCatalog(dirname(__DIR__,2).'/config/game');$fixture=self::fixture();
        $property=new \ReflectionProperty(GameCatalog::class,'data');$data=$property->getValue($catalog);
        foreach($fixture['historical_definitions'] as $slug=>$definition)$data['buildings'][$slug]=$definition;
        $data['rules']=$fixture['historical_rules'];$property->setValue($catalog,$data);
        return $catalog;
    }
}
