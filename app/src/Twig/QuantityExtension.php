<?php
declare(strict_types=1);

namespace App\Twig;

use Twig\Extension\AbstractExtension;
use Twig\TwigFilter;

final class QuantityExtension extends AbstractExtension
{
    private const UNITS = [1 => '', 1000 => 'k', 1000000 => 'M', 1000000000 => 'Md', 1000000000000 => 'Bn'];

    public function getFilters(): array
    {
        return [
            new TwigFilter('compact_number', $this->compact(...)),
            new TwigFilter('quantity', $this->render(...), ['is_safe' => ['html']]),
        ];
    }

    public function compact(int|float|null $value, int $decimals = 0, bool $trim = false): string
    {
        $value ??= 0;
        if (abs($value) < 10000) {
            $label = number_format($value, $decimals, ',', ' ');
            return $trim && $decimals > 0 ? rtrim(rtrim($label, '0'), ',') : $label;
        }

        $divisor = 1000;
        foreach (self::UNITS as $candidate => $suffix) {
            if ($candidate > 1 && round(abs($value) / $divisor, 2) >= 1000) {
                $divisor = $candidate;
            }
        }

        return number_format($value / $divisor, 2, ',', ' ') . ' ' . self::UNITS[$divisor];
    }

    public function render(int|float|null $value, int $decimals = 0, bool $trim = false): string
    {
        $value ??= 0;
        $label = $this->compact($value, $decimals, $trim);
        if (abs($value) < 10000) {
            return $label;
        }

        $exact = number_format($value, max($decimals, floor($value) == $value ? 0 : 2), ',', ' ');
        return '<abbr class="quantity-number" title="' . $exact . '" tabindex="0">' . $label . '</abbr>';
    }
}
