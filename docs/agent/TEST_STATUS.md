# Validation du projet

## État vérifié — 5 novembre 2026

L’utilisateur a confirmé la conservation des règles et valeurs actuelles. Aucun fichier de `config/game/` n’est modifié par ces corrections.

| Vérification | Résultat final |
|---|---|
| `make validate` | Réussite — configuration Compose. |
| `make lint` | Réussite — PHP, YAML, Twig, conteneur et schéma Doctrine synchronisé. |
| `make assets` (`npm run build`) | Réussite — compilation de production Encore. |
| `make test` | **192 tests, 202 850 assertions, aucune erreur ni aucun échec.** |
| Playwright, lots isolés et reprises ciblées | **87 scénarios actuels sur 87 ont chacun réussi.** |
| `node --check` sur les tests navigateur modifiés et `git diff --check` | Réussite. |

Le résultat Playwright est une couverture consolidée de plusieurs exécutions, et non une exécution unique entièrement verte de `make test-browser`. Le lot de 75 scénarios a terminé avec 65 réussites et dix échecs ; les dix scénarios concernés ont ensuite passé après correction dans des reprises isolées. Les douze autres scénarios ont également passé dans les lots dédiés au catalogue, à la colonie et aux comptes/flottes. La correspondance des noms de fichiers et de scénarios a été vérifiée avec les 87 entrées de `npx playwright test --list`. Les lots et reprises utilisent `bash scripts/test-browser-isolated.sh <fichiers>`, éventuellement avec `--grep`, chacun dans une base temporaire distincte. Tous ces environnements temporaires ont été retirés à leur arrêt.

Corrections applicatives :

- Espionnage : accès à l’univers par le système solaire et la galaxie de la planète, dans les quatre catégories de l’instantané. Un nouveau test déterministe vérifie les libellés surchargés par univers ; les trois tests ciblés passent (31 assertions). Le parcours navigateur PNJ passe également. Le worker local a été redémarré après validation.
- Schéma : migration `Version20261105181020` appliquée en local, retirant les commentaires de type hérités sur les dates de `patch_note` et `user_feedback`, et alignant deux noms d’index. Aucune table ni colonne supprimée. Les nouvelles bases navigateur ont également exécuté cette migration.
- Catalogue administrateur : les technologies de statistiques et de cadence exposent le plafond commun réellement utilisé, à la place de l’ancien effet par niveau ignoré. Le test d’intégration vérifie la modification effective du bonus et l’isolation entre univers.
- Accessibilité : le nom accessible du résumé « Renommer cette planète » inclut désormais son texte visible et le nom de la colonie. L’audit axe des écrans applicatifs passe à 1440 et 390 px.

Les tests obsolètes ont été adaptés aux prérequis achevés, aux rendements décroissants, aux statistiques R14, aux coûts actuels, au ratio de protection et aux durées contrôlées des scénarios de boost. Les parcours navigateur utilisent les libellés et sélecteurs actuels, les recherches de joueurs/guildes et les trois onglets d’alliances. La consultation du laboratoire et du chantier dans les scénarios utilise l’option existante d’affichage des éléments verrouillés ; leur visibilité dans le jeu n’a pas été modifiée.

La campagne PHP avant correction comptait 191 tests, douze échecs et quatre erreurs. La première campagne navigateur avait été arrêtée pour corriger les attentes obsolètes : 22 réussites, six échecs, un scénario interrompu et 58 non exécutés. Ces résultats sont remplacés par le bilan consolidé ci-dessus. Les journaux bruts et captures des nouvelles exécutions restent dans les temporaires locaux, sans ajout d’illustrations au dépôt.

Les résultats du 2 novembre ci-dessous sont conservés comme historique.

## Derniers résultats

Les résultats ci-dessous sont des exécutions historiques du 02/11/2026 et ne prouvent pas l’état actuel. Lors du nettoyage, avant ses changements applicatifs, les commandes `make test` et `make test-browser` ont été relancées sur l’arbre de travail alors déjà modifié; elles ont révélé des échecs. Les journaux détaillés de cette exécution sont dans l’historique de la session Codex, pas archivés comme preuves dans le dépôt.

| Vérification au début du nettoyage (02/11/2026) | Résultat |
|---|---|
| make test | 126 tests, 2 303 assertions, 3 erreurs et 14 échecs; conflits d’attentes avec les valeurs et comportements présents dans l’arbre de travail. |
| make test-browser | 30/36 scénarios réussis. Six échecs liés aux attentes du catalogue, des prérequis, de la colonisation et à une assertion d’image ensuite retirée. Le test responsive corrigé a ensuite passé seul (1/1). |
| make validate, make lint, npm run build | Réussite avant et après le retrait des images et l’adaptation des vues. |

| Vérification | Résultat |
|---|---|
| make test (02/11/2026) | Réussite — 118 tests, 1 696 assertions, après recréation de la base de test éphémère devenue incohérente. |
| make lint (02/11/2026) | Réussite — PHP, 74 fichiers YAML, 39 fichiers Twig, conteneur Symfony et schéma Doctrine. |
| make validate | Réussite — configuration Docker Compose. |
| npm run build (02/11/2026) | Réussite — compilation frontend Encore, 5 bundles. |
| Playwright isolé complet final (02/11/2026) | Réussite — 35/35 scénarios, 10,5 minutes, 1 worker, base et services isolés. |
| Playwright ciblé UI/a11y (2 nov.) | Réussite — 5/5 scénarios : administration catalogue, thème sombre, modération, responsive multi-breakpoints et accessibilité smoke. |
| Playwright, parcours Alpha historiquement en échec (2 nov.) | Réussite — 9/9 : construction/notification, colonie + exploration + transport aller-retour, bénéfices/arbre techno, conquête/rapport privé, espionnage/rapport privé, prévisualisation flotte, assistant flotte, orbite alliée et pages 403/404. Exécutés ensemble dans la base isolée, avec 1 worker et remise à zéro entre scénarios. |
| Liens Markdown relatifs | Vérifiés après la consolidation documentaire. |
| Playwright ciblé progression/navigation (02/11/2026) | Réussite — 4/4 scénarios isolés : menu, arbre dans Compte, recherche, rangs et affichage des éléments verrouillés. Les illustrations de rang ont été rendues dans le catalogue. |
| Playwright ciblé final préférences/flottes (02/11/2026) | Réussite — 2/2 scénarios isolés : enregistrement des préférences depuis Profil et désactivation de l’envoi pendant une estimation en attente ou en erreur. |
| node --check + git diff --check | Réussite sur les scripts JavaScript/MJS modifiés et le diff. |

La campagne Playwright utilise une base et des services jetables. Chaque scénario se lance dans un projet Compose isolé, supprimé en fin d’exécution. Le journal brut de la campagne complète est conservé dans le temporaire local, pas dans le dépôt.

## Historique des échecs corrigés

Une campagne antérieure de 34 scénarios avait enregistré 21 réussites et 13 échecs. Après les mises à jour des attentes et des fixtures, la campagne complète finale a passé 35/35. Les 13 échecs historiques se répartissaient ainsi :

- Sélecteurs ou navigation non actualisés : onglets admin, libellé Production, étapes du formulaire de flotte, accès à la modération.
- Modèle d’entrée de mission devenu obsolète dans les tests : la destination est saisie par coordonnées, et non choisie dans une liste de planètes.
- Données de scénario insuffisantes : planète PNJ connue, technologies disponibles, et accélération des files.
- Assertions d’interface trop strictes ou périmées : dimensions exactes de l’image, contrôles dans des panneaux details fermés, réponse personnalisée d’accès refusé.
- Le test d’orbite a expiré sur l’ancien formulaire de destination.

Les scénarios corrigés pour les trois rangs et la navigation ont également passé dans la campagne finale. Les captures actualisées couvrent le thème clair/sombre, les menus, les rapports, les flottes, les rangs visibles et les écrans mobile.

## Lecture des résultats

La réussite PHPUnit et du lint vérifie le comportement couvert côté serveur et la validité de la configuration, mais ne remplace pas les parcours navigateur. La campagne initiale contient des échecs reproductibles, dont plusieurs attendent une réécriture des fixtures pour correspondre à l’interface actuelle.

Les audits manuels d’accessibilité, une exécution CI distante, les tests de charge, la supervision et l’exercice de reprise après sinistre ne sont pas attestés par cette validation locale. Voir ALPHA_READINESS.md, BLOCKERS.md et PHASE_15_FINAL_AUDIT.md.
