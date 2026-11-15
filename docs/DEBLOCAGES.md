# Déblocages initiaux permanents — grille du 15 novembre 2026

Lot 1 préparé sur `feature/progression-acces-par-rang`. Voir le [suivi de mission](PROGRESSION_CARBURANT_UNITES.md) pour les vérifications et l’état réel de l’intégration. Aucune activation sur la base de jeu ni publication Alpha annoncée.

## Lecture

Les tableaux donnent les conditions complètes : toutes appartiennent au rang demandé, sauf les cinq passerelles explicites ci-dessous. Aucun autre rang du même élément, aucune ancienne spécialisation ni recherche implicite ne s’ajoute. Les libellés de famille ci-dessous gardent leurs variantes dans le jeu.

Les conditions sont initiales et permanentes après acquisition selon cette grille. Elles ne changent pas pendant les améliorations. Recherches : accès du joueur dans l’univers, depuis toute colonie après déblocage, sans laboratoire local permanent. Bâtiments, vaisseaux et défenses : accès du joueur sur la colonie concernée. Prix, plafonds, durées et conditions de mission restent applicables.

## Bâtiments — 13

| Famille | Basique | Intermédiaire | Supérieur |
|---|---|---|---|
| Mine à ciel ouvert (`mine_metal`) | Aucun | Même bâtiment Basique 10 | Même bâtiment Intermédiaire 20 |
| Atelier de nanofabrication (`mine_nano`) | Aucun | Même bâtiment Basique 10 | Même bâtiment Intermédiaire 20 |
| Raffinerie d’hydrogène (`mine_hydrogen`) | Aucun | Même bâtiment Basique 10 | Même bâtiment Intermédiaire 20 |
| Centrale nucléaire (`power_plant`) | Aucun | Même bâtiment Basique 10 | Même bâtiment Intermédiaire 20 |
| Laboratoire colonial (`lab`) | Aucun | Même bâtiment Basique 10 | Même bâtiment Intermédiaire 20 |

Pour les huit autres familles, les conditions suivantes s’appliquent dans chacun des trois rangs :

| Famille | Conditions du même rang |
|---|---|
| Piège à antimatière (`mine_antimatter`) | Raffinerie d’hydrogène 3 + Laboratoire colonial 2 |
| Chantier d’assemblage (`shipyard`) | Mine à ciel ouvert 2 + Atelier de nanofabrication 2 |
| Bunker de défense (`fortress`) | Chantier d’assemblage 3 + Laboratoire colonial 2 |
| Dépôt de minerais (`storage_metal`) | Mine à ciel ouvert 5 |
| Coffre de nanomatériaux (`storage_nano`) | Atelier de nanofabrication 5 |
| Réservoir cryogénique (`storage_hydrogen`) | Raffinerie d’hydrogène 5 |
| Batterie de colonie (`storage_electricity`) | Centrale nucléaire 5 |
| Cage magnétique (`storage_antimatter`) | Piège à antimatière 5 |

## Recherches — 19

Toutes les conditions appartiennent au rang demandé.

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

## Défenses — 4

Toutes les conditions appartiennent au rang demandé.

| Famille | Bunker | Recherches nécessaires |
|---|---|---|
| Canon laser anti-léger (`ground_turret_anti_light`) | 1 | Aucune |
| Canon laser anti-capital (`ground_turret_anti_capital`) | 8 | Canons à énergie focalisée 8 + Télémétrie de poursuite 4 + Séquenceur de salves 3 |
| Canon laser anti-escorteur (`ground_turret_anti_escort`) | 3 | Canons à énergie focalisée 2 + Télémétrie de poursuite 1 |
| Canon laser anti-lourd (`ground_turret_anti_heavy`) | 5 | Canons à énergie focalisée 5 + Blindage multicouche 3 |

## Parcours

• Métal 2 + nano 2 → chantier 1 → chasseur et sonde, sans recherche supplémentaire.

• Hydrogène 3 + laboratoire 2 → piège à antimatière ; piège 5 → stockage d’antimatière.

• Chantier 3 + laboratoire 2 → bunker 1 → défense anti-léger, sans recherche supplémentaire.

• Chaque stockage exige son producteur du même rang au niveau 5 ; aucun stockage précédent.

Le test de parcours explore les 150 profils depuis les entrées libres avec leurs plafonds réels. Il vérifie l’accessibilité et les cycles, sans prétendre mesurer le financement ou un délai de progression.

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
