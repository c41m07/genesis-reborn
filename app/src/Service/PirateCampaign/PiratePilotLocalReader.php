<?php

declare(strict_types=1);

namespace App\Service\PirateCampaign;

use App\Entity\Player;
use App\Repository\{PirateCampaignReadRepository, PirateConvoyReadRepository};
use Psr\Clock\ClockInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\DependencyInjection\Attribute\When;

/** Lecture privée du pilote, sans présence ni écriture de jeu. */
#[When(env: 'dev')]
#[When(env: 'prod')]
final readonly class PiratePilotLocalReader
{
    public function __construct(
        private Security $security,
        private ClockInterface $clock,
        private PiratePilotGate $gate,
        private PirateCampaignReadRepository $repository,
        private PirateCampaignCatalogue $catalogue,
        private PirateCampaignWorkspace $workspace,
        private PirateConvoyReadRepository $convoys,
        private PirateConvoyDossierProjection $convoyProjection,
    ) {
    }

    public function dossier(?int $missionNumber = null): ?array
    {
        if (!$this->gate->isAvailable()) {
            return null;
        }
        $account = $this->security->getUser();
        if (!$account instanceof Player || $account->id === null || $account->currentPlayerUniverse?->id === null) {
            return null;
        }

        // La relecture porte sur la sélection authentifiée, jamais sur le numéro de la page.
        // Ne pas appeler UniverseContext : la route n'arme pas le suivi de présence.
        $at = $this->clock->now()->setTimezone(new \DateTimeZone('UTC'));
        $viewer = $this->repository->activeMembership($account->id, $account->currentPlayerUniverse->id, $at);
        if ($viewer === null || !$this->gate->admits($account, $viewer, $at)) {
            return null;
        }

        // Les requêtes de détail revérifient la possession, la sélection et l'univers frais.
        $keys = $this->catalogue->keys();
        $rows = $this->repository->ownedRows($viewer, $keys, $at);
        $dossier = $this->workspace->forViewer($viewer, $rows, $at, $missionNumber);
        if ($dossier === null) {
            return null;
        }

        if (isset($dossier['briefing']['losses'], $dossier['briefing']['recon_report_id'])) {
            $dossier['briefing']['combat_report_id'] = $this->repository->lastCombatReport($viewer, $dossier['briefing']['recon_report_id']);
        }

        // Un droit se lit seulement après autorisation du dossier ; ne pas en inférer une victoire.
        $visible = $this->convoyProjection->visibleKeys($dossier, $keys);
        $rights = $this->convoys->forOwnedDossier($viewer, $visible, $at);

        return $dossier + ['convoy' => $this->convoyProjection->forDossier($viewer, $dossier, $keys, $rights, $at)];
    }
}
