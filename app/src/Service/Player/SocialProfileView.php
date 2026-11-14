<?php
namespace App\Service\Player;

use App\Entity\{Guild,GuildMember,GuildProfile,PlayerUniverse};
use App\Repository\GameRepository;
use App\Service\Catalog\GameCatalog;

final class SocialProfileView
{
    public function __construct(private GameRepository $repo,private DailyLeaderboard $leaderboard,private RelationResolver $relations,private GameCatalog $catalog) {}

    public function player(PlayerUniverse $viewer,int $id): array
    {
        if(!$viewer->isPlayerControlled()) throw new \DomainException('Profil inaccessible.');
        $target=$this->repo->find(PlayerUniverse::class,$id);
        if(!$target || !$target->isPlayerControlled() || $target->universe->id!==$viewer->universe->id) throw new \DomainException('Commandant inaccessible dans cet univers.');
        $daily=$this->leaderboard->forUniverse($viewer);$score=null;
        foreach($daily['players'] as $row) if($row['membership_id']===$id) {$score=$row;break;}
        $categories=['points'=>['Points totaux','total_points'],'buildings'=>['Points de bâtiments','building_points'],
            'research'=>['Points de recherche','research_points'],'fleet'=>['Points de flotte','fleet_points'],'planets'=>['Nombre de planètes','planets']];
        $scores=[];
        foreach($categories as $key=>[$label,$field]) $scores[]=['category'=>$key,'label'=>$label,'value'=>$score[$field]??null,'rank'=>$score['ranks'][$key]??null];
        $guild=$this->relations->guild($target);
        return ['id'=>$target->id,'username'=>$target->player->username,'scores'=>$scores,'updated_at'=>$daily['updated_at'],
            'guild'=>$guild?['id'=>$guild->id,'name'=>$guild->name,'tag'=>$guild->tag,'can_view'=>$this->guildsEnabled($viewer)]:null];
    }

    public function accessibleGuild(PlayerUniverse $viewer,int $id): Guild
    {
        if(!$viewer->isPlayerControlled()) throw new \DomainException('Guilde inaccessible.');
        $guild=$this->repo->find(Guild::class,$id);
        if(!$this->guildsEnabled($viewer) || !$guild || $guild->archivedAt!==null || $guild->universe->id!==$viewer->universe->id) throw new \DomainException('Guilde inaccessible dans cet univers.');
        return $guild;
    }

    public function guild(PlayerUniverse $viewer,int $id): array
    {
        $guild=$this->accessibleGuild($viewer,$id);$profile=$this->repo->one(GuildProfile::class,['guild'=>$guild]);
        return ['id'=>$guild->id,'name'=>$guild->name,'tag'=>$guild->tag,'description'=>$profile?->description??'',
            'has_logo'=>$profile?->logoFilename!==null,'has_banner'=>$profile?->bannerFilename!==null,
            'version'=>$profile?->version??0,'member_count'=>count($this->repo->all(GuildMember::class,['guild'=>$guild])),
            'leader'=>$guild->leader->isPlayerControlled()?['id'=>$guild->leader->id,'username'=>$guild->leader->player->username]:null];
    }

    public function media(PlayerUniverse $viewer,int $id,string $kind): ?string
    {
        $guild=$this->accessibleGuild($viewer,$id);$profile=$this->repo->one(GuildProfile::class,['guild'=>$guild]);
        return match($kind){'logo'=>$profile?->logoFilename,'banner'=>$profile?->bannerFilename,default=>null};
    }

    private function guildsEnabled(PlayerUniverse $viewer): bool
    {
        return (bool)$this->catalog->forUniverse($viewer->universe)['features']['guilds_enabled'];
    }
}
