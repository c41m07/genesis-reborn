<?php
namespace App\Service\Colony;
use App\Entity\{Player,Planet,Notification};
use App\Repository\GameRepository;
use Psr\Clock\ClockInterface;
use Symfony\Component\HttpFoundation\RequestStack;
final class GameShell
{
    public function __construct(private GameRepository $repo,private ResourceService $resources,private ClockInterface $clock,private RequestStack $requests,private \App\Service\Event\GameEventService $gameEvents) {}
    public function get(?Player $player,?Planet $selected=null):array
    {
        $m=$player?->currentPlayerUniverse;
        if(!$m || $m->player->id!==$player->id || !$m->universe->isAccessible($this->clock->now())) return ['membership'=>null,'planet'=>null,'planets'=>[],'resources'=>[],'notifications'=>[],'events'=>[]];
        $planets=$this->repo->planets($m->universe,$m);$session=$this->requests->getCurrentRequest()?->getSession();
        if($selected && $selected->owner?->id===$m->id) $session?->set('selected_planet_'.$m->id,$selected->id);
        if(!$selected) foreach($planets as $p) if($p->id===$session?->get('selected_planet_'.$m->id)) $selected=$p;
        $selected??=$planets[0]??null;
        $notifications=array_slice($this->repo->all(Notification::class,['recipient'=>$m],['id'=>'DESC']),0,5);
        return ['membership'=>$m,'planet'=>$selected,'planets'=>$planets,'resources'=>$selected?$this->resources->snapshot($selected):[],'notifications'=>$notifications,'events'=>$this->gameEvents->visibleFor($m)];
    }
}
