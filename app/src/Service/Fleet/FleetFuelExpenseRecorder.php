<?php

declare(strict_types=1);

namespace App\Service\Fleet;

use App\Entity\{Fleet, FleetFuelExpense};
use App\Manager\GameManager;
use Psr\Clock\ClockInterface;

final readonly class FleetFuelExpenseRecorder
{
    public function __construct(private GameManager $manager, private ClockInterface $clock)
    {
    }

    /** Sous verrou, dans la transaction de la transition ; ne pas appeler pour une simple réserve. */
    public function burnLocked(Fleet $fleet, float $requested, string $phase): void
    {
        $before = $fleet->cargo['hydrogen'] ?? 0;
        $after = max(0.0, $before - $requested);
        $fleet->cargo['hydrogen'] = $after;
        $burned = $before - $after;
        if ($burned <= 0 || !$fleet->playerUniverse->isPlayerControlled()) {
            return;
        }

        // Le jeton courant identifie ce trajet avant sa reprogrammation. Un retour ou un repli sur
        // la même flotte aura son propre jeton. Les gardes de la mission arrêtent les redélivrances.
        $expense = new FleetFuelExpense();
        $expense->fleetId = $fleet->id ?? throw new \LogicException('Flotte non enregistrée.');
        $expense->phase = $phase;
        $expense->transitionId = hash('sha256', $expense->fleetId . '|' . $fleet->dispatchToken . '|' . $phase);
        $expense->playerUniverse = $fleet->playerUniverse;
        $expense->hydrogen = $burned;
        $expense->burnedAt = $this->clock->now()->setTimezone(new \DateTimeZone('UTC'));
        $this->manager->save($expense);
    }
}
