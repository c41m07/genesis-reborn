<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\ReportArchive;

final readonly class ReportArchiveRepository
{
    /**
     * Les expressions et le type sont des constantes de requête, jamais des entrées du joueur.
     * Appliquer avant le tri et la limite préserve les résultats suivants du sélecteur.
     */
    public static function notRemovedDql(string $reportId, string $kind, string $membership): string
    {
        return 'NOT EXISTS (SELECT removed.id FROM '.ReportArchive::class.' removed
            WHERE removed.playerUniverse = '.$membership." AND removed.kind = '".$kind."'
            AND removed.reportId = ".$reportId.' AND removed.removedAt IS NOT NULL)';
    }

    /** Les expressions et le type sont fixés par la requête appelante. */
    public static function notRemovedSql(string $reportId, string $kind, string $membership): string
    {
        return 'NOT EXISTS (SELECT 1 FROM report_archive removed
            WHERE removed.player_universe_id = '.$membership." AND removed.kind = '".$kind."'
            AND removed.report_id = ".$reportId.' AND removed.removed_at IS NOT NULL)';
    }
}
