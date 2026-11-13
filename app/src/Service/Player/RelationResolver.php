<?php
namespace App\Service\Player;
use App\Entity\{PlayerUniverse,Guild,GuildMember,GuildRelation,AllianceContract};
use App\Repository\GameRepository;
final class RelationResolver
{
    public function __construct(private GameRepository $repo) {}
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
