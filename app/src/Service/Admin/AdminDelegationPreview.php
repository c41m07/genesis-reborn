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

    public const OBSERVER_SCENARIOS = [
        'active' => 'Droit actif',
        'expired' => 'Droit expiré',
        'revoked' => 'Droit révoqué',
        'outside' => 'Hors périmètre',
    ];

    /** @return array{decision: string, detail: string, remaining_minutes: int, status: ?string}|null */
    public static function observeFixture(string $scope, string $scenario): ?array
    {
        if (!isset(self::SAMPLES[$scope], self::OBSERVER_SCENARIOS[$scenario])) {
            return null;
        }

        // Horloge et droit d’exemple : aucun acteur, compte ni univers réel
        // n’est pris depuis la session ou le catalogue.
        $now = 15;
        $grant = [
            'scope' => $scenario === 'outside' ? ($scope === 'a' ? 'b' : 'a') : $scope,
            'start' => 0,
            'end' => $scenario === 'expired' ? $now : 30,
            'revoked' => $scenario === 'revoked',
        ];
        $decision = match (true) {
            $grant['scope'] !== $scope => 'outside',
            $grant['revoked'] => 'revoked',
            $now < $grant['start'] || $now >= $grant['end'] => 'expired',
            default => 'allowed',
        };

        // Sur un refus, ne rien projeter du ticket ni de l’autre univers.
        return [
            'decision' => $decision,
            'detail' => match ($decision) {
                'outside' => 'Le droit d’exemple ne couvre pas cet univers. Aucun statut affiché.',
                'revoked' => 'Le droit d’exemple a été révoqué. Aucun statut affiché.',
                'expired' => 'La durée du droit d’exemple est écoulée. Aucun statut affiché.',
                default => 'Le droit d’exemple est actif dans cet univers. Seul le statut est affiché.',
            },
            'remaining_minutes' => $decision === 'allowed' ? $grant['end'] - $now : 0,
            'status' => $decision === 'allowed' ? ($scope === 'a' ? 'En attente' : 'Résolu') : null,
        ];
    }
}
