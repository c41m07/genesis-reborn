<?php

declare(strict_types=1);

namespace App\Service\Advent;

use App\Entity\AdventEdition;

final class AdventAdminCaptureStatus
{
    /** @return array{edition: AdventEdition, state: string} */
    public function forEdition(AdventEdition $edition, \DateTimeImmutable $now): array
    {
        // L’édition porte le relevé commun à tous les univers. Une fermeture locale ne
        // rend pas la reprise impossible si une autre fenêtre de cette saison est ouverte.
        $state = match (true) {
            $edition->plan !== 'consolidated' => 'legacy',
            $edition->cohortCapturedAt !== null => AdventCohortCapture::isLate($edition->startsAt, $edition->cohortCapturedAt) ? 'captured_late' : 'captured',
            $now >= $edition->closesAt => 'closed_missing',
            $now < $edition->startsAt => 'scheduled',
            AdventCohortCapture::isLate($edition->startsAt, $now) => 'missed',
            default => 'normal_window',
        };

        return ['edition' => $edition, 'state' => $state];
    }
}
