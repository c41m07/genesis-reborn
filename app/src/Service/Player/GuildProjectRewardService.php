<?php
namespace App\Service\Player;

use App\Entity\{GuildProject,PlayerUniverse};
use App\Manager\GameManager;
use App\Repository\GameRepository;
use App\Service\Boost\BoostGrantService;
use App\Service\Notification\NotificationService;
use Psr\Clock\ClockInterface;

final class GuildProjectRewardService
{
    public function __construct(private GameManager $manager,private GameRepository $repo,private BoostGrantService $boosts,private NotificationService $notifications,private ClockInterface $clock) {}

    public function distribute(int $projectId,string $token): void
    {
        $project=$this->repo->find(GuildProject::class,$projectId);
        if(!$project || $project->status!=='completed' || $project->rewardToken!==$token || $project->rewardsDeliveredAt!==null) return;
        // Chaque récompense est distribuée après le don, dans sa propre transaction.
        foreach($project->eligibleIds as $id) {
            $player=$this->repo->find(PlayerUniverse::class,$id);
            if(!$player || $player->universe->id!==$project->universe->id || ($player->player===null && $player->formerPlayer===null)) continue;
            $this->manager->transaction(function()use($player,$project): void {
                if($this->boosts->awardGuildProject($player,$project->definition->id)) {
                    $this->notifications->enqueue($player,'Projet de guilde terminé : '.$project->snapshot['name'].'. Votre contribution vous rapporte un boost, disponible dans l’inventaire ou sa file d’attente.');
                }
            });
        }
        $this->manager->transaction(function()use($project,$token): void {
            $this->manager->lock($project);
            if($project->rewardToken===$token && $project->status==='completed') $project->rewardsDeliveredAt=$this->clock->now();
        });
    }
}
