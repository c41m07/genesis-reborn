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

La première ancre concerne « Le silence de Balise 17 ». Les signaux suivants
reprennent le dernier site de victoire seulement s'il est toujours connu,
possédé et joignable, sinon le système découvert de la colonie active. Une
reconnaissance existante garde toujours son système historique : si ce système
ne peut plus être utilisé, aucun déplacement ni nouveau départ n’est simulé.

## Première intention de scout, sans route

Le service n’existe qu’en environnement Symfony dev et ne lit le compte
qu’après l’opt-in local explicite. Il ne traite que l’ordinal canonique
réellement en cours parmi les vingt et reçoit la colonie active résolue par
un futur adaptateur serveur, jamais un choix du formulaire. Les champs de destination, de profil, de coût et de durée sont
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

## Progression canonique et état final

Le lecteur de commandes réutilise la même séquence que le dossier. La victoire
réelle et remise de la mission précédente ouvrent la suivante, sans attendre
un convoi ou un retour d’escorte. Un ancien dossier reste consultable, mais ne
réarme ni scout ni attaque. Un renseignement concerne uniquement sa propre
mission. Le CSRF contient l’ordinal, la clé canonique et la preuve de progression,
en plus du contexte, du devis et, pour l’attaque, de la composition précise.
Après la vingtième victoire, aucune nouvelle commande de rencontre n’est proposée.
Aucune lecture ne crédite un convoi ni ne modifie un retour.

## Premier circuit HTTP local : reconnaissance et attaque

Le dossier et ses trois POST n’existent que dans le routeur Symfony dev ;
l’opt-in local reste fermé par défaut. Aucun menu du jeu ne les publie.
La colonie et le compte viennent de la session serveur, de la participation
active relue et de la propriété courante. Sans sélection explicite, le
premier choix initial du jeu est visible avant le clic ; si une sélection
explicite est devenue invalide, aucun changement d’origine n’est inventé.

Le GET et l’aperçu ne règlent jamais de convoi ni ne déclenchent d’ordre
ou de suivi de présence. Le POST de scout confirme son devis. Le POST
d’aperçu d’attaque ne prépare qu’une confirmation distincte, que le POST
final relit à nouveau sous verrou. Les formulaires ne transportent ni
coordonnées, ni colonie, ni coûts et aucun résultat brut du moteur. Seuls
le jeton Symfony et, pour l’attaque, les ordinaux et quantités choisis
sont acceptés. Après réussite : retour 303 au dossier. Un refus reste
neutre (404 hors accès, 409 pour une intention désormais indisponible).
Toutes les réponses du contrôleur sont privées et non mises en cache.

Le compte à rebours est seulement une aide d’affichage, calculée à partir
d’une échéance persistée et de l’horloge monotone du navigateur. Il ne
modifie pas l’heure absolue, n’appelle pas de serveur et n’affiche ni
arrivée ni victoire au moment zéro. Les heures absolues restent utilisables
sans JavaScript ; seul le vrai rapport ou le vrai retour confirmé déclenche
l’étape suivante lors d’une lecture explicite. Les dossiers passés et le
dossier terminé demeurent lisibles sans formulaire.

Les actions de récupération de convoi et de repli des survivants ne sont
pas encore routées par ce raccordement. Le circuit n’est pas annoncé
jouable avant une recette privée comprenant session Symfony authentique,
transport Messenger réellement relayé et workers, sans compte ni donnée réelle.
