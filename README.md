# Genesis Reborn

Genesis Reborn est un jeu de stratégie spatiale multijoueur par navigateur. Chaque joueur développe ses colonies, organise sa production et ses recherches, explore la galaxie et conduit des opérations de flotte dans des univers indépendants. L’Alpha sert aussi à observer la progression et les retours des joueurs avant de futures décisions d’équilibrage.

L’orientation confirmée est une stratégie au rythme modéré ; les durées de progression restent des objectifs à mesurer pendant l’Alpha.

## État du projet

**L’Alpha est hébergée, selon le propriétaire.** Son URL publique, sa version exécutée et sa configuration serveur restent à confirmer. Les changements intégrés à `dev` ne sont pas déclarés déployés : consulter [l’état du déploiement](docs/DEPLOIEMENT.md).

Le code comprend :

- Comptes, connexion, profils et préférences ; bienvenue et vérification d’adresse pour les nouveaux comptes, renvoi du lien et réinitialisation du mot de passe.
  - Session expirée après 24 h sans requête au serveur ; case « Se souvenir de moi » facultative pendant 30 jours, annulée à la déconnexion. Un cookie de mémorisation valide permet de retrouver sa connexion après expiration de la session.
  - Mots de passe masqués par défaut, avec un bouton œil pour afficher ou masquer chaque champ.
- Univers, colonies et ressources ; bâtiments, recherches, rangs et files de production ; progression traitée pendant la déconnexion.
- Flottes, exploration, transport, colonisation, attaque, défense, orbite et espionnage ; combats persistés, conquête de planètes PNJ et rapports privés.
- Diplomatie, guildes, messages, notifications, inventaire de boosts et événements.
- Administration des comptes et univers, retours utilisateur, patchnotes, signaux des processus, relais en attente et mesures par univers.
- Wiki connecté et compte à rebours dans les dernières 24 heures avant la fermeture d’un univers.

Les jauges distinguent le stock protégé du stock exposé au pillage : remplissage vert jusqu’au seuil protégé, puis doré, avec un repère fixe. Le seuil actuellement documenté est de 10 % de la capacité. Les règles et leurs décisions restent centralisées dans [REGLES.md](docs/REGLES.md) et [DECISIONS.md](docs/DECISIONS.md).

## Technologies et versions

Les contraintes déclarées, les versions verrouillées et les observations d’exécution sont distinctes.

| Élément | Déclaration ou verrouillage vérifié |
| --- | --- |
| PHP / Composer | `composer.json` annonce PHP `>=8.2`, mais DoctrineBundle verrouillé exige `^8.4` et PHPUnit `>=8.4.1`. Les images fournies utilisent PHP 8.4 et Composer 2. |
| Symfony | Contrainte `7.4.*`, FrameworkBundle verrouillé en `7.4.21`. |
| Doctrine / Twig | ORM `3.7.5`, DoctrineBundle `3.3.2`, Twig `3.30.0` dans `composer.lock`. |
| Interface | Twig, Stimulus `3.2.2`, Webpack Encore `7.2.0` et Babel `8.0.7` verrouillés. |
| Node.js | La CI et l’étape de compilation de l’image utilisent la branche 24. Pour cette branche, les contraintes Encore/Babel demandent au moins 24.11.0. |
| Services Docker | Images MariaDB `11.8`, RabbitMQ `4.3-management-alpine`, Mercure `v0.18`, avec références de digest dans Compose. Le proxy Traefik local est externe au projet. |
| Tests | PHPUnit `13.4.5`, Playwright `1.64.1`, axe et tests Python des scripts. |

Sources : [Composer](app/composer.json), [verrou PHP](app/composer.lock), [npm](app/package.json), [verrou JavaScript](app/package-lock.json), [Docker de développement](docker/dev/Dockerfile), [Docker de production](docker/prod/Dockerfile) et [CI](.github/workflows/ci.yml).

Observation locale du 7 novembre 2026 : PHP **8.4.26**, Symfony **7.4.21**, Composer **2.10.3**, Python **3.13.5**, plugin Compose **5.6.0** ; l’image Node disponible contient **24.21.0 / npm 11.19.0**. Le poste hôte contient aussi Node **20.19.2 / npm 9.2.0** : une compilation réussie sur ce poste ne le rend pas conforme aux contraintes actuelles d’Encore/Babel. Ces observations ne décrivent pas les versions de l’Alpha hébergée.

## Prérequis locaux

Le parcours documenté vise Linux ou Debian sous WSL, avec Git, un accès au dépôt et aux registres de dépendances, Docker accessible, le plugin `docker compose`, GNU Make, Bash, Python 3 et les utilitaires GNU utilisés par les scripts, dont `flock`. Utiliser Node **24.11 ou plus récent dans la branche 24**, avec npm. PHP et Composer sont fournis par le conteneur ; ils ne sont pas requis sur l’hôte pour les commandes Make ci-dessous.

Le Compose local attend deux réseaux externes, `traefik_network` et `mail_network`. Il ne crée ni Traefik ni le serveur SMTP : préparer un proxy Traefik avec fournisseur Docker et point d’entrée `web`, ainsi qu’un SMTP de développement joignable. L’exemple mail vise `mailhog:1025` ; adapter `MAILER_DSN` à votre installation. `make doctor` contrôle la présence des réseaux, pas le fonctionnement du proxy ou du SMTP.

## Installation d’un nouveau développeur

Exécuter cette séquence dans un **nouveau clone et une nouvelle base locale** :

```bash
git clone https://github.com/c41m07/genesis-reborn.git
cd genesis-reborn
git switch dev
make configure
```

`make configure` crée `.env` depuis [.env.example](.env.example) s’il manque et génère les secrets privés dans `.env.local`. Il conserve les valeurs existantes et ajoute une clé Mercure si nécessaire. Vérifier ensuite la configuration privée, le SMTP et, si nécessaire, `LOCAL_UID` / `LOCAL_GID` avant de construire l’image.

```bash
make doctor
make validate
make build
make install
make assets
docker compose --env-file .env.local up -d --wait app database rabbitmq mercure
make migrate
make import
make start
```

La première commande de démarrage prépare l’infrastructure et l’application. Les migrations et l’import précèdent le lancement des consommateurs : c’est aussi l’ordre utilisé en CI. `make start` démarre ensuite tous les services ; il n’applique aucune migration et ne charge aucune donnée automatiquement.

Ouvrir [l’adresse locale du jeu](http://genesis-reborn.localhost), puis créer un compte et suivre le lien reçu dans le SMTP de développement. Le proxy doit être configuré pour cette adresse. La base écoute sur `127.0.0.1:3309` par défaut depuis l’hôte ; les conteneurs utilisent `database:3306`.

## Configuration et variables d’environnement

Les fichiers `.env`, `.env.local`, `.env.prod` et les secrets sont exclus par [.gitignore](.gitignore) ; seuls les modèles sont versionnés. Ne pas publier leur contenu. Le modèle serveur est [.env.prod.example](.env.prod.example) ; sa préparation est décrite dans le guide de déploiement.

| Variables | Usage |
| --- | --- |
| `APP_ENV`, `APP_DEBUG`, `APP_SECRET` | Environnement Symfony, débogage et secret applicatif. |
| `DATABASE_URL`, `DB_PASSWORD`, `DB_ROOT_PASSWORD`, `DB_HOST_PORT` | Connexion MariaDB, secrets du service local et port d’accès depuis l’hôte. |
| `MESSENGER_TRANSPORT_DSN`, `RABBITMQ_PASSWORD` | Transport des traitements asynchrones. |
| `MAILER_DSN`, `MAILER_FROM`, `DEFAULT_URI` | Transport SMTP, expéditeur et URL absolue des liens, notamment ceux des mails. |
| `MERCURE_URL`, `MERCURE_PUBLIC_URL` | Adresse interne de publication et adresse accessible au navigateur. |
| `MERCURE_JWT_SECRET`, `MERCURE_JWT_ISSUER` | Signature et émetteur des jetons Mercure. |
| `LOCAL_UID`, `LOCAL_GID` | Identifiants utilisés lors de la construction et par les processus locaux ; défaut 1000. |
| `OPS_ALERT_*` | Activation, destination privée et seuils des alertes ; envois désactivés par défaut. |

Les variables de l’image et du proxy de production (`APP_IMAGE`, `APP_DOMAIN`, `TRAEFIK_CERTRESOLVER`, `SYMFONY_TRUSTED_PROXIES`) sont documentées dans [DEPLOIEMENT.md](docs/DEPLOIEMENT.md). Les paramètres de sauvegarde sont maintenus dans [SAUVEGARDES.md](docs/SAUVEGARDES.md). Aucun fournisseur SMTP de production ni destinataire privé n’est imposé par ce README.

## Démarrage et travail quotidien

Après l’initialisation : `make start`. Utiliser `make status`, `make logs`, `make stop` pour suivre ou arrêter les services. `make stop` conserve les conteneurs et les données ; `make down` retire les conteneurs du projet et conserve les volumes de données.

| Commande | Fonction |
| --- | --- |
| `make help` | Liste des cibles réellement disponibles dans le [Makefile](Makefile). |
| `make console CMD=about` | Console Symfony dans l’application ; remplacer `about` par la commande voulue. |
| `make shell` | Terminal dans le conteneur applicatif. |
| `make assets` / `cd app && npm run build` | Compilation de production dans `app/public/build/` ; Make reste lancé à la racine. |
| `make watch` / `cd app && npm run watch` | Recompilation de développement ; actualiser ensuite le navigateur. |
| `cd app && npm run dev` | Compilation de développement ponctuelle ; `npm run` liste les scripts déclarés. |

Après modification d’un service consommé par les processus persistants locaux, les redémarrer :

```bash
docker compose --env-file .env.local restart worker relay notifier
```

Compiler avant les tests navigateur : la compilation remplace les fichiers de `app/public/build/`. Les dépendances et résultats générés (`app/vendor/`, `app/node_modules/`, `app/var/`, `app/public/build/`, `app/test-results/`, `app/playwright-report/`) sont ignorés par Git.

## Base de données et données de développement

[Les migrations](app/migrations) décrivent l’évolution du schéma. `make migrate` les applique à la base configurée : utiliser cette commande lors de l’initialisation locale ou après une évolution autorisée du schéma. Sauvegarder une base contenant des données avant une migration ; la procédure serveur est distincte.

`make import` importe explicitement les cartes et univers de référence. `make seed-demo` est facultatif et réservé à `APP_ENV=dev` : il crée un compte de démonstration connu, ou conserve le compte existant et son mot de passe. Il ne remplace pas les migrations ni l’import. Les jeux de données de test sont sous [tests/Fixtures](app/tests/Fixtures) et dans les scénarios.

[Le guide des sauvegardes](docs/SAUVEGARDES.md) décrit `make backup`, `make restore`, la conservation de 14 jours par défaut, les contrôles d’échec et la restauration isolée. `make backup-status` consulte l’état sans créer de sauvegarde. `make test-backup` vérifie les comptes et la progression sur deux instances jetables distinctes.

## Tests et qualité

Depuis `app/`, préparer Chromium pour les tests avec `npx playwright install --with-deps chromium` : cette commande installe le navigateur et ses dépendances système. Les six contrôles avant commit ou publication sont :

```bash
make validate
make lint
make test
(cd app && npm run build)
BROWSER_PERSISTENCE=1 make test-browser
git diff --check
```

- `make validate` valide Compose sans afficher les secrets.
- `make lint` contrôle PHP, Twig, YAML, le conteneur Symfony et le schéma Doctrine de la base configurée.
- `make test` utilise `database-test` et `genesis_v2_test` en mémoire, un transport Messenger en mémoire et un transport mail nul ; il lance aussi les tests Python des sauvegardes. Lancer une seule suite PHP à la fois sur cette base.
- `make test-browser` prépare un projet Docker et une base jetables propres à l’exécution. `BROWSER_PERSISTENCE=1` ajoute la persistance temporaire nécessaire aux essais de redémarrage complet ; les volumes sont retirés en fin de test. Les mails et alertes externes y sont neutralisés. Le script npm `test:browser` seul ne prépare pas cet environnement.
- `make verify-install` prépare une installation temporaire, réinstalle les dépendances et vérifie le redémarrage. Son existence ne vaut pas preuve d’une exécution récente ; consulter le suivi pour les commandes réellement exécutées.

Preuve de référence sur `dev` après le lot K, le 7 novembre 2026 : [PR #13](https://github.com/c41m07/genesis-reborn/pull/13), six contrôles locaux réussis, **229 tests PHP**, **13 tests Python**, **97 scénarios Playwright** (95 parcours et 2 tests JavaScript déterministes). La suite complète locale a réussi en deux lots ; une reprise ciblée finale de trois cas a aussi réussi. Les **11 contrôles GitHub** ont réussi et la restauration CI a vérifié 38 tables, les comptes et la progression. Les résultats détaillés et les livraisons suivantes sont dans [le suivi Alpha](docs/audits/SUIVI_ALPHA.md).

La réception SMTP réelle, la configuration et les parcours sur l’Alpha, la planification des sauvegardes et le stockage distant restent à vérifier sur serveur. Les essais perturbateurs utilisent les instances locales isolées. Les mesures d’équilibrage, les tests de charge et la revue d’accessibilité exhaustive restent ouverts ; les contrôles ciblés avec axe ne constituent pas une certification RGAA.

## Organisation des fichiers

```text
app/       sources, configuration Symfony, templates, assets, public,
           migrations, tests, manifestes/verrous et outils applicatifs
docker/    Dockerfiles, Apache et définitions Compose
docs/      règles, décisions, exploitation, conception et historique
scripts/   outils du dépôt, sauvegarde, déploiement et vérification
var/       archives opérationnelles privées (ignorées)
.github/   CI et déploiement
```

Les quatre fichiers `compose*.yaml` à la racine sont des liens vers `docker/`.
Les commandes existantes continuent de fonctionner depuis la racine ; les chemins
Compose sont relatifs à celle-ci. Pour viser directement `docker/compose.yaml`,
ajouter `--project-directory .`. Projets, réseaux et volumes gardent leurs noms.

Les modèles et secrets d’environnement restent à la racine pour Compose et Symfony.
`make configure` prépare les liens locaux `app/.env` et `app/.env.local` ; Compose
monte ces fichiers en lecture seule. Le runtime est dans `app/var/`, les sauvegardes
dans `var/backups/`. Les ressources de conception sont sous `docs/artwork/`,
les images servies sous `app/public/`. Le wiki lit les sources uniques de `docs/`,
montées en lecture seule en développement et copiées dans `/docs` en production.

## Architecture générale

- `app/src/Controller/`, [templates/](app/templates) et [assets/](app/assets) : requêtes HTTP, rendu Twig et comportements Stimulus ; [public/](app/public) sert les fichiers accessibles au navigateur.
- `app/src/Service/`, `app/src/Entity/`, `app/src/Repository/` et `app/src/Manager/` : logique de jeu, données Doctrine et transactions.
- [config/game/](app/config/game) et [config/universes/](app/config/universes) : catalogues, règles configurées et données d’univers ; les décisions produit restent dans la documentation de référence.
- [compose.yaml](docker/compose.yaml), point d’entrée vers [docker/compose.yaml](docker/compose.yaml) : application PHP/Apache, MariaDB, RabbitMQ, Mercure, consommateur `worker`, relais `relay` et notificateur `notifier` ; Traefik et SMTP locaux sont fournis séparément.
- Les événements persistés dans l’outbox sont transmis aux traitements asynchrones et aux notifications ; les files et états serveur permettent la progression sans navigateur ouvert.
- [scripts/](scripts) : configuration, exploitation et validations isolées ; [tests/](app/tests) : tests PHP, scripts et parcours Playwright.

## Accueil public et référencement

Les visiteurs disposent d’un accueil public sur `/` ; les joueurs connectés
conservent l’accès à leur colonie. Titres, descriptions, URL canoniques, Open Graph,
robots.txt et sitemap.xml sont générés côté serveur. `DEFAULT_URI` doit correspondre
à l’origine HTTPS réelle ; voir [SEO et Search Console](docs/REFERENCEMENT.md).

## Documentation de référence

[docs/README.md](docs/README.md) oriente vers une référence par sujet :
règles et décisions, roadmap, architecture, exploitation, déploiement/sauvegardes,
SEO, interface et données d’équilibrage. Les anciens bilans et leur contexte
restent accessibles depuis [l’index des archives](docs/archives/README.md).

Le wiki utilise les sources uniques `docs/wiki.json`, `docs/REGLES.md` et
`docs/DECISIONS.md`. Les PDF d’origine et leurs extractions textuelles sont
conservés ; les libellés du catalogue restent dans `docs/CATALOGUE_LABELS.csv`.

## Contribution et conventions

Lire [AGENTS.md](AGENTS.md), examiner l’état Git et préserver les changements présents. Partir du dernier `dev`, créer une branche `feature/<chantier>`, puis proposer une PR vers `dev`. Utiliser des commits courts et explicites en français, des noms de variables en anglais et les conventions du code existant.

Mettre à jour le README dans le même lot lorsque changent l’architecture, les dépendances, les commandes ou les fonctionnalités documentées. Ne pas changer les règles ou l’équilibrage sans décision explicite, et conserver les logos et SVG d’identité. Distinguer dans chaque livraison ce qui a été vérifié localement, validé en CI, intégré à `dev` et effectivement déployé.
