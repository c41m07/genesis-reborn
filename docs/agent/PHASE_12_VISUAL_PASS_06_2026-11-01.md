# Phase 12 — passe visuelle 06 : messagerie

Date : 01/11/2026. Route évaluée : /messages avec la boîte de réception locale de 52 conversations (données de test déjà présentes).

## Écart corrigé

Avec une boîte de réception longue, la grille étirait simultanément la liste et le panneau de conversation à plus de 3 600 px de haut. Le wireframe garde une colonne de conversations et un panneau de fil stables. La liste est maintenant défilable indépendamment, tandis que le fil reste dans une hauteur de travail bornée. Sur mobile, la liste conserve sa zone défilable de 220 px au-dessus du fil empilé.

Aucun message n’a été envoyé; la conversation ouverte pour contrôle était une lecture seule. Les contrôleurs et formulaires backend restent inchangés.

## Vérification

- Compilation production npm run build : réussie (5 fichiers écrits).
- Playwright Chromium connecté : /messages répond 200 à 1440×1000, 768×1024 et 390×844.
- Largeur document / viewport : 1440/1440, 768/768, 390/390.
- Avant : liste et panneau à 3 664 px de haut sur desktop. Après : 500 px chacun; à 390 px, liste 220 px et fil 340 px.
- La liste se défile indépendamment (scrollTop mesuré à 1200 px sur mobile).
- L’ouverture d’une conversation conserve l’URL /messages?with=… et le compositeur réel est présent.

## Captures

- docs/validation/messagerie-scrollable-20261101-1440.png
- docs/validation/messagerie-scrollable-20261101-768.png
- docs/validation/messagerie-scrollable-20261101-390.png
- docs/validation/messagerie-fil-scrollable-20261101-1440.png
- docs/validation/messagerie-liste-defilement-mobile-20261101.png

Cette passe valide la disposition et le défilement de la boîte de réception. Elle ne clôt pas la phase 12 ni l’audit complet des fonctionnalités.
