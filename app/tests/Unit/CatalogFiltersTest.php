<?php
namespace App\Tests\Unit;

use App\Service\Catalog\GameCatalog;
use App\Service\Colony\CatalogFilters;
use PHPUnit\Framework\TestCase;

final class CatalogFiltersTest extends TestCase
{
    public function testCombatShipsWithCargoRemainInTheirCombatClass(): void
    {
        $catalog=new GameCatalog(dirname(__DIR__,2).'/config/game');$filters=new CatalogFilters();
        foreach (['fighter'=>'light','corvette'=>'escort','bomber'=>'bomber','cruiser'=>'heavy','capital'=>'capital'] as $slug=>$expected) {
            $ship=$catalog->all('ships')[$slug];self::assertGreaterThan(0,$ship['cargo_capacity']);self::assertSame($expected,$filters->category('ships',$ship));
        }
        foreach (['transport'=>'transport','colony_ship'=>'colony','probe'=>'espionage'] as $slug=>$expected) self::assertSame($expected,$filters->category('ships',$catalog->all('ships')[$slug]));
    }

    public function testResearchUsesCurrentEffectsInsteadOfOldCombatList(): void
    {
        $catalog=new GameCatalog(dirname(__DIR__,2).'/config/game');$filters=new CatalogFilters();
        foreach (['cryogenic_reserves'=>'logistics','modular_cargo_bays'=>'logistics','vector_propulsion'=>'logistics','colonial_expansion'=>'expansion',
            'automated_defense_repair'=>'defense','naval_engineering'=>'economy','weapons_engineering'=>'combat','signature_masking'=>'espionage'] as $slug=>$expected) {
            self::assertSame($expected,$filters->category('technologies',$catalog->all('technologies')[$slug]),$slug);
        }
    }

    public function testEveryFamilyHasAnAvailableFilterAndDefensesHaveDistinctTargets(): void
    {
        $catalog=new GameCatalog(dirname(__DIR__,2).'/config/game');$filters=new CatalogFilters();
        foreach (['buildings','technologies','ships','defenses'] as $group) {
            $definitions=$catalog->all($group);$options=$filters->options($group,$definitions);
            foreach ($definitions as $definition) self::assertArrayHasKey($filters->category($group,$definition),$options,$definition['slug']);
        }
        self::assertSame(['all','light','escort','heavy','capital'],array_keys($filters->options('defenses',$catalog->all('defenses'))));
    }
}
