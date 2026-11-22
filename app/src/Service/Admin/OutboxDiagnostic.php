<?php

declare(strict_types=1);

namespace App\Service\Admin;

/** Interprète une ligne d'outbox sans lire la cible ni déclencher son traitement. */
final class OutboxDiagnostic
{
    private const KINDS = [
        'queue', 'fleet', 'combat', 'universe', 'notification',
        'guild_project', 'occupation', 'reconstruction',
    ];

    /** @return array{state: string, label: string, guidance: string, fleet_link: bool} */
    public static function describe(string $kind, bool $due): array
    {
        // Une nature inconnue échoue dans le relais actuel : ne jamais proposer
        // de la rejouer en la convertissant arbitrairement vers une autre nature.
        if (!in_array($kind, self::KINDS, true)) {
            return [
                'state' => 'unsupported',
                'label' => 'Examen humain requis',
                'guidance' => 'Nature non prise en charge : vérifier son origine et les journaux du relais. Aucune reprise proposée.',
                'fleet_link' => false,
            ];
        }

        if (!$due) {
            return [
                'state' => 'future',
                'label' => 'Échéance future',
                'guidance' => 'La publication peut précéder l’échéance ; Messenger porte le délai des événements. La notification suit son circuit séparé.',
                'fleet_link' => $kind === 'fleet',
            ];
        }

        return [
            'state' => 'due',
            'label' => 'Publication à vérifier',
            'guidance' => 'Comparer les signaux et journaux du relais concerné, puis contrôler le résultat métier. Une échéance atteinte ne prouve pas un échec.',
            'fleet_link' => $kind === 'fleet',
        ];
    }
}
