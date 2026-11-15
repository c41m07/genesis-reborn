<?php

namespace App\Repository;

use App\Entity\{Planet, PlayerUniverse};
use Doctrine\DBAL\ArrayParameterType;
use Doctrine\ORM\EntityManagerInterface;
/** Résumés d’affichage ; les rapports détaillés restent des entités complètes. */
final class ReportSummaryRepository
{
    public function __construct(private EntityManagerInterface $em)
    {
    }

    public function combats(PlayerUniverse $viewer, array $ids): array
    {
        if ($ids === []) {
            return [];
        }
        $rows = $this->em->getConnection()->fetchAllAssociative(<<<'SQL'
        SELECT r.id, r.outcome, r.resolved_at, e.planet_id,
               COALESCE(JSON_LENGTH(r.snapshot, '$.participants'), 0) AS participant_count
        FROM combat_log r
        JOIN combat_event e ON e.id = r.combat_event_id
        JOIN planet p ON p.id = e.planet_id
        JOIN solar_system s ON s.id = p.solar_system_id
        JOIN galaxy g ON g.id = s.galaxy_id
        WHERE r.id IN (:ids) AND g.universe_id = :universe
          AND (p.owner_id = :viewer OR EXISTS (
              SELECT 1 FROM combat_participant cp WHERE cp.combat_event_id = e.id AND cp.player_universe_id = :viewer
          ))
        SQL, ['ids' => $ids, 'universe' => $viewer->universe->id, 'viewer' => $viewer->id], ['ids' => ArrayParameterType::INTEGER]);
        if ($rows === []) {
            return [];
        }
        $byId = $this->planets($viewer, array_column($rows, 'planet_id'));
        $summaries = [];
        foreach ($rows as $row) {
            $summaries[(int) $row['id']] = [
                'id' => (int) $row['id'],
                'outcome' => $row['outcome'],
                'resolvedAt' => new \DateTimeImmutable($row['resolved_at'], new \DateTimeZone('UTC')),
                'planet' => $byId[(int) $row['planet_id']],
                'participantCount' => (int) $row['participant_count'],
            ];
        }
        return $summaries;
    }

    public function espionage(PlayerUniverse $viewer, array $ids): array
    {
        if ($ids === []) {
            return [];
        }
        $rows = $this->em->createQueryBuilder()->select('r.id', 'r.createdAt', 'r.result', 'r.intelCategories', 'IDENTITY(r.target) AS planetId')->from(\App\Entity\EspionageReport::class, 'r')->join('r.sender', 'sender')->where('r.id IN (:ids)')->andWhere('sender = :viewer')->andWhere('sender.universe = :universe')->setParameter('ids', $ids)->setParameter('viewer', $viewer)->setParameter('universe', $viewer->universe)->getQuery()->getArrayResult();
        if ($rows === []) {
            return [];
        }
        $byPlanet = $this->planets($viewer, array_column($rows, 'planetId'));
        $summaries = [];
        foreach ($rows as $row) {
            if (isset($byPlanet[$row['planetId']])) {
                $summaries[$row['id']] = $row + ['target' => $byPlanet[$row['planetId']]];
            }
        }
        return $summaries;
    }

    public function transports(PlayerUniverse $viewer, array $ids): array
    {
        if ($ids === []) {
            return [];
        }
        $rows = $this->em->getConnection()->fetchAllAssociative(<<<'SQL'
        SELECT r.id, r.status, r.created_at,
               JSON_UNQUOTE(JSON_EXTRACT(r.snapshot, '$.sender')) AS sender_name,
               JSON_UNQUOTE(JSON_EXTRACT(r.snapshot, '$.recipient')) AS recipient_name,
               JSON_UNQUOTE(JSON_EXTRACT(r.snapshot, '$.destination')) AS destination
        FROM transport_report r
        JOIN player_universe sender ON sender.id = r.sender_id
        JOIN player_universe recipient ON recipient.id = r.recipient_id
        WHERE r.id IN (:ids) AND (r.sender_id = :viewer OR r.recipient_id = :viewer)
          AND sender.universe_id = :universe AND recipient.universe_id = :universe
        SQL, ['ids' => $ids, 'viewer' => $viewer->id, 'universe' => $viewer->universe->id], ['ids' => ArrayParameterType::INTEGER]);
        $summaries = [];
        foreach ($rows as $row) {
            $summaries[(int) $row['id']] = [
                'id' => (int) $row['id'],
                'status' => $row['status'],
                'createdAt' => new \DateTimeImmutable($row['created_at'], new \DateTimeZone('UTC')),
                'snapshot' => ['sender' => $row['sender_name'] ?? '', 'recipient' => $row['recipient_name'] ?? '', 'destination' => $row['destination'] ?? ''],
            ];
        }
        return $summaries;
    }

    private function planets(PlayerUniverse $viewer, array $ids): array
    {
        $planets = $this->em->createQueryBuilder()->select('planet', 'system', 'galaxy')->from(Planet::class, 'planet')->join('planet.solarSystem', 'system')->join('system.galaxy', 'galaxy')->where('planet.id IN (:ids)')->andWhere('galaxy.universe = :universe')->setParameter('ids', array_unique($ids))->setParameter('universe', $viewer->universe)->getQuery()->getResult();
        $byId = [];
        foreach ($planets as $planet) {
            $byId[$planet->id] = $planet;
        }
        return $byId;
    }
}
