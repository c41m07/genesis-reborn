<?php
namespace App\Service\Admin;

use App\Entity\{AdminAuditLog, CombatLog, CombatParticipant, EspionageReport, Fleet, FleetMissionTrace, FleetShip, Player, TransportReport, Universe};
use App\Service\Catalog\GameCatalog;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Clock\ClockInterface;

final class AdminMissionMonitoring
{
    public const MISSIONS = ['explore'=>'Exploration', 'transport'=>'Transport', 'colonize'=>'Colonisation', 'attack'=>'Attaque', 'spy'=>'Espionnage', 'defend'=>'Défense alliée', 'orbit'=>'Stationnement en orbite', 'station'=>'Stationnement', 'conquer'=>'Conquête JcE'];
    public const STATUSES = ['traveling'=>'Aller', 'returning'=>'Retour', 'attacking'=>'Combat', 'defending'=>'Défense', 'orbiting'=>'Orbite', 'orbit_pending'=>'En attente d’accord', 'occupying'=>'Occupation JcE', 'station_waiting'=>'Stationnement en attente', 'completed'=>'Terminée'];
    private const ACTIVE = ['traveling', 'returning', 'attacking', 'defending', 'orbiting', 'orbit_pending', 'occupying', 'station_waiting'];

    public function __construct(private EntityManagerInterface $em, private GameCatalog $catalog, private ClockInterface $clock) {}

    public function search(array $filters, int $page): array
    {
        $query = $this->em->createQueryBuilder()->select('f', 'm', 'p', 'u', 'h', 'd', 's')
            ->from(Fleet::class, 'f')->join('f.playerUniverse', 'm')->leftJoin('m.player', 'p')->leftJoin('m.formerPlayer', 'former')
            ->join('m.universe', 'u')->join('f.homePlanet', 'h')->leftJoin('f.destination', 'd')->leftJoin('f.destinationSystem', 's')
            ->leftJoin('d.owner', 'targetMember')->leftJoin('targetMember.player', 'targetPlayer')
            ->where('f.mission <> :idle')->setParameter('idle', 'idle');
        foreach (['universe'=>'u.id', 'mission'=>'f.mission'] as $key=>$field) {
            if ($filters[$key] !== '' && $filters[$key] !== 0) $query->andWhere($field.' = :'.$key)->setParameter($key, $filters[$key]);
        }
        if ($filters['status'] === 'active') $query->andWhere('f.status IN (:active)')->setParameter('active', self::ACTIVE);
        elseif ($filters['status'] !== '') $query->andWhere('f.status = :status')->setParameter('status', $filters['status']);
        if ($filters['q'] !== '') {
            $query->andWhere('LOWER(p.username) LIKE :search OR LOWER(former.username) LIKE :search OR LOWER(h.name) LIKE :search OR LOWER(d.name) LIKE :search OR LOWER(s.name) LIKE :search OR LOWER(targetPlayer.username) LIKE :search')
                ->setParameter('search', '%'.mb_strtolower($filters['q']).'%');
        }
        $byStatus = (clone $query)->select('f.status AS status', 'COUNT(f.id) AS total')->groupBy('f.status')->getQuery()->getArrayResult();
        $counts = array_column($byStatus, 'total', 'status');
        $total = (int) array_sum($counts);
        $pages = max(1, (int) ceil($total / 50));
        $page = min($pages, max(1, $page));
        $fleets = $query->orderBy('f.id', 'DESC')->setFirstResult(($page - 1) * 50)->setMaxResults(50)->getQuery()->getResult();
        return [
            'fleets'=>$fleets, 'total'=>$total, 'page'=>$page, 'pages'=>$pages, 'filters'=>$filters,
            'universes'=>$this->em->getRepository(Universe::class)->findBy([], ['name'=>'ASC']),
            'missions'=>self::MISSIONS, 'statuses'=>self::STATUSES, 'captured_at'=>$this->clock->now(),
            'combat_events'=>$this->pendingCombats($fleets),
            'summary'=>[
                'active'=>(int) array_sum(array_intersect_key($counts, array_flip(self::ACTIVE))),
                'combat'=>(int) ($counts['attacking'] ?? 0) + (int) ($counts['defending'] ?? 0),
                'returning'=>(int) ($counts['returning'] ?? 0), 'waiting'=>(int) ($counts['orbit_pending'] ?? 0),
            ],
        ];
    }

    private function pendingCombats(array $fleets): array
    {
        if ($fleets === []) return [];
        $participants = $this->em->createQueryBuilder()->select('p', 'e')->from(CombatParticipant::class, 'p')
            ->join('p.combatEvent', 'e')->where('p.fleet IN (:fleets)')->andWhere('e.status = :status')
            ->setParameter('fleets', $fleets)->setParameter('status', 'pending')->orderBy('e.id', 'DESC')->getQuery()->getResult();
        $events = [];
        foreach ($participants as $participant) $events[$participant->fleet->id] ??= $participant->combatEvent;
        return $events;
    }

    public function inspect(Fleet $fleet): array
    {
        $definitions = $this->catalog->forUniverse($fleet->playerUniverse->universe)['catalog']['ships'];
        $units = [];
        foreach ($this->em->getRepository(FleetShip::class)->findBy(['fleet'=>$fleet], ['slug'=>'ASC', 'tier'=>'ASC']) as $ship) {
            if ($ship->quantity < 1) continue;
            $definition = $definitions[$ship->slug] ?? null;
            $units[] = ['label'=>$definition ? $this->catalog->withTier($definition, $ship->tier)['label'] : $ship->slug, 'tier'=>$ship->tier, 'quantity'=>$ship->quantity];
        }
        $combats = [];
        foreach ($this->em->getRepository(CombatParticipant::class)->findBy(['fleet'=>$fleet], ['id'=>'DESC'], 20) as $participant) {
            $combats[] = ['event'=>$participant->combatEvent, 'report'=>$this->em->getRepository(CombatLog::class)->findOneBy(['combatEvent'=>$participant->combatEvent])];
        }
        $pending = $this->pendingCombats([$fleet]);
        return [
            'fleet'=>$fleet, 'departure_trace'=>$this->em->getRepository(FleetMissionTrace::class)->findOneBy(['fleet'=>$fleet]), 'units'=>$units, 'combats'=>$combats, 'missions'=>self::MISSIONS, 'statuses'=>self::STATUSES,
            'captured_at'=>$this->clock->now(), 'deadline'=>$pending[$fleet->id]->resolveAt ?? $fleet->arrivalAt,
            'transport_report'=>$this->em->getRepository(TransportReport::class)->findOneBy(['fleet'=>$fleet]),
            'espionage_reports'=>$fleet->mission === 'spy' && $fleet->destination ? $this->em->getRepository(EspionageReport::class)->findBy(['sender'=>$fleet->playerUniverse, 'target'=>$fleet->destination], ['id'=>'DESC'], 10) : [],
            'resources'=>$this->catalog->rules()['resources'],
        ];
    }

    public function recordView(Player $actor, Fleet $fleet, string $type = 'fleet', ?int $id = null): void
    {
        $entry = new AdminAuditLog();
        $entry->actor = $actor;
        $entry->action = 'admin_'.$type.'_view';
        $entry->targetType = $type;
        $entry->targetId = $id ?? $fleet->id;
        $entry->details = ['fleet_id'=>$fleet->id, 'universe_id'=>$fleet->playerUniverse->universe->id];
        $entry->createdAt = $this->clock->now();
        $this->em->persist($entry);
        $this->em->flush();
    }
}
