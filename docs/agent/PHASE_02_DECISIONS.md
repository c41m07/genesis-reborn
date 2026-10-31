# Phase 02 — choix déterministes pour la simulation

Date : 31/10/2026. Ces décisions rendent implémentables les points non chiffrés des spécifications; elles restent des choix d'agent et ne remplacent pas une validation d'équilibrage.

## Algorithme retenu

- Le moteur reçoit un instantané de participants déjà validé et réparti en camps; il ne lit ni HTTP ni données Doctrine.
- Les relations alliées sont regroupées par composantes connexes de la relation d'alliance du combat. Cela rend les camps transitifs et permet de traiter sans ambiguïté les coalitions. En FFA, chaque joueur non relié est un camp distinct.
- Un renfort entre au round le plus proche de son arrivée relative : round = clamp(1, maxRounds, 1 + round(fractionÉcoulée × (maxRounds - 1))). L'arrivée initiale entre au round 1; une arrivée trop tardive après résolution ne rejoint pas l'événement.
- À chaque round, les unités vivantes et arrivées sont mélangées avec Randomizer/Mt19937 alimenté par la graine persistée. Chaque unité choisit uniformément une cible hostile vivante au début de son tour. Elle tire ensuite un nombre entier uniforme et inclusif entre shots_min et shots_max; chaque tir a son propre jet de toucher.
- Le jet de toucher utilise 10 000 valeurs discrètes par pourcentage, pour conserver les décimales de la formule (ex. 94,2 %). Les dégâts sont base_attack × matchup, sans arrondi, plafonnés aux HP restants. Une cible détruite avant son tour ne tire pas; les tirs restants d'un attaquant restent sur sa cible et sont perdus si elle est détruite.
- Les paramètres de dégâts et les alliances sont figés dans l'instantané de résolution. Les HP simulés sont temporaires; seuls survivants/pertes et le journal seront persistés par l'intégration.
- Les défenses statiques appartiennent au camp du propriétaire pour l'issue, mais ne ciblent que les unités de rôle attacker et ne reçoivent pas les tirs des défenseurs extérieurs. Les vaisseaux défenseurs extérieurs ciblent seulement les attaquants.
- Issue : draw si les deux côtés sont éliminés; attacker_wins si les défenseurs sont éliminés et qu'au moins un attaquant survit; defender_wins si les attaquants sont éliminés ou si la limite de rounds est atteinte avec défenseurs survivants (retraite des attaquants). Les attaquants FFA peuvent aussi se tirer dessus avant de gagner.
- Les entrées de même propriétaire/rôle/type ayant des identifiants d'unité dupliqués sont rejetées. Le simulateur limite à 5 000 unités par résolution pour borner le coût mémoire/CPU.

## Composant créé

CombatSimulator reste un service pur. Son intégration aux événements persistés, aux arrivées de flottes, aux pertes, au journal interne et aux notifications est implémentée et couverte par des tests d'intégration. Un rapport joueur avec contrôle d’accès participant/propriétaire est maintenant en place; la mission Défense temporisée rejoint un combat ouvert ou repart après 600 secondes sans attaque; l’orbite, le butin et la conquête JcE sont intégrés. Kévin a confirmé les règles de conquête; les compositions PNJ restent provisoires. L’espionnage reste à faire, donc la phase 08/09 est partielle.

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
- Chaque niveau définit des préréglages de bâtiments, stock, flotte et défenses. Ils sont configurables dans config/game/planet_conquest.yaml; leurs valeurs de départ sont explicitement provisoires.
- Le préréglage PNJ est créé à la première attaque JcE. Les bâtiments et ressources existants sont conservés; les pertes de flotte PNJ sont persistées et ne sont pas regénérées après un échec.

## Équilibrage et limites

La fenêtre de 600 secondes, les dix rounds, les statistiques et la matrice restent provisoires lorsque les catalogues l'indiquent. Le choix uniforme, l'initiative, la valeur de base du dégât et la retraite sont des règles explicites d'implémentation; ils devront être relus dans l'audit d'équilibrage final. Règle de butin demandée par Kévin : 100 % du stock au-dessus de 10 % de la capacité de stockage propre à chaque ressource est pillable après une victoire des attaquants. Le mélange suit le prorata des stocks pillables; le butin va à tous les attaquants survivants, proportionnellement à leur soute libre après protection du carburant de retour. Au retour, le dépôt est plafonné par la capacité de stockage de la planète d’origine et le surplus est perdu.

Le ratio 100 % et le plancher protégé de 10 % sont confirmés par l'utilisateur; leur équilibrage économique reste à observer en partie réelle.
