# Genesis_Reborn

Jeu de stratégie spatiale en construction à partir des huit PDF dans `docs/technic` (108 pages). Le projet historique a été supprimé le 21 octobre 2026 à la demande de l'utilisateur. Ses volumes restent conservés et ne sont jamais utilisés par cette application.

**Livraison actuelle :** domaines économiques, missions logistiques et diplomatie finalisés et testés ; interface refondue avec [navigation multi-colonies, files intégrées et suivi des vols](docs/UI-MULTI-COLONIES.md). Voir [le bilan et ses limites](docs/LIVRAISON-DOMAINES-UI.md). Le jeu complet des six phases reste en développement.

URL locale : **http://genesis-reborn.localhost**

Compte de démonstration local : `explorateur@genesis.local` / `Genesis-Demo-2026!`. Créé uniquement par la commande explicite `make seed-demo`, refusée hors environnement dev. Un compte déjà présent n'est jamais réinitialisé.

## Installation dans Debian WSL

Prérequis : Docker Compose, make, Python 3, Node 24/npm. Traefik et MailHog partagés doivent être démarrés, avec leurs réseaux `traefik_network` et `mail_network`.

```bash
cd /home/caim/Projets/Genesis_Reborn
make configure
make doctor
make build
make install
make assets
make start
make migrate
make import
make seed-demo
```

`make start` ne migre, n'importe et ne prépare aucun compte. Les commandes de préparation sont explicites. Le relais et le worker redémarrent en cas d'indisponibilité initiale du schéma. Si les UID/GID locaux diffèrent de 1000, adapter les arguments LOCAL_UID/LOCAL_GID du build Docker à l'utilisateur du montage.

Sur WSL sans résolution des sous-domaines localhost, le navigateur Windows peut utiliser l'URL ; pour les scripts WSL, résoudre le domaine vers 127.0.0.1 ou utiliser cet IP avec l'en-tête Host `genesis-reborn.localhost`.

## Commandes

`make help` décrit toutes les cibles. `make stop` et `make down` conservent les volumes. `make console CMD='about'` exécute Symfony dans PHP. Après modification des handlers ou services utilisés par les processus longs :

```bash
docker compose --env-file .env.local restart worker relay notifier
```

Base : MariaDB 11.8, serveur Docker `database:3306`, base `genesis_v2`, utilisateur `genesis`. Mot de passe aléatoire dans `.env.local`, ignoré de Git. Accès depuis l’hôte : `127.0.0.1:3309` (`DB_HOST_PORT` configurable). Le `.env` versionnable contient des exemples, pas les vrais secrets.

## État réel

Utilisable : inscription/connexion/déconnexion, sélection de deux univers, attribution d'une première colonie, carte avec systèmes inconnus masqués, cinq ressources calculées à la demande, bâtiments, recherches, chantier et défenses, queues indépendantes, annulation en cascade, notifications Mercure privées.

Missions exploration/colonisation/transport activées avec consommation proportionnelle à la vitesse, réservoirs individuels et réservation aller-retour. Tests MariaDB de découvertes, colonisation, livraisons et idempotence réussis. Guildes et alliances : consentement réciproque, relations ennemies/neutres, adhésion, transfert, départ et dissolution vérifiés. Profil éditable avec confirmation du mot de passe et reconnexion. Estimation serveur des trajets et mises à jour automatiques des queues, ressources et inventaires.

À réaliser : attaque/défense/orbite et résolution persistée des combats, rapports, notifications d'attaque avec visibilité, variante conquête, fermeture asynchrone des univers temporaires, classements, CI et guide utilisateur final. Le projet n'est pas encore le jeu complet des six phases.

Les données d'équilibrage absentes des PDF sont marquées provisoires dans `config/game`. Turbo est un univers de démonstration à durées réduites. Les catalogues et coûts sont autoritaires côté serveur.

## Vérification

```bash
make test
make lint
npx playwright install chromium
make test-browser
make verify-install
```

PHPUnit utilise uniquement `database-test`, base `genesis_v2_test`, stockée en tmpfs. Le bootstrap force les paramètres isolés et les tests de persistance refusent tout autre serveur ou nom de base avant leur préparation destructive. Les tests navigateur créent leurs propres comptes locaux préfixés E2E/Live/Fleet/Leader/Member et font évoluer leurs colonies ; ils ne modifient pas le compte démo. Ils vérifient de vraies opérations sur la base locale, tandis que PHPUnit prépare uniquement sa base isolée. `make verify-install` crée un projet Compose temporaire distinct sans publication de ports ni route Traefik, installe depuis les lockfiles, applique les migrations, puis vérifie le redémarrage sans perte. Son nettoyage supprime uniquement ses propres volumes temporaires.

Roadmap et état détaillé : [ROADMAP.md](docs/ROADMAP.md), [TRACEABILITE.md](docs/TRACEABILITE.md). Références, ambiguïtés et adaptations : `docs/REGLES.md`, `docs/DECISIONS.md`, `docs/TRACEABILITE.md`. Les dépendances PHP sont verrouillées par composer.lock ; les dépendances frontend par package-lock.json. Les images Docker sont fixées par digest et PECL AMQP par version exacte. PHP 8.4 est fourni par l’image Docker (PHPUnit 13 nécessite également PHP 8.4). Recherche UI et références : [UI-RECHERCHE.md](docs/UI-RECHERCHE.md). Captures desktop/mobile dans docs/validation. Installer Chromium avant `make test-browser`.
