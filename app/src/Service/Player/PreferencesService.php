<?php
namespace App\Service\Player;
use App\Entity\Player;
use App\Manager\GameManager;
use App\Validator\DTO\PreferencesData;
final class PreferencesService
{
    public const DEFAULTS=['timezone'=>'Europe/Paris','reducedMotion'=>false,'realtimeNotifications'=>true,'theme'=>'dark','showLockedItems'=>false,'galaxySpySpeed'=>1.0,'galaxyColonizeSpeed'=>1.0,'transportReception'=>'allies'];
    public function __construct(private GameManager $manager){}
    private function preferencesFor(Player $player):array
    { return array_replace(self::DEFAULTS,isset($player->preferences)?$player->preferences:[]); }
    public function hydrate(Player $player,PreferencesData $data):void
    { $prefs=$this->preferencesFor($player);$data->transportReception=$prefs['transportReception'];$data->timezone=$prefs['timezone'];$data->theme=$prefs['theme'];$data->reducedMotion=(bool)$prefs['reducedMotion'];$data->realtimeNotifications=(bool)$prefs['realtimeNotifications'];$data->showLockedItems=(bool)$prefs['showLockedItems'];$data->galaxySpySpeed=(float)$prefs['galaxySpySpeed'];$data->galaxyColonizeSpeed=(float)$prefs['galaxyColonizeSpeed']; }
    public function save(Player $player,PreferencesData $data):void
    { if(!in_array($data->transportReception,['all','allies','none'],true))throw new \DomainException('Réception des transports invalide.');if(!in_array($data->timezone,\DateTimeZone::listIdentifiers(),true))throw new \DomainException('Fuseau horaire invalide.');if(!in_array($data->theme,['dark','light'],true))throw new \DomainException('Thème invalide.');if(!in_array($data->galaxySpySpeed,[.1,.2,.3,.4,.5,.6,.7,.8,.9,1.0],true)||!in_array($data->galaxyColonizeSpeed,[.1,.2,.3,.4,.5,.6,.7,.8,.9,1.0],true))throw new \DomainException('Vitesse de mission invalide.');$this->manager->transaction(function()use($player,$data){$this->manager->lock($player);$player->preferences=['timezone'=>$data->timezone,'reducedMotion'=>$data->reducedMotion,'realtimeNotifications'=>$data->realtimeNotifications,'theme'=>$data->theme,'showLockedItems'=>$data->showLockedItems,'galaxySpySpeed'=>$data->galaxySpySpeed,'galaxyColonizeSpeed'=>$data->galaxyColonizeSpeed,'transportReception'=>$data->transportReception];}); }
    public function for(Player $player):array{return $this->preferencesFor($player);}
}
