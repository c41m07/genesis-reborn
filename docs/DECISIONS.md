# Décisions et ambiguïtés — 21 octobre 2026

Les huit PDF (108 pages) sont conservés dans `docs/technic`, avec extraction intégrale paginée par sauts de page dans `docs/specifications`.

## Références techniques vérifiées
- Symfony 7.4 LTS : https://symfony.com/releases/7.4 (maintenance jusqu'en 2028, sécurité 2029). PHP 8.4 : https://www.php.net/supported-versions.php. Version compatible avec l'hôte, plutôt que les prescriptions contradictoires PHP 8.3/8.5 et Symfony 7/8 des PDF.
- MariaDB 11.8 LTS : https://mariadb.org/about/ ; Doctrine ORM 3 et DBAL 4.
- RabbitMQ 4.3 : https://www.rabbitmq.com/release-information ; broker privé au nouveau projet.
- Symfony AMQP 7.4 utilise TTL + dead-letter pour DelayStamp (vendor/symfony/amqp-messenger/Transport/Connection.php). Contrairement au PDF 6 p.7, le plugin delayed exchange n'est pas obligatoire pour ce transport. Aucun plugin additionnel nécessaire.
- Encore, Twig et Stimulus ; pas de Turbo. Authentification `form_login`, pas de Guard obsolète.

## Infrastructure
Traefik et MailHog partagés existants conservés. Route `genesis-reborn.localhost` sur entrée web. Compose `genesis_reborn_v2`, volumes dédiés et réseau privé. MariaDB publiée uniquement sur 127.0.0.1:3309 (configuration utilisateur), connexion interne database:3306. Le projet historique utilisait 8080 et MariaDB 3309 ; il a ensuite été supprimé sur instruction de l’utilisateur.
Serveur PHP Apache exposant uniquement public/, adaptation de PHP-FPM + Nginx des PDF à l'infrastructure harmonisée. Aucun démarrage ne lance de migration, import ou seed.

## Arbitrages métier
- PDF 7 plus spécifique prévaut sur les anciennes queues séparées des PDF 1/4 : QueueItem unique, trois queues par planète, id ASC, échéance calculée, token de dispatch et dépendance simple.
- PDF 5 prévaut pour l'isolation : possessions et recherches liées à PlayerUniverse, jamais au compte global.
- Les multiplicateurs de durée multiplient la durée (0.1 = dix fois plus rapide), conformément au tableau PDF 5 p.9, malgré l'exemple contradictoire de division pour travel_time p.8.
- Exploration découvre un système entier (PDF 1 p.3), malgré le schéma historique PlayerDiscovery → Planet p.9.
- Carburant : PDF 2 p.7 décrit `× ratio`, PDF 3 p.4 et exemple p.6 utilisent `÷ ratio`. Arbitrage utilisateur confirmé : `× ratio` ; à 50 %, consommation divisée par deux et durée doublée. Les départs peuvent être activés.
- Combat : PDF 1 p.6 parle d'une résolution à l'arrivée, PDF 2 p.3 précise une fenêtre de renfort puis résolution. Le supplément prévaut. La notion déterministe et les tirs probabilistes exigent une graine persistée pour rejouer un résultat.
- Les statistiques complètes des 14 vaisseaux, des mines, stockages et défenses ne sont pas fournies. Les valeurs ajoutées pour rendre une démonstration jouable sont explicitement marquées `provisional` dans les YAML ; elles ne constituent pas un équilibrage validé.
- Même niveau suivant autorisé dans une queue via depends_on ; les autres prérequis utilisent exclusivement l'état achevé, pas l'état futur.
- Annulation : taux de remboursement non défini. Aucun remboursement par défaut (coût déjà payé) ; règle provisoire affichée avant annulation.
- Boosts : mécanique documentée mais aucune source ni prix définis. Ne pas offrir de boosts gratuits aux joueurs ; application persistée interne et redispatch testés, aucun achat ou bouton joueur tant que source/prix restent indéfinis.
- Premières colonies : allocation d'une planète libre à l'inscription à un univers ; ressources et production initiales provisoires de démonstration.

- Guildes : recrutement ouvert provisoire ; le commandement peut être transféré à un membre avant le départ du fondateur ; le dirigeant peut dissoudre la guilde. Les contrats sont automatiquement rompus dès qu'une appartenance crée une incompatibilité entre guildes ennemies.

## Suppression historique autorisée le 21 octobre 2026
À la demande explicite de l'utilisateur : dossier `/home/caim/Projets/genesis_reborn`, quatre conteneurs historiques et images `genesis_reborn-app:latest` / `genesis_reborn-php:latest` supprimés. Les huit PDF copiés ont été vérifiés par SHA-256 avant suppression. Les volumes historiques sont conservés, jamais raccordés au nouveau jeu. L'infrastructure partagée n'est pas modifiée.

- Mercure : hub v0.18 configuré avec `protocol_version: 0.x` côté Symfony Mercure 0.9, qui utilise sinon le nouveau protocole 1.0. Publication et réception privée vérifiées dans Chromium.

## Règles de carburant confirmées par l’utilisateur
- Consommation de déplacement = distance × consommation unitaire × quantité × ratio de vitesse. La formule inverse du PDF 3 reste uniquement un cas de comparaison historique dans les tests.
- Chaque vaisseau possède un petit réservoir dédié au carburant (`fuel_capacity`), distinct de sa soute de fret (`cargo_capacity`). Valeur provisoire : 1 000 H par vaisseau, explicitement marquée dans chaque catalogue. Les réservoirs ne transportent pas de fret. Le carburant excédant leur capacité utilise la soute cargo disponible.
- L’ensemble aller + stationnement prévu + retour est réservé au départ. Les missions actuellement disponibles (exploration, colonisation, transport) ne stationnent pas ; les futures missions d’orbite/combat devront passer leur durée prévue au calculateur, qui la prend déjà en charge.
- Port MariaDB hôte : `3309`, correction explicite de l’utilisateur ; disponibilité vérifiée. Publication uniquement sur `127.0.0.1`, configurable par `DB_HOST_PORT`. Le port `3306` interne ne change pas.

## Audit du prompt initial
Port corrigé explicitement par l’utilisateur à `3309` (et non 309). Connexion authentifiée sur 127.0.0.1:3309 vérifiée. Le jeu complet n’est pas terminé : voir `docs/AUDIT-PROMPT-INITIAL.md`. L’audit initial est conservé comme historique. Les défauts de reprise, de production et de notification identifiés ont depuis été corrigés : voir LIVRAISON-DOMAINES-UI.md et la matrice actualisée. Les combats et la phase avancée des univers restent hors de cette livraison.

## Finalisation des domaines et refonte UI
- Rattrapage chronologique des trois queues avant toute lecture de colonie ou dépense : production à l’ancien taux jusqu’à chaque échéance, nouveau taux après l’événement ; plafonds de stockage respectés. La prochaine opération commence à l’échéance de la précédente, même après interruption.
- Durées affichées et exécutées calculées par OperationCalculator ; effet réel de la centrale énergétique et du chantier. Production énergétique de la centrale : 0,75/s par niveau avant facteur position/univers, valeur provisoire explicitée dans le catalogue.
- Relais des événements et notifications séparés. Une indisponibilité Mercure conserve les notifications à retenter sans bloquer les échéances RabbitMQ. Heartbeats des trois processus et /healthz du hub contrôlés par Compose.
- Vaisseaux consommés en exploration/colonisation : si aucun autre vaisseau ne survit, mission terminée dès l’arrivée ; pas de retour à vide. Le carburant réservé non utilisé disparaît avec le vaisseau consommé ; pas de fret pour ces missions.
- Profil éditable avec mot de passe actuel obligatoire ; reconnexion après modification. Prévisualisation de mission recalculée côté serveur, accessible via CSRF et contrôles de propriété identiques au départ.
- Images PHP/Composer/MariaDB/RabbitMQ/Mercure fixées par digest ; extension amqp 2.2.0. Dépendances applicatives verrouillées par leurs lockfiles.
- Le verrou d’univers sérialise actuellement les écritures de jeu : compromis volontaire de correction pour ce périmètre, validé par deux processus réellement concurrents. Une granularité plus fine demandera des tests de charge et une évolution du protocole de verrouillage.
- UI fondée sur six références publiques et des SVG originaux ; détails et limites dans UI-RECHERCHE.md. Pas d’écran de combat fictif ajouté.

## Navigation multi-colonies et suivi dans la scène
- Sélecteur des colonies de l’inscription active en haut du menu, à la place de la carte « Commandant ». La sélection est mémorisée en session par inscription à un univers. « Vue générale » conserve cette sélection ; une colonie étrangère explicitement demandée est refusée.
- Menu « Mon empire » : Vue générale puis Recherche ; menu « Ma colonie » : Bâtiments, Chantier spatial, Défenses. Les liens ciblent explicitement l’ID de la colonie active et la section ; changer de colonie conserve la section. Le choix reste utilisable sans JavaScript par soumission du formulaire.
- Trois lignes dans la scène planétaire, chacune avec commande active, `+x` commandes supplémentaires, progression de la commande active et compte à rebours jusqu’à la fin de la file entière. Le détail conserve les annulations en cascade. Vaisseaux et défenses partagent toujours la troisième file conformément au PDF 7.
- Suivi « Flottes en vol » au-dessus de la scène et sur la page Flottes : toutes les flottes de l’inscription active, quelle que soit leur colonie d’origine ; aucun vol d’un autre joueur/univers. Progression distincte de l’aller ou du retour, route et temps restant avant la prochaine arrivée. Les flottes terminées restent dans le journal, pas dans ce suivi actif.
- Les barres Stimulus extrapolent les dates autoritaires, sans modifier les durées ni l’état métier. Au terme, une actualisation est demandée ; tant que le serveur finalise, réessai visuel toutes les cinq secondes. Le détail de chaque file reste consultable.

## Simplification finale du menu et bandeau fixe
Sur demande utilisateur : suppression des onglets « Gestion de la colonie » et du bloc inventaire. Le menu est la navigation principale vers chaque catégorie, rendue seule par Symfony. Colonie regroupe Bâtiments/Chantier spatial/Défenses ; Empire regroupe Vue générale/Recherche/Carte/Flottes/Diplomatie ; Commandement regroupe Univers/Profil et la déconnexion reste en bas. Les quantités militaires sont conservées sur leurs cartes de catalogue.

La barre de contexte et le bandeau de ressources sont sticky avec des niveaux d’empilement inférieurs au menu mobile. Décalage testé : 68 px sur ordinateur, 60 px sur mobile. Défilement horizontal conservé à l’actualisation des ressources. Aucun changement de données ou de règles métier.


## Mise à jour — 31 octobre 2026

La phase 02 a ajouté le combat persistant, défense et orbite temporisées, butin, conquête JcE des planètes PNJ et espionnage. Les décisions détaillées et les coefficients provisoires figurent dans docs/agent/PHASE_02_DECISIONS.md. La conquête ne permet pas de prendre une planète détenue par un joueur; les bâtiments, stocks et développement de la planète PNJ sont conservés après capture. Les sondes d’espionnage ciblent uniquement des planètes connues non possédées par l’émetteur, sans escorte.

Validation du lot : 67 tests / 335 assertions; lint PHP/YAML/Twig, schéma, Compose et installation vierge avec 11 migrations réussis. Ce résultat n’est pas une validation de l’équilibrage final, de l’accessibilité exhaustive, de la sécurité complète ou de la charge.
