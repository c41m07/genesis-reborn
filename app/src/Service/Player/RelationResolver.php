<?php
namespace App\Service\Player;
use App\Entity\{PlayerUniverse,Guild,GuildMember,GuildRelation,AllianceContract};
use App\Repository\GameRepository;
final class RelationResolver
{
    public function __construct(private GameRepository $repo) {}
    /** Préparation d’affichage uniquement ; les validations d’actions relisent leur état. */
    public function displayRows(PlayerUniverse $viewer, array $players, ?Guild $own, array $contracts, array $guildRelations): array
    {
        $guilds = [];
        foreach ($this->repo->guildMemberships($players, $viewer->universe) as $member) {
            $guilds[$member->playerUniverse->id] = $member->guild;
        }
        $byPeer = [];
        foreach ($contracts as $contract) {
            $byPeer[$contract->playerA->id === $viewer->id ? $contract->playerB->id : $contract->playerA->id] = $contract;
        }
        $rows = [];
        foreach ($players as $player) {
            if ($player->id === $viewer->id || !$player->isPlayerControlled() || $player->universe->id !== $viewer->universe->id) continue;
            $guild = $guilds[$player->id] ?? null;
            $status = $own && $guild ? ($own->id === $guild->id ? 'allies' : ($guildRelations[$guild->id]->status ?? 'neutral')) : 'neutral';
            $contract = $byPeer[$player->id] ?? null;
            // Les alliances et rivalités de guilde gardent la priorité sur les contrats personnels.
            $rows[$player->id] = ['player'=>$player, 'contract'=>$contract, 'guildStatus'=>$status,
                'allied'=>$status !== 'enemies' && ($status === 'allies' || $contract?->status === 'accepted'),
                'enemy'=>$status === 'enemies' || ($status !== 'allies' && in_array($contract?->status, AllianceContract::HOSTILE_STATUSES, true))];
        }
        return $rows;
    }
    public function guild(PlayerUniverse $p): ?Guild
    {
        $guild=$p->isPlayerControlled()?$this->repo->one(GuildMember::class,['playerUniverse'=>$p])?->guild:null;
        return $guild?->archivedAt===null?$guild:null;
    }
    public function guildStatus(?Guild $a,?Guild $b): string
    {
        if(!$a || !$b || $a->archivedAt!==null || $b->archivedAt!==null || $a->universe->id!==$b->universe->id) return 'neutral';
        if($a->id===$b->id) return 'allies';
        if($a->id>$b->id) [$a,$b]=[$b,$a];
        return $this->repo->one(GuildRelation::class,['guildA'=>$a,'guildB'=>$b])?->status??'neutral';
    }
    public function nonAggression(PlayerUniverse $a,PlayerUniverse $b): bool
    {
        return $a->isPlayerControlled() && $b->isPlayerControlled()
            && $a->universe->id===$b->universe->id
            && in_array($this->guildStatus($this->guild($a),$this->guild($b)),['non_aggression','defense'],true);
    }
    public function canStationDefenders(PlayerUniverse $a,PlayerUniverse $b): bool
    {
        return $this->allied($a,$b) || ($a->isPlayerControlled() && $b->isPlayerControlled()
            && $a->universe->id===$b->universe->id
            && $this->guildStatus($this->guild($a),$this->guild($b))==='defense');
    }
    public function contract(PlayerUniverse $a,PlayerUniverse $b): ?AllianceContract
    {
        if(!$a->isPlayerControlled() || !$b->isPlayerControlled()) return null;
        if($a->id>$b->id) [$a,$b]=[$b,$a];
        return $this->repo->one(AllianceContract::class,['playerA'=>$a,'playerB'=>$b]);
    }
    public function enemy(PlayerUniverse $actor, PlayerUniverse $target): bool
    {
        if (!$actor->isPlayerControlled() || !$target->isPlayerControlled()
            || $actor->universe->id !== $target->universe->id || $actor->id === $target->id) return false;
        $status = $this->guildStatus($this->guild($actor), $this->guild($target));
        if ($status === 'enemies') return true;
        if ($status === 'allies') return false;

        return in_array($this->contract($actor, $target)?->status, AllianceContract::HOSTILE_STATUSES, true);
    }

    public function allied(PlayerUniverse $a,PlayerUniverse $b): bool
    {
        if(!$a->isPlayerControlled() || !$b->isPlayerControlled()) return false;
        if($a->universe->id!==$b->universe->id) return false;
        if($a->id===$b->id) return true;
        $status=$this->guildStatus($this->guild($a),$this->guild($b));
        if($status==='enemies') return false;
        return $status==='allies' || $this->contract($a,$b)?->status==='accepted';
    }
}
