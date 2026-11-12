# Vérification manuelle des évolutions A à K

Cette liste sert à notre revue ensemble sur dev. Cocher après essai et noter la page, le résultat attendu, le résultat observé et une capture si utile. Les contrôles automatisés ne remplacent pas cette validation de confort d’utilisation.

## Préparer les essais

- [ ] Vérifier que la version réellement affichée correspond à la dernière fusion de dev ; actualiser les pages et les ressources du navigateur.
- [ ] Utiliser des comptes de test dans le même univers : propriétaire, allié, neutre et ennemi ; prévoir un second univers pour contrôler l’isolation.
- [ ] Faire les essais sur ordinateur, tablette et téléphone, en thème sombre puis clair.
- [ ] Parcourir les commandes au clavier : focus visible, noms compréhensibles, ouverture et fermeture des détails, retour du focus après une annulation.

## Vue générale et colonies

- [ ] La mention de mise à jour à minuit a disparu de la vue générale ; le classement continue son calcul quotidien.
- [ ] L’ordre des colonies est identique dans la vue générale et le menu latéral. Les acquisitions nouvelles suivent leur date réelle ; les anciennes dates inconnues ne sont pas inventées.
- [ ] Après une première ouverture servant de départ au suivi, quitter la vue générale, provoquer un événement puis revenir : seuls les événements de l’absence apparaissent. Vérifier la consultation explicite et la conservation des notifications historiques.
- [ ] Survoler le nom d’une colonie puis un encadré de flotte : une bulle flottante montre ressources/vaisseaux ou composition/fret, sans agrandir la carte. Vérifier aussi focus clavier, toucher, fermeture et Échap.

## Galaxie et confidentialité

- [ ] Le cercle extérieur d’un système possédé est vert ; les autres informations restent lisibles.
- [ ] Depuis une planète rapprochée, le retour conduit au système de cette planète, même si la navigation venait d’un autre système.
- [ ] Une sonde en trajet apparaît pour son propriétaire et reste cachée pour sa cible.
- [ ] Une flotte étrangère normalement visible expose seulement son nombre total de vaisseaux et ses points. Sa composition et son fret restent privés.
- [ ] Aucune planète ou flotte inconnue ne devient accessible en changeant un identifiant dans une adresse ou une requête.
- [ ] Un déplacement non offensif respecte propriétaire, destinataire, alliés et découvertes ; tester aussi un joueur sans droit.

## Préparer et suivre une flotte

- [ ] Le sélecteur démarre sur « Mes planètes et alliés » ; les trois modes, PNJ figurent dans « Ennemis » ; les joueurs neutres passent par « Destination personnalisée ».
- [ ] La saisie personnalisée et les suggestions ne renvoient que des destinations autorisées et découvertes dans l’univers actif.
- [ ] Sur un PNJ découvert, « Attaque » combat sans capture ; « Conquête JcE » respecte le vaisseau colonie survivant, la place disponible et l’occupation existante.
- [ ] Le journal de mission a disparu de la page Flottes ; rapports et historiques restent accessibles.
- [ ] Tester un rappel à l’aller, après un boost et en orbite : retour cohérent, carburant et fret conservés selon les règles validées, aucun double dépôt.
- [ ] Vérifier les refus : retour déjà engagé, combat, occupation, arrivée en cours et ancienne flotte impossible à reconstituer.
- [ ] Si l’origine est perdue, sélectionner une colonie de repli ; un carburant insuffisant refuse l’action sans prendre le fret d’hydrogène ni les stocks distants.

## Détection des attaques

- [ ] Avec plusieurs niveaux d’Analyse tactique, vérifier le préavis et sa limite à la moitié du trajet ; avec les plafonds 5/5/5 vérifiés dans l’univers de référence, le niveau effectif maximal est quinze. Contrôler les éventuelles surcharges de l’univers testé.
- [ ] Avant la détection, aucune alerte anticipée ne révèle l’attaque. Après : cible, compte à rebours, commandant, nombre de vaisseaux et points ; origine seulement si découverte.
- [ ] Les effets existants sur l’espionnage et la détection des sondes à l’arrivée sont conservés.

## Demandes d’orbite

- [ ] Une nouvelle demande est visible dès le départ, avec échéance à l’arrivée ; allié accepté automatiquement, neutre soumis à décision.
- [ ] Refus, retrait ou absence de réponse entraînent le retour prévu ; répéter un clic ne crée pas de double effet.
- [ ] Un changement de propriétaire ou de relation est revérifié à l’arrivée.
- [ ] Ennemi, PNJ réel ou planète vierge restent interdits ; une ancienne planète PNJ devenue colonie de joueur suit son propriétaire actuel.
- [ ] Une orbite neutre acceptée défend son hôte ; les anciennes missions conservent leur transition documentée.

## Rapports, messages et classements

- [ ] Rapports récents en premier ; case, résumé et action sont alignés, sans texte de sélection visible. Tester archivage individuel/par sélection, consultation des archives et restauration ; les preuves restent conservées.
- [ ] Le triangle jaune des messages est utilisable au clavier et au toucher et ouvre les motifs de signalement.
- [ ] Cliquer les cinq en-têtes du classement joueur : ordre et rang changent ; la colonne active est indiquée et la pagination conserve la catégorie sur le classement global.
- [ ] Les valeurs et leurs variations sont sur la même ligne, y compris le nombre de planètes avant les points totaux.
- [ ] Gains « + » verts, pertes « − » rouges, stabilité « (= 0) », comparaison absente « (—) ». Une progression de place est un gain.
- [ ] Le tag apparaît sous la forme [TAG] Commandant au classement uniquement ; aucune paire de crochets vide.
- [ ] Le tag est requis à la création d’une guilde et choisi par le commandant pour une guilde existante ; unicité dans l’univers.
- [ ] Le signalement d’un joueur propose les motifs validés et transmet le contexte de classement uniquement à la modération.

## Administration et catalogues

- [ ] Attribuer quelques vaisseaux sur une colonie de test : planète, type, rang et quantité exacts ; trace administrative et refus pour un compte non autorisé.
- [ ] Le tableau des déplacements affiche commandant origine et commandant destination, avec une présentation claire des PNJ et destinations sans propriétaire.
- [ ] Les cartes Bâtiments, Recherche, Chantier et Défenses n’affichent plus « Accès associés à ce rang » ; les déblocages restent actifs.
- [ ] Chaque prérequis mène à la bonne page, au bon rang et à l’élément requis en conservant univers et colonie.

## Abandon — uniquement avec une colonie de test sacrifiable

- [ ] Lire le bilan exact des pertes avant toute confirmation. La dernière planète ne peut pas être abandonnée.
- [ ] Les flottes liées, attaques, orbites, occupations, combats et commandes dépendantes d’une autre colonie bloquent l’abandon.
- [ ] Un nom incorrect ou un bilan devenu obsolète empêche l’abandon ; le nom exact autorise une seule exécution.
- [ ] La planète redevient vierge et les éléments annoncés sont perdus ; les historiques demeurent. La colonie active restante reste sélectionnable.
- [ ] Recoloniser avec le même joueur ne donne pas de nouveau kit ; un autre propriétaire conserve le kit normal.

## Diplomatie, Profil et Inventaire

- [ ] Comparer les trois pages réelles aux maquettes approuvées, sur ordinateur et mobile.
- [ ] Diplomatie : retrouver équipage, canal, projets, grades, permissions, tag, commandement, recherche, propositions et relations ; tester dirigeant et membre.
- [ ] Profil : identifiants conservés après erreur, mot de passe facultatif, confirmation avec le mot de passe actuel et reconnexion après enregistrement.
- [ ] Suppression de compte : conséquences visibles avant action, confirmation conservée ; ne l’essayer qu’avec un compte jetable.
- [ ] Inventaire : capacité, état vide, quantités, récompenses en attente et opérations éligibles clairement séparés.
- [ ] Vérifier l’examen puis l’annulation et la confirmation d’un boost ou d’une boîte : aucun élément consommé lors de l’annulation, aucun double effet à la confirmation.
- [ ] En vacances ou sans capacité de stockage, les bonus et boîtes restent conservés ; messages d’erreur compréhensibles.

## Retouches à décider ensemble

| Page et taille d’écran | Problème observé | Résultat souhaité | Priorité | Décision |
| --- | --- | --- | --- | --- |
| À compléter après les essais | | | | |

Le déploiement Alpha et un audit complet de conformité RGAA ne sont pas inclus dans cette recette.

### Retouche Diplomatie approuvée

- [ ] Sans guilde : Créer une guilde, Rejoindre une guilde et Relations commandants ; formulaire de création dans le premier, recherche et adhésion dans le second. Vérifier une adhésion effective et la disparition des deux onglets après adhésion.
- [ ] Avec guilde : Ma guilde, Membres, Grades et permissions, Relations guildes, Relations commandants ; tester chaque onglet au clavier et sur mobile.
- [ ] Membres : noms, grades, messagerie et attributions autorisées ; état En ligne vert / Hors ligne rouge, avec texte, thèmes clair et sombre.
- [ ] Deux comptes de la même guilde : actualiser la liste après cinq minutes sans activité du second ; son activité dans un autre univers ne doit pas changer cet état. Aucune heure ou durée ne doit être affichée.
- [ ] Ma guilde : aperçu sans marquer lu, canal, projets, tag, transfert, dissolution ou départ selon le rôle ; grades et relations gardent leurs contrôles de droits.

- [ ] Bulles sans croix : passage du nom/encadré à la bulle possible ; sortie du pointeur ferme même après un clic souris. Vérifier Échap, sortie du focus et toucher extérieur.
- [ ] Ma guilde : trois actions Projets, Galaxie et Classement sur une ligne sur ordinateur, retour à la ligne sur mobile ; aucun raccourci Relations guildes en doublon dans ce bloc.
- [ ] Avec au moins cinq messages, Derniers échanges montre seulement les trois plus récents ; Tout lire ouvre la conversation complète et le simple aperçu conserve les non-lus.
- [ ] Dissoudre est visible pour le commandant, Quitter pour un membre, sans déplier les réglages ; les autres rôles ne reçoivent aucun droit supplémentaire.
