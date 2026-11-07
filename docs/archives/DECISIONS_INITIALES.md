# Phase 02 — décisions de gameplay et critères de combat

> Archive : texte conservé dans son contexte d’origine, sans validation nouvelle.
> Les règles actuelles sont dans [REGLES](../REGLES.md),
> [DECISIONS](../DECISIONS.md) et [ROADMAP](../ROADMAP.md).

Mis à jour : 2 novembre 2026. Les règles et choix utilisateurs ci-dessous ont été conservés. Les valeurs chiffrées du QCM appliquées à la configuration et les mesures attendues pendant l’Alpha sont dans BALANCING_QCM_2026-11-02.md.

## Orientation d’équilibrage confirmée — 01/11/2026
Kévin choisit **B — stratégie modérée** : progression régulière, gains et pertes contrôlés, fenêtres de protection et décisions tactiques lisibles, sans obligation de rester connecté en permanence. Cette orientation guide une progression régulière sans obligation de connexion continue. Les valeurs initiales ont été choisies par Kévin dans le QCM; les joueurs Alpha mesureront la progression réelle et guideront les ajustements.

## Décision confirmée — remboursement d’annulation (01/11/2026)
Kévin fixe le remboursement à **25 % du coût effectivement payé** pour toute opération annulée (bâtiment, recherche, vaisseau ou défense), y compris les éléments dépendants retirés en cascade. Le remboursement reste plafonné par la capacité de stockage disponible. Le paramètre `cancellation_refund_ratio` du catalogue est déjà réglé à `0.25`; les tests existants couvrent le taux, le remboursement idempotent et la limite de stockage.

## Fenêtre de renforts confirmée — 01/11/2026
Kévin confirme une fenêtre de **600 secondes (10 minutes)** à partir de la première arrivée d’attaque. La valeur `combat_window_seconds` est déjà fixée à `600` dans `config/game/rules.yaml`; la durée n’est plus un arbitrage ouvert. Le plafond de 10 rounds est confirmé. Les statistiques et coefficients initiaux sont détaillés dans BALANCING_QCM_2026-11-02.md; ils seront évalués en Alpha.

## Cadence de combat en temps réel confirmée — 01/11/2026
Kévin choisit un round réel par minute, avec un maximum de 10 rounds. Les renforts doivent être intégrés au prochain round auquel ils peuvent participer selon leur arrivée. Cette cadence est implémentée et testée : le premier round démarre une minute après l’arrivée de la première attaque, puis les rounds se déroulent chaque minute jusqu’au 10e ou à l’élimination d’un camp.

## Maximum de rounds confirmé — 01/11/2026
Kévin confirme un plafond de **10 rounds** par combat, valeur déjà configurée dans `config/game/rules.yaml`. La résolution actuelle persiste les rounds à leurs échéances et intègre les renforts au prochain round non résolu. Le premier round démarre une minute après l’arrivée de la première attaque; les rounds suivants se déclenchent toutes les 60 secondes, jusqu’au 10e round.

## Source des boosts confirmée — 01/11/2026
Kévin choisit les événements comme source principale des boosts et les missions à risque comme source secondaire, avec une limite de gains sur ces missions; aucun achat réel. Chaque boost retire 25 % du temps restant de l’opération chronométrée active, y compris les missions de flotte; les boosts successifs se cumulent sur le nouveau temps restant, sans accélérer les opérations ou files en attente. Le plafond des missions à risque est fixé à un boost maximum par joueur et par jour civil UTC, avec réinitialisation à minuit UTC; le jour de comptabilisation est le jour UTC de fin de mission et d’attribution du boost; les récompenses d’événements ne sont pas incluses dans cette limite. Les missions éligibles sont l’attaque, la défense, la conquête JcE, l’exploration et l’espionnage. Les missions à risque ne donnent un boost qu’en cas d’objectif atteint; une attaque à risque n’est réussie qu’en cas de victoire des attaquants (égalité ou défaite : aucun boost); échec ou espionnage détecté n’en donne pas. Une défense à risque réussit si les défenseurs remportent le combat, y compris lorsque les attaquants battent en retraite à la limite des rounds. Une conquête JcE à risque réussit uniquement si la planète PNJ est capturée et passe sous le contrôle du joueur. Une exploration à risque ne réussit que si elle découvre un système jusque-là inconnu. Une mission d’espionnage à risque ne réussit que si elle n’est pas détectée et révèle au moins une catégorie de renseignement. Le rythme initial des événements est d’une fois par semaine, avec un démarrage commun chaque lundi à 00 h UTC, modifiable en configuration; la durée est configurable pour chaque événement, avec une valeur par défaut de 7 jours; les événements hebdomadaires peuvent se chevaucher, dans la limite d’un nombre maximal d’événements actifs configurable, fixé à 2 par défaut; si le plafond est atteint à l’heure prévue, l’événement est mis en attente et démarre dès qu’une place se libère; s’il y en a plusieurs, le plus ancien événement prévu démarre d’abord; sa durée commence à son démarrage effectif; la force du boost étant fixée à 25 %, chaque joueur obtient un boost lorsqu’il termine l’événement hebdomadaire, au maximum un par événement. Les événements hebdomadaires peuvent prendre trois formats : objectif individuel, objectif collectif ou classement compétitif. Le mode de récompense se règle événement par événement : top 3, seuil de contribution ou tous les contributeurs. En mode « tout le monde », toute contribution donne droit au boost même si l’objectif collectif échoue. En mode « seuil de contribution », un seuil minimal est défini séparément pour chaque événement selon son objectif; atteindre ce seuil individuel suffit même si l’objectif global échoue. Pour un événement collectif en mode « top 3 », l’objectif collectif doit être réussi pour attribuer les boosts. En mode « top 3 », le classement utilise la même métrique de contribution que celle configurée pour l’événement; tous les joueurs à égalité à la troisième place reçoivent le boost, même si cela porte le total au-delà de trois joueurs. Au maximum un boost par joueur et par événement; la contribution est mesurée par une métrique propre à chaque événement et liée à son objectif; les boosts d’événement sont attribués à la clôture, après figement des contributions et du classement; les boosts gagnés n’expirent pas et restent dans l’inventaire jusqu’à utilisation; le stock maximal par joueur est plafonné avec une limite configurable, fixée à 3 boosts par défaut; lorsqu’une récompense est attribuée alors que le stock est plein, elle reste en attente jusqu’à ce qu’une place se libère; toutes les récompenses de boosts attribuées pendant que le stock est plein, qu’elles proviennent d’un événement ou d’une mission à risque, sont conservées sans limite dans une file d’attente; le plafond de 3 s’applique aux boosts disponibles dans l’inventaire, pas au total des récompenses en attente; dès qu’une place se libère, la récompense en attente la plus ancienne est transférée automatiquement dans l’inventaire; les métriques des trois événements initiaux sont détaillées ci-dessous; le moteur correspondant est désormais implémenté et testé.


## Événements initiaux confirmés — 01/11/2026

Kévin valide une rotation hebdomadaire de trois événements, par univers, avec un événement différent chaque semaine. La première occurrence est programmée le lundi 02/11/2026 à 00 h UTC; les suivantes tournent dans l’ordre ci-dessous. Durée par défaut : 7 jours; maximum de 2 événements actifs par univers; les événements en attente sont démarrés dans l’ordre de leur date de programmation, et leur durée commence au démarrage réel.

| Événement | Format | Récompense | Contribution et objectif |
|---|---|---|---|
| Exploration | Compétitif | Top 3, ex æquo à la troisième place inclus | +1 par système jamais découvert par ce joueur; objectif collectif de 10 nouvelles découvertes dans l’univers. Le Top 3 n’est récompensé que si l’objectif est atteint. |
| Combat | Individuel | Seuil | Somme des dégâts effectivement infligés par le joueur, d’après les tirs résolus par le simulateur; seuil provisoire et réglable : 5 000 dégâts par joueur. L’atteindre suffit même si un objectif collectif échoue. |
| Transport | Collectif | Tous les contributeurs | Une livraison de fret réussie avec une quantité strictement positive compte comme contribution; tout contributeur reçoit le boost à la clôture. |

Les événements démarrent automatiquement depuis le worker d’outbox existant. Les définitions, seuils, durée, cadence et chevauchement sont configurés dans config/game/boosts.yaml. Les seuils de combat sont réglables sans migration de base de données.

## Algorithme retenu

- Le moteur reçoit un instantané de participants déjà validé et réparti en camps; il ne lit ni HTTP ni données Doctrine.
- Les relations alliées sont regroupées par composantes connexes de la relation d'alliance du combat. Cela rend les camps transitifs et permet de traiter sans ambiguïté les coalitions. En FFA, chaque joueur non relié est un camp distinct.
- Les rounds se déclenchent à +60, +120, ..., +600 secondes. Un renfort arrivé avant un round encore non résolu rejoint le prochain round éligible. Une flotte arrivée exactement au début d’un round est traitée avant ce round et peut y participer, notamment à +600 secondes avant le 10e round. Le combat se clôt immédiatement dès qu’un camp est éliminé; une flotte arrivée après cette clôture repart sans rejoindre le combat.
- À chaque round, les unités vivantes et arrivées sont mélangées avec Randomizer/Mt19937 alimenté par la graine persistée. Chaque unité choisit uniformément une cible hostile vivante au début de son tour. Elle tire ensuite un nombre entier uniforme et inclusif entre shots_min et shots_max; chaque tir a son propre jet de toucher.
- Le jet de toucher utilise 10 000 valeurs discrètes par pourcentage, pour conserver les décimales de la formule (ex. 94,2 %). Les dégâts sont base_attack × matchup, sans arrondi, plafonnés aux HP restants. Une cible détruite avant son tour ne tire pas; les tirs restants d'un attaquant restent sur sa cible et sont perdus si elle est détruite.
- Les paramètres de dégâts et les alliances sont figés dans l'instantané de résolution. Les HP simulés sont temporaires; seuls survivants/pertes et le journal seront persistés par l'intégration.
- Les défenses statiques appartiennent au camp du propriétaire pour l'issue, mais ne ciblent que les unités de rôle attacker et ne reçoivent pas les tirs des défenseurs extérieurs. Les vaisseaux défenseurs extérieurs ciblent seulement les attaquants.
- Issue : draw si les deux côtés sont éliminés; attacker_wins si les défenseurs sont éliminés et qu'au moins un attaquant survit; defender_wins si les attaquants sont éliminés ou si la limite de rounds est atteinte avec défenseurs survivants (retraite des attaquants). Les attaquants FFA peuvent aussi se tirer dessus avant de gagner.
- Les entrées de même propriétaire/rôle/type ayant des identifiants d'unité dupliqués sont rejetées. Le simulateur limite à 5 000 unités par résolution pour borner le coût mémoire/CPU.

## Composant créé

CombatSimulator reste un service pur. Son intégration aux événements persistés, aux arrivées de flottes, aux pertes, au journal interne et aux notifications est implémentée et couverte par des tests d'intégration. Un rapport joueur avec contrôle d’accès participant/propriétaire est maintenant en place; la mission Défense temporisée rejoint un combat ouvert ou repart après 600 secondes sans attaque; l’orbite, le butin et la conquête JcE sont intégrés. Kévin a confirmé les règles de conquête; les profils PNJ choisis pour l’Alpha seront évalués avec les joueurs. L’espionnage est intégré et couvert par des tests d’intégration; lint, schéma et installation vierge passent. La phase 02 est clôturée; les valeurs PNJ et d’espionnage retenues servent de base à l’Alpha; leur équilibre sera jugé à partir des retours.

## Choix ajoutés — mission Défense

- Une flotte Défense peut viser une planète connue et possédée du même univers, qu’il existe ou non une relation d’alliance; ce choix autorise le cas documenté d’un défenseur extérieur non allié.
- Si un combat a une fenêtre ouverte, la flotte entre immédiatement comme participante défenseuse et son round dépend de l’heure d’arrivée. Sinon elle attend une fenêtre `combat_window_seconds`; si aucun combat ne s’ouvre, elle rentre automatiquement.
- Le carburant de stationnement est réservé distinctement à l’envoi. À l’expiration sans attaque ou à la résolution du combat, il est débité avant le retour; le carburant aller-retour reste réservé.

## Choix ajoutés — mission Orbite

- Durée choisie à l’envoi : nombre entier de 1 à 168 heures; le plafond de 168 heures est un choix provisoire d’implémentation.
- Une flotte peut stationner sur une planète possédée par un autre membre allié, dans le même univers. Un contrat direct accepté permet l’acceptation automatique; les relations par guilde ou alliance de guildes exigent la décision du propriétaire.
- Le carburant de trajet aller-retour et le carburant de stationnement sont réservés au départ. Le carburant de stationnement est prélevé au retour de la durée acceptée; un refus d’accueil libère cette réserve et lance le retour. La durée de stationnement ne dépend pas de la vitesse choisie.
- Une flotte en orbite rejoint la défense si une attaque ouvre un combat pendant son stationnement. Une demande d’accueil en attente n’est pas une défense participante.
- La demande masque l’identité de l’émetteur si son système d’origine n’a pas été découvert par l’hôte.

## Choix confirmés par Kévin — conquête JcE

- La conquête n’est pas JcJ : elle vise uniquement les planètes neutres défendues par le jeu et est activée dans Omega et Turbo. Les planètes vierges restent colonisables sans combat; les planètes déjà détenues restent aux joueurs. Le contrôle est représenté par propriétaire, statut PNJ ou statut vierge.
- La flotte PNJ et les défenses fixes combattent. Les pertes PNJ restent acquises après une attaque échouée.
- La planète est prise après une victoire avec au moins un vaisseau colonie survivant; le vaisseau est consommé. La limite habituelle de colonies s’applique.
- Après la prise, la planète conserve ses bâtiments et ses ressources. Les défenses fixes et la flotte PNJ disparaissent.
- La carte respecte la spécification existante : 1 à 9 planètes par système, maximum déjà validé par l’importateur. Environ 30 % des planètes cataloguées sont choisies aléatoirement comme PNJ; les autres restent vierges. Ce tirage est écrit une fois dans le JSON de carte pour rester fixe après import et redémarrage.
- Chaque planète PNJ possède un niveau fixe 1–5 dans le catalogue. La répartition des niveaux progresse selon la position orbitale (positions 1–2 niveau 1, 3–4 niveau 2, 5–6 niveau 3, 7–8 niveau 4, 9 niveau 5).
- Chaque niveau définit des préréglages de bâtiments, stock, flotte et défenses. Ils sont configurables dans config/game/planet_conquest.yaml; leurs valeurs de départ sont celles choisies pour l’Alpha et seront évaluées par les retours des joueurs.
- Le préréglage PNJ est créé à la première attaque JcE. Les bâtiments et ressources existants sont conservés; les pertes de flotte PNJ sont persistées et ne sont pas regénérées après un échec.

## Équilibrage et limites

La fenêtre de combat de 600 secondes et le plafond de dix rounds restent confirmés. Les paramètres initiaux de combat choisis pendant le QCM, dont la matrice des matchups, les stats d’unités et les boucliers, sont détaillés dans BALANCING_QCM_2026-11-02.md. Les tests Alpha serviront à évaluer le résultat des combats et les pertes; la règle d’un round par minute est implémentée.

Le ratio 100 % et le plancher protégé de 10 % sont confirmés par l'utilisateur; leur équilibrage économique reste à observer en partie réelle.


## Choix confirmés par Kévin — espionnage

- Une mission d’espionnage vise une planète connue du même univers qui n’appartient pas à l’émetteur : joueur, PNJ ou planète vierge.
- La mission transporte une ou plusieurs sondes uniquement, sans escorte. Toutes les sondes envoyées sont consommées à l’arrivée, que la mission soit détectée ou non.
- Catégories révélées, dans l’ordre : ressources/production, bâtiments, recherches, flottes, défenses fixes. Les valeurs sont filtrées selon le niveau d’espionnage de l’émetteur comparé à celui de la cible et le nombre de sondes : score = max(0, 1 + espionnage émetteur − espionnage cible + sondes envoyées − 1), plafonné au nombre de catégories.
- Le risque de détection choisi est de 50 % de base, puis +10 points par niveau de contre-espionnage de la cible, −10 par niveau de furtivité de l’émetteur, +1 par sonde envoyée après la première et +1 par sonde dans la réserve locale de la cible; résultat borné entre 5 et 95 %.
- En cas de détection, le propriétaire reçoit une alerte et le rapport ne contient aucun renseignement. Son identité n’est révélée que si le système d’origine est connu de la cible. Les rapports restent consultables uniquement par leur émetteur.
- Les sondes locales présentes dans une flotte idle sur la planète, en défense ou en orbite comptent comme réserve défensive. Les réserves PNJ sont prises dans le catalogue de niveau PNJ.
- Pour les rapports PNJ, la garnison PNJ est initialisée avant la capture des renseignements. Les valeurs de recherche PNJ sont issues du préréglage de niveau; une planète vierge ne possède aucune recherche ni garnison.
- Ces coefficients sont retenus dans config/game/espionage.yaml; les profils de recherche et les sondes PNJ sont dans config/game/planet_conquest.yaml.


## Équilibrage des règles de base validé par Kévin — 01–02/11/2026

Le QCM de balance est clos. Les valeurs et règles retenues sont détaillées dans docs/agent/BALANCING_QCM_2026-11-02.md. La matrice conserve les contre-mesures explicitement sourcées dans le PDF, puis applique le cycle extrême aux paires absentes; les règles confirmées de cadence et de pillage sont préservées. Les multiplicateurs PNJ modulent le stock initial, pas la production récurrente. Les cibles de rythme de progression restent à mesurer par scénarios.
