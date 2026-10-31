# Genesis Reborn — reprise

## Démarrage de la prochaine session
1. Accéder à /home/caim/Projets/Genesis_Reborn.
2. Lire ce fichier, docs/agent/PROGRESS.md, MASTER_PLAN.md, BLOCKERS.md et docs/DECISIONS.md.
3. Contrôler git status --short --branch; travailler sur feat/genesis-reborn-production-completion. Ne pas écraser les modifications préexistantes indiquées dans PROGRESS.
4. Lire docs/REGLES.md, docs/LIVRAISON-DOMAINES-UI.md, les sections combat des spécifications 1 et 2 et les trajets pertinents du document 3; lire aussi docs/agent/PHASE_02_COMBAT_CRITERIA.md et PHASE_02_DECISIONS.md.
5. Examiner les entités Fleet/FleetShip, FleetService/MovementCalculator, messages/handlers, schéma, migrations et tests avant toute extension.
6. Mettre à jour la matrice d'acceptation 08/09 avant d'écrire les changements. Commencer par la première tâche inachevée; ne pas refaire le travail vérifié.

## État de reprise
- HEAD de départ : 80668fa; plusieurs commits locaux de suivi sont postérieurs, vérifier git log.
- Dernière validation : 61 tests / 274 assertions; make lint réussi (58 YAML, 16 Twig, conteneur et schéma Doctrine). Installation vierge avec 8 migrations, ré-import idempotent et redémarrage réussis après l’ajout du retour plafonné. Le butin ciblé passe l’intégration; voir docs/agent/TEST_STATUS.md. Reprendre par la consolidation et l'implémentation de la conquête, sans refaire le combat et l’orbite.
- Un commit de documentation de reprise existe; vérifier git log pour les changements après cette note.
- Aucun déploiement. Pas de mécanisme capable de réveiller une session de code avec accès au dépôt après quota; reprise manuelle documentée.
