<?php

namespace App\Security;

/** Capacités raccordées ; cette liste ne constitue pas un écran d'attribution. */
final class AdminCapability
{
    public const PLAYER_VIEW = 'ADMIN_CAPABILITY_PLAYER_VIEW';
    public const RESOURCES_ADJUST = 'ADMIN_CAPABILITY_RESOURCES_ADJUST';
    public const FLEET_INTERVENE = 'ADMIN_CAPABILITY_FLEET_INTERVENE';
    public const SHIPS_GRANT = 'ADMIN_CAPABILITY_SHIPS_GRANT';

    /** @return list<string> */
    public static function all(): array
    {
        return array_keys(self::definitions());
    }

    /**
     * La même liste fermée alimente la politique et sa présentation : une ligne
     * descriptive ne doit jamais créer une permission supplémentaire par erreur.
     *
     * @return array<string, array{label: string, effect: string, scope: string, safeguards: string}>
     */
    public static function definitions(): array
    {
        return [
            self::PLAYER_VIEW => [
                'label' => 'Consulter une fiche joueur',
                'effect' => 'Consultation journalisée',
                'scope' => 'Compte et participations aux univers',
                'safeguards' => 'Administrateur actif ; consultation inscrite au journal.',
            ],
            self::RESOURCES_ADJUST => [
                'label' => 'Corriger les ressources',
                'effect' => 'Ajout ou retrait',
                'scope' => 'Colonie du joueur sélectionné',
                'safeguards' => 'Acteur revérifié sous verrou, propriétaire contrôlé, capacité de stockage, motif et clé anti-double envoi.',
            ],
            self::SHIPS_GRANT => [
                'label' => 'Attribuer des vaisseaux',
                'effect' => 'Ajout uniquement',
                'scope' => 'Colonie du joueur sélectionné et catalogue de son univers',
                'safeguards' => 'Acteur revérifié sous verrou, propriétaire contrôlé, quantité bornée, motif et clé anti-double envoi.',
            ],
            self::FLEET_INTERVENE => [
                'label' => 'Intervenir sur une flotte',
                'effect' => 'Échéance, rappel ou retour forcé éligible',
                'scope' => 'Flotte sélectionnée dans son empire',
                'safeguards' => 'Acteur revérifié sous verrou, version de mission, protection des combats, motif et clé anti-double envoi.',
            ],
        ];
    }
}
