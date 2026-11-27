<?php
namespace App\Service\Fleet;

use App\Entity\{Fleet,GuildHallConvoy,OutboxMessage};
use App\Manager\GameManager;
use App\Repository\GameRepository;
use Symfony\Component\Uid\Uuid;

final class FleetDispatch
{
    public function __construct(private GameManager $manager, private OrbitRequestState $orbits, private GameRepository $repo) {}

    /** L'appelant conserve la transaction et les verrous de la flotte. */
    public function schedule(Fleet $fleet, ?GuildHallConvoy $newConvoy = null): void
    {
        if ($fleet->arrivalAt === null) throw new \LogicException('Échéance de flotte absente.');
        $token = Uuid::v4()->toRfc4122();
        if ($fleet->mission === GuildHallConvoy::MISSION && $fleet->status === 'traveling') {
            $receipt = $newConvoy ?? $this->repo->one(GuildHallConvoy::class, ['fleet' => $fleet]);
            if (!$receipt instanceof GuildHallConvoy || $receipt->fleet !== $fleet) {
                throw new \LogicException('Reçu de départ du convoi de guilde absent.');
            }
            if ($newConvoy === null) {
                $this->manager->lockCurrent($receipt);
            }
            // Le message actif et son reçu doivent changer ensemble ; ne pas
            // réarmer le dépôt quand la mission part en retour ou est rappelée.
            $receipt->scheduleOutbound($fleet->dispatchToken, $token);
        }
        $this->orbits->reschedule($fleet);
        $fleet->dispatchToken = $token;
        $message = new OutboxMessage();$message->kind = 'fleet';$message->targetId = $fleet->id;
        $message->token = $fleet->dispatchToken;$message->dueAt = $fleet->arrivalAt;
        $this->manager->save($message);
    }
}
