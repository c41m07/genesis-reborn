# Runbook d’exploitation Genesis Reborn

## Vérifier services et application

1. Créer les secrets locaux une fois avec `make configure`; ne jamais partager `.env.local`.
2. Lancer `make doctor`, `make validate`, puis `make start`.
3. Vérifier `make status`, `make console CMD=about` et le endpoint `/health`. Compose contrôle MariaDB, RabbitMQ, Mercure, worker, relay et notifier.
4. Appliquer `make migrate` et `make import` uniquement lors d’une installation ou opération planifiée.
5. Lire `make logs` sans exposer de secrets ou données de compte.

## Sauvegarder

`make backup` produit une archive SQL gzip et son SHA-256 dans `var/backups/`. `BACKUP_DIR` peut cibler un emplacement externe protégé. Les archives contiennent des données personnelles; l’opérateur doit fournir chiffrement, accès et rétention adaptés. Les volumes RabbitMQ et Mercure ne sont pas sauvegardés; les événements métier persistants sont dans la base et les messages transport peuvent être reconstruits.

## Restaurer — opération destructive

1. Vérifier cible, archive, checksum, provenance et âge.
2. Arrêter les écritures et prendre une sauvegarde de la base cible.
3. Définir `BACKUP_FILE=/chemin/archive.sql.gz` et `RESTORE_CONFIRM=genesis_reborn_v2`, puis exécuter `make restore`. Le nom confirmé doit correspondre au projet ciblé.
4. Vérifier migrations, healthchecks et données restaurées, puis redémarrer les consommateurs.

La preuve de phase utilise un projet jetable unique; la base de travail `genesis_reborn_v2` n’est jamais utilisée pour le test destructif.

## Limites

La CI ne déploie ni ne publie. Le dépôt ne configure pas de stockage distant, chiffrement des sauvegardes, rétention, monitoring externe ou plan de reprise après sinistre. Les timings de phase 13 sont des échantillons locaux, sans SLA.
