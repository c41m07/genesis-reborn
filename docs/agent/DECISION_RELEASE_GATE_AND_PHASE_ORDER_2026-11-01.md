# Décision — équilibrage obligatoire avant production et ordre de reprise

Date : 2026-11-01. Décision confirmée par Kévin.

## Verrou de mise en production

La feuille d’équilibrage doit être construite, simulée et validée avant toute mise en production. Elle doit couvrir au minimum les coûts, productions, durées, stocks initiaux, kit de colonie, coefficients de progression et de carburant, ainsi que les compositions et statistiques des PNJ. Les valeurs actuellement provisoires ne deviennent pas définitives sans cette validation.

Cette feuille et son approbation sont un critère de sortie vers la production; elles ne doivent pas être déclarées terminées sur la seule base de l’implémentation technique.

## Ordre de reprise

1. Terminer et documenter les critères encore ouverts de la phase 12 (UI/UX et accessibilité).
2. Une fois la phase 12 strictement validée, reprendre la phase 07 pour raccorder l’accès et le parcours UI des boosts sur /boosts et valider cette intégration.
3. Conserver la phase 09 (rounds de combat temps réel) comme chantier ouvert distinct.
4. Avant toute production, réaliser les simulations et obtenir la validation de la feuille d’équilibrage décrite ci-dessus, puis lever les autres blocages de lancement consignés dans BLOCKERS.md et PHASE_15_FINAL_AUDIT.md.

## État au moment de la décision

La matrice REQUIREMENTS_MATRIX.md classe encore les critères exhaustifs d’états UI et d’accessibilité de la phase 12 comme partiels. Les passes visuelles Technologies/Flottes et thème de novembre 2026 apportent des preuves supplémentaires, mais ne suffisent pas à fermer ces critères. La phase 07 n’est donc pas reprise dans cet ordre avant la clôture stricte de la phase 12.
