<?php

declare(strict_types=1);

namespace App\Service\Admin;

/** Échantillons fermés, sans lien avec les entités, la base ou le voter. */
final class AdminDelegationPreview
{
    private const SAMPLES = [
        'a' => ['name' => 'Atlas (fictif)', 'support' => 'Ticket fictif SUP-A17 · en attente', 'moderation' => 'Signalement fictif MOD-A23 · à examiner', 'operations' => 'File fictive OPS-A31 · échéance dépassée'],
        'b' => ['name' => 'Boréal (fictif)', 'support' => 'Ticket fictif SUP-B62 · résolu', 'moderation' => 'Signalement fictif MOD-B73 · classé', 'operations' => 'File fictive OPS-B89 · échéance future'],
    ];

    /** @return array{name: string, profiles: list<array{label: string, example: string, excluded: string}>}|null */
    public static function project(string $scope): ?array
    {
        $sample = self::SAMPLES[$scope] ?? null;
        if ($sample === null) {
            return null;
        }

        // Construire une liste fermée de champs : ne jamais transmettre les
        // échantillons bruts ni s’appuyer sur la navigation pour les isoler.
        return [
            'name' => $sample['name'],
            'profiles' => [
                ['label' => 'Support', 'example' => $sample['support'], 'excluded' => 'Adresse, identité, liens d’activation, secrets, état de sécurité du compte.'],
                ['label' => 'Modération', 'example' => $sample['moderation'], 'excluded' => 'Auteurs, contenu des messages, éléments de preuve, décision ou sanction.'],
                ['label' => 'Exploitation', 'example' => $sample['operations'], 'excluded' => 'Données des joueurs, jetons, charge utile, détails réseau, reprise de traitement.'],
            ],
        ];
    }
}
