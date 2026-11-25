# Déblocages initiaux permanents — grille actualisée le 18 novembre 2026

Lot 1 fusionné dans `dev` par la [PR #69](https://github.com/c41m07/genesis-reborn/pull/69), après CI complète réussie. Voir le [suivi de mission](PROGRESSION_CARBURANT_UNITES.md) pour les vérifications et l’état réel de l’intégration. Aucune activation sur la base de jeu ni publication Alpha annoncée.

## Lecture

Les tableaux donnent les conditions complètes et indiquent les exceptions où un autre rang intervient. Les libellés de famille ci-dessous gardent leurs variantes dans le jeu.

Les conditions sont initiales et permanentes après acquisition selon cette grille. Elles ne changent pas pendant les améliorations. Recherches : déblocage partagé dans l’univers. Chaque ajout exige aussi le laboratoire du rang commandé, au niveau 1 minimum sur la colonie de lancement. Bâtiments, vaisseaux et défenses : accès du joueur sur la colonie concernée. Prix, plafonds, durées et conditions de mission restent applicables.

## Bâtiments — 13

| Famille | Basique | Intermédiaire | Supérieur |
|---|---|---|---|
| Mine à ciel ouvert (`mine_metal`) | Aucun | Même bâtiment Basique 25 + Ingénierie de chantier automatisée 5 | Même bâtiment Basique 50 + même bâtiment Intermédiaire 25 + Planification industrielle intégrée 5 |
| Atelier de nanofabrication (`mine_nano`) | Aucun | Même bâtiment Basique 25 + Ingénierie de chantier automatisée 5 | Même bâtiment Basique 50 + même bâtiment Intermédiaire 25 + Planification industrielle intégrée 5 |
| Raffinerie d’hydrogène (`mine_hydrogen`) | Génie civil colonial 3 | Même bâtiment Basique 25 + Ingénierie de chantier automatisée 3 | Même bâtiment Basique 50 + même bâtiment Intermédiaire 25 + Planification industrielle intégrée 3 |
| Centrale nucléaire (`power_plant`) | Aucun | Même bâtiment Basique 25 + Ingénierie de chantier automatisée 5 | Même bâtiment Basique 50 + même bâtiment Intermédiaire 25 + Planification industrielle intégrée 5 |
| Laboratoire colonial (`lab`) | Aucun | Laboratoire colonial Basique 10 + Génie civil colonial 5 | Laboratoire colonial Basique 10 + Institut de recherche orbitale Intermédiaire 10 + Ingénierie de chantier automatisée 5 |

Les quatre producteurs utilisent la recherche industrielle du rang à débloquer.
Le laboratoire utilise celle du rang précédent : exiger une recherche du rang
visé créerait un cycle, car cette recherche exige déjà le laboratoire de ce rang.
Son plafond reste 25 ; aucun laboratoire Basique 50 n’est demandé.
Ces conditions s’appliquent aux accès restant à acquérir, sans retirer ceux déjà
mémorisés. Aucun changement de version du registre ni requalification supplémentaire.
Les coûts, durées et plafonds restent conservés ; l’effet sur les délais de
progression de cette révision n’est pas mesuré.

| Famille | Conditions du même rang | En plus pour l’Intermédiaire | En plus pour le Supérieur |
|---|---|---|---|
| Piège à antimatière (`mine_antimatter`) | Raffinerie d’hydrogène 5 + recherche industrielle 5 | Antimatière Basique 25 | Antimatière Basique 50 + Intermédiaire 25 |
| Chantier d’assemblage (`shipyard`) | Mine de métal 2 + producteur de nano 2 | Chantier Basique 20 | Chantiers Basique et Intermédiaire 20 |
| Bunker de défense (`fortress`) | Mine de métal 1 + producteur de nano 1 | Bunker Basique 8 | Bunkers Basique et Intermédiaire 8 |

La recherche industrielle s’appelle Génie civil colonial en Basique,
Ingénierie de chantier automatisée en Intermédiaire et Planification
industrielle intégrée en Supérieur.

Les cinq stockages suivent la même progression sur la colonie.

| Rang du stockage | Conditions de déblocage initial | Niveau maximum pour les nouvelles commandes |
|---|---|---|
| Basique | Producteur de la ressource Basique 5 | 5 |
| Intermédiaire | Producteur de la ressource Intermédiaire 5 + même stockage Basique 5 | 5 |
| Supérieur | Producteur de la ressource Supérieur 5 + même stockage Basique 5 + même stockage Intermédiaire 5 | Illimité |

| Stockage (nom de famille) | Producteur correspondant |
|---|---|
| Dépôt de minerais (`storage_metal`) | Mine à ciel ouvert |
| Coffre de nanomatériaux (`storage_nano`) | Atelier de nanofabrication |
| Réservoir cryogénique (`storage_hydrogen`) | Raffinerie d’hydrogène |
| Batterie de colonie (`storage_electricity`) | Centrale nucléaire |
| Cage magnétique (`storage_antimatter`) | Piège à antimatière |

## Recherches — 19

Aux conditions du tableau s’ajoute, pour les 19 familles, la recherche de la même famille au rang précédent, niveau 5. Cette exigence ouvre le T2 après le T1 et le T3 après le T2. Elle sert de référence au prix des matériaux. Les autres recherches et le laboratoire restent des conditions d’accès.

Les conditions du tableau appartiennent au rang demandé.

| Famille | Laboratoire | Recherches nécessaires |
|---|---|---|
| Protocoles de colonisation (`colonial_expansion`) | 2 | Architecture navale 1 |
| Manœuvres d’évitement (`evasive_navigation`) | 8 | Propulseurs vectoriels 4 + Télémétrie de poursuite 3 |
| Discipline du secret (`intelligence_countermeasures`) | 5 | Analyse tactique 2 |
| Soute compartimentée (`modular_cargo_bays`) | 3 | Architecture navale 2 |
| Réservoirs cryogéniques (`cryogenic_reserves`) | 5 | Propulseurs vectoriels 2 |
| Analyse tactique (`tactical_intelligence`) | 3 | Architecture navale 2 |
| Drones réparateurs (`automated_defense_repair`) | 5 | Blindage multicouche 2 |
| Générateurs de bouclier accordés (`shield_harmonics`) | 7 | Blindage multicouche 3 + Canons à énergie focalisée 3 |
| Séquenceur de salves (`rapid_fire_cycle`) | 10 | Canons à énergie focalisée 5 + Télémétrie de poursuite 4 |
| Viseurs balistiques (`weapon_calibration`) | 4 | Canons à énergie focalisée 2 |
| Métallurgie des nanomatériaux (`nanomaterial_synthesis`) | 3 | Génie civil colonial 3 |
| Architecture navale (`naval_engineering`) | 2 | Génie civil colonial 2 |
| Écran de brouillage (`signature_masking`) | 6 | Analyse tactique 3 |
| Télémétrie de poursuite (`target_acquisition`) | 5 | Viseurs balistiques 2 + Architecture navale 3 |
| Propulseurs vectoriels (`vector_propulsion`) | 4 | Architecture navale 3 |
| Génie civil colonial (`engineering_foundations`) | 1 | Aucune |
| Blindage multicouche (`structural_armor`) | 4 | Métallurgie des nanomatériaux 2 + Canons à énergie focalisée 2 |
| Revêtement absorbant (`signature_reduction`) | 7 | Écran de brouillage 2 + Métallurgie des nanomatériaux 3 |
| Canons à énergie focalisée (`weapons_engineering`) | 3 | Architecture navale 2 |

## Vaisseaux existants — 14

Toutes les conditions appartiennent au rang demandé.

| Famille | Chantier spatial | Recherches nécessaires |
|---|---|---|
| Croiseur de combat (`cruiser`) | 12 | Blindage multicouche 5 + Générateurs de bouclier accordés 4 + Propulseurs vectoriels 4 |
| Corvette de patrouille (`corvette`) | 5 | Architecture navale 4 + Propulseurs vectoriels 2 + Blindage multicouche 2 |
| Vaisseau colonisateur (`colony_ship`) | 2 | Protocoles de colonisation 1 |
| Chasseur lourd (`heavy_fighter`) | 4 | Blindage multicouche 1 + Viseurs balistiques 2 |
| Frégate d’escorte (`frigate`) | 8 | Blindage multicouche 3 + Générateurs de bouclier accordés 1 |
| Destroyer d’escorte (`destroyer`) | 10 | Canons à énergie focalisée 5 + Télémétrie de poursuite 3 + Générateurs de bouclier accordés 2 |
| Chasseur de patrouille (`fighter`) | 1 | Aucune |
| Cuirassé de ligne (`battleship`) | 15 | Blindage multicouche 7 + Générateurs de bouclier accordés 6 + Séquenceur de salves 2 |
| Cargo de ligne (`transport`) | 2 | Soute compartimentée 1 |
| Chasseur furtif (`stealth_fighter`) | 6 | Écran de brouillage 2 + Propulseurs vectoriels 2 |
| Intercepteur atmosphérique (`interceptor`) | 3 | Propulseurs vectoriels 1 + Viseurs balistiques 1 |
| Bombardier tactique (`bomber`) | 6 | Canons à énergie focalisée 3 + Télémétrie de poursuite 2 |
| Vaisseau capital (`capital`) | 20 | Canons à énergie focalisée 10 + Blindage multicouche 10 + Générateurs de bouclier accordés 10 + Séquenceur de salves 5 + Propulseurs vectoriels 7 |
| Sonde de reconnaissance (`probe`) | 1 | Aucune |

Les [simulations financées](SIMULATIONS_PROGRESSION_ECONOMIE.md) mesurent séparément
la date d’accès, le financement et l’achèvement. L’accessibilité du graphe ne constitue
pas une garantie de délai ni une activation des accès historiques.

## Unités économiques — 2 familles, 6 profils

Toutes les conditions appartiennent au rang demandé. Elles utilisent les mêmes
accès locaux initiaux et permanents que les autres vaisseaux.

| Famille | Conditions complètes du même rang |
|---|---|
| Récolteur d’hydrogène (`hydrogen_collector`) | Chantier 2 + raffinerie d’hydrogène 5 + Propulseurs vectoriels 1 |
| Satellite solaire (`solar_satellite`) | Chantier 2 + centrale 5 + Génie civil colonial 2 |

La construction du satellite exige aussi une place libre ou non réservée ; les
récolteurs excédentaires restent conservés sans production. Ce contrôle de
capacité est distinct de l’accès acquis. Voir [les règles de déploiement](UNITES_ECONOMIQUES.md).

## Défenses — 4

Toutes les conditions appartiennent au rang demandé.

| Famille | Bunker | Recherches nécessaires |
|---|---|---|
| Canon laser anti-léger (`ground_turret_anti_light`) | 1 | Aucune |
| Canon laser anti-capital (`ground_turret_anti_capital`) | 8 | Canons à énergie focalisée 8 + Télémétrie de poursuite 4 + Séquenceur de salves 3 |
| Canon laser anti-escorteur (`ground_turret_anti_escort`) | 3 | Canons à énergie focalisée 2 + Télémétrie de poursuite 1 |
| Canon laser anti-lourd (`ground_turret_anti_heavy`) | 5 | Canons à énergie focalisée 5 + Blindage multicouche 3 |

## Parcours

• En Basique, métal 2 + nano 2 → chantier 1 → chasseur et sonde.

• Producteur d’hydrogène 5 sur la colonie + recherche industrielle 5 dans l’univers → piège à antimatière du même rang. Les rangs avancés demandent aussi les niveaux des producteurs d’antimatière précédents indiqués dans le tableau. Le piège 5 remplit le prérequis de production du stockage d’antimatière du même rang.

• En Basique, mine de métal 1 + atelier de nanofabrication 1 → bunker 1 → défense anti-léger.

• Chaque stockage exige son producteur du même rang au niveau 5. Les rangs avancés exigent aussi le niveau 5 du même stockage dans chacun des rangs précédents. Les accès déjà acquis, les niveaux existants et les commandes déjà payées sont conservés.

Le test de parcours explore les 156 profils (150 existants et 6 nouveaux) depuis les entrées libres avec leurs plafonds réels. Il vérifie l’accessibilité et les cycles, sans prétendre mesurer le financement ou un délai de progression.

## Compatibilité

Le registre JSON existant reçoit des clés `v2:universe:...` ou `v2:planet:<id>:...` et le marqueur `_requirements_version: 2`. Aucun changement de schéma n’est nécessaire pour ce lot. Les anciennes clés sont conservées pour diagnostic mais ne donnent aucun accès. L’instantané historique du 6 novembre reste conservé comme référence ; le code de reprise ne le consulte plus.

Les nouveaux empires commencent avec le marqueur courant. Les participations existantes nécessitent une requalification explicite, sous verrou de participation et d’univers. Avant cette opération, les nouvelles commandes sont indisponibles ; les anciennes continuent leur résolution. Aucune lecture, aucun redémarrage ni worker ne déclenche automatiquement cette bascule.

La requalification utilise seulement les conditions réellement remplies : jamais la possession de l’élément, ses unités, une ancienne clé, une commande ou son achèvement. Niveaux, effets, stocks, unités et historiques restent inchangés. Les opérations payées gardent leur coût, leur durée et leur résultat. Lorsqu’un rang verrouillé est consulté, ses niveaux et quantités déjà possédés restent lisibles ; le verrou concerne les nouvelles commandes.

Après requalification, la synchronisation mémorise les nouveaux accès dont les conditions sont remplies. Une perte ultérieure de bâtiment, une annulation ou un redémarrage ne retire pas ces accès. La portée joueur/univers ou joueur/colonie est conservée, sans transmission des accès de l’ancien propriétaire lors d’une capture.

### Activation après validation

1. Activer la maintenance, arrêter les entrées de commandes et coordonner code web, workers, relais et notifier sur la même révision. Vérifier une sauvegarde restaurable avant toute reprise de la base existante.
2. Exécuter `make console CMD="app:catalog:preserve-unlocks --dry-run"`. Sans option, la commande reste également en lecture seule. Elle compte les participations anciennes et détaille les profils modifiés par les surcharges d’univers.
3. Examiner les surcharges signalées. `--apply` refuse la bascule si elles changent les prérequis. Aucune personnalisation n’est effacée ; toute correction éventuelle exige un choix et une autorisation séparés.
4. Après autorisation explicite et sauvegarde vérifiée, exécuter `make console CMD="app:catalog:preserve-unlocks --apply"`. Cette opération est idempotente et n’altère que le registre ; aucun import, aucune synchronisation globale de schéma.
5. Relancer le diagnostic : zéro participation restante et zéro surcharge incompatible. Contrôler sur un cas ancien les niveaux, unités, stocks, files, paiements et durées, ainsi que le refus des nouvelles commandes non débloquées. Redémarrer les consommateurs et rouvrir les accès après contrôle.

## Autres mécanismes

Les règles particulières des PNJ, de reconstruction, de capture, de réparation et de mission restent conservées. Les commandes nouvelles du joueur suivent les contrôles d’accès existants avec le nouveau registre ; la résolution d’une commande déjà acceptée ne revérifie pas ses prérequis. La reconstruction des empires oubliés conserve explicitement son chemin particulier `addReconstructionLocked`, qui ne consulte pas les accès du joueur. Cette exception existante n’est pas étendue et l’achèvement ne confère aucun accès au catalogue.

Les surcharges restent présentes en base. Le diagnostic compare leurs prérequis effectifs à la grille livrée et refuse une requalification incompatible ; les autres personnalisations, par exemple un prix ou une durée, restent applicables. Les anciens `levels.*.unlock_requirements` restent ignorés.

## Vérifications historiques — 6 novembre 2026

150 profils atteignables, exigences constantes sur neuf niveaux de contrôle. Tests des accès acquis, portées, anciennes unités/files, reprise idempotente, commandes directes, améliorations et présentation. Les devis, budgets, effets et statistiques restent conservés en dehors des prérequis et descriptions volontairement révisés.

Résultats :

- `make validate` : réussi.
- `make test` : dernier passage complet réussi, **200 tests / 205 447 assertions**. Après ajout du contrôle explicite du plafond et des assertions d’achèvement militaire, la sélection ciblée passe : **32 tests / 19 069 assertions**. La suite contient désormais 201 tests ; le dernier test ajouté est couvert par cette sélection.
- `npm run build` : réussi.
- `make lint` : réussi après migration, y compris PHP, 78 catalogues/configurations YAML/JSON, 56 templates Twig, services, mapping et synchronisation du schéma. Le seul écart avant migration était l’ajout de la colonne du registre.
- `git diff --check` : réussi.
- Tests navigateur : non lancés. Les fiches sont rendues dans les tests Twig ; la disponibilité de l’arbre est vérifiée avec des conditions manquantes et un accès acquis. Aucune vérification visuelle en navigateur n’est revendiquée.

Une exécution précédente de la suite complète a rencontré l’assertion intermittente préexistante `testNpcSpyReportInitializesGarrisonAndUsesItsCounterTechnology` : elle attend des catégories positives si l’espionnage réussit, alors que le score peut en révéler zéro. Le dernier passage complet réussit ; ce test et les règles d’espionnage ne sont pas modifiés dans ce travail.

## Fichiers concernés par le lot historique du 6 novembre

Catalogues de bâtiments/recherches et quatre fiches d’unités ; instantané `config/legacy`. `RequirementChecker`, `CatalogAccess`, `QueueService`, `UniverseService`, `PlayerUniverse` pour validation et registre. `ColonyView`, `CatalogueDetails`, `CatalogueInsights`, templates `catalogue_info`/`technology_tree` pour la présentation. Migration et commande de reprise dédiées ; tests de parcours, persistance, présentation et économie ; règles et décisions.

Provenance vérifiée pour les 50 fiches contre la révision `c6ecc7118cc58d8ae09b4bc72266d459a074cef2`. SHA-256 : `3e3fd07f433ef359720b76d0dea714f5d2f7021bdceda94a5889b57cc11c8ff9`.

## Application locale — 6 novembre 2026

L’utilisateur valide explicitement la migration. Branche vérifiée : `dev`. Base locale : `genesis_v2`. Une seule migration restait à appliquer : `Version20261106120000`, exécutée avec succès (une requête additive). Aucune autre migration appliquée.

Sauvegarde préalable : `var/backups/genesis-genesis_reborn_v2-20261106T015809Z.sql.gz`, avec somme SHA-256 associée. Worker, relais et notifications mis temporairement en pause, puis tous repris.

La reprise annonce 5 participations à traiter, puis 5 reprises ; le dernier contrôle indique 0 participation restante. Les empreintes avant/après sont identiques pour les tables comptes, colonies, ressources, niveaux, technologies, flottes, unités, défenses, files et outbox, ainsi que les champs historiques des participations. Seul le registre ajouté est renseigné. Somme SHA-256 des deux relevés : `4221093a2c037b76d077c82d3e945f61c12fa1ab1ad66d6529c4ce803e78afd4`.

`make validate` et `make lint` réussissent après l’opération. Les services locaux sont tous en fonctionnement. Aucun push, aucune fusion vers Alpha, aucun déploiement.
