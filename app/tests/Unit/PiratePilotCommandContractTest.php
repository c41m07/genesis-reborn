<?php

declare(strict_types=1);

namespace App\Tests\Unit;

use App\Service\PirateCampaign\{PiratePilotCommandAction as Action, PiratePilotCommandInput, PiratePilotCommandIntent, PiratePilotCommandOptions};
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\{Request, RequestStack};
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;
use Symfony\Component\Security\Csrf\{CsrfToken, CsrfTokenManager};
use Symfony\Component\Security\Csrf\TokenStorage\SessionTokenStorage;

final class PiratePilotCommandContractTest extends TestCase
{
    public function testUntouchedFormsAreUnreadAndScoutAndDepositDoNotAcceptTargets(): void
    {
        foreach ([Action::Reconnaissance, Action::RetryDeposit] as $action) {
            $options = PiratePilotCommandOptions::noChoices($action);
            self::assertNull(PiratePilotCommandInput::parse(null, $options));
            $submitted = PiratePilotCommandInput::parse([], $options);
            self::assertSame([], $submitted->escortQuantities);
            self::assertNull($submitted->destinationChoice);
            foreach (['target', 'origin_id', 'discovery', 'cost', '_token', 'action'] as $key) {
                $this->assertRejected([$key => '1'], $options);
            }
        }
    }

    public function testAttackOnlyAcceptsAvailableEscortsByBoundedOrdinalsAndQuantities(): void
    {
        $options = PiratePilotCommandOptions::escort(['probe:basic' => 1, 'fighter:intermediate' => 4], 3);
        self::assertSame('fighter:intermediate', $options->escortKey(1));
        self::assertSame('probe:basic', $options->escortKey(2));
        $input = PiratePilotCommandInput::parse(['escort' => [2 => '0', 1 => '3']], $options);
        self::assertSame([1 => 3], $input->escortQuantities);
        self::assertNull($input->destinationChoice);
        foreach ([[], [1 => '0', 2 => '0'], [3 => '1'], ['01' => '1'], [1 => '4'], [1 => '3', 2 => '1'],
            [2 => '2'], [1 => ['nested'=>'1']], [1 => 1], [1 => true], [1 => '-1'], [1 => '01'],
            [1 => ' 1'], [1 => '1.0'], [1 => '1e0'], [1 => "1\n"], [1 => '2147483648'],
            ['fighter:intermediate' => '1'], ['ship_id' => '1'], array_fill(1, 257, '1')] as $invalid) {
            $this->assertRejected(['escort' => $invalid], $options);
        }
        foreach (['player', 'owner', 'membership', 'universe', 'mission_key', 'action', 'seed', 'target',
            'eta', 'cost', 'attempt', 'phase', 'worker_token', 'version'] as $field) {
            $this->assertRejected(['escort' => [1 => '1'], $field => '1'], $options);
        }
        $large = PiratePilotCommandOptions::escort(['fighter:basic' => 2147483647, 'fighter:superior' => 2147483647], 2147483647);
        $this->assertRejected(['escort' => [1 => '2147483647', 2 => '1']], $large);
    }

    public function testOnlyOwnedDestinationOrdinalsAreParsedNotTheirServerIds(): void
    {
        foreach ([Action::ChooseConvoyDestination, Action::SurvivorFallback] as $action) {
            $options = PiratePilotCommandOptions::destinations($action, [8190, 901, 711]);
            $input = PiratePilotCommandInput::parse(['destination_choice' => '1'], $options);
            self::assertSame(1, $input->destinationChoice);
            self::assertSame(711, $options->colonyId($input->destinationChoice));
            self::assertSame([], $input->escortQuantities);
            foreach ([[], ['destination_choice' => '0'], ['destination_choice' => '8190'], ['destination_choice' => '4'],
                ['destination_choice' => ['1']], ['destination_choice' => '01'], ['planet_id' => '711'],
                ['destination_choice' => '1', 'generation' => '1']] as $invalid) {
                $this->assertRejected($invalid, $options);
            }
        }
    }

    public function testNativeCsrfTokenIsBoundToActorMissionAndEveryRevisedServerChoice(): void
    {
        $fingerprint = hash('sha256', 'quote-version-known-by-server');
        $options = PiratePilotCommandOptions::destinations(Action::ChooseConvoyDestination, [44, 30]);
        $intent = new PiratePilotCommandIntent(11, 12, 17, 2, 1, $fingerprint, $options);

        $request = Request::create('/');
        $request->setSession(new Session(new MockArraySessionStorage()));
        $stack = new RequestStack();
        $stack->push($request);
        $manager = new CsrfTokenManager(storage: new SessionTokenStorage($stack));
        $issued = $manager->getToken($intent->csrfId())->getValue();
        self::assertTrue($manager->isTokenValid(new CsrfToken($intent->csrfId(), $issued)));

        $same = PiratePilotCommandOptions::destinations(Action::ChooseConvoyDestination, [30, 44]);
        $repeat = new PiratePilotCommandIntent(11, 12, 17, 2, 1, $fingerprint, $same);
        self::assertSame($intent->csrfId(), $repeat->csrfId());

        $insert = PiratePilotCommandOptions::destinations(Action::ChooseConvoyDestination, [29, 30, 44]);
        self::assertSame(30, $options->colonyId(1));
        self::assertSame(29, $insert->colonyId(1));
        foreach ([
            new PiratePilotCommandIntent(12, 12, 17, 2, 1, $fingerprint, $options),
            new PiratePilotCommandIntent(11, 13, 17, 2, 1, $fingerprint, $options),
            new PiratePilotCommandIntent(11, 12, 18, 2, 1, $fingerprint, $options),
            new PiratePilotCommandIntent(11, 12, 17, 3, 1, $fingerprint, $options),
            new PiratePilotCommandIntent(11, 12, 17, 2, 2, $fingerprint, $options),
            new PiratePilotCommandIntent(11, 12, 17, 2, 1, hash('sha256','other-quote'), $options),
            new PiratePilotCommandIntent(11, 12, 17, 2, 1, $fingerprint, $insert),
            new PiratePilotCommandIntent(11, 12, 17, 2, 1, $fingerprint,
                PiratePilotCommandOptions::destinations(Action::SurvivorFallback, [30, 44])),
        ] as $changed) {
            self::assertNotSame($intent->csrfId(), $changed->csrfId());
            self::assertFalse($manager->isTokenValid(new CsrfToken($changed->csrfId(), $issued)));
        }
    }

    public function testOptionSnapshotsDetachReferencesAndChangeWhenAvailabilityChanges(): void
    {
        $available = 2;
        $planet = 20;
        $escorts = PiratePilotCommandOptions::escort(['fighter:basic' => &$available, 'probe:basic' => 1], 3);
        $destination = PiratePilotCommandOptions::destinations(Action::SurvivorFallback, [&$planet, 31]);
        $available = 4;
        $planet = 35;
        self::assertSame(2, $escorts->maxEscortQuantity(1));
        self::assertSame(20, $destination->colonyId(1));
        self::assertSame(
            PiratePilotCommandOptions::escort(['probe:basic' => 1, 'fighter:basic' => 2], 3)->fingerprint(),
            $escorts->fingerprint(),
        );
        self::assertNotSame(
            PiratePilotCommandOptions::escort(['fighter:basic' => $available, 'probe:basic' => 1], 3)->fingerprint(),
            $escorts->fingerprint(),
        );
    }

    private function assertRejected(array $submitted, PiratePilotCommandOptions $options): void
    {
        try {
            PiratePilotCommandInput::parse($submitted, $options);
            self::fail('Champ ou option non autorisés acceptés.');
        } catch (\DomainException $expected) {
            self::assertSame('Les choix de cette commande ne sont plus valides. Rouvrez le dossier.', $expected->getMessage());
        }
    }
}
