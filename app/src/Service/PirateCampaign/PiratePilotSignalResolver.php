<?php

declare(strict_types=1);

namespace App\Service\PirateCampaign;

use App\Entity\{PirateReconnaissance, Planet, PlayerDiscovery, PlayerUniverse};
use App\Manager\GameManager;
use App\Repository\GameRepository;

/** Source bornée du premier signal pilote. Ne crée aucun signal persistant, système ou planète. */
final readonly class PiratePilotSignalResolver
{
    public function __construct(private GameRepository $repository, private GameManager $manager)
    {
    }

    /**
     * Appeler uniquement après gardes pilote et verrou courant du compte, participation,
     * univers et colonie active issue du contexte serveur. Aucune destination de requête.
     */
    public function resolveLocked(PlayerUniverse $owner, Planet $active, \DateTimeImmutable $at): ?PiratePilotSignal
    {
        $history = $this->repository->one(PirateReconnaissance::class, [
            'membership' => $owner, 'missionKey' => PiratePilotSignal::FIRST_KEY,
        ]);
        if ($history !== null) {
            // La présence de la ligne, pas sa projection, décide si c'est un premier départ.
            $this->manager->lockCurrent($history);
            $system = $history->existingSystemFor($owner, $at);
            if ($system === null) {
                return null;
            }
        } else {
            $system = $active->solarSystem;
        }
        $discovery = $this->repository->one(PlayerDiscovery::class, ['playerUniverse' => $owner, 'solarSystem' => $system]);
        if ($discovery === null) {
            return null;
        }
        $this->manager->lockCurrent($discovery);
        $anchor = null;
        if ($history !== null) {
            $anchor = $active->solarSystem->id === $system->id ? $active
                : $this->repository->one(Planet::class, ['owner' => $owner, 'solarSystem' => $system]);
            if ($anchor !== null && $anchor !== $active) {
                $this->manager->lockCurrent($anchor);
            }
        }

        return PiratePilotSignal::fromEvidence($owner, $active, $system, $discovery, $history, $anchor, $at);
    }
}
