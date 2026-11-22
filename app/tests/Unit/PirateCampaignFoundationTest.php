<?php

declare(strict_types=1);

namespace App\Tests\Unit;

use App\Entity\{Galaxy, Player, PlayerUniverse, SolarSystem, Universe};
use App\Service\PirateCampaign\{PirateDistressSite, PirateEncounterSnapshot};
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class PirateCampaignFoundationTest extends TestCase
{
    public function testOnlyTheOriginalMembershipCanReadTheLoreAndNoCoordinatesLeak(): void
    {
        $universe = new Universe();
        $universe->id = 1;
        $otherUniverse = new Universe();
        $otherUniverse->id = 2;
        $actor = $this->membership($universe, 10, 20);
        $system = $this->system($universe, 30);
        $site = PirateDistressSite::inExistingSystem($actor, $system);

        self::assertTrue($site->matchesSystem($this->system($universe, 30)));
        self::assertFalse($site->matchesSystem($this->system($universe, 31)));
        self::assertFalse($site->matchesSystem($this->system($otherUniverse, 30)));

        $signal = $site->signalFor($actor);
        self::assertSame(['title', 'message'], array_keys($signal ?? []));
        self::assertStringContainsString('relais civil', $signal['message']);
        self::assertNull($site->signalFor($this->membership($universe, 11, 21)));
        self::assertNull($site->signalFor($this->membership($otherUniverse, 12, 20)));
        self::assertNull($site->signalFor($this->membership($universe, 10, 22)));
        $actor->forgottenAt = new \DateTimeImmutable();
        self::assertNull($site->signalFor($actor));
    }

    public function testSiteCannotBeCreatedInAnotherUniverseOrForATemporarySystem(): void
    {
        $universe = new Universe();
        $universe->id = 1;
        $otherUniverse = new Universe();
        $otherUniverse->id = 2;
        $actor = $this->membership($universe, 10, 20);
        foreach ([$this->system($otherUniverse, 30), $this->system($universe, null)] as $system) {
            try {
                PirateDistressSite::inExistingSystem($actor, $system);
                self::fail('Le poste doit rester ancré à un système persistant du même univers.');
            } catch (\DomainException $exception) {
                self::assertStringContainsString('système existant de cet univers', $exception->getMessage());
            }
        }
    }

    public function testCompositionAndCostAreIndependentOfEditsToTheirSourceOrReturnedArrays(): void
    {
        $quantity = 3;
        $price = 120;
        $source = ['fighter' => ['intermediate' => &$quantity]];
        $costs = ['metal' => &$price, 'nano' => 40];
        $snapshot = PirateEncounterSnapshot::freeze($source, $costs);
        $quantity = 8;
        $price = 240;
        $visible = $snapshot->composition();
        $visible['fighter']['intermediate'] = 11;

        self::assertSame(['fighter' => ['intermediate' => 3]], $snapshot->composition());
        self::assertSame(['metal' => 120, 'nano' => 40], $snapshot->buildCosts());
        self::assertSame('{}', json_encode($snapshot), 'Pas de structure publique par défaut avant la reconnaissance.');
    }

    #[DataProvider('invalidSnapshots')]
    public function testSnapshotRefusesMissingOrInvalidData(array $composition, array $costs): void
    {
        $this->expectException(\DomainException::class);
        PirateEncounterSnapshot::freeze($composition, $costs);
    }

    public static function invalidSnapshots(): iterable
    {
        yield 'pas de flotte' => [[], ['metal' => 1]];
        yield 'pas de prix' => [['fighter' => ['basic' => 1]], []];
        yield 'effectif nul' => [['fighter' => ['basic' => 0]], ['metal' => 1]];
        yield 'rang absent du catalogue' => [['fighter' => ['unknown' => 1]], ['metal' => 1]];
        yield 'coût négatif' => [['fighter' => ['basic' => 1]], ['metal' => -1]];
        yield 'coût non entier' => [['fighter' => ['basic' => 1]], ['metal' => 2.5]];
        yield 'ressource inconnue' => [['fighter' => ['basic' => 1]], ['other' => 1]];
    }

    private function membership(Universe $universe, int $membershipId, int $playerId): PlayerUniverse
    {
        $player = new Player();
        $player->id = $playerId;
        $membership = new PlayerUniverse();
        $membership->id = $membershipId;
        $membership->universe = $universe;
        $membership->player = $player;

        return $membership;
    }

    private function system(Universe $universe, ?int $systemId): SolarSystem
    {
        $galaxy = new Galaxy();
        $galaxy->universe = $universe;
        $system = new SolarSystem();
        $system->id = $systemId;
        $system->galaxy = $galaxy;

        return $system;
    }
}
