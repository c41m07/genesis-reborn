<?php

declare(strict_types=1);

namespace App\Tests\Unit;

use App\Service\Colony\ResourceWaitEstimate;
use PHPUnit\Framework\TestCase;

final class ResourceWaitEstimateTest extends TestCase
{
    public function testUsesSlowestResourceAndItsUnroundedDeficit(): void
    {
        $estimate = ResourceWaitEstimate::forCosts(['metal' => 20, 'nano' => 15, 'hydrogen' => 3], [
            'metal' => $this->resource('Métal', 19.75, 20, 0.0625),
            'nano' => $this->resource('Nano', 13.75, 30, 0.125),
            'hydrogen' => $this->resource('Hydrogène', 3, 3, -2),
        ]);

        self::assertSame(['metal' => ['label' => 'Métal', 'amount' => 1], 'nano' => ['label' => 'Nano', 'amount' => 2]], $estimate['missing']);
        self::assertSame('Délai estimé : 10 s (Nano).', $estimate['paymentWait']);
    }

    public function testExplainsEveryBlockingResourceWithoutOfferingATime(): void
    {
        $estimate = ResourceWaitEstimate::forCosts(['metal' => 21, 'nano' => 15, 'hydrogen' => 4], [
            'metal' => $this->resource('Métal', 18, 20, 0),
            'nano' => $this->resource('Nano', 12, 30, -0.25),
            'hydrogen' => $this->resource('Hydrogène', 3, 30, 0),
        ]);

        self::assertSame('Délai indisponible : Métal : stockage insuffisant ; Nano : aucun gain net ; Hydrogène : aucun gain net.', $estimate['paymentWait']);
    }

    public function testCapacityEqualityAndFractionalSecondsRoundUp(): void
    {
        self::assertSame('Délai estimé : 1 min 1 s (Métal).', ResourceWaitEstimate::forCosts(['metal' => 20], [
            'metal' => $this->resource('Métal', 4.875, 20, 0.25),
        ])['paymentWait']);
    }

    public function testAlreadyAffordableDoesNotNeedAnEstimateEvenAboveCapacity(): void
    {
        self::assertSame(['missing' => [], 'paymentWait' => null], ResourceWaitEstimate::forCosts(['metal' => 25], [
            'metal' => $this->resource('Métal', 25, 20, 0),
        ]));
    }

    public function testUnrepresentableDelayDoesNotConvertToANegativeDuration(): void
    {
        self::assertSame('Délai indisponible : Métal : délai non calculable.', ResourceWaitEstimate::forCosts(['metal' => 2], [
            'metal' => $this->resource('Métal', 0, 20, PHP_FLOAT_MIN),
        ])['paymentWait']);
    }

    private function resource(string $label, float $stock, float $capacity, float $rate): array
    {
        return compact('label', 'stock', 'capacity', 'rate');
    }
}
