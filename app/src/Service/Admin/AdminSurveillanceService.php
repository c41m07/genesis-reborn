<?php
namespace App\Service\Admin;

use App\Entity\{AdminAuditLog, Fleet, PlayerUniverse, Universe};
use Doctrine\ORM\EntityManagerInterface;
use Psr\Clock\ClockInterface;

final class AdminSurveillanceService
{
    public const DEFAULTS = ['late_minutes'=>5, 'transport_count'=>5, 'attack_count'=>10];
    public function __construct(private EntityManagerInterface $em, private ClockInterface $clock) {}

    public function settings(Universe $universe): array
    {
        return array_replace(self::DEFAULTS, array_intersect_key($universe->gameSettings['admin_surveillance'] ?? [], self::DEFAULTS));
    }

    public function alerts(Universe $universe): array
    {
        $settings=$this->settings($universe);$connection=$this->em->getConnection();$now=$this->clock->now()->setTimezone(new \DateTimeZone('UTC'));
        $cutoff=$now->modify('-24 hours')->format('Y-m-d H:i:s');$alerts=[];
        $late=$connection->fetchAllAssociative("SELECT f.id, COALESCE(MAX(e.resolve_at),f.arrival_at) AS deadline FROM fleet f INNER JOIN player_universe m ON m.id=f.player_universe_id LEFT JOIN combat_participant p ON p.fleet_id=f.id LEFT JOIN combat_event e ON e.id=p.combat_event_id AND e.status='pending' WHERE m.universe_id=:universe AND f.status IN ('traveling','returning','attacking','defending','orbiting','occupying') GROUP BY f.id,f.arrival_at HAVING deadline < :cutoff ORDER BY deadline LIMIT 100",['universe'=>$universe->id,'cutoff'=>$now->modify('-'.$settings['late_minutes'].' minutes')->format('Y-m-d H:i:s')]);
        foreach ($late as $row) {
            $fleet=$this->em->find(Fleet::class,(int)$row['id']);
            if (!$fleet) continue;
            $alerts[]=['key'=>'late:'.$fleet->id,'type'=>'Mission en retard','title'=>'Échéance dépassée · Flotte n° '.$fleet->id,
                'description'=>'Échéance attendue : '.$row['deadline'].' UTC. Vérifier le traitement avant toute conclusion.',
                'membership'=>$fleet->playerUniverse,'fleet'=>$fleet,'latest_at'=>new \DateTimeImmutable($row['deadline'],new \DateTimeZone('UTC'))];
        }
        $transfers=$connection->fetchAllAssociative("SELECT LEAST(sender_id,recipient_id) AS first_member,GREATEST(sender_id,recipient_id) AS second_member,COUNT(*) AS total,MAX(departed_at) AS latest_at FROM fleet_mission_trace WHERE universe_id=:universe AND mission='transport' AND departed_at>=:cutoff AND departed_at<=:now AND target_was_player=1 AND sender_id<>recipient_id GROUP BY first_member,second_member HAVING total>=:threshold ORDER BY latest_at DESC LIMIT 100",['universe'=>$universe->id,'cutoff'=>$cutoff,'now'=>$now->format('Y-m-d H:i:s'),'threshold'=>$settings['transport_count']]);
        foreach ($transfers as $row) {
            $first=$this->em->find(PlayerUniverse::class,(int)$row['first_member']);$second=$this->em->find(PlayerUniverse::class,(int)$row['second_member']);
            if (!$first || !$second) continue;
            $alerts[]=['key'=>'transfers:'.$universe->id.':'.$row['first_member'].':'.$row['second_member'],'type'=>'Transports répétés',
                'title'=>$row['total'].' transports en 24 h','description'=>$first->getDisplayName().' ↔ '.$second->getDisplayName().' ; les deux sens sont comptés.',
                'membership'=>$first,'fleet'=>null,'latest_at'=>new \DateTimeImmutable($row['latest_at'],new \DateTimeZone('UTC'))];
        }
        $attacks=$connection->fetchAllAssociative("SELECT recipient_id,COUNT(*) AS total,COUNT(DISTINCT sender_id) AS attackers,MAX(departed_at) AS latest_at FROM fleet_mission_trace WHERE universe_id=:universe AND mission='attack' AND departed_at>=:cutoff AND departed_at<=:now AND target_was_player=1 AND sender_id<>recipient_id GROUP BY recipient_id HAVING total>=:threshold ORDER BY latest_at DESC LIMIT 100",['universe'=>$universe->id,'cutoff'=>$cutoff,'now'=>$now->format('Y-m-d H:i:s'),'threshold'=>$settings['attack_count']]);
        foreach ($attacks as $row) {
            $target=$this->em->find(PlayerUniverse::class,(int)$row['recipient_id']);
            if (!$target) continue;
            $alerts[]=['key'=>'attacks:'.$universe->id.':'.$target->id,'type'=>'Attaques répétées','title'=>$row['total'].' attaques en 24 h',
                'description'=>$target->getDisplayName().' · '.$row['attackers'].' commandant(s) à l’origine des départs.',
                'membership'=>$target,'fleet'=>null,'latest_at'=>new \DateTimeImmutable($row['latest_at'],new \DateTimeZone('UTC'))];
        }
        return $alerts;
    }

    public function securityIncidents(): array
    {
        return $this->em->createQueryBuilder()->select('a')->from(AdminAuditLog::class,'a')->where('a.action = :action')
            ->andWhere('NOT EXISTS (SELECT r.id FROM '.AdminAuditLog::class.' r WHERE r.action = :review AND r.targetId = a.id)')
            ->setParameter('action','admin_access_denied')->setParameter('review','admin_access_reviewed')->orderBy('a.id','DESC')->setMaxResults(20)->getQuery()->getResult();
    }
}
