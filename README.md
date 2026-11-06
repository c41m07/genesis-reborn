# Genesis Reborn

Jeu de stratégie spatiale multijoueur construit avec PHP 8.4, Symfony 7.4, Doctrine, Twig, MariaDB et Docker Compose. Le projet est au stade **Alpha jouable contrôlée** : la boucle principale fonctionne et peut être testée; la production publique n’est pas déclarée prête.

## État du projet

Les quinze phases de travail ont été auditées. Quatorze sont validées; la phase 12 reste partielle pour la revue manuelle d’accessibilité exhaustive. Au 5 novembre 2026, les contrôles de configuration, lint et compilation passent, ainsi que les 192 tests PHP. Les 87 scénarios navigateur ont chacun réussi dans des lots isolés et reprises ciblées ; consulter [le bilan détaillé des tests](docs/agent/TEST_STATUS.md). Le projet reste une Alpha contrôlée, pas une validation complète. Les points ouverts incluent aussi les mesures d’équilibrage pendant l’Alpha, la CI distante, les tests de charge, le PRA et l’audit manuel avec lecteur d’écran. Les preuves figurent dans [l’audit final](docs/agent/PHASE_15_FINAL_AUDIT.md).

L’orientation confirmée pour le rythme est **stratégie modérée**. Les objectifs de durée de progression et les valeurs économiques/combat seront mesurés pendant l’Alpha auprès des joueurs, puis ajustés à partir de ces retours; ils ne sont pas présentés comme des valeurs déjà validées.

Le jeu comprend les comptes et profils, univers et colonies, économie, bâtiments et recherches, files de production, flottes et missions (exploration, colonisation, transport, attaque, défense, orbite, espionnage), combats persistés, conquête de planètes PNJ, rapports privés, diplomatie, guildes, messages, boosts/événements et outils d’administration. Le combat exécute jusqu’à dix rounds, à raison d’un round par minute; ces règles sont conservées pour l’Alpha.

## Documentation

Les sauvegardes disposent de noms uniques, d’un contrôle d’intégrité lié à l’archive, d’une conservation configurable de 14 jours et d’un suivi des échecs. `make test-backup` vérifie une restauration sur deux instances jetables, sans mail réel ; voir [sauvegardes, automatisation et limites de reprise](docs/BACKUPS.md). Les scripts hôte demandent Python 3 et les utilitaires GNU, dont `flock`.

Des alertes d’exploitation par courriel sont préparées, désactivées par défaut : arrêt confirmé, signal absent pendant 2 minutes, retard de relais de 5 minutes et échecs définitifs. Rappels espacés d’au moins une heure et notification de rétablissement ; voir [configuration et automatisation des alertes](docs/OPERATIONS_ALERTS.md).

L’admin permet de consulter les relais en attente et leurs échéances ; une collecte indisponible est distinguée d’une file vide. Les limites des données, les tentatives du worker et la reprise après interruption sont détaillées dans [le guide des relais](docs/OUTBOX.md).

L’administration affiche les signaux récents, anciens, absents ou incohérents des processus. En production, les trois processus partagent leur dossier de signaux avec l’application en lecture seule. L’état est relu à chaque chargement de l’admin ; voir [le diagnostic des signaux](docs/OPERATIONS_SIGNALS.md).

Les nouveaux comptes reçoivent un message de bienvenue et un lien de vérification d’adresse valable 24 heures. L’accès nécessite cette vérification ; un renvoi est proposé depuis la connexion. Les comptes antérieurs à cette fonctionnalité conservent leur accès. Le transport (`MAILER_DSN`), l’expéditeur (`MAILER_FROM`) et l’URL publique (`DEFAULT_URI`) doivent être configurés sur chaque environnement. Voir le [parcours de confirmation](docs/ACCOUNT_EMAIL.md).

Le mot de passe oublié utilise un lien valable une heure, à usage unique. Une nouvelle demande invalide le précédent lien ; la réponse publique reste neutre. Les mails utilisent les mêmes variables de configuration que la confirmation de compte. Voir le [parcours de réinitialisation](docs/ACCOUNT_EMAIL.md#réinitialisation-du-mot-de-passe).

- [Déploiement GitHub Actions et image GHCR](docs/DEPLOIEMENT.md) : préparation du serveur, secrets et déploiement manuel.
- [Index documentaire](docs/README.md) : règles, décisions, fonctionnement, audits, validation et limites.
- [Règles de jeu](docs/REGLES.md) et [décisions confirmées](docs/DECISIONS.md).
- [Roadmap](docs/ROADMAP.md), [plan maître](docs/agent/MASTER_PLAN.md) et [matrice de couverture](docs/agent/REQUIREMENTS_MATRIX.md).
- [État de préparation Alpha](docs/agent/ALPHA_READINESS.md) et [audit final](docs/agent/PHASE_15_FINAL_AUDIT.md).

## Environnement local Debian/WSL

Prérequis : Docker Compose, make, Python 3 et Node.js/npm. Le projet s’attend aux réseaux locaux partagés traefik_network et mail_network.

    cd /home/caim/Projets/Genesis_Reborn
    make configure
    make doctor
    make build
    make install
    make assets
    make start
    make migrate
    make import

make configure crée le fichier .env depuis .env.example si nécessaire, puis génère les secrets locaux dans .env.local sans remplacer ceux déjà présents. make start ne lance ni migration, ni import, ni création de compte démo. make seed-demo est une commande facultative réservée à l’environnement local. Le compte de démonstration n’est pas requis pour créer un compte par l’interface.

Pour les scripts exécutés depuis WSL si le domaine local ne se résout pas, utiliser 127.0.0.1 avec l’en-tête Host: genesis-reborn.localhost. La base MariaDB écoute par défaut sur 127.0.0.1:3309; son port est configurable dans .env.local, fichier local ignoré par Git.

## Commandes principales

    make help
    make start
    make stop
    make test
    make lint
    make validate
    npm run build
    make test-browser
    make verify-install
    make backup-status
    make test-backup

make backup-status ne crée rien et signale un état inconnu si aucune sauvegarde n’est disponible ; make test-backup utilise uniquement ses instances jetables.

make help affiche les commandes. make stop arrête sans supprimer les volumes. make test utilise la base de test isolée et lance aussi les tests Python des scripts de sauvegarde. make test-browser lance Chromium avec une stack et une base jetables; installer Chromium avec npx playwright install chromium. make verify-install vérifie une installation propre dans un projet Compose distinct et supprime uniquement ses propres volumes temporaires. make console CMD='about' lance une commande Symfony.

Après modification des services consommés par des processus persistants, redémarrer les services concernés avec docker compose --env-file .env.local restart worker relay notifier.

## Structure du dépôt

- src/, templates/, public/ : application Symfony et interface.
- config/, migrations/, compose*.yaml : configuration et évolution du schéma.
- tests/, tests/browser/ : tests serveur, intégration et navigateurs.
- docs/specifications/, docs/technic/ : sources du jeu; docs/agent/ : audits, plans et preuves techniques.
- scripts/ : configuration locale, validation, sauvegarde et tests isolés.

Les dépendances PHP et JavaScript sont verrouillées par composer.lock et package-lock.json. Aucun déploiement ou publication n’est effectué par les commandes locales décrites ici.
