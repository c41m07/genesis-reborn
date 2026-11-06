# Signaux des processus

Le worker, le relais d’événements et le relais de notifications écrivent chacun un fichier dans `/app/var/run`. L’admin, réservé au rôle administrateur, lit leurs dates de modification à chaque chargement et affiche l’heure du relevé en UTC. Actualiser la page pour obtenir un nouveau relevé ; aucun rechargement automatique des formulaires n’est déclenché.

## États affichés

| État | Preuve disponible |
|---|---|
| Signal récent | Fichier lisible datant de moins de 45 secondes ; le processus a récemment émis un signal. |
| Signal ancien | Dernier signal datant d’au moins 45 secondes ; diagnostiquer le processus et le stockage. |
| Aucun signal | Aucun fichier visible ; le processus peut ne pas avoir démarré ou le dossier partagé peut manquer. |
| État inconnu | Fichier illisible ou date future/incohérente ; vérifier les droits et l’horloge. |

Un fichier ancien ou absent ne prouve pas un arrêt. Un signal récent ne garantit pas la réussite de tous les traitements. Un arrêt réel doit être confirmé par le superviseur ou Docker. Le seuil de 45 secondes est aligné sur les healthchecks existants ; il ne modifie aucune règle de jeu.

## Configuration et diagnostic

`compose.prod.yaml` monte le volume nommé `process_runtime` sur les quatre services. Les trois processus peuvent y écrire ; le montage de l’application est en lecture seule. L’image prépare ce répertoire avec les droits de `www-data`. Sans ce partage, chaque processus peut avoir un healthcheck valide dans son conteneur alors que l’application ne voit aucun signal.

Sur un serveur autorisé, comparer l’état des services, leurs journaux et leurs montages : `docker compose --env-file .env.prod -f compose.prod.yaml ps` puis `docker compose --env-file .env.prod -f compose.prod.yaml logs --tail=50 worker relay notifier`. Vérifier que les quatre services utilisent le même volume, que les processus peuvent écrire, et que l’application peut lire les fichiers.

Lors du prochain déploiement autorisé, les quatre services devront être recréés pour prendre en compte les nouveaux montages ; un simple redémarrage ne les ajoute pas. Conserver les volumes de données et de sessions. Aucune intervention ni recréation de service sur l’Alpha n’a été exécutée dans ce chantier.

## Preuves locales et limites

La configuration de production rendue a été vérifiée pour les quatre montages. Un volume jetable a été testé avec l’image de production déjà disponible localement, en tant que `www-data`, sans réseau : écriture de trois signaux, lecture depuis un autre conteneur et refus d’écriture sur le montage en lecture seule. Le test PHPUnit couvre absence, fraîcheur, seuil exact de 45 secondes, fichier illisible, horodatage futur et rafraîchissement des métadonnées.

Un second essai initialise le volume depuis le montage applicatif en lecture seule : le propriétaire reste `www-data`, les trois appels réels à `ProcessHeartbeat` écrivent leurs signaux et `AdminOperationsService`, connecté à la base locale isolée, les classe tous comme récents. Le volume jetable est ensuite supprimé.

Ces preuves valident le dépôt et les droits locaux. L’image déployée, les éventuelles surcharges Compose, les droits et les montages effectifs de l’Alpha restent à contrôler sur le serveur.
