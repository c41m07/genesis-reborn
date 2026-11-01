# Phase 12 — tableau de bord, cartes d’empire

Date : 01/11/2026. Passe visuelle complémentaire; la phase 12 reste ouverte.

## Ajustement

Les quatre cartes du wireframe sont maintenant reprises dans la vue générale : Planètes, Classement, Flottes en vol et Alliance. Les valeurs viennent des services du jeu : nombre de colonies, rang calculé par le classement, flottes actives, guilde réelle et drapeau de fonctionnalité de l’univers. Les constructions et recherches restent visibles dans le panneau Activité et leurs files backend. Le nouveau calcul de rang est appelé uniquement sur la vue générale.

## Vérifications

- Contrôle de syntaxe PHP du contrôleur et lint Twig de la page réussis.
- Build production réussi : Webpack a compilé 5 fichiers.
- Playwright isolé : dark-dashboard.spec.mjs et ui-routes-responsive.spec.mjs réussis (2/2). Assertions ajoutées sur les quatre cartes, le rang calculé et le lien Diplomatie.
- Edge local : /planetes/82 répond HTTP 200 à 1440, 768 et 390 px; aucune largeur supplémentaire. Valeurs observées dans l’univers de démonstration : 1 planète, rang #1, 0 flotte et guildes désactivées.
- Captures réelles : dashboard-live-metrics-20261101-1440.png, -768.png et -390.png.

## Limites

La carte Alliance montre l’état actuel de l’univers; aucune alliance n’est inventée pour Turbo. Les tests couvrent l’univers Turbo sans guilde et le classement à une entrée. Il reste à vérifier un univers avec plusieurs joueurs et une guilde active, puis les autres états écran par écran.
