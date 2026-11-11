# Hébergement de l’Alpha et déploiement GitHub Actions

## État connu au 7 novembre 2026

Le propriétaire confirme que **l’Alpha est déjà hébergée**. Ce fait ne constitue
pas une vérification technique du serveur. Cette mission n’a effectué ni accès
SSH, ni déploiement, ni redémarrage de production. Les changements intégrés dans
`dev` ne sont pas réputés déployés sur l’Alpha.

| Élément | Preuve disponible | À confirmer sur le serveur |
| --- | --- | --- |
| Hébergement Alpha | Information du propriétaire | URL publique, version et digest réellement exécutés |
| CI GitHub | Contrôles réussis des PR [#9](https://github.com/c41m07/genesis-reborn/pull/9) et [#10](https://github.com/c41m07/genesis-reborn/pull/10) : PHP, scripts, restauration isolée et navigateurs | Aucun résultat de CI ne valide la production |
| Publication et livraison | Workflows versionnés de construction GHCR et déploiement SSH manuel | Exécution effective, protections GitHub et image installée |
| Configuration d’exploitation | Compose, scripts et guides présents dans le dépôt | Variables privées, SMTP, signaux partagés, jobs et réception des alertes |
| Sauvegardes et reprise | Restauration SQL vérifiée sur instances jetables et en CI | Planification, stockage distant, protection des archives et reprise du transport |

Les audits sous `docs/agent/` décrivent leur état à leur date de rédaction. Leur
mention « aucun déploiement » ne décrit pas l’hébergement actuel. De même, un
compte rendu indiquant qu’une migration locale précise n’a pas été déployée
conserve son sens : il ne doit pas être transformé en preuve de livraison.

## Chaîne versionnée

La CI exécute les contrôles et les tests à l’ouverture, à la réouverture et à chaque
mise à jour d’une PR vers `dev` ou `alpha`. Les push seuls sur les branches de
travail et sur `dev`, y compris après fusion, ne lancent pas de seconde validation.
Le déclenchement manuel reste disponible.

Les push sur `alpha`, actuellement aussi branche par défaut, conservent la
validation complète. Après sa réussite, le job `publish` construit `docker/prod/Dockerfile` et publie
`ghcr.io/<propriétaire>/<dépôt>:<commit>` avec le `GITHUB_TOKEN` du workflow.
Le résumé du job indique aussi la référence immuable avec digest.
Les pull requests ne publient aucune image. Si la branche par défaut change,
adapter les filtres `push.branches` et `pull_request.branches` du workflow ; la
condition du job `publish` ne suffit pas à déclencher une exécution.
Le job PHP vérifie aussi les espaces du diff avec `git diff --check HEAD^ HEAD` :
sur une PR, il compare le commit de fusion testé à son premier parent (la base) ;
sur un push de livraison, il compare le commit livré à son parent.

La validation utilise cinq jobs en parallèle : un pour PHP et le lint, quatre
pour les tests navigateur répartis avec `--shard=N/4`. Chaque lot navigateur
conserve un seul worker, son runner et sa base jetable ; tous les lots doivent
réussir avant la publication. Le nombre de scénarios évolue avec le dépôt ; le lot H a validé 93 scénarios localement, puis ses contrôles GitHub. Les nombres datés des anciens audits ne décrivent pas la suite courante. Les couches de
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
Le Compose de production attend un transport SMTP configuré hors du dépôt ; le fournisseur et le transport réellement utilisés par l’Alpha restent à confirmer. Aucun réseau Mailhog n’est requis par ce Compose.

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
Pour les nouvelles inscriptions, configurer aussi `MAILER_FROM` avec un expéditeur autorisé par le fournisseur de `MAILER_DSN`. Le domaine de repli local ne convient pas au serveur. Vérifier la réception et le lien de confirmation avec un compte de test après application de la migration autorisée ; voir [le parcours des mails de compte](ARCHITECTURE.md).
L'image et les services ne publient aucun port MariaDB/RabbitMQ sur l'hôte.

Si le package GHCR est privé, authentifier Docker sur le serveur avec un compte
ayant accès au package et un token limité à `read:packages` :

```bash
docker login ghcr.io -u UTILISATEUR_GITHUB
```

## Installer les outils d’exploitation séparément

Le workflow SSH transfère **uniquement** `compose.prod.yaml` et `scripts/deploy.sh`
(installé comme `deploy.sh` dans le répertoire distant). Il ne synchronise pas un
checkout complet, le Makefile ou les autres scripts hôte. La présence de commandes
dans le dépôt ne signifie donc pas leur installation sur l’Alpha.

Pour activer sauvegardes et alertes, l’opérateur doit installer une version
cohérente de `scripts/db-backup.sh`, `scripts/db-restore.sh`,
`scripts/backup-tools.py` et `scripts/operations-check.sh`, en conservant leur
arborescence et leurs chemins configurés. Vérifier leurs prérequis (Python 3,
Bash, outils GNU et `flock`), droits, configuration privée et codes de sortie.
Consulter les guides [sauvegardes](SAUVEGARDES.md), [alertes](EXPLOITATION.md) et
[signaux](EXPLOITATION.md) avant de créer les jobs de l’hôte. Aucun cron,
destinataire privé ou secret n’est installé par la seule livraison de l’image.
Les envois externes restent désactivés tant que leur configuration n’est pas
explicitement activée et leur réception vérifiée par l’opérateur.

Ne pas appliquer au serveur les valeurs d’exemple ou les résultats des instances
jetables comme s’ils avaient été vérifiés sur l’Alpha. Les essais perturbateurs
restent décrits dans les [scénarios isolés](EXPLOITATION.md).

## Protection de dev

Le workflow ne rend pas ses contrôles obligatoires à lui seul. Dans les paramètres
GitHub du dépôt, protéger `dev` en imposant une PR avant fusion et les cinq
contrôles suivants sur la dernière version de la PR :

- `quality (php, lot 0)`
- `quality (browser, lot 1)`
- `quality (browser, lot 2)`
- `quality (browser, lot 3)`
- `quality (browser, lot 4)`

Ne pas exiger le job `publish`, qui ne s’exécute pas sur les PR. Demander une
branche à jour avant fusion pour revalider les changements avec le dernier `dev`
et appliquer les exigences aux administrateurs pour éviter les push directs.
Conserver toute protection existante plus stricte.

Protection activée et relue via l’API GitHub le 11 novembre 2026 avec les
identifiants Git autorisés : PR obligatoire, cinq jobs requis, branche à jour et
exigences appliquées aux administrateurs. Aucun avis humain n’est obligatoire
(zéro approbation requise) ; force-push et suppression de `dev` sont interdits.
Le connecteur GitHub seul refuse l’accès administratif (HTTP 403) ; cette limite
n’a pas empêché la vérification par l’accès Git existant. Revérifier les paramètres
si les noms des jobs ou la stratégie de fusion changent.

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

La visibilité du workflow manuel et la branche sélectionnée doivent être
vérifiées dans GitHub avant une intervention autorisée. Ne pas supposer le nom
de la branche par défaut : les noms sont sensibles à la casse. Cette mission
ne change pas la branche par défaut et ne fusionne rien vers `main`.

## Données, échecs et retour arrière

MariaDB, RabbitMQ, Mercure et les sessions PHP utilisent des volumes persistants.
Ne pas utiliser `down --volumes`. Préparer une sauvegarde vérifiée de MariaDB avant
une migration ; consulter le [guide actif des sauvegardes](SAUVEGARDES.md) pour les paramètres, la conservation et les vérifications de restauration. Le stockage distant reste à configurer par l’opérateur.
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

## Organisation du dépôt — 7 novembre 2026

Le contexte de construction reste la racine. Le Dockerfile copie le code depuis
`app/` et les sources uniques du wiki depuis `docs/`. Les définitions Compose
sont sous `docker/`, avec des liens de compatibilité à la racine. Le transfert
SSH lit directement `docker/compose.prod.yaml` : le serveur reçoit `compose.prod.yaml`, fichier ordinaire,
sans dépendance au clone. Suivre [les contrôles SEO](REFERENCEMENT.md) avant ouverture publique.
