# Livraison des domaines existants et refonte UI — 21 octobre 2026

Cette livraison termine et vérifie les domaines demandés dans le dernier périmètre : infrastructure, comptes/profil, univers/carte, économie/constructions/recherches/queues, missions exploration-colonisation-transport et carburant, guildes/alliances. Elle ne constitue pas la réalisation de toutes les phases des huit PDF : combats et univers avancés restent à développer.

## Accès et démarrage

- Projet : `/home/caim/Projets/Genesis_Reborn`.
- URL : **http://genesis-reborn.localhost**.
- Depuis une installation déjà préparée : `make start` ; arrêt sans perte : `make stop` ou `make down`.
- Installation propre : suivre les commandes distinctes du [README](../README.md) ; démarrer n’exécute aucune migration, import ou création de compte.
- Compte de démonstration : `explorateur@genesis.local` / `Genesis-Demo-2026!`. `make seed-demo` conserve un compte existant et son mot de passe, commande refusée hors dev.
- MariaDB : `127.0.0.1:3309`, base `genesis_v2`, utilisateur `genesis`, mot de passe local dans `.env.local`. Connexion interne `database:3306`. Aucun secret DB recopié dans la documentation.

## Parcours utilisables

1. Créer un compte, se connecter, rejoindre Omega ou Turbo, consulter/modifier son profil avec confirmation du mot de passe puis reconnexion.
2. Consulter ses colonies et les systèmes connus, changer de planète dans le bandeau de ressources ; les planètes inconnues restent masquées.
3. Développer extracteurs, stockages, laboratoire, chantier et forteresse ; rechercher les technologies selon les prérequis ; produire vaisseaux et défenses. Trois files indépendantes, niveaux dépendants et annulation en cascade.
4. Retrouver l’état correct après interruption : reprise des échéances à leurs dates métier, taux de production successifs et plafonds de stockage respectés. Les opérations et stocks s’actualisent sans rechargement après notification/expiration.
5. Estimer puis envoyer une flotte : distance, durée, carburant et place disponible calculés par Symfony. Explorer un système, coloniser une planète découverte libre, transporter des ressources entre ses colonies et récupérer les vaisseaux au retour. Livraison et consommation idempotentes.
6. Proposer/accepter/rompre une alliance personnelle, fonder/rejoindre/quitter une guilde, transférer le commandement ou dissoudre sa guilde. Alliances entre guildes à consentement réciproque, neutralité/hostilité et invalidation des contrats incompatibles. Guildes activées dans Omega et désactivées dans Turbo.

## Corrections livrées

- Production tardive A1 corrigée avec traitement chronologique avant dépense/lecture ; production réelle de la centrale énergétique.
- A2 : événements de jeu et notifications séparés en deux relais ; panne Mercure testée sans blocage des échéances.
- Accès HTTP croisés, confidentialité des abonnements privés, recherches multi-planètes, messages répétés/périmés, droits diplomatiques et concurrence réelle vérifiés.
- Application persistée interne des boosts et renouvellement des tokens testés. Aucune source/prix n’étant définis, pas d’achat ou boost gratuit ajouté aux joueurs.
- Healthchecks applicatifs pour worker, relay, notifier et Mercure ; images Docker figées par digest et AMQP 2.2.0.
- Installation reproductible testée dans un projet Compose temporaire distinct, sans ports ni routage partagé, puis conservation du compte et des données après redémarrage.

## Refonte de l’interface

[Recherche et références publiques](UI-RECHERCHE.md) : OGame, Projet Génésis, Astro Empires, Antaris Legacy, Fallen Galaxy et Astra Genesis. Orvion n’a pas pu être identifié de façon fiable ; une URL reste nécessaire pour intégrer cette référence précise.

Navigation en trois groupes Colonie/Empire/Commandement, HUD économique fixé en haut, sélection des colonies, illustration planétaire originale, cartes de catalogue avec coûts/durées/prérequis, files intégrées à la scène, carte avec légende, estimation interactive et journal de missions en français. Profil et diplomatie intégrés au même cadre. Menu mobile, focus visible, clavier, éléments hors écran rendus inertes, réduction des animations et contenu d’onglets accessible sans JavaScript. Pas de copie d’images d’autres jeux dans le produit.

Captures : [aperçu ordinateur](validation/colonie-apercu.png), [aperçu mobile](validation/mobile-apercu.png), [colonie complète](validation/colonie-desktop.png), [carte](validation/carte.png), [diplomatie](validation/diplomatie.png).

## Vérifications exécutées

| Contrôle | Résultat |
|---|---|
| `make build` | Construction PHP Apache réussie avec les images fixées ; cache de build utilisé |
| `make validate`, `make start` | Compose valide ; app, DB, RabbitMQ, Mercure, worker, relay et notifier healthy |
| `make test` | **47 tests, 168 assertions réussis** sur MariaDB de test isolée en tmpfs |
| `npx playwright test` | **5 scénarios réussis** : compte/navigation mobile, construction+notification+actualisation, chantier+colonisation+transport+exploration, diplomatie à deux comptes, file +1/progression/annulation |
| Nouvelle vérification navigation après ajout de `inert` | Test inscription/navigation/clavier/mobile relancé et réussi |
| `make lint` | PHP, 59 YAML, 12 Twig, injection du conteneur et schéma Doctrine valides |
| `composer validate --strict --no-check-publish` | Réussi |
| `make assets` | Compilation de production Encore réussie |
| `make verify-install` | Dépendances depuis lockfiles, nouvelle base, migrations/import/seed, arrêt/redémarrage : installation vierge réussie, compte conservé, import suivant 0 planète ajoutée |
| `doctrine:migrations:status` | 4 migrations appliquées, aucune en attente |
| MariaDB hôte `127.0.0.1:3309` | Authentification et `SELECT 1` réussis |
| HTTP via Traefik | `/health` et `/connexion` : 200 ; `/.env` et `/.git/config` : 403 ; `/composer.json` : 404 |
| Relecture visuelle Chromium | Captures desktop et mobile inspectées ; largeur 390 px sans débordement horizontal du document |

Les tests navigateur créent des comptes de travail distincts dans la base locale ; ils utilisent les vraies opérations, sans accélération artificielle du temps autre que le multiplicateur explicite de Turbo. Les tests PHPUnit refusent toute base autre que `database-test/genesis_v2_test` avant préparation destructive. La vérification d’installation nettoie uniquement les volumes de son projet temporaire.

## Limites et suite

- Combats/rapports, missions d’orbite/défense/espionnage, renforts, butin et bonus technologiques de combat : **restants**. Le catalogue permet de préparer des unités et des recherches ; leur présence ne signifie pas que ces combats existent.
- Conquête, fermeture asynchrone des univers temporaires et classements/statistiques : **restants**.
- Paramètres marqués `provisional` non validés comme équilibrage final : notamment stocks/production de départ, statistiques manquantes, capacité de réservoir de 1 000 H, recrutement ouvert, annulation sans remboursement. Les règles de ratio carburant et réserve aller-retour sont confirmées par l’utilisateur.
- Boosts : usage interne testé ; acquisition et prix à préciser avant exposition aux joueurs.
- Le verrou global d’univers privilégie la cohérence actuelle ; la montée en charge, une CI, un audit d’accessibilité exhaustif et un audit de sécurité complet ne sont pas revendiqués.

La [matrice](TRACEABILITE.md) relie les règles aux pages des PDF, composants et tests. L’[audit initial](AUDIT-PROMPT-INITIAL.md) est conservé en tant qu’historique antérieur aux corrections.

## Évolution UI multi-colonies

Voir [UI-MULTI-COLONIES.md](UI-MULTI-COLONIES.md) : sélecteur latéral, liens par colonie, files intégrées à la scène avec progression et temps total, suivi global des flottes actives. Les nouvelles vérifications portent le total PHP à 47 tests / 168 assertions.
