<?php
namespace App\Service\Player;

use App\Entity\{Guild,GuildMember,GuildRank,PlayerUniverse};

final class DiplomacyAccessPolicy
{
    public const PERMISSIONS = [
        'members.assign_rank' => 'Attribuer les grades inférieurs',
        'diplomacy.alliance' => 'Proposer et accepter les alliances de guilde',
        'diplomacy.hostility' => 'Déclarer une hostilité de guilde',
        'diplomacy.neutral' => 'Revenir à la neutralité entre guildes',
    ];

    public function allows(?GuildMember $member, string $permission): bool
    {
        if ($member === null || !array_key_exists($permission, self::PERMISSIONS)) return false;
        $actor = $member->playerUniverse;
        $guild = $member->guild;
        if (!$actor->isPlayerControlled() || $actor->id === null || $guild->id === null
            || $guild->archivedAt !== null || $actor->universe->id !== $guild->universe->id) return false;
        if ($this->canCommand($actor, $guild)) return true;

        return $member->rank !== null
            && $member->rank->guild->id === $guild->id
            && in_array($permission, $member->rank->permissions, true);
    }

    public function canAssignRank(GuildMember $actor, GuildMember $target, ?GuildRank $rank): bool
    {
        if (!$this->allows($actor, 'members.assign_rank')
            || !$target->playerUniverse->isPlayerControlled()
            || $actor->guild->id !== $target->guild->id
            || $actor->playerUniverse->id === $target->playerUniverse->id
            || $target->guild->leader->id === $target->playerUniverse->id
            || ($rank !== null && $rank->guild->id !== $actor->guild->id)
            || ($target->rank !== null && $target->rank->guild->id !== $actor->guild->id)) return false;
        if ($this->canCommand($actor->playerUniverse, $actor->guild)) return true;

        return ($target->rank === null || $target->rank->position > $actor->rank->position)
            && ($rank === null || ($rank->position > $actor->rank->position
                && array_diff($rank->permissions, $actor->rank->permissions) === []));
    }

    public function canCommand(PlayerUniverse $actor, ?Guild $guild): bool
    {
        return $guild !== null
            && $guild->archivedAt === null
            && $actor->id !== null
            && $actor->universe->id !== null
            && $actor->isPlayerControlled()
            && $actor->universe->id === $guild->universe->id
            && $guild->leader->id === $actor->id;
    }

    public function canRelateToPlayer(PlayerUniverse $actor, PlayerUniverse $target): bool
    {
        return $actor->id !== null
            && $target->id !== null
            && $actor->universe->id !== null
            && $actor->id !== $target->id
            && $actor->universe->id === $target->universe->id
            && $actor->isPlayerControlled()
            && $target->isPlayerControlled();
    }
}
