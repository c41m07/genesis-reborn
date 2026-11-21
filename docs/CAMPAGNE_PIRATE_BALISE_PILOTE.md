# Balise 17 : résolution interne du premier signal

Le résolveur ne reçoit que la colonie active issue du contexte serveur. Pour
le premier départ, le signal appartient à son système existant et déjà
découvert par cette participation (ligne PlayerDiscovery persistée). Il n’y
a ni exploration imposée, ni système, planète ou position colonisable créée.

Dès qu’une reconnaissance canonique existe pour le dossier, elle seule fixe
l’ancre. Une colonie active différente ne peut ni réarmer le scout ni déplacer
le signal. L’ancre historique est consultable dans ce résolveur seulement si
une colonie du même empire est encore présente dans ce système et que le
système connu reste joignable dans la même galaxie. À défaut, le résultat est
neutre. Une ancienne preuve sans profil ne donne pas de nouveau premier
départ. Les appels ne renvoient pas de coordonnées au navigateur et ne
modifient aucune donnée.

Ce socle ne traite que le premier signal « Le silence de Balise 17 ». Ce
n’est ni une règle de placement des 19 autres missions, ni un nouveau
prérequis de progression. La garde pilote, l’intention de scout, les routes,
la navigation et la première recette authentifiée sont des étapes séparées.

## Première intention de scout, sans route

Le service n’existe qu’en environnement Symfony dev et ne lit le compte
qu’après l’opt-in local explicite. Il ne traite que l’ordinal 1 et reçoit la
colonie active résolue par un futur adaptateur serveur, jamais un choix du
formulaire. Les champs de destination, de profil, de coût et de durée sont
rejetés : il n’y a aucun choix POST pour le scout.

Les verrous jeu sont ceux du lanceur natif. Le compte et sa sélection sont
relus via le verrou SQL NOWAIT déjà employé par les récompenses quotidiennes :
une sélection ou suppression en cours ne doit pas ajouter de cycle de verrous.
Un changement de participant ou d’univers, une colonie non possédée, un
système non découvert ou une preuve existante ne préparent aucun ordre.

L’intention CSRF Symfony couvre l’origine, le système choisi par les preuves
de jeu et le devis H₂ natif (réserve embarquée utilisable, complément du
stock et durée), sans date de clic. Recalcul sous les mêmes verrous avant
vérification du jeton. Tant que l’intention reste invalide ou le contexte
occupé, il n’y a ni paiement, sonde retirée, ligne de mission ni événement.
Après confirmation seulement le lanceur natif atomique s’exécute, sans faire
explorer une zone inconnue, fabriquer de coordonnées ni modifier la carte.

Aucune route POST, aucun bouton, pas de trajet sur compte réel ni activation
du pilote dans ce périmètre. Les champs du devis ne constituent pas une
preuve que la sonde est partie. Le futur adaptateur ne projettera ni l’objet
d’intention, ni sa coordonnée privée, ni les paramètres par défaut du pilote.
