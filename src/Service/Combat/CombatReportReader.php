<?php
namespace App\Service\Combat;

use App\Entity\CombatLog;
use App\Entity\CombatParticipant;
use App\Entity\PlayerUniverse;
use App\Repository\GameRepository;

final class CombatReportReader
{
    public function __construct(private GameRepository $repo) {}

    public function list(PlayerUniverse $membership): array
    {
        return $this->repo->combatLogsFor($membership);
    }

    public function findVisible(PlayerUniverse $membership, int $id): ?CombatLog
    {
        $log = $this->repo->find(CombatLog::class, $id);
        if (!$log) {
            return null;
        }
        if ($log->combatEvent->planet->owner?->id === $membership->id) {
            return $log;
        }
        return $this->repo->one(CombatParticipant::class, [
            'combatEvent' => $log->combatEvent,
            'playerUniverse' => $membership,
        ]) ? $log : null;
    }
}
