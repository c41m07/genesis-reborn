<?php
namespace App\Tests\Unit;

use App\Entity\{Guild,GuildMember,GuildRank,Player,PlayerUniverse,Universe};
use App\Service\Player\DiplomacyAccessPolicy;
use PHPUnit\Framework\TestCase;

final class DiplomacyAccessPolicyTest extends TestCase
{
    private function membership(int $id, int $universeId = 1): PlayerUniverse
    {
        $membership = new PlayerUniverse();
        $membership->id = $id;
        $membership->player = new Player();
        $membership->universe = new Universe();
        $membership->universe->id = $universeId;

        return $membership;
    }

    public function testAuthorityFollowsTransferAndRejectsArchivedGuild(): void
    {
        $policy = new DiplomacyAccessPolicy();
        $leader = $this->membership(1);
        $successor = $this->membership(2);
        $guild = new Guild();
        $guild->universe = $leader->universe;
        $guild->leader = $leader;
        self::assertTrue($policy->canCommand($leader, $guild));
        self::assertFalse($policy->canCommand($successor, $guild));
        $guild->leader = $successor;
        self::assertFalse($policy->canCommand($leader, $guild));
        self::assertTrue($policy->canCommand($successor, $guild));
        $guild->archivedAt = new \DateTimeImmutable('2026-11-10T00:00:00Z');
        self::assertFalse($policy->canCommand($successor, $guild));
        self::assertFalse($policy->canCommand($leader, null));
    }

    public function testCommandRejectsForeignUniverseAndForgottenLeader(): void
    {
        $policy = new DiplomacyAccessPolicy();
        $leader = $this->membership(1);
        $guild = new Guild();
        $guild->leader = $leader;
        $guild->universe = $this->membership(2, 2)->universe;
        self::assertFalse($policy->canCommand($leader, $guild));
        $guild->universe = $leader->universe;
        $leader->forgottenAt = new \DateTimeImmutable('2026-11-10T00:00:00Z');
        self::assertFalse($policy->canCommand($leader, $guild));
    }

    public function testPersonalAllianceDoesNotRequireGuildAndIsSymmetric(): void
    {
        $policy = new DiplomacyAccessPolicy();
        $actor = $this->membership(1);
        $target = $this->membership(2);
        self::assertTrue($policy->canRelateToPlayer($actor, $target));
        self::assertTrue($policy->canRelateToPlayer($target, $actor));
        self::assertFalse($policy->canRelateToPlayer($actor, $actor));
        self::assertFalse($policy->canRelateToPlayer($actor, $this->membership(3, 2)));
    }

    public function testPersonalRelationsRejectDetachedForgottenAndUnpersistedPlayers(): void
    {
        $policy = new DiplomacyAccessPolicy();
        $actor = $this->membership(1);
        $target = $this->membership(2);
        $target->player = null;
        self::assertFalse($policy->canRelateToPlayer($actor, $target));
        self::assertFalse($policy->canRelateToPlayer($target, $actor));
        $target->player = new Player();
        $target->forgottenAt = new \DateTimeImmutable('2026-11-10T00:00:00Z');
        self::assertFalse($policy->canRelateToPlayer($actor, $target));
        self::assertFalse($policy->canRelateToPlayer($target, $actor));
        $target->forgottenAt = null;
        $target->id = null;
        self::assertFalse($policy->canRelateToPlayer($actor, $target));
    }

    public function testDelegationCannotEscalateOrCrossGuildBoundaries(): void
    {
        $policy = new DiplomacyAccessPolicy();
        $guild = new Guild();
        $guild->id = 1;
        $guild->leader = $this->membership(1);
        $guild->universe = $guild->leader->universe;
        $actor = new GuildMember();
        $actor->guild = $guild;
        $actor->playerUniverse = $this->membership(2);
        $actor->rank = new GuildRank();
        $actor->rank->guild = $guild;
        $actor->rank->position = 10;
        $actor->rank->permissions = ['members.assign_rank', 'diplomacy.alliance'];
        $target = new GuildMember();
        $target->guild = $guild;
        $target->playerUniverse = $this->membership(3);
        $rank = new GuildRank();
        $rank->guild = $guild;
        $rank->position = 20;
        $rank->permissions = ['diplomacy.alliance'];
        self::assertTrue($policy->canAssignRank($actor, $target, $rank));
        self::assertTrue($policy->canAssignRank($actor, $target, null));
        self::assertFalse($policy->canAssignRank($actor, $actor, $rank));
        $rank->permissions[] = 'diplomacy.hostility';
        self::assertFalse($policy->canAssignRank($actor, $target, $rank));
        $rank->permissions = [];
        $rank->position = 10;
        self::assertFalse($policy->canAssignRank($actor, $target, $rank));
        $rank->position = 5;
        self::assertFalse($policy->canAssignRank($actor, $target, $rank));
        $rank->position = 20;
        $target->rank = clone $actor->rank;
        self::assertFalse($policy->canAssignRank($actor, $target, $rank));
        $target->rank = null;
        $rank->guild = clone $guild;
        $rank->guild->id = 2;
        self::assertFalse($policy->canAssignRank($actor, $target, $rank));
        $target->playerUniverse = $guild->leader;
        self::assertFalse($policy->canAssignRank($actor, $target, null));
    }

    public function testRankRevocationAndForeignRankFailClosed(): void
    {
        $policy = new DiplomacyAccessPolicy();
        $guild = new Guild();
        $guild->id = 1;
        $guild->leader = $this->membership(1);
        $guild->universe = $guild->leader->universe;
        $member = new GuildMember();
        $member->guild = $guild;
        $member->playerUniverse = $this->membership(2);
        self::assertFalse($policy->allows($member, 'diplomacy.alliance'));
        $member->rank = new GuildRank();
        $member->rank->guild = $guild;
        $member->rank->permissions = ['diplomacy.alliance'];
        self::assertTrue($policy->allows($member, 'diplomacy.alliance'));
        self::assertFalse($policy->allows($member, 'ROLE_ADMIN'));
        $member->rank->permissions = [];
        self::assertFalse($policy->allows($member, 'diplomacy.alliance'));
        $member->rank->permissions = ['diplomacy.alliance'];
        $member->rank->guild = clone $guild;
        $member->rank->guild->id = 2;
        self::assertFalse($policy->allows($member, 'diplomacy.alliance'));
    }
}
