<?php
namespace App\Service\Player;
use App\Entity\{PlayerUniverse,Guild,GuildMember,GuildRelation,AllianceContract};
use App\Repository\GameRepository;
final class RelationResolver
{
    public function __construct(private GameRepository $repo) {}
    public function guild(PlayerUniverse $p): ?Guild { return $this->repo->one(GuildMember::class,['playerUniverse'=>$p])?->guild; }
    public function guildStatus(?Guild $a,?Guild $b): string
    {
        if(!$a || !$b || $a->universe->id!==$b->universe->id) return 'neutral';
        if($a->id===$b->id) return 'allies';
        if($a->id>$b->id) [$a,$b]=[$b,$a];
        return $this->repo->one(GuildRelation::class,['guildA'=>$a,'guildB'=>$b])?->status??'neutral';
    }
    public function contract(PlayerUniverse $a,PlayerUniverse $b): ?AllianceContract
    {
        if($a->id>$b->id) [$a,$b]=[$b,$a];
        return $this->repo->one(AllianceContract::class,['playerA'=>$a,'playerB'=>$b]);
    }
    public function allied(PlayerUniverse $a,PlayerUniverse $b): bool
    {
        if($a->universe->id!==$b->universe->id) return false;
        if($a->id===$b->id) return true;
        $status=$this->guildStatus($this->guild($a),$this->guild($b));
        if($status==='enemies') return false;
        return $status==='allies' || $this->contract($a,$b)?->status==='accepted';
    }
}
