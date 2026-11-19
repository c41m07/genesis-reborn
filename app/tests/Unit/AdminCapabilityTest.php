<?php

namespace App\Tests\Unit;

use App\Entity\Player;
use App\Security\{AdminCapability, AdminCapabilityPolicy, AdminCapabilityVoter};
use PHPUnit\Framework\TestCase;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;
use Symfony\Component\Security\Core\Authentication\Token\NullToken;
use Symfony\Component\Security\Core\Authorization\Voter\VoterInterface;

final class AdminCapabilityTest extends TestCase
{
    public function testKnownCapabilitiesPreserveLegacyAdminsAndDenyOtherAccounts(): void
    {
        $policy = new AdminCapabilityPolicy();
        $actor = new Player();
        foreach (AdminCapability::all() as $capability) {
            self::assertFalse($policy->allows(null, $capability));
            self::assertFalse($policy->allows($actor, $capability));
        }
        $actor->roles = ['ROLE_ADMIN'];
        foreach (AdminCapability::all() as $capability) {
            self::assertTrue($policy->allows($actor, $capability));
        }
        self::assertFalse($policy->allows($actor, 'ADMIN_CAPABILITY_UNKNOWN'));
        $actor->lockedAt = new \DateTimeImmutable();
        foreach (AdminCapability::all() as $capability) {
            self::assertFalse($policy->allows($actor, $capability));
        }
        $actor->lockedAt = null;
        $actor->roles = ['ROLE_USER', AdminCapability::PLAYER_VIEW, 'ROLE_SUPPORT'];
        $actor->preferences['admin_capabilities'] = AdminCapability::all();
        foreach (AdminCapability::all() as $capability) {
            self::assertFalse($policy->allows($actor, $capability));
        }
    }

    public function testVoterUsesCurrentActorAndRefusesUnknownScopesWithoutCachingGrants(): void
    {
        $voter = new AdminCapabilityVoter(new AdminCapabilityPolicy());
        $actor = new Player();
        $actor->roles = ['ROLE_ADMIN'];
        $token = new UsernamePasswordToken($actor, 'main', ['ROLE_ADMIN']);
        self::assertSame(VoterInterface::ACCESS_GRANTED, $voter->vote($token, null, [AdminCapability::PLAYER_VIEW]));
        self::assertSame(VoterInterface::ACCESS_DENIED, $voter->vote(new NullToken(), null, [AdminCapability::PLAYER_VIEW]));
        self::assertSame(VoterInterface::ACCESS_DENIED, $voter->vote($token, new \stdClass(), [AdminCapability::PLAYER_VIEW]));
        self::assertSame(VoterInterface::ACCESS_DENIED, $voter->vote($token, null, ['ADMIN_CAPABILITY_UNKNOWN']));
        self::assertSame(VoterInterface::ACCESS_ABSTAIN, $voter->vote($token, null, ['ROLE_ADMIN']));
        $actor->roles = [];
        self::assertSame(VoterInterface::ACCESS_DENIED, $voter->vote($token, null, [AdminCapability::PLAYER_VIEW]));
    }
}
