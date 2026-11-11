<?php
namespace App\Service\Admin;

use App\Entity\{AdminAuditLog, CombatParticipant, Fleet, Planet, PlanetBuilding, Player};
use App\Manager\GameManager;
use App\Repository\GameRepository;
use App\Service\Catalog\GameCatalog;
use App\Service\Colony\{ActionGuard, ResourceService};
use App\Service\Fleet\FleetService;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Clock\ClockInterface;

final class AdminInterventionService
{
    public function __construct(private EntityManagerInterface $em, private GameManager $manager, private GameRepository $repo, private GameCatalog $catalog,
        private ActionGuard $guard, private ResourceService $resources, private FleetService $fleets, private ClockInterface $clock) {}

    public function resources(Player $actor, int $playerId, Planet $planet, string $operation, array $amounts, string $reason, string $requestKey): bool
    {
        $reason = $this->reason($reason);
        if (!in_array($operation, ['add', 'remove'], true)) throw new \DomainException('Choisissez un ajout ou un retrait.');
        $values = [];
        foreach ($this->catalog->rules()['resources'] as $slug=>$label) {
            $value = $amounts[$slug] ?? '0';
            if (!is_scalar($value) || !preg_match('/^\d{1,12}(?:\.\d{1,2})?$/D', (string) $value)) throw new \DomainException('Quantité invalide pour '.$label.' : utilisez un nombre positif avec deux décimales au maximum.');
            $values[$slug] = (float) $value;
        }
        if (array_sum($values) <= 0) throw new \DomainException('Indiquez au moins une quantité supérieure à zéro.');
        return $this->manager->transaction(function () use ($actor, $playerId, $planet, $operation, $values, $reason, $requestKey): bool {
            $this->authorize($actor);
            $membership = $planet->owner;
            if (!$membership) throw new \DomainException('Cette colonie ne possède plus de joueur.');
            $this->guard->lock($membership, $planet);
            if ($planet->owner?->player?->id !== $playerId) throw new \DomainException('Cette colonie n’appartient plus au joueur sélectionné.');
            if (!$this->guard->receipt($membership, $requestKey)) return false;
            $rows = $this->resources->materialize($planet);
            $levels = $this->repo->levelsByTier(PlanetBuilding::class, ['planet'=>$planet]);
            $before = $after = [];
            foreach ($values as $slug=>$amount) {
                if (!isset($rows[$slug])) throw new \DomainException('Stocks de la colonie incomplets : aucune correction appliquée.');
                $before[$slug] = $rows[$slug]->currentStock;
                $after[$slug] = $before[$slug] + ($operation === 'add' ? $amount : -$amount);
                if ($after[$slug] < 0 || $after[$slug] > $this->resources->capacity($planet, $slug, $levels)) throw new \DomainException('Correction refusée : stock négatif ou capacité d’entrepôt dépassée pour '.$slug.'.');
            }
            foreach ($after as $slug=>$stock) $rows[$slug]->currentStock = $stock;
            $this->audit($actor, 'admin_resources_adjust', 'planet', $planet->id, $reason, ['player_id'=>$playerId, 'universe_id'=>$membership->universe->id, 'operation'=>$operation, 'amounts'=>$values, 'before'=>$before, 'after'=>$after]);
            return true;
        });
    }

    public function fleet(Player $actor, Fleet $fleet, string $operation, int $seconds, string $reason, string $expectedVersion, string $requestKey): bool
    {
        $reason = $this->reason($reason);
        if (!in_array($operation, ['reschedule', 'recall', 'force_return'], true)) throw new \DomainException('Intervention inconnue.');
        if ($operation === 'reschedule' && ($seconds < 1 || $seconds > 31536000)) throw new \DomainException('Le délai doit être compris entre une seconde et un an.');
        return $this->manager->transaction(function () use ($actor, $fleet, $operation, $seconds, $reason, $expectedVersion, $requestKey): bool {
            $this->authorize($actor);
            $this->guard->lock($fleet->playerUniverse, $fleet->homePlanet);
            $this->manager->lock($fleet);
            if (!$this->guard->receipt($fleet->playerUniverse, $requestKey)) return false;
            if (!hash_equals(hash('sha256', $fleet->dispatchToken), $expectedVersion)) throw new \DomainException('La mission a changé depuis l’ouverture du formulaire. Actualisez sa fiche.');
            $pending = $this->em->createQueryBuilder()->select('COUNT(p.id)')->from(CombatParticipant::class, 'p')->join('p.combatEvent', 'e')
                ->where('p.fleet = :fleet')->andWhere('e.status = :status')->setParameter('fleet', $fleet)->setParameter('status', 'pending')->getQuery()->getSingleScalarResult();
            if ($pending > 0) throw new \DomainException('Intervention refusée : cette flotte participe à un combat en cours.');
            $expired = $fleet->arrivalAt && $fleet->arrivalAt <= $this->clock->now();
            if ($operation === 'force_return' && !$expired) throw new \DomainException('Le retour forcé est réservé aux missions dont l’échéance est atteinte.');
            if ($operation !== 'force_return' && $expired) throw new \DomainException('L’échéance est déjà atteinte. Vérifiez les traitements ou utilisez le retour forcé après contrôle.');
            $before = $this->fleetState($fleet);
            if ($operation === 'force_return') $this->fleets->forceReturnForAdministrationLocked($fleet);
            elseif ($operation === 'recall') $this->fleets->recallForAdministrationLocked($fleet);
            else $this->fleets->rescheduleForAdministrationLocked($fleet, $this->clock->now()->modify('+'.$seconds.' seconds'));
            $this->audit($actor, 'admin_fleet_'.$operation, 'fleet', $fleet->id, $reason, ['universe_id'=>$fleet->playerUniverse->universe->id, 'before'=>$before, 'after'=>$this->fleetState($fleet)]);
            return true;
        });
    }

    private function authorize(Player $actor): void
    {
        $this->manager->lock($actor);
        if (!in_array('ROLE_ADMIN', $actor->getRoles(), true) || $actor->lockedAt !== null) throw new \DomainException('Droits administrateur requis.');
    }

    private function reason(string $reason): string
    {
        $reason = trim($reason);
        if ($reason === '' || mb_strlen($reason) > 1000) throw new \DomainException('Le motif est obligatoire et limité à 1 000 caractères.');
        return $reason;
    }

    private function fleetState(Fleet $fleet): array
    {
        return ['status'=>$fleet->status, 'departure_at'=>$fleet->departureAt?->format(DATE_ATOM), 'arrival_at'=>$fleet->arrivalAt?->format(DATE_ATOM), 'cargo'=>$fleet->cargo, 'reserved_fuel'=>$fleet->reservedFuel, 'orbit_fuel'=>$fleet->orbitFuel];
    }

    private function audit(Player $actor, string $action, string $type, int $id, string $reason, array $details): void
    {
        $entry = new AdminAuditLog();$entry->actor = $actor;$entry->action = $action;$entry->targetType = $type;$entry->targetId = $id;
        $entry->createdAt = $this->clock->now();$entry->details = ['reason'=>$reason] + $details;$this->em->persist($entry);
    }
}
