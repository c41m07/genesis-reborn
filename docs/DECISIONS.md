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
- Combat : la cadence confirmée par Kévin est implémentée et vérifiée : un round par minute, jusqu’à 10 rounds ou clôture anticipée, avec les renforts intégrés au prochain round éligible. Le premier round commence à +60 secondes et le 10e au plus tôt à +600 secondes.
- Les statistiques de départ des unités, les stocks, productions, coûts, durées, boucliers, contres, espionnage, profils PNJ et protections ont été choisis pendant le QCM du 2 novembre 2026. Ils servent de base à l’Alpha; les objectifs de progression et leurs effets sur le rythme sont à mesurer auprès des joueurs. Détail et formules : docs/agent/BALANCING_QCM_2026-11-02.md.
- Même niveau suivant autorisé dans une queue via depends_on ; les autres prérequis utilisent exclusivement l'état achevé, pas l'état futur.
- Annulation : Kévin confirme le 01/11/2026 un remboursement de **25 % des coûts effectivement payés** pour chaque opération annulée, y compris ses éléments dépendants annulés en cascade. Le remboursement est plafonné par la capacité de stockage restante de chaque ressource. La règle s’applique aux files de bâtiments, recherches, vaisseaux et défenses.
- Boosts : Kévin choisit le 01/11/2026 des boosts obtenus principalement comme récompenses d’événements, avec un apport secondaire plafonné par les missions à risque; aucun achat réel. Chaque boost retire 25 % du temps restant de l’opération chronométrée active, y compris les missions de flotte; les boosts se cumulent successivement sur le temps restant. Le plafond des missions à risque est fixé à un boost maximum par joueur et par jour civil UTC, avec réinitialisation à minuit UTC; le jour de comptabilisation est le jour UTC de fin de mission et d’attribution du boost; ce plafond ne concerne pas les récompenses d’événements. Les missions éligibles sont l’attaque, la défense, la conquête JcE, l’exploration et l’espionnage. Une mission à risque ne donne un boost que si son objectif est atteint; une attaque n’est réussie qu’en cas de victoire des attaquants (égalité ou défaite : aucun boost); une défense est réussie en cas de victoire des défenseurs, y compris si les attaquants battent en retraite à la limite des rounds; une conquête JcE à risque réussit uniquement si la planète PNJ est capturée et passe sous le contrôle du joueur; une exploration à risque ne réussit que si elle découvre un système jusque-là inconnu; une mission d’espionnage à risque ne réussit que si elle n’est pas détectée et révèle au moins une catégorie de renseignement; un échec ou un espionnage détecté ne donne pas de boost. Le moteur de récompenses est implémenté et testé pour ces types de mission; les preuves sont consignées dans PHASE_07_BUILDINGS_TECHNOLOGIES_AUDIT.md. Le rythme initial des événements est d’une fois par semaine, avec un démarrage commun chaque lundi à 00 h UTC, modifiable en configuration; la durée est configurable pour chaque événement, avec une valeur par défaut de 7 jours; les événements hebdomadaires peuvent se chevaucher, dans la limite d’un nombre maximal d’événements actifs configurable, fixé à 2 par défaut; si le plafond est atteint à l’heure prévue, l’événement est mis en attente et démarre dès qu’une place se libère; s’il y en a plusieurs, le plus ancien événement prévu démarre d’abord; sa durée commence à son démarrage effectif; un joueur reçoit un boost par événement hebdomadaire qu’il termine. Le mode de récompense est configurable événement par événement : top 3, seuil de contribution ou tous les contributeurs. En mode « tout le monde », tout joueur ayant contribué reçoit un boost même si l’objectif collectif échoue. En mode « seuil de contribution », un seuil minimal est défini séparément pour chaque événement selon son objectif; atteindre ce seuil individuel suffit même si l’objectif global échoue. Pour un événement collectif en mode « top 3 », l’objectif collectif doit être réussi pour attribuer les boosts. En mode « top 3 », le classement utilise la même métrique de contribution que celle configurée pour l’événement; tous les joueurs à égalité à la troisième place reçoivent le boost, même si plus de trois joueurs sont ainsi récompensés. Les boosts d’événement sont attribués à la clôture, après figement des contributions et du classement. Les boosts gagnés n’expirent pas et restent dans l’inventaire jusqu’à utilisation; le stock maximal par joueur est plafonné avec une limite configurable, fixée à 3 boosts par défaut; lorsqu’une récompense est attribuée alors que le stock est plein, elle reste en attente jusqu’à ce qu’une place se libère. Toutes les récompenses de boosts attribuées pendant que le stock est plein, qu’elles proviennent d’un événement ou d’une mission à risque, sont conservées sans limite dans une file d’attente; le plafond de 3 s’applique aux boosts disponibles dans l’inventaire, pas au total des récompenses en attente. Dès qu’une place se libère, la récompense en attente la plus ancienne est transférée automatiquement dans l’inventaire. Un joueur reçoit au maximum un boost par événement; la contribution est mesurée par une métrique propre à chaque événement et liée à son objectif; les modalités techniques de calcul restent à préciser.
- Première colonie : allocation d’une planète libre à l’inscription. Les stocks, productions et autres valeurs initiales de l’Alpha sont définis dans BALANCING_QCM_2026-11-02.md.

- Guildes : recrutement ouvert provisoire ; le commandement peut être transféré à un membre avant le départ du fondateur ; le dirigeant peut dissoudre la guilde. Les contrats sont automatiquement rompus dès qu'une appartenance crée une incompatibilité entre guildes ennemies.

## Suppression historique autorisée le 21 octobre 2026
À la demande explicite de l'utilisateur : dossier `/home/caim/Projets/genesis_reborn`, quatre conteneurs historiques et images `genesis_reborn-app:latest` / `genesis_reborn-php:latest` supprimés. Les huit PDF copiés ont été vérifiés par SHA-256 avant suppression. Les volumes historiques sont conservés, jamais raccordés au nouveau jeu. L'infrastructure partagée n'est pas modifiée.

- Mercure : hub v0.18 configuré avec `protocol_version: 0.x` côté Symfony Mercure 0.9, qui utilise sinon le nouveau protocole 1.0. Publication et réception privée vérifiées dans Chromium.

## Règles de carburant confirmées par l’utilisateur
- Consommation de déplacement = distance × consommation unitaire × quantité × ratio de vitesse. La formule inverse du PDF 3 reste uniquement un cas de comparaison historique dans les tests.
- Chaque vaisseau possède un petit réservoir dédié au carburant (`fuel_capacity`), distinct de sa soute de fret (`cargo_capacity`). Capacité dédiée choisie par type d’unité dans BALANCING_QCM_2026-11-02.md; elle est distincte du coût de fabrication et de la soute cargo. Les réservoirs ne transportent pas de fret. Le carburant excédant leur capacité utilise la soute cargo disponible.
- L’ensemble aller + stationnement prévu + retour est réservé au départ. Les missions actuellement disponibles (exploration, colonisation, transport) ne stationnent pas ; les futures missions d’orbite/combat devront passer leur durée prévue au calculateur, qui la prend déjà en charge.
- Port MariaDB hôte : `3309`, correction explicite de l’utilisateur ; disponibilité vérifiée. Publication uniquement sur `127.0.0.1`, configurable par `DB_HOST_PORT`. Le port `3306` interne ne change pas.

## Audit du prompt initial
Port corrigé explicitement par l’utilisateur à 3309 (et non 309); connexion authentifiée sur 127.0.0.1:3309 vérifiée. L’état Alpha, les critères de production et les preuves courantes sont maintenus dans docs/agent/ALPHA_READINESS.md, docs/agent/PHASE_15_FINAL_AUDIT.md et docs/agent/REQUIREMENTS_MATRIX.md.

## Finalisation des domaines et refonte UI
- Rattrapage chronologique des trois queues avant toute lecture de colonie ou dépense : production à l’ancien taux jusqu’à chaque échéance, nouveau taux après l’événement ; plafonds de stockage respectés. La prochaine opération commence à l’échéance de la précédente, même après interruption.
- Durées affichées et exécutées calculées par OperationCalculator ; effet réel de la centrale énergétique et du chantier. Production énergétique de la centrale : +0,1 énergie/s par niveau selon le réglage de départ choisi pour l’Alpha; la configuration doit rester cohérente avec le taux d’entretien énergétique des extracteurs.
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

La phase 02 a ajouté le combat persistant, défense et orbite temporisées, butin, conquête JcE des planètes PNJ et espionnage. Les décisions de combat et les valeurs du QCM figurent dans docs/agent/PHASE_02_DECISIONS.md et docs/agent/BALANCING_QCM_2026-11-02.md. La conquête ne permet pas de prendre une planète détenue par un joueur; les bâtiments, stocks et développement de la planète PNJ sont conservés après capture. Les sondes d’espionnage ciblent uniquement des planètes connues non possédées par l’émetteur, sans escorte.

Validation du lot : 67 tests / 335 assertions; lint PHP/YAML/Twig, schéma, Compose et installation vierge avec 11 migrations réussis. Ce résultat n’est pas une validation de l’équilibrage final, de l’accessibilité exhaustive, de la sécurité complète ou de la charge.


## Mise à jour — cible technique, 31 octobre 2026

L’utilisateur a retenu PHP 8.4 et Symfony 7.4 LTS pour le dépôt; l’exigence initiale PHP 8.5/Symfony 8.x est remplacée pour la cible courante par addendum à la spécification 6. Motif : conserver la branche LTS et éviter une migration majeure avant que le jeu et ses contrôles ne soient stabilisés. Symfony 7.4 est maintenu jusqu’en 2029 selon https://symfony.com/releases/7.4. Le runtime actuel est conservé; l’installation vierge et les migrations avaient déjà passé les contrôles consignés dans TEST_STATUS.md.


## Mise à jour — fermeture asynchrone, 31 octobre 2026

Les univers temporaires utilisent le modèle Outbox et le transport Messenger existants. Un jeton dérivé de l’univers et de endsAt empêche une ancienne échéance de fermer un univers dont la date a été repoussée. Le statut passe à closed sans supprimer les affiliations, planètes ni stocks. Les références et validations figurent dans docs/agent/PHASE_05_UNIVERSE_AUDIT.md.


## QCM d’équilibrage validé par Kévin — 01–02/11/2026

Kévin choisit une stratégie modérée et fixe les valeurs de départ des cinq ressources, leur production, le stockage, les coefficients de coûts/durées, les statistiques et coûts des unités, les règles de bouclier, l’espionnage, les planètes PNJ et les protections après pertes. Les valeurs complètes sont consignées dans docs/agent/BALANCING_QCM_2026-11-02.md. Les décisions de combat déjà confirmées (round/minute, dix minutes, butin 1,0 et protection de stockage 0,1) restent inchangées. Pour la matrice de combat, les coefficients explicites du PDF priment; le cycle de rôles complète uniquement les paires sans valeur documentée. La table PNJ multiplie uniquement les stocks initiaux.


## Rythme des déblocages — 4 novembre 2026

Décision utilisateur : renforcer les prérequis, car les vaisseaux et recherches de base se débloquaient trop tôt. Les jalons visés sont : early game, première colonie en 3–5 jours ; mid game, flotte spécialisée en 4–6 semaines ; late game, cuirassé en 2–3 mois ; very late game, premier vaisseau capital en 4–6 mois. Les catalogues ajoutent des chaînes progressives pour les bâtiments, les recherches, les vaisseaux et les quatre familles de défenses ; les niveaux supérieurs des bâtiments et les rangs de défense ont leurs propres seuils. Les coûts, statistiques et durées existants restent inchangés. Ces durées restent des cibles à mesurer en Alpha, pas des garanties : l’économie, les bonus de planète et la recherche parallèle influencent le temps réel.

## Progression par rangs — 2 novembre 2026

Décision confirmée : Basique, Intermédiaire et Supérieur sont des pistes indépendantes et simultanées. Les données historiques deviennent Basique. Les multiplicateurs de rang sont 1×, 1,25× et 1,5625×, appliqués cumulativement à la puissance, à chaque ressource du coût et aux durées d’opération. La recherche conserve ×1,8 de temps et ×2 de coût par niveau; les durées de construction ×1,6. Les technologies précédemment à +5 % par niveau deviennent +1 % avec niveau maximum 25; laboratoire et chantier spatial sont à +2 % avec niveau maximum 25. Mines, centrale et stockage n’ont pas de niveau maximum. Les déblocages Intermédiaire/Supérieur sont thématiques, aux seuils 5 Basique puis 10 Intermédiaire.

La puissance de vaisseau suit sa fonction et ses statistiques de base. Les nombres de tirs restent entiers grâce à une chance de tir supplémentaire par tir de base. L’arrondi des ressources positives et des statistiques entières dérivées est vers le haut; zéro reste zéro.


## Contres sur les chances de toucher et tirs secondaires — 4 novembre 2026

Décision explicite de Kévin : un même coefficient de contre ou de faiblesse multiplie à la fois les dégâts d’un impact et la chance de toucher calculée. Il s’agit d’une multiplication relative : ×1,30 transforme 50 % en 65 %, et non en 80 %. Le coefficient est appliqué une fois à chacune des deux grandeurs, pour les tirs principaux, supplémentaires, secondaires et les défenses.

La chance de base est calculée avec précision, suivi, esquive, correction de l’arme et écart de rang, puis bornée à 15–95 %. Le coefficient multiplie cette chance ; le résultat est à nouveau borné à 15–95 %. Les dégâts utilisent le même coefficient, sans second cumul de faiblesse réciproque.

Les armes secondaires des destroyers, croiseurs, cuirassés et vaisseaux capitaux reçoivent toutes une correction de précision de **+30 points**, en remplacement des anciens −20 / −25. Leur nombre de tirs, leurs dégâts de base et leur filtre de cible restent ceux du catalogue. Les caractéristiques secondaires et la catégorie sont conservées dans l’instantané du simulateur pour que les tirs soient réellement exécutés.


Les contres affichés dans les fiches utilisent des noms de famille génériques et stables (Corvette, Capital, Chasseur furtif, etc.), communs à tous leurs rangs. Le nom d’illustration ou de variante (Dreadnought, Vaisseau amiral, etc.) ne détermine pas la relation. La matrice existante est indexée par les familles de coque. Une relation générique envers les défenses au sol s’applique à toutes les tourelles ; une relation explicite envers une tourelle reste prioritaire.


## Classe de contre Chasseur — 4 novembre 2026

Kévin précise explicitement : **Chasseur = Chasseur standard, Chasseur furtif et Intercepteur**. Le Chasseur lourd est exclu de ce regroupement. La classe cible couvre les trois rangs de chacun de ses membres. Les coques, statistiques, spécialisations offensives et catégories de ciblage des armes secondaires restent distinctes.

La matrice de référence exprime ses relations vers cette classe une seule fois, puis le catalogue les développe vers les trois types pour les calculs et les instantanés du simulateur. Pour un même tireur, les anciens coefficients vers ces membres sont consolidés par leur valeur la plus forte : notamment +15 % et +30 % deviennent un seul +30 %, sans addition ni cumul. Les coefficients neutres identiques et les faiblesses identiques restent inchangés.

La Corvette applique ainsi ×1,30 contre les trois membres de Chasseur et garde ×1,15 contre Capital. Les fiches affichent « Chasseur » et détaillent ses membres. Les contres continuent de multiplier les dégâts et les chances de toucher selon la décision précédente.
