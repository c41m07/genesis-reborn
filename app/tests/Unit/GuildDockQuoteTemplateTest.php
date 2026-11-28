<?php

declare(strict_types=1);

namespace App\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Twig\{Environment, TwigFunction};
use Twig\Loader\FilesystemLoader;

final class GuildDockQuoteTemplateTest extends TestCase
{
    public function testComparisonShowsBothStepsWithoutAddingThemToTheConfirmationCharge(): void
    {
        $twig = new Environment(new FilesystemLoader(dirname(__DIR__, 2).'/templates'), ['strict_variables' => true]);
        $twig->addFunction(new TwigFunction('path', static fn (string $route, array $parameters = []): string => '/'.$route));
        $twig->addFunction(new TwigFunction('csrf_token', static fn (string $scope): string => 'token'));
        $html = $twig->render('player/_guild_dock.html.twig', [
            'guild' => ['id' => 2], 'hall' => ['id' => 4],
            'dock' => [
                'origin' => ['id' => 7, 'name' => 'Orion'], 'choices' => [],
                'review' => ['key' => 'fixture', 'quote' => [
                    'ship' => 'fighter', 'source_tier' => 'basic', 'target_tier' => 'intermediate', 'quantity' => 1,
                    'source_costs' => ['hydrogen' => 7, 'antimatter' => 12, 'electricity' => 100],
                    'target_costs' => ['hydrogen' => 10, 'antimatter' => 22, 'electricity' => 200],
                    'costs' => ['hydrogen' => 5, 'antimatter' => 9, 'electricity' => 0],
                    'source_duration' => 2700, 'target_duration' => 3600, 'duration' => 1320,
                ]],
            ],
        ]);

        self::assertMatchesRegularExpression(
            '~Construire au rang de départ puis moderniser</dt>\s*<dd>1 h 7 min 0 s\s*<ul><li>Hydrogène : 12</li><li>Antimatière : 21</li><li>Électricité : 100</li>~u',
            $html,
        );
        self::assertMatchesRegularExpression(
            '~Moderniser le lot existant</dt>\s*<dd>0 h 22 min 0 s\s*<ul><li>Hydrogène : 5</li><li>Antimatière : 9</li></ul>~u',
            $html,
        );
        self::assertStringContainsString('Confirmer et moderniser', $html);
    }
}
