<?php

namespace App\Tests\Unit;

use App\Service\Admin\AdminActivationFormContext;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Clock\MockClock;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;

final class AdminActivationFormContextTest extends TestCase
{
    public function testOpaqueKeyIsScopedToActorAccountAndSession(): void
    {
        $context = new AdminActivationFormContext(new MockClock());
        $session = new Session(new MockArraySessionStorage());
        $another = new Session(new MockArraySessionStorage());
        $key = $context->issue($session, 1, 2, 3);
        self::assertMatchesRegularExpression('/^[a-f0-9]{64}$/D', $key);
        self::assertSame(3, $context->expectedReceipt($session, 1, 2, $key));
        self::assertSame(3, $context->expectedReceipt($session, 1, 2, $key)); // Rejeu borné.
        self::assertNull($context->expectedReceipt($session, 2, 2, $key));
        self::assertNull($context->expectedReceipt($session, 1, 1, $key));
        self::assertNull($context->expectedReceipt($session, 1, 2, str_repeat('a', 64)));
        self::assertNull($context->expectedReceipt($another, 1, 2, $key));
        // Même si une régénération Symfony conserve les attributs en mémoire,
        // la nouvelle session ne peut reprendre un ancien formulaire.
        $session->migrate();
        self::assertNull($context->expectedReceipt($session, 1, 2, $key));
    }

    public function testDeadlineIsStrictAndNumberOfContextsIsBounded(): void
    {
        $clock = new MockClock();
        $context = new AdminActivationFormContext($clock);
        $session = new Session(new MockArraySessionStorage());
        $oldest = $context->issue($session, 1, 2, 3);
        for ($i = 0; $i < 20; ++$i) {
            $fresh = $context->issue($session, 1, 2, 4 + $i);
        }
        self::assertNull($context->expectedReceipt($session, 1, 2, $oldest));
        $clock->sleep(119);
        self::assertSame(23, $context->expectedReceipt($session, 1, 2, $fresh));
        $clock->sleep(1);
        self::assertNull($context->expectedReceipt($session, 1, 2, $fresh));
    }
}
