<?php
namespace App\Service\Colony;

use App\Entity\{Fleet,FleetShip,PlayerUniverse};
use App\Repository\GameRepository;
use App\Service\Catalog\GameCatalog;

final class EmpireDetails
{
    public function __construct(private GameRepository $repo,private ResourceService $resources,private GameCatalog $catalog) {}

    public function planets(PlayerUniverse $membership): array
    {
        $details=[];
        foreach($this->repo->planets($membership->universe,$membership) as $planet) {
            $fleets=$this->repo->all(Fleet::class,['playerUniverse'=>$membership,'homePlanet'=>$planet,'status'=>'idle']);
            $details[$planet->id]=['ships'=>$this->ships($membership,$fleets),'resources'=>$this->resources->snapshot($planet)];
        }
        return $details;
    }

    public function fleets(PlayerUniverse $membership,array $fleets): array
    {
        $details=[];
        foreach($fleets as $fleet) {
            if($fleet->playerUniverse->id!==$membership->id || $fleet->playerUniverse->universe->id!==$membership->universe->id) continue;
            $cargo=[];
            foreach($this->catalog->rules()['resources'] as $slug=>$label) {
                $stock=max(0,(float)($fleet->cargo[$slug]??0));
                if($stock>0) $cargo[$slug]=['label'=>$label,'stock'=>$stock];
            }
            $details[$fleet->id]=['ships'=>$this->ships($membership,[$fleet]),'resources'=>$cargo];
        }
        return $details;
    }

    private function ships(PlayerUniverse $membership,array $fleets): array
    {
        $ships=[];$definitions=$this->catalog->forUniverse($membership->universe)['catalog']['ships'];
        foreach($fleets as $fleet) foreach($this->repo->all(FleetShip::class,['fleet'=>$fleet]) as $ship) {
            if($ship->quantity<=0) continue;
            $key=$ship->slug.':'.$ship->tier;
            $ships[$key]??=['label'=>$definitions[$ship->slug]['label']??str_replace('_',' ',$ship->slug),
                'tier'=>$this->catalog->tiers()[$ship->tier]['label']??$ship->tier,'quantity'=>0];
            $ships[$key]['quantity']+=$ship->quantity;
        }
        return array_values($ships);
    }
}
