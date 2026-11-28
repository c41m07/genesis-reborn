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

    public function open(PlayerUniverse $membership, int $boxId, int $planetId, string $requestKey, array $acceptedLoss = []): bool
    {
        return $this->manager->transaction(function () use ($membership,$boxId,$planetId,$requestKey,$acceptedLoss): bool {
            $planet=$this->repo->find(Planet::class,$planetId);
            if (!$planet) throw new \DomainException('Colonie introuvable.');
            $this->guard->lock($membership,$planet);
            if (!$this->guard->receipt($membership,$requestKey)) return false;
            $box=$this->repo->find(PlayerBoost::class,$boxId);
            if (!$box || $box->playerUniverse->id!==$membership->id || $box->kind!=='resource_box') throw new \DomainException('Boîte introuvable dans cet univers.');
            $this->manager->lock($box);
            $now = $this->clock->now();
            if (ResourceBoxPolicy::hasExpired($box, $now)) throw new \DomainException('Cette boîte a expiré.');
            if ($this->vacations->isActive($membership)) throw new \DomainException('L’ouverture des boîtes est bloquée pendant les vacances.');
            if ($box->status!=='available') throw new \DomainException('Cette boîte n’est pas disponible.');
            $payload=$this->policy->validatePayload($box->payload ?? []);
            $contents = $payload['resources'];
            $result = $this->resources->deposit($planet, $contents, acceptedOverflow: $acceptedLoss);
            foreach ($result['overflow'] as $slug => $amount) {
                if ($amount <= ($acceptedLoss[$slug] ?? 0.0)) continue;

                // Laisser le temps de lire malgré la production continue. Le plafond affiché
                // borne toujours la perte, même si un autre convoi arrive avant la confirmation.
                $forecast = $this->resources->snapshot($planet, observedAt: $now->modify('+1 minute'));
                $estimate = [];
                $limits = [];
                foreach ($contents as $resource => $quantity) {
                    $room = max(0.0, $forecast[$resource]['capacity'] - $forecast[$resource]['stock']);
                    $current = $result['overflow'][$resource];
                    $limit = max($current, $quantity - $room);
                    if ($limit > 0.0) {
                        $estimate[$resource] = ceil($current * 100) / 100;
                        $limits[$resource] = ceil($limit * 100) / 100;
                    }
                }
                throw new BoxOverflowConfirmation($estimate, $limits, $planet->name);
            }
            $box->giftUnread = false;
            $box->status='used';$box->usedAt=$now;$box->operationType='planet';$box->operationId=$planetId;
            $this->manager->flush();$this->grants->promoteOldestPendingLocked($membership);
            return true;
        });
    }
}
