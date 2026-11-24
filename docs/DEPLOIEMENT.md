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

La CI se déclenche à l’ouverture, à la réouverture et à chaque mise à jour d’une PR
vers `dev` ou `alpha`, ainsi qu’aux passages entre brouillon et « prête à relire ».

- En brouillon : un seul job `development checks` vérifie les espaces du diff,
  Compose, la syntaxe PHP, Twig, YAML et le conteneur Symfony ; il compile les
  assets et lance les tests Python. Aucun service de base de données, migration,
  parcours navigateur ou restauration SQL n’est lancé.
- Prête à relire : les jobs de qualité, les cinq familles PHPUnit, la sauvegarde
  et la préparation de la référence navigateur peuvent démarrer en parallèle.
  Les jobs des quatre familles Playwright démarrent dès que la référence et leurs listes de fichiers sont disponibles.
  Les contrôles de fusion passent au vert quand tous les jobs ont réussi.

La CI publie les cinq noms historiques obligatoires uniquement pour le bilan
d’une validation complète. Le bilan vérifie aussi les annulations et les jobs
ignorés. Une exécution ciblée possède ses propres résultats.

Pendant la CI, il est possible de relire et documenter le lot courant ou de
développer un lot indépendant sur une autre branche issue de `dev` actualisée.
Préserver la branche testée pour éviter les relances inutiles ; un lot dépendant
attend l’intégration du précédent. Examiner les résultats avant chaque fusion,
actualiser la branche si nécessaire et attendre la CI complète de cette version.
Ne pas partager les bases de test entre vérifications simultanées.

Les push seuls sur les branches de travail et sur `dev`, y compris après fusion,
ne lancent pas de seconde validation. Dans GitHub Actions, ouvrir « Genesis Reborn CI », puis « Run workflow ».
Choisir la branche et une catégorie ou « Suite complète ». Une sélection ciblée
exécute ses seuls prérequis et ses tests. Seule la suite complète peut publier
une image sur une branche de livraison. Le formulaire GitHub utilise la
définition du workflow disponible sur la branche par défaut.

Les push sur `alpha`, actuellement aussi branche par défaut, conservent la
validation complète. Après sa réussite, le job `publish` construit `docker/prod/Dockerfile` et publie
`ghcr.io/<propriétaire>/<dépôt>:<commit>` avec le `GITHUB_TOKEN` du workflow.
Le résumé du job indique aussi la référence immuable avec digest.
Les pull requests ne publient aucune image. Si la branche par défaut change,
adapter les filtres `push.branches` et `pull_request.branches` du workflow ; la
condition du job `publish` ne suffit pas à déclencher une exécution.
Le job de qualité vérifie le diff par rapport au premier parent du commit testé,
Compose, les assets et les contrôles statiques. Les jobs PHP correspondent aux
dossiers Unit, Service, Controller, Integration et Twig. Chaque runner dispose
de sa base MariaDB de test isolée. Le job Integration vérifie également le
schéma obtenu par migration. Le job sauvegarde exécute les tests Python et une
restauration réelle dans deux instances jetables.

La préparation navigateur migre une base neuve et importe les cartes du commit
avant tout consommateur. Elle publie le dump de cette base de jeu synthétique, sa somme SHA-256 et les
listes de fichiers des sous-lots dans un seul artefact de l’exécution. Chaque sous-lot
le télécharge, le vérifie et travaille dans son projet Docker avec un seul worker.
Avant chaque scénario utilisant la fixture de base, les consommateurs sont
arrêtés, le vhost RabbitMQ jetable est recréé et la copie SQL est restaurée.
Les scénarios de persistance peuvent redémarrer leurs propres conteneurs.

Les projets Playwright répartissent les fichiers par parcours. Les audits
transversaux sont prioritaires, puis viennent Administration et Flottes et
interactions. Empire et progression couvre les autres parcours. Les nouveaux
fichiers restent ainsi inclus dans la suite complète.

Le job de référence inventorie les fichiers et les scénarios du commit avec
Playwright. Il estime le coût de chaque fichier en multipliant son nombre actuel
de cas par la durée observée par cas. Les valeurs initiales proviennent des
journaux de la CI complète de la PR #138, avec un worker par catégorie. Elles
incluent le temps entre deux résultats de scénarios, dont la restauration. Le
démarrage de la pile et l’attente d’un runner sont comptés à part. Pour les
fichiers nouveaux, le calcul utilise la médiane des valeurs connues.

La cible est de 22 minutes de scénarios par job, avec un maximum de quatre jobs
par catégorie. Le plan affecte chaque fichier entier, du plus long au plus court,
au lot le moins chargé. Les listes voyagent avec le dump du commit. Le lancement
manuel d’une catégorie utilise le même découpage. Pour recalibrer les estimations
après une évolution du jeu ou des runners, actualiser les secondes par cas dans
`.github/ci/browser-seconds-per-test.json` à partir d’une suite complète réussie
et y indiquer l’exécution de référence.

Exemples locaux :

```bash
make test-php PHP_TEST_PATH=tests/Twig
BROWSER_PERSISTENCE=1 make test-browser BROWSER_ARGS='--project=audits'
```

La commande locale prépare la référence une fois pour son lot. En CI, tous les
sous-lots partagent le même artefact du commit. Le gain de durée globale est non
mesuré.

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

## Mettre à jour la configuration de production

Avant chaque déploiement, prendre le fichier `.env.prod.example` de la version
à livrer comme référence. Le workflow conserve le `.env.prod` privé du serveur.
Comparer les noms de variables et reporter les clés manquantes avec les valeurs
du serveur. Conserver les valeurs existantes, en particulier les secrets et les
mots de passe des services persistants.

Sur le serveur, placer une copie du modèle versionné à côté de `.env.prod`, puis
exécuter dans `/opt/docker-compose/genesis-reborn` :

```bash
env_keys() {
  sed -nE 's/^[[:space:]]*(export[[:space:]]+)?([A-Za-z_][A-Za-z0-9_]*)[[:space:]]*=.*/\2/p' "$1" |
    LC_ALL=C sort -u
}
LC_ALL=C comm -23 <(env_keys .env.prod.example) <(env_keys .env.prod)
```

La commande affiche uniquement les noms des clés absentes. Certaines options
ont des valeurs par défaut dans Symfony. Pour les mails, renseigner
`DEFAULT_URI` avec l’URL publique HTTPS du jeu et `MAILER_FROM` avec un expéditeur
autorisé par le relais configuré dans `MAILER_DSN`.

Le script de déploiement exécute `docker compose --env-file .env.prod -f compose.prod.yaml config --quiet`
avant le téléchargement de l’image et tout arrêt de service. Compose signale les
variables obligatoires absentes ou vides. Ce contrôle vérifie leur présence.
La réception des mails et la validité du lien de confirmation se vérifient
avec un compte de test après la livraison.

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

Le job léger `development checks` ne remplace aucun de ces cinq contrôles.
Vérification du 16 novembre 2026 : `dev` exige toujours la PR, les cinq contrôles,
la branche à jour et l’application aux administrateurs. `alpha` n’a ni protection
de branche ni règle de dépôt applicable à cette date. Le workflow y lance bien la
CI complète pour les PR prêtes, mais ne pouvait pas imposer seul son attente
avant fusion à cette date.

Le 17 novembre 2026, avec l’accord explicite du propriétaire, `alpha` a reçu une
protection équivalente à `dev`, appliquée puis relue via l’API : PR obligatoire,
cinq jobs `quality` associés à GitHub Actions, branche à jour, exigences appliquées
aux administrateurs, force-push et suppression interdits. Aucune approbation humaine
n’est imposée ; `publish` n’est pas un contrôle requis pour les PR.

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
```

`make validate` contrôle aussi le Compose de production avec `.env.prod.example`.
Lorsqu’une clé devient obligatoire, la déclarer dans `docker/compose.prod.yaml`,
l’ajouter au modèle et signaler la configuration à reporter avant livraison.

La construction compile les assets et installe les dépendances PHP sans celles
de développement. `.dockerignore` exclut les secrets, dépendances locales et Git.
Le Compose local et le Makefile continuent à utiliser `docker/dev/Dockerfile`.

## Organisation du dépôt — 7 novembre 2026

Le contexte de construction reste la racine. Le Dockerfile copie le code depuis
`app/` et les sources uniques du wiki depuis `docs/`. Les définitions Compose
sont sous `docker/`, avec des liens de compatibilité à la racine. Le transfert
SSH lit directement `docker/compose.prod.yaml` : le serveur reçoit `compose.prod.yaml`, fichier ordinaire,
sans dépendance au clone. Suivre [les contrôles SEO](REFERENCEMENT.md) avant ouverture publique.

## Préparer les présentations de guildes

Le lot profils/guildes ajoute GD (JPEG/PNG/WebP) dans les images de développement et de production, ainsi que le volume logique `guild_media` monté uniquement dans le service web sous `/app/var/guild-media`. L’image prépare ce dossier pour l’utilisateur Apache ; les images téléversées restent hors de la racine web. Conserver ce volume lors des remplacements de conteneur et l’inclure dans les sauvegardes de fichiers.

La migration `Version20261114133852` crée seulement `guild_profile`. Sa présence dans le dépôt ne signifie pas son application : sur une base existante, obtenir l’autorisation explicite et vérifier une sauvegarde avant de l’exécuter. Les nouveaux écrans de guildes attendent cette table. Les profils de joueurs ne requièrent aucun nouveau champ sur les comptes. Aucun déploiement Alpha ni aucune migration de la base habituelle n’est effectué dans ce lot.

### Préparation du lot messagerie

Le lot Ctrl + Entrée / Markdown ajoute league/commonmark au verrou Composer
et Version20261114151245. Les nouvelles colonnes sont nécessaires aux requêtes
ORM des messages ; livrer code et schéma ensemble. La migration conserve la
source historique et lui attribue le format texte simple. Sa création ne vaut
pas autorisation d’application : sur base existante, obtenir l’accord pour la
base visée et vérifier une sauvegarde avant toute exécution.
Aucune application ni livraison Alpha effectuée pour ce lot.
Voir [la reprise](MESSAGERIE_MARKDOWN.md).
