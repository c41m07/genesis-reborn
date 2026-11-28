<?php

declare(strict_types=1);

namespace App\Service\PirateCampaign;

use App\Entity\Player;
use App\Repository\PirateCampaignReadRepository;
use Psr\Clock\ClockInterface;
use Symfony\Bundle\SecurityBundle\Security;

/** Seule entrée de lecture destinée au futur contrôleur, actuellement sans route. */
final readonly class PirateCampaignWorkspaceReader
{
    public function __construct(
        private Security $security,
        private ClockInterface $clock,
        private PirateCampaignReadRepository $repository,
        private PirateCampaignCatalogue $catalogue,
        private PirateCampaignWorkspace $workspace,
    ) {
    }

    /** Le numéro de lecture ne peut pas choisir un compte, un univers ou une participation. */
    public function current(?int $missionNumber = null): ?array
    {
        $account = $this->security->getUser();
        if (!$account instanceof Player || $account->id === null || $account->currentPlayerUniverse?->id === null) {
            return null;
        }

        // Pas d'attribut de suivi d'activité posé ici : c'est une consultation sans écriture de jeu.
        $at = $this->clock->now()->setTimezone(new \DateTimeZone('UTC'));
        $viewer = $this->repository->activeMembership($account->id, $account->currentPlayerUniverse->id, $at);
        if ($viewer === null) {
            return null;
        }
        $rows = $this->repository->ownedRows($viewer, $this->catalogue->keys($viewer->universe), $at);

        return $this->workspace->forViewer($viewer, $rows, $at, $missionNumber);
    }
}
