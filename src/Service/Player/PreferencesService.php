<?php
namespace App\Service\Player;
use App\Entity\Player;
use App\Manager\GameManager;
use App\Validator\DTO\PreferencesData;
final class PreferencesService
{
    public const DEFAULTS=['timezone'=>'Europe/Paris','reducedMotion'=>false,'realtimeNotifications'=>true,'theme'=>'dark'];
    public function __construct(private GameManager $manager){}
    private function preferencesFor(Player $player):array
    { return array_replace(self::DEFAULTS,isset($player->preferences)?$player->preferences:[]); }
    public function hydrate(Player $player,PreferencesData $data):void
    { $prefs=$this->preferencesFor($player);$data->timezone=$prefs['timezone'];$data->theme=$prefs['theme'];$data->reducedMotion=(bool)$prefs['reducedMotion'];$data->realtimeNotifications=(bool)$prefs['realtimeNotifications']; }
    public function save(Player $player,PreferencesData $data):void
    { if(!in_array($data->timezone,\DateTimeZone::listIdentifiers(),true))throw new \DomainException('Fuseau horaire invalide.');if(!in_array($data->theme,['dark','light'],true))throw new \DomainException('Thème invalide.');$this->manager->transaction(function()use($player,$data){$this->manager->lock($player);$player->preferences=['timezone'=>$data->timezone,'reducedMotion'=>$data->reducedMotion,'realtimeNotifications'=>$data->realtimeNotifications,'theme'=>$data->theme];}); }
    public function for(Player $player):array{return $this->preferencesFor($player);}
}
