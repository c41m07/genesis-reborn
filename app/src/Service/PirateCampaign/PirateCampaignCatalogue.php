<?php

declare(strict_types=1);

namespace App\Service\PirateCampaign;

use App\Entity\{PlayerUniverse, Universe};
use App\Service\Catalog\GameCatalog;
use App\Service\Combat\CombatUnitFactory;

/** Contenus de la V1. Ni l'ordre éditorial ni la préparation ne débloquent les rencontres. */
final class PirateCampaignCatalogue
{
    private const TIER_ROUTE_SPAN = 100_000_000;

    public const FINAL_MISSION = 'la_nuit_se_brise';

    private const CHAPTER_TITLES = [
        1 => 'Les appels interrompus',
        2 => 'Les routes trompeuses',
        3 => 'La guerre des relais',
        4 => 'L’arsenal des exilés',
        5 => 'La nuit sur le réseau',
    ];

    private const LIGHT_FLEET = ['fighter' => ['basic' => 120], 'interceptor' => ['basic' => 100], 'stealth_fighter' => ['basic' => 80]];
    private const BOMBER_FLEET = self::LIGHT_FLEET + ['bomber' => ['basic' => 60]];
    private const ESCORT_FLEET = self::BOMBER_FLEET + ['corvette' => ['intermediate' => 15], 'frigate' => ['intermediate' => 30], 'destroyer' => ['intermediate' => 18]];
    private const HEAVY_FLEET = self::ESCORT_FLEET + ['heavy_fighter' => ['intermediate' => 60], 'cruiser' => ['intermediate' => 12], 'battleship' => ['intermediate' => 9]];

    private const DEFINITIONS = [
        'balise_17' => [
            'chapter' => 1,
            'title' => 'Le silence de Balise 17',
            'signal' => 'Ici Balise 17. Les pirates bloquent notre avant-poste et les navettes de ravitaillement ne passent plus. Nos communications sont coupées, mais la balise de secours émet encore.',
            'victory' => 'Ici Balise 17. Le blocus est levé et les navettes peuvent de nouveau passer.',
            'ships' => ['fighter' => ['basic' => 12], 'interceptor' => ['basic' => 10], 'stealth_fighter' => ['basic' => 8]],
        ],
        'escorte_interceptee' => [
            'chapter' => 1,
            'title' => 'L’escorte interceptée',
            'signal' => 'Une escorte devait rouvrir la route de ravitaillement vers Balise 17. Elle ne répond plus. Les navettes attendent qu’un passage sûr soit rétabli.',
            'victory' => 'La route de ravitaillement est rouverte. Nous pouvons reprendre contact avec les navettes.',
            'ships' => ['fighter' => ['basic' => 24], 'interceptor' => ['basic' => 20], 'stealth_fighter' => ['basic' => 16]],
        ],
        'derniere_liaison' => [
            'chapter' => 1,
            'title' => 'La dernière liaison',
            'signal' => 'Le dernier relais tient encore le réseau civil, mais ses transmissions sont brouillées. Sans lui, les escortes ne peuvent plus coordonner leurs trajets.',
            'victory' => 'Le relais émet de nouveau. Les avant-postes peuvent se parler et les escortes reprendre leurs trajets.',
            'intel_tip' => 'Comparez les pertes prévues contre les intercepteurs avant de choisir votre escorte.',
            'ships' => self::LIGHT_FLEET,
        ],
        'balises_volees' => [
            'chapter' => 2,
            'title' => 'Les balises volées',
            'signal' => 'Quelqu’un émet avec nos identifiants. Les navettes suivent les faux signaux et n’arrivent plus au dépôt.',
            'victory' => 'Le dépôt peut de nouveau guider les navettes. Nous révoquons les codes de secours volés.',
            'intel_tip' => 'Essayez une escorte capable de toucher les appareils légers du blocus.',
            'ships' => self::LIGHT_FLEET + ['bomber' => ['basic' => 20]],
        ],
        'porteurs_de_brume' => [
            'chapter' => 2,
            'title' => 'Porteurs de brume',
            'signal' => 'Des navettes disparaissent des relevés dès qu’elles approchent du relais. Les pilotes ont besoin d’un passage sûr.',
            'victory' => 'Les navettes reparaissent sur nos écrans. Nous reprenons le guidage depuis le relais.',
            'intel_tip' => 'L’écran adverse ne se résume pas à ses intercepteurs. Des patrouilleurs appuyés par des chasseurs lourds offrent une réponse plus variée.',
            'ships' => self::LIGHT_FLEET + ['bomber' => ['basic' => 30]],
        ],
        'refuge_sous_escorte' => [
            'chapter' => 2,
            'title' => 'Un refuge sous escorte',
            'signal' => 'Nous abritons les équipages évacués des navettes. La route jusqu’au refuge est coupée.',
            'victory' => 'La route est dégagée. Nous pouvons évacuer les blessés et ravitailler le refuge.',
            'intel_tip' => 'Comparez plusieurs escortes dans le simulateur avant de dégager la route du refuge.',
            'ships' => self::LIGHT_FLEET + ['bomber' => ['basic' => 40]],
        ],
        'noeud_fausses_routes' => [
            'chapter' => 2,
            'title' => 'Le nœud des fausses routes',
            'signal' => 'Les cartes se contredisent : toutes les corrections conduisent les navettes vers le même secteur.',
            'victory' => 'Les coordonnées authentiques circulent de nouveau. Nous avons retrouvé une route sûre.',
            'intel_tip' => 'Comparez les cibles prioritaires de vos vaisseaux face à la formation du rapport.',
            'ships' => self::LIGHT_FLEET + ['bomber' => ['basic' => 50]],
        ],
        'corridor_des_ombres' => [
            'chapter' => 2,
            'title' => 'Le corridor des ombres',
            'signal' => 'Le trafic se concentre dans un corridor entre deux relais. Aucun pilote civil ne veut être le prochain à s’y engager.',
            'victory' => 'Le corridor est dégagé. Les navettes peuvent de nouveau suivre la route balisée.',
            'intel_tip' => 'Gardez de quoi intercepter les appareils qui couvrent le corridor.',
            'ships' => self::BOMBER_FLEET,
        ],
        'briseur_de_relais' => [
            'chapter' => 3,
            'title' => 'Le briseur de relais',
            'signal' => 'Un relais secondaire s’est tu. Son dernier paquet d’urgence indique où son équipe est bloquée.',
            'victory' => 'Le relais secondaire répond. Les équipes au sol ont repris contact.',
            'intel_tip' => 'Repérez les vaisseaux d’escorte dans le relevé et prévoyez de quoi les engager.',
            'ships' => self::BOMBER_FLEET + ['corvette' => ['intermediate' => 5], 'frigate' => ['intermediate' => 10], 'destroyer' => ['intermediate' => 6]],
        ],
        'couper_le_commandement' => [
            'chapter' => 3,
            'title' => 'Couper le commandement',
            'signal' => 'Les attaques semblent coordonnées depuis le même secteur. L’antenne civile tient encore et peut transmettre votre relevé.',
            'victory' => 'L’antenne transmet sans interruption. Les pirates ne tiennent plus ce nœud de commandement.',
            'intel_tip' => 'Prévoyez de quoi affronter les frégates tout en contenant les autres vaisseaux.',
            'ships' => self::BOMBER_FLEET + ['corvette' => ['intermediate' => 8], 'frigate' => ['intermediate' => 15], 'destroyer' => ['intermediate' => 9]],
        ],
        'secours_en_tenaille' => [
            'chapter' => 3,
            'title' => 'Secours en tenaille',
            'signal' => 'Les pilotes de secours sont immobilisés entre les relais. Leurs appareils civils ne peuvent pas dégager seuls la route.',
            'victory' => 'L’itinéraire de secours est libre. Les pilotes peuvent rejoindre l’avant-poste.',
            'intel_tip' => 'Les bombardiers et les chasseurs lourds peuvent menacer les destroyers. La frégate peut toutefois punir les appareils légers envoyés seuls.',
            'ships' => self::BOMBER_FLEET + ['corvette' => ['intermediate' => 10], 'frigate' => ['intermediate' => 20], 'destroyer' => ['intermediate' => 12]],
        ],
        'cuirasse_de_la_passe' => [
            'chapter' => 3,
            'title' => 'Le passage scellé',
            'revealed_title' => 'La garde de la passe',
            'signal' => 'La passe entre les deux relais est fermée. Les navettes n’ont plus d’itinéraire de secours.',
            'victory' => 'La passe est rouverte. Les équipes civiles peuvent reprendre leurs trajets.',
            'intel_tip' => 'Prévoyez des contres pour les frégates de la passe et leur couverture.',
            'ships' => self::BOMBER_FLEET + ['corvette' => ['intermediate' => 13], 'frigate' => ['intermediate' => 25], 'destroyer' => ['intermediate' => 15]],
        ],
        'deux_croiseurs_du_verrou' => [
            'chapter' => 3,
            'title' => 'Le verrou du réseau',
            'revealed_title' => 'La garde du verrou',
            'signal' => 'Les relais secondaires fonctionnent, mais leurs messages ne franchissent plus ce point du réseau.',
            'victory' => 'Les messages passent de nouveau par le verrou. Nous pouvons coordonner les équipes de plusieurs relais.',
            'intel_tip' => 'Examinez les rangs et les effectifs du verrou avant de choisir vos contres.',
            'ships' => self::ESCORT_FLEET,
        ],
        'arsenal_des_exiles' => [
            'chapter' => 4,
            'title' => 'L’arsenal des exilés',
            'signal' => 'Les derniers relevés remontent jusqu’à un arsenal pirate. Les équipes civiles attendent que le secteur cesse d’alimenter le blocus.',
            'victory' => 'La flotte de garde est dispersée. Le relais peut signaler une route à l’écart de l’arsenal.',
            'intel_tip' => 'Des cuirassés et leur escorte ne se contrent pas simplement par un miroir. Répartissez les rôles entre vos coques de ligne et votre soutien.',
            'ships' => self::ESCORT_FLEET + ['heavy_fighter' => ['intermediate' => 20], 'cruiser' => ['intermediate' => 4], 'battleship' => ['intermediate' => 3]],
        ],
        'signal_noye' => [
            'chapter' => 4,
            'title' => 'Le signal noyé',
            'signal' => 'Un poste civil émet encore, mais son message se perd dans le brouillage. Aucune navette ne reçoit son signal de retour.',
            'victory' => 'Nous captons à nouveau le poste. Les navettes peuvent recevoir ses indications.',
            'intel_tip' => 'Le rapport mêle des menaces légères et lourdes. Une seule ligne de croiseurs ne répond pas de la même façon à chaque écran.',
            'ships' => self::ESCORT_FLEET + ['heavy_fighter' => ['intermediate' => 40], 'cruiser' => ['intermediate' => 8], 'battleship' => ['intermediate' => 6]],
        ],
        'bastion_des_faussaires' => [
            'chapter' => 4,
            'title' => 'Le bastion des faussaires',
            'signal' => 'Les codes civils continuent d’être copiés depuis un bastion. Les équipages refusent de suivre les dernières corrections de route.',
            'victory' => 'Nous avons repris le contrôle des codes authentiques. Les navettes vérifient leurs itinéraires avant de repartir.',
            'intel_tip' => 'Pour affronter plusieurs coques lourdes, associez des contres à la ligne et un soutien capable d’entamer l’écran.',
            'ships' => self::HEAVY_FLEET,
        ],
        'premier_capital_noir' => [
            'chapter' => 5,
            'title' => 'L’armada du secteur noir',
            'revealed_title' => 'Les capitaux du secteur noir',
            'signal' => 'Les avant-postes captent un appel de détresse dans un secteur jusqu’ici silencieux. Le relais ne voit plus aucune route de sortie.',
            'victory' => 'La route de sortie tient de nouveau. Le relais confirme qu’il peut guider les appareils civils.',
            'intel_tip' => 'Les vaisseaux capitaux menacent les croiseurs et cuirassés. Vérifiez aussi les cibles que leur escorte peut atteindre.',
            'ships' => self::HEAVY_FLEET + ['capital' => ['superior' => 2]],
        ],
        'cuirasse_et_son_ecran' => [
            'chapter' => 5,
            'title' => 'Derrière le brouillage',
            'revealed_title' => 'La cuirasse et son écran',
            'signal' => 'Un relais isolé ne voit plus ce qui approche au-delà du brouillage. Les navettes civiles attendent son signal.',
            'victory' => 'Le brouillage tombe. Le relais reprend contact avec les navettes en attente.',
            'intel_tip' => 'Les frégates de l’écran punissent les bombardiers seuls. Prévoyez de les contrer en même temps que les coques capitales.',
            'ships' => self::HEAVY_FLEET + ['capital' => ['superior' => 6]],
        ],
        'deux_ombres_sur_orbite' => [
            'chapter' => 5,
            'title' => 'Des ombres sur l’orbite',
            'revealed_title' => 'La flotte sur l’orbite',
            'signal' => 'Le réseau fonctionne jusqu’à l’avant-dernier relais. Après ce point, tous les appels civils restent sans réponse.',
            'victory' => 'L’avant-dernier relais reçoit de nouveau les appels. Il ne reste qu’une coupure sur le réseau.',
            'intel_tip' => 'Le rapport mêle des capitaux et des destroyers d’escorte. Ne préparez pas uniquement une réponse au navire principal.',
            'ships' => self::HEAVY_FLEET + ['capital' => ['superior' => 10]],
        ],
        self::FINAL_MISSION => [
            'chapter' => 5,
            'title' => 'La nuit se brise',
            'signal' => 'Tous les relais sauf un répondent. Les pilotes civils attendent un dernier signal pour rouvrir les communications d’un bout à l’autre du réseau.',
            'victory' => 'Le dernier relais répond. Les avant-postes se parlent de nouveau et les routes civiles rouvrent.',
            'intel_tip' => 'La formation finale combine des capitaux, des coques de ligne et un écran. Comparez les contres contre chacun de ces rôles : le budget seul ne suffit pas.',
            'ships' => self::HEAVY_FLEET + ['capital' => ['superior' => 20]],
        ],
    ];

    public function __construct(
        private readonly GameCatalog $catalog,
        private readonly CombatUnitFactory $units,
    ) {
    }

    /** @return list<string> Ordre narratif, pas liste des missions accessibles au joueur. */
    public function keys(?Universe $universe = null): array
    {
        return array_merge(
            $this->campaignKeys(1), $this->campaignKeys(2), $this->campaignKeys(3),
            array_keys($universe?->gameSettings['pirate_missions'] ?? []),
        );
    }

    /** @return list<string> Clés persistées des vingt missions d’une campagne. */
    public function campaignKeys(int $tier): array
    {
        $keys = array_keys(self::DEFINITIONS);
        return $tier === 1 ? $keys : array_map(static fn (string $key): string => 't'.$tier.'_'.$key, $keys);
    }

    /** Les dossiers éditoriaux conservent leurs numéros à partir de 21. Les deux autres campagnes
     * disposent chacune d'une plage stable, indépendante des publications ultérieures.
     * @return array<int,string> Clé de mission indexée par numéro de route moins un.
     */
    public function routeKeys(Universe $universe): array
    {
        $legacy = array_keys(self::DEFINITIONS + ($universe->gameSettings['pirate_missions'] ?? []));
        foreach ([2, 3] as $tier) {
            foreach ($this->campaignKeys($tier) as $index => $key) {
                $legacy[$tier * self::TIER_ROUTE_SPAN + $index] = $key;
            }
        }

        return $legacy;
    }

    public static function dossierLabel(int $number): string
    {
        $tier = intdiv($number, self::TIER_ROUTE_SPAN);
        return $tier >= 2 ? sprintf('T%d · Mission %d', $tier, $number % self::TIER_ROUTE_SPAN) : 'Mission '.$number;
    }

    public function campaignTier(string $key): int
    {
        return match (substr($key, 0, 3)) {
            't2_' => 2,
            't3_' => 3,
            default => isset(self::DEFINITIONS[$key]) ? 1 : 0,
        };
    }

    /** @return list<array{title: string, keys: list<string>}> Sommaire narratif interne, pas un état de déblocage. */
    public function chapters(?Universe $universe = null, int $tier = 1): array
    {
        if ($tier === 0) {
            return [['title' => 'Missions de l’univers', 'keys' => array_keys($universe?->gameSettings['pirate_missions'] ?? [])]];
        }
        $chapters = [];
        foreach (self::CHAPTER_TITLES as $number => $title) {
            $chapters[$number] = ['title' => $title, 'keys' => []];
        }
        foreach (self::DEFINITIONS as $key => $entry) {
            $chapters[$entry['chapter']]['keys'][] = $tier === 1 ? $key : 't'.$tier.'_'.$key;
        }

        return array_values($chapters);
    }

    /**
     * Texte civil sans site, flotte ni récompense. Le dossier l’appelle après avoir validé le signal courant.
     *
     * @return array{title: string, message: string, guidance: string}
     */
    public function signalCopy(string $key, ?Universe $universe = null): array
    {
        $content = $this->definition($key,$universe);

        return [
            'title' => $content['title'],
            'message' => $content['signal'],
            'guidance' => 'Envoyez une seule sonde vers le poste civil. La colonie utilise le premier rang disponible, du Basique au Supérieur. Elle sera perdue, mais son rapport révélera la flotte pirate à l’arrivée, même si la sonde est détectée.',
        ];
    }

    /**
     * Préparer une seule fois l'adversaire, le prix catalogue et les paramètres natifs déjà mesurés.
     * Ne définit ni génération de site, ni détection, cadence, conditions d'accès, ni carburant.
     */
    public function prepareForServer(string $key, Universe $universe): PiratePreparedEncounter
    {
        $entry = $this->definition($key,$universe);
        $composition = $entry['ships'];
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

        $reward = null;
        if (isset($entry['reward'])) {
            $parts = [];
            foreach ($entry['reward'] as $resource=>$amount) $parts[$resource] = ['whole'=>$amount,'extra_half'=>false];
            $reward = PirateConvoyQuote::fromFrozenParts($parts);
        }
        return new PiratePreparedEncounter($key,$encounter,$battle,$reward);
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

        if ($moment === PirateCampaignMoment::Signal) {
            return $this->signalCopy($key,$viewer->universe);
        }

        $content = $this->definition($key,$viewer->universe);
        [$message, $guidance] = match ($moment) {
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
        $revealed = $moment !== PirateCampaignMoment::ReconnaissanceEnRoute;
        $title = $revealed ? ($content['revealed_title'] ?? $content['title']) : $content['title'];

        return ['title' => $title, 'message' => $message, 'guidance' => $guidance];
    }

    public function definition(string $key, ?Universe $universe = null): array
    {
        $tier = $this->campaignTier($key);
        if ($tier === 0) {
            return $universe?->gameSettings['pirate_missions'][$key] ?? throw new \DomainException('Rencontre pirate inconnue.');
        }
        $baseKey = $tier === 1 ? $key : substr($key, 3);
        $entry = self::DEFINITIONS[$baseKey] ?? throw new \DomainException('Rencontre pirate inconnue.');
        if ($tier > 1) {
            $rank = $tier === 2 ? 'intermediate' : 'superior';
            foreach ($entry['ships'] as $slug => $ranks) {
                $entry['ships'][$slug] = [$rank => array_sum($ranks)];
            }
        }

        return $entry;
    }
}
