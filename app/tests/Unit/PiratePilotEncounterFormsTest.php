<?php

declare(strict_types=1);

namespace App\Tests\Unit;

use App\Service\PirateCampaign\{PiratePilotCommandAction, PiratePilotEncounterForms};
use PHPUnit\Framework\TestCase;

final class PiratePilotEncounterFormsTest extends TestCase
{
    public function testOnlyNativeDisplayFieldsAndCanonicalOrdinalsReachConfirmation(): void
    {
        $quote = ['seconds' => 60, 'hydrogen' => 3.5, 'orbit' => 0.5, 'hold' => 1, 'planet' => 2.5,
            'private_world' => 'opaque', 'site' => [1, 2, 3, 4], 'formation' => ['pirate' => 8]];
        $runtimeToken = bin2hex(random_bytes(16));
        $view = PiratePilotEncounterForms::confirmationCard(PiratePilotCommandAction::Attack, $quote, $runtimeToken, [3 => '7', 1 => '2']);
        self::assertSame(['seconds' => 60, 'hydrogen' => 3.5, 'hold' => 1.0, 'planet' => 2.5, 'orbit' => 0.5], $view['quote']);
        self::assertSame([1 => '2', 3 => '7'], $view['hidden_escort']);
        self::assertSame($runtimeToken, $view['token']);
        self::assertSame('attack', $view['action']);
        self::assertArrayNotHasKey('intent', $view);
    }

    public function testScoutDoesNotRenderAnyCombatOrChoiceField(): void
    {
        $quote = ['seconds' => 30, 'hydrogen' => 2, 'hold' => 0, 'planet' => 2, 'orbit' => 8];
        $view = PiratePilotEncounterForms::confirmationCard(PiratePilotCommandAction::Reconnaissance, $quote, bin2hex(random_bytes(16)));
        self::assertSame([], $view['hidden_escort']);
        self::assertArrayNotHasKey('orbit', $view['quote']);
        self::assertSame('reconnaissance', $view['action']);
    }

    public function testReadonlyAdaptationRefusesAClientCatalogueKey(): void
    {
        $this->expectException(\LogicException::class);
        PiratePilotEncounterForms::confirmationCard(PiratePilotCommandAction::Attack,
            ['seconds' => 60, 'hydrogen' => 3, 'orbit' => 1, 'hold' => 0, 'planet' => 3],
            bin2hex(random_bytes(16)), ['fighter' => '8']);
    }
}
