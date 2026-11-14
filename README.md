# Genesis Reborn

Genesis Reborn est un jeu de stratégie spatiale multijoueur par navigateur. Chaque joueur développe ses colonies, organise sa production et ses recherches, explore la galaxie et conduit des opérations de flotte dans des univers indépendants. L’Alpha sert aussi à observer la progression et les retours des joueurs avant de futures décisions d’équilibrage.

L’orientation confirmée est une stratégie au rythme modéré ; les durées de progression restent des objectifs à mesurer pendant l’Alpha.

## État du projet

- Messagerie : Ctrl + Entrée pour envoyer dans les canaux privé, guilde et Global ; Entrée conserve les sauts de ligne. Markdown sécurisé pour les nouveaux messages seulement, historique conservé en texte ; soumissions répétées protégées côté serveur. CommonMark est une dépendance Composer. [Guide et migration préparée](docs/MESSAGERIE_MARKDOWN.md).

- Profils et guildes : profils de joueurs consultables uniquement par les membres du même univers, avec pseudo, guilde/tag et scores/rangs quotidiens déjà publics ; liens depuis classement, messagerie et membres de guilde. Pages de guildes avec description, logo et bannière, modifiables uniquement par le commandant. Voir [le guide et la préparation de migration](docs/PROFILS_JOUEURS_GUILDES.md). GD (JPEG/PNG/WebP) est intégré aux images Docker ; les médias privés utilisent le volume de production `guild_media`, distinct de la sauvegarde SQL.
- Outils stratégiques : simulateur de dix combats reproductibles pour une même configuration, avec estimation des victoires de l’attaquant, décompte de toutes les issues et détails repliables par combat, sans pertes moyennes ni garantie de probabilité réelle ; 20 compositions personnelles par empire/univers et recherche avancée paginée dans les messages et rapports. Voir [le suivi et les limites](docs/CADRAGE_OUTILS_STRATEGIQUES.md).

- Parcours : la page Flottes et missions affiche les vaisseaux disponibles et les missions de la colonie active, ainsi que ses demandes d’orbite envoyées/reçues ; les compteurs suivent ce périmètre. Charger une composition sur une autre colonie sélectionne cette colonie sans envoyer de flotte. Rubriques liées par des ancres dans Flottes, Inventaire, Projets et Rapports ; confirmations d’annulation avec dépendances et remboursements estimés ; retours aux rapports conservant catégorie, archives et page. Profil, Préférences et Vacances sont reliés ; l’accueil public présente des captures d’un compte de démonstration. [Suivi du lot](docs/PARCOURS_UI_UX.md).
- Espionnage : risque de détection de base 50 / 40 / 30 % selon le rang des sondes, pondéré par les quantités en mission mixte. La défense gagne un point par tranche complète de cinq sondes présentes, plafonné à vingt ; technologies, score de renseignement et protections conservés. Valeurs validées comme base d’essais Alpha, sans équilibrage confirmé : [règles et exemples](docs/REGLES.md#espionnage).
- Opérations : recherches, vaisseaux et défenses paient une fois `100 × ceil(durée effective / 60)` en électricité. Soit 100 E par minute entamée sur la commande entière ; un lot est arrondi une seule fois. Les accélérations ne recalculent pas le coût ; les annulations remboursent 25 % du paiement conservé, dans la place disponible. Les reconstructions automatiques suivent aussi cette règle.
- Catalogues : interrupteur « Vue condensée » placé avant « Éléments verrouillés » et mémorisé sur cet appareil, sans modifier les actions ni les prérequis. Les cartes affichent jusqu’à cinq ressources sur une ligne, avec libellés abrégés si nécessaire. Le bouton gris désactivé indique les ressources manquantes ; les illustrations des rangs verrouillés sont en noir et blanc au repos et retrouvent leurs couleurs au survol ou au focus clavier. Le wiki propose une recherche utilisable sans JavaScript, un sommaire persistant sur ordinateur, un sélecteur mobile et des liens directs ; avec JavaScript, un chapitre est affiché à la fois hors recherche, avec retour arrière conservé. Les points totaux suivent immédiatement le commandant dans le classement ; sur mobile, les autres catégories sont consultables dans chaque ligne. Les catalogues gardent leur présentation, avec les files, flottes en vol et événements fixes au défilement sur grand écran. Un lien direct vers un élément l’encadre en jaune pendant cinq secondes, sans animation si les mouvements sont réduits.
- Colonies : comparaison par tableau (stocks / capacités, solde net horaire, commandes en file), avec accès direct à chaque colonie et sélection de la ressource sur mobile. Les projets collectifs ont une fiche dédiée à la progression et aux dons, accessible uniquement à leur guilde dans l’univers actif. Le bandeau propose une fiche économique par ressource au survol, au clavier ou au toucher : stock, production brute, production actuelle, consommation brute, consommation actuelle, solde net et délai estimé issus du moteur de production. Les besoins de production distinguent électricité et hydrogène ; les consommations électriques affichent leurs totaux brut et actuel, avec le détail par activité de production repliable, tous rangs cumulés. La ligne « Non stocké / h » est omise. Les fiches électricité et hydrogène récapitulent les usines qui les consomment. Les besoins bruts restent visibles lorsque le stockage est plein ; le gain stocké devient nul. Les pénuries et pauses modifient uniquement les valeurs actuelles. La vue générale garde le compteur et l’accès aux colonies ; leur liste détaillée reste sur Planètes. Bases métal et nano : 10/h chacun avant multiplicateurs ; base nulle pour électricité, antimatière et hydrogène ; production suspendue en vacances.
- Navigation : les raccourcis « Explorer la galaxie » ouvrent la carte interactive ; la liste des systèmes reste accessible. Le guide technologique se trouve dans Développement et les événements restent accessibles depuis Opérations, même sans événement actif.

**L’Alpha est hébergée, selon le propriétaire.** Son URL publique, sa version exécutée et sa configuration serveur restent à confirmer. Les changements intégrés à `dev` ne sont pas déclarés déployés : consulter [l’état du déploiement](docs/DEPLOIEMENT.md).

Le code comprend :

- Comptes, connexion, profils et préférences ; suppression définitive depuis `/profil`, avec mot de passe et confirmation, effacement des identifiants et conversion des empires en empires oubliés (historiques conservés) ; bienvenue et vérification d’adresse pour les nouveaux comptes, renvoi du lien et réinitialisation du mot de passe.
  - Ces trois mails disposent d’une présentation HTML sombre adaptée aux mobiles, avec une version texte de secours. Les modèles communs et leurs contenus sont dans `app/templates/emails/` ; les liens de confirmation (24 h) et de réinitialisation (1 h, usage unique) conservent leur fonctionnement.
  - Les échecs de transport des mails de compte sont retentés trois fois au total pendant la requête. Après échec de confirmation à l’inscription, le compte est conservé et le formulaire de renvoi est proposé. Les états et alertes sont journalisés sans adresse ni jeton ; voir [le diagnostic des emails](docs/DIAGNOSTIC_EMAILS.md).
  - Session expirée après 24 h sans requête au serveur ; case « Se souvenir de moi » facultative pendant 30 jours, annulée à la déconnexion. Un cookie de mémorisation valide permet de retrouver sa connexion après expiration de la session.
  - Mots de passe masqués par défaut, avec un bouton œil pour afficher ou masquer chaque champ.
- Classements joueurs : en-têtes cliquables pour les points totaux (par défaut), bâtiments, recherche, flotte et nombre de planètes ; rang et variation propres à chaque catégorie, tri global croissant ou décroissant avant pagination de 50 lignes, catégorie et sens conservés entre pages et au retour d’un signalement. Les colonnes non interactives des guildes ne portent pas de flèche de tri. Scores et guildes figés jusqu’au prochain minuit, heure de Paris (été/hiver inclus). Les écarts comparent les deux derniers calculs : dans le tableau joueur, (+N) en vert, (−N) en rouge, (= 0) si inchangé, (—) sans comparaison. Les signes et libellés accessibles complètent les couleurs. Les protections de combat restent en temps réel. Le relais existant initialise et actualise les instantanés persistés ; une lecture rattrape aussi une échéance manquée. Nouveaux joueurs et guildes apparaissent au prochain calcul.
- Équilibrage des ressources : première planète à 10 000 de chaque ressource, colonies à 5 000 hors électricité selon le rang, stockage de base à 10 000 ; productions passives M10/E0/A0/H0/N10 par heure à position 5, univers ×1. Les règles s’appliquent directement au déploiement, sans date à configurer, y compris à toute la production en attente depuis le dernier calcul enregistré. Les stocks existants et leurs surplus sont conservés. Voir [les règles et la transition](docs/REGLES.md#ressources-initiales-et-production-passive--décision-du-10-novembre-2026).
- Interface partagée : les événements occupent leur propre zone sous la barre de ressources et défilent avec la page. Carte V2 : numéro, nom et statut des planètes alignés selon l’espace disponible ; les coordonnées des systèmes restent dans les panneaux et ne sont dessinées sur la carte qu’au survol ou au focus clavier d’un résultat de recherche de système.
- Suivi des pages Bâtiments, Recherche, Chantier spatial et Défenses : activité en haut sur mobile et tablette ; flottes en vol masquées sur mobile (jusqu’à 740 px), conservées sur tablette. À partir de 1 251 px, activité et flottes sont regroupées à droite, activité au-dessus. L’ordre de lecture et l’actualisation en direct sont conservés.
- Recherche : une file commune par joueur et par univers, accessible depuis toutes ses colonies, avec 30 commandes maximum. Chaque ajout utilise les ressources et le laboratoire de la colonie choisie ; coûts et durée restent fixés à cet instant. Les recherches déjà actives en parallèle sont conservées ; les suivantes attendent leur fin avant de se succéder.
- Boutons Max : lots de vaisseaux et défenses selon les ressources disponibles, sans plafond fixe de 100 ; sélection des vaisseaux dans les stocks et la limite de flotte de l’univers ; fret par ressource selon les stocks, les autres chargements et la soute restante après réservation du carburant. Les saisies excessives sont réduites automatiquement et revérifiées côté serveur.
- Affichage des quantités : format compact dès 10 000, avec deux décimales et les suffixes k, M, Md et Bn ; valeur exacte au survol (et au focus des abréviations). Les niveaux, coordonnées, rangs, dates, durées et champs de saisie gardent leur format habituel.
- Univers, colonies et ressources ; bâtiments, recherches, rangs et files de production ; progression traitée pendant la déconnexion.
  - Production des usines rééquilibrée le 9 novembre : débits Basiques M360/N180/H720/A90 par heure et par niveau à position de référence, réacteur à 4 H/A et centrale à 1 080 électricité/h/niveau. Prix et temps de base révisés ; courbes et rangs conservés. Voir les [valeurs, simulations et limites](docs/EQUILIBRAGE.md#production-des-batiments). Cette intégration sur branche de travail ne vaut pas déploiement de l’Alpha.
  - Guide technologique sans graphe : un domaine à la fois (économie, recherche, flotte, défense), six cartes initiales puis affichage des suivantes à la demande. Rang basique par défaut, recherche dans tous les domaines, filtres par rang et état. La sélection conserve les filtres ; les prérequis réels et étapes précédentes sont consultables dans la fiche, avec les déblocages regroupés par domaine et la prochaine étape conseillée. Un objectif peut être suivi dans ce navigateur, séparément par participation et colonie. Les accès permanents et les fiches sans JavaScript restent conservés.
  - Sur mobile, les cinq stocks restent visibles sur une seule ligne à droite de « Colonie active » sous forme compacte. Un clic sur la colonie déplie les stocks exacts, débits, rendements et jauges de stockage ; un second clic les replie.
  - Typographie du jeu harmonisée entre les thèmes : petits textes des catalogues et des files agrandis, champs de saisie à 16 px sur mobile et tablette, messages à 13 px. Les grands titres et les stocks mobiles compacts conservent leurs tailles.
  - Bâtiments, recherches, vaisseaux et défenses regroupés par famille avec trois onglets de rang, niveau ou quantité possédée dans l’onglet, indicateurs de verrouillage et d’action disponible, interrupteur local des éléments verrouillés et coûts abrégés en k/M (valeur exacte accessible). Quatre cartes par ligne sur grand écran, avec adaptation aux écrans plus étroits ; illustrations d’origine en couleur, cadrées en 16/9 et affichées bord à bord sans bandes ; boutons d’amélioration, de recherche et de construction alignés en bas des cartes d’une même ligne. Les quantités à construire restent propres à chaque rang (1 à 100).
- Flottes, exploration, transport, colonisation, attaque, défense, orbite et espionnage ; combats persistés, conquête de planètes PNJ et rapports privés. Les rapports présentent un résumé puis les détails : tableaux de combat en vis-à-vis par round et type d’unité (effectifs, pertes et tirs), reconstruction des défenses après le verdict, renseignements d’espionnage en cadres par catégorie et bilan des transports. Les anciens combats sans historique de rounds conservent leur bilan disponible.
- Galaxie V2 : modes Exploration, PNJ (niveaux) et Joueurs, positions visuelles alignées par dizaines consécutives sur le tracé des huit bras allongés et courbés (1–10, 11–20, etc.), sans modifier les coordonnées de jeu et cadrage de lecture sur les systèmes disponibles, légende à cases à cocher et disques remplis jusqu’au cercle extérieur, partagés en quatre quarts fixes : PNJ en haut à gauche (orange en exploration ; bleu niveaux 1–3, violet niveaux 4–6 et fuchsia dès le niveau 7 en mode PNJ), allié en haut à droite (vert), neutre/planète vierge en bas à droite (gris), ennemi en bas à gauche (rouge). Les catégories absentes, masquées ou exclues par le mode restent grises. Une maison au survol indique vos colonies dans le système. Les systèmes inexplorés portent un « ? » jaune centré, désactivable dans la légende ; les planètes découvertes distinguent vos colonies, PNJ, mondes inhabités et joueurs alliés, neutres ou ennemis selon la diplomatie existante. Textes agrandis sur ordinateur et mobile. L’infobulle d’un système sépare coordonnées, état d’exploration, décompte des planètes par relation et niveaux PNJ découverts. La vue du système solaire répartit les planètes sur des orbites espacées et animées à leurs vitesses propres (immobiles si les animations sont réduites), avec numéros permanents et nom de la planète sélectionnée uniquement. Les légendes sont compactes et repliées par défaut ; sur la galaxie mobile, les filtres se déplient en bas et le journal sans mission se réduit à un bouton ; sur mobile, les informations du système se déplient depuis une barre en bas. Elle dispose d’une légende des planètes ; un clic sur leur dessin ouvre directement la vue rapprochée, tandis que la liste conserve les fiches et actions.
- Grades personnalisés de guilde : création et modification par le dirigeant, priorités et permissions déléguées pour l’attribution des grades inférieurs et la diplomatie de guilde. Le transfert du commandement et la dissolution restent réservés au dirigeant ; les alliances personnelles restent indépendantes. Les migrations `Version20261111010000` et `Version20261111173000` réconcilient les historiques existants avant utilisation. Voir le [suivi guildes et alliances](docs/GESTION_GUILDES_ALLIANCES.md).
- Relations personnelles : alliance réciproque, déclaration de rivalité sans guilde et paix acceptée par les deux joueurs. Les liens de guilde restent prioritaires ; la carte et le classement utilisent la relation effective. Un contrat terminé se distingue d’une proposition en attente.
- Diplomatie, guildes, messages, notifications, inventaire de boosts et événements. Le canal « Global », placé avant la guilde, permet aux joueurs de l’univers courant de discuter sans notifications, badges ni compteurs de messages non lus. Les conversations privées et de guilde s’ouvrent sur le dernier message ; les messages envoyés et reçus se distinguent par leur alignement, leur couleur et un libellé. Les deux boutons du menu mobile signalent les nouveautés d’administration, les messages et patchnotes non lus, ainsi que les nouvelles activités du journal jusqu’à la consultation de la vue générale sur cet appareil.
- Le contrôle des traitements signale aussi les voyages sans planification ou en retard au-delà du seuil de leur univers, même si les processus fonctionnent et la file est vide. Sur la fiche d’une flotte échue, un retour forcé avec motif et confirmation programme son retour sans exécuter la mission à destination ; les combats en cours restent protégés. Aucun rattrapage automatique n’est effectué.
- Interfaces Diplomatie, Profil et Inventaire réorganisées pour ordinateur et mobile :
  sans guilde, trois onglets « Créer une guilde », « Rejoindre une guilde » et « Relations commandants » ;
  avec guilde, cinq onglets « Ma guilde », « Membres », « Grades et permissions »,
  « Relations guildes » et « Relations commandants ». Les attributions de grades sont
  dans Membres ; le tag et le commandement restent dans Ma guilde. Dissoudre (commandant)
  ou quitter (membre) sont directement visibles dans Gestion de la guilde. Les projets,
  la galaxie et le classement sont regroupés ; Derniers échanges présente seulement
  les trois messages les plus récents sans les marquer lus. Les membres voient
  leur présence dans cet univers : En ligne en vert (activité depuis moins de cinq minutes),
  Hors ligne en rouge, à l’ouverture ou à l’actualisation de la liste. Le profil distingue
  les identifiants, le mot de passe facultatif et la suppression définitive.
  L’inventaire sépare capacité, boosts, boîtes et récompenses en attente ; un sélecteur
  choisit l’opération et un dialogue permet d’examiner, annuler ou confirmer l’utilisation.
  Sans JavaScript, les formulaires natifs restent disponibles avec les mêmes contrôles serveur.
  La [recette des évolutions A à K](docs/RECETTE_EVOLUTIONS_INTERFACES.md) accompagne la revue sur dev.
- Depuis l’inventaire, un boost peut accélérer une construction, recherche, production ou mission chronométrée active de l’empire ; les opérations en attente, les occupations et les vacances restent exclues.
- Console d’administration avec une navigation principale à cinq entrées, un accueil synthétique et quatre pages de rubrique en cartes (`/admin/rubriques/{group}`) : joueurs et modération, univers et activité, animation, exploitation. Les fiches disposent d’un fil d’Ariane ; une sous-navigation relie les rubriques du groupe et les outils d’un même univers. Les nouveautés des signalements, de la sécurité et des feedbacks sont comptées par administrateur ; ouvrir l’accueil ne les marque pas comme consultées. Les feedbacks archivés sont masqués par défaut.
- Surveillance `/admin/flottes` : missions actives et historique, recherche par joueur ou cible, filtres, actualisation et fiches détaillées. Les transports y sont réunis ; les rapports et consultations sont journalisés. Les nouveaux départs conservent un instantané sans reconstruire les anciennes missions.
- Alertes `/admin/surveillance` et dossiers `/admin/dossiers` : seuils par univers, notes conservées, états ouvert/en examen/clos, recherche et pagination. Les alertes restent des indices à examiner, sans sanction automatique.
- Fiches `/admin/joueurs` : présence estimée depuis moins de cinq minutes, dernière activité et temps de jeu par univers (aujourd’hui, sept jours UTC, total depuis le début du suivi). Aucun historique antérieur n’est inventé ; les onglets concurrents ne multiplient pas le temps.
- Interventions administratives : ajout/retrait de ressources par colonie dans les capacités de stockage, ajustement des échéances et rappel des flottes éligibles. Motif, confirmation, contrôle des doubles envois et journal avant/après ; les combats et occupations restent protégés.
- Assistant `/admin/events/assistant` : bonus temporaires de production, annonces et défis collectifs par univers, avec réglages conservés, aperçu et confirmation. Les objectifs suivent les découvertes, dégâts ou livraisons entre ses propres colonies. L’administration suit les contributions individuelles dans `/admin/defis` ; les joueurs consultent leurs défis depuis `/evenements`. L’encart sous les ressources conserve son titre et son compteur fermé, sur PC et mobile. Son ouverture se superpose à la page sans agrandir la barre ; événements et rotations de l’univers courant présentent objectif, contribution et classement pertinent. Les boutons de suppression retirent les éléments démarrés des listes courantes avec arrêt des effets futurs et conservation des contributions/récompenses ; seuls les éléments jamais démarrés sont réellement supprimés. Les historiques des retraits restent accessibles dans l’administration.
- Rotations hebdomadaires : `/admin/rotations` permet de créer des modèles par univers et de les activer/désactiver, sans changer les occurrences déjà programmées. L’annulation conserve la semaine réservée, les contributions et les récompenses acquises ; une occurrence annulée n’est pas recréée même si les modèles changent. Les paramètres existants restent le défaut tant qu’aucun réglage d’univers n’est enregistré.
- Filtres des catalogues : bâtiments par fonction, recherches par effet, vaisseaux par classe/rôle (une soute ne les classe plus tous en transport), défenses par classe ciblée. Le résumé redondant en tête de la liste des planètes est retiré.
- Inventaire commun de 100 objets, boosts et boîtes confondus, avec attente dans l’ordre d’attribution. Un défi donne au maximum un objet par participant éligible : boost OU boîte. Le contenu annoncé est figé ; le joueur choisit une colonie du même univers. Vacances ou stockage insuffisant refusent l’ouverture sans perte. Les modèles et plafonds de boîtes sont réglables par univers.
- Le [cadrage de l’administration](docs/REFONTE_ADMINISTRATION.md) précise les règles, accès et contrôles. Quatre migrations additives, Version20261109010000 à Version20261109040000, préparent ces fonctions. Leur publication sur Dev ne vaut pas application sur une base existante ni déploiement.
- Quatre pages juridiques publiques provisoires (`/mentions-legales`, `/cgu`, `/confidentialite`, `/cookies`), accessibles depuis le pied de page, avec métadonnées et `noindex` tant qu’elles sont incomplètes ; exclues du sitemap. Leur présence ne vaut pas conformité juridique.
- Aperçu privé des quatre brouillons juridiques dans l’administration (`/admin/juridique`), réservé aux administrateurs ; ces textes ne sont pas encore publiés.
- Wiki connecté et compte à rebours dans les dernières 24 heures avant la fermeture d’un univers.

Les jauges distinguent le stock protégé du stock exposé au pillage : remplissage vert jusqu’au seuil protégé, puis doré, avec un repère fixe. Le seuil actuellement documenté est de 10 % de la capacité. Les règles et leurs décisions restent centralisées dans [REGLES.md](docs/REGLES.md) et [DECISIONS.md](docs/DECISIONS.md).

Chantier en développement : empires oubliés après 30 jours d'inactivité,
mode vacances et occupation de 24 heures avant capture JcE. Le premier lot ajoute
la dernière visite par univers et le calcul des échéances à 14 et 30 jours ; la
migration `Version20261107180000` a été appliquée sur la base locale le 7 novembre
2026 après accord et sauvegarde ; son application reste explicite sur les autres
environnements.
Les visites réussies de pages de jeu comptent comme activité ; les actualisations
automatiques de ressources, requêtes JSON et pages de compte n'y contribuent pas.
Les anciennes participations sans date connue restent non initialisées jusqu'à
leur première visite ; elles sont exclues du traitement tant que cette date est inconnue.
Les avertissements par courriel à J14/J27 sont préparés avec un historique
par univers et période ; leurs deux textes ont été validés le 7 novembre 2026. La commande
`make empires-warn` prévisualise les éligibles sans écriture ni envoi, après
application de la migration `Version20261107220000` (appliquée localement le
7 novembre 2026 après accord et sauvegarde).
Les envois sont désactivés par défaut dans les exemples. En local, le cycle complet
et les avertissements sont activés après accord le 8 novembre 2026 ; la crontab de
`caim` exécute leur traitement quotidien à 00 h, heure de Paris.
La suspension des protections débutant/après pertes dès J14 est préparée derrière
`FORGOTTEN_EMPIRES_ENABLED` : elle est activée localement ; les exemples restent à `0`. Une reprise d'activité rétablit uniquement
les protections encore valables, sans renouveler leurs échéances. Les alliances,
la limite d'écart de score et le stock protégé restent applicables.
Le socle des vacances prépare les périodes et les limites de 48 h minimum,
90 jours maximum et 15 jours entre activations. La migration d'historique
`Version20261107230000` a été appliquée localement après accord et sauvegarde
le 7 novembre 2026. La suspension/reprise économique est raccordée au service
interne : stocks et consommations figés, files reportées sans perte du travail
acquis, reprise au terme réel ou à l'expiration des 90 jours. Les préférences
proposent l'activation avec confirmation, les dates de sortie et le délai entre
deux périodes. Les files affichent leur progression figée et les catalogues
retirent les commandes de construction pendant la pause. Le mode reste masqué
et indisponible tant que `FORGOTTEN_EMPIRES_ENABLED=0`.
Le service exige le retour de toutes les flottes avant les vacances et bloque
les nouveaux départs. Les empires en vacances sont protégés contre attaques,
espionnage et nouveaux stationnements ; un transport refusé revient avec sa
cargaison. Les stations déjà acceptées terminent leur durée normale. Les anciennes
demandes d’orbite en attente après l’arrivée sont renvoyées à l’activation ;
les nouvelles demandes encore en trajet sont revérifiées à l’arrivée.
L'inactivité et les avertissements déduisent les vacances réelles : consulter le
jeu pendant une pause ne remet pas le compteur à zéro, et les courriels reprennent
aux seuils ajustés sans répéter J14. `make empires-preview` liste sans écriture
les empires arrivés à J30, leurs flottes, files, combats et liens sociaux à traiter.
Le traitement se parcourt par lots avec `PREVIEW_ARGS="--limit=100 --after-id=0"`.
La commande n'exécute aucune transformation et ne fournit aucune option d'application.
Le détachement de l'identité est préparé : l'empire oublié n'a plus de joueur
propriétaire, mais garde son identifiant et une référence historique à l'ancien
propriétaire. Une nouvelle inscription démarre sans hériter de ses recherches
ou déblocages. La migration `Version20261108010000` a été appliquée sur la base
locale le 8 novembre 2026 après accord et vérification de la sauvegarde ; elle
reste nécessaire avant utilisation de cette version sur les autres environnements.
Les anciens accès sont refusés et les commandes déjà payées peuvent se terminer
automatiquement. Un service interne prépare désormais la conversion J30 sous
verrou, avec report en cas de combat, rappel des flottes et fin des liens sociaux.
Il conserve un journal unique, transmet la direction des guildes au plus ancien
membre actif ou archive leur historique. La migration `Version20261108020000`
a été appliquée localement le 8 novembre 2026 après accord et vérification de la
sauvegarde, sans conversion ni archivage réel. La commande `make empires-convert`
prévisualise désormais les conversions par lots, sans écriture ni envoi par défaut.
Le mode `CONVERT_ARGS="--apply --all"` permet un passage complet après activation
explicitement autorisée ; il reste refusé avec le drapeau désactivé. Les combats
reportés n'empêchent pas de traiter les lots suivants. Chaque empire est revérifié
sous verrou ; une erreur interrompt le lot et indique le curseur de reprise, tout
en conservant les conversions précédentes. La planification quotidienne est installée localement depuis le 8 novembre 2026.
Le parcours complet est couvert par les tests isolés. Le socle d'occupation
prépare les états, le délai fixe de 24 heures, le comptage des réservations et
l'historique des changements d'occupant. Le démarrage après victoire est raccordé
derrière le drapeau d'activation : flotte maintenue sur place, vaisseau de colonisation
non consommé et place réservée dans les limites de colonisation/conquête.
Le devis prévoit 24 heures de carburant de stationnement ; une conquête non
occupante libère cette réserve pour son retour. Le transfert définitif est codé
dans un service déclenché par les tâches différées, derrière le drapeau d'activation : il attend la fin des
combats et la défaite des forces PNJ restantes ou de retour, puis conserve
bâtiments et stocks, annule les commandes non terminées sans remboursement et
consomme un seul vaisseau de colonisation. Les recherches et déblocages de l'ancien
empire ne sont pas transmis. Les renforts de l'occupant et de ses alliés sont
raccordés, sans remise à zéro du délai. Ils réservent le carburant du temps
restant, avec au minimum une fenêtre de combat, puis récupèrent la part inutilisée.
Les occupations peuvent être contestées sans protection débutant ni limite
d'écart de score, en respectant les alliances. PNJ, occupant et rivaux combattent
dans des camps distincts avec leurs recherches propres. Un occupant victorieux
conserve son délai ; une reprise par conquête recommence 24 heures.
Les cartes classique et interactive proposent désormais la conquête des empires
oubliés, affichent l'échéance des occupations et les liens de renfort/contestation
selon les alliances. Les liens de conquête et de colonisation tiennent compte
des places réellement disponibles, y compris celles réservées par une occupation.
La migration de suivi des renforts `Version20261108040000` a été appliquée localement
après accord et vérification de la sauvegarde le 8 novembre 2026, sans déplacer de
flotte. Le début d'occupation programme son contrôle à l'échéance ; une victoire
conservant l'occupation programme un nouvel examen sans modifier son délai.
Un combat ouvert ou des forces hostiles empêchent toujours la capture.
`make occupations-resume` prévisualise les occupations actives, sans écriture.
Après activation explicitement autorisée, `RESUME_ARGS=--apply` permet de
reprogrammer leurs contrôles après interruption ; les messages périmés et rejoués
ne peuvent pas capturer deux fois. Aucun nouveau processus n'est lancé par ce lot.
La reconstruction autonome concerne uniquement les empires convertis après
l'installation de la migration de compatibilité `Version20261111173000`. Une référence
par planète conserve les modèles, rangs et effectifs à la conversion, retours
compris, hors commandes déjà payées. Après chaque combat, le chantier reprend au
bout de cinq minutes, avec les coûts et durées normaux prélevés sur les stocks
locaux. La réparation partielle des défenses est déduite du manque ; aucune
croissance autonome au-delà de la référence. Les unités finançables les moins
coûteuses en valeur économique sont prioritaires, puis les plus rapides.
Le travail payé est suspendu en combat, sans progression, puis repris sans second
paiement. La reconstruction continue sous occupation ; les forces hostiles doivent
être vaincues avant capture. La capture arrête le mécanisme et annule le travail
restant sans remboursement. Les messages utilisent le relais et le worker existants,
sans nouveau processus. Les PNJ classiques et les empires déjà oubliés sans référence
sont exclus. Cette migration doit être appliquée avant de charger cette version des
services ; aucune synchronisation globale du schéma n'est nécessaire.
Le passage quotidien est fixé à 00 h, heure de Paris, avec adaptation été/hiver.
Après 30 jours effectifs, la conversion devient éligible au premier minuit suivant
(ou au même instant si le seuil tombe déjà à minuit). Le joueur conserve l'accès
jusqu'à la transformation effective ; une visite préalable réinitialise l'inactivité.
Le mode d'envoi quotidien utilise `--send --all` pour traiter tous les lots.
Le traitement J30 et les avertissements sont activés et planifiés localement ;
aucune mise en service distante n'est réalisée.
La [procédure de mise en service](docs/EXPLOITATION.md#empires-oubliés--procédure-de-mise-en-service-préparée-le-8-novembre-2026)
précise les prévisualisations, les services à recharger et le passage quotidien à minuit,
avec les précautions d'activation. Les rapports suivent les fins et remplacements
d'occupation depuis leur historique ; le verdict et les récompenses distinguent
le camp du joueur du camp PNJ.
Sans activation, la capture immédiate actuelle est
conservée. La migration `Version20261108030000` a été appliquée localement le
8 novembre 2026 après accord et vérification de la sauvegarde, sans créer
d'occupation. Les règles à développer
et les lots sont consignés dans les [décisions](docs/DECISIONS.md#empires-oubliés-vacances-et-occupation-jce--décisions-du-7-novembre-2026)
et la [feuille de route](docs/FEUILLE_DE_ROUTE.md#empires-oubliés--chantier-validé-le-7-novembre-2026).

## Technologies et versions

Les contraintes déclarées, les versions verrouillées et les observations d’exécution sont distinctes.

| Élément | Déclaration ou verrouillage vérifié |
| --- | --- |
| PHP / Composer | `composer.json` annonce PHP `>=8.2`, mais DoctrineBundle verrouillé exige `^8.4` et PHPUnit `>=8.4.1`. Les images fournies utilisent PHP 8.4 et Composer 2. |
| Symfony | Contrainte `7.4.*`, FrameworkBundle verrouillé en `7.4.21`. |
| Doctrine / Twig | ORM `3.7.5`, DoctrineBundle `3.3.2`, Twig `3.30.0` dans `composer.lock`. |
| Interface | Twig, Stimulus `3.2.2`, Webpack Encore `7.2.0` et Babel `8.0.7` verrouillés. |
| Node.js | La CI et l’étape de compilation de l’image utilisent la branche 24. Pour cette branche, les contraintes Encore/Babel demandent au moins 24.11.0. |
| Services Docker | Images MariaDB `11.8`, RabbitMQ `4.3-management-alpine`, Mercure `v0.18`, avec références de digest dans Compose. Le proxy Traefik local est externe au projet. |
| Tests | PHPUnit `13.4.5`, Playwright `1.64.1`, axe et tests Python des scripts. |

Sources : [Composer](app/composer.json), [verrou PHP](app/composer.lock), [npm](app/package.json), [verrou JavaScript](app/package-lock.json), [Docker de développement](docker/dev/Dockerfile), [Docker de production](docker/prod/Dockerfile) et [CI](.github/workflows/ci.yml).

Observation locale du 7 novembre 2026 : PHP **8.4.26**, Symfony **7.4.21**, Composer **2.10.3**, Python **3.13.5**, plugin Compose **5.6.0** ; l’image Node disponible contient **24.21.0 / npm 11.19.0**. Le poste hôte contient aussi Node **20.19.2 / npm 9.2.0** : une compilation réussie sur ce poste ne le rend pas conforme aux contraintes actuelles d’Encore/Babel. Ces observations ne décrivent pas les versions de l’Alpha hébergée.

## Prérequis locaux

Le parcours documenté vise Linux ou Debian sous WSL, avec Git, un accès au dépôt et aux registres de dépendances, Docker accessible, le plugin `docker compose`, GNU Make, Bash, Python 3 et les utilitaires GNU utilisés par les scripts, dont `flock`. Utiliser Node **24.11 ou plus récent dans la branche 24**, avec npm. PHP et Composer sont fournis par le conteneur ; ils ne sont pas requis sur l’hôte pour les commandes Make ci-dessous.

Le Compose local attend deux réseaux externes, `traefik_network` et `mail_network`. Il ne crée ni Traefik ni le serveur SMTP : préparer un proxy Traefik avec fournisseur Docker et point d’entrée `web`, ainsi qu’un SMTP de développement joignable. L’exemple mail vise `mailhog:1025` ; adapter `MAILER_DSN` à votre installation. `make doctor` contrôle la présence des réseaux, pas le fonctionnement du proxy ou du SMTP.

## Installation d’un nouveau développeur

Exécuter cette séquence dans un **nouveau clone et une nouvelle base locale** :

```bash
git clone https://github.com/c41m07/genesis-reborn.git
cd genesis-reborn
git switch dev
make configure
```

`make configure` crée `.env` depuis [.env.example](.env.example) s’il manque et génère les secrets privés dans `.env.local`. Il conserve les valeurs existantes et ajoute une clé Mercure si nécessaire. Vérifier ensuite la configuration privée, le SMTP et, si nécessaire, `LOCAL_UID` / `LOCAL_GID` avant de construire l’image.

```bash
make doctor
make validate
make build
make install
make assets
docker compose --env-file .env.local up -d --wait app database rabbitmq mercure
make migrate
make import
make start
```

La première commande de démarrage prépare l’infrastructure et l’application. Les migrations et l’import précèdent le lancement des consommateurs : c’est aussi l’ordre utilisé en CI. `make start` démarre ensuite tous les services ; il n’applique aucune migration et ne charge aucune donnée automatiquement.

Ouvrir [l’adresse locale du jeu](http://genesis-reborn.localhost), puis créer un compte et suivre le lien reçu dans le SMTP de développement. Le proxy doit être configuré pour cette adresse. La base écoute sur `127.0.0.1:3309` par défaut depuis l’hôte ; les conteneurs utilisent `database:3306`.

## Configuration et variables d’environnement

Les fichiers `.env`, `.env.local`, `.env.prod` et les secrets sont exclus par [.gitignore](.gitignore) ; seuls les modèles sont versionnés. Ne pas publier leur contenu. Le modèle serveur est [.env.prod.example](.env.prod.example) ; sa préparation est décrite dans le guide de déploiement.

| Variables | Usage |
| --- | --- |
| `APP_ENV`, `APP_DEBUG`, `APP_SECRET` | Environnement Symfony, débogage et secret applicatif. |
| `DATABASE_URL`, `DB_PASSWORD`, `DB_ROOT_PASSWORD`, `DB_HOST_PORT` | Connexion MariaDB, secrets du service local et port d’accès depuis l’hôte. |
| `MESSENGER_TRANSPORT_DSN`, `RABBITMQ_PASSWORD` | Transport des traitements asynchrones. |
| `MAILER_DSN`, `MAILER_FROM`, `DEFAULT_URI` | Transport SMTP, expéditeur et URL absolue des liens, notamment ceux des mails. |
| `EMPIRE_INACTIVITY_WARNINGS_ENABLED` | `0` par défaut ; interdit les envois d'inactivité, même avec `--send`. Textes révisés validés et valeur `1` autorisée localement le 8 novembre 2026. Voir [EXPLOITATION.md](docs/EXPLOITATION.md#avertissements-dinactivité-j14j27). |
| `FORGOTTEN_EMPIRES_ENABLED` | `0` par défaut ; active le cycle d'inactivité, les vacances, la conversion PNJ et les occupations JcE. Valeur `1` autorisée localement le 8 novembre 2026. |
| `MERCURE_URL`, `MERCURE_PUBLIC_URL` | Adresse interne de publication et adresse accessible au navigateur. |
| `MERCURE_JWT_SECRET`, `MERCURE_JWT_ISSUER` | Signature et émetteur des jetons Mercure. |
| `LOCAL_UID`, `LOCAL_GID` | Identifiants utilisés lors de la construction et par les processus locaux ; défaut 1000. |
| `OPS_ALERT_*` | Activation, destination privée et seuils des alertes ; envois désactivés par défaut. |

Les variables de l’image et du proxy de production (`APP_IMAGE`, `APP_DOMAIN`, `TRAEFIK_CERTRESOLVER`, `SYMFONY_TRUSTED_PROXIES`) sont documentées dans [DEPLOIEMENT.md](docs/DEPLOIEMENT.md). Les paramètres de sauvegarde sont maintenus dans [SAUVEGARDES.md](docs/SAUVEGARDES.md). Aucun fournisseur SMTP de production ni destinataire privé n’est imposé par ce README.

## Démarrage et travail quotidien

Après l’initialisation : `make start`. Utiliser `make status`, `make logs`, `make stop` pour suivre ou arrêter les services. `make stop` conserve les conteneurs et les données ; `make down` retire les conteneurs du projet et conserve les volumes de données.

| Commande | Fonction |
| --- | --- |
| `make help` | Liste des cibles réellement disponibles dans le [Makefile](Makefile). |
| `make console CMD=about` | Console Symfony dans l’application ; remplacer `about` par la commande voulue. |
| `make shell` | Terminal dans le conteneur applicatif. |
| `make assets` / `cd app && npm run build` | Compilation de production dans `app/public/build/` ; Make reste lancé à la racine. |
| `make watch` / `cd app && npm run watch` | Recompilation de développement ; actualiser ensuite le navigateur. |
| `cd app && npm run dev` | Compilation de développement ponctuelle ; `npm run` liste les scripts déclarés. |

Après modification d’un service consommé par les processus persistants locaux, les redémarrer :

```bash
docker compose --env-file .env.local restart worker relay notifier
```

Compiler avant les tests navigateur : la compilation remplace les fichiers de `app/public/build/`. Les dépendances et résultats générés (`app/vendor/`, `app/node_modules/`, `app/var/`, `app/public/build/`, `app/test-results/`, `app/playwright-report/`, `output/` et les rapports de couverture) sont ignorés par Git. Les captures conservées comme documentation restent dans `docs/ui-ux/captures/`.

## Base de données et données de développement

[Les migrations](app/migrations) décrivent l’évolution du schéma. `make migrate` les applique à la base configurée : utiliser cette commande lors de l’initialisation locale ou après une évolution autorisée du schéma. Sauvegarder une base contenant des données avant une migration ; la procédure serveur est distincte.

`make import` importe explicitement les cartes et univers de référence. `make seed-demo` est facultatif et réservé à `APP_ENV=dev` : il crée un compte de démonstration connu, ou conserve le compte existant et son mot de passe. Il ne remplace pas les migrations ni l’import. Les jeux de données de test sont sous [tests/Fixtures](app/tests/Fixtures) et dans les scénarios.

[Le guide des sauvegardes](docs/SAUVEGARDES.md) décrit `make backup`, `make restore`, la conservation de 14 jours par défaut, les contrôles d’échec et la restauration isolée. `make backup-status` consulte l’état sans créer de sauvegarde. `make test-backup` vérifie les comptes et la progression sur deux instances jetables distinctes.

## Tests et qualité

Pendant le développement, lancer les contrôles locaux adaptés au changement et `git diff --check` avant commit ou push. Une branche de travail peut être poussée sans exécuter toute la suite ; signaler les échecs et vérifications restant à faire.

La CI complète se lance sur les PR vers `dev` et `alpha`, y compris à chaque correction. Attendre sa réussite sur la dernière version avant fusion. Les push seuls sur une branche de travail ou sur `dev` ne relancent pas la CI. Les push sur `alpha` (aussi branche par défaut actuellement) conservent la validation avant publication de l’image ; le lancement manuel reste disponible. Les [règles GitHub à vérifier pour protéger `dev`](docs/DEPLOIEMENT.md#protection-de-dev) sont distinctes du workflow.

Pour reproduire les six contrôles localement, préparer Chromium depuis `app/` avec `npx playwright install --with-deps chromium`, puis lancer depuis la racine :

```bash
make validate
make lint
make test
make assets
BROWSER_PERSISTENCE=1 make test-browser
git diff --check
```

- `make assets` compile les assets de production en lançant `npm run build` depuis `app/`.
- `make validate` valide Compose sans afficher les secrets.
- `make lint` contrôle PHP, Twig, YAML, le conteneur Symfony et le schéma Doctrine de la base configurée.
- `make test` utilise `database-test` et `genesis_v2_test` en mémoire, un transport Messenger en mémoire et un transport mail nul ; il lance aussi les tests Python des sauvegardes. Lancer une seule suite PHP à la fois sur cette base. PHPUnit est limité à 512 Mo pour ce seul processus, sans changer PHP pour le jeu.
- `make test-browser` prépare un projet Docker et une base jetables propres à l’exécution. `BROWSER_PERSISTENCE=1` ajoute la persistance temporaire nécessaire aux essais de redémarrage complet ; les volumes sont retirés en fin de test. Les mails et alertes externes y sont neutralisés. Le script npm `test:browser` seul ne prépare pas cet environnement.
- `make verify-install` prépare une installation temporaire, réinstalle les dépendances et vérifie le redémarrage. Son existence ne vaut pas preuve d’une exécution récente ; consulter le suivi pour les commandes réellement exécutées.

Preuve de référence sur `dev` après le lot K, le 7 novembre 2026 : [PR #13](https://github.com/c41m07/genesis-reborn/pull/13), six contrôles locaux réussis, **229 tests PHP**, **13 tests Python**, **97 scénarios Playwright** (95 parcours et 2 tests JavaScript déterministes). La suite complète locale a réussi en deux lots ; une reprise ciblée finale de trois cas a aussi réussi. Les **11 contrôles GitHub** ont réussi et la restauration CI a vérifié 38 tables, les comptes et la progression. Les résultats détaillés et les livraisons suivantes sont dans [le suivi Alpha](docs/audits/SUIVI_ALPHA.md).

La réception SMTP réelle, la configuration et les parcours sur l’Alpha, la planification des sauvegardes et le stockage distant restent à vérifier sur serveur. Les essais perturbateurs utilisent les instances locales isolées. Les mesures d’équilibrage, les tests de charge et la revue d’accessibilité exhaustive restent ouverts ; les contrôles ciblés avec axe ne constituent pas une certification RGAA.

## Organisation des fichiers

```text
app/       sources, configuration Symfony, templates, assets, public,
           migrations, tests, manifestes/verrous et outils applicatifs
docker/    Dockerfiles, Apache et définitions Compose
docs/      règles, décisions, exploitation, conception et historique
scripts/   outils du dépôt, sauvegarde, déploiement et vérification
var/       archives opérationnelles privées (ignorées)
.github/   CI et déploiement
```

Les quatre fichiers Compose de base à la racine sont des liens vers `docker/`.
Les commandes existantes continuent de fonctionner depuis la racine ; les chemins
Compose sont relatifs à celle-ci. Pour viser directement `docker/compose.yaml`,
ajouter `--project-directory .`. Projets, réseaux et volumes gardent leurs noms.

Les modèles et secrets d’environnement restent à la racine pour Compose et Symfony.
`make configure` prépare les liens locaux `app/.env` et `app/.env.local` ; Compose
monte ces fichiers en lecture seule. Le runtime est dans `app/var/`, les sauvegardes
dans `var/backups/`. Les ressources de conception sont sous `docs/artwork/`,
les images servies sous `app/public/`. Le wiki lit les sources uniques de `docs/`,
montées en lecture seule en développement et copiées dans `/docs` en production.

## Architecture générale

- `app/src/Controller/`, [templates/](app/templates) et [assets/](app/assets) : requêtes HTTP, rendu Twig et comportements Stimulus ; [public/](app/public) sert les fichiers accessibles au navigateur.
- `app/src/Service/`, `app/src/Entity/`, `app/src/Repository/` et `app/src/Manager/` : logique de jeu, données Doctrine et transactions.
- [config/game/](app/config/game) et [config/universes/](app/config/universes) : catalogues, règles configurées et données d’univers ; les décisions produit restent dans la documentation de référence.
- [compose.yaml](docker/compose.yaml), point d’entrée vers [docker/compose.yaml](docker/compose.yaml) : application PHP/Apache, MariaDB, RabbitMQ, Mercure, consommateur `worker`, relais `relay` et notificateur `notifier` ; Traefik et SMTP locaux sont fournis séparément.
- Le relais de gameplay actualise aussi les classements chaque jour à 00 h, heure de Paris, dans la table `leaderboard_snapshot`. La migration `Version20261110010000` crée cette table sans modifier les données de jeu existantes. Après migration et déploiement, redémarrer le relais pour charger ce traitement ; aucune nouvelle crontab n’est nécessaire. Le premier calcul fournit la référence, sans évolution. Si le relais est arrêté à minuit, sa reprise ou la première lecture calcule l’état alors disponible, avec son heure réelle : les jours manqués ne sont pas reconstitués.
- Les événements persistés dans l’outbox sont transmis aux traitements asynchrones et aux notifications ; les files et états serveur permettent la progression sans navigateur ouvert.
- [scripts/](scripts) : configuration, exploitation et validations isolées ; [tests/](app/tests) : tests PHP, scripts et parcours Playwright.

## Accueil public et référencement

Les visiteurs disposent d’un accueil public sur `/` ; les joueurs connectés
conservent l’accès à leur colonie. Titres, descriptions, URL canoniques, Open Graph,
robots.txt et sitemap.xml sont générés côté serveur. `DEFAULT_URI` doit correspondre
à l’origine HTTPS réelle ; voir [SEO et Search Console](docs/REFERENCEMENT.md).

## Documentation de référence

[docs/README.md](docs/README.md) oriente vers une référence par sujet :
règles et décisions, roadmap, architecture, exploitation, déploiement/sauvegardes,
SEO, interface et données d’équilibrage. Les anciens bilans et leur contexte
restent accessibles depuis [l’index des archives](docs/archives/README.md).

Le [guide technique du worker et du notifier](docs/WORKER_NOTIFIER.md) détaille
la chaîne outbox → RabbitMQ → traitements métier → Mercure, les transactions,
les contrats de messages, les abonnements privés et les scénarios de panne.

Le wiki utilise les sources uniques `docs/wiki.json`, `docs/REGLES.md` et
`docs/DECISIONS.md`. Il présente un manuel du joueur en un document avec sommaire,
premiers pas, règles et calculs expliqués par des formules et exemples dépliables,
accessibles au clavier et sans JavaScript. Les PDF d’origine et leurs extractions textuelles sont
conservés ; les libellés du catalogue restent dans `docs/CATALOGUE_LABELS.csv`.

## Contribution et conventions

Lire [AGENTS.md](AGENTS.md), examiner l’état Git et préserver les changements présents. Partir du dernier `dev`, créer une branche `feature/<chantier>`, puis proposer une PR vers `dev`. Utiliser des commits courts et explicites en français, des noms de variables en anglais et les conventions du code existant.

Mettre à jour le README dans le même lot lorsque changent l’architecture, les dépendances, les commandes ou les fonctionnalités documentées. Ne pas changer les règles ou l’équilibrage sans décision explicite, et conserver les logos et SVG d’identité. Distinguer dans chaque livraison ce qui a été vérifié localement, validé en CI, intégré à `dev` et effectivement déployé.


## Transports entre joueurs — décision validée le 7 novembre 2026

Les transports peuvent viser une colonie connue du même univers et de la même galaxie, appartenant au joueur ou à un destinataire qui les autorise. Le réglage de compte accepte tout le monde, les alliés uniquement (défaut) ou personne ; les propres colonies restent autorisées. Les alliances suivent les relations existantes (guilde ou contrat accepté, hors relation ennemie).

L’expéditeur fournit ressources, vaisseaux et carburant aller-retour. L’autorisation est contrôlée au départ et à l’arrivée. Un changement de propriétaire ou un refus à l’arrivée entraîne le retour de la cargaison. La livraison respecte chaque capacité de stockage ; le surplus revient, y compris entre les propres colonies. Au retour, le dépôt reste soumis au stockage de la colonie de départ : un éventuel dépassement y est perdu et indiqué dans le rapport.

Les dons entre joueurs ne donnent aucun boost et ne font progresser aucun objectif. La protection débutant reste inchangée. Aucun plafond de dons, taxe ou sanction automatique sur une adresse IP n’est ajouté. Les deux participants disposent du rapport économique ; l’administration dispose de l’historique paginé des transports pour une vérification humaine.

Installation ou mise à jour : la migration `Version20261107150000` crée l’historique des transports ; son application sur chaque base active nécessite une validation explicite. Elle a été appliquée à la base locale le 7 novembre 2026 après sauvegarde vérifiée. Les anciens transports déjà en vol restent limités au propriétaire initial (l’expéditeur).

## Pages juridiques provisoires

Le nom public de l’éditeur et du responsable des données se renseigne avec `LEGAL_PUBLISHER_NAME` dans `.env.local` (local) ou `.env.prod` (serveur), hors Git. En son absence, les pages restent disponibles et signalent l’identité manquante. Adresse et téléphone de publication restent à compléter ; ne pas annoncer une conformité juridique sur la seule présence de ces pages.

## Noms Docker locaux

Conteneurs : `genesis-web`, `genesis-worker`, `genesis-relay`, `genesis-notifications`, `genesis-database`, `genesis-database-test`, `genesis-rabbitmq`, `genesis-mercure`. Image applicative : `local/genesis-web:dev`. Les identifiants des projets Compose et les volumes existants sont conservés.
Le fichier local `compose.override.yaml`, ignoré par Git, applique ces noms avec les commandes habituelles (`make start`, `make status`). Il reste propre au poste de développement ; un clone neuf utilise les noms générés par Compose. Les tests et installations isolés utilisent leurs fichiers `-f` explicites et conservent leurs propres noms.

### Gestion des guildes et projets collectifs

Fonctionnalité intégrée dans `dev` par la [PR #36](https://github.com/c41m07/genesis-reborn/pull/36), sans déploiement ni nouvelle migration de la base habituelle. Consulter le [guide d’utilisation et d’administration](docs/GUIDE_GUILDES_ALLIANCES.md) pour les grades, les trois traités, les alliances personnelles et les projets.

Les traités officiels distinguent alliance complète, non-agression et défense mutuelle, avec permissions séparées, signatures des deux guildes, paix bilatérale et historique. Les effets et l’état de validation sont détaillés dans [le suivi guildes/alliances](docs/GESTION_GUILDES_ALLIANCES.md). Cette extension nécessite la migration `Version20261111020000`, préparée sans application autorisée à une base existante. Les projets collectifs ajoutent des paliers financés par les membres, des conditions de contribution administrables et une récompense de boost unique. Leur migration `Version20261111150933` est préparée ; aucun modèle n’est actif par défaut. Le suivi détaille les validations locales et les migrations restant à appliquer aux bases existantes avec autorisation.

La migration de compatibilité `Version20261111173000` réconcilie les bases ayant enregistré `Version20261111010000` pour les grades ou pour la reconstruction. Elle conserve les données et l’historique ; son application sur une base existante exige une sauvegarde et une autorisation distinctes. Voir [le suivi des guildes](docs/GESTION_GUILDES_ALLIANCES.md).

Évolutions en cours : [lots, décisions et points ouverts](docs/EVOLUTIONS_INTERFACES_FLOTTES.md). Le journal des missions est retiré de Flotte ; suivi des vols, rapports et traces administratives restent disponibles. Les prérequis des cartes ouvrent la bonne page et le bon rang dans la colonie active. Les sections « Accès associés à ce rang » sont retirées des cartes et fiches, sans changer les déblocages. Le signalement des messages privés et de guilde utilise un triangle jaune accessible au clavier et au toucher.


Carte V2 : le cercle extérieur est vert dans les systèmes possédés. La fiche
planète propose un retour à son système, au clavier et au toucher ; sa croix
garde le retour galaxie. Les sondes en trajet sont réservées à leur propriétaire.
Pour une flotte tierce déjà visible, seuls le nombre total de vaisseaux et les
points remplacent sa composition ; les types et rangs restent accessibles au
propriétaire. Le calcul reprend les coûts de vaisseaux de l’univers, sans défense
fixe, avec division par 100 après totalisation. Ces restrictions sont appliquées
avant l’envoi des données au navigateur.

Détails privés des colonies et flottes : la page Planètes présente les vaisseaux
disponibles par rang et les stocks calculés de chaque colonie ; le suivi des vols
présente la composition et le fret de ses propres flottes. Ces panneaux s’ouvrent
au survol et restent utilisables au clavier et au toucher. Leur lecture ne
matérialise pas les stocks. La vue générale et le sélecteur latéral partagent
l’ordre d’acquisition par le propriétaire actuel. Les anciennes dates inconnues
restent nulles, avec un ordre historique signalé comme approximatif.
Le journal commence à la première ouverture après mise à jour. Les événements
validés des absences suivantes restent visibles jusqu’au bouton de consultation ;
les actualisations automatiques ne changent pas cet état. Au-delà de 50 événements,
les suivants sont proposés après consultation, sans effacer l’historique.
La migration additive Version20261112010000 est appliquée sur la seule base locale
après accord et sauvegarde vérifiée. Les autres installations nécessitent leur
propre sauvegarde vérifiée et autorisation avant migration, puis le redémarrage
des consommateurs. Aucun déploiement Alpha de ce lot n’est annoncé.

Destinations des flottes : trois modes sont proposés, Mes planètes et alliés
par défaut, Ennemis (dont PNJ connus), et Destination personnalisée. Les joueurs
neutres restent dans le mode personnalisé. La recherche de pseudonyme propose
au plus 20 planètes déjà découvertes de l’univers actif, compatibles avec la
mission. Les suggestions se sélectionnent au clavier et au toucher ; les
coordonnées restent disponibles pour les destinations personnalisées, notamment
l’exploration d’un système inconnu. Les liens de la carte conservent leur cible.
Les attaques classiques des PNJ sont possibles sans capture ni nouveau butin PNJ.
Conquête JcE conserve ses règles de capture et d’occupation existantes.
Aucune migration, dépendance ou activation globale supplémentaire pour ce lot D,
qui n’est pas encore publié ni déployé sur Alpha.

### Rapports personnels et attribution administrative

Le centre de rapports mélange combats, renseignements et transports du plus récent
au plus ancien, avant pagination (50 par page). Les filtres et le choix
Rapports actifs / Archives personnelles restent conservés en navigation.
L’archivage individuel ou par sélection est personnel et restaurable ; les preuves
et l’accès autorisé aux rapports restent conservés.

La fiche administrative d’un joueur permet d’attribuer des vaisseaux au stock
au sol d’une colonie choisie : type, rang, quantité entière, motif et confirmation.
L’attribution est contrôlée côté serveur, journalisée et protégée contre le rejeu.
La migration additive Version20261112020000 mémorise seulement les choix d’archive ;
sur base existante, son application exige accord explicite et sauvegarde vérifiée.

### Tags de guilde et signalements de joueurs

La création d’une guilde exige un tag de 2 à 5 lettres/chiffres, normalisé en
majuscules et unique dans l’univers. Le commandant choisit ou met à jour ce tag ;
les anciennes guildes restent sans tag jusqu’à son choix. Le classement affiche
[TAG] Commandant ; aucun autre écran ne préfixe le pseudonyme.

Dans Actions du classement, le triangle de signalement ouvre les quatre motifs
validés avec un commentaire facultatif (1000 caractères maximum). Le contexte
serveur joint univers, cible/pseudonyme, auteur/date et catégorie/rang consultés.
Le signalement reste privé à la modération ; une double soumission du formulaire
ne crée pas de doublon. La rubrique Modération distingue messages et joueurs,
conserve les preuves et journalise consultation/traitement, sans sanction automatique.

Migration additive Version20261112030000 : tag nullable pour l’historique,
unicité univers/tag et table player_report. L’application à une base existante
nécessite accord explicite et sauvegarde vérifiée.

### Rappel des flottes

Dans le suivi des vols, le nom de mission ouvre composition et fret ; le rappel est à droite de la barre. Les libellés de propriété et de mission complètent les distinctions visuelles.

Depuis ses flottes en vol, le joueur peut préparer puis confirmer le rappel
d’une mission à l’aller, en orbite ou en attente d’accord d’orbite. L’aperçu
présente la colonie de retour, la durée et le carburant. Les retours, combats,
résolutions d’arrivée et occupations sont exclus. Le fret et les vaisseaux
restent à bord ; le dépôt conserve les règles habituelles de stockage.

Le retour tient compte du chemin parcouru et des boosts. Si l’origine est
perdue, une autre colonie du même univers doit être choisie explicitement :
seul le carburant réservé encore disponible sert au repli, sans prendre dans
le fret d’hydrogène ni dans les stocks des planètes. Si le calcul est impossible
ou le carburant insuffisant, le refus laisse la mission inchangée. Les anciennes
flottes ne sont rappelables que lorsque leurs données suffisent à un calcul fiable.

Migration additive Version20261112040000 : suivi nullable du trajet et colonie
de retour distincte de l’origine historique. Toute application sur base existante
nécessite accord explicite et sauvegarde vérifiée ; aucun déploiement Alpha.

### Détection des attaques et confidentialité des déplacements

Analyse tactique additionne les niveaux de ses trois rangs pour le seul préavis
d’attaque : min(5 + 5 × niveaux, 120 minutes, 50 % du trajet). L’espionnage et
la détection des sondes à l’arrivée conservent leurs effets pondérés actuels.
Les plafonds du catalogue de l’univers sont pris en compte pour les informations
de progression ; à 5/5/5, le préavis théorique atteint 80 minutes.

Les attaques détectées sont affichées sur les pages de jeu avec cible, commandant,
vaisseaux, points et compte à rebours. Une origine non découverte reste masquée.
La surveillance se met à jour sans rechargement, avec horloge serveur, et conserve
le focus et la sélection de carte. Sans JavaScript, actualiser la page.
Les sondes en trajet restent visibles seulement par leur propriétaire.
Les autres mouvements restent réservés au propriétaire, au destinataire et aux
alliés, dans les limites des découvertes, avec contrôles avant sérialisation.


### Demandes d’orbite
Une nouvelle mission Orbite envoie sa demande dès le départ. Les alliés sont
acceptés automatiquement ; un joueur neutre répond depuis Flottes avant
l’arrivée. La page affiche les demandes envoyées/reçues et leur échéance
(heure de Paris et compte à rebours). Le refus ou le retrait rappelle la flotte
depuis la position atteinte ; sans accord valide à l’arrivée, elle rentre.
Les planètes ennemies, PNJ et sans propriétaire ne peuvent pas être ciblées.
Une orbite acceptée participe à la défense de l’hôte. Les missions déjà parties
avant cette évolution gardent leur ancien processus pour ce voyage.


### Abandon d’une colonie
Depuis Planètes, « Préparer l’abandon » ouvre un bilan avant toute action.
La confirmation exige de retaper son nom. La planète redevient vierge ; bâtiments,
ressources, défenses et vaisseaux présents sont perdus, et les files sont annulées
sans remboursement. Les recherches acquises et historiques restent conservés.
La dernière planète de l’univers ne peut pas être abandonnée, même s’il existe
des colonies dans un autre univers. Les flottes actives liées, orbites, attaques,
combats et occupations bloquent l’abandon. Une commande dépendante d’une autre
colonie le bloque aussi ; les commandes à gérer sont indiquées dans le bilan.
Si les actifs ou commandes changent après le bilan, il faut le relire.
Les stocks peuvent évoluer jusqu’à la confirmation. Aucune migration nécessaire.

Après abandon, une recolonisation par le même joueur sur cette planète dans le
même univers ne donne aucun nouveau kit de ressources. Il doit prévoir le
ravitaillement ; un nouveau propriétaire conserve le kit normal.
Le bandeau tronque visuellement les noms très longs ; le nom complet reste
accessible dans le sélecteur de colonie, le texte accessible et l’infobulle.


### Retouches de présentation après recette

Les noms des colonies de la vue générale et de Planètes ouvrent une bulle
flottante avec vaisseaux et ressources. Les encadrés des flottes ouvrent leur
composition et leur fret. Survol, focus clavier et toucher sont pris en charge ;
La bulle, sans croix, se ferme quand le pointeur quitte le nom ou l’encadré et la bulle,
quand le focus sort, avec Échap ou par toucher extérieur, sans déplacer les cartes.
La navigation vers la colonie reste disponible depuis la bulle de l’aperçu
et les commandes existantes de Planètes.

Les listes de rapports alignent sélection, résumé et action ; les noms des
cases sont conservés pour les lecteurs d’écran. Le paragraphe explicatif des
archives et la phrase technique sur le calcul du préavis en Recherche sont
retirés des pages concernées. Les règles d’archive et de détection ne changent pas.

- [Étude du coût électrique des opérations](docs/ETUDE_COUT_ELECTRIQUE.md) : coût de 100 E par minute entamée validé, hypothèses et effets des lots, rangs et vitesses.
