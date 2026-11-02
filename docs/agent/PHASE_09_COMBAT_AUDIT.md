# Phase 09 — combat

État : **terminée et validée techniquement**. Mise à jour : 2 novembre 2026.

## Règles appliquées

- Un round de combat est exécuté par minute, jusqu’à dix rounds ou élimination anticipée d’un camp.
- Les renforts rejoignent le prochain round non résolu admissible selon leur heure d’arrivée; une flotte arrivée après la clôture repart sans participer.
- Les combats sont persistés, déterministes à partir de leur graine et traités de manière idempotente.
- Les pertes, rapports privés, butin, retraite, conquête JcE PNJ, retours et notifications sont intégrés.
- Structure et bouclier sont des statistiques distinctes. Les boucliers absorbent les dégâts avant la structure, se rechargent entre rounds selon la configuration, et les survivants sont restaurés après combat.
- Robotique reconstruit la proportion décidée des défenses; les vaisseaux détruits ne sont pas récupérés.

## Équilibrage Alpha

Les statistiques, précisions, suivi, chances de toucher, matrice de matchups, boucliers et profils PNJ sont choisis dans BALANCING_QCM_2026-11-02.md et reliés aux catalogues. Les résultats des joueurs Alpha permettront de mesurer la durée, l’issue, les pertes et le temps de récupération. Ne pas extrapoler l’équilibre à partir du seul passage des tests.

## Vérification

Les tests de domaine et de persistance couvrent les rounds, les renforts, les limites d’arrivée, l’idempotence, les pertes, le butin et la conquête. La campagne navigateur et ses résultats les plus récents sont consignés dans TEST_STATUS.md.
