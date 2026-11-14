<?php
namespace App\Service\Fleet;

use App\Entity\{Fleet,OutboxMessage};
use App\Manager\GameManager;
use Symfony\Component\Uid\Uuid;

final class FleetDispatch
{
    public function __construct(private GameManager $manager, private OrbitRequestState $orbits) {}

    /** L'appelant conserve la transaction et les verrous de la flotte. */
    public function schedule(Fleet $fleet): void
    {
        if ($fleet->arrivalAt === null) throw new \LogicException('Échéance de flotte absente.');
        $this->orbits->reschedule($fleet);
        $fleet->dispatchToken = Uuid::v4()->toRfc4122();
        $message = new OutboxMessage();$message->kind = 'fleet';$message->targetId = $fleet->id;
        $message->token = $fleet->dispatchToken;$message->dueAt = $fleet->arrivalAt;
        $this->manager->save($message);
    }
}
