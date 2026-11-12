<?php
namespace App\Service\Colony;
use App\Entity\{Player,Planet,Notification};
use App\Repository\GameRepository;
use Psr\Clock\ClockInterface;
use Symfony\Component\HttpFoundation\RequestStack;
final class GameShell
{
    public function __construct(private GameRepository $repo,private ResourceService $resources,private ClockInterface $clock,private RequestStack $requests,private \App\Service\Event\EventPanelService $eventPanel,private \App\Service\Player\LeaderboardService $leaderboard,private \App\Service\Player\SocialMessageService $messages,private \App\Service\Catalog\GameCatalog $catalog,private \App\Service\Player\VacationCalendar $vacations,private \App\Service\Player\EmpireActivityService $activity,private \App\Service\Notification\JournalService $journal,private \App\Service\Fleet\FleetVisibility $visibility) {}
    public function get(?Player $player,?Planet $selected=null):array
    {
        $m=$player?->currentPlayerUniverse;
        if(!$m || !$m->isPlayerControlled() || $m->player->id!==$player->id || !$m->universe->isAccessible($this->clock->now())) return ['membership'=>null,'planet'=>null,'planets'=>[],'resources'=>[],'notifications'=>[],'events'=>[]];
        $planets=$this->repo->planets($m->universe,$m);$session=$this->requests->getCurrentRequest()?->getSession();
        if($selected && $selected->owner?->id===$m->id) $session?->set('selected_planet_'.$m->id,$selected->id);
        if(!$selected) foreach($planets as $p) if($p->id===$session?->get('selected_planet_'.$m->id)) $selected=$p;
        $selected??=$planets[0]??null;
        $notifications=array_slice($this->repo->all(Notification::class,['recipient'=>$m],['id'=>'DESC']),0,5);
        $protection=null;
        foreach($this->leaderboard->forUniverse($m) as $row) if($row['membership']->id===$m->id) {$protection=$row['protection'];break;}
        $config=$this->catalog->forUniverse($m->universe);
        $effect=$config['catalog']['technologies']['colonial_expansion']['effect']??null;
        $colonyLimit=(int)$config['limits']['max_planets'];
        if(is_array($effect) && ($effect['type']??null)==='planet_limit') {
            $levels=$this->repo->levelsByTier(\App\Entity\PlayerTechnology::class,['playerUniverse'=>$m])['colonial_expansion']??[];
            $colonyLimit=\App\Service\Catalog\GameCatalog::colonyLimit($levels,$effect,$colonyLimit);
        }
        return ['incomingAttacks'=>$this->visibility->incoming($m),'serverNow'=>$this->clock->now()->format(DATE_ATOM),'journal'=>$this->journal->data($m),'vacation'=>$this->vacations->currentPeriod($m),'colonyLimit'=>$colonyLimit,'unreadMessages'=>$this->messages->unreadPrivateCount($m)+$this->messages->unreadGuildCount($m),'protection'=>$protection,'membership'=>$m,'planet'=>$selected,'planets'=>$planets,'resources'=>$selected?$this->resources->snapshot($selected):[],'notifications'=>$notifications,'events'=>$this->eventPanel->forPlayer($m)];
    }
}
