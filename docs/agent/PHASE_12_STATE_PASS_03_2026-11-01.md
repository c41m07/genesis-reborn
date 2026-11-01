# Phase 12 — état vide Espionnage

Date : 2026-11-01.

## Correction

Quand aucune flotte disponible n’existe, l’écran Espionnage affiche désormais un état vide dédié : prérequis de sonde explicite et lien « Accéder au chantier spatial » vers la route réelle de la colonie active. La vue Flottes générale conserve son propre texte d’absence de vaisseaux et utilise le même accès au chantier. Aucune mission ni donnée métier fictive n’est créée.

## Vérifications

- fleet-empty-state.spec.mjs : 1/1 sur la stack isolée.
- Le compte de test sans flotte ouvre /flottes?mission=spy, voit l’état attendu, suit le lien vers /planetes/{id}?section=ships et y trouve le titre du chantier spatial.
- À 390×844, la barre latérale est fermée après sa transition et le document ne dépasse pas la largeur du viewport.
- Captures prises après stabilisation :
  - docs/validation/espionnage-sans-flotte-20261101.png
  - docs/validation/espionnage-sans-flotte-mobile-20261101.png

## Limite

Cette preuve couvre l’état sans flotte et son CTA sur desktop/mobile. Les états d’erreur, indisponibilité et chargement des autres parcours restent à inventorier; P12-STATE-01 n’est pas clôturé.
