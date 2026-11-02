# Validation du projet — 2 novembre 2026

## Derniers résultats

| Vérification | Résultat |
|---|---|
| make test | Réussite — 110 tests, 627 assertions. |
| make lint | Réussite — PHP, 67 fichiers YAML, 37 fichiers Twig, conteneur Symfony et schéma Doctrine. |
| make validate | Réussite — configuration Docker Compose. |
| npm run build | Réussite — compilation frontend Encore, 5 bundles. |
| Playwright isolé complet | 21/34 réussis, 13 échecs, 15,8 minutes, 1 worker. Ce résultat n’est pas une campagne verte. |
| Playwright ciblé après mise à jour des attentes | Réussite — 5/5 scénarios : administration catalogue, thème sombre, modération, responsive multi-breakpoints et accessibilité smoke. Modération réussie au rejeu après correction d’un onglet fermé au rechargement. |
| Liens Markdown relatifs | Vérifiés après la consolidation documentaire. |
| git diff --check | À relancer après les derniers changements documentaires. |

La campagne Playwright utilise une base et des services jetables. Chaque scénario se lance dans un projet Compose isolé, supprimé en fin d’exécution. Le journal brut de la campagne complète est conservé dans le temporaire local, pas dans le dépôt.

## Échecs de la campagne complète

Les 13 échecs observés avant les ajustements ciblés se répartissaient ainsi :

- Sélecteurs ou navigation non actualisés : onglets admin, libellé Production, étapes du formulaire de flotte, accès à la modération.
- Modèle d’entrée de mission devenu obsolète dans les tests : la destination est saisie par coordonnées, et non choisie dans une liste de planètes.
- Données de scénario insuffisantes : planète PNJ connue, technologies disponibles, et accélération des files.
- Assertions d’interface trop strictes ou périmées : dimensions exactes de l’image, contrôles dans des panneaux details fermés, réponse personnalisée d’accès refusé.
- Le test d’orbite a expiré sur l’ancien formulaire de destination.

Cinq scénarios ciblés ont été rejoués avec succès après mise à jour de leurs attentes. Huit échecs de la campagne complète restent sans rejeu ciblé concluant : construction/réserve, arbre technologique, conquête, espionnage, prévisualisation de flotte, assistant de flotte, orbite et page d’erreur. La campagne de 34 tests n’a pas été relancée intégralement après les corrections ciblées; son dernier résultat agrégé reste 21/34.

## Lecture des résultats

La réussite PHPUnit et du lint vérifie le comportement couvert côté serveur et la validité de la configuration, mais ne remplace pas les parcours navigateur. La campagne initiale contient des échecs reproductibles, dont plusieurs attendent une réécriture des fixtures pour correspondre à l’interface actuelle.

Les audits manuels d’accessibilité, une exécution CI distante, les tests de charge, la supervision et l’exercice de reprise après sinistre ne sont pas attestés par cette validation locale. Voir ALPHA_READINESS.md, BLOCKERS.md et PHASE_15_FINAL_AUDIT.md.
