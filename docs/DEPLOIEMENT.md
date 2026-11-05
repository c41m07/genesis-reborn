# Image et déploiement GitHub Actions

La CI existante exécute les contrôles et les tests. Sur la branche `alpha` ou la branche par défaut,
après leur réussite, le job `publish` construit `docker/prod/Dockerfile` et publie
`ghcr.io/<propriétaire>/<dépôt>:<commit>` avec le `GITHUB_TOKEN` du workflow.
Le résumé du job indique aussi la référence immuable avec digest.
Les pull requests ne publient aucune image.

La validation utilise cinq jobs en parallèle : un pour PHP et le lint, quatre
pour les tests navigateur répartis avec `--shard=N/4`. Chaque lot navigateur
conserve un seul worker, son runner et sa base jetable ; tous les lots doivent
réussir avant la publication. Les 87 scénarios sont conservés. Les couches de
l'image PHP de test, les dépendances PHP verrouillées et les téléchargements npm
sont mis en cache. Le premier passage remplit les caches ; la durée dépend aussi
de la disponibilité des runners GitHub.

Pour exécuter un lot localement :

```bash
make test-browser BROWSER_ARGS='--shard=1/4'
```

Le workflow `Deploy Genesis Reborn` est manuel par défaut. Il transfère le Compose
de production et le script par SSH, télécharge l'image, arrête les processus
applicatifs, applique les migrations puis démarre et vérifie les services.
Une courte maintenance est donc prévue. Aucun import ni compte démo n'est créé
automatiquement. Cette configuration ne vaut pas validation de production publique.

## Préparer le serveur une fois

Prérequis : serveur Linux amd64, Docker avec Compose v2, Bash et `flock`.
Un Traefik existant doit être connecté au réseau `traefik_network`, avec
l'entrypoint `websecure` sur 443 et un résolveur de certificats opérationnel.
Configurer le DNS du domaine vers le serveur et l'accès HTTPS dans le pare-feu.
Le serveur SMTP est externe ; aucun réseau Mailhog n'est requis.

Créer `/opt/docker-compose/genesis-reborn`, accessible en écriture par l'utilisateur SSH de
déploiement, qui doit pouvoir utiliser Docker. Copier `.env.prod.example` vers
`/opt/docker-compose/genesis-reborn/.env.prod`, puis protéger ce fichier avec `chmod 600`.
Renseigner domaine, résolveur Traefik, SMTP et tous les secrets.
Générer chaque secret indépendamment avec `openssl rand -hex 32`.
Reporter les mêmes mots de passe dans les DSN MariaDB/RabbitMQ correspondants.
Les secrets ne sont jamais copiés dans l'image ni envoyés depuis GitHub.

Configurer `SYMFONY_TRUSTED_PROXIES` avec le sous-réseau réel de Traefik
(`docker network inspect traefik_network`), afin que Symfony reconnaisse HTTPS.
Les URL publiques et `DEFAULT_URI` doivent utiliser le domaine HTTPS retenu.
L'image et les services ne publient aucun port MariaDB/RabbitMQ sur l'hôte.

Si le package GHCR est privé, authentifier Docker sur le serveur avec un compte
ayant accès au package et un token limité à `read:packages` :

```bash
docker login ghcr.io -u UTILISATEUR_GITHUB
```

## Configurer GitHub

Autoriser GitHub Actions à publier des packages et créer l'environnement
`production`. Y renseigner ces secrets :

| Secret | Contenu |
| --- | --- |
| `DEPLOY_HOST` | Nom DNS ou IPv4 du serveur |
| `DEPLOY_USER` | Utilisateur SSH ayant accès à Docker |
| `DEPLOY_SSH_KEY` | Clé privée dédiée au déploiement |
| `DEPLOY_KNOWN_HOSTS` | Ligne known_hosts du serveur, empreinte vérifiée hors bande |

La clé publique correspondante doit figurer dans `authorized_keys` du serveur.
SSH utilise le port 22. Le répertoire distant est `/opt/docker-compose/genesis-reborn`.

Après une CI réussie sur `alpha` ou la branche par défaut, lancer `Deploy Genesis Reborn`
depuis Actions sur cette même branche, et fournir seulement le digest
`sha256:...` indiqué par la CI. Utiliser l'image construite pour la version du
Compose sélectionnée. La référence du dernier déploiement réussi est conservée
sur le serveur dans `deployed-image.txt` ; sa valeur doit être recopiée dans
`APP_IMAGE` de `.env.prod` pour les commandes manuelles suivantes.

Au premier déploiement uniquement, importer les univers de référence :

```bash
cd /opt/docker-compose/genesis-reborn
docker compose --env-file .env.prod -f compose.prod.yaml run --rm --no-deps --user www-data app php bin/console app:universe:import
```

Le workflow manuel doit également exister sur la branche par défaut pour être
visible et déclenchable depuis GitHub. Si la branche par défaut est encore
`Main`, y intégrer le workflow ou choisir `alpha` comme branche par défaut
dans les paramètres du dépôt. Les noms de branches sont sensibles à la casse.

## Données, échecs et retour arrière

MariaDB, RabbitMQ, Mercure et les sessions PHP utilisent des volumes persistants.
Ne pas utiliser `down --volumes`. Préparer une sauvegarde vérifiée de MariaDB avant
une migration et organiser les sauvegardes distantes selon le runbook existant.
Les journaux des conteneurs sont accessibles via `docker compose logs`.

Une migration échouée arrête le déploiement et laisse les processus applicatifs
arrêtés pour éviter leur exécution sur un schéma partiellement modifié. Examiner
les journaux et corriger ou restaurer la base avant de relancer le workflow.
Il n'y a pas de retour arrière automatique des données. Pour reprendre une image
antérieure, sélectionner son digest dans le workflow uniquement après avoir
vérifié sa compatibilité avec le schéma courant ; conserver également le Compose
de cette version. Les modifications de schéma incompatibles nécessitent une
procédure de restauration spécifique.

## Vérification locale de l'image

```bash
docker build -f docker/prod/Dockerfile -t genesis-reborn-prod:check .
APP_IMAGE=genesis-reborn-prod:check APP_ENV_FILE=.env.prod.example docker compose --env-file .env.prod.example -f compose.prod.yaml config --quiet
```

La construction compile les assets et installe les dépendances PHP sans celles
de développement. `.dockerignore` exclut les secrets, dépendances locales et Git.
Le Compose local et le Makefile continuent à utiliser `docker/dev/Dockerfile`.
