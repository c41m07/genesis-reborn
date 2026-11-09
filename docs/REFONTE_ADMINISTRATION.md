# Cadrage de la refonte de l’administration

La refonte vise une console organisée autour de la surveillance des univers,
de la gestion des joueurs et de la création guidée d’événements. Les choix
produit ci-dessous sont issus des réponses de l’utilisateur du 8 novembre 2026.
Ils décrivent une évolution à développer, pas le fonctionnement déjà livré.

## Premier lot vérifié localement

Sur `feature/refonte-console-admin`, l’accueil et les rubriques ont été séparés
en conservant les formulaires existants. La surveillance présente les missions
actives, une recherche par commandant ou cible, une actualisation périodique et
des fiches détaillées. Les rapports de transport sont regroupés dans ces fiches.
Les lectures des fiches et rapports administratifs sont journalisées. Les anciens
liens et l’URL des transports conservent une destination utilisable.

Vérifications locales : validation, lint et compilation frontend réussis ;
475 tests PHP et 13 tests des scripts réussis. Les 27 scénarios navigateur ciblés
réussissent par consolidation de plusieurs exécutions, après correction du
contraste, de la navigation et du libellé accessible du bouton Admin.
Les contrôles portent aussi sur les autorisations, les rapports privés des joueurs,
les formulaires conservés et les thèmes clair/sombre aux tailles bureau et mobile.
Cela ne constitue pas une validation de la CI ni un audit RGAA complet.

Intégration avec Dev : guide technologique et correction concurrente du compteur
conservés. Les six contrôles ont été repris sur l’intégration : validation, lint,
475 tests PHP, 13 tests des scripts, compilation, 19 scénarios navigateur en une
exécution complète et contrôle des différences réussis. Les rapports de conquête
et d’espionnage des joueurs avaient également été vérifiés sur le premier lot.

Les dossiers, les alertes répétitives, les
interventions, l’assistant complet d’événements et les boîtes ne sont pas encore
implémentés dans ce lot. Aucune migration de ce lot sur une base existante.

## Organisation validée

| Rubrique | Contenu attendu |
| --- | --- |
| Accueil | Synthèse de l’activité, alertes prioritaires et événements en cours ; accès aux détails concernés. |
| Surveillance | Missions en cours en priorité, historique, recherche par joueur ou cible, fiches détaillées, alertes, notes et suivi des dossiers. |
| Joueurs | Fiches des comptes, participations aux univers et accès aux outils de gestion existants. |
| Univers | Gestion, calendrier, paramètres, catalogues et mesures existants. |
| Événements | Modèles guidés de bonus temporaires, annonces et défis collectifs avec contributions individuelles. |
| Modération | Signalements et feedbacks ; archives masquées par défaut. |
| Exploitation | État des traitements, relais, sauvegardes et journal des actions administratives. |

L’entrée séparée « Transports » doit être regroupée dans Surveillance. Les
rapports et leurs détails économiques doivent rester consultables depuis la
mission concernée. Conserver un accès compatible avec les anciennes URL.

Les rôles existants sont conservés. Les interventions supplémentaires validées
sont décrites ci-dessous ; aucune sanction automatique n’est demandée.

## Présence et interventions validées

- Présence estimée : activité depuis moins de cinq minutes, avec date de dernière
  activité. Ce statut ne garantit pas la présence devant l’écran.
- Temps de jeu estimé par univers : aujourd’hui, sept derniers jours et total
  depuis le début du suivi. Suspendre le compteur après cinq minutes sans activité ;
  ne pas reconstruire artificiellement un historique antérieur au suivi.
- Ressources : choisir une colonie appartenant au joueur, ajouter ou retirer
  les quantités indiquées, respecter les entrepôts et refuser tout solde négatif.
  Motif obligatoire et confirmation avant application.
- Flottes : accélérer ou retarder une échéance et rappeler une mission lorsque
  le retour est possible dans son état courant. La modification en vol des
  vaisseaux et de la cargaison ne fait pas partie du choix validé.
- Journaliser les interventions avec acteur, motif et valeurs avant/après.
  Recontrôler les permissions et l’état courant sous verrou au moment d’appliquer
  l’action ; les tâches déjà planifiées doivent rester cohérentes.

Ces outils sont à développer dans un lot distinct du suivi en lecture seule.

## Surveillance validée

- Présenter les missions actives, leurs acteurs, l’univers, l’origine, la cible,
  le statut et les échéances.
- Fournir une fiche avec vaisseaux, ressources, carburant et rapports disponibles.
- Conserver l’accès à l’historique et aux filtres par univers, mission et statut.
- Remonter les incidents techniques et les activités inhabituelles : échéances
  dépassées, transferts ou attaques répétés et incidents de sécurité.
- Permettre les notes et le suivi des dossiers depuis les fiches concernées.

Une indication d’activité inhabituelle est un élément à examiner, pas une preuve
de triche. Les seuils initiaux validés, réglables par univers, sont : mission en
retard de plus de 5 minutes ; 5 transports entre deux joueurs distincts en 24 h ;
10 attaques contre un même joueur en 24 h, PNJ exclus.
Les accès et actions d’administration restent protégés et traçables.

### Limites des données actuelles

La liste `/admin/flottes` conserve déjà des missions actives et terminées.
Cependant, le départ peut être remplacé par celui du trajet de retour ; les
unités et la cargaison d’une flotte rentrée ne constituent pas un instantané de
son départ. Les rapports de transport et de combat possèdent leurs propres
instantanés. Les rapports d’espionnage n’ont pas de relation directe avec Fleet.

La nouvelle interface doit distinguer les données actuelles des données
historiques conservées. Ne pas inventer de composition initiale, de date de fin
ou de rapprochement entre rapports et missions. Étudier une trace fiable des
futures étapes des missions avant de calculer les alertes répétitives.

## Événements validés

L’assistant doit proposer des bonus temporaires, des annonces et des défis
collectifs par univers, avec suivi de la contribution de chaque joueur.
Le paramétrage comprend le modèle, la portée, les dates, les objectifs et les
récompenses. Un aperçu précède la planification dans le parcours proposé.

Réutiliser les mécanismes d’événements existants et vérifier chaque effet avant
de le présenter comme utilisable. Les cycles hebdomadaires déjà configurés et
les règles d’attribution existantes ne sont pas supprimés par la priorité donnée
aux nouveaux défis collectifs.

Les récompenses demandées sont les boosts et les boîtes de ressources. Les
distinctions de profil restent une proposition non validée.

## Boîtes de ressources validées

| Sujet | Décision |
| --- | --- |
| Contenu | Assortiment de ressources annoncé à l’avance, sans contenu aléatoire. |
| Formats | Petite, moyenne et grande. |
| Quantités | Montants fixes validés dans le tableau ci-dessous, avec réglages et plafonds par univers. |
| Réception | Boîte conservée dans l’inventaire, à ouvrir manuellement. |
| Destination | Le joueur choisit une colonie lui appartenant dans le même univers. |
| Stockage insuffisant | Ouverture refusée et boîte conservée intacte. |
| Capacité | 100 emplacements partagés entre boosts et boîtes, dans la participation à l’univers. |
| Inventaire plein | Récompenses supplémentaires conservées en attente. |
| Mode vacances | Ouverture bloquée ; boîte conservée sans consommation. |

Les boîtes stockées réduisent donc le nombre de places disponibles pour les
boosts. Cette évolution remplace, une fois implémentée, le plafond actuel de
100 boosts disponibles par un plafond de 100 objets disponibles.

### Quantités initiales validées

Cette base représente respectivement 10 %, 25 % et 50 % des stocks de départ
de référence dans `app/config/game/rules.yaml`. Ces valeurs sont validées pour
le futur système ; elles ne sont pas encore appliquées à des récompenses.
Les noms des ressources suivent cette configuration, notamment Nano.

| Format | Métal | Nano | Hydrogène | Antimatière | Électricité |
| --- | ---: | ---: | ---: | ---: | ---: |
| Petite | 4 000 | 1 000 | 1 000 | 200 | 2 000 |
| Moyenne | 10 000 | 2 500 | 2 500 | 500 | 5 000 |
| Grande | 20 000 | 5 000 | 5 000 | 1 000 | 10 000 |

Chaque participant remplissant les conditions de l’événement peut recevoir
**un seul objet pour cet événement : un boost ou une boîte**, sans cumul des deux.

Proposition de réalisation : figer le contenu lorsque la boîte est attribuée,
pour que les changements ultérieurs du modèle ne modifient pas une récompense
déjà gagnée. Vérifier la propriété, l’univers et toute la place nécessaire sous
verrou avant de consommer la boîte ; une double soumission ne doit pas produire
un second dépôt.

## Points encore à trancher

- Les contrôles d’édition des plafonds configurables par univers.

Les boîtes sont une nouvelle récompense d’événement. Le plafond existant des
boosts issus des missions à risque reste inchangé.

## Séquence de réalisation proposée

1. Réorganiser la navigation et l’accueil en conservant les fonctionnalités
   existantes ; regrouper les transports et le suivi détaillé des missions.
2. Ajouter les fiches, notes, dossiers et traces nécessaires aux alertes.
3. Construire le parcours guidé des événements sur les effets et objectifs
   réellement raccordés au jeu.
4. Étendre l’inventaire et l’attribution des récompenses aux boîtes, après
   validation des règles restantes.
5. Vérifier les permissions, les transitions, les doublons, le responsive,
   le clavier et les contrastes ; exécuter les six contrôles avant publication.

Toute migration nécessaire doit être préparée puis soumise à validation avant
application sur une base existante, conformément aux consignes du dépôt.

## Lot présence et temps de jeu — vérifié localement

Complexité 4/5 : suivi concurrent et permissions, sans modification des règles
économiques ou des seuils d’inactivité des empires. Une table dédiée par
participation conserve le début du suivi, le dernier échantillon, la dernière
interaction estimée, le total et les compteurs quotidiens récents. Un verrou sur
la participation sérialise les onglets. Aucun temps n’est crédité pour un
intervalle entre deux échantillons supérieur à cinq minutes. Les intervalles
qui passent minuit sont répartis entre les journées UTC concernées ; les journées
anciennes sont écartées lors du prochain échantillon, le total est conservé.

Les visites de pages du jeu et un échantillon par minute dans une page visible
alimentent ce suivi. Le client s’arrête après cinq minutes sans interaction,
et au changement de participation. Les requêtes de suivi vérifient le jeton de
formulaire et la participation courante ; elles ne modifient pas les règles
existantes de vacances ou de conversion des empires oubliés.

La fiche est accessible dans Administration → Joueurs → nom du compte.
Ses lectures sont journalisées ; son accès reste réservé aux administrateurs.
La migration préparée est Version20261109010000 (nouvelle table player_activity,
sans modification des données existantes). Son application sur une base existante
attend une validation distincte et une sauvegarde vérifiée.
Contrôles : validation, lint, compilation et différences propres ; 478 tests PHP
réussis par consolidation de l’exécution complète et de la reprise des trois
tests de suivi après correction d’une comparaison de dates ; 13 tests des scripts
et six scénarios navigateur réussis. HTML de confidentialité contrôlé, captures
bureau/mobile et contrastes vérifiés. Migration appliquée uniquement sur les
bases jetables de vérification, pas sur une base existante du jeu.
## Lot interventions — vérifié localement

Complexité 5/5 : écritures sur les stocks et les missions, sans nouveau schéma.
Les corrections par colonie sont relatives (ajout ou retrait) et atomiques pour
l’ensemble des ressources demandées. Les droits, la propriété, l’univers et
les stocks produits sont contrôlés sous verrou avant application. Un dépassement
de capacité ou un solde négatif annule toute l’opération. Un identifiant de
requête empêche de réappliquer un envoi doublé.

Les flottes en aller, retour ou orbite peuvent recevoir un nouveau délai restant,
entre une seconde et un an (borne de saisie administrative). Le rappel concerne
l’aller, l’orbite et les demandes d’orbite en attente ; le retour utilise la durée
de référence et les règles existantes de carburant. Aucun combat, occupation,
mission terminée ou traitement déjà échu n’est modifié ici. Une empreinte de
version refuse une fiche devenue périmée sans exposer le jeton du traitement.
La replanification remplace ce jeton et crée une nouvelle tâche fiable ; l’ancienne
tâche devient inopérante. Les rapports de transport identifient le rappel admin.

Chaque intervention exige motif et confirmation explicite, avec contrôle des
droits administrateur actuels et journal des valeurs avant/après. Les écritures
sont testées uniquement sur les bases jetables ; aucune correction sur un joueur
réel n’est effectuée pendant le développement.
Contrôles réussis : validation, lint, compilation, 486 tests PHP et 13 tests des
scripts. Huit scénarios navigateur réussissent par consolidation : les deux
nouveaux parcours ont été repris après correction des appels à l’hôte isolé et
du décodage SQL de leurs assertions. La revue mobile a conduit à agrandir les
boutons, puis les deux parcours ont réussi à nouveau. Les contrats de transport,
les jetons de tâche périmés, le refus des combats, l’atomicité et les doubles
soumissions sont couverts. Aucun nouveau schéma dans ce lot.
## Lot dossiers et alertes — vérifié localement

Complexité 5/5 : nouveau schéma, instantanés, permissions et protection des
modifications concurrentes. Chaque futur départ de flotte conserve son univers,
son expéditeur, son destinataire au départ et un instantané des unités, cargaison,
carburant et coordonnées. Les missions plus anciennes ne sont pas reconstituées.

Les alertes comparent les échéances au retard autorisé, regroupent les transports
entre deux joueurs dans les deux sens et comptent les attaques contre un même
joueur, tous attaquants confondus, dans une fenêtre de 24 heures. Les cibles PNJ,
les transports à soi-même et les traces hors fenêtre sont exclus. Jusqu’à cent
signaux par catégorie sont présentés par univers, avec liens vers les missions,
les joueurs et les dossiers. Les incidents de sécurité gardent leur rubrique.
Les seuils sont réglables par univers et conservés lors de l’édition des autres
paramètres ; leur modification est journalisée.

Un dossier peut concerner la plateforme, un univers, une participation ou une
flotte. Il dispose d’un titre, d’observations initiales et d’un état ouvert,
en examen ou clos. Les notes datées sont ajoutées sans réécriture de l’historique.
Les formulaires doublés ne créent pas de doublons et un ancien état ne peut pas
écraser un changement concurrent. Les lectures sont journalisées. La liste est
filtrable et paginée ; les dossiers clos restent accessibles dans l’historique.
Un dossier déjà lié à un signal est réutilisé, sans clôture ni réouverture automatiques.

La migration préparée Version20261109020000 crée admin_case, admin_case_note et
fleet_mission_trace. Elle est vérifiée sur des bases jetables ; toute application
sur une base existante reste soumise à validation et sauvegarde vérifiée.
Contrôles réussis : validation, lint, compilation, 494 tests PHP et 13 tests des
scripts. Quatorze scénarios navigateur réussissent par consolidation. La revue
des captures a révélé que certains tests de thèmes ne transformaient pas les
préférences vides en objet JSON ; ces tests vérifient désormais explicitement
le thème appliqué. Leur reprise réelle a conduit à renforcer le contraste des
liens latéraux en thème clair, puis les dossiers, fiches et interventions ont
réussi les contrôles concernés. Le HTML public de confidentialité a été contrôlé.
La migration reste limitée aux bases jetables pour cette vérification.
## Lot boîtes et inventaire commun — en vérification

Complexité 5/5 : migration compatible et opérations économiques atomiques.
La table existante d’objets distingue les boosts des boîtes et conserve un
contenu figé. L’attribution d’un événement utilise toujours une clé unique par
participation : attribuer ensuite l’autre type d’objet ne donne pas une seconde
récompense pour le même événement. Les anciens boosts restent des boosts.

Les cent places disponibles sont partagées. Le plus ancien objet en attente,
boîte ou boost, rejoint l’inventaire lorsqu’un objet est consommé. Les boîtes
identiques sont regroupées visuellement, mais occupent chacune une place.
L’utilisation d’un boost sélectionne exclusivement un objet de type boost.

L’ouverture vérifie sous verrou la participation, la colonie, l’univers, le mode
vacances, l’objet disponible et la place pour chaque ressource. Le dépôt entier
est annulé au moindre dépassement ; le contenu et la boîte sont conservés.
Un identifiant de requête évite un double dépôt.

Les modèles petite/moyenne/grande reprennent les montants validés. Les plafonds
initiaux correspondent aux montants du grand format et restent réglables par
univers, avec motif, confirmation et journal avant/après. Les nouveaux réglages
ne modifient pas les boîtes déjà attribuées. L’interface se trouve dans
Administration → Univers → réglages de l’univers → modèles de boîtes.

La migration Version20261109030000 ajoute le type (boost par défaut) et un
contenu JSON facultatif aux objets existants, sans supprimer d’objet. Son retour
en arrière est bloqué si des boîtes ou contenus doivent être conservés. Elle
reste testée uniquement sur des bases jetables avant validation d’application.