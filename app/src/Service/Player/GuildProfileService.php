<?php
namespace App\Service\Player;

use App\Entity\{Guild,GuildProfile,PlayerUniverse};
use App\Manager\GameManager;
use App\Repository\GameRepository;
use App\Service\Catalog\GameCatalog;
use App\Service\Colony\ActionGuard;
use Symfony\Component\HttpFoundation\File\UploadedFile;

final class GuildProfileService
{
    public function __construct(private GameRepository $repo,private GameManager $manager,private ActionGuard $guard,private DiplomacyAccessPolicy $access,private RelationResolver $relations,private GameCatalog $catalog,private GuildImageStorage $images) {}

    public function save(PlayerUniverse $actor,int $guildId,string $description,int $version,?UploadedFile $logo,?UploadedFile $banner): void
    {
        $description=trim($description);
        if(!mb_check_encoding($description,'UTF-8') || mb_strlen($description)>2000) throw new \DomainException('La description doit contenir au maximum 2 000 caractères.');
        $created=[];$previous=[];
        try {
            $this->manager->transaction(function()use($actor,$guildId,$description,$version,$logo,$banner,&$created,&$previous): void {
                $this->guard->lockMembership($actor);
                $this->manager->lock($actor->player);
                if($actor->player->lockedAt!==null) throw new \DomainException('Ce compte est verrouillé.');
                $guild=$this->repo->find(Guild::class,$guildId);
                if(!$guild || $guild->universe->id!==$actor->universe->id || !$this->catalog->forUniverse($actor->universe)['features']['guilds_enabled']) throw new \DomainException('Guilde inaccessible.');
                $this->manager->lock($guild);
                if(!$this->access->canCommand($actor,$guild) || $this->relations->guild($actor)?->id!==$guild->id) throw new \DomainException('Seul le commandant actuel peut personnaliser la guilde.');
                $profile=$this->repo->one(GuildProfile::class,['guild'=>$guild]);
                if(($profile?->version??0)!==$version) throw new \DomainException('La présentation a changé. Vérifiez le formulaire avant de réessayer.');
                foreach(['logo'=>$logo,'banner'=>$banner] as $kind=>$file) if($file!==null) $created[$kind]=$this->images->store($file,$kind);
                $profile??=new GuildProfile();$profile->guild=$guild;$profile->description=$description;$profile->version=$version+1;
                foreach($created as $kind=>$filename) {
                    $property=$kind==='logo'?'logoFilename':'bannerFilename';$previous[]=$profile->$property;$profile->$property=$filename;
                }
                $this->manager->save($profile);
            });
        } catch(\Throwable $error) {
            foreach($created as $filename) $this->images->discard($filename);
            throw $error;
        }
        foreach($previous as $filename) $this->images->discard($filename);
    }
}
