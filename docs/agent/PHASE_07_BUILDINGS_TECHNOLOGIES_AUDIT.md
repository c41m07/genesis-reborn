# Phase 07 — bâtiments, technologies et boosts

État : **terminée et validée techniquement**. Mise à jour : 2 novembre 2026.

## Couverture

- Prérequis, catalogue, effets économiques, files de bâtiments et de recherche, annulations et effets de technologie vérifiés côté serveur.
- Les recherches d’une inscription à un univers affectent ses flottes, et les niveaux de recherche PNJ sont transmis au même calculateur. Les défenses fixes restent hors des bonus ciblant les vaisseaux.
- Les boosts d’événement et de mission à risque sont implémentés : attribution idempotente selon la réussite métier, plafond quotidien UTC pour les missions à risque, inventaire et file FIFO en cas de stock plein, consommation et réduction de 25 % du temps restant d’une opération active.
- /boosts est relié au menu Opérations. Les événements hebdomadaires configurés utilisent les contributions réelles d’exploration, de combat et de transport.

## Paramètres d’Alpha

Les niveaux, coûts, durées et bonus choisis figurent dans BALANCING_QCM_2026-11-02.md. Leur effet sur le rythme, les spécialisations et les combats doit être observé pendant l’Alpha. Cette évaluation ne constitue pas une fonctionnalité technique manquante.

## Vérifications

Les tests couvrent la recherche et ses effets, les défenses fixes non affectées, la concurrence et l’idempotence des boosts, les récompenses Top 3/égalité, seuil de contribution, livraison, plafond/FIFO et réinitialisation UTC. Les résultats récents sont dans TEST_STATUS.md.
