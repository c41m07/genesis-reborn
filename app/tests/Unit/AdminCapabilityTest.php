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
    public function testDefinitionsCoverTheClosedMatrixWithoutGrantingRoles(): void
    {
        self::assertSame([
            AdminCapability::PLAYER_VIEW,
            AdminCapability::RESOURCES_ADJUST,
            AdminCapability::SHIPS_GRANT,
            AdminCapability::FLEET_INTERVENE,
        ], AdminCapability::all());
        foreach (AdminCapability::definitions() as $definition) {
            foreach (['label', 'effect', 'scope', 'safeguards'] as $field) {
                self::assertNotEmpty($definition[$field]);
            }
        }
    }

    public function testDecisionsDoNotLeakBetweenActorsOrUnsupportedScopes(): void
    {
        $voter = new AdminCapabilityVoter(new AdminCapabilityPolicy());
        $admin = new Player();
        $admin->roles = ['ROLE_ADMIN'];
        $ordinary = new Player();
        // Le rôle du token ne doit pas remplacer les droits courants de l'acteur.
        $adminToken = new UsernamePasswordToken($admin, 'main', ['ROLE_ADMIN']);
        $forgedToken = new UsernamePasswordToken($ordinary, 'main', ['ROLE_ADMIN']);
        foreach (AdminCapability::all() as $capability) {
            self::assertSame(VoterInterface::ACCESS_GRANTED, $voter->vote($adminToken, null, [$capability]));
            self::assertSame(VoterInterface::ACCESS_DENIED, $voter->vote($forgedToken, null, [$capability]));
            self::assertSame(VoterInterface::ACCESS_GRANTED, $voter->vote($adminToken, null, [$capability]));
            // Aucun identifiant d'univers ou de cible ne constitue une portée autorisée.
            foreach ([1, '1', [], ['universe_id' => 1], new \stdClass()] as $subject) {
                self::assertSame(VoterInterface::ACCESS_DENIED, $voter->vote($adminToken, $subject, [$capability]));
            }
            $admin->lockedAt = new \DateTimeImmutable();
            self::assertSame(VoterInterface::ACCESS_DENIED, $voter->vote($adminToken, null, [$capability]));
            $admin->lockedAt = null;
            $admin->roles = [];
            self::assertSame(VoterInterface::ACCESS_DENIED, $voter->vote($adminToken, null, [$capability]));
            $admin->roles = ['ROLE_ADMIN'];
        }
    }

}
