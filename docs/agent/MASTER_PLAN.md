# Genesis Reborn — plan maître

## Objectif et sources de vérité

Le jeu doit préserver les décisions consignées dans docs/REGLES.md et docs/DECISIONS.md, puis les huit sources conservées dans docs/specifications/ et docs/technic/. L’index de documentation est dans docs/README.md. Les valeurs initiales choisies pour l’Alpha sont détaillées dans BALANCING_QCM_2026-11-02.md; les résultats des joueurs détermineront les ajustements.

## État des phases

Les quinze phases ont un audit. Le statut strict du dernier audit est 14/15 validées; la phase 12 reste partielle uniquement pour l’audit manuel d’accessibilité exhaustive. Ce statut ne signifie pas une préparation à la production.

| Phase | Domaine | État |
|---|---|---|
| 01 | Audit du dépôt | Audité; inventaire et architecture consignés. |
| 02 | Spécifications et règles | Décisions de gameplay et rythme modéré consignés; voir PHASE_02_DECISIONS.md. |
| 03 | Architecture et environnement | Installation isolée, migrations et reprise documentées. |
| 04 | Comptes et sécurité | Authentification, récupération, sessions, permissions et préférences vérifiés. |
| 05 | Univers et planètes | Import, accès et fermeture asynchrone documentés. |
| 06 | Économie | Calculs, chronologie, plafonds, dépenses et remboursement vérifiés; rythme empirique à observer en Alpha. |
| 07 | Bâtiments, technologies et boosts | Files, effets et récompenses événementielles reliés et testés. |
| 08 | Flottes et missions | Trajets, carburant, composition, départ/retour et missions vérifiés. |
| 09 | Combat | Rounds persistés à raison d’un par minute, jusqu’à dix ou clôture anticipée; équilibre à observer. |
| 10 | Social et stratégie | Diplomatie, classement, messages et protections vérifiés. |
| 11 | Administration | Modération, réglages par univers, audit et restauration de catalogues vérifiés. |
| 12 | UI/UX et accessibilité | Refonte et principaux parcours livrés; audit manuel avec lecteur d’écran reste partiel. |
| 13 | Tests, sécurité et performance | Suites locales et contrôles applicatifs documentés; charge soutenue non prouvée. |
| 14 | Exploitation et documentation | Runbook, CI locale, sauvegarde/restauration jetable vérifiés; exécution CI distante non prouvée. |
| 15 | Audit final | Rapport et limites de production consignés dans PHASE_15_FINAL_AUDIT.md. |

## Validation courante

Les résultats de test datés et les limites sont maintenus dans TEST_STATUS.md. Les prochaines décisions d’équilibrage dépendent des retours et mesures de l’Alpha; aucune valeur confirmée n’est changée automatiquement.
