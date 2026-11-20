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
}
