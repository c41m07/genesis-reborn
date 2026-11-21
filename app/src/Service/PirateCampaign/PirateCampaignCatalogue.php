<?php

declare(strict_types=1);

namespace App\Service\PirateCampaign;

use App\Entity\{PlayerUniverse, Universe};
use App\Service\Catalog\GameCatalog;
use App\Service\Combat\CombatUnitFactory;

/** Contenus de la V1. Ni l'ordre éditorial ni la préparation ne débloquent les rencontres. */
final class PirateCampaignCatalogue
{
    private const DEFINITIONS = [
        'balise_17' => [
            'title' => 'Le silence de Balise 17',
            'signal' => 'Ici Balise 17. Les pirates bloquent notre avant-poste et les navettes de ravitaillement ne passent plus. Nos communications sont coupées, mais la balise de secours émet encore.',
            'victory' => 'Ici Balise 17. Le blocus est levé et les navettes peuvent de nouveau passer.',
            'ships' => ['fighter' => ['basic' => 1]],
        ],
        'escorte_interceptee' => [
            'title' => 'L’escorte interceptée',
            'signal' => 'Une escorte devait rouvrir la route de ravitaillement vers Balise 17. Elle ne répond plus. Les navettes attendent qu’un passage sûr soit rétabli.',
            'victory' => 'La route de ravitaillement est rouverte. Nous pouvons reprendre contact avec les navettes.',
            'ships' => ['fighter' => ['basic' => 2], 'interceptor' => ['basic' => 1]],
        ],
        'derniere_liaison' => [
            'title' => 'La dernière liaison',
            'signal' => 'Le dernier relais tient encore le réseau civil, mais ses transmissions sont brouillées. Sans lui, les escortes ne peuvent plus coordonner leurs trajets.',
            'victory' => 'Le relais émet de nouveau. Les avant-postes peuvent se parler et les escortes reprendre leurs trajets.',
            'intel_tip' => 'Face à cette formation, les intercepteurs sont un bon contre. Envoyer seulement des chasseurs de patrouille est risqué.',
            'ships' => ['heavy_fighter' => ['basic' => 1], 'bomber' => ['basic' => 1]],
        ],
    ];

    public function __construct(
        private readonly GameCatalog $catalog,
        private readonly CombatUnitFactory $units,
    ) {
    }

    /** @return list<string> Ordre narratif, pas liste des missions accessibles au joueur. */
    public function keys(): array
    {
        return array_keys(self::DEFINITIONS);
    }

    /**
     * Préparer une seule fois l'adversaire, le prix catalogue et les paramètres natifs déjà mesurés.
     * Ne définit ni génération de site, ni détection, cadence, conditions d'accès, ni carburant.
     */
    public function prepareForServer(string $key, Universe $universe): PiratePreparedEncounter
    {
        $composition = $this->definition($key)['ships'];
        $encounter = PirateConvoyQuote::captureCatalogue($this->catalog, $universe, $composition);
        $rules = $this->catalog->rules();
        $battle = PirateBattleScenario::freeze(
            $encounter,
            // Aucun niveau technologique pirate inventé : c'est le profil des simulations retenues.
            $this->units->ships($universe, $encounter->composition(), []),
            $this->catalog->all('combat_modifiers')['modifiers'],
            $rules['max_combat_rounds'],
            $rules['shield_recharge_percent_per_round'],
        );

        return new PiratePreparedEncounter($key, $encounter, $battle);
    }

    /**
     * Ne renvoyer que du texte ; l'adaptateur affichera les effectifs du véritable rapport après remise.
     * La valeur de moment vient d'une preuve d'état côté serveur, jamais d'un bouton ou d'un paramètre client.
     *
     * @return array{title: string, message: string, guidance: ?string}|null
     */
    public function narrativeFor(string $key, PirateDistressSite $site, PlayerUniverse $viewer, PirateCampaignMoment $moment): ?array
    {
        if ($site->signalFor($viewer) === null) {
            return null;
        }

        $content = $this->definition($key);
        [$message, $guidance] = match ($moment) {
            PirateCampaignMoment::Signal => [$content['signal'], 'Envoyez une sonde basique vers le poste civil. Elle sera perdue, mais son rapport révélera la flotte pirate à l’arrivée, même si la sonde est détectée.'],
            PirateCampaignMoment::ReconnaissanceEnRoute => ['La sonde est en route vers le poste civil.', 'Le renseignement sera disponible après son arrivée.'],
            PirateCampaignMoment::RenseignementRecu => ['La sonde a cessé d’émettre, mais son relevé est complet.', $content['intel_tip'] ?? 'Consultez le rapport pour préparer votre escorte.'],
            PirateCampaignMoment::AttaqueEngagee => ['Votre escorte est engagée. Le relais attend l’issue du combat.', null],
            PirateCampaignMoment::Defaite => ['Les pirates tiennent toujours le relais. Le dernier relevé reste valable.', 'Préparez une nouvelle tentative lorsque la précédente sera terminée. Vous n’avez pas besoin de renvoyer une sonde.'],
            PirateCampaignMoment::IssueIndecise => ['Le combat n’a pas levé le blocus. Le dernier relevé reste valable.', 'Vous pourrez retenter sans renvoyer de sonde après la fin de cette tentative.'],
            PirateCampaignMoment::RetourDesSurvivants => ['Les vaisseaux survivants sont en route vers leur colonie.', null],
            PirateCampaignMoment::Victoire => [$content['victory'], 'La victoire est confirmée. Le convoi de remerciement est une étape distincte : aucune ressource n’est encore livrée.'],
        };

        // Même la projection après renseignement n'inclut ni composition, ni coords, ni coût.
        return ['title' => $content['title'], 'message' => $message, 'guidance' => $guidance];
    }

    private function definition(string $key): array
    {
        return self::DEFINITIONS[$key] ?? throw new \DomainException('Rencontre pirate inconnue.');
    }
}
