# Audit initial du prompt — 21 octobre 2026

> **Document historique, antérieur aux corrections et à la refonte.** Les défauts A1/A2 et les lacunes profil, concurrence, diplomatie, aperçu de mission, actualisation et installation propre ont depuis été traités. Consulter [la livraison actuelle](LIVRAISON-DOMAINES-UI.md) et [la traçabilité](TRACEABILITE.md) pour l’état en vigueur. Les constats ci-dessous sont conservés pour comprendre les corrections.

**Conclusion : le projet n’est pas terminé.** Un socle local jouable existe, mais les six phases des PDF ne sont pas achevées. Les contrôles automatisés actuels ne couvrent pas l’ensemble du cahier des charges.

Cet audit compare les fichiers, routes, services, tests et services Docker réellement présents aux demandes du prompt. Il intègre les décisions ultérieures de l’utilisateur : suppression de l’ancien projet, port DB `3309`, consommation proportionnelle à la vitesse, réservoir individuel et réservation complète au départ.

## Corrections appliquées pendant cet audit

- MariaDB publiée sur **127.0.0.1:3309 → 3306** ; ancien port 309 fermé.
- `DB_HOST_PORT=3309` dans la configuration locale, valeur d’exemple et défaut Compose cohérents.
- Connexion authentifiée depuis l’hôte avec l’utilisateur applicatif, `SELECT 1` réussi.
- Documentation et état d’avancement synchronisés.

## Comparaison avec les six phases

| Phase PDF 0 | État réel | Ce qui reste |
|---|---|---|
| 1 — Socle et authentification | Socle local opérationnel, inscription/connexion/déconnexion | Validation d’une installation entièrement neuve à compléter ; profil éditable demandé dans le prompt absent |
| 2 — Univers et carte | Sélection, inscriptions indépendantes, import JSON et découvertes initiales opérationnels | Vérifications d’imports invalides et d’accès HTTP croisés à étendre |
| 3 — Colonie | Ressources, bâtiments, technologies, vaisseaux, défenses et trois queues implémentés | Défaut de production après traitement tardif reproduit ; recherches multi-planètes, effets de certains bâtiments, boosts et tests complets restants |
| 4 — Flottes | Exploration, colonisation et transport implémentés ; formules et cycle de mission testés sur MariaDB isolée | Orbite, défense, espionnage planétaire, prévisualisation serveur/Stimulus, couverture navigateur des missions et scénarios concurrents restants |
| 5 — Combat et guildes | Guildes/contrats/relations en implémentation initiale ; notifications privées de fin d’opération | Combat, camps, renforts, bonus technologiques de combat, butin, rapports et notifications d’attaque absents ; diplomatie à tester intégralement |
| 6 — Variantes et finitions | Deux univers paramétrables, indicateur d’activation des guildes ; UI de base responsive | Conquête, fermeture asynchrone, classements, statistiques et finitions avancées absents |

## Contrôle du prompt par domaine

| Demande | Constat et preuve | Statut |
|---|---|---|
| Nouveau dossier Genesis_Reborn | Application présente à l’emplacement demandé | Fait |
| Préserver le projet historique | L’utilisateur a ensuite explicitement demandé sa suppression ; dossier, quatre conteneurs et deux images applicatives supprimés. Volumes historiques conservés, non utilisés par le nouveau projet | Modifié par instruction ultérieure |
| Lire et copier les huit PDF | Huit PDF et extractions intégrales présents ; copies vérifiées par SHA-256 avant suppression historique ; les extractions étaient exploitables | Fait ; pas de contrôle visuel exhaustif revendiqué |
| Synthèse, ambiguïtés, traçabilité | REGLES.md, DECISIONS.md et TRACEABILITE.md présents | Fait, mais les statuts doivent rester partiels là où la validation manque |
| Stack imposée | Symfony 7.4.21, PHP 8.4.26 dans Docker, MariaDB 11.8, Doctrine ORM 3, Twig, Stimulus, Docker, Traefik, PHPUnit | En place |
| Versions et dépendances verrouillées | composer.lock et package-lock.json présents ; références officielles dans DECISIONS.md. Les images PHP/MariaDB/RabbitMQ et PECL AMQP ne sont pas toutes verrouillées par digest/version exacte | Partiel |
| Réutiliser Traefik et MailHog | Réseaux externes réutilisés ; route applicative et route Mercure propres à ce projet ; pas de deuxième Traefik/MailHog | Fait |
| Isolation des services/données | Compose genesis_reborn_v2, réseau backend et volumes dédiés ; aucune base historique raccordée | Fait |
| Accès DB configurable sans conflit | Publication locale 3309 demandée ultérieurement ; database:3306 à l’intérieur de Docker | Fait et vérifié |
| Healthchecks | app, MariaDB et RabbitMQ ont un contrôle ; relay, worker et Mercure n’ont pas de contrôle de santé applicatif dédié | Partiel |
| Exposer public/ seulement | Apache DocumentRoot /app/public ; .env et .git refusés en HTTP ; composer.json inexistant dans la racine exposée | Fait et vérifié |
| Architecture par responsabilité | Contrôleurs, services par domaine, GameManager et GameRepository séparés ; DTO d’inscription présent. Repository générique, tableaux de catalogue plutôt que ValueObjects typés ; certains paramètres métier encore codés dans les services | En place, à consolider |
| Serveur autoritaire | Coûts, prérequis, durées, possessions et carburant recalculés côté serveur | En place pour les fonctions présentes ; aucun combat encore |
| Transactions et concurrence | Transactions et verrous pessimistes existants ; verrou d’univers large qui sérialise les actions | Partiel : pas de validation sous accès concurrents réels |
| Doubles soumissions et idempotence | ActionReceipt pour constructions/missions, dispatch_token pour échéances ; tests doublons/périmés réussis | Vérifié pour les chemins testés ; couverture à étendre |
| Reprise après interruption | Outbox persistante et redémarrage des processus ; défaut de production tardive reproduit, panne Mercure susceptible de bloquer le relais | Partiel, non validé comme robuste |
| UTC | PHP configuré UTC, dates immuables, affichage local explicite pour les timers concernés | En place |
| Catalogues complets | Slugs et catalogue de démonstration présents ; nombreuses valeurs provisoires signalées | Équilibrage final absent |
| Queues conformes au PDF 7 | QueueItem, id ASC, trois files, dépendances, fin calculée et jetons présents | Implémenté ; boost seulement calculatoire et reprise à corriger |
| Authentification et profil | Inscription, connexion et déconnexion présents ; aucune route/formulaire de profil | Partiel |
| Flottes et missions | Trois missions disponibles ; aller-retour, ressources et découvertes testés | Partiel : autres missions et prévisualisation absentes |
| Combats et rapports | Aucun CombatEvent, CombatResolver, CombatParticipant, CombatLog ni contrôleur de rapports | Absent |
| Multi-univers avancé | Deux univers et configuration par univers ; accès logique fermé/dates contrôlé | Partiel : fermeture par message, conquête et classements absents |
| Interface française et accessible | UI française, focus, labels, onglets clavier, compteurs et responsive testés | Partiel : statuts/slugs techniques encore affichés, accès sans JS aux onglets à améliorer, pas d’audit d’accessibilité complet |
| Mises à jour Stimulus | Compteurs, onglets, compte à rebours et notification Mercure ; actualisation manuelle proposée par la notification | Partiel : inventaires/queues non actualisés intégralement sans rechargement |
| Sécurité | Hachage Symfony, CSRF, contrôle de propriété et de contexte, limitation des connexions | En place ; tests négatifs HTTP, flotte, diplomatie et abonnements privés à compléter |
| Secrets | Secrets locaux dans .env.local ignoré ; exemples dans .env ; aucun secret réel ajouté au rapport | En place ; intégration Git du projet à finaliser |
| Migrations et préparation explicite | Quatre migrations présentes/appliquées. start distinct de migrate, import et seed-demo ; seed refusé hors dev | Fait |
| Makefile | Toutes les cibles demandées existent ; validation, build, start, console, test et lint exécutés | En place ; installation totalement vierge, stop/down et conservation après redémarrage non rejoués dans cet audit |
| Tests isolés | Bootstrap force database-test / genesis_v2_test ; refus d’une autre base avant préparation destructive | Vérifié |
| Tests de toutes les règles | 23 tests couvrent une partie des ressources/queues/prérequis/accès/missions ; aucun test de combat ni de diplomatie métier actuellement | Incomplet |
| README et livraison | URL, commandes, compte démo, état et limites documentés | Documentation d’avancement présente ; pas de livraison finale du jeu complet |

## Défauts et risques concrets relevés

### A1 — Production incorrecte si un extracteur est achevé avec retard

**Reproduit sur la base de test isolée.** Une amélioration achevée théoriquement il y a près d’une heure mais traitée seulement maintenant calcule tout l’intervalle avec l’ancien taux. Dans le scénario exécuté : stock attendu environ **2 979**, stock obtenu **1 180**.

Code concerné : `src/Service/Queue/QueueService.php::complete()` et `src/Service/Colony/ResourceService.php::materialize()`. Le calcul matérialise jusqu’au moment de traitement, puis change le taux ; l’échéance métier `$finish` n’est pas utilisée pour répartir la production. La correction doit aussi gérer une consommation effectuée après l’échéance et avant le rattrapage, ainsi que les plafonds de stockage. Une simple modification de date sans traitement chronologique ne suffit pas.

### A2 — Une panne de publication Mercure peut retarder les événements de jeu

**Constat de lecture, corroboré par les échecs de publication rencontrés lors de la mise en place.** `OutboxRelayCommand` traite notifications et échéances dans la même boucle et quitte sur la première exception. Une notification en échec en tête peut donc empêcher l’envoi des constructions/missions suivantes. Séparer les circuits ou prévoir une stratégie de reprise qui ne bloque pas les événements de jeu.

### A3 — Fonctionnalités affichées mais effets partiels

`power_plant` existe dans le catalogue et ses prérequis, mais `ResourceService::rate()` ne consulte que `mine_<ressource>` : pas d’effet propre de la centrale énergétique. Les bonus de performance des technologies ne peuvent pas être appliqués au combat tant que le resolver n’existe pas. L’affichage des quantités de défenses est également à compléter.

### A4 — Validation encore insuffisante pour un jeu multi-joueurs complet

Il manque notamment des tests MariaDB de recherche concurrente multi-planètes et de diplomatie, des tests HTTP d’accès à toutes les actions d’un autre joueur/univers, un test de confidentialité Mercure négatif, des tests de concurrence réelle et des scénarios de panne/reprise. Les tests navigateur utilisent un compte de démonstration mutable : la durée/coût d’un nouvel upgrade augmente d’une exécution à l’autre, donc ce test n’est pas encore indépendant de l’état.

## Vérifications réellement exécutées lors de cet audit

| Vérification | Résultat |
|---|---|
| make validate | Réussi |
| make build | Réussi, construction avec cache ; aucune installation vierge revendiquée |
| make start | Réussi ; app, DB et RabbitMQ healthy ; autres processus démarrés |
| Connexion DB depuis hôte 127.0.0.1:3309, SELECT 1 | Réussi |
| Ancien port 309 | Fermé |
| make test | **23 tests / 81 assertions**, tous réussis |
| make lint | PHP, 59 YAML, 8 Twig, conteneur et schéma : réussis |
| doctrine:migrations:status | Quatre migrations appliquées, aucune en attente |
| composer validate --strict --no-check-publish | Réussi |
| make assets | Réussi |
| Chromium, connexion/compteurs/onglets/clavier/mobile/carte | Réussi : 1 test navigateur relancé |
| HTTP Traefik /health et /connexion | 200 |
| HTTP /.env et /.git/config | 403 |
| HTTP /composer.json | 404 |
| Scénario exploratoire reprise d’extracteur après 1h | **Défaut A1 reproduit** ; hors des 23 tests existants |

Le test navigateur Mercure a réussi précédemment ; il n’est pas revendiqué comme relancé dans cet audit. Ni la reconstruction d’une installation totalement vierge ni une validation exhaustive de sécurité/accessibilité/performance ne sont revendiquées.

## Ordre de poursuite

1. Corriger A1 et A2 avec tests de non-régression ; compléter les règles et tests du socle existant.
2. Terminer profil, effets de bâtiments et tests multi-planètes/diplomatie/confidentialité.
3. Ajouter prévisualisation et validation navigateur des missions ; compléter orbite, défense et espionnage selon les PDF.
4. Implémenter la phase combat de bout en bout : entités, camps, graine reproductible, bonus, renforts, butin, rapports et notifications.
5. Terminer fermeture asynchrone, variantes, conquête et classements.
6. Valider installation propre, dépendances reproductibles, tests navigateur indépendants, accessibilité et documentation finale.

Les arbitrages de carburant sont réglés. Les grandes fonctionnalités absentes relèvent du travail restant ; elles ne sont pas bloquées par une autorisation utilisateur manquante. Les valeurs d’équilibrage provisoires devront être validées avant une livraison finale.
