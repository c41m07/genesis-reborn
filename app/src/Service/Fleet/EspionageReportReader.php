<?php
namespace App\Service\Fleet;
use App\Entity\{EspionageReport, PlayerUniverse};
use App\Repository\GameRepository;
final class EspionageReportReader
{
    public function __construct(private GameRepository $repo) {}
    public function list(PlayerUniverse $membership): array { return $this->repo->espionageReportsFor($membership); }
    public function findVisible(PlayerUniverse $membership, int $id): ?EspionageReport
    {
        $report = $this->repo->find(EspionageReport::class, $id);
        return $report && $membership->isPlayerControlled() && $report->sender->universe->id === $membership->universe->id
            && $report->target->solarSystem->galaxy->universe->id === $membership->universe->id
            && ($report->sender->id === $membership->id || ($report->result === 'detected' && $report->detectedBy?->id === $membership->id))
            && !$this->repo->isReportRemoved($membership, 'espionage', $id) ? $report : null;
    }
}
