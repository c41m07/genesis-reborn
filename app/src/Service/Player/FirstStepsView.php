<?php

declare(strict_types=1);

namespace App\Service\Player;

use App\Entity\{Planet, PlayerUniverse};
use App\Service\Colony\ResourceService;

/** Conseils du guide, distincts des validations du catalogue et des actions. */
final class FirstStepsView
{
    private const HINTS = [
        'metal_mine' => [
            'title' => 'Mine à ciel ouvert',
            'explanation' => 'Le métal paie vos premières installations. Vous pouvez lancer cette mine avant la centrale.',
            'action' => 'Construisez la Mine à ciel ouvert basique de niveau 1, puis attendez la fin de la file.',
            'label' => 'Ouvrir la Mine à ciel ouvert',
            'section' => 'buildings',
            'item' => 'mine_metal',
        ],
        'power_plant' => [
            'title' => 'Centrale nucléaire et bilan énergie',
            'explanation' => 'L’électricité alimente les usines. Sans assez d’énergie, les installations qui en utilisent produisent moins.',
            'action' => 'Construisez la Centrale nucléaire basique de niveau 1 et comparez la production et la consommation d’électricité dans la barre de ressources.',
            'label' => 'Ouvrir la Centrale nucléaire',
            'section' => 'buildings',
            'item' => 'power_plant',
        ],
        'nano_workshop' => [
            'title' => 'Atelier de nanofabrication',
            'explanation' => 'Les nanos complètent le métal pour les bâtiments, recherches et premiers vaisseaux.',
            'action' => 'Terminez l’Atelier de nanofabrication basique de niveau 1.',
            'label' => 'Ouvrir l’Atelier de nanofabrication',
            'section' => 'buildings',
            'item' => 'mine_nano',
        ],
        'metal_storage' => [
            'title' => 'Dépôt de minerais',
            'explanation' => 'La capacité indique ce que vous pouvez stocker. Seule une partie est protégée du pillage ; la fiche affiche les deux chiffres.',
            'action' => 'Visez le Dépôt de minerais basique de niveau 1. La grille de base demande la Mine à ciel ouvert niveau 5. Vous pouvez passer au laboratoire en attendant.',
            'label' => 'Ouvrir le Dépôt de minerais',
            'section' => 'buildings',
            'item' => 'storage_metal',
        ],
        'laboratory' => [
            'title' => 'Laboratoire colonial',
            'explanation' => 'Le laboratoire permet de lancer une recherche depuis cette colonie.',
            'action' => 'Construisez le Laboratoire colonial basique de niveau 1. La recherche de colonisation demandera un niveau supérieur.',
            'label' => 'Ouvrir le Laboratoire colonial',
            'section' => 'buildings',
            'item' => 'lab',
        ],
        'research' => [
            'title' => 'Première recherche',
            'explanation' => 'Les technologies améliorent votre empire et ouvrent de nouvelles possibilités.',
            'action' => 'Lancez une première recherche basique. Le guide propose Génie civil colonial, accessible avec un Laboratoire colonial basique de niveau 1.',
            'label' => 'Ouvrir la fiche Génie civil colonial',
            'section' => 'technologies',
            'item' => 'engineering_foundations',
        ],
        'assembly_yard' => [
            'title' => 'Chantier d’assemblage',
            'explanation' => 'Le chantier construit des unités pour les missions. La grille de base exige Mine à ciel ouvert et Atelier de nanofabrication basiques niveau 2.',
            'action' => 'Terminez le Chantier d’assemblage basique de niveau 1 lorsque les prérequis indiqués sur sa fiche sont prêts.',
            'label' => 'Ouvrir le Chantier d’assemblage',
            'section' => 'buildings',
            'item' => 'shipyard',
        ],
        'ship' => [
            'title' => 'Première Sonde de reconnaissance',
            'explanation' => 'La sonde vous permet d’explorer un nouveau système. Posséder un vaisseau donné n’atteste pas cette construction.',
            'action' => 'Avec le Chantier d’assemblage basique niveau 1, construisez et attendez votre première Sonde de reconnaissance basique.',
            'label' => 'Ouvrir la fiche de la sonde',
            'section' => 'ships',
            'item' => 'probe',
        ],
        'exploration' => [
            'title' => 'Premier système exploré',
            'explanation' => 'Explorer révèle un système nouveau. La mission consomme une sonde à l’arrivée : prévoyez d’en construire une autre au chantier pour l’espionnage.',
            'action' => 'Préparez une mission d’exploration d’un système non cartographié et vérifiez le trajet avant l’envoi.',
            'label' => 'Préparer une exploration',
            'mission' => 'explore',
        ],
        'espionage' => [
            'title' => 'Premier rapport d’espionnage PNJ',
            'explanation' => 'Un rapport « Détecté » ne contient aucun renseignement. Même sans détection, les informations ne sont pas garanties.',
            'action' => 'Préparez l’espionnage guidé avec exactement une Sonde de reconnaissance basique, sans escorte, vers une planète connue au propriétaire indiqué « PNJ ». La sonde envoyée est consommée à l’arrivée. Le rapport du tutoriel donne droit au remplacement d’une sonde, une seule fois par compte, même si elle est détectée.',
            'label' => 'Préparer un espionnage PNJ',
            'mission' => 'spy',
        ],
        'pirate_exercise' => [
            'title' => 'Exercice pirate',
            'explanation' => 'Entraînez-vous à lire un rapport face à des pirates fictifs, sans flotte réelle.',
            'action' => 'Lisez le briefing puis lancez la simulation. Ni perte réelle, ni carburant, ni butin. Vous pouvez coloniser avant cet entraînement.',
            'label' => 'Ouvrir le briefing de l’exercice',
        ],
        'colonization' => [
            'title' => 'Première colonisation',
            'explanation' => 'Agrandissez votre empire sans avoir à terminer le guide.',
            'action' => 'Construisez le Vaisseau colonisateur basique : chantier basique niveau 2 et Protocoles de colonisation basiques niveau 1. Cette technologie demande notamment le Laboratoire colonial niveau 2 et Architecture navale niveau 1. Vérifiez une place disponible, puis choisissez une planète découverte et inhabitée, non PNJ. Le colonisateur est consommé si la colonie est fondée.',
            'label' => 'Préparer une colonisation',
            'mission' => 'colonize',
        ],
    ];

    // Documents d’interface et visuels officiels déjà fournis avec le jeu.
    // Les exemples expliquent l’étape : seuls les événements métier peuvent la valider.
    private const EXAMPLES = [
        'metal_mine' => ['images/catalog/buildings/mine_metal__basic.webp', 'Mine à ciel ouvert du catalogue', 'La mine basique atteint le niveau 1. La mise en file seule ne compte pas.'],
        'power_plant' => ['images/catalog/buildings/power_plant__basic.webp', 'Centrale nucléaire du catalogue', 'La centrale basique atteint le niveau 1.'],
        'nano_workshop' => ['images/catalog/buildings/mine_nano__basic.webp', 'Atelier de nanofabrication du catalogue', 'L’atelier basique atteint le niveau 1.'],
        'metal_storage' => ['images/catalog/buildings/storage_metal__basic.webp', 'Dépôt de minerais du catalogue', 'Le dépôt basique atteint le niveau 1.'],
        'laboratory' => ['images/catalog/buildings/lab__basic.webp', 'Laboratoire colonial du catalogue', 'Le laboratoire basique atteint le niveau 1.'],
        'research' => ['images/catalog/technologies/engineering_foundations__basic.webp', 'Génie civil colonial dans le catalogue', 'Une recherche basique est terminée.'],
        'assembly_yard' => ['images/catalog/buildings/shipyard__basic.webp', 'Chantier d’assemblage du catalogue', 'Le chantier basique atteint le niveau 1.'],
        'ship' => ['images/catalog/ships/probe__basic.webp', 'Sonde de reconnaissance du catalogue', 'Une sonde basique est réellement construite.'],
        'exploration' => ['art/ui-preview-galaxy.webp', 'Aperçu de l’interface Galaxie', 'La mission révèle un système encore inconnu de votre empire.'],
        'espionage' => ['images/catalog/ships/probe__basic.webp', 'Sonde basique utilisée pendant l’espionnage guidé', 'La mission guidée vers un PNJ produit un rapport, même si la sonde est détectée.'],
        'pirate_exercise' => ['art/ui-preview-colony-432.webp', 'Aperçu de la Vue générale dans le jeu', 'Lancez la simulation et affichez le rapport de l’exercice.'],
        'colonization' => ['images/catalog/ships/colony_ship__basic.webp', 'Vaisseau colonisateur du catalogue', 'La mission colonise une planète et vous en obtenez le contrôle.'],
    ];

    public function __construct(private FirstSteps $firstSteps, private ResourceService $resources)
    {
    }

    /** @param array<string, array<string, int>> $buildings @param array<string, array<string, int>> $technologies */
    public function progress(PlayerUniverse $membership, array $buildings, array $technologies): array
    {
        $completed = $this->firstSteps->completed($membership);
        $existing = [];
        foreach (FirstStep::cases() as $step) {
            if (isset($completed[$step->value])) {
                continue;
            }
            $building = $step->buildingSlug();
            $alreadyPresent = $building !== null && ($buildings[$building]['basic'] ?? 0) > 0;
            if ($step === FirstStep::Research) {
                foreach ($technologies as $levels) {
                    if (($levels['basic'] ?? 0) > 0) {
                        $alreadyPresent = true;
                        break;
                    }
                }
            }
            if ($alreadyPresent) {
                // Un niveau déjà installé suffit pour le guide ; aucune écriture ni prime rétroactive.
                $existing[$step->value] = true;
            }
        }
        $done = $completed + $existing;
        $next = null;
        foreach (FirstStep::cases() as $step) {
            if (!isset($done[$step->value])) {
                $next = $step->value;
                break;
            }
        }

        $parallel = null;
        if ($next === FirstStep::MetalStorage->value && ($buildings['mine_metal']['basic'] ?? 0) < 5) {
            // Ne pas laisser la carte pointer uniquement vers le dépôt bloqué pendant plusieurs niveaux.
            $afterStorage = false;
            foreach (FirstStep::cases() as $step) {
                if ($afterStorage && !isset($done[$step->value])) {
                    $parallel = $step->value;
                    break;
                }
                $afterStorage = $afterStorage || $step === FirstStep::MetalStorage;
            }
        }

        return [
            'hints' => self::HINTS,
            'examples' => self::EXAMPLES,
            'completed' => $done,
            'existing' => $existing,
            'next' => $next,
            'parallel' => $parallel,
        ];
    }

    /** @param array<string, array<string, int>> $buildings */
    public function catalogHint(string $slug, string $section, Planet $planet, array $buildings, array $snapshot): ?array
    {
        foreach (self::HINTS as $hint) {
            if (($hint['item'] ?? null) !== $slug || ($hint['section'] ?? null) !== $section) {
                continue;
            }
            if ($slug === 'power_plant') {
                $hint['electricity'] = [
                    'produced' => $snapshot['electricity']['gross'] ?? 0.0,
                    'used' => $snapshot['electricity']['consumption'] ?? 0.0,
                ];
            }
            if ($slug === 'storage_metal') {
                $capacity = $snapshot['metal']['capacity'] ?? 0.0;
                $protected = $snapshot['metal']['protectedCapacity'] ?? 0.0;
                $nextLevels = $buildings;
                $nextLevels['storage_metal']['basic'] = max(1, (int) ($buildings['storage_metal']['basic'] ?? 0));
                $newCapacity = $this->resources->capacity($planet, 'metal', $nextLevels);
                $hint['storage'] = [
                    'capacity' => $capacity,
                    'protected' => $protected,
                    'nextCapacity' => $newCapacity,
                    'nextProtected' => $capacity > 0 ? $newCapacity * $protected / $capacity : 0,
                ];
            }

            return $hint;
        }

        return null;
    }
}
