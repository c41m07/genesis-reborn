# Phase 12 — passe visuelle 07 : Galaxie

Date : 01/11/2026. Comparaison du wireframe publié avec la route locale /carte.

## Résultat de la revue

La page reprend la hiérarchie du wireframe : contrôles de galaxie et de système, légende, table des positions, puis note sur les actions possibles. À 1440 et 768 px, le tableau est entièrement visible. À 390 px, la table défile horizontalement dans son panneau sans élargir la page.

Le contenu local reste fourni par les données de l’univers. Le système 0:0 contient 3 positions et le système 3:0 en contient 5; les valeurs et actions fictives de la maquette ne sont pas reprises.

## Vérification

- Playwright Chromium connecté : /carte répond 200 à 1440×1000, 768×1024 et 390×844.
- Largeur document / viewport : 1440/1440, 768/768 et 390/390.
- La sélection du système 3:0 via le contrôle existant ouvre /carte?galaxy=2&system=14, affiche le titre « Système 3:0 · Système 02 » et les 5 positions du système.
- Aucune modification de code n’était requise sur cette page pendant cette passe.

## Captures

- docs/validation/galaxie-audit-20261101-1440.png
- docs/validation/galaxie-audit-20261101-768.png
- docs/validation/galaxie-audit-20261101-390.png
- docs/validation/galaxie-systeme-14-20261101-390.png

Cette revue ne clôt pas l’audit visuel global ni la phase 12.
