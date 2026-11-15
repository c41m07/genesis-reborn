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
| 1 | 150 profils de prérequis, registre versionné, diagnostic et requalification explicite, interface et documentation associées | PR #69 ouverte ; corrections de recette en validation |
| 2 | Durées, taxe électrique calculée sur la durée effective, carburant différencié, navigation historique préservée | À réaliser après fusion du lot 1 |
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

## Vérifications et limites

- `make validate` : réussi avant les modifications.
- `make lint` : réussi (PHP, YAML, Twig, conteneur, mapping et schéma).
- `make test` sur `510610f` : réussi, 1 205 tests PHP, 214 139 assertions et 13 tests Python.
  Une mesure de préparation facultative préexistante est ignorée par défaut
  (`REWORK_MEASURE=1` pour l’activer) ; aucun test métier n’est désactivé.
- Parcours navigateur ciblés : 9 scénarios réussis sur la première passe, puis
  les 2 scénarios actualisés réussis sur leur relance (11 scénarios distincts).
  Fiches des 150 profils, liens de rang, guide et focus clavier, affichages mobile
  et ordinateur, recherche commune, opérations historiques et wiki couverts.
  La suite navigateur complète reste à exécuter en CI.
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
- Aucune requalification appliquée à la base de jeu. [PR #69](https://github.com/c41m07/genesis-reborn/pull/69) ouverte ; aucune fusion à ce stade.

## Reprise

Publication et fusion du lot 1 autorisées, sous réserve de CI complète réussie.
Attendre la réussite des cinq jobs quality sur la dernière version de la PR #69,
puis fusionner vers dev et commencer le lot 2 depuis dev actualisée.
Pour une base existante : maintenance coordonnée, sauvegarde restaurable vérifiée,
diagnostic des surcharges puis autorisation explicite avant `--apply`.

## Recette CI du lot 1

Le premier passage CI a validé les lots navigateur 3 et 4. Les lots 1 et 2
ont révélé quatre préparations de test fondées sur l’ancienne grille : métal
sans nano pour le chantier, ancienne passerelle de centrale et chantier
Intermédiaire absent. Les préparations sont actualisées sans retrait d’assertion.
Le cas d’unités possédées mais non débloquées vérifie désormais explicitement
que leur quantité reste lisible et qu’aucun formulaire de construction ne s’ouvre.

Le contrôle PHP s’est arrêté avant PHPUnit, la base isolée database-test étant
unhealthy. Ses journaux sont ajoutés aux diagnostics CI en cas d’échec ; aucun
contrôle n’est affaibli. Une nouvelle CI complète doit valider le lot corrigé.

Corrections de recette vérifiées localement : 4/4 scénarios navigateur concernés,
16 tests PHP ciblés / 133 assertions, make lint et git diff --check réussis.
La nouvelle CI complète doit confirmer ces corrections sur leur dernier commit.
