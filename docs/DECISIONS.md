# Décisions et ambiguïtés — 21 octobre 2026

## Comptes — décision du 6 novembre 2026

Le propriétaire demande un mail de bienvenue et une vérification d’adresse avant l’accès pour les nouveaux comptes. Les comptes déjà présents conservent leur accès lors de la migration. Le parcours, la configuration et les limites sont décrits dans [ACCOUNT_EMAIL.md](ARCHITECTURE.md).

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
- Les statistiques de départ des unités, les stocks, productions, coûts, durées, boucliers, contres, espionnage, profils PNJ et protections ont été choisis pendant le QCM du 2 novembre 2026. Ils servent de base à l’Alpha; les objectifs de progression et leurs effets sur le rythme sont à mesurer auprès des joueurs. Détail et formules : docs/archives/CHOIX_EQUILIBRAGE_2026-11-02.md.
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
Port corrigé explicitement par l’utilisateur à 3309 (et non 309); connexion authentifiée sur 127.0.0.1:3309 vérifiée. L’état Alpha, les critères de production et les preuves courantes sont maintenus dans docs/agent/ALPHA_READINESS.md, docs/agent/PHASE_15_FINAL_AUDIT.md et docs/archives/EXIGENCES_WORK.md.

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

La phase 02 a ajouté le combat persistant, défense et orbite temporisées, butin, conquête JcE des planètes PNJ et espionnage. Les décisions de combat et les valeurs du QCM figurent dans docs/archives/DECISIONS_INITIALES.md et docs/archives/CHOIX_EQUILIBRAGE_2026-11-02.md. La conquête ne permet pas de prendre une planète détenue par un joueur; les bâtiments, stocks et développement de la planète PNJ sont conservés après capture. Les sondes d’espionnage ciblent uniquement des planètes connues non possédées par l’émetteur, sans escorte.

Validation du lot : 67 tests / 335 assertions; lint PHP/YAML/Twig, schéma, Compose et installation vierge avec 11 migrations réussis. Ce résultat n’est pas une validation de l’équilibrage final, de l’accessibilité exhaustive, de la sécurité complète ou de la charge.


## Mise à jour — cible technique, 31 octobre 2026

L’utilisateur a retenu PHP 8.4 et Symfony 7.4 LTS pour le dépôt; l’exigence initiale PHP 8.5/Symfony 8.x est remplacée pour la cible courante par addendum à la spécification 6. Motif : conserver la branche LTS et éviter une migration majeure avant que le jeu et ses contrôles ne soient stabilisés. Symfony 7.4 est maintenu jusqu’en 2029 selon https://symfony.com/releases/7.4. Le runtime actuel est conservé; l’installation vierge et les migrations avaient déjà passé les contrôles consignés dans TEST_STATUS.md.


## Mise à jour — fermeture asynchrone, 31 octobre 2026

Les univers temporaires utilisent le modèle Outbox et le transport Messenger existants. Un jeton dérivé de l’univers et de endsAt empêche une ancienne échéance de fermer un univers dont la date a été repoussée. Le statut passe à closed sans supprimer les affiliations, planètes ni stocks. Les références et validations figurent dans docs/agent/PHASE_05_UNIVERSE_AUDIT.md.


## QCM d’équilibrage validé par Kévin — 01–02/11/2026

Kévin choisit une stratégie modérée et fixe les valeurs de départ des cinq ressources, leur production, le stockage, les coefficients de coûts/durées, les statistiques et coûts des unités, les règles de bouclier, l’espionnage, les planètes PNJ et les protections après pertes. Les valeurs complètes sont consignées dans docs/archives/CHOIX_EQUILIBRAGE_2026-11-02.md. Les décisions de combat déjà confirmées (round/minute, dix minutes, butin 1,0 et protection de stockage 0,1) restent inchangées. Pour la matrice de combat, les coefficients explicites du PDF priment; le cycle de rôles complète uniquement les paires sans valeur documentée. La table PNJ multiplie uniquement les stocks initiaux.


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


## R14 — combats Alpha, décision du 5 novembre 2026

L’utilisateur autorise l’implémentation complète de R14 sur sa branche de test Alpha après l’étude chiffrée. Les neuf classes du tableau reçoivent les coûts réels par rang, les statistiques et les 22 coefficients de R14 ; les relations hors de ce tableau restent conservées après développement des groupes. Les profils de ressources restent au format JSON malgré leur extension `.yaml`.

Règles adoptées : bouclier épuisé non rechargeable pendant le combat ; explosion critique progressive sur le prochain tir qui touche sous 25 % de structure ; priorité aux cibles déjà entamées, pondérée par signature ; dégâts multipliés par le carré de la fraction structure+bouclier restante au début du tour ; tir rapide appliqué aux secondaires ; avantage de rang de 30 points après le coefficient ; progression HP/bouclier/dégâts de 1/1,5/2,25 pour ces neuf classes seulement. La limite de dix rounds et le verdict de retraite restent conservés.

Le rendement économique de référence compare la valeur adverse détruite à la valeur propre détruite sur toutes les victoires et défaites : objectif moyen ≥ 1,25 à environ moitié budget. Poids M=2, N=4, A=8, H=1 ; dommages survivants, carburant, fret, butin et réparations sont séparés. Les temps, prérequis, mobilité, capacités et assets restent conservés. Aucun coût de réparation ni persistance individuelle des HP n’est introduit.

L’étude a contrôlé 439 configurations distinctes à demi-budget, les rangs, les escortes par remplacement et des compositions mixtes. Furtif, corvette, défenses, arrivées décalées et métajeu restent des contrôles Alpha ; cette adoption n’ajoute pas de rôle implicite à ces classes. Des surcharges de catalogue propres à un univers peuvent toujours remplacer les valeurs de base : elles doivent être examinées sans réinitialisation automatique des données existantes.

### 5 novembre 2026 — Finalité de l’arbre technologique

L’arbre sert à comprendre comment débloquer un bâtiment, une recherche, un vaisseau ou une défense encore inaccessible. Il conserve donc les éléments verrouillés indépendamment de la préférence de masquage du catalogue. Une recherche affiche les correspondances et tous leurs prérequis directs et indirects, même dans les autres catégories ; les correspondances sont mises en évidence. Les liens depuis l’arbre rendent les fiches verrouillées consultables sans enregistrer de changement de préférence. Les règles de déblocage restent inchangées.


## Vitesses des flottes — décision du 5 novembre 2026

L’utilisateur demande un Chasseur plus lent que toutes les autres classes et une Sonde rapide. Il valide 1.A / 2.A / 3.B : références au rang basique, sans recherche, départ à 100 %, univers normal ; bonus de rang/recherche et multiplicateurs d’univers conservés ; minimum d’une minute par aller et par retour, sauf flotte exclusivement composée de sondes dans le même système (30 secondes) ; distances géométriques de la carte conservées.

Les références locales depuis la position 4 vers la position 9 sont 1 h en Chasseur et 1 min en Sonde. Sur la grille actuelle, entre positions 4, A:40 → A:1 prend environ 12 h en Chasseur et 3 min en Sonde ; A:40 → A:80 est géométriquement plus court et ne peut pas avoir la même durée avec des vitesses constantes. Les profils, valeurs exactes et contrôles sont consignés dans [SPEED_BALANCE_2026-11-05.md](EQUILIBRAGE.md#vitesses-et-autonomie). Les coûts, consommations, capacités et caractéristiques de combat sont conservés.


### Correction des références de vitesse — plafond sur toutes les distances

L’utilisateur précise que A:1 et A:80 ne sont que des exemples : il faut rechercher les trajets réellement les plus longs. Cette correction remplace la calibration précédente, sans changer les distances géométriques. Sur toutes les positions des cartes du projet et de la grille générée maximale de 100 systèmes : plafond de référence de 12 h par aller/retour en Chasseur et de 3 min en Sonde ; dans un système, plafond de 1 h et de 1 min respectivement. Le trajet local de référence devient donc position 1 → 9, plus long que 4 → 9. Les minimums 60 s / exception sondes locales 30 s et les références sans recherche, rang basique, départ à 100 %, univers normal restent inchangés. Les réglages des autres classes sont rééchelonnés pour conserver leur hiérarchie et leurs rapports de vitesse. Il n’est pas introduit de plafond artificiel dans la formule de durée : réduire la vitesse de départ peut toujours dépasser les durées de référence.


### Mobilité par rôle et autonomie — décision du 5 novembre 2026

L’utilisateur remplace explicitement la demande « Chasseur le plus lent » par l’ordre suivant, appliqué dans un système comme entre systèmes, à rang/recherches comparables : Capital → Cuirassé → Colonie → Transport → Bombardier → Destroyer → Croiseur → Frégate → Chasseur lourd → Chasseur → Corvette → Furtif → Intercepteur → Sonde. Le Capital reprend les références maximales de 1 h dans un système et environ 12 h entre systèmes ; les références Sonde et les minimums restent conservés.

Chaque vaisseau doit pouvoir effectuer seul le plus long aller-retour avec son réservoir dédié, sans emprunter de capacité à sa soute. Les consommations actuelles sont identiques pour les 14 classes : 0,5 H par unité de distance normale et 2 H par unité hyperspatiale. Le besoin maximal de référence est donc identique, environ 15 453,51 H aller-retour à 100 %, hors stationnement. Les réservoirs basiques sont portés à 16 000 H, soit environ 546,49 H de marge. Les rangs et la recherche de réservoir restent applicables. Aucun coût de construction, statistique de combat, consommation par distance ni capacité de fret n’est changé par cette décision. Une différenciation future des consommations par rôle reste une décision séparée.


### Soutes des vaisseaux — validation du 5 novembre 2026

L’utilisateur valide explicitement les capacités basiques proposées : Sonde 0 ; Intercepteur 100 ; Chasseur 200 ; Bombardier 200 ; Furtif 300 ; Chasseur lourd 500 ; Corvette 1 000 ; Colonie 1 000 ; Frégate 1 500 ; Destroyer 2 500 ; Croiseur 4 000 ; Cuirassé 8 000 ; Capital 15 000 ; Transport 50 000, valeur conservée. Les unités de combat peuvent ramener un petit butin ; le transport conserve une capacité par coût nettement supérieure. La Sonde reste sans fret.

Les facteurs de soute par rang restent 1 / 1,25 / 1,5625, avec arrondi supérieur avant recherche ; la recherche Soute compartimentée s’applique ensuite normalement. Les réservoirs dédiés de carburant, consommations, vitesses, coûts et caractéristiques de combat restent inchangés. Il s’agit des valeurs de base : une surcharge d’univers peut toujours les remplacer. Aucune migration ni modification des missions déjà enregistrées n’est effectuée.


## Kit de colonisation par rang — 5 novembre 2026

Le kit de ressources de départ d’une nouvelle colonie suit le multiplicateur du rang du vaisseau colonisateur consommé : Basique ×1, Intermédiaire ×1,25, Supérieur ×1,5625. En flotte mixte, le rang le plus bas disponible reste consommé en priorité et détermine le kit ; les autres vaisseaux ne cumulent aucun bonus. Les quantités fractionnaires sont conservées, dans la limite du stockage. Cette décision concerne la fondation sur planète vierge, pas la conquête.


## Bâtiments — cadence A et centrale ×2, décision du 5 novembre 2026

Après l’étude, l’utilisateur valide A pour un remboursement de référence d’environ 2–3 semaines de l’amélioration T1 de mine de métal vers le niveau 80 en univers normal, puis A pour une centrale deux fois plus productive et une progression énergétique T2/T3 utile. Le candidat retenu applique aux producteurs les facteurs de production et consommation 1 / 1,5 / 2,25 ; la centrale basique passe de 0,1 à 0,2 électricité/s/niveau. Les consommations de base et les profils des autres ressources restent conservés.

Les coûts des seuls producteurs suivent ×1,12 jusqu’au niveau 20, ×1,09 jusqu’à 40, ×1,035 jusqu’à 80, ×1,015 jusqu’à 120, puis ×1,01. Les facteurs de durée, les profils de coûts par rang, les capacités de stockage et les effets des bâtiments utilitaires restent distincts. Une usine à stockage plein ne gaspille plus ses consommations ; hydrogène et électricité continuent à fournir les consommateurs locaux. Les résidus numériques ne doivent plus tronquer une absence de production.

L’intégration et ses vérifications sont détaillées dans [BUILDING_BALANCE_2026-11-05.md](EQUILIBRAGE.md#production-des-batiments). Elle conserve les opérations déjà payées, ne réinitialise aucune donnée et n’applique aucune migration. Les surcharges d’univers restent prioritaires. La validation économique globale reste à confirmer en Alpha.

### Messagerie et panneaux d’alliances — 5 novembre 2026

- La recherche de destinataires porte sur les pseudos des joueurs du même univers, à partir de deux caractères. La liste privée ne présente que les joueurs avec lesquels au moins un message a été échangé.
- Le canal de guilde reste en tête. Les conversations privées comportant des messages non lus précèdent les autres, puis suivent l’ordre du dernier message. Une pastille rouge signale les non-lus dans les conversations et sur l’entrée « Messages » de la navigation. Les indicateurs sont actualisés à réception d’une notification et toutes les quinze secondes.
- L’ouverture d’une conversation marque les messages affichés comme lus. La lecture du canal de guilde est suivie séparément pour chaque joueur et chaque guilde.
- La page Alliances s’ouvre sur « Guildes ». Les liens sont ordonnés « Guildes », « Diplomatie », « Relation » et seul le panneau sélectionné est affiché. « Diplomatie » regroupe les relations entre guildes, « Relation » les contrats entre commandants.

### Canal Global — 9 novembre 2026

- Le canal « Global » est accessible aux joueurs connectés de l’univers courant, avec ou sans guilde, et se place au-dessus du canal de guilde. Cette décision remplace l’ordre des canaux défini le 5 novembre.
- La lecture et l’envoi réutilisent les contrôles d’accès de la messagerie ; les messages suivent la validation de 1 à 2 000 caractères et la limitation partagée des envois.
- Les messages Global ne déclenchent aucune notification et ne participent à aucun badge ni compteur de non-lus, y compris dans les totaux et la navigation. Leur consultation ne marque pas les autres conversations comme lues.
- Les 100 derniers messages sont affichés dans l’ordre chronologique. Les conversations privées et de guilde conservent leur comportement.

### Limites par défaut des univers — 5 novembre 2026

Le nombre maximum de vaisseaux par flotte est fixé par défaut à **1 000 000** et le plafond de planètes à **31**. Ces valeurs s’appliquent aux profils de référence et aux nouveaux univers. Les réglages personnalisés d’un univers restent prioritaires. Le plafond de planètes ne modifie pas les prérequis de recherche de colonisation.

### Capacité d’inventaire — 5 novembre 2026

L’inventaire peut contenir **100 bonus disponibles** par joueur et par univers. Les récompenses supplémentaires restent en attente et rejoignent l’inventaire lorsqu’une place se libère. Cette capacité remplace la limite précédente de 3 emplacements.

### Hub de guilde et relations personnelles — 5 novembre 2026

- L’onglet Guildes rassemble le rôle du joueur, l’équipage, les nombres de guildes alliées et ennemies, un aperçu des trois derniers messages du canal et les accès à la discussion, à la diplomatie, à la galaxie et au classement. L’aperçu ne marque pas les messages comme lus.
- L’annuaire indique les membres et permet de contacter le commandant. La fondation, le recrutement ouvert et les permissions de commandement conservent leurs règles existantes.
- L’onglet Relation présente par défaut les contrats et propositions du joueur, ainsi que les alliances et hostilités issues des guildes. Une recherche compacte par pseudo permet de trouver de nouveaux contacts du même univers, à partir de deux caractères, sans remplacer les relations existantes.

### Classements, profil et recherche diplomatique — 5 novembre 2026

- Le profil comporte deux onglets : Profil et Paramètres.
- Le classement joueur présente, après le rang et le pseudo, les points de bâtiments, de recherche, de flotte puis le total. Le rang repose sur les points totaux ; les planètes ne figurent plus dans ce tableau.
- Les lignes ennemies sont teintées en rouge, les alliances de guilde en bleu et les alliances personnelles en vert, avec un libellé textuel. Priorité : hostilité de guilde, alliance de guilde (y compris sa propre guilde), puis contrat personnel accepté. La ligne du joueur conserve son identification « Vous ».
- Le classement des guildes additionne les points des membres actuels par catégorie. Il indique l’effectif, les totaux et la médiane des scores individuels (moyenne des deux valeurs centrales pour un effectif pair). Le rang dépend du total ; les égalités sont départagées par nom, puis identifiant.
- Diplomatie affiche les relations de guilde existantes et une recherche compacte par nom, limitée à l’univers courant. La recherche permet aussi de rejoindre une guilde. L’annuaire « Guildes de l’univers » est retiré du hub pour éviter le doublon.

### Accès aux paramètres et relations du classement des guildes — 5 novembre 2026

L’accès aux paramètres passe désormais par une roue crantée à côté du pseudo dans l’en-tête. Les onglets Profil/Paramètres sont retirés des deux pages. Les titres intermédiaires des classements et le paragraphe explicatif du classement des guildes sont retirés. Les guildes alliées (dont la guilde du joueur) sont teintées en bleu et les guildes ennemies en rouge ; une légende et des libellés complètent la couleur.

### En-têtes et onglets de rapports — 5 novembre 2026

Les surtitres eyebrow sont retirés de l’interface, en conservant les métadonnées utiles. Les paragraphes d’aide gv2-access-help sont retirés de la galaxie interactive. Les onglets du centre de rapports retrouvent leur style antérieur, contrairement aux autres onglets en pastilles.


## Répartition des ressources des vaisseaux — décision du 5 novembre 2026

L’utilisateur valide l’application aux 14 classes des parts en valeur économique : T1 60 % métal / 40 % nano ; T2 45 % métal / 45 % nano / 10 % antimatière ; T3 30 % métal / 50 % nano / 20 % antimatière. Chaque profil de rang conserve exactement sa valeur réelle avant recherche (M×2, N×4, A×8, H×1), avec des quantités entières et des arrondis ajustés ensemble. Le Chasseur conserve les budgets 6 000 / 7 504 / 9 380 et reçoit les quantités de l’exemple approuvé.

Les réductions de recherche continuent d’arrondir chaque ressource : de petits écarts économiques après recherche sont possibles et mesurés, jusqu’à 10 unités par commande dans les devis de référence. Durées, statistiques, contres, mobilité, capacités et autres catalogues restent conservés. Les coûts déjà payés ne sont pas réécrits et les surcharges d’univers restent prioritaires. Voir [SHIP_COSTS_2026-11-05.md](COUTS.md) pour les 42 profils, budgets et vérifications. Les besoins réels de production et les pénuries restent à mesurer en Alpha.


## Grille commune et hydrogène industriel T3 — décision du 5 novembre 2026

L’utilisateur valide la progression T1 métal/nano, T2 ajout d’antimatière, T3 ajout d’hydrogène, et demande son application aux bâtiments, chantier spatial, défenses et recherches. Les parts en valeur retenues sont T1 M60/N40 ; T2 M45/N45/A10 ; T3 M30/N45/A20/H5. Les 50 fiches et 150 profils conservent exactement leur valeur économique de base réelle (M×2, N×4, A×8, H×1), avec des quantités entières. Les courbes de niveau et les réductions restent inchangées ; les coûts réels après leurs arrondis peuvent légèrement différer, et sont vérifiés séparément.

L’hydrogène de construction/recherche est une dépense industrielle, distincte du carburant réservé au départ des missions et des consommations des usines. Les coûts enregistrés des commandes déjà payées restent conservés. Les surcharges d’univers sont préservées champ par champ. L’étude et les vérifications de démarrage, progression et conservation sont consignées dans [COUTS.md](COUTS.md). Aucun prix d’asset premium ni réglage d’univers n’est modifié par cette décision.


## Déblocages initiaux permanents — décision du 6 novembre 2026

Les prérequis pendant les niveaux sont supprimés dans les quatre catégories. Chaque rang conserve des conditions initiales puis un accès acquis : colonie pour constructions et unités, univers pour recherches. Le laboratoire devient une condition initiale explicite de toutes les recherches, commune au serveur et à l’interface. Coûts, durées, ressources, plafonds et conditions des missions restent appliqués.

Le choix livré garde un démarrage accessible, retire les anciens paliers sans les cumuler, reprend un jalon léger pour le chantier et un développement hydrogène/naval pour le réacteur. Le stockage s’ouvre au réacteur 1. Les conditions militaires restent graduelles. Huit accès hors plafond sont corrigés sans augmenter les bonus. Le [tableau des 50 éléments et 150 profils](DEBLOCAGES.md) explique chaque choix et la conservation historique par registre.

Travail sur dev, sans fusion sur alpha ni déploiement. Migration additive et commande de reprise préparées ; exécution sur la base active soumise à validation selon les consignes du projet.


### Application locale validée — 6 novembre 2026

Après accord explicite de l’utilisateur, la migration du registre des déblocages est appliquée sur la base locale utilisée par dev, précédée d’une sauvegarde. Les 5 participations existantes sont reprises, puis le contrôle indique zéro participation restante. Les empreintes des données de progression et des files sont identiques avant/après. `make validate` et `make lint` réussissent. Aucun déploiement ni fusion vers Alpha. Voir le [bilan d’application](DEBLOCAGES.md#application-locale--6-novembre-2026).

### Fighter : chantier spatial requis — 6 novembre 2026

À la demande explicite de l’utilisateur, le déblocage initial du Fighter basique exige désormais un chantier spatial basique niveau 1. Les conditions des rangs intermédiaire et supérieur restent conservées. Les accès déjà acquis restent régis par le registre persistant existant.

## Alertes d’exploitation — 6 novembre 2026

Décision explicite du propriétaire : courriel vers une adresse conservée en configuration privée ; profil modéré, signal absent 120 secondes, relais en retard de 300 secondes, rappel au maximum chaque heure, échec définitif dès détection et message de rétablissement. Le contrôle d’arrêt confirmé reste distinct de la seule absence d’un signal. Ce choix ne modifie aucune règle de jeu et ne vaut pas preuve d’activation sur l’Alpha.


## Empires oubliés, vacances et occupation JcE — décisions du 7 novembre 2026

**Statut : règles validées ; premier socle de suivi d'activité préparé, système
complet non activé.** Le code enregistre la dernière visite par univers et calcule
les échéances de 14 et 30 jours. La migration additive `Version20261107180000`
a été appliquée sur la base locale après accord explicite et sauvegarde le
7 novembre 2026 ; elle laisse les anciennes dates inconnues à `NULL`, sans reprise
rétroactive. Les courriels J14/J27 sont préparés et testés sans envoi réel ;
l'utilisateur a validé leurs deux textes le 7 novembre 2026. Cet accord porte
sur le contenu, sans autoriser l'activation, les envois réels ou une nouvelle
migration. Leur journal nécessite la migration
`Version20261107220000`, appliquée localement après un accord distinct et
sauvegarde le 7 novembre 2026. Les envois restent désactivés en
configuration et sans planification automatique. La suspension des protections
à J14 est préparée derrière `FORGOTTEN_EMPIRES_ENABLED=0`, sans activation.
Les vacances sont raccordées derrière le drapeau désactivé ; un service interne
de conversion PNJ est préparé et testé, sans point d'entrée d'application ni
planification. L'occupation reste à développer. Une prévisualisation J30 sans
écriture est disponible pour préparer les conversions.
La conquête immédiate décrite dans REGLES.md reste le comportement actuel jusqu'à
la livraison du nouveau système. Le suivi des lots figure dans
[FEUILLE_DE_ROUTE.md](FEUILLE_DE_ROUTE.md#empires-oubliés--chantier-validé-le-7-novembre-2026).

### Activité et perte de l'empire

- L'inactivité se mesure par joueur et par univers. Une visite authentifiée dans
  cet univers remet le compteur à zéro ; les traitements automatiques ne le font pas.
- À 14 jours sans activité, l'empire devient inactif. Un courriel et un avertissement
  visible annoncent le risque de perte définitive à 30 jours. Un second courriel
  est prévu à 27 jours.
- De 14 à 30 jours, les protections contre le pillage liées au statut du joueur
  sont neutralisées pendant l'inactivité. Une reconnexion avant 30 jours rétablit
  le fonctionnement normal, sans renouveler les protections déjà expirées.
  Les autres règles de combat restent applicables.
- À 30 jours, l'empire devient définitivement un empire oublié PNJ. Le propriétaire
  perd l'accès à cet empire, mais conserve son compte, ses préférences et son
  historique. À son retour, il peut recommencer de zéro dans cet univers selon les
  règles de départ ; il ne récupère pas son ancien empire.
- Les colonies, bâtiments, stocks, flottes, défenses et recherches existants sont
  conservés. Les recherches sont figées et continuent de déterminer les effets de
  l'empire PNJ ; aucune garnison standard ne remplace ses véritables forces.

### Mode vacances

- Complément validé le 7 novembre 2026 : la production et les files sont suspendues,
  les nouvelles missions interdites et la consultation reste autorisée. Une sortie
  volontaire n'est possible qu'après 48 heures. L'interface est raccordée mais
  masquée derrière le drapeau désactivé ; le mode n'est pas ouvert aux joueurs.
- Le mode est accessible depuis les options ou préférences et s'applique à l'empire
  de l'univers concerné. Une activation dure au maximum 90 jours.
- Une nouvelle activation est possible au plus tôt 15 jours après la fin de la
  précédente période.
- Le compteur d'inactivité est suspendu, puis reprend à sa valeur antérieure ;
  l'activation ne le remet pas à zéro. L'expiration est annoncée au joueur.
- Complément validé : toutes les flottes du joueur doivent être rentrées avant
  activation, y compris transports, explorations, colonisations, sondes et
  stationnements. Une attaque entrante ou un combat en cours bloque aussi l'entrée.
- Les défenses et orbites d'autres joueurs déjà acceptées conservent leur échéance
  normale ; les demandes d'orbite encore en attente sont refusées automatiquement
  à l'activation. Aucun nouveau stationnement n'est autorisé pendant les vacances.
- Un transport d'un autre joueur encore en route est refusé s'il arrive pendant
  les vacances ; sa cargaison et ses vaisseaux reviennent selon les règles de retour
  et de stockage existantes.

Le premier socle prépare `VacationPeriod` et `VacationPolicy` : période planifiée
de 90 jours, sortie au plus tôt après 48 heures, puis 15 jours de délai depuis
la fin réelle. Une expiration traitée tardivement conserve sa date prévue comme
fin effective ; elle ne repousse pas artificiellement la prochaine activation.
Le calcul du temps suspendu borne chaque période à l'intervalle demandé et
fusionne les chevauchements pour ne pas déduire deux fois la même durée.
Il ne remet pas à zéro l'inactivité acquise avant les vacances.

Le socle économique peut désormais enregistrer une période et coordonner la
production et les files via un service interne. Les routes POST des préférences
permettent désormais de l'activer ou le terminer avec contrôles d'accès et CSRF,
sous réserve du drapeau d'activation pour une nouvelle période.
L'activité et les avertissements sont désormais suspendus sur les périodes enregistrées.
La migration additive `Version20261107230000` prépare uniquement l'historique des
périodes, avec une relation à la participation dans l'univers ; elle a été
appliquée sur la base locale le 7 novembre 2026 après accord et sauvegarde.
Le raccordement des préférences et des files en pause est réalisé le 8 novembre
2026. Le cycle complet reste à terminer avant activation.

### Application locale de l'historique des vacances — 7 novembre 2026

Après accord explicite, une sauvegarde locale a été créée dans
`var/backups/vacation-before-20261107230000/` :
`genesis-genesis_reborn_v2-20261107T220948Z-TN2wPJrCETzV.sql.gz`.
L'intégrité gzip et le manifeste SHA-256 sont vérifiés ; aucun essai de
restauration n'est annoncé.

Une seule migration était en attente : `Version20261107230000`. Elle a exécuté
deux requêtes, créant `vacation_period` puis sa clé étrangère vers la participation
à l'univers. `make validate` et `make lint` réussissent, schéma actif compris.
Les 288 tests PHP et 13 tests Python avaient déjà réussi sur ce code dans la
base isolée ; ils n'ont pas été relancés sans changement applicatif.

Cette opération ne crée aucune période de vacances et n'active pas le mode.
La pause/reprise de production et des files, les missions et l'interface restent
à raccorder. Aucun envoi réel, commit ni push n'a été réalisé.

### Pause et reprise économiques des vacances — 7 novembre 2026

Le service interne `VacationEconomyService` prépare une activation transactionnelle
en verrouillant la participation et l'univers. Les opérations arrivées à terme
avant l'entrée en vacances sont terminées ; les ressources acquises à cet instant
sont matérialisées avant l'enregistrement de la période.

Pendant la période, production et consommations industrielles sont nulles. Les
stocks et les coûts déjà payés restent conservés, dans les limites de stockage
habituelles. Les événements de production sont appliqués à leur date réelle,
uniquement pendant les intervalles hors vacances.

Les têtes des trois files sont reportées de 90 jours par décalage de leur date
de départ. Durée de travail, quantité, coût payé et dépendances sont conservés.
Leurs jetons changent et de nouveaux messages sont programmés ; les anciens
messages devenus obsolètes sont ignorés. Une sortie volontaire après 48 heures
retire uniquement la partie inutilisée du report, sans redémarrer le travail.
Une seconde demande de sortie ne décale pas une nouvelle fois les files.

L'expiration à 90 jours ne nécessite pas de reconnexion : le calendrier reprend
la production après la date prévue et les échéances des files incluent déjà le
report. Un traitement tardif termine chaque commande à sa véritable date de fin,
avec les anciens niveaux de production jusqu'à cette date. Les ajouts, annulations
et accélérations de files sont interdits pendant les vacances.

Le service refuse l'entrée en présence d'attaque/conquête entrante, d'un combat
en attente sur une colonie ou d'une flotte du joueur encore déployée, quelle que
soit sa mission (retour et attente d'orbite compris). Les contrôles de départ et
d'arrivée sont raccordés dans le sous-lot militaire décrit ci-dessous.

L'activation interne exige `FORGOTTEN_EMPIRES_ENABLED=1`, toujours désactivé sur
la base locale. Une période déjà enregistrée reste respectée et peut être
terminée même si de nouvelles activations sont désactivées. Aucun endpoint,
activation réelle, migration supplémentaire ni envoi de mail dans ce sous-lot.

### Compteur et avertissements suspendus pendant les vacances — 7 novembre 2026

La date de dernière visite reste conservée pendant les vacances, y compris lors
d'une consultation HTML authentifiée. Une visite après la fin de la période
redevient une activité normale. Une activité historiquement inconnue n'est pas
initialisée par une simple consultation pendant les vacances.

Les durées hors vacances déterminent les seuils J14, J27 et J30. Les échéances
sont calculées sur les périodes effectivement enregistrées, avec leur fin réelle
ou prévue. Une période commençant après un seuil déjà atteint ne réécrit pas
l'ancienne date de ce seuil. Le statut d'activité vaut `vacation` pendant une
période active et aucune conversion n'y est déclarée exigible. Le service de
démarrage refuse désormais une entrée en vacances lorsque l'échéance J30 est
déjà atteinte ; les vacances ne permettent pas de récupérer un empire perdu.

Le traitement des courriels exclut les vacances actives et déduit aussi les
périodes terminées. La sélection et l'exclusion des avertissements déjà envoyés
sont faites avant la limite du lot, afin qu'un empire en pause ne bloque pas les
autres. Le calcul SQL utilise les fonctions temporelles de MariaDB, backend du
projet ; il additionne les périodes non chevauchantes garanties par le service
d'activation sous verrou.

Le journal reste attaché à la véritable dernière visite, sans date artificielle
de reprise : un J14 déjà envoyé ne se répète pas à la fin des vacances. J27 est
envoyé uniquement lorsque ce nouveau seuil d'inactivité effective est atteint.
La date limite insérée dans le texte validé suit l'échéance J30 ajustée. La
condition d'inactivité est revérifiée sous verrou avant envoi ; une entrée en
vacances survenue depuis la sélection annule cet envoi.

Aucune modification de schéma, activation réelle ni envoi de courriel dans ce
sous-lot. L'interface reste à raccorder avant
ouverture du mode aux joueurs. La conversion PNJ à J30 n'est pas implémentée ici.

### Missions et protection des vacances — 8 novembre 2026

Décisions explicites complémentaires : attendre le retour de toutes les flottes
du joueur avant activation, conserver jusqu'à leur terme les défenses/orbites
alliées déjà acceptées et renvoyer les demandes d'orbite en attente à l'entrée.

Le devis et le départ des huit types de mission sont bloqués depuis un empire
en vacances. Attaques, espionnage, défenses, orbites et transports ne peuvent
pas cibler un empire en vacances. Les contrôles sont revérifiés à l'arrivée ;
une mission militaire devenue interdite repart avec ses vaisseaux, sans combat,
renseignement ni récompense de mission. Les sondes ne sont pas consommées dans
ce cas. Les retours déjà engagés restent traités normalement pour préserver
les vaisseaux et cargaisons, même en présence d'un état ancien incohérent.

La protection commune indique « Mode vacances » et sa date de fin effective,
indépendamment du score. Elle disparaît à l'échéance, sans prolonger les autres
protections. Les accélérations de flotte sont interdites pendant les vacances,
avant toute consommation d'un boost.

Le refus automatique des demandes d'orbite a lieu dans la transaction d'entrée
en vacances. Leur propriétaire reçoit un retour programmé, avec le carburant
de stationnement inutilisé libéré. Les acceptations manuelles sont sérialisées
avec l'activation pour éviter un nouveau stationnement pendant la transition.
Les flottes alliées déjà stationnées conservent leur durée et repartent à son terme.

Un transport lancé avant l'entrée de son destinataire en vacances est marqué
refusé à l'arrivée et sa cargaison revient sans livraison à la colonie protégée.
Les capacités de stockage et pertes de débordement habituelles restent appliquées
au retour : aucune nouvelle règle d'équilibrage n'est ajoutée.

Aucun changement de schéma ni activation réelle. Les préférences et leurs
contrôles d'accès, CSRF et validations d'interface restent à livrer avant ouverture.

### Préférences et files en pause — 8 novembre 2026

L'interface est désormais raccordée : activation confirmée dans l'univers courant,
sortie après 48 heures, raisons des blocages et date de prochaine activation.
Les routes POST vérifient l'accès à l'univers et le jeton CSRF ; le service conserve
ses validations transactionnelles. Les files montrent une progression figée et
les actions de construction, recherche et annulation sont retirées pendant la pause.
Le drapeau `FORGOTTEN_EMPIRES_ENABLED=0` masque les commandes et interdit toute
nouvelle activation ; la sortie d'une période enregistrée reste possible.
Aucune activation réelle ni modification des règles d'équilibrage dans ce lot.

### Vie des PNJ


- Les PNJ compatibles produisent des ressources dans la limite du stockage et
  peuvent reconstruire des défenses détruites, avec un coût en ressources, un délai
  et un plafond. La reconstruction ne fait pas réapparaître gratuitement des forces
  pendant un combat. Les paramètres de reconstruction restent à arbitrer.
- Les empires oubliés défendent leurs territoires sans lancer d'attaques, de
  nouvelles colonisations ou de nouvelles recherches. Les autres catégories de PNJ
  conservent leurs comportements particuliers.
- La disparition automatique des colonies PNJ anciennes est hors de ce premier
  périmètre : transformer ou conquérir un empire ne libère pas automatiquement
  d'emplacements dans la galaxie.

### Conquête JcE et occupation de 24 heures

- Réutiliser la mission existante, renommée « Conquête JcE », pour les planètes PNJ
  actuelles et les colonies d'empires oubliés. Les empires de joueurs encore actifs
  ou simplement inactifs restent exclus de la conquête.
- Une victoire avec un vaisseau de colonisation survivant démarre une occupation
  de 24 heures. La planète n'appartient pas encore à l'occupant.
- Complément validé le 8 novembre 2026 : les 24 heures sont fixes et ne peuvent
  pas être raccourcies par un boost. Le carburant des 24 heures de stationnement
  est prévu au départ ; la part inutilisée est restituée si l'occupation cesse
  avant son terme.
- Complément validé le 8 novembre 2026 : un combat ouvert à l'échéance retarde
  la capture jusqu'à son résultat. Un occupant victorieux conserve son temps
  acquis ; un nouveau conquérant repart pour 24 heures.
- Une place de colonie est réservée pendant l'occupation, dans le respect des
  prérequis et plafonds actuels. Elle est libérée si l'occupation échoue.
- Le vaisseau de colonisation reste sur place avec la flotte d'occupation ; il est
  consommé à la capture définitive. L'occupant peut renforcer sa défense. Retirer
  toutes ses forces ou perdre le dernier vaisseau de colonisation fait échouer
  l'occupation.
- Un concurrent peut attaquer l'occupant. Pour lancer sa propre occupation, il
  doit gagner et disposer lui aussi d'un vaisseau de colonisation. Chaque nouvel
  occupant recommence un délai complet de 24 heures.
- Pendant l'occupation, la planète continue de produire, dans la limite du
  stockage, et reste pillable selon les règles habituelles. Les stocks restent
  sur place ; l'occupant ne peut ni construire ni transférer ces stocks avant la
  capture définitive.
- Les recherches et déblocages permanents de l'ancien propriétaire ne sont pas
  transmis au conquérant. Ses propres technologies s'appliquent après capture.
  Les bâtiments hérités restent soumis à ses règles d'utilisation et d'amélioration.
  Complément validé le 8 novembre 2026 : les bâtiments et stocks sont conservés
  sans dégâts supplémentaires de capture. Toute force PNJ hostile encore présente
  ou reconstruite pendant l'occupation doit être vaincue avant le transfert ;
  aucune force ennemie n'est offerte au conquérant. L'occupant qui reste vainqueur
  conserve le temps déjà acquis.

### Socle d'occupation — préparé le 8 novembre 2026

`PlanetOccupation` conserve un état courant unique par planète ; ses changements
sont gardés dans `OccupationEvent`. Chaque combat ne peut produire qu'un seul
événement de démarrage, maintien ou remplacement, grâce à une contrainte unique.
La répétition du combat d'origine ne redémarre pas l'état courant. Le futur
coordinateur devra consulter le journal avant une transition et traiter toute
la capture dans une transaction ; un ancien combat déjà journalisé ne peut pas
réécrire l'occupant en base, même après un remplacement.

`OccupationPolicy` prépare uniquement des transitions en mémoire. Elle fixe
24 heures, conserve la date d'un occupant qui se maintient et repart de zéro
pour son remplaçant. Les échecs (abandon, vaisseau perdu, capacité perdue,
occupant devenu indisponible, planète reprise) libèrent la réservation d'état.
Un combat ouvert suspend la décision de capture. Les planètes ordinaires vides,
les empires de joueurs simplement inactifs et les autres univers sont exclus.

La politique seule ne déplace ni flotte, ne consomme ni carburant ni vaisseau et
ne transfère pas la planète. Le démarrage après victoire est désormais raccordé
par le coordinateur décrit ci-dessous ; la capture physique reste à développer. La capture
instantanée actuelle reste inchangée jusqu'au branchement complet et à son
activation validée. `Version20261108030000` prépare uniquement les deux tables
et leurs contraintes, sans occupation réelle ; elle a été appliquée localement
le 8 novembre 2026 après accord et vérification de la sauvegarde. Ses huit
requêtes ont réussi, le schéma est synchronisé, validation et lint réussissent.
Son retour arrière refuse de supprimer des états ou historiques existants.

### Démarrage d'occupation après victoire — 8 novembre 2026

`OccupationService` est raccordé à la victoire JcE derrière le drapeau désactivé.
Le résolveur vérifie les vaisseaux survivants après pertes et la capacité sous
verrou des conquérants et de la planète. Le journal par combat est consulté avant
création ; une répétition ne crée pas de réservation supplémentaire. La flotte
retenue prend le statut `occupying` et garde son vaisseau de colonisation,
son carburant de stationnement et la planète à son propriétaire actuel.
La conquête d'une colonie oubliée n'embarque pas de butin ; les recherches et
stocks existants ne sont pas transférés. La récompense de capture n'est pas
attribuée au simple début d'occupation.

Les places actives sont déduites des capacités de colonisation au devis et à
l'arrivée, ainsi qu'à la nouvelle victoire de conquête. Une flotte non retenue
rentre sans consommer sa réserve de stationnement inutilisée. Les nouvelles
missions d'occupation prévoient 24 heures de carburant au départ et portent cette
durée dans la mission. Une désactivation pendant le trajet ou le combat renvoie
ces missions, sans les transformer en captures immédiates. Les occupations et
réservations déjà enregistrées restent prises en compte même après désactivation.

Les affichages de flottes et de rapports distinguent le statut d'occupation et
son échéance ; les libellés de mission deviennent « Conquête JcE ». L'occupation
est comptée comme flotte déployée : elle interdit l'entrée en vacances et ne peut
être accélérée comme un trajet. Une conversion J30 annule ses réservations et
renvoie sa flotte en libérant le carburant inutilisé selon le temps effectué.

Ce lot ne déclenche pas de capture à l'échéance et ne permet pas encore renforts
ou affrontements contre l'occupant. Pour empêcher un contournement par l'ancien
résolveur, attaques et conquêtes d'une planète déjà occupée sont temporairement
refusées dans ce mode de développement. Ce n'est pas la règle finale validée :
le drapeau doit rester désactivé jusqu'à intégration des combats de concurrence,
de la capture physique et de leurs traitements d'échéance. Aucune nouvelle
migration n'est nécessaire et aucune occupation réelle n'est créée par ce lot.

### Contrôle des forces avant capture — 8 novembre 2026

`OccupationReadiness` lit les forces présentes avant toute future décision de
transfert : vaisseaux PNJ, défenses positives et flottes ennemies sur place.
Les alliés ne sont pas comptés comme hostiles. L'occupant doit garder ses propres
forces et au moins un vaisseau de colonisation ; la présence de troupes alliées
seules ne suffit pas. Les quantités nulles ne bloquent pas la capture.
Le contrôle des combats ouverts reste prioritaire et les attentes ne modifient
ni l'échéance, ni le jeton, ni les réservations. L'état `hostile_forces` n'est pas
un échec : il impose un nouveau combat avant de pouvoir transférer la planète.

Cette lecture ne transfère pas de planète, ne consomme aucun vaisseau et n'écrit
pas d'historique. Le coordinateur de capture la refait sous verrou, après
traitement des commandes arrivées à échéance. Compléments validés le 8 novembre
2026 : les flottes PNJ encore en retour doivent revenir et être vaincues avant
la capture ; les commandes restant sur la planète sont annulées lors du transfert,
sans transmission ni remboursement au conquérant. Cette exception de capture
complète la conservation des commandes payées à J30. Leurs chaînes dépendantes
sont aussi annulées pour ne pas produire des niveaux devenus impossibles.

### Transfert définitif interne après occupation — 8 novembre 2026

`OccupationCaptureService` vérifie sous verrou l'état courant et son jeton, les
participations concernées, l'univers et la planète. Un ancien message, une capture
déjà terminée ou un univers fermé ne peuvent provoquer un nouveau transfert.
Il traite les commandes échues de l'ancien empire avant de rechercher les forces
restantes : une défense qui vient d'être terminée impose donc un nouveau combat.
Attente du délai, combat ouvert, forces hostiles et flottes ennemies en retour
conservent l'échéance et la réservation. Abandon, perte du vaisseau, capacité perdue
ou occupant indisponible mettent fin à la tentative et renvoient ses survivants,
en libérant le carburant de stationnement inutilisé.

Une capture validée consomme un seul vaisseau de colonisation, conserve les
bâtiments et stocks matérialisés à l'instant du transfert, change le propriétaire
et journalise les commandes annulées sans remboursement. Elle applique les
recherches du conquérant, sans copier celles ni les déblocages de l'ancien empire.
Les bâtiments hérités ne suffisent pas à hériter d'un accès permanent de rang :
les conditions propres au nouveau propriétaire restent vérifiées. La planète
reçoit un nom selon la règle de conquête existante. Les forces survivantes
rentrent comme lors de la conquête actuelle ; une flotte vide après consommation
du dernier vaisseau de colonisation est terminée, sans retour fictif.

La récompense de conquête est déclenchée à la capture, sans doublon et selon
les plafonds existants. Le résumé
du rapport de combat d'origine est alors mis à jour sans ajouter la consommation
du vaisseau aux pertes du combat. Le journal d'occupation conserve la transition,
les commandes annulées et les flottes renvoyées. Toute erreur annule ensemble
propriété, consommation, annulations, récompense et historique.
Le service reste interne et désactivé, sans traitement automatique d'échéance.
Les renforts et combats de concurrence doivent être raccordés avant activation.

### Intégrité et arbitrages à terminer

- Complément validé le 8 novembre 2026 : les commandes déjà payées à J30 vont
  à leur terme, sans nouvelles commandes PNJ ; leurs résultats restent dans
  l'empire oublié. Les recherches sont ensuite figées.
- Complément validé le 8 novembre 2026 : les propres flottes déployées sont
  renvoyées avec leurs vaisseaux et cargaisons, sans nouvelle action offensive.
  Un combat déjà ouvert retarde la conversion jusqu'à sa résolution.
- Complément validé le 8 novembre 2026 : les flottes alliées stationnées sur les
  colonies oubliées sont également renvoyées ; les attaques déjà en route continuent.
  Les comptes verrouillés ou non vérifiés suivent la même échéance lorsque leur
  dernière activité est connue. Les dates inconnues restent exclues.
- Complément validé le 8 novembre 2026 : la conversion met fin aux alliances
  et à l'appartenance à une guilde. Si l'ancien joueur dirige la guilde, le membre
  le plus ancien encore actif reprend sa direction. Sans successeur actif,
  la guilde est archivée en conservant son historique. Ces transitions restent
  à implémenter ; la prévisualisation ne les exécute pas.
- Préserver et traiter explicitement les missions, combats, échanges et files en
  cours lors de la transformation, sans duplication ni disparition accidentelle.
- Prévoir un historique des transformations, occupations et transferts pour les
  vérifications contre les abus. Les limites de fréquence des conquêtes et les
  sanctions ne sont pas chiffrées ni automatisées par cette décision.
- Définir avant activation le point de départ des compteurs des comptes existants,
  sans déduire une ancienne activité d'une simple date de création ou de session.
- Préciser le traitement des combats encore ouverts à l'échéance des 24 heures,
  des occupations simultanées, des boosts de mission et du carburant d'occupation.
  Le report pendant un combat, le temps conservé par l'occupant victorieux,
  l'absence de boost du délai et le carburant prépayé sont désormais validés
  ci-dessus ; le traitement concret reste à raccorder.
- Toute migration ou application aux empires existants demande une validation
  distincte. Seule la migration du suivi d'activité décrite ci-dessus a été
  exécutée localement ; aucun empire n'a été transformé par cette opération.

### Prévisualisation des échéances J30 — 8 novembre 2026

`make empires-preview` est une lecture préparatoire, pas un convertisseur. Elle
déduit les périodes de vacances réelles avant de limiter le lot et exclut les
univers inaccessibles ainsi que les dates d'activité inconnues. Le curseur
`--after-id` permet de parcourir tous les empires à échéance, même lorsqu'un
premier lot comporte des situations à traiter. Les comptes verrouillés ou non
vérifiés sont signalés pour examen, sans décider ici de leur conversion.
La décision complémentaire ci-dessous soumet désormais aussi les comptes verrouillés
ou non vérifiés à J30 ; leur signalement dans le relevé n'est pas une exemption.
Le relevé compte colonies, flottes déployées et accueillies, commandes, recherches,
combats ouverts sur place ou ailleurs, alliances et responsabilités de guilde.
Il ne garantit pas qu'un empire soit immédiatement transformable : les conditions
devront être revérifiées sous verrou par le futur service de conversion.
Les courriels, états des flottes, files et dates d'activité restent inchangés.

### Identité des empires oubliés — 8 novembre 2026

Après comparaison des deux solutions, décision explicite : détacher le joueur
comme propriétaire actuel. `PlayerUniverse.player` devient nullable ;
`formerPlayer` conserve uniquement la provenance historique, sans droit d'accès.
`forgottenAt` marque le détachement. Les colonies, flottes, recherches et historiques
gardent leur inscription d'origine et leurs identifiants, sans transfert au nouvel
empire. La contrainte unique joueur/univers est conservée : le joueur ne peut
posséder qu'un empire actif, tandis que plusieurs anciens empires sans propriétaire
peuvent rester présents. Les accès et actions de l'ancien joueur sont refusés,
même si une ancienne sélection pointe encore sur cet empire.

La méthode de détachement prépare uniquement l'identité ; aucun bouton, commande
ou traitement automatique ne l'exécute sur les vrais empires. Le futur service
de conversion doit d'abord coordonner seuil J30, combats, retours et liens sociaux.
Les commandes déjà payées peuvent encore être terminées par les traitements
automatiques. L'empire oublié ne participe plus au classement des joueurs et ne
peut plus recevoir de nouveaux messages privés ni transports de joueur.
Ses anciens échanges restent affichables à leurs interlocuteurs, sans lui envoyer
de nouveaux messages. La référence historique ne donne pas accès aux conversations
de l'ancien empire depuis une nouvelle inscription.

La migration `Version20261108010000` ajoute les champs historiques et autorise
un propriétaire absent, sans détacher ni modifier les empires existants. Elle est
testée dans la base isolée et a été appliquée localement après accord distinct
et vérification de la sauvegarde le 8 novembre 2026. Trois requêtes ont préparé
la structure, sans conversion. `make validate`, `make lint` (schéma compris)
et `git diff --check` réussissent ; les deux drapeaux restent désactivés.
Le retour arrière est refusé dès qu'un empire oublié ou sans propriétaire existe,
pour empêcher une perte de l'historique.

### Conversion J30 interne — préparée le 8 novembre 2026

`EmpireConversionService` revalide l'échéance effective sous verrou de l'empire et
de l'univers. Un combat ouvert sur ses colonies ou impliquant ses flottes ailleurs
reporte toute la conversion. Une activité modifiée avant la prise du verrou est
rechargée et empêche une conversion prématurée. Lorsque le cycle sera activé,
une visite à partir de J30 ne remettra plus le compteur à zéro et les accès seront
refusés pendant l'attente de conversion. Avant J30, le retour reste normal.

Les flottes propres sorties et les alliées stationnées rentrent avec leurs forces
et cargaisons. Les retours déjà engagés et les attaques étrangères en route sont
conservés. Les anciens jetons des missions rappelées sont invalidés ; la durée de
retour réutilise le trajet connu et le carburant de stationnement inutilisé est
libéré (consommation au prorata pour l'orbite déjà effectuée). Les transports
rappelés ou arrivant chez un empire converti sont refusés sans livraison.
Les colonies, recherches et véritables forces restent attachées à l'ancien empire,
sans garnison PNJ standard. Les commandes payées peuvent toujours se terminer.
Le pillage d'un empire effectivement converti relève du JcE, sans protection ou
limite de score de joueur ; les joueurs actifs et simplement inactifs gardent les
contrôles de combat existants. La conquête avec occupation reste à raccorder.

Les alliances prennent le statut historique `ended`. La succession d'une guilde
utilise l'ordre des adhésions (identifiants croissants) parmi les membres encore
joueurs, non verrouillés et à moins de 14 jours d'inactivité effective. Les vacances
comptent selon leur temps réellement suspendu. Sans successeur, la guilde est
archivée, ses relations neutralisées et ses adhésions libérées ; ses messages,
son ancien commandement et son nom restent conservés. La composition antérieure
est gardée dans le journal, permettant aux anciens membres de rejoindre une
autre guilde sans perdre cette trace. Les guildes archivées ne sont plus proposées
au recrutement ni au classement. Leurs noms restent réservés.

Un seul journal `EmpireConversion` est possible par empire. Il garde la dernière
activité, l'échéance, la date de traitement et les effets (inventaire avant,
flottes rappelées, alliances et guildes). Toute erreur annule la transaction,
y compris rappels et changements sociaux. La migration additive
`Version20261108020000` prépare ce journal et la date d'archivage des guildes ;
son retour arrière refuse d'effacer des conversions ou archives existantes.
Elle a été appliquée localement après accord explicite et vérification de la
sauvegarde le 8 novembre 2026 : trois requêtes, aucun empire converti ni guilde
archivée. Le schéma est synchronisé ; validation et lint réussissent.
Le service est désactivé, sans commande
d'application ni planification automatique ; aucun empire réel n'a été converti.

### Application locale du socle d'activité — 7 novembre 2026

Après l'accord explicite de l'utilisateur, la sauvegarde locale
`genesis-genesis_reborn_v2-20261107T212518Z-0ffS00FEGKab.sql.gz` a été créée dans
`var/backups`, avec intégrité gzip et manifeste SHA-256 vérifiés. Cette vérification
ne constitue pas un essai de restauration.

Le contrôle des versions indiquait une seule migration en attente :
`Version20261107180000`. Son application a exécuté une seule requête, ajoutant
la colonne nullable `player_universe.last_activity_at`. `make validate` puis
`make lint` réussissent ; le schéma actif est synchronisé avec le mapping.
Les 260 tests PHP et 13 tests Python avaient déjà réussi sur ce code avant
l'application, dans la base isolée ; ils n'ont pas été relancés sans changement
applicatif. Aucun commit, push ou déploiement n'est réalisé par cette opération.

### Suspension des protections à J14 — préparée le 7 novembre 2026

Le contrôle des attaques et l'état de protection utilisé par le classement et
l'interface utilisent le même service. Lorsque le cycle sera activé, une activité
connue datant d'au moins 14 jours suspendra les protections débutant et après
pertes. Une activité inconnue ne déclenche aucune suspension.

Pendant l'inactivité, de lourdes pertes n'accordent pas de nouvelle protection
de récupération et ne consomment pas son délai entre activations. Les dates
existantes ne sont pas effacées ni prolongées : une visite avant J30 rétablit les
protections encore valables, sans renouveler une protection expirée.

Alliances, interdiction d'attaquer ses propres colonies, séparation des univers,
limite d'écart de score et part de stockage protégée restent applicables. Une
colonie de joueur inactif reste exclue de la conquête JcE jusqu'à sa future
transformation PNJ.

`FORGOTTEN_EMPIRES_ENABLED` vaut `0` par défaut. Le lot est testé avec activation
limitée aux instances des tests ; aucun changement de la configuration privée
ni aucune migration supplémentaire n'est nécessaire. L'activation réelle attend
la livraison du cycle complet, la disponibilité des avertissements et un accord
distinct. La perte d'accès à J30 n'est pas implémentée par ce lot.

### Application locale du journal d'avertissements — 7 novembre 2026

Après accord explicite distinct de la validation des textes, une sauvegarde
locale a été créée et vérifiée :
`var/backups/genesis-genesis_reborn_v2-20261107T214301Z-IsWwVDp84gF3.sql.gz`
(intégrité gzip et manifeste SHA-256 ; aucun essai de restauration effectué).

Le contrôle des versions indiquait une seule migration en attente,
`Version20261107220000`. Son application a exécuté trois requêtes : création du
journal `empire_inactivity_warning`, clé étrangère vers la participation et
index de dernière activité. `make validate` et `make lint` réussissent ; le
schéma actif est synchronisé.

La commande `make empires-warn` a été exécutée en prévisualisation : zéro
éligible, aucun envoi et aucune écriture du journal. La valeur traitée de
`EMPIRE_INACTIVITY_WARNINGS_ENABLED` est `false` dans la console locale. Aucun
job d'envoi n'est ajouté ; cette opération n'active ni courriels ni transformation
PNJ. Le travail parallèle sur les modèles de courriels et sa branche courante
`codex/design-emails` ont été préservés. Aucun commit ni push dans cette opération.

## Transports entre joueurs — décision validée le 7 novembre 2026

Les transports peuvent viser une colonie connue du même univers et de la même galaxie, appartenant au joueur ou à un destinataire qui les autorise. Le réglage de compte accepte tout le monde, les alliés uniquement (défaut) ou personne ; les propres colonies restent autorisées. Les alliances suivent les relations existantes (guilde ou contrat accepté, hors relation ennemie).

L’expéditeur fournit ressources, vaisseaux et carburant aller-retour. L’autorisation est contrôlée au départ et à l’arrivée. Un changement de propriétaire ou un refus à l’arrivée entraîne le retour de la cargaison. La livraison respecte chaque capacité de stockage ; le surplus revient, y compris entre les propres colonies. Au retour, le dépôt reste soumis au stockage de la colonie de départ : un éventuel dépassement y est perdu et indiqué dans le rapport.

Les dons entre joueurs ne donnent aucun boost et ne font progresser aucun objectif. La protection débutant reste inchangée. Aucun plafond de dons, taxe ou sanction automatique sur une adresse IP n’est ajouté. Les deux participants disposent du rapport économique ; l’administration dispose de l’historique paginé des transports pour une vérification humaine.

### Renforts et contestation des occupations — décisions du 8 novembre 2026

Décisions explicites : les renforts de l'occupant et de ses alliés sont autorisés,
sans recommencer les 24 heures. Les joueurs sans alliance ne peuvent pas défendre
l'occupation. Le devis réserve le temps restant jusqu'à l'échéance, avec au minimum
la fenêtre de combat existante ; le carburant de stationnement non consommé revient
avec la flotte. Le renfort vise le titulaire présent à son départ : un changement
de titulaire ou une occupation terminée provoque son retour.

Uniquement sur le monde JcE occupé, la protection débutant et la limite d'écart de
score ne bloquent pas une contestation. Les alliances et l'interdiction des vacances
avec une flotte déployée restent applicables. Les autres colonies du joueur gardent
leurs contrôles habituels. Chaque camp conserve ses recherches : les forces PNJ ne
sont pas alliées de l'occupant contre ses rivaux.

Le titulaire victorieux conserve son échéance. Un rival doit gagner avec une mission
Conquête JcE, un vaisseau de colonisation survivant et une place disponible pour
reprendre l'occupation et commencer ses propres 24 heures. Une attaque simple peut
mettre fin à l'occupation, sans acquérir la planète. Le vaisseau d'un allié ne remplace
pas le vaisseau de colonisation requis de l'occupant. La limite de rounds existante
conserve le retrait des attaquants ; les forces PNJ restantes bloquent la capture.
Les renforts alliés repartent aussi en fin d'occupation ou à la conversion J30 du
titulaire, avec leurs survivants et cargaisons.

Le raccordement reste derrière le drapeau désactivé. La migration
`Version20261108040000` ajoute uniquement le suivi des renforts ; son application
locale demande un accord distinct. Aucun courriel n'est modifié ni envoyé par ce lot.
### Application du suivi des renforts et échéances différées — 8 novembre 2026

L'utilisateur a autorisé l'application locale de `Version20261108040000`. La
sauvegarde préalable a été revérifiée (gzip et SHA-256), la version en attente
contrôlée, puis les trois requêtes appliquées. Le schéma est synchronisé ; aucune
occupation, flotte de renfort ou conversion réelle n'a été créée.

Le contrôle des captures est maintenant raccordé au relais existant par un message
`OccupationDue`, programmé au début de l'occupation et à nouveau après une victoire
conservant le titulaire. L'échéance de jeu reste inchangée. Un message prématuré
reprogramme cette même échéance ; un message périmé ou répété ne permet pas une
deuxième capture. Les combats ouverts, forces hostiles et retours PNJ empêchent
la capture ; une nouvelle victoire relance son examen. Les échecs techniques suivent
les reprises du transport existant. Aucun sondage permanent n'est ajouté pendant
l'attente de forces à vaincre.

La commande `app:occupations:resume`, accessible par `make occupations-resume`,
prévisualise par défaut les occupations actives, sans écriture. Le mode explicite
`--apply` remet leurs contrôles dans le relais et reste refusé si le drapeau est
à zéro. Les univers inaccessibles sont ignorés. Les lots incluent les occupations
futures comme échues et disposent d'un curseur pour pouvoir reprendre après une
interruption. Ce raccordement ne change aucune règle ni valeur d'équilibrage, ne
planifie pas les conversions J30 et n'active ni système ni courriel.
### Traitement J30 par lots — raccordement du 8 novembre 2026

Le service de conversion sous verrou est accessible par une commande dédiée,
`app:empires:convert-forgotten` (`make empires-convert`). Le comportement par défaut
reste une prévisualisation sans écriture ni envoi. L'application exige `--apply`
et le drapeau `FORGOTTEN_EMPIRES_ENABLED` activé ; la commande n'active pas ce drapeau.
La sélection reprend les échéances effectives existantes, après déduction des
vacances et exclusion des dates inconnues et univers inaccessibles. Les comptes
verrouillés/non vérifiés restent soumis à la règle validée, sans exemption ajoutée.

Chaque empire est revérifié sous verrou dans une transaction distincte. Les combats
ouverts reportent la conversion ; un passage `--all` continue les lots suivants,
puis le prochain passage sans curseur réexamine les reports. Une erreur technique
arrête le lot avec un code d'échec et un curseur précédant l'empire en erreur ;
les conversions déjà validées ne sont pas annulées. Après correction, une reprise
ne peut pas convertir deux fois le même empire. Les détails techniques sont dans
les journaux, pas dans le tableau destiné à l'opérateur.

Ce raccordement ne change aucune règle d'équilibrage, n'installe aucune tâche
périodique et ne déclenche aucune conversion réelle dans cette session. La future
planification et l'activation restent distinctes des tests de la commande.
### Cartes et parcours complet des empires oubliés — 8 novembre 2026

La vérification du parcours a relevé un écart d'interface : les liens de conquête
des cartes visaient uniquement les planètes PNJ classiques, alors que le service
accepte aussi les empires oubliés. Les deux cartes utilisent désormais les contrôles
existants pour proposer la conquête de ces mondes, les renforts du titulaire/alliés
et les contestations par les rivaux. L'échéance UTC et le caractère non définitif
de l'occupation sont affichés. Un allié ne reçoit pas de lien de conquête ou d'attaque
contre le titulaire ; s'il reste des forces hostiles, il peut préparer leur combat.
Les liens tiennent compte des places de colonie réelles et des réservations en cours,
sans modifier leurs limites ni les règles de déblocage.

Ces corrections ne changent aucune règle ni aucun texte de courriel. Le parcours
complet est vérifié dans la base de tests, avec une horloge et un transport de
courriels simulés. Aucun empire réel n'est converti et aucun envoi SMTP réel n'est
réalisé dans ce lot.
### Revue finale des occupations — 8 novembre 2026

Corrections de cohérence avec les règles déjà validées : une victoire du camp PNJ
n'attribue pas de récompense au renfort humain vaincu ; le verdict du rapport tient
compte du camp réel et distingue une position maintenue à la limite des rounds.
Les rapports d'occupation consultent leur journal de fin/remplacement pour afficher
l'état actuel de cette occupation sans altérer l'instantané historique de bataille.
Une capture ultérieure par un rival ne fait pas apparaître l'ancien titulaire
comme conquérant. Les données et fonctionnalités des autres rapports sont conservées.

Une désactivation pendant un combat d'occupation ne doit pas bloquer sa résolution :
le combat se termine, mais aucune nouvelle occupation n'est créée par la victoire
d'un rival tant que le drapeau est à zéro. Il ne s'agit pas d'une modification des
règles de combat ou d'une nouvelle valeur d'équilibrage.

La procédure de mise en service est préparée dans EXPLOITATION.md. La cadence de
cinq minutes y est une proposition d'exploitation, pas une planification installée
ni une décision d'activation. Les contenus de courriel restent inchangés et validés ;
leur réception SMTP réelle, l'activation et l'installation des passages périodiques
nécessitent encore les accords correspondants.
### Passage quotidien à minuit à Paris — décision validée le 8 novembre 2026

L'utilisateur remplace la proposition de cinq minutes par une vérification quotidienne
à 00 h, heure de Paris, et valide l'alignement de la perte d'accès sur la conversion
effective. Les avertissements J14/J27 et les conversions utilisent ce passage ; les
occupations conservent leurs 24 heures exactes et leur traitement Messenger distinct.

L'échéance après 30 jours effectifs, vacances déduites, est arrondie au premier
minuit `Europe/Paris` qui lui est supérieur ou égal. Elle reste stockée en UTC et
suit le calendrier civil, y compris les journées de 23 ou 25 heures. La sélection
SQL applique le dernier minuit parisien avant LIMIT, pour qu'un empire dont le
seuil brut tombe dans la journée ne masque pas un autre empire déjà éligible.
Les univers et vacances actuellement inaccessibles restent exclus au moment du
traitement, puis la conversion revalide sous verrou.

Le passage du seuil ou de minuit n'interdit plus à lui seul le contrôle d'un empire
encore humain. Un retard de traitement ou un combat reporté laisse l'accès jusqu'au
détachement effectif ; une visite légitime avant ce détachement réinitialise la
période d'inactivité. La visite et la conversion sont sérialisées par les verrous.
La sélection d'univers, les actions, les vacances et les occupations suivent cette
même règle ; un empire réellement converti reste inaccessible à son ancien joueur.

Le passage quotidien des avertissements utilise `--send --all` pour traiter tous
les lots, avec déduplication et arrêt sur échec de transport ou absence de progression.
Les seuils de protections J14 et les autres règles de jeu ne changent pas.
Deux formulations des courriels sont adaptées : l'empire « pourra être transformé »
et le nouveau départ devient nécessaire « après cette transformation ». Les modèles
révisés doivent être présentés à l'utilisateur avant activation ; aucun envoi réel
n'est effectué pendant ce lot.

### Mise en service locale — accord du 8 novembre 2026

L'utilisateur valide les deux courriels révisés et l'activation locale du cycle
complet, avec avertissements et conversions à 00 h Europe/Paris. Les deux drapeaux
sont activés dans la configuration privée locale et le passage quotidien est
installé dans la crontab de `caim`. Les occupations gardent leur échéance de 24 h
indépendante. Les participations sans activité connue restent exclues, sans
initialisation inventée. Aucun envoi ou conversion manuel ni déploiement distant.
Les descriptions précédentes de drapeaux désactivés sont des états historiques.

## Refonte de l’administration et boîtes de ressources — choix du 8 novembre 2026

Évolution validée à développer ; ces décisions ne signifient pas que les nouveaux
écrans ou objets sont déjà implémentés. Le périmètre détaillé et les propositions
encore en attente sont dans [le cadrage de l’administration](REFONTE_ADMINISTRATION.md).

- Accueil synthétique et rubriques séparées : Surveillance, Joueurs, Univers,
  Événements, Modération et Exploitation.
- Surveillance détaillée des missions avec fiches joueurs, alertes à examiner,
  notes et suivi des dossiers ; les transports sont regroupés dans ce suivi.
- Alertes techniques et activités inhabituelles, notamment missions en retard,
  transferts ou attaques répétés et sécurité ; ce sont des indications à examiner.
- Création guidée de bonus temporaires, annonces et défis collectifs avec suivi
  des contributions individuelles. Les mécanismes existants restent conservés.
- Récompenses demandées : boosts et boîtes de ressources. Les distinctions de
  profil proposées ne sont pas encore validées.
- Boîtes petite, moyenne et grande : assortiment annoncé, montants fixes
  plafonnés par univers, stockage dans l’inventaire et ouverture manuelle.
- Le joueur choisit une de ses colonies du même univers ; si son stockage ne
  suffit pas pour tout le contenu, l’ouverture est refusée et la boîte reste intacte.
- Inventaire commun de 100 objets disponibles, boosts et boîtes confondus,
  par participation à l’univers ; récompenses supplémentaires conservées en attente.

Les quantités initiales sont validées : petite = 4 000 métal, 1 000 nano,
1 000 hydrogène, 200 antimatière et 2 000 électricité ; moyenne = ces montants
×2,5 ; grande = ×5. Elles restent réglables et plafonnées par univers.
Chaque participant éligible reçoit au maximum un objet par événement : un boost
ou une boîte, sans cumul des deux. Ces règles concernent le futur système et
ne décrivent pas encore des récompenses distribuées par le code actuel.

Les alertes de surveillance sont des indices à examiner, avec ces valeurs
initiales réglables par univers : retard de mission supérieur à 5 minutes,
5 transports entre deux joueurs distincts en 24 h, ou 10 attaques contre un même
joueur en 24 h, PNJ exclus. L’ouverture d’une boîte est bloquée pendant le mode
vacances et la boîte est conservée. Le plafond des boosts issus des missions
à risque n’est pas modifié par l’ajout des boîtes aux récompenses d’événements.

### Présence et interventions administratives — choix validés le 8 novembre 2026

À développer dans la refonte, sans annonce d’activation à ce stade :

- Présence estimée si activité depuis moins de cinq minutes, avec date de dernière activité.
- Temps de jeu estimé par univers : aujourd’hui, sept derniers jours et total depuis le début du suivi ; suspension après cinq minutes sans activité, sans historique antérieur inventé.
- Ajout ou retrait de ressources sur une colonie choisie du joueur, dans les capacités de stockage et sans solde négatif ; motif obligatoire.
- Ajustement des échéances de flottes et rappel quand leur état permet un retour ; pas de modification de la composition ou cargaison en vol dans ce périmètre.
- Confirmation et journal des interventions : administrateur, motif, valeurs avant/après ; respect des verrous et des tâches planifiées.
### 8 novembre 2026 — Présentation de l’arbre technologique

Décision utilisateur : organiser l’arbre en sections par domaines (économie,
recherche, flotte, défense), avec de vraies branches de prérequis et les déblocages
à droite. Tous les éléments restent consultables. La sélection éclaire le chemin
complet entre domaines et les déblocages directs ; les autres cartes restent
présentes. Les liens entre domaines sont affichés pour le parcours sélectionné.
Le panneau de détail, latéral sur ordinateur et plein écran sur mobile, indique
les niveaux réels requis, les accès acquis et la prochaine étape conseillée.
Un objectif peut être mémorisé localement dans le navigateur, séparément pour
chaque participation et colonie. Les rangs gardent leurs règles indépendantes,
les accès permanents restent acquis et aucune valeur de gameplay ne change.
Les recherches et filtres conservent les prérequis directs et indirects associés.


### 8 novembre 2026 — Lisibilité des parcours technologiques

Après vérification d’utilisation, décision utilisateur : conserver les filtres
à la sélection et ajouter seulement les cartes nécessaires au parcours consulté.
La sélection affiche les prérequis en priorité ; un bouton affiche séparément
les déblocages directs. Les flèches indiquent le sens des dépendances et les
conditions remplies, manquantes ou historiques restent distinguées par du texte
et des tracés. Les anciens prérequis d’un accès acquis ne sont pas à refaire.
Des accès rapides aux domaines et un recentrage facilitent la navigation. Sur mobile, fermer les détails conserve le parcours sélectionné.
Le détail conserve un seul parcours de prérequis et regroupe les déblocages dans
des sections repliables par domaine. Les règles et niveaux requis sont conservés.

### 8 novembre 2026 — Guide technologique, un domaine à la fois

L’utilisateur juge encore le graphe brouillon et illisible, puis choisit
explicitement l’aperçu « Un domaine à la fois ». Cette décision remplace les
présentations graphiques précédentes : plus de lignes ni de zone de graphe,
quatre boutons de domaines, six cartes initiales et les suivantes à la demande,
avec rang basique par défaut. La recherche parcourt tous les domaines ;
les filtres par rang et état restent conservés à la sélection. Les prérequis
ne sont plus ajoutés comme cartes dans les résultats : ils restent consultables
dans la fiche, avec leurs étapes précédentes repliables et les déblocages
regroupés par domaine. L’objectif local et la prochaine étape sont conservés,
avec un panneau latéral sur ordinateur et une fiche plein écran sur mobile.
Les règles de déblocage et les accès permanents ne changent pas.


## Production et consommation des usines — décision du 9 novembre 2026

Après 648 simulations déterministes aux niveaux 20/40/80, l’utilisateur confirme les valeurs proposées et le rythme de construction de 84,16 jours cumulés à ×1 pour cinq bâtiments chacun jusqu’à 80, contre 88,16 précédemment, hors attente de ressources, prérequis, stockages et recherches. La branche demandée est créée depuis dev : `codex/equilibrage-production-basique`. Aucune fusion ni publication n’est demandée.

Production Basique par niveau et par heure (×1, position 5, sans passif) : M360, N180, H720, A90, centrale E1080. Électricité : 180/h par extracteur. Réacteur : 4 H/A. Prix niveau 1 : producteurs directs 480 M + 160 N ; réacteur 360 M + 120 N ; centrale 1 440 M + 480 N. Durées initiales : 60 s pour les producteurs directs, 120 s pour réacteur et centrale. Courbes, bonus de position, recherches, stockages et règles de pénurie sont conservés.

Les facteurs existants de production/consommation 1/1,5/2,25 et de durée 1/1,25/1,5625 restent appliqués. Les nouveaux budgets des deux bâtiments moins chers sont déclinés mécaniquement avec les facteurs de coût existants : réacteur 1 200/1 500/1 875, centrale 4 800/6 000/7 500 ; recettes par rang inchangées en parts économiques. Cela ne constitue pas une étude de progression complète des rangs I/S. Les files conservent leurs prix payés et leurs durées enregistrées, les surcharges d’univers restent prioritaires et aucun changement de base ni déploiement n’est effectué. Le [bilan et ses limites](EQUILIBRAGE.md#production-des-batiments) et les nouvelles fixtures distinguent le candidat livré des anciennes références.


### Publication de l’équilibrage sur dev — 9 novembre 2026

L’utilisateur demande explicitement le commit et le push sur dev. Le lot est préparé sur le dernier dev intégré (1b02f4f), en conservant ses nouveaux travaux d’administration et d’événements, sans inclure les autres documents ou captures locaux. Les [résultats de publication](EQUILIBRAGE.md#publication-sur-dev--9-novembre-2026) distinguent suite PHP complète et contrôles navigateur consolidés après correction d’une ancienne attente de prix. Aucune publication sur Alpha ni déploiement demandé.

## Suppressions et visibilité des événements — 9 novembre 2026

Décisions validées par l’utilisateur : conserver le texte « Événements » et le
compteur lorsque l’encart est fermé, sur PC et mobile. Les événements et défis
déjà démarrés sont retirés des listes courantes et conservés dans l’historique ;
les contributions et récompenses acquises ne sont pas supprimées. Un retrait
en cours arrête les effets futurs et conserve les effets déjà acquis. Seuls les
éléments jamais démarrés, sans contribution ni récompense, sont supprimés
réellement ; les rotations automatiques restent en lecture seule.

La suppression volontaire du compte concerne tous les univers : confirmation
explicite et mot de passe actuel, conversion des empires en empires oubliés,
effacement des identifiants et préférences, révocation des jetons de confirmation
et de réinitialisation, fermeture de l’accès. Une ligne technique anonymisée est
conservée pour les références historiques ; ce mécanisme ne purge pas les
messages, journaux, feedbacks ou sauvegardes. La politique de confidentialité
précise le canal des demandes complémentaires. Un combat en cours bloque la
suppression complète sans modification partielle ; le dernier administrateur
actif doit désigner un successeur. Les vacances prennent fin à la suppression.
La conversion volontaire ne dépend ni des trente jours d’inactivité, ni du
drapeau des conversions automatiques. Dans un univers fermé, les files restent
figées ; l’identité est détachée. Aucun changement de schéma n’est nécessaire.

## Administration des rotations et panneau joueur — 9 novembre 2026

À la demande explicite de l’utilisateur, la restriction de lecture seule des
rotations hebdomadaires est remplacée par la gestion de modèles par univers :
création, activation et désactivation. Les trois modèles historiques et leur
séquence restent les valeurs par défaut. Les modèles actifs alternent dans leur
ordre enregistré, selon l’indice de semaine existant. Chaque univers conserve
au plus une occurrence hebdomadaire par semaine, indépendamment du modèle.
Les changements de modèles concernent uniquement les semaines non encore
programmées ; une occurrence existante garde un instantané de ses règles.

Une annulation explicite conserve l’enregistrement, sa clé de semaine, les
contributions et récompenses acquises. Elle arrête les nouvelles contributions
et attributions de cette occurrence, sans annuler les autres semaines. Une
rotation terminée reste consultable ; une occurrence échue attend sa clôture
pour préserver les récompenses dues. Le lundi à 00 h UTC, le plafond simultané
et les modalités historiques de récompense restent inchangés. Les réglages
utilisent `Universe.gameSettings`, sans migration.

Les joueurs voient aussi les rotations de leur univers dans Événements et dans
le panneau sous les ressources : objectif, contribution, progression, place
lorsqu’un classement existe, récompense et durée. Les événements globaux
applicables à cet univers restent inclus. Le nom de l’univers n’est plus répété
dans le panneau. L’ouverture superpose le contenu sans agrandir la barre.
Le bloc `planet-index-summary panel` est retiré à la demande de l’utilisateur.

Les filtres des quatre catalogues sont alignés sur les rôles et effets actuels.
Les vaisseaux de combat avec soute restent dans leur classe de combat ; les
rôles transport, colonisation et espionnage sont distincts. Réservoirs, soutes
et propulsion relèvent de la logistique ; extension coloniale et réparation
des défenses possèdent leur filtre. Aucun coût, prérequis ou effet n’est modifié.


### Contrôles du lot avant publication sur dev — 9 novembre 2026

Publication demandée explicitement par l’utilisateur. Copie isolée issue de
`dev` à `621cd11`, incluant le correctif Quality d’Alpha ; changements parallèles
de navigation et d’envoi des mails exclus de ce lot.

- `make validate`, `make lint`, `npm run build` via `make assets` et
  `git diff --check` : réussis.
- `make test` : exécution complète finale réussie, 545 tests PHP et
  209104 assertions, puis 13 tests Python. Le domaine Mercure du conteneur de
  test est aligné sur celui des requêtes de la suite HTTP.
- `make test-browser` : les 129 scénarios ont été exécutés en quatre lots
  isolés ; 128 succès au premier passage et un succès en reprise ciblée après
  regroupement de la préparation des données d’inventaire. Il s’agit de
  résultats consolidés, pas d’une exécution unique intégralement verte.
- Le test de migration d’activité nettoie sa table temporaire : sa contrainte
  nommée différemment de celle générée par l’ORM bloquait parfois le nettoyage
  du test suivant. Les assertions et les migrations livrées restent inchangées.

Ces contrôles sont locaux ; ils ne constituent ni une validation de la CI
GitHub, ni un déploiement. Les migrations de vérification concernent uniquement
les bases jetables ; aucune migration de la base de jeu existante n’est appliquée.

### Boutons Max et quantités — 10 novembre 2026

L’utilisateur valide des boutons Max pour les vaisseaux et défenses à construire, la composition des flottes et chaque ressource de fret. Le plafond fixe de 100 unités par commande de construction est retiré : le maximum dépend des ressources et des coûts réels, réductions et arrondis compris. Les 30 commandes par file et les contraintes de stockage des entiers et durées sont conservées.

Une quantité excessive est automatiquement réduite au maximum possible, avec un message compréhensible. Le serveur revérifie les limites sous verrou lors de la validation, même sans JavaScript. Une quantité nulle ne crée pas de commande ni de flotte.

La composition respecte les vaisseaux disponibles et le plafond de flotte configuré dans l’univers ; la restriction historique de 100 par type ne doit pas empêcher l’application de cette limite déjà validée. Le fret possède un Max par ressource : les autres ressources déjà saisies sont conservées, l’hydrogène nécessaire au carburant est réservé, et la place restante en soute est respectée. Le plafond de saisie de fret existant de 100 000 000 par ressource est conservé. Si plusieurs valeurs soumises simultanément dépassent la capacité, elles sont ajustées dans l’ordre des champs envoyés ; la prévisualisation montre les quantités effectives avant confirmation.

### File de recherche commune — 10 novembre 2026

L’utilisateur valide une file de recherche commune au joueur dans chaque univers, accessible depuis toutes ses colonies. Un ajout depuis une autre colonie rejoint la même file, y compris pour le niveau suivant d’un rang déjà programmé. Les univers restent indépendants ; la limite de 30 commandes s’applique à cette file commune.

Chaque recherche conserve sa colonie d’origine : elle y prélève ses ressources et utilise son laboratoire pour calculer la durée au moment de l’ajout. Coûts et durées déjà enregistrés ne sont pas recalculés au démarrage. Les recherches s’exécutent dans l’ordre des commandes, une à la fois.

Les commandes historiques payées sont conservées. Si plusieurs recherches étaient déjà actives en parallèle, elles gardent leurs dates et durées ; les commandes en attente et les nouveaux ajouts attendent la fin de toutes ces recherches, puis utilisent la file commune. Aucune annulation ni migration de données n’est imposée pour cette transition.

Les dépendances de niveaux suivent aussi les commandes ajoutées depuis d’autres colonies. Les règles existantes d’annulation, de remboursement, de vacances et de capture sont conservées ; les remboursements vont à la colonie qui a payé chaque commande. L’affichage commun montre les colonies d’origine et les états réellement actifs ou en attente.


## Classements quotidiens — 10 novembre 2026

Tous les classements (points des joueurs, flotte, nombre de colonies et guildes) sont figés entre deux calculs quotidiens, à 00 h en Europe/Paris, en tenant compte des changements d’heure. Les points affichés dans l’aperçu de colonie et l’API utilisent le même instantané. Le calcul des points reste inchangé ; les scores utilisés pour les protections et combats restent en temps réel.

Les évolutions comparent les deux derniers calculs persistés : points actuels moins points précédents ; rang précédent moins rang actuel (une place gagnée est positive). Gains et pertes sont signés ; une valeur inchangée s’affiche « = ». Sans référence précédente (initialisation, nouveau joueur ou nouvelle guilde), l’évolution s’affiche « = ». Les nouveaux inscrits et guildes entrent au prochain calcul. Les noms, relations diplomatiques et protections restent actuels ; les joueurs supprimés ou devenus empires oubliés et les guildes archivées ne sont plus affichés, sans renuméroter les rangs avant l’échéance suivante.

Le relais existant déclenche les calculs même sans visite. Les lectures rattrapent une échéance manquée ; après interruption, le calcul utilise l’état disponible à la reprise et indique son heure réelle, sans inventer les états des jours manqués. Les constructions et recherches déjà terminées sont réconciliées avant le calcul. Les instantanés et leurs dernières évolutions sont conservés en base, avec uniquement des identifiants et valeurs de classement, sans copie des identités.


## Ressources initiales et production passive — décision du 10 novembre 2026

Les valeurs suivantes s’appliquent après activation du nouvel équilibrage. La production horaire de référence est celle d’une position 5, dans un univers ×1, sans bâtiment ni événement.

| Ressource | Première planète | Colonie basique | Intermédiaire | Supérieure | Stockage de base | Production / h |
|---|---:|---:|---:|---:|---:|---:|
| Métal | 10 000 | 5 000 | 6 250 | 7 812,5 | 10 000 | 40 |
| Électricité | 10 000 | 0 | 0 | 0 | 10 000 | 0 |
| Antimatière | 10 000 | 5 000 | 6 250 | 7 812,5 | 10 000 | 10 |
| Hydrogène | 10 000 | 5 000 | 6 250 | 7 812,5 | 10 000 | 80 |
| Nano | 10 000 | 5 000 | 6 250 | 7 812,5 | 10 000 | 20 |

Le rang du vaisseau réellement consommé détermine le kit : ×1, ×1,25 ou ×1,5625 ; les fractions sont conservées et l’électricité reste nulle. Les stocks initiaux ne sont attribués qu’à la création des ressources. Aucun stock existant n’est redistribué ou remis à zéro.

Les capacités et productions de base concernent aussi les planètes existantes. Les usines, consommations, bonus de stockage, modificateurs de position, multiplicateurs d’univers et événements gardent leurs règles. Les taux du fichier de règles sont stockés par seconde (valeur horaire / 3 600).

Transition validée : tout surplus déjà présent au-dessus de la capacité est conservé et utilisable pour les dépenses. Aucun apport positif supplémentaire n’est crédité tant que la ressource dépasse la capacité ; la production reprend sous le plafond. Les remboursements et dépôts ne doivent jamais écrêter le stock déjà présent. Les consommations industrielles continuent selon les règles existantes. Les jauges restent bornées à 100 %, mais la quantité affichée montre tout le stock.

Clarification validée le 10 novembre 2026 : les règles courantes s’appliquent directement à toute la période de production non encore matérialisée, depuis le dernier calcul enregistré, y compris lorsque cette période précède le déploiement. Ce recalcul rétroactif des gains en attente est accepté ; il peut augmenter ou réduire ces gains selon les nouveaux taux. Il ne réinitialise pas les stocks déjà enregistrés. Les frontières d’événements et de vacances restent prises en compte. Cette décision remplace le mécanisme initial de bascule par date : `RESOURCE_BALANCE_EFFECTIVE_AT` n’est plus utilisée et aucun ancien régime n’est maintenu.

Aucune migration ni réécriture globale des stocks n’est requise. Les nouvelles valeurs sont actives dès que le nouveau code est exécuté, pour les univers existants comme pour les nouveaux. Lors du déploiement, arrêter les anciens processus applicatifs puis démarrer tous les services avec la même version ; aucun paramètre d’activation supplémentaire n’est nécessaire.

### Disposition du suivi sur les catalogues — ajustement du 10 novembre 2026

Sur Bâtiments, Recherche, Chantier spatial et Défenses : jusqu’à 740 px, l’activité reste en haut et les flottes en vol sont masquées. De 741 à 1 250 px, l’activité reste en haut et les flottes restent accessibles. À partir de 1 251 px, l’activité est à droite du catalogue, au-dessus des flottes en vol. La vue générale et la page Flottes gardent leur suivi. Le masquage mobile retire aussi les liens correspondants du parcours clavier ; aucune duplication de file n’est introduite.

### Rivalités personnelles — décisions du 11 novembre 2026

Décisions explicites de l'utilisateur : une hostilité personnelle est réciproque et peut être déclenchée par un seul joueur, comme entre guildes. Elle est accessible sans appartenance à une guilde.

Les liens collectifs priment : entre membres de la même guilde ou de guildes alliées, une rivalité personnelle reste sans effet sur les autorisations de combat. Elle ne permet pas de contourner l'interdiction d'attaquer ces alliés.

La fin d'une rivalité exige l'accord des deux joueurs : l'un propose la paix et l'autre l'accepte. Une proposition, son retrait ou son refus ne suffit pas à terminer l'hostilité.

Ces décisions ne valident aucun bonus de combat, accès à des renseignements privés, récompense de rivalité ou modification des protections existantes.

### Traités entre guildes — décisions du 11 novembre 2026

Décisions explicites de l’utilisateur : trois traités réciproques, signés par deux guildes du même univers. Une proposition conserve les effets du traité actif jusqu’à acceptation par l’autre guilde.

| Traité | Effets |
| --- | --- |
| Alliance complète | Conserve les effets existants : liens alliés pour les camps, stationnement défensif et préférences de transport. Aucune ressource ni flotte envoyée automatiquement. |
| Non-agression | Bloque les attaques et conquêtes directes entre les deux guildes au départ et à l’arrivée. Aucun droit supplémentaire de défense ou de transport. L’espionnage reste permis et les tirs entre camps d’un combat impliquant des tiers restent possibles. |
| Défense | Non-agression et possibilité de stationner des flottes défensives chez l’autre guilde avec accord du propriétaire. Aucun droit supplémentaire de transport ni regroupement offensif automatique. La défense ponctuelle déjà ouverte reste inchangée. |

Le dirigeant possède les droits ; chaque grade peut déléguer séparément alliance, non-agression, défense, hostilité et neutralité/paix. Les nouvelles permissions ne sont pas attribuées automatiquement aux grades existants. Les grades n’accordent aucun droit sur les relations personnelles des autres joueurs.

Une rupture est immédiate et unilatérale avec le droit de neutralité ; une déclaration d’hostilité rompt immédiatement le traité et ses propositions. La paix nécessite une proposition puis la signature de l’autre guilde : proposer, retirer ou refuser une offre maintient l’hostilité. Une alliance ou un pacte ne peut pas être proposé pour contourner une hostilité : signer la paix d’abord. Aucun préavis, coût, durée imposée, bonus économique ou engagement automatique n’est ajouté.

Les pactes ne fusionnent pas les camps offensifs. Le pacte de défense concerne le stationnement chez les propriétaires de colonies ; il ne donne pas à lui seul le droit de renforcer une occupation offensive. Une flotte déjà engagée est contrôlée à l’arrivée selon les règles en vigueur ; un combat déjà ouvert conserve ses règles de composition des camps. Les alliances et rivalités personnelles restent indépendantes et ne sont pas effacées par un pacte ou sa rupture.

### Projets collectifs de guilde — décisions du 11 novembre 2026

- Les membres donnent des ressources de leurs propres colonies pour financer des paliers successifs. Les dons sont définitifs et ne sont pas remboursés lors d’un départ, d’une dissolution ou d’un abandon.
- Chaque projet peut être lancé une seule fois par guilde. Le dirigeant ou un membre ayant la permission explicite `projects.start` choisit le modèle. Tous les membres peuvent contribuer sans grade particulier. Les vacances interdisent lancement et dons, mais permettent la consultation.
- L’administration définit, pour chaque univers, les coûts des paliers et les minimums de contribution par ressource. Aucun modèle n’est créé ou activé automatiquement. Les coûts et minimums sont figés dans chaque projet au lancement ; les modifications administratives et la désactivation du modèle ne changent pas les projets déjà financés.
- Un participant doit atteindre tous les minimums positifs, cumulés sur l’ensemble des paliers, et encore appartenir à la guilde à la clôture. Aucun taux de conversion entre ressources n’est inventé. Les personnes éligibles sont figées à cette clôture ; un départ ultérieur ne retire pas une récompense acquise.
- Chaque participant reçoit au maximum un boost standard pour le même modèle, même après changement de guilde, utilisation du boost ou création d’un nouvel empire dans le même univers. L’effet, la capacité et la file d’attente de l’inventaire existant sont conservés. Il n’y a ni bonus permanent, ni coefficient de puissance, ni récompense proportionnelle au volume donné.
- Le don est enregistré avec son prélèvement dans une transaction. Une quantité négative, fractionnaire, inconnue, excessive ou supérieure au besoin restant est refusée, sans débit partiel. Un ancien formulaire ne peut pas payer deux fois. Un palier se termine uniquement lorsque toutes ses ressources sont financées.
- Les récompenses sont distribuées après clôture par le relais existant. Une interruption peut être reprise sans doubler les boosts ; une récompense attend si l’inventaire est plein. La dissolution ou l’archivage d’une guilde abandonne les projets encore actifs et conserve leur historique ; les projets terminés gardent leurs récompenses acquises.

Bornes techniques des formulaires, sans montant d’équilibrage par défaut : 1 à 10 paliers, quantités entières, au plus un milliard d’unités par ressource et palier, et minimums ne dépassant jamais le coût total. Les coûts et seuils réels restent à choisir par l’administration avant activation. Les projets et grades appartiennent aux guildes ; aucune guilde n’est requise pour les alliances et rivalités personnelles, dont les règles restent indépendantes.

## Reconstruction autonome des empires oubliés — décision du 11 novembre 2026

L'utilisateur valide la reconstruction de tous les modèles de vaisseaux et de
défenses des empires oubliés, sans inclure les PNJ classiques. Chaque planète
possède une référence immuable des effectifs, modèles et rangs à la conversion,
flottes rappelées/en retour comprises. Les commandes déjà payées restent acquises
et peuvent dépasser cette référence sans la relever. Les empires antérieurement
convertis sans référence sont exclus ; aucune estimation rétroactive n'est créée.

Les coûts et durées normaux s'appliquent, avec prélèvement sur les stocks locaux
et réutilisation du chantier partagé. La reconstruction complète seulement le
manque après réparation partielle des défenses, survivants, retours et commandes
existantes. Le choix privilégie le coût effectif unitaire croissant selon les poids
économiques existants M=2, N=4, A=8, H=1, puis la durée croissante et un ordre stable
par modèle, rang et groupe. Les modèles non finançables sont ignorés lors de ce
choix. Cette règle favorise la quantité sans promettre un optimum combinatoire.

Chaque nouvelle commande autonome porte sur une unité. Le début ou la reprise
attend cinq minutes après la résolution du dernier combat local. Un nouveau combat
suspend le travail, conserve l'avancement et le paiement, et invalide ses messages
précédents. Aucun progrès ni livraison autonome pendant le combat.

La reconstruction continue pendant l'occupation ; les forces hostiles doivent
être vaincues avant transfert, et l'occupant victorieux garde son temps acquis.
La capture définitive arrête le mécanisme de l'ancien empire et annule ses commandes
restantes sans remboursement. Aucun mouvement offensif, nouvelle colonisation ou
nouvelle recherche n'est ajouté. Cette décision autorise les nouvelles commandes
de reconstruction et complète l'interdiction générale de nouvelles commandes PNJ
consignée le 8 novembre ; les autres commandes autonomes restent exclues.

Une table dédiée conserve référence et reprise, avec une commande en cours et
un jeton invalidant les messages périmés. Le relais et le worker existants assurent
l'exécution. En cas de stocks insuffisants, le contrôle est reprogrammé après
60 secondes ; ce délai technique ne remplace pas le délai de cinq minutes après
combat. La migration de compatibilité `Version20261111173000` (ancien numéro partagé `Version20261111010000`, voir [le suivi](GESTION_GUILDES_ALLIANCES.md)) ne convertit aucun empire
et demande une autorisation distincte avant application sur une base de jeu.


## Évolutions des interfaces et flottes — décisions du 11 novembre 2026

Cette demande remplace la présentation du classement joueur décidée le 5 novembre
(points totaux seuls et absence de colonne planètes). Les cinq catégories ont leur
rang, les variations sont sur la même ligne et le nombre de planètes précède les
points totaux. Formules de points et échéance quotidienne inchangées.
Dans ce tableau : « (= 0) » si inchangé, « (—) » sans comparaison.

Décisions confirmées pour les lots suivants, avec état d’implémentation dans le suivi :
- Ancienneté : acquisition par le propriétaire actuel, conquête incluse.
- Journal : absence depuis la dernière ouverture de la vue générale ;
  constructions/recherches terminées, arrivées et événements de combat,
  transport, espionnage et conquête ; marquage explicite comme consultés.
- Détails : vaisseaux et stocks des planètes ; composition et fret des flottes,
  accessibles au clavier et au toucher.
- Destinations : PNJ dans « Ennemis », neutres dans « Destination personnalisée » ;
  suggestions initialement limitées à la destination par pseudonyme.
- Points de flotte visible : somme des coûts des vaisseaux présents selon
  l’univers et le rang, divisée par 100 ; sans défense fixe.
- Rapports : archive personnelle consultable et restaurable, retrait individuel
  ou par sélection ; preuves administratives conservées.
- Guilde : tag de 2 à 5 lettres/chiffres majuscules, unique dans l’univers, choisi
  par le commandant et obligatoire à la création. « [TAG] Commandant » au classement
  uniquement ; pas de crochets vides sans guilde. Transition des anciennes guildes
  précisée ci-dessous.

Les précisions validées pour les rappels, la détection, les orbites, l’abandon et
les signalements figurent ci-dessous. Voir le [suivi](EVOLUTIONS_INTERFACES_FLOTTES.md).


### Précisions confirmées dans la même session

- Préavis d’attaque : niveau effectif = somme simple des trois rangs pour ce seul
  préavis. Formule validée : min(5 + 5 × niveau effectif, 120 minutes,
  50 % de la durée totale du trajet). Les effets pondérés sur l’espionnage et la
  détection à l’arrivée sont conservés. À la détection : planète ciblée et
  compte à rebours ; aucun détail supplémentaire validé à ce stade.
- Anciennes guildes : tag choisi par leur commandant, sans génération automatique.
  Tant qu’il manque, aucun crochet n’est affiché. Les nouvelles créations exigent
  immédiatement le tag validé.
- Anciennes colonies : ordre actuel conservé comme approximation si aucune date
  fiable ne peut être retrouvée. Enregistrer toutes les nouvelles acquisitions ;
  ne créer aucune date historique fictive.

L’état d’implémentation est décrit dans le suivi de chaque lot ; ces décisions
ne constituent pas une preuve de déploiement.


### Rappels, visibilité, orbite, abandon et signalements — validation utilisateur

- Rappel : toutes les missions en trajet aller, espionnage et colonisation inclus,
  ainsi que l’orbite et les demandes d’orbite. Retours, combats, résolutions
  d’arrivée et occupations exclus. Retour sur le chemin déjà parcouru selon la
  progression réelle, boosts compris. Carburant consommé au prorata ; fret et
  carburant inutilisé conservés. Si l’origine est perdue, choisir une autre colonie
  du joueur et recalculer temps/carburant. Sans repli possible, refuser sans
  changer l’état de la flotte.
- Déplacements non offensifs : propriétaire, destinataire et alliés uniquement,
  dans les limites de découverte. Sondes en trajet visibles par leur seul
  propriétaire ; détection à l’arrivée conservée.
- Attaque détectée : cible, compte à rebours, commandant, total de vaisseaux et
  points de flotte. Origine uniquement si déjà découverte ; jamais de composition
  détaillée pour un tiers.
- Orbite : demande au départ et valide jusqu’à l’arrivée. Alliance = accord
  automatique ; neutralité = décision manuelle. Refus ou retrait = retour
  immédiat ; silence à l’arrivée = retour, sans attente indéfinie. PNJ et mondes
  sans propriétaire interdits. Propriété et relation revérifiées à l’arrivée ;
  un accord invalidé entraîne le retour.
- Abandon : planète redevenue vierge ; perte des bâtiments, ressources, défenses
  et vaisseaux présents ; files annulées sans remboursement, historiques conservés.
  Interdit pour la dernière planète, en présence de flottes en trajet liées à la
  planète (aller/retour), d’orbites, d’attaques, d’occupation ou de combat.
  La planète initiale est autorisée si une autre colonie reste. Confirmation en
  retapant son nom, après présentation du bilan des pertes.
- Signalement joueur : Pseudonyme inapproprié, Harcèlement, Suspicion de triche,
  Autre ; commentaire. Contexte automatique : univers, joueur visé et pseudonyme
  au signalement, auteur, date, catégorie/rang consultés. Modération seule habilitée.

Décisions validées pour les futurs lots, non implémentées dans le lot A.
Les refontes attendent encore la validation des maquettes.


### Validation des maquettes et précision du retour système

Les trois maquettes locales Diplomatie, Profil et Inventaire sont validées par
l’utilisateur pour leur future intégration. Le blocage de quota Figma reste
distinct : aucune maquette n’y a été composée.
La croix de la fiche planète renvoie à la galaxie. La demande porte sur l’ajout
d’un bouton distinct dans cette fiche pour revenir au système de la planète
consultée ; le retour galaxie existant est conservé.

### Lot C — précisions validées et intégration locale du 12 novembre 2026

Accord explicite pour sauvegarder, vérifier puis appliquer la seule migration
locale Version20261112010000. Accord sur le démarrage du journal à la première
ouverture après mise à jour : aucune visite historique inventée, notifications
antérieures conservées ; seuls les événements suivants des catégories validées
alimentent le suivi d’absence.

Acquisitions enregistrées à la date réelle pour l’univers initial, colonisation,
conquête et capture après occupation. Les anciens propriétaires déjà enregistrés
ne reçoivent pas de date fictive ; les colonies inconnues gardent leur ordre par
identifiant, signalé comme approximatif, avant les acquisitions datées. Les dates
connues sont triées par ordre croissant, puis par identifiant en cas d’égalité.
Le même ordre s’applique aux cartes de la vue générale, à la page Planètes et
au sélecteur latéral.

Journal : ouverture explicite de la vue générale, requêtes automatiques exclues.
Les événements proposés restent non consultés entre visites jusqu’au bouton de
consultation. Une action ne marque que les identifiants effectivement affichés ;
un événement arrivé après l’affichage reste à consulter. Conservation des données,
50 événements affichés au maximum puis accès aux suivants après consultation.
Les détails privés des planètes (unités au repos et ressources) et flottes
(composition et fret) conservent survol, clavier et toucher.
Ces éléments sont locaux sur la branche du lot C, pas une annonce de déploiement.

### Lot D — choix PNJ confirmé le 12 novembre 2026

Le besoin dépasse l’interface : canAttack refusait les PNJ sans propriétaire,
tandis que Conquête JcE existait déjà. Décision explicite : proposer deux missions
sur les PNJ découverts, Attaque pour combattre et Conquête JcE pour capturer.
Une attaque classique ne capture pas la planète et n’ajoute aucun butin PNJ.
La capture conserve vaisseau colonie survivant, emplacement disponible et occupation
de 24 heures selon le fonctionnement validé de l’univers. Aucun coût, préavis,
niveau de garnison, calcul de butin ou valeur d’équilibrage n’est modifié.

### Mise en œuvre du lot E — choix déjà validés

Archive personnelle consultable/restaurable, actions individuelles ou par sélection,
sans suppression des preuves ni changement des droits d’accès. Le tri devient
commun aux trois types de rapports avant pagination. Les états d’archive sont
isolés par participation, type et rapport ; restauration sans effacement du rapport.

L’attribution de vaisseaux demandée reprend les protections de la correction de
ressources : administrateur actif, planète explicitement choisie sur la fiche du
joueur, type/rang du catalogue, quantité entière, motif et confirmation, trace
avant/après et reçu de requête. Elle crédite uniquement le stock au sol.
Les limites d’entier sont des gardes techniques, pas un nouvel équilibrage.

### Mise en œuvre des choix du lot F

Le commandant actuel choisit le tag obligatoire à la création et celui des guildes
anciennes, sans reprise fictive. Normalisation en majuscules, unicité par univers ;
préfixe des commandants uniquement au classement. Les autres pseudonymes restent
inchangés. Les fixtures de création ajoutent explicitement la nouvelle donnée
obligatoire ; aucun tag automatique n’est ajouté au jeu.

Les quatre motifs et le contexte du signalement sont ceux validés dans cette
session. Le contexte de classement est mémorisé côté serveur au formulaire ;
l’envoi conserve le pseudonyme courant et, s’il diffère, celui consulté.
Le commentaire est facultatif, borné techniquement à 1000 caractères. La modération
classe ou marque le signalement traité avec audit, sans nouvelle sanction automatique.
L’idempotence concerne le même formulaire ; aucun quota de signalements ni règle
d’équilibrage supplémentaire n’est introduit.

### Précisions du rappel et du repli — 12 novembre 2026

Pour les anciennes flottes, le rappel est autorisé quand son calcul est fiable.
Si les données sont insuffisantes, le rappel est indisponible avec explication
et la mission continue normalement ; aucune progression historique n’est inventée.
Les nouveaux départs enregistrent les informations nécessaires.

Après perte de l’origine, le repli vers la colonie choisie utilise les calculs
normaux de l’univers au moment du rappel. Seul le carburant encore disponible
est utilisable : le fret d’hydrogène et les stocks des planètes sont préservés.
Si le carburant manque, le rappel est refusé sans changement d’état.


### Orbites — transition validée le 12 novembre 2026
- Les missions déjà parties sans demande versionnée conservent leur processus
  d’acceptation à l’arrivée pour ce voyage ; aucune échéance n’est inventée.
- Les nouveaux départs créent une demande valable jusqu’à l’arrivée :
  accord automatique entre alliés, décision manuelle pour un joueur neutre,
  refus pour une planète ennemie, PNJ ou sans propriétaire.
- Une orbite neutre acceptée participe à la défense de la planète d’accueil,
  comme une orbite alliée. Les permissions de la mission Défense restent inchangées.
- Refus ou retrait : retour immédiat depuis la position atteinte, avec le calcul
  validé du rappel. Sans accord valide à l’arrivée : retour, sans attente indéfinie.
  Propriétaire et diplomatie sont revérifiés à l’arrivée.


### Abandon — dépendances entre colonies, validation du 12 novembre 2026
L’abandon est bloqué si une commande d’une autre colonie dépend d’une commande
de la planète abandonnée. Le bilan indique les commandes à gérer ; aucune
commande d’une autre colonie n’est annulée implicitement. Cette décision
complète les conséquences d’abandon déjà validées.


### Abandon — kit de recolonisation, validation du 12 novembre 2026
Un joueur qui abandonne une planète ne reçoit plus de kit de colonisation s’il
recolonise cette même planète dans cet univers. Un nouveau propriétaire conserve
le kit normal. Les autres coûts, conditions, quotas et consommations restent
inchangés. Cette règle empêche la récupération répétée du kit après vidage,
abandon et recolonisation. Le bilan d’abandon annonce l’absence de nouveau kit
et le besoin de ravitaillement pour le joueur qui abandonne.
