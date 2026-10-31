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

CombatSimulator reste un service pur. Son intégration aux événements persistés, aux arrivées de flottes, aux pertes, au journal interne et aux notifications est implémentée et couverte par des tests d'intégration. Un rapport joueur avec contrôle d’accès participant/propriétaire est maintenant en place; les renforts/défense en missions dédiées, le butin et la conquête manquent encore; la phase 08/09 reste donc partielle.

## Équilibrage et limites

La fenêtre de 600 secondes, les dix rounds, les statistiques et la matrice restent provisoires lorsque les catalogues l'indiquent. Le choix uniforme, l'initiative, la valeur de base du dégât et la retraite sont des règles explicites d'implémentation; ils devront être relus dans l'audit d'équilibrage final. Le modèle de butin demeure non spécifié et n'est pas simulé par ce composant.
