# Phase 12 — passe visuelle 05 : classement

Date : 01/11/2026. Route évaluée : /classement avec session de jeu réelle en environnement local.

## Ajustement

Les actions de contact du tableau prenaient la largeur complète de la colonne. Le prototype les affiche comme des commandes compactes. Une règle locale au tableau limite maintenant le bouton à son contenu; les noms, rangs, points, colonnes, liens de contact et tri backend restent inchangés.

## Vérification

- Compilation production npm run build : réussie (5 fichiers écrits).
- Playwright Chromium connecté à l’application locale : /classement répond 200 à 1440×1000, 768×1024 et 390×844.
- Largeur document / viewport : 1440/1440, 768/768, 390/390; aucun débordement de page.
- Le tableau conserve les 53 commandants présents dans l’environnement de test.
- Le tri ?by=fleet sélectionne bien la colonne « Flotte et défenses »; le lien de contact conserve sa destination /messages?with=….
- Largeur mesurée du premier bouton : 139 px à 1440 px contre 249 px avant le changement; 126 px à 390 px dans la table défilante.

## Captures

- docs/validation/classement-compact-actions-20261101-1440.png
- docs/validation/classement-compact-actions-20261101-768.png
- docs/validation/classement-compact-actions-20261101-390.png

Cette passe valide uniquement la présentation des actions du classement; elle ne clôt pas l’audit de toute la phase 12 ni les phases suivantes.
