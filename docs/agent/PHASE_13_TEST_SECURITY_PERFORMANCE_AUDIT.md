# Phase 13 — Tests, sécurité et performance

Date de clôture : 01/11/2026.

## Résultat

Phase validée après revue des tests critiques, des contrôles HTTP et des dépendances. Le périmètre browser a été rendu reproductible avec une base dédiée temporaire, une remise à zéro entre scénarios, un seul worker et un contrôle strict du nom de projet avant suppression.

## Preuves

- PHPUnit : 94 tests, 484 assertions, suite complète réussie.
- Playwright : campagne finale agrégée 24/24 réussie en un run avec un worker, en 10,1 minutes. Le runner réinitialise la base de test entre scénarios et détruit le projet Compose temporaire à la sortie (code 0).
- Scénarios corrigés : assertion de barre fixe devenue relative, navigation recherche renommée, contraste de texte d’aide corrigé.
- Composer audit : aucun advisory après mise à jour de PHPUnit 13.4.5 et Symfony Mime 7.4.22. `npm audit --audit-level=moderate` : aucune vulnérabilité.
- En-têtes de sécurité : test dédié, 1 test / 7 assertions; valeurs strictes préexistantes préservées.
- `make lint`, `make validate`, `npm run build` et `git diff --check` réussis.
- Performance chaude locale (30 requêtes par route, via Traefik) : `/health` p50 10,4 ms / p95 12,0 ms; `/connexion` p50 23,6 ms / p95 32,8 ms. Échantillon sans charge concurrente, sans objectif de capacité production.

## Limites

Pas de test de capacité sous charge, de conformité WCAG/RGAA exhaustive ni de revue d’intrusion indépendante. L’équilibrage du jeu reste à traiter dans l’audit final.
