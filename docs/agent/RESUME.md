# Genesis Reborn — reprise

## Démarrage de la prochaine session
1. Accéder à /home/caim/Projets/Genesis_Reborn.
2. Lire ce fichier, docs/agent/PROGRESS.md, MASTER_PLAN.md, BLOCKERS.md et docs/DECISIONS.md.
3. Contrôler git status --short --branch; travailler sur feat/genesis-reborn-production-completion. Ne pas écraser les modifications préexistantes indiquées dans PROGRESS.
4. Lire docs/REGLES.md, docs/LIVRAISON-DOMAINES-UI.md, les sections combat des spécifications 1 et 2 et les trajets pertinents du document 3.
5. Examiner les entités Fleet/FleetShip, FleetService/MovementCalculator, messages/handlers, schéma, migrations et tests avant toute extension.
6. Mettre à jour la matrice d'acceptation 08/09 avant d'écrire les changements. Commencer par la première tâche inachevée; ne pas refaire le travail vérifié.

## État de reprise
- Dernier HEAD connu : 80668fa; vérifier qu'il est toujours le même.
- Dernière validation : 51 tests / 189 assertions; lint et make validate réussis le 31/10.
- Aucun commit créé pendant cette reprise.
- Aucun déploiement. Pas de mécanisme capable de réveiller une session de code avec accès au dépôt après quota; reprise manuelle documentée.
