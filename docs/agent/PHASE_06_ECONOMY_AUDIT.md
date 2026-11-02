# Phase 06 — économie

Mise à jour : 2 novembre 2026. La phase économique est techniquement validée; les valeurs chiffrées forment le point de départ du test Alpha.

## Règles et calculs vérifiés

- Production des ressources calculée côté serveur à partir du stock, du taux et de l’instant de matérialisation.
- Reprise chronologique des files avant lecture ou dépense, avec l’ancien taux appliqué jusqu’à l’échéance de l’amélioration et les plafonds de stockage respectés.
- Dépenses concurrentes protégées par verrou; remboursements d’annulation fixés à 25 % des coûts effectivement payés et limités par la capacité de stockage.
- La consommation énergétique des extracteurs est appliquée avant de réduire proportionnellement la production minière lorsque l’énergie disponible ne couvre pas la demande.
- Les boosts et événements sont documentés dans PHASE_07_BUILDINGS_TECHNOLOGIES_AUDIT.md; leur moteur n’est plus un travail restant de phase 06.

## Valeurs et mesure

Les stocks de départ, productions, coûts, durées et réductions ont été choisis par Kévin et appliqués aux catalogues pour l’Alpha. La stratégie retenue est modérée. Mesurer avec les joueurs les ressources limitantes, les temps d’attente, les jalons de progression, les effets de l’entretien énergétique et la capacité de récupération après revers. Les choix initiaux et calculs dérivés figurent dans BALANCING_QCM_2026-11-02.md.

## Preuves

Les tests ciblés de chronologie, stockage, concurrence, dépenses et remboursement sont couverts dans GamePersistenceTest et GameRulesTest. L’état courant de la suite complète et des contrôles est dans TEST_STATUS.md.
