# Progression, carburant et unités économiques

## Périmètre et état — 16 novembre 2026

Mission issue du prompt utilisateur : nouvelles grilles, nouvelles durées et
consommations de carburant, récolteur d’hydrogène mobile, satellite solaire fixe,
simulation des effets économiques et militaires. Aucun déploiement Alpha.
La redistribution des coûts B 50/50 et I 40/40/20 reste un candidat à comparer,
sans activation avant une décision séparée et chiffrée. Aucun budget, effet,
plafond ni règle non explicitement visé ne doit changer.

Chaque lot part de dev actualisée et doit être validé en CI complète puis fusionné
par PR vers dev avant le suivant. Les push, fusions et opérations de base restent
soumis aux autorisations du dépôt. Les trois sources Work nommées par AGENTS.md
n’ont pas été trouvées dans les fichiers accessibles du dépôt ; elles ne sont
pas présentées comme lues.

| Lot | Contenu | État |
|---|---|---|
| 1 | 150 profils de prérequis, registre versionné, diagnostic et requalification explicite, interface et documentation associées | Fusionné dans dev par PR #69 après CI complète réussie |
| 2 | Durées, taxe électrique calculée sur la durée effective, carburant différencié, navigation historique préservée | Fusionné dans dev par PR #70 après CI complète réussie |
| 3 | Six profils civils, production segmentée, mobilité/immobilité, affectations et réservations concurrentes, combat, rapports et simulateur | Fusionné dans dev par PR #71 après CI complète n° 299 réussie |
| 4 | Simulations avec les calculateurs réels, comparaison des répartitions, mesures et limites | Publié par [PR #72](https://github.com/c41m07/genesis-reborn/pull/72) ; résultats CI et fusion consultables dans la PR |

## Lot 1 — complexité 5/5

Base : dev `176c556`, propre et identique à origin/dev après actualisation autorisée.
Branche : `feature/progression-acces-par-rang`.
La [grille finale](DEBLOCAGES.md) donne les conditions complètes des 50 familles
et trois rangs, ainsi que la procédure de bascule. Prix, durées, statistiques,
files, verrous, reçus et jetons de résolution restent conservés dans ce lot.

Le registre réutilise la colonne JSON existante : nouvelles clés préfixées `v2:`,
marqueur de version, anciennes clés conservées mais ignorées pour les accès.
Aucune migration SQL nécessaire. Le défaut des nouveaux empires est courant ;
les participations historiques ne sont pas reprises automatiquement. Avant la
requalification, les nouvelles commandes sont indisponibles et les commandes
payées continuent à se terminer normalement. Après requalification, seul le
respect de la grille ouvre un accès, ensuite permanent dans sa portée habituelle.

La commande `app:catalog:preserve-unlocks` devient diagnostique par défaut ;
`--dry-run` explicite cette lecture seule et `--apply` exige l’intervention
autorisée. Les surcharges d’univers modifiant la grille sont signalées et
bloquent l’application ; aucune personnalisation n’est effacée. L’application
est idempotente et sérialisée par les verrous existants de participation/univers.

## Vérifications et limites du lot 1

- `make validate` : réussi avant les modifications.
- `make lint` : réussi (PHP, YAML, Twig, conteneur, mapping et schéma).
- `make test` sur `510610f` : réussi, 1 205 tests PHP, 214 139 assertions et 13 tests Python.
  Une mesure de préparation facultative préexistante est ignorée par défaut
  (`REWORK_MEASURE=1` pour l’activer) ; aucun test métier n’est désactivé.
- Parcours navigateur ciblés : 9 scénarios réussis sur la première passe, puis
  les 2 scénarios actualisés réussis sur leur relance (11 scénarios distincts).
  Fiches des 150 profils, liens de rang, guide et focus clavier, affichages mobile
  et ordinateur, recherche commune, opérations historiques et wiki couverts.
  La suite navigateur complète a ensuite réussi en CI sur le dernier commit de la PR.
- Les 13 tests Python passent. Les 291 tests ciblés ont révélé une attente
  de libellé de rang, corrigée ; la sélection de reprise passe (29 tests / 2 006 assertions).
- `git diff --check` : réussi après les corrections.
- Protections GitHub de dev vérifiées en lecture seule : PR obligatoire, les cinq
  jobs quality requis, administrateurs inclus ; publish absent des exigences.
- Nouveaux tests : grille exacte, seuils, rangs incorrects, anciens accès,
  possessions, résolutions historiques des quatre catégories, reprise et surcharges.
- Aucun résultat économique ou militaire de l’ancien système ne valide la mission.
  Les tests de graphe vérifient l’accessibilité, sans mesurer le financement ni le temps.
- Wiki : source locale `docs/wiki.json` et références REGLES/DECISIONS synchronisées ; aucun wiki hébergé ni déploiement n’est déclaré mis à jour.
- Diagnostic en lecture seule de la base locale : 6 participations à requalifier,
  aucune surcharge de prérequis signalée. Aucune écriture par cette commande.
- Aucune requalification appliquée à la base de jeu. [PR #69](https://github.com/c41m07/genesis-reborn/pull/69) fusionnée dans dev au commit `01bc65a`, après CI complète réussie sur `a1fe8af`.

## Recette CI du lot 1

Le premier passage CI a validé les lots navigateur 3 et 4. Les lots 1 et 2
ont révélé quatre préparations de test fondées sur l’ancienne grille : métal
sans nano pour le chantier, ancienne passerelle de centrale et chantier
Intermédiaire absent. Les préparations sont actualisées sans retrait d’assertion.
Le cas d’unités possédées mais non débloquées vérifie désormais explicitement
que leur quantité reste lisible et qu’aucun formulaire de construction ne s’ouvre.

Le contrôle PHP s’est arrêté avant PHPUnit, la base isolée database-test étant
unhealthy. Ses journaux sont ajoutés aux diagnostics CI en cas d’échec ; aucun
contrôle n’est affaibli. La nouvelle CI complète a validé le lot corrigé.

Corrections de recette vérifiées localement : 4/4 scénarios navigateur concernés,
16 tests PHP ciblés / 133 assertions, make lint et git diff --check réussis.
La [CI complète n° 294](https://github.com/c41m07/genesis-reborn/actions/runs/43761530128)
a réussi sur `a1fe8af` : cinq jobs quality, tous les parcours navigateur,
compilation, lints, tests PHP/Python, schéma et sauvegarde/restauration isolées.
GitGuardian réussi ; publish ignoré sur PR, conformément au workflow.
La fusion autorisée dans dev est `01bc65ae5ba20077d5506dc303b9c5ae277897ce`.

## Lot 2 — complexité 4/5

Base : dev actualisée `01bc65a`, après intégration du lot 1 et contrôle de propreté.
Branche : `feature/durees-carburant-par-classe`.
Sous-tâches : durées et arrondis 3/5 ; compatibilité carburant 4/5 ;
mesures reproductibles 3/5 ; synchronisation documentaire 2/5.
Les [tableaux et mesures](DUREES_CARBURANT.md) donnent les durées, coûts électriques,
coefficients de carburant, hypothèses et limites. Les durées de bâtiments, courbes,
rangs, vitesses, réservoirs, statistiques et prix M/N/A/H restent conservés.

Le calculateur commun reçoit les nouvelles références. Une correction numérique
retire la seconde artificielle au plafond exact de 70 % ; le Capital Basique
prend 7 h 12 et coûte 43 200 E à ce plafond. Sans réduction, il passe de
432 000 à 144 000 E. Les défenses gardent l’absence de réduction propre.
Les commandes déjà payées gardent leurs échéances, coûts, annulations et boosts.

La navigation enregistre les coefficients réellement employés au départ.
Les rappels vers une autre colonie et les replis de stationnement les réutilisent.
Les anciennes missions sans instantané reprennent les coefficients archivés et
les surcharges persistées ; une surcharge modifiée depuis le départ nécessite
un diagnostic préalable. Aucun état de flotte n’est réécrit par un devis.
L’archive `config/legacy/progression-operations-2026-11-15.json` conserve la
référence antérieure et ne doit pas être régénérée depuis le nouveau catalogue.

La commande `app:balance:compare-operations` produit les 150 comparaisons,
les scénarios de file commune et les trajets avec les calculateurs réels,
sans lecture ni écriture de données de jeu. Colonisation B1 garde 80 h avant
réduction : laboratoire B2, 77 h 54 min 17 s et 467 500 E. Les vingt chasseurs
sur dix unités de coordonnées passent de 82 000 à 8 200 H à vitesse 100 %.
La fiche de sonde conserve l’affichage de 0,006 H/h.

### Fichiers principaux du lot 2

- `app/config/game/technologies/`, `ships/`, `defenses/` : 33 fiches de durée/carburant.
- `OperationCalculator.php` : précision du pourcentage au plafond.
- `FleetFuel.php`, `FleetNavigation.php`, `FleetRecallPlanner.php`, `FleetStationing.php` : barème figé et historique.
- `CatalogueDetails.php`, `catalogue_info.html.twig` : affichage des millièmes de H/h.
- `CompareProgressionOperationsCommand.php` et l’archive `config/legacy/` : mesures reproductibles.
- Tests unitaires de durée et carburant, intégration des files/rappels/replis et fiches navigateur ; références historiques conservées.
- README, index et documentation de règles, décisions, coûts, architecture, wiki et reprise.

### Vérifications locales du lot 2

- `make validate`, `make lint` et `make assets` réussis. Aucun changement de schéma nécessaire.
- 11 parcours navigateur ciblés réussis : fiches, recherche commune et historique,
  devis, rappels et stationnement ; aucune erreur ni scénario ignoré.
- Les suites PHP complètes de développement ont exécuté 1 282 tests. La première
  a relevé sept attentes historiques ; la seconde a confirmé les corrections
  métier et isolé deux références anciennes de production encore mal combinées.
  Après correction de l’ordre de restauration, les 21 tests concernés passent
  (16 891 assertions), sans modification des fixtures ni retrait d’assertion.
  La dernière correction porte uniquement sur les supports d’études historiques ;
  aucune troisième passe complète locale n’a été lancée. La CI complète du lot 2
  reste obligatoire avant fusion. La mesure facultative de préparation préexistante
  est la seule omission de la suite complète, inchangée (`REWORK_MEASURE=1`).
- Les 13 tests Python passent. Les tests nouveaux couvrent les 150 durées, paliers,
  rangs, lots et maxima, la taxe électrique, le plafond de 70 %, le minimum,
  les coefficients de 14 classes, les compositions et les missions historiques.
- Les anciennes études de coûts/combat rejouent leurs paramètres datés ; les valeurs
  actuelles sont vérifiées séparément. Cette dépendance à deux références datées
  a fait réévaluer le support de tests de 3/5 à 4/5, sans extension du gameplay.
- L’archive est identique aux 50 durées et 14 carburants du commit de référence.
  La comparaison des 33 fiches confirme l’absence de changement des autres champs.
- `git diff --check` réussi ; wiki JSON et liens des deux suivis valides.
- Diagnostic local en lecture seule : aucune surcharge de durée/carburant et aucune
  flotte active ; cela ne décrit pas Alpha. Aucune migration SQL nécessaire.
- [PR #70](https://github.com/c41m07/genesis-reborn/pull/70) fusionnée avec autorisation dans
  dev au commit `6fa8823`, après [CI complète n° 295](https://github.com/c41m07/genesis-reborn/actions/runs/43767637219)
  réussie sur `ce4bc69` : cinq jobs quality, 1 282 PHP / 215 798 assertions,
  13 Python et 202 parcours navigateur ; compilation, lint, schéma, diff et
  sauvegarde/restauration isolées réussis. GitGuardian réussi ; publish ignoré sur PR.
  Aucune requalification d’accès en base de jeu ni livraison Alpha.

## Lot 3 — implémentation locale, complexité 5/5

Base : dev actualisée `6fa8823`, après fusion du lot 2 ; arbre initialement propre.
Branche : `feature/unites-economiques-orbitales`.
Sous-tâches : catalogue et statistiques 3/5 ; capacités et réservations concurrentes
5/5 ; production et transitions 5/5 ; combat et chronologie 5/5 ; interface 3/5.

Décisions complémentaires explicites des 15 et 16 novembre :
- Coefficient neutre ×1 pour les tirs ciblant les deux unités civiles, sans nouveau contre.
- Livraisons du chantier et du Stationnement : la flotte locale complète rejoint
  les renforts au prochain round éligible, en conservant son carburant embarqué.
- Les pertes effectives et l’arrêt de production suivent la clôture du combat,
  conformément au moteur actuel. Aucun arrêt général au début du combat.

### Résultat

Les [six profils et leurs règles](UNITES_ECONOMIQUES.md) sont implémentés : prix,
prérequis, production, places du même rang, inactivité sans perte des excédents,
réservations de chantier/Stationnement, retours et replis. Les transactions et
verrous existants portent les contrôles, sans nouvelle table ni migration SQL.
Les sources civiles sont distinguées des usines dans les ressources et les cartes.
Les satellites sont exclus des départs, les récolteurs limités au Stationnement
entre colonies possédées. Aucune modification de coûts/statistiques des unités
existantes et aucune activation du candidat de redistribution.

Le moteur de combat conserve l’unicité des FleetShip, le carburant et le fret des
renforts locaux. Une livraison retardée ne réécrit pas les rounds déjà résolus.
Deux SVG originaux de famille sont fournis et partagés entre leurs trois rangs,
sans réutiliser l’illustration d’un autre vaisseau.

### Fichiers principaux

- Deux catalogues de vaisseaux, deux SVG et colonnes neutres de la matrice de combat.
- `EconomicUnitCapacity`, `EconomicUnitDeployment`, `GameRepository` : stocks ciblés,
  places et transitions sous les verrous existants.
- `ResourceService`, `CatalogueProductionContext` : production civile et frontières temporelles.
- `QueueService`, services de flotte, `LocalGarrison`, `CombatEventService` : livraisons,
  réservations, pertes et renforts sans duplication.
- Cartes, détails, composition et simulateur : disponibilité, capacité, immobilité et accès clavier.
- Tests unitaires, intégration, concurrence réelle et deux nouveaux parcours navigateur.
- README, règles, décisions, coûts, déblocages, architecture, économie, déplacements,
  illustrations, 156 libellés CSV, wiki embarqué et index synchronisés. Aucune mise
  à jour d’un wiki hébergé externe ni livraison Alpha annoncée.

### Vérifications locales du 16 novembre 2026

- `make validate`, `make lint` (PHP, 80 YAML, 170 Twig, injection et schéma) et
  `make assets` réussis ; aucun changement de schéma requis.
- Suite `make test` : 1 306 tests PHP exécutés, 228 957 assertions, un seul échec
  dans un ancien test de retour qui mélangeait horloge simulée et date réelle.
  La correction aligne l’échéance de test sur l’horloge applicative ; ce test
  repasse (8 assertions). Aucune assertion retirée. La suite complète n’a pas été
  relancée après cette correction limitée au support de test ; la CI de PR devra
  donc confirmer l’ensemble. L’unique omission reste la mesure facultative
  préexistante `REWORK_MEASURE=1`.
- Les 13 tests Python passent. Les 18 cas d’intégration économiques passent dans
  la suite, dont deux commandes réellement simultanées pour la dernière place.
- Huit parcours navigateur distincts ont été exécutés : fiches des 156 profils,
  deux nouveaux parcours économiques, Stationnement et rappels. Sept passent
  immédiatement ; le nouveau contrôle Axe détecte le libellé du bouton Max,
  corrigé pour inclure son texte visible. Le parcours concerné repasse avec
  vérifications clavier, contrastes et absence de débordement à 1440 et 390 px.
  Les pictogrammes et l’affichage des places ont été examinés sur captures.
- La commande de comparaison existante produit 156 profils et 180 trajets ; les
  six nouveaux profils n’inventent pas de durée historique. L’archive est conservée.
- Diagnostic local en lecture seule : zéro univers surchargeant les deux nouvelles
  unités, zéro ligne de stock civil et zéro commande civile. Ce constat ne décrit
  pas Alpha et ne constitue pas une activation.
- Relecture de la matrice : toutes les relations existantes sont conservées, deux
  colonnes neutres seulement ajoutées. Aucun prix existant modifié.

Un autre travail a changé la branche du dossier partagé pendant la vérification.
Le lot a été copié dans un checkout séparé de sa branche, en préservant les
modifications étrangères. Après autorisation explicite, sa copie initiale a été
retirée du dossier partagé avec vérification d’identité de chaque fichier ; les
six fichiers modifiés par l’autre tâche sont conservés.

Publication et fusion conditionnelle autorisées : [PR #71](https://github.com/c41m07/genesis-reborn/pull/71).
La première [CI complète n° 296](https://github.com/c41m07/genesis-reborn/actions/runs/43780432127)
confirme 1 306 PHP / 228 969 assertions, 13 Python, lint, schéma, compilation,
diff et sauvegarde/restauration isolées (60 tables). Les 204 parcours navigateur
sont allés au bout : 202 réussis, deux attentes devenues obsolètes. Le test
d’onglets attendait 14 familles au lieu de 16 ; le test de maximum cherchait
l’ancien nom accessible du bouton Max. Leurs attentes sont actualisées sans
retrait de contrôle, avec conservation de la correction d’accessibilité.
Les deux parcours corrigés repassent dans des bases navigateur isolées, sans
modifier les délais d’attente ni retirer leurs assertions métier.
La CI complète finale décrite ci-dessous a ensuite validé ces corrections avant fusion.
Aucune opération sur une base de jeu ni aucun déploiement Alpha.

### Complément de relecture : attributions administratives

La vérification des chemins créant des FleetShip identifie l’attribution
administrative : elle doit solder l’ancien effectif avant d’ajouter des unités
économiques. `AdminInterventionService` applique maintenant cette transition et
le raccordement commun des livraisons locales aux renforts. Les permissions,
propriété, verrouillage, reçu et audit sont conservés, sans attribution réelle
ni changement des règles de correction administrative. Les rappels et retours
administratifs passent déjà par `startReturn`, qui libère et replace les réservations.
Trois régressions vérifient l’absence de production rétroactive, les excédents
inactifs, l’idempotence, le prochain round éligible et le carburant conservé.
La synchronisation des commandes échues précède la correction afin de conserver
la production due depuis leur livraison payée. Les 26 cas administratifs existants
et 18 cas économiques précédents réussissent. Les deux premiers cas ajoutés passent
(21 assertions), puis le cas de livraison échue passe (6 assertions) ; l’injection
du conteneur est valide. Ces résultats ciblés ne remplacent pas la nouvelle CI.
La CI n° 297 portait sur la correction des deux attentes navigateur ; l’ajout
métier impose une nouvelle CI complète sur sa propre révision avant fusion.

## Lot 3 — intégration confirmée

[PR #71](https://github.com/c41m07/genesis-reborn/pull/71) fusionnée avec autorisation
au commit `4192821`, après [CI complète n° 299](https://github.com/c41m07/genesis-reborn/actions/runs/43785384598)
réussie sur `e5bb9d6` : cinq jobs quality, GitGuardian, 1 309 PHP / 228 985 assertions,
13 Python et 204 parcours navigateur (51 + 61 + 41 + 51). Compilation, lint, schéma,
diff et sauvegarde/restauration isolée de 60 tables réussis. La seule omission PHP
reste la mesure facultative préexistante. Les CI 297 et 298 ont été remplacées
par les révisions suivantes ; elles ne sont pas présentées comme des validations.
Aucune requalification de base de jeu ni livraison Alpha.

## Lot 4 — analyse et plan, complexité 5/5

Base : dev actualisée `4192821`, arbre propre ; branche
`feature/simulations-progression-economie`, dans le checkout séparé réutilisé.

1. Comparer en mémoire les 156 budgets actuels avec B50/50 et I40/40/20 ; conserver
   le Supérieur, les budgets propres aux défenses et toutes les règles existantes.
2. Exposer une projection de ressources à état explicite, sans lecture de compte,
   utilisant la même simulation segmentée et les mêmes capacités/places civiles.
   Mesurer ses flux cumulés avec conservation des stocks ; aucun effet de bord.
3. Ajouter des parcours déterministes financés : prérequis réels, accès acquis,
   bâtiments/recherche/chantier indépendants, file de recherche unique, paiement
   et bonus au lancement, production pendant les opérations, plafonds et manques.
4. Mesurer missions, carburant, transferts de récolteurs, petites/grandes flottes,
   combats avec unités civiles, pertes et reconstruction. Séparer les infrastructures
   données pour une expérience militaire des parcours démarrant avec les vrais kits.
5. Publier les hypothèses, résultats reproductibles et limites ; tester les invariants
   économiques, la chronologie et l’absence d’accès aux données de jeu. La grille
   candidate reste inactive ; aucune valeur n’est ajustée pour obtenir un résultat.

Risques suivis : copie divergente des formules, arrondis après croissance/remises,
financement non monotone en déficit, capacité insuffisante, doubles facteurs de rang,
production pendant les vols ou après pertes, effet de masse et limites du simulateur.
Les anciennes mesures restent des références datées, pas une validation de ce lot.

## Lot 4 — résultats et vérifications locales

Le [rapport détaillé](SIMULATIONS_PROGRESSION_ECONOMIE.md) décrit les stratégies,
les 54 bilans de ressources et leurs limites. Le [CSV des prix](COMPARAISON_COUTS_CANDIDATS.csv)
compare les 156 profils. Le résultat JSON complet reste dans le dossier ignoré
`output/balance/` ; la commande et l’empreinte de configuration permettent sa reproduction.

- Suite PHP complète sur MariaDB jetable : 1 328 tests / 230 759 assertions,
  une mesure facultative préexistante ignorée ; 13 tests Python réussis.
- Après ajout du refus d’un calendrier de vacances interrompant une mission et
  présentation lisible des nouveaux fichiers : 20 nouveaux tests / 1 776 assertions réussis.
  Les arbres syntaxiques sont comparés avant/après mise en forme.
- Après la dernière relecture des deux services de production : 24 tests ciblés /
  176 assertions réussis (usines, projection et parcours financés).
- `make validate` réussi. Syntaxe PHP (487 fichiers), YAML (80), Twig (170) et
  conteneur Symfony réussis. La relance de `make lint` a d’abord échoué sur une
  adresse non protégée dans la commande de contrôle, puis Docker a été constaté arrêté.
  Les contrôles statiques ont été terminés nativement ; la nouvelle vérification
  du schéma sur base jetable relève donc de la CI complète requise avant fusion.
- Pas de changement de template ou d’asset dans ce lot ; pas de nouvelle suite
  navigateur/compilation locale. La CI de PR complète est obligatoire sur la dernière révision avant fusion.
- `git diff --check` et liens locaux de la documentation vérifiés.
- Les catalogues applicatifs, l’archive historique, les valeurs de jeu et les
  dépendances restent inchangés. Le candidat vit seulement dans un univers en mémoire.
- Dev distante reconsultée : aucun nouveau commit depuis `4192821`. Les six
  modifications étrangères du dossier partagé sont toujours conservées.
- Aucune nouvelle migration SQL, aucune reprise de base de jeu et aucun déploiement Alpha.

## Reprise

Les simulations et le [comparatif chiffré](SIMULATIONS_PROGRESSION_ECONOMIE.md) du
lot 4 sont réalisés : 54 parcours financés, 156 profils / 912 devis de prix,
36 devis de navigation, transferts, reconstructions et combats à graines fixes.
Les 54 bilans de ressources sont conservés. Le commit initial `843f0aa` est publié
par la [PR #72](https://github.com/c41m07/genesis-reborn/pull/72) après autorisation explicite du 16 novembre.
La fusion est autorisée uniquement après CI complète sur la dernière révision ;
les résultats et l’état d’intégration sont consultables directement dans cette PR.
Le candidat reste inactif ; sa décision d’activation est distincte de la publication du code d’étude.

Pour une base existante : maintenance coordonnée, sauvegarde restaurable vérifiée,
diagnostic des surcharges puis autorisation explicite avant la requalification `--apply`.

## Complément — comparaison des coûts par rang, complexité 3/5

Demande explicite : isoler B50/50 puis I40/40/20, sans activation. Base dev `4bbb0cf`,
actualisée et propre ; branche `feature/comparaison-couts-par-rang` dans le checkout
économique réutilisé. Les sources Work nommées restent absentes du dépôt accessible.

Périmètre : sélection du rang dans les seules surcharges d'univers transitoires,
option de commande reproductible, mêmes parcours économiques et militaires,
tests de conservation stricte des profils non sélectionnés, rapport comparatif.
Risques : application involontaire aux deux rangs, variation des stratégies ou du
point de départ, comparaison de dates d'accès avec des dates de livraison.
Aucune formule, valeur applicative, donnée persistée, dépendance ou migration modifiée.

### Résultats du complément par rang

[Rapport](SIMULATIONS_COUTS_PAR_RANG.md) et [108 bilans synthétiques](RESULTATS_COUTS_PAR_RANG.csv).
Les trois commandes exécutent 162 parcours avec répétition des contrôles actuels ;
ces contrôles et les bilans combinés reproduisent exactement les résultats initiaux.
Les 162 bilans de ressources sont conservés (écart maximal inférieur à 0,00000009 unité).
B seul modifie 52 profils B, I seul 52 profils I, sans autre modification de profil.
La recommandation est actualisée : conserver B60/40, privilégier I40/40/20 comme
candidat à valider, S inchangé ; aucune activation ni décision produit implicite.

Contrôles locaux : 22 tests PHP / 2 303 assertions, syntaxe des cinq fichiers PHP,
conteneur Symfony, `make validate`, option répétée identique au défaut combiné,
refus du Supérieur avant simulation. Aucune nouvelle compilation ou suite navigateur
locale pour cet outil sans interface. Les contrôles CI du lot 4 portent sur la version
précédente ; ce complément local n’est pas déclaré validé par cette CI.
Les liens documentaires et le diff sont vérifiés avant commit.

Les vérifications de bout en bout confirment aussi : colonies B identiques au courant
avec I seul, premier accès I inchangé avec I seul, reconstruction des défenses B et
formation B identiques avec I seul, résultats physiques des combats et devis de
navigation strictement identiques entre variantes (mesures de temps machine exclues).
