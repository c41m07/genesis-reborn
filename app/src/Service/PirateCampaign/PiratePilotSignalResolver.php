<?php

declare(strict_types=1);

namespace App\Service\PirateCampaign;

use App\Entity\{PirateReconnaissance, Planet, PlayerDiscovery, PlayerUniverse, SolarSystem};
use App\Manager\GameManager;
use App\Repository\{GameRepository, PirateCampaignReadRepository};

/** Source bornée du signal pilote. Ne crée aucun signal persistant, système ou planète. */
final readonly class PiratePilotSignalResolver
{
    public function __construct(
        private GameRepository $repository,
        private GameManager $manager,
        private PirateCampaignReadRepository $campaigns,
        private PirateCampaignCatalogue $catalogue,
        private PiratePilotCampaignOrder $order,
    ) {
    }

    /**
     * Appeler uniquement après gardes pilote et verrou courant du compte, participation,
     * univers et colonie active issue du contexte serveur. L'ordinal ne peut choisir une autre mission.
     */
    public function resolveLocked(PlayerUniverse $owner, Planet $active, \DateTimeImmutable $at, int $ordinal = 1): ?PiratePilotSignal
    {
        $keys = $this->catalogue->keys();
        if ($ordinal < 1 || !isset($keys[$ordinal - 1])) {
            return null;
        }
        $rows = $this->campaigns->ownedRows($owner, $keys, $at);
        $choice = $this->order->choose($owner, $rows, $ordinal, $at);
        if ($choice === null) {
            return null;
        }
        $history = $this->repository->one(PirateReconnaissance::class, ['membership' => $owner, 'missionKey' => $choice['key']]);
        if ($choice['isNew'] !== ($history === null)) {
            // Une ligne ancienne, même improjetable sans profil, n'est jamais un nouveau signal.
            return null;
        }
        if ($history !== null) {
            $this->manager->lockCurrent($history);
            $system = $history->existingSystemFor($owner, $at);

            return $system === null ? null : $this->onSystem($owner, $active, $system, $history, $choice, $at, needsAnchor: true);
        }
        if ($choice['preceding'] !== null) {
            $prior = $choice['preceding']->existingSystemFor($owner, $at);
            $keep = $prior === null ? null : $this->onSystem($owner, $active, $prior, null, $choice, $at, needsAnchor: true);
            if ($keep !== null) {
                return $keep;
            }
        }

        // Le fallback ne change jamais une ligne existante et n'invente aucun point de carte.
        return $this->onSystem($owner, $active, $active->solarSystem, null, $choice, $at, needsAnchor: false);
    }

    private function onSystem(
        PlayerUniverse $owner,
        Planet $active,
        SolarSystem $system,
        ?PirateReconnaissance $history,
        array $choice,
        \DateTimeImmutable $at,
        bool $needsAnchor,
    ): ?PiratePilotSignal {
        if ($system->galaxy->id !== $active->solarSystem->galaxy->id) {
            return null; // Le trajet natif ne franchit pas de galaxie.
        }
        $discovery = $this->repository->one(PlayerDiscovery::class, ['playerUniverse' => $owner, 'solarSystem' => $system]);
        if ($discovery === null) {
            return null;
        }
        $this->manager->lockCurrent($discovery);
        $anchor = null;
        if ($needsAnchor) {
            $anchor = $active->solarSystem->id === $system->id ? $active
                : $this->repository->one(Planet::class, ['owner' => $owner, 'solarSystem' => $system]);
            if ($anchor !== null && $anchor !== $active) {
                $this->manager->lockCurrent($anchor);
            }
        }

        return PiratePilotSignal::fromEvidence($owner, $active, $system, $discovery, $history, $anchor, $at,
            $choice['ordinal'], $choice['key'], $choice['preceding'], $choice['progress']);
    }
}
