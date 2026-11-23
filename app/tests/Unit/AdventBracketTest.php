<?php

declare(strict_types=1);

namespace App\Tests\Unit;

use App\Entity\Universe;
use App\Service\Advent\AdventBracket;
use App\Service\Catalog\GameCatalog;
use App\Service\Requirement\{CatalogAccess, RequirementChecker};
use PHPUnit\Framework\TestCase;

final class AdventBracketTest extends TestCase
{
    public function testOnlyCombatAndOwnedColoniesCanRaiseTheBracket(): void
    {
        $universe = new Universe();
        $universe->slug = 'omega-1';
        $base = new GameCatalog(dirname(__DIR__, 2).'/config/game');
        $catalog = $base->forUniverse($universe)['catalog'];
        $rule = new AdventBracket(new RequirementChecker($base));
        $keys = [
            '_requirements_version' => CatalogAccess::VERSION,
            'v2:planet:6:ships:cruiser:superior' => true,
            'v2:planet:7:ships:transport:superior' => true,
            'v2:planet:8:ships:probe:superior' => true,
            'v2:planet:9:ships:fighter:superior' => true,
            'v2:planet:10:ships:capital:intermediate' => true,
        ];

        self::assertSame('beginner', $rule->classify([7 => [], 8 => []], [], $keys, $catalog));
        self::assertSame('intermediate', $rule->classify([9 => [], 10 => []], [], $keys, $catalog));
        self::assertSame('veteran', $rule->classify([6 => [], 7 => [], 9 => []], [], $keys, $catalog));
        self::assertNull($rule->classify([6 => []], [], ['_requirements_version' => 1] + $keys, $catalog));
    }
}
