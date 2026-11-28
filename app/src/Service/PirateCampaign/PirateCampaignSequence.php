<?php

declare(strict_types=1);

namespace App\Service\PirateCampaign;

use App\Entity\{PirateAttackCampaign, PirateReconnaissance, PlayerUniverse};

/** Ordre de lecture privé. Aucun choix de site, ordre de flotte ou crédit. */
final readonly class PirateCampaignSequence
{
    public function __construct(
        private PirateCampaignCatalogue $catalogue,
        private PirateCampaignProgress $progress,
    ) {
    }

    /**
     * Les lignes sont chargées côté serveur pour la participation courante. L'appelant futur doit refaire
     * la vérification sous verrou avant toute commande : cette lecture n'attribue aucun droit de départ.
     *
     * @param iterable<array{reconnaissance:PirateReconnaissance, attack:?PirateAttackCampaign}> $rows
     * @return array{current_mission:?string, next_signal:?string, consultable:list<string>}|null
     */
    public function forViewer(PlayerUniverse $viewer, iterable $rows, \DateTimeImmutable $at, bool $editorial = false): ?array
    {
        if ($viewer->id === null || $viewer->player?->id === null
            || !$viewer->isPlayerControlled() || !$viewer->universe->isAccessible($at)) {
            return null;
        }

        $keys = $this->catalogue->keys();
        if ($editorial) {
            // Les missions publiées gardent leur propre suite après la première finale.
            $keys = array_merge($this->catalogue->campaignKeys(1), array_keys($viewer->universe->gameSettings['pirate_missions'] ?? []));
        }
        $known = array_fill_keys($keys, true);
        $proofs = [];
        foreach ($rows as $row) {
            $proof = $this->progress->orderingProofFor($viewer, $row['reconnaissance'], $row['attack'], $at);
            if ($proof === null || !isset($known[$proof['key']])) {
                continue;
            }

            $key = $proof['key'];
            if (isset($proofs[$key])) {
                // Deux lignes propriétaires pour une définition ne donnent pas un déblocage arbitraire.
                return null;
            }
            $proofs[$key] = $proof['won'];
        }

        // Un créneau ouvert après le départ d'une rencontre suivante ne redirige pas un dossier engagé.
        $lastStarted = -1;
        foreach ($keys as $index=>$key) {
            if (isset($proofs[$key])) $lastStarted = $index;
        }
        $consultable = [];
        foreach ($keys as $index=>$key) {
            $mission = $viewer->universe->gameSettings['pirate_missions'][$key] ?? null;
            if ($mission && !isset($proofs[$key])) {
                if ($index < $lastStarted) continue;
                if ($mission['ends'] && $at >= new \DateTimeImmutable($mission['ends'])) continue;
                if ($mission['starts'] && $at < new \DateTimeImmutable($mission['starts'])) continue;
            }
            if (isset($proofs[$key])) {
                $consultable[] = $key;
                if ($proofs[$key]) {
                    continue;
                }
            }

            // Un résultat isolé plus loin dans le catalogue ne permet pas de sauter un chapitre.
            return [
                'current_mission' => $key,
                'next_signal' => isset($proofs[$key]) ? null : $key,
                'consultable' => $consultable,
            ];
        }

        return ['current_mission' => null, 'next_signal' => null, 'consultable' => $consultable];
    }
}
