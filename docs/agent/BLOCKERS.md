# Genesis Reborn — blocages et travaux restants

## Verdict au 01/11/2026

Les phases 02, 07 et 09 sont validées techniquement; seule la phase 12 reste partielle pour l’audit manuel d’accessibilité exhaustif. **Genesis Reborn est prêt pour une Alpha Test, mais pas pour la production.** Le rapport détaillé est `PHASE_15_FINAL_AUDIT.md`; les lignes partielles restent dans `REQUIREMENTS_MATRIX.md`.

## Blocages avant production

- Équilibrage : coûts, production, durées, stocks initiaux, kit de colonie, coefficients, compositions/statistiques PNJ restent provisoires; orientation de rythme confirmée : stratégie modérée. Les valeurs finales et leur validation restent à faire.
- CI : workflow localement parsé, job contrôlé, mais aucun run GitHub (branche non publiée).
- Performance : 30 requêtes chaudes locales par route uniquement, aucune mesure de charge/capacité ou SLA.
- États UI : couverture écran par écran documentée; exceptions internes non injectées listées dans UI_STATE_COVERAGE.md.
- Exploitation : round-trip local vérifié mais pas de backup distant/chiffré, rétention, monitoring ni PRA.

## État des données

Aucun déploiement ou push n’a eu lieu. La restauration destructive a été validée uniquement dans un projet MariaDB jetable et n’a pas modifié la base locale du site. Les changements du dépôt sont conservés sur la branche de travail.

## État Alpha — 01/11/2026

La boucle jouable minimale est vérifiée; le verdict et les preuves de préparation sont consignés dans ALPHA_TEST_READINESS.md. Les travaux listés plus haut sont des bloqueurs de production et ne bloquent pas l’Alpha.
