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
        return $report && $report->sender->id === $membership->id ? $report : null;
    }
}
