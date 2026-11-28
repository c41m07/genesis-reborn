<?php

declare(strict_types=1);

namespace App\Service\Report;

use App\Entity\{CombatLog, CombatParticipant, EspionageReport, PlayerUniverse, ReportNotice, TransportReport};
use App\Manager\GameManager;

/** Enregistre la naissance d’un rapport dans la transaction de jeu qui le crée. */
final readonly class ReportNoticeWriter
{
    public function __construct(private GameManager $manager)
    {
    }

    /** @param list<CombatParticipant> $participants */
    public function combatCreated(CombatLog $report, array $participants): void
    {
        $planet = $report->combatEvent->planet;
        $readers = [$planet->owner];
        foreach ($participants as $participant) {
            $readers[] = $participant->playerUniverse;
        }
        $this->record($report, $planet->solarSystem->galaxy->universe->id, $readers);
    }

    public function espionageCreated(EspionageReport $report): void
    {
        $this->record($report, $report->sender->universe->id, [$report->sender, $report->detectedBy]);
    }

    public function transportCreated(TransportReport $report): void
    {
        $this->record($report, $report->sender->universe->id, [$report->sender, $report->recipient]);
    }

    /** @param list<?PlayerUniverse> $readers */
    private function record(CombatLog|EspionageReport|TransportReport $report, int $universeId, array $readers): void
    {
        $seen = [];
        foreach ($readers as $reader) {
            if ($reader?->id === null || !$reader->isPlayerControlled()
                || $reader->universe->id !== $universeId || isset($seen[$reader->id])) {
                continue;
            }
            $seen[$reader->id] = true;
            $this->manager->save(new ReportNotice($reader, $report));
        }
    }
}
