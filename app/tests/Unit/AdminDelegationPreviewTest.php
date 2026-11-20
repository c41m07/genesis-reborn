<?php

declare(strict_types=1);

namespace App\Tests\Unit;

use App\Service\Admin\AdminDelegationPreview;
use PHPUnit\Framework\TestCase;

final class AdminDelegationPreviewTest extends TestCase
{
    public function testProjectionUsesOnlyClosedScopesAndFields(): void
    {
        foreach (['', '314', 'A', 'production'] as $invalid) {
            self::assertNull(AdminDelegationPreview::project($invalid));
        }

        $atlas = AdminDelegationPreview::project('a');
        $boreal = AdminDelegationPreview::project('b');

        self::assertSame(['name', 'profiles'], array_keys($atlas));
        self::assertSame(['name', 'profiles'], array_keys($boreal));
        self::assertSame(['Support', 'Modération', 'Exploitation'], array_column($atlas['profiles'], 'label'));
        self::assertSame(['Support', 'Modération', 'Exploitation'], array_column($boreal['profiles'], 'label'));
        foreach ([...$atlas['profiles'], ...$boreal['profiles']] as $profile) {
            self::assertSame(['label', 'example', 'excluded'], array_keys($profile));
        }
        self::assertSame($atlas, AdminDelegationPreview::project('a'));
        self::assertSame([], array_intersect(array_column($atlas['profiles'], 'example'), array_column($boreal['profiles'], 'example')));
    }

    public function testObserverFixtureProjectsOnlyStatusInSelectedUniverse(): void
    {
        foreach (['a' => 'En attente', 'b' => 'Résolu'] as $scope => $state) {
            $result = AdminDelegationPreview::observeFixture($scope, 'active');
            self::assertSame(['decision', 'detail', 'remaining_minutes', 'status'], array_keys($result));
            self::assertSame('allowed', $result['decision']);
            self::assertSame($state, $result['status']);
            self::assertSame(15, $result['remaining_minutes']);
            self::assertStringNotContainsString('SUP-', json_encode($result, JSON_THROW_ON_ERROR));
        }
    }

    public function testObserverFixtureShowsDurationAndReasonsWithoutAnyStatusOnRefusal(): void
    {
        foreach (['a', 'b'] as $scope) {
            foreach (['expired' => 'écoulée', 'revoked' => 'révoqué', 'outside' => 'ne couvre pas'] as $scenario => $reason) {
                $result = AdminDelegationPreview::observeFixture($scope, $scenario);
                self::assertSame($scenario, $result['decision']);
                self::assertSame(0, $result['remaining_minutes']);
                self::assertNull($result['status']);
                self::assertStringContainsString($reason, $result['detail']);
                self::assertStringNotContainsString('SUP-', json_encode($result, JSON_THROW_ON_ERROR));
            }
        }
        self::assertNull(AdminDelegationPreview::observeFixture('other', 'active'));
        self::assertNull(AdminDelegationPreview::observeFixture('a', 'unknown'));
    }
}
