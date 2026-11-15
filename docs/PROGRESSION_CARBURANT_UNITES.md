# Progression, carburant et unités économiques

## Périmètre et état — 15 novembre 2026

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
| 2 | Durées, taxe électrique calculée sur la durée effective, carburant différencié, navigation historique préservée | Implémenté et vérifié localement ; publication à autoriser |
| 3 | Six profils civils, production segmentée, mobilité/immobilité, affectations et réservations concurrentes, combat, rapports et simulateur | À réaliser après fusion du lot 2 |
| 4 | Simulations avec les calculateurs réels, comparaison des répartitions, mesures et limites | À réaliser après intégration des unités |

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
- Publication, PR et CI du lot 2 non exécutées à ce stade. Aucune requalification
  d’accès en base de jeu et aucun déploiement Alpha.

## Reprise

Présenter le commit local relu et les contrôles du lot 2 pour autorisation du push, de la PR vers dev et de la fusion après CI complète.
Ne démarrer le lot 3 qu’après cette intégration. Les simulations conjointes restent
au lot 4, sans activer la redistribution des prix candidate.

Pour une base existante : maintenance coordonnée, sauvegarde restaurable vérifiée,
diagnostic des surcharges puis autorisation explicite avant la requalification `--apply`.
