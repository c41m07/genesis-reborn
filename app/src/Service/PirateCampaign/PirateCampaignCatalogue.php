<?php

declare(strict_types=1);

namespace App\Service\PirateCampaign;

use App\Entity\{PlayerUniverse, Universe};
use App\Service\Catalog\GameCatalog;
use App\Service\Combat\CombatUnitFactory;

/** Contenus de la V1. Ni l'ordre éditorial ni la préparation ne débloquent les rencontres. */
final class PirateCampaignCatalogue
{
    private const CHAPTER_TITLES = [
        1 => 'Les appels interrompus',
        2 => 'Les routes trompeuses',
        3 => 'La guerre des relais',
        4 => 'L’arsenal des exilés',
        5 => 'La nuit sur le réseau',
    ];

    private const DEFINITIONS = [
        'balise_17' => [
            'chapter' => 1,
            'title' => 'Le silence de Balise 17',
            'signal' => 'Ici Balise 17. Les pirates bloquent notre avant-poste et les navettes de ravitaillement ne passent plus. Nos communications sont coupées, mais la balise de secours émet encore.',
            'victory' => 'Ici Balise 17. Le blocus est levé et les navettes peuvent de nouveau passer.',
            'ships' => ['fighter' => ['basic' => 1]],
        ],
        'escorte_interceptee' => [
            'chapter' => 1,
            'title' => 'L’escorte interceptée',
            'signal' => 'Une escorte devait rouvrir la route de ravitaillement vers Balise 17. Elle ne répond plus. Les navettes attendent qu’un passage sûr soit rétabli.',
            'victory' => 'La route de ravitaillement est rouverte. Nous pouvons reprendre contact avec les navettes.',
            'ships' => ['fighter' => ['basic' => 2], 'interceptor' => ['basic' => 1]],
        ],
        'derniere_liaison' => [
            'chapter' => 1,
            'title' => 'La dernière liaison',
            'signal' => 'Le dernier relais tient encore le réseau civil, mais ses transmissions sont brouillées. Sans lui, les escortes ne peuvent plus coordonner leurs trajets.',
            'victory' => 'Le relais émet de nouveau. Les avant-postes peuvent se parler et les escortes reprendre leurs trajets.',
            'intel_tip' => 'Face à cette formation, les intercepteurs sont un bon contre. Envoyer seulement des chasseurs de patrouille est risqué.',
            'ships' => ['heavy_fighter' => ['basic' => 1], 'bomber' => ['basic' => 1]],
        ],
        'balises_volees' => [
            'chapter' => 2,
            'title' => 'Les balises volées',
            'signal' => 'Quelqu’un émet avec nos identifiants. Les navettes suivent les faux signaux et n’arrivent plus au dépôt.',
            'victory' => 'Le dépôt peut de nouveau guider les navettes. Nous révoquons les codes de secours volés.',
            'intel_tip' => 'Les corvettes résistent aux petits chasseurs. Songez à épauler vos patrouilleurs par des coques plus solides.',
            'ships' => ['corvette' => ['basic' => 1], 'fighter' => ['basic' => 2]],
        ],
        'porteurs_de_brume' => [
            'chapter' => 2,
            'title' => 'Porteurs de brume',
            'signal' => 'Des navettes disparaissent des relevés dès qu’elles approchent du relais. Les pilotes ont besoin d’un passage sûr.',
            'victory' => 'Les navettes reparaissent sur nos écrans. Nous reprenons le guidage depuis le relais.',
            'intel_tip' => 'L’écran adverse ne se résume pas à ses intercepteurs. Des patrouilleurs appuyés par des chasseurs lourds offrent une réponse plus variée.',
            'ships' => ['fighter' => ['basic' => 2], 'interceptor' => ['basic' => 2], 'stealth_fighter' => ['basic' => 1]],
        ],
        'refuge_sous_escorte' => [
            'chapter' => 2,
            'title' => 'Un refuge sous escorte',
            'signal' => 'Nous abritons les équipages évacués des navettes. La route jusqu’au refuge est coupée.',
            'victory' => 'La route est dégagée. Nous pouvons évacuer les blessés et ravitailler le refuge.',
            'intel_tip' => 'La frégate menace surtout les petites coques. Des chasseurs lourds peuvent la prendre à contre, avec un écran pour les épauler.',
            'ships' => ['frigate' => ['basic' => 1], 'interceptor' => ['basic' => 2]],
        ],
        'noeud_fausses_routes' => [
            'chapter' => 2,
            'title' => 'Le nœud des fausses routes',
            'signal' => 'Les cartes se contredisent : toutes les corrections conduisent les navettes vers le même secteur.',
            'victory' => 'Les coordonnées authentiques circulent de nouveau. Nous avons retrouvé une route sûre.',
            'intel_tip' => 'Une ligne de chasseurs lourds ne couvre pas toutes les menaces de ce rapport. Les corvettes peuvent chercher un appui différent plutôt que combattre seules.',
            'ships' => ['corvette' => ['basic' => 2], 'stealth_fighter' => ['basic' => 2]],
        ],
        'corridor_des_ombres' => [
            'chapter' => 2,
            'title' => 'Le corridor des ombres',
            'signal' => 'Le trafic se concentre dans un corridor entre deux relais. Aucun pilote civil ne veut être le prochain à s’y engager.',
            'victory' => 'Le corridor est dégagé. Les navettes peuvent de nouveau suivre la route balisée.',
            'intel_tip' => 'Préparez des réponses aux frégates plutôt qu’un seul écran d’intercepteurs.',
            'ships' => ['frigate' => ['basic' => 2], 'stealth_fighter' => ['basic' => 1], 'fighter' => ['basic' => 2]],
        ],
        'briseur_de_relais' => [
            'chapter' => 3,
            'title' => 'Le briseur de relais',
            'signal' => 'Un relais secondaire s’est tu. Son dernier paquet d’urgence indique où son équipe est bloquée.',
            'victory' => 'Le relais secondaire répond. Les équipes au sol ont repris contact.',
            'intel_tip' => 'Les chasseurs lourds peuvent menacer l’écran comme une partie de la ligne. Les bombardiers offrent un autre contre au destroyer.',
            'ships' => ['destroyer' => ['basic' => 1], 'frigate' => ['basic' => 1], 'fighter' => ['basic' => 2]],
        ],
        'couper_le_commandement' => [
            'chapter' => 3,
            'title' => 'Couper le commandement',
            'signal' => 'Les attaques semblent coordonnées depuis le même secteur. L’antenne civile tient encore et peut transmettre votre relevé.',
            'victory' => 'L’antenne transmet sans interruption. Les pirates ne tiennent plus ce nœud de commandement.',
            'intel_tip' => 'Un destroyer menace particulièrement un croiseur. Pensez aussi à la frégate du rapport : la flotte doit répondre aux deux menaces.',
            'ships' => ['cruiser' => ['basic' => 1], 'frigate' => ['basic' => 1]],
        ],
        'secours_en_tenaille' => [
            'chapter' => 3,
            'title' => 'Secours en tenaille',
            'signal' => 'Les pilotes de secours sont immobilisés entre les relais. Leurs appareils civils ne peuvent pas dégager seuls la route.',
            'victory' => 'L’itinéraire de secours est libre. Les pilotes peuvent rejoindre l’avant-poste.',
            'intel_tip' => 'Les bombardiers et les chasseurs lourds peuvent menacer les destroyers. La frégate peut toutefois punir les appareils légers envoyés seuls.',
            'ships' => ['destroyer' => ['basic' => 2], 'frigate' => ['basic' => 1], 'interceptor' => ['basic' => 2]],
        ],
        'cuirasse_de_la_passe' => [
            'chapter' => 3,
            'title' => 'Le passage scellé',
            'revealed_title' => 'Le cuirassé de la passe',
            'signal' => 'La passe entre les deux relais est fermée. Les navettes n’ont plus d’itinéraire de secours.',
            'victory' => 'La passe est rouverte. Les équipes civiles peuvent reprendre leurs trajets.',
            'intel_tip' => 'Le rôle anti-cuirassé n’est pas le même que le rôle anti-frégate. Prévoyez des contres pour l’écran autant que pour la coque principale.',
            'ships' => ['battleship' => ['basic' => 1], 'frigate' => ['basic' => 1], 'fighter' => ['basic' => 2]],
        ],
        'deux_croiseurs_du_verrou' => [
            'chapter' => 3,
            'title' => 'Le verrou du réseau',
            'revealed_title' => 'Les deux croiseurs du verrou',
            'signal' => 'Les relais secondaires fonctionnent, mais leurs messages ne franchissent plus ce point du réseau.',
            'victory' => 'Les messages passent de nouveau par le verrou. Nous pouvons coordonner les équipes de plusieurs relais.',
            'intel_tip' => 'Les croiseurs prennent les chasseurs lourds à contre. Les destroyers sont conçus pour menacer les croiseurs.',
            'ships' => ['cruiser' => ['basic' => 2], 'stealth_fighter' => ['basic' => 1], 'corvette' => ['basic' => 2]],
        ],
        'arsenal_des_exiles' => [
            'chapter' => 4,
            'title' => 'L’arsenal des exilés',
            'signal' => 'Les derniers relevés remontent jusqu’à un arsenal pirate. Les équipes civiles attendent que le secteur cesse d’alimenter le blocus.',
            'victory' => 'La flotte de garde est dispersée. Le relais peut signaler une route à l’écart de l’arsenal.',
            'intel_tip' => 'Des cuirassés et leur destroyer ne se contrent pas simplement par un miroir. Répartissez les rôles entre vos coques de ligne et votre soutien.',
            'ships' => ['battleship' => ['basic' => 2], 'destroyer' => ['basic' => 1], 'interceptor' => ['basic' => 2]],
        ],
        'signal_noye' => [
            'chapter' => 4,
            'title' => 'Le signal noyé',
            'signal' => 'Un poste civil émet encore, mais son message se perd dans le brouillage. Aucune navette ne reçoit son signal de retour.',
            'victory' => 'Nous captons à nouveau le poste. Les navettes peuvent recevoir ses indications.',
            'intel_tip' => 'Le rapport mêle des menaces légères et lourdes. Une seule ligne de croiseurs ne répond pas de la même façon à chaque écran.',
            'ships' => ['battleship' => ['basic' => 2], 'cruiser' => ['basic' => 1], 'frigate' => ['basic' => 2], 'stealth_fighter' => ['basic' => 2]],
        ],
        'bastion_des_faussaires' => [
            'chapter' => 4,
            'title' => 'Le bastion des faussaires',
            'signal' => 'Les codes civils continuent d’être copiés depuis un bastion. Les équipages refusent de suivre les dernières corrections de route.',
            'victory' => 'Nous avons repris le contrôle des codes authentiques. Les navettes vérifient leurs itinéraires avant de repartir.',
            'intel_tip' => 'Pour affronter plusieurs coques lourdes, associez des contres à la ligne et un soutien capable d’entamer l’écran.',
            'ships' => ['battleship' => ['basic' => 3], 'destroyer' => ['basic' => 2], 'frigate' => ['basic' => 2]],
        ],
        'premier_capital_noir' => [
            'chapter' => 5,
            'title' => 'L’armada du secteur noir',
            'revealed_title' => 'Le premier capital noir',
            'signal' => 'Les avant-postes captent un appel de détresse dans un secteur jusqu’ici silencieux. Le relais ne voit plus aucune route de sortie.',
            'victory' => 'La route de sortie tient de nouveau. Le relais confirme qu’il peut guider les appareils civils.',
            'intel_tip' => 'Le capital est fortement avantagé contre les croiseurs et cuirassés. Les destroyers et bombardiers peuvent le menacer, mais d’autres contres peuvent être utiles face à son écran.',
            'ships' => ['capital' => ['basic' => 1], 'battleship' => ['basic' => 1], 'cruiser' => ['basic' => 1], 'frigate' => ['basic' => 2], 'destroyer' => ['basic' => 1], 'corvette' => ['basic' => 1]],
        ],
        'cuirasse_et_son_ecran' => [
            'chapter' => 5,
            'title' => 'Derrière le brouillage',
            'revealed_title' => 'La cuirasse et son écran',
            'signal' => 'Un relais isolé ne voit plus ce qui approche au-delà du brouillage. Les navettes civiles attendent son signal.',
            'victory' => 'Le brouillage tombe. Le relais reprend contact avec les navettes en attente.',
            'intel_tip' => 'Les frégates de l’écran punissent les bombardiers seuls. Prévoyez de les contrer en même temps que la coque capitale.',
            'ships' => ['capital' => ['basic' => 1], 'battleship' => ['basic' => 1], 'frigate' => ['basic' => 4], 'stealth_fighter' => ['basic' => 2], 'destroyer' => ['basic' => 2]],
        ],
        'deux_ombres_sur_orbite' => [
            'chapter' => 5,
            'title' => 'Des ombres sur l’orbite',
            'revealed_title' => 'Deux ombres sur l’orbite',
            'signal' => 'Le réseau fonctionne jusqu’à l’avant-dernier relais. Après ce point, tous les appels civils restent sans réponse.',
            'victory' => 'L’avant-dernier relais reçoit de nouveau les appels. Il ne reste qu’une coupure sur le réseau.',
            'intel_tip' => 'Le rapport mêle des capitaux et des destroyers d’escorte. Ne préparez pas uniquement une réponse au navire principal.',
            'ships' => ['capital' => ['basic' => 2], 'frigate' => ['basic' => 2], 'destroyer' => ['basic' => 2]],
        ],
        'la_nuit_se_brise' => [
            'chapter' => 5,
            'title' => 'La nuit se brise',
            'signal' => 'Tous les relais sauf un répondent. Les pilotes civils attendent un dernier signal pour rouvrir les communications d’un bout à l’autre du réseau.',
            'victory' => 'Le dernier relais répond. Les avant-postes se parlent de nouveau et les routes civiles rouvrent.',
            'intel_tip' => 'La formation finale combine des capitaux, des coques de ligne et un écran. Comparez les contres contre chacun de ces rôles : le budget seul ne suffit pas.',
            'ships' => ['capital' => ['basic' => 2], 'battleship' => ['basic' => 1], 'cruiser' => ['basic' => 1], 'frigate' => ['basic' => 2], 'stealth_fighter' => ['basic' => 2]],
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

    /** @return list<array{title: string, keys: list<string>}> Sommaire narratif interne, pas un état de déblocage. */
    public function chapters(): array
    {
        $chapters = [];
        foreach (self::CHAPTER_TITLES as $number => $title) {
            $chapters[$number] = ['title' => $title, 'keys' => []];
        }
        foreach (self::DEFINITIONS as $key => $entry) {
            $chapters[$entry['chapter']]['keys'][] = $key;
        }

        return array_values($chapters);
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

        // Certains titres d'après rapport nomment une coque ou le nombre de menaces.
        // Avant le vrai renseignement, garder l'intitulé narratif neutre.
        $revealed = !in_array($moment, [PirateCampaignMoment::Signal, PirateCampaignMoment::ReconnaissanceEnRoute], true);
        $title = $revealed ? ($content['revealed_title'] ?? $content['title']) : $content['title'];

        return ['title' => $title, 'message' => $message, 'guidance' => $guidance];
    }

    private function definition(string $key): array
    {
        return self::DEFINITIONS[$key] ?? throw new \DomainException('Rencontre pirate inconnue.');
    }
}
