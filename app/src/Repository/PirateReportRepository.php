<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\{PirateMissionReport, PlayerUniverse};
use App\Service\PirateCampaign\PiratePilotGate;
use Doctrine\DBAL\ArrayParameterType;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Clock\ClockInterface;

final readonly class PirateReportRepository
{
    // L’identité de l’auteur du départ est figée sur la reconnaissance. Un changement
    // de commandant ou de galaxie ne doit pas transmettre les anciens renseignements.
    public const SOURCE = ' FROM pirate_mission_report r JOIN pirate_reconnaissance scout ON scout.id=r.reconnaissance_id
        JOIN player_universe seat ON seat.id=scout.membership_id JOIN player account ON account.id=scout.owner_id
        JOIN solar_system site ON site.id=scout.system_id JOIN galaxy region ON region.id=site.galaxy_id ';
    public const OWNER = 'seat.player_id=account.id AND account.current_player_universe_id=seat.id
        AND region.universe_id=seat.universe_id';

    public function __construct(private EntityManagerInterface $em, private PiratePilotGate $gate, private ClockInterface $clock)
    {
    }

    public function admitted(PlayerUniverse $member): bool
    {
        return $this->gate->admits($member->player, $member, $this->clock->now());
    }

    public function visible(PlayerUniverse $member, int $id): ?PirateMissionReport
    {
        if (!$this->admitted($member)) {
            return null;
        }
        $found = $this->em->getConnection()->fetchOne('SELECT r.id'.self::SOURCE.' WHERE '.self::OWNER.'
            AND seat.id=? AND seat.universe_id=? AND r.id=? AND '.
            ReportArchiveRepository::notRemovedSql('r.id', 'campaign', 'seat.id'), [$member->id, $member->universe->id, $id]);

        return $found ? $this->em->find(PirateMissionReport::class, $id) : null;
    }

    public function summaries(PlayerUniverse $member, array $ids): array
    {
        if (!$this->admitted($member) || $ids === []) {
            return [];
        }

        // Les rounds et les contenus de boîtes restent réservés à la fiche ouverte.
        return $this->em->getConnection()->fetchAllAssociativeIndexed(
            'SELECT r.id, r.category, r.location, r.status, r.created_at'.self::SOURCE.' WHERE '.self::OWNER.'
            AND seat.id=:member AND seat.universe_id=:universe AND r.id IN (:ids)',
            ['member' => $member->id, 'universe' => $member->universe->id, 'ids' => $ids], ['ids' => ArrayParameterType::INTEGER]
        );
    }
}
