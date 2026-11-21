<?php

declare(strict_types=1);

namespace App\Service\PirateCampaign;

/** Calendrier théorique interne, non relié aux événements ou au dossier du joueur. */
final class PirateCombatSchedule
{
    // Même cadence que CombatEventService et REGLES : première action à +60 s, dix rounds au plus.
    public const ROUND_SECONDS = 60;
    public const MAX_ROUNDS = 10;

    /**
     * Reprend un résultat natif terminal déjà calculé côté serveur. Ne simule ni ne montre le résultat.
     * Les dates sont des échéances, pas des preuves de traitement ni de retour.
     * @param array{outcome:string, rounds_played:int} $result
     * @return array{rounds_due:list<\DateTimeImmutable>, resolution_due:\DateTimeImmutable, maximum_due:\DateTimeImmutable}|null
     */
    public function afterArrival(
        \DateTimeImmutable $arrivalAt,
        \DateTimeImmutable $at,
        array $result,
    ): ?array {
        if ($at < $arrivalAt) {
            return null;
        }

        $roundsPlayed = $result['rounds_played'] ?? null;
        if (!in_array($result['outcome'] ?? null, ['attacker_wins', 'defender_wins', 'draw'], true)
            || !is_int($roundsPlayed) || $roundsPlayed < 1 || $roundsPlayed > self::MAX_ROUNDS) {
            throw new \DomainException('Le calendrier exige un combat résolu en un à dix rounds.');
        }

        $due = [];
        // Addition de secondes absolues, même pendant un changement d'heure.
        for ($round = 1; $round <= $roundsPlayed; ++$round) {
            $due[] = $arrivalAt->setTimestamp($arrivalAt->getTimestamp() + $round * self::ROUND_SECONDS);
        }

        return [
            'rounds_due' => $due,
            'resolution_due' => $due[$roundsPlayed - 1],
            'maximum_due' => $arrivalAt->setTimestamp($arrivalAt->getTimestamp() + self::MAX_ROUNDS * self::ROUND_SECONDS),
        ];
    }
}
