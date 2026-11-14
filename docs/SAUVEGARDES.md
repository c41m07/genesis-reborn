# Sauvegardes et restauration isolée

## Périmètre et état vérifié

Les scripts sauvegardent la base MariaDB de l’application : comptes, mots de passe hachés, appartenances aux univers, ressources, niveaux, flottes, files et données sociales. Ils ne sauvegardent ni les fichiers de configuration privée, ni les images applicatives, ni les volumes RabbitMQ/Mercure. Conserver séparément la version du code ou le digest d’image et les secrets nécessaires, hors du dépôt.

Une restauration a été réellement exécutée sur une nouvelle instance locale isolée : 38 tables comparées intégralement par empreinte des lignes, source inchangée, compte vérifié et mot de passe contrôlé, ressources, bâtiments, recherche, vaisseaux, file en cours et outbox identiques. Ce résultat valide les données de ce scénario, pas un PRA complet de l’Alpha ni une reprise de tous les traitements asynchrones.

## Prérequis et configuration

Exécuter depuis Debian/WSL ou un hôte Linux avec Bash, Python 3 (bibliothèque standard), Docker Compose, gzip, sha256sum, mktemp et flock. Les identifiants de base restent dans le fichier d’environnement privé du déploiement ; les scripts utilisent les variables du conteneur database sans inscrire de secret dans le dépôt. Aucun fournisseur de stockage distant ni emplacement serveur n’est supposé.

| Variable de l’environnement du job hôte | Défaut | Rôle |
|---|---|---|
| `GENESIS_PROJECT` | `genesis_reborn_v2` | Projet Compose ciblé ; le préciser pour un autre environnement. |
| `GENESIS_COMPOSE_FILE` | `compose.yaml` | Configuration du projet sauvegardé/restauré. |
| `GENESIS_ENV_FILE` | `.env.local` | Fichier utilisé par Compose, sans être sourcé par le shell. |
| `BACKUP_DIR` | `var/backups` dans le dépôt | Dossier privé dédié aux archives ; éventuellement un stockage monté. |
| `BACKUP_RETENTION_DAYS` | `14` | Conservation de 14 périodes de 24 heures, configurable. |
| `BACKUP_MAX_AGE_SECONDS` | `129600` | Ancienneté maximale du dernier succès : 36 heures. |
| `BACKUP_MAX_RUN_SECONDS` | `3600` | Détecter un job resté en cours au-delà d’une heure, sans le tuer. |

Ces paramètres de job doivent être exportés par le planificateur ou fournis à la commande. Les ajouter dans le fichier env_file de Compose ne les exporte pas automatiquement dans le shell hôte. Les changements de seuil de conservation n’affectent aucune règle de jeu.

## Créer et conserver une sauvegarde

`make backup` lance le script réel de sauvegarde. Choisir explicitement les paramètres du projet avant de le lancer sur un environnement hébergé. Le dump utilise `--single-transaction`, les routines, événements et déclencheurs. Coordonner les migrations et autres changements de schéma avec l’opérateur ; ne pas en lancer pendant le dump. L’import contient un remplacement de la base nommée, donc il est destructif pour la cible de restauration.

Le dossier est privé (0700), les archives et manifestes sont en 0600. Un verrou empêche deux sauvegardes simultanées du même projet dans ce dossier. Le nom associe date UTC et suffixe aléatoire ; deux exécutions la même seconde ne s’écrasent plus. Le gzip temporaire est vérifié avant publication. Le manifeste SHA-256 doit désigner exactement le fichier choisi et son contenu ; il ne peut plus valider un autre fichier à la place.

La rétention s’exécute uniquement après création et vérification d’une nouvelle archive. Elle retire les paires archive/manifeste validées du seul projet, directement dans le dossier configuré, dont la date de modification dépasse le délai. Elle conserve toujours la nouvelle archive, même si sa date paraît ancienne. Les fichiers étrangers et liens symboliques ne sont pas supprimés. Une ancienne archive au checksum invalide interrompt la rétention avant ses suppressions et rend le job en échec ; elle n’est pas effacée pour masquer l’incident. Les fichiers incomplets restent à examiner manuellement.

Le statut JSON privé `status-<projet>.json` conserve dernière tentative, dernier succès et nom de la dernière archive. Une panne ne fait pas disparaître le dernier succès. Les erreurs sont remontées par le code de sortie et les journaux ; les fichiers temporaires de la tentative sont nettoyés.

## Automatisation et détection des échecs

Exemple à adapter au vrai projet, aux chemins et aux permissions, chaque jour à 03 h 15 selon le fuseau du serveur. Le projet indiqué correspond à la valeur déclarée dans compose.prod.yaml, pas à une vérification de l’Alpha :

```cron
15 3 * * * cd /chemin/du/projet && GENESIS_PROJECT=genesis_reborn_prod GENESIS_COMPOSE_FILE=compose.prod.yaml GENESIS_ENV_FILE=.env.prod BACKUP_DIR=/chemin/prive/sauvegardes bash scripts/db-backup.sh >> /chemin/prive/sauvegardes.log 2>&1
```

Aucun job n’est installé sur un serveur par ce chantier. Protéger et faire tourner les journaux, vérifier l’espace disque et prévoir une copie chiffrée hors hôte : un dossier local seul ne protège pas contre la perte de la machine. Le dépôt ne configure pas cette copie ni son fournisseur.

`BACKUP_DIR=... GENESIS_PROJECT=... make backup-status` contrôle le statut et, pour un succès récent, la présence et le checksum de l’archive. L’outil Python renvoie 0 pour un succès récent vérifié, 1 pour un échec/ancienneté excessive/job trop long, 2 si les données sont inconnues ou invalides, et 3 pour un job normalement en cours. Make traduit les retours non nuls en son propre code d’erreur : consulter aussi le relevé JSON.

Pour réutiliser les [alertes par courriel](EXPLOITATION.md), fournir `BACKUP_DIR` et `GENESIS_PROJECT` au job hôte `operations-check.sh`, avec les limites ci-dessus si nécessaire. Un échec ou un succès trop ancien devient une alerte de sauvegarde, un état invalide reste inconnu avec alerte de diagnostic. Un job normalement en cours ne déclenche ni alerte ni rétablissement prématuré ; l’incident précédent est conservé jusqu’à un succès vérifié. Les règles existantes de rappels horaires, rétablissement, simulation et activation privée restent applicables.

## Restaurer uniquement sur une instance séparée pour vérifier

1. Utiliser le même code ou une version explicitement compatible avec les migrations du dump. Créer un projet Compose neuf, un réseau et une base distincts, sans port public. Ne pas réutiliser un projet de production ni ses volumes.
2. Ne lancer que database, avec `event_scheduler=OFF`. Garder app et les consommateurs arrêtés. Si une commande PHP ponctuelle est nécessaire, forcer `MAILER_DSN=null://null` et `OPS_ALERT_ENABLED=0`, sans démarrer les dépendances ni un job d’alertes.
3. Vérifier provenance et âge de l’archive ; conserver son manifeste à côté. Fournir `BACKUP_FILE`, `RESTORE_CONFIRM` égal au projet neuf, `GENESIS_PROJECT`, `GENESIS_COMPOSE_FILE` et `GENESIS_ENV_FILE` puis lancer `bash scripts/db-restore.sh`. Le script vérifie gzip et SHA-256 avant le moindre import. Ne jamais faire cette vérification sur la production.
4. Comparer les comptes, hachages de mots de passe et statut de vérification, les appartenances, stocks et progression. Vérifier le schéma, les contraintes et le planificateur toujours désactivé. Préserver le diagnostic et ne pas démarrer les consommateurs avant analyse des événements en cours.

`make test-backup` automatise ce scénario sur deux projets générés uniquement par le test, avec préfixes stricts et bases en tmpfs. Il applique les migrations sur la source neuve, crée des données de référence, appelle les vrais scripts, restaure sur la cible neuve et compare les lignes des 38 tables présentes lors de l’essai. Il vérifie notamment le compte et son mot de passe, métal 12 345,5, bâtiment niveau 3, recherche niveau 2, sept vaisseaux et une opération en attente. Seule database reste active pendant les comparaisons ; aucun consommateur ni mail réel. Le test nettoie uniquement ses deux projets et signale un échec de nettoyage. Les images déjà préparées sont requises : aucun téléchargement automatique d’image n’est demandé par ce test.

Les 13 tests Python des scripts sont inclus dans `make test` ; le test de restauration est également ajouté au lot PHP de la CI. Les contrôles locaux ne constituent pas une validation GitHub ni de l’Alpha.

## Limites de reprise

Une base restaurée peut contenir des opérations dont le message a déjà quitté l’outbox pour RabbitMQ. Le dump SQL ne restaure pas les messages du broker. La comparaison de données ne prouve donc pas la poursuite automatique de ces opérations. Le runbook historique affirmait une reconstruction possible sans en fournir une commande générale vérifiée ; ne pas déduire que cette reconstruction est livrée. Prévoir une analyse et une réconciliation contrôlées, avec tests de doublons, avant une reprise complète après perte du transport. Le chantier G doit examiner cette reprise ; aucune opération de ce type n’a été exécutée sur l’Alpha.

## Images des guildes

Les archives SQL ne contiennent pas les logos et bannières. Sauvegarder séparément le volume logique Compose `guild_media`, en conservant les noms des fichiers, en même temps que la base contenant leurs références dans `guild_profile`. Une sauvegarde de la base seule ne permet pas de restaurer les images.

Toute restauration doit coordonner ces deux sauvegardes et rétablir les droits du dossier privé pour l’utilisateur web de l’image déployée. Ne pas supprimer le volume lors d’une mise à jour. Aucune sauvegarde ou restauration de production n’est revendiquée par l’ajout de cette documentation. Les tests de persistance utilisent uniquement leurs propres volumes jetables.
