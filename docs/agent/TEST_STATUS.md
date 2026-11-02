# Validation du projet — 2 novembre 2026

## Derniers résultats

| Vérification | Résultat |
|---|---|
| make test (02/11/2026) | Réussite — 118 tests, 1 696 assertions, après recréation de la base de test éphémère devenue incohérente. |
| make lint (02/11/2026) | Réussite — PHP, 74 fichiers YAML, 39 fichiers Twig, conteneur Symfony et schéma Doctrine. |
| make validate | Réussite — configuration Docker Compose. |
| npm run build (02/11/2026) | Réussite — compilation frontend Encore, 5 bundles. |
| Playwright isolé complet | 21/34 réussis, 13 échecs, 15,8 minutes, 1 worker. Ce résultat n’est pas une campagne verte. |
| Playwright ciblé UI/a11y (2 nov.) | Réussite — 5/5 scénarios : administration catalogue, thème sombre, modération, responsive multi-breakpoints et accessibilité smoke. |
| Playwright, parcours Alpha historiquement en échec (2 nov.) | Réussite — 9/9 : construction/notification, colonie + exploration + transport aller-retour, bénéfices/arbre techno, conquête/rapport privé, espionnage/rapport privé, prévisualisation flotte, assistant flotte, orbite alliée et pages 403/404. Exécutés ensemble dans la base isolée, avec 1 worker et remise à zéro entre scénarios. |
| Liens Markdown relatifs | Vérifiés après la consolidation documentaire. |
| Playwright ciblé progression/navigation (02/11/2026) | Réussite — 4/4 scénarios isolés : menu, arbre dans Compte, recherche, rangs et affichage des éléments verrouillés. Les illustrations de rang ont été rendues dans le catalogue. |
| Playwright ciblé final préférences/flottes (02/11/2026) | Réussite — 2/2 scénarios isolés : enregistrement des préférences depuis Profil et désactivation de l’envoi pendant une estimation en attente ou en erreur. |
| node --check + git diff --check | Réussite sur les scripts JavaScript/MJS modifiés et le diff. |

La campagne Playwright utilise une base et des services jetables. Chaque scénario se lance dans un projet Compose isolé, supprimé en fin d’exécution. Le journal brut de la campagne complète est conservé dans le temporaire local, pas dans le dépôt.

## Échecs de la campagne complète

Les 13 échecs observés avant les ajustements ciblés se répartissaient ainsi :

- Sélecteurs ou navigation non actualisés : onglets admin, libellé Production, étapes du formulaire de flotte, accès à la modération.
- Modèle d’entrée de mission devenu obsolète dans les tests : la destination est saisie par coordonnées, et non choisie dans une liste de planètes.
- Données de scénario insuffisantes : planète PNJ connue, technologies disponibles, et accélération des files.
- Assertions d’interface trop strictes ou périmées : dimensions exactes de l’image, contrôles dans des panneaux details fermés, réponse personnalisée d’accès refusé.
- Le test d’orbite a expiré sur l’ancien formulaire de destination.

Les huit familles de parcours qui restaient à reprendre ont désormais une couverture ciblée concluante (9 tests, car le chantier/colonie couvre plusieurs étapes) : construction/réserve et notifications, arbre technologique, conquête, espionnage, prévisualisation, assistant de flotte, orbite et pages d’erreur. Le lot ciblé a passé 9/9 dans un seul run isolé. La campagne Playwright complète de 34 tests n’a pas été relancée; son dernier résultat agrégé reste 21/34 et ne doit pas être présenté comme un résultat à jour ni comme une campagne verte.

## Lecture des résultats

La réussite PHPUnit et du lint vérifie le comportement couvert côté serveur et la validité de la configuration, mais ne remplace pas les parcours navigateur. La campagne initiale contient des échecs reproductibles, dont plusieurs attendent une réécriture des fixtures pour correspondre à l’interface actuelle.

Les audits manuels d’accessibilité, une exécution CI distante, les tests de charge, la supervision et l’exercice de reprise après sinistre ne sont pas attestés par cette validation locale. Voir ALPHA_READINESS.md, BLOCKERS.md et PHASE_15_FINAL_AUDIT.md.
