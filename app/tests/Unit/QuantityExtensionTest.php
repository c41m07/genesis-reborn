<?php
declare(strict_types=1);

namespace App\Tests\Unit;

use App\Twig\QuantityExtension;
use PHPUnit\Framework\TestCase;
use Twig\Environment;
use Twig\Loader\ArrayLoader;

final class QuantityExtensionTest extends TestCase
{
    public function testCompactThresholdsAndFrenchUnits(): void
    {
        $formatter = new QuantityExtension();
        $cases = json_decode(file_get_contents(dirname(__DIR__) . '/Fixtures/quantity-format.json'), true, flags: JSON_THROW_ON_ERROR);
        foreach ($cases as [$value, $expected]) {
            self::assertSame($expected, $formatter->compact($value), (string) $value);
        }
        self::assertSame('0', $formatter->compact(null));
        self::assertSame('0,00', $formatter->render(null, 2));
        self::assertSame('12,50', $formatter->compact(12.5, 2));
        self::assertSame('12,5', $formatter->compact(12.5, 2, true));
    }

    public function testExactValuesAndMarkupSurviveQueueRendering(): void
    {
        $twig = new Environment(new ArrayLoader([
            'value' => "{{ value|quantity }}",
            'queue' => "{% if level %}Niveau {{ level }}{% else %}× {{ value|quantity }}{% endif %}",
        ]), ['autoescape' => 'html']);
        $twig->addExtension(new QuantityExtension());
        self::assertSame('9 999', $twig->render('value', ['value' => 9999]));
        self::assertStringContainsString('title="12 345"', $twig->render('value', ['value' => 12345]));
        self::assertStringContainsString('<abbr', $twig->render('queue', ['level' => 0, 'value' => 12345]));
        self::assertSame('Niveau 10000', $twig->render('queue', ['level' => 10000, 'value' => 12345]));
    }
}
