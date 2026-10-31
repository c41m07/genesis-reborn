# Phase 03 — architecture et environnement

État : TERMINÉE après arbitrage utilisateur du 31/10/2026.

## Vérifications déjà établies

- Runtime livré : image Docker PHP 8.4, Symfony 7.4, Doctrine ORM 3, MariaDB 11.8, RabbitMQ 4.3 et Mercure 0.18; images Docker épinglées par digest et extension AMQP par version exacte. Les dépendances PHP et JavaScript sont verrouillées par composer.lock et package-lock.json.
- Configuration locale : make configure crée des secrets aléatoires dans .env.local sans écraser le fichier présent; celui-ci est ignoré de Git. Compose exige les secrets et réseaux partagés explicitement. Les commandes start/migrate/import/seed-demo sont séparées.
- Reproductibilité déjà vérifiée : make validate, make lint, make test et make verify-install ont réussi au 31/10/2026. L’installation isolée a appliqué 11 migrations, importé 146 planètes, vérifié la ré-importation idempotente et le redémarrage avec persistance (résultats détaillés dans TEST_STATUS.md).
- Architecture observée : Symfony conventionnel, contrôleurs d’entrée, services de domaine, entités et repositories Doctrine, handlers Messenger, migrations versionnées, outbox pour découpler les notifications. Les validations de conteneur et de schéma Doctrine ont réussi.

## Arbitrage retenu

La spécification docs/specifications/6 - Choix techniques — Stack complète.txt vise PHP 8.5 et Symfony 8.x. Le dépôt utilise PHP 8.4 et Symfony 7.4. L’utilisateur a choisi de conserver Symfony 7.4 LTS et PHP 8.4, puis de mettre à jour la spécification de stack. La combinaison actuelle est maintenue; aucun runtime ni lockfile ne doit être changé dans cette phase.

Au 31/10/2026, Symfony 8.1 est la branche stable et demande PHP 8.4 ou supérieur; Symfony 7.4 est la branche LTS maintenue avec corrections de bugs jusqu’en novembre 2028 et sécurité jusqu’en novembre 2029. Source officielle : https://symfony.com/releases.

La spécification de stack, README, suivi maître et journal de décisions sont synchronisés avec la cible retenue. Les vérifications d’installation et de migrations référencées plus haut précèdent les changements documentaires; aucune modification applicative n’a été nécessaire. Phase 04 — comptes et sécurité — est la prochaine étape.
