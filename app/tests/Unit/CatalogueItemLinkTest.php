<?php

declare(strict_types=1);

namespace App\Tests\Unit;

use App\Service\Colony\CatalogueItemLink;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Routing\{RequestContext, Route, RouteCollection};
use Symfony\Component\Routing\Generator\UrlGenerator;

final class CatalogueItemLinkTest extends TestCase
{
    public function testRoutesToTheNativeCatalogueSectionRankAndFocusWithLockedItemsRevealed(): void
    {
        $links = $this->links();
        foreach (['buildings' => ['lab', 'basic'], 'technologies' => ['weapons_engineering', 'intermediate'],
            'ships' => ['transport', 'superior'], 'defenses' => ['ground_turret_anti_light', 'basic']] as $group => [$slug, $tier]) {
            self::assertSame('/planetes/19?section='.$group.'&showLockedItems=1&focusItem=1#item-'.$slug.'-'.$tier,
                $links->focus(19, $group, $slug, $tier));
        }
    }

    public function testUnknownOrMalformedReferenceDoesNotProduceALink(): void
    {
        $links = $this->links();
        foreach ([[0, 'buildings', 'lab', 'basic'], [19, 'overview', 'lab', 'basic'],
            [19, 'ships', '../fighter', 'basic'], [19, 'defenses', 'turret', 'unknown'],
            [19, 'technologies', "bad\n", 'basic']] as [$planet, $group, $slug, $tier]) {
            self::assertNull($links->focus($planet, $group, $slug, $tier));
        }
    }

    private function links(): CatalogueItemLink
    {
        $routes = new RouteCollection();
        $routes->add('app_colony', new Route('/planetes/{id}'));

        return new CatalogueItemLink(new UrlGenerator($routes, new RequestContext()));
    }
}
