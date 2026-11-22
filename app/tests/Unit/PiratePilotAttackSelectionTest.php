<?php

declare(strict_types=1);

namespace App\Tests\Unit;

use App\Service\PirateCampaign\{PiratePilotAttackSelection, PiratePilotCommandOptions};
use PHPUnit\Framework\TestCase;

final class PiratePilotAttackSelectionTest extends TestCase
{
    public function testSameCostDoesNotAuthorizeAnotherComposition(): void
    {
        $options = PiratePilotCommandOptions::escort(['fighter:basic' => 3, 'interceptor:basic' => 3], 4);
        $fighter = PiratePilotAttackSelection::fromSubmission(['escort' => [1 => '2']], $options);
        $interceptors = PiratePilotAttackSelection::fromSubmission(['escort' => [2 => '2']], $options);
        self::assertSame(['fighter' => ['basic' => 2]], $fighter->composition);
        self::assertSame(['interceptor' => ['basic' => 2]], $interceptors->composition);
        $quote = $this->quote();
        $world = hash('sha256', 'scope-and-same-attested-report');
        self::assertNotSame($fighter->confirmation(17, 4, 9, 1, 0, $world, $quote)->csrfId(),
            $interceptors->confirmation(17, 4, 9, 1, 0, $world, $quote)->csrfId());
    }

    public function testOrderingOfFieldsDoesNotInvalidateTheSameSelection(): void
    {
        $options = PiratePilotCommandOptions::escort(['fighter:basic' => 3, 'fighter:intermediate' => 3], 4);
        $a = PiratePilotAttackSelection::fromSubmission(['escort' => [1 => '1', 2 => '2']], $options);
        $b = PiratePilotAttackSelection::fromSubmission(['escort' => [2 => '2', 1 => '1']], $options);
        $quote = $this->quote();
        $report = hash('sha256', 'same-world-proof');
        self::assertSame($a->confirmation(17, 4, 9, 1, 0, $report, $quote)->csrfId(),
            $b->confirmation(17, 4, 9, 1, 0, $report, array_reverse($quote, true))->csrfId());
        self::assertNotSame($a->confirmation(17, 4, 9, 1, 0, $report, $quote)->csrfId(),
            $b->confirmation(17, 4, 9, 1, 1, $report, $quote)->csrfId());
        self::assertNotSame($a->confirmation(17, 4, 9, 1, 0, $report, $quote)->csrfId(),
            $b->confirmation(17, 4, 9, 1, 0, hash('sha256', 'other-proof'), $quote)->csrfId());
        self::assertNotSame($a->confirmation(17, 4, 9, 1, 0, $report, $quote)->csrfId(),
            $b->confirmation(17, 4, 9, 1, 0, $report, array_replace($quote, ['seconds' => 95]))->csrfId());
    }

    public function testCannotSealAnInconsistentGroundDebit(): void
    {
        $options = PiratePilotCommandOptions::escort(['fighter:basic' => 3], 4);
        $input = PiratePilotAttackSelection::fromSubmission(['escort' => [1 => '2']], $options);
        $this->expectException(\InvalidArgumentException::class);
        $input->confirmation(17, 4, 9, 1, 0, hash('sha256', 'report'), array_replace($this->quote(), ['planet' => 0]));
    }

    private function quote(): array
    {
        return ['seconds' => 94, 'hydrogen' => 7.0, 'orbit' => 1.0, 'hold' => 1.5, 'planet' => 5.5];
    }
}
