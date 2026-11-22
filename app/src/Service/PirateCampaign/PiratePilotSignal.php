<?php

declare(strict_types=1);

namespace App\Service\PirateCampaign;

use App\Entity\{PirateReconnaissance, Planet, PlayerDiscovery, PlayerUniverse, SolarSystem};
use Symfony\Component\DependencyInjection\Attribute\Exclude;

/** Résultat interne seulement ; ni choix de joueur ni coordonnée à projeter. */
#[Exclude]
final readonly class PiratePilotSignal
{
    public const FIRST_KEY = 'balise_17';

    private function __construct(
        public SolarSystem $system,
        public PirateDistressSite $site,
        public bool $allowsFirstScout,
        public int $ordinal,
        public string $key,
        public string $progress,
    ) {
    }

    /** Preuves chargées par le résolveur serveur, dans le contexte propriétaire de départ. */
    public static function fromEvidence(
        PlayerUniverse $owner,
        Planet $active,
        SolarSystem $selected,
        ?PlayerDiscovery $discovery,
        ?PirateReconnaissance $existing,
        ?Planet $ownedAnchor,
        \DateTimeImmutable $at,
        int $ordinal = 1,
        string $key = self::FIRST_KEY,
        ?PirateReconnaissance $preceding = null,
        string $progress = '',
    ): ?self {
        if ($ordinal < 1 || preg_match('/^[a-z0-9][a-z0-9_-]{0,63}$/D', $key) !== 1
            || ($ordinal === 1 && $key !== self::FIRST_KEY)
            || $owner->id === null || $owner->player?->id === null || !$owner->isPlayerControlled()
            || !$owner->universe->isAccessible($at) || !self::owned($owner, $active)
            || $selected->id === null || $active->solarSystem->galaxy->id !== $selected->galaxy->id
            || $selected->galaxy->universe->id !== $owner->universe->id || $discovery === null || $discovery->id === null
            || $discovery->playerUniverse->id !== $owner->id || $discovery->solarSystem->id !== $selected->id
            || $discovery->solarSystem->galaxy->universe->id !== $owner->universe->id) {
            return null;
        }
        if ($existing === null) {
            if ($selected->id !== $active->solarSystem->id
                && ($ordinal === 1 || $preceding?->existingSystemFor($owner, $at)?->id !== $selected->id
                    || $ownedAnchor === null || !self::owned($owner, $ownedAnchor) || $ownedAnchor->solarSystem->id !== $selected->id)) {
                return null;
            }

            return new self($selected, PirateDistressSite::inExistingSystem($owner, $selected), true, $ordinal, $key, $progress);
        }
        // Une ligne historique qui ne peut pas être projetée, même sans rapport exploitable, ne doit
        // jamais redevenir un nouvel ordre ni changer de système au gré de la colonie courante.
        $context = $existing->narrativeContextFor($owner, $at);
        if ($existing->existingSystemFor($owner, $at)?->id !== $selected->id
            || ($context['key'] ?? null) !== $key || !$context['site']->matchesSystem($selected)
            || $ownedAnchor === null || !self::owned($owner, $ownedAnchor) || $ownedAnchor->solarSystem->id !== $selected->id) {
            return null;
        }

        return new self($selected, $context['site'], false, $ordinal, $key, $progress);
    }

    private static function owned(PlayerUniverse $owner, Planet $planet): bool
    {
        return $planet->id !== null && $planet->owner?->id === $owner->id
            && $planet->owner->player?->id === $owner->player->id
            && $planet->solarSystem->galaxy->universe->id === $owner->universe->id;
    }
}
