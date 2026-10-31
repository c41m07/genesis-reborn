# Phase 03 — architecture et environnement

État : en cours, choix de version Symfony à confirmer.

## Vérifications déjà établies

- Runtime livré : image Docker PHP 8.4, Symfony 7.4, Doctrine ORM 3, MariaDB 11.8, RabbitMQ 4.3 et Mercure 0.18; images Docker épinglées par digest et extension AMQP par version exacte. Les dépendances PHP et JavaScript sont verrouillées par composer.lock et package-lock.json.
- Configuration locale : make configure crée des secrets aléatoires dans .env.local sans écraser le fichier présent; celui-ci est ignoré de Git. Compose exige les secrets et réseaux partagés explicitement. Les commandes start/migrate/import/seed-demo sont séparées.
- Reproductibilité déjà vérifiée : make validate, make lint, make test et make verify-install ont réussi au 31/10/2026. L’installation isolée a appliqué 11 migrations, importé 146 planètes, vérifié la ré-importation idempotente et le redémarrage avec persistance (résultats détaillés dans TEST_STATUS.md).
- Architecture observée : Symfony conventionnel, contrôleurs d’entrée, services de domaine, entités et repositories Doctrine, handlers Messenger, migrations versionnées, outbox pour découpler les notifications. Les validations de conteneur et de schéma Doctrine ont réussi.

## Arbitrage bloquant

La spécification docs/specifications/6 - Choix techniques — Stack complète.txt vise PHP 8.5 et Symfony 8.x. Le dépôt utilise PHP 8.4 et Symfony 7.4. Ces versions actuelles sont compatibles entre elles, mais l’écart avec la cible du document doit être tranché avant de déclarer la phase 03 terminée. Ne pas modifier le runtime ni les dépendances avant la réponse de l’utilisateur.

Au 31/10/2026, Symfony 8.1 est la branche stable et demande PHP 8.4 ou supérieur; Symfony 7.4 est la branche LTS maintenue avec corrections de bugs jusqu’en novembre 2028 et sécurité jusqu’en novembre 2029. Source officielle : https://symfony.com/releases.

## À terminer après l’arbitrage

1. Garder Symfony 7.4 LTS/PHP 8.4 et mettre à jour la spécification de stack; ou migrer vers Symfony 8.x/PHP compatible, puis reprendre l’installation vierge et les contrôles.
2. Mettre à jour les décisions, README et état maître avec la cible retenue.
3. Clore la phase 03 puis passer à la phase 04.
