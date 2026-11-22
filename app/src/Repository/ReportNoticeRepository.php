<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\PlayerUniverse;
use Doctrine\ORM\EntityManagerInterface;

final readonly class ReportNoticeRepository
{
    private const ACTIVE = <<<'SQL'
        n.player_universe_id = :member AND EXISTS (
            SELECT 1 FROM player_universe m JOIN player account_owner ON account_owner.id = m.player_id
            WHERE m.id = n.player_universe_id AND m.universe_id = :universe AND m.forgotten_at IS NULL
              AND account_owner.current_player_universe_id = m.id AND account_owner.locked_at IS NULL
        )
        SQL;

    /** Les prédicats de visibilité repris des fiches s’appliquent aussi à tout marqueur privé. */
    private const VISIBLE = [
        'combat' => <<<'SQL'
            EXISTS (
                SELECT 1 FROM combat_log report JOIN combat_event ce ON ce.id = report.combat_event_id
                JOIN planet p ON p.id = ce.planet_id JOIN solar_system s ON s.id = p.solar_system_id
                JOIN galaxy g ON g.id = s.galaxy_id
                WHERE report.id = n.combat_log_id AND g.universe_id = :universe
                  AND (p.owner_id = n.player_universe_id OR EXISTS (
                    SELECT 1 FROM combat_participant participant WHERE participant.combat_event_id = ce.id
                      AND participant.player_universe_id = n.player_universe_id
                  ))
            )
            SQL,
        'espionage' => <<<'SQL'
            EXISTS (SELECT 1 FROM espionage_report report WHERE report.id = n.espionage_report_id
                AND report.sender_id = n.player_universe_id)
            SQL,
        'transport' => <<<'SQL'
            EXISTS (SELECT 1 FROM transport_report report WHERE report.id = n.transport_report_id
                AND (report.sender_id = n.player_universe_id OR report.recipient_id = n.player_universe_id))
            SQL,
    ];

    private const COLUMNS = ['combat' => 'combat_log_id', 'espionage' => 'espionage_report_id', 'transport' => 'transport_report_id'];

    public function __construct(private EntityManagerInterface $em)
    {
    }

    public function count(PlayerUniverse $membership): int
    {
        return (int) $this->em->getConnection()->fetchOne(
            'SELECT COUNT(*) FROM report_notice n WHERE n.consulted_at IS NULL AND '.self::ACTIVE
                .' AND ('.implode(' OR ', self::VISIBLE).')',
            $this->parameters($membership),
        );
    }

    public function unread(PlayerUniverse $membership, string $kind, int $id): bool
    {
        return $this->available($membership, $kind, $id, unreadOnly: true);
    }

    public function consult(PlayerUniverse $membership, string $kind, int $id, \DateTimeImmutable $now): bool
    {
        $criteria = $this->identityAndAccess($kind);
        $parameters = $this->parameters($membership) + ['report' => $id];
        // Rien n’est créé ici : même en cas de double consultation, la date originale reste.
        $changed = $this->em->getConnection()->executeStatement(
            'UPDATE report_notice n SET n.consulted_at = :consulted WHERE n.consulted_at IS NULL AND '.$criteria,
            $parameters + ['consulted' => $now->format('Y-m-d H:i:s')],
        );
        return $changed > 0 || $this->available($membership, $kind, $id, unreadOnly: false);
    }

    private function available(PlayerUniverse $membership, string $kind, int $id, bool $unreadOnly): bool
    {
        return (bool) $this->em->getConnection()->fetchOne(
            'SELECT 1 FROM report_notice n WHERE '.$this->identityAndAccess($kind)
                .($unreadOnly ? ' AND n.consulted_at IS NULL' : '').' LIMIT 1',
            $this->parameters($membership) + ['report' => $id],
        );
    }

    private function identityAndAccess(string $kind): string
    {
        $column = self::COLUMNS[$kind] ?? throw new \InvalidArgumentException('Catégorie de rapport inconnue.');
        return self::ACTIVE.' AND n.'.$column.' = :report AND '.self::VISIBLE[$kind];
    }

    /** @return array{member: int, universe: int} */
    private function parameters(PlayerUniverse $membership): array
    {
        return ['member' => $membership->id, 'universe' => $membership->universe->id];
    }
}
