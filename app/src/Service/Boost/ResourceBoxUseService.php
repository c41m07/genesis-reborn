<?php
namespace App\Service\Boost;

use App\Entity\{Planet, PlayerBoost, PlayerUniverse};
use App\Manager\GameManager;
use App\Repository\GameRepository;
use App\Service\Colony\{ActionGuard, ResourceService};
use App\Service\Player\VacationCalendar;
use Psr\Clock\ClockInterface;

final class ResourceBoxUseService
{
    public function __construct(private GameManager $manager,private GameRepository $repo,private ActionGuard $guard,private ResourceService $resources,
        private VacationCalendar $vacations,private ResourceBoxPolicy $policy,private BoostGrantService $grants,private ClockInterface $clock) {}

    public function open(PlayerUniverse $membership, int $boxId, int $planetId, string $requestKey): bool
    {
        return $this->manager->transaction(function () use ($membership,$boxId,$planetId,$requestKey): bool {
            $planet=$this->repo->find(Planet::class,$planetId);
            if (!$planet) throw new \DomainException('Colonie introuvable.');
            $this->guard->lock($membership,$planet);
            if ($this->vacations->isActive($membership)) throw new \DomainException('L’ouverture des boîtes est bloquée pendant les vacances. Votre boîte est conservée.');
            if (!$this->guard->receipt($membership,$requestKey)) return false;
            $box=$this->repo->find(PlayerBoost::class,$boxId);
            if (!$box || $box->playerUniverse->id!==$membership->id || $box->kind!=='resource_box') throw new \DomainException('Boîte introuvable dans cet univers.');
            $this->manager->lock($box);
            if ($box->status!=='available') throw new \DomainException('Cette boîte n’est pas disponible.');
            $payload=$this->policy->validatePayload($box->payload ?? []);
            $result=$this->resources->deposit($planet,$payload['resources']);
            if (array_sum($result['overflow'])>0) throw new \DomainException('Ouverture refusée : les entrepôts ne peuvent pas accueillir tout le contenu. La boîte reste intacte.');
            $box->status='used';$box->usedAt=$this->clock->now();$box->operationType='planet';$box->operationId=$planetId;
            $this->manager->flush();$this->grants->promoteOldestPendingLocked($membership);
            return true;
        });
    }
}
