# Synthèse de conception

## Protection après pertes et riposte

Lorsque l’attaque gagne, le propriétaire de la colonie défendue reçoit 24 h de protection s’il perd au moins 70 % de ses vaisseaux militaires engagés. Le délai entre deux activations est de sept jours et la suspension pour inactivité s’applique. La protection prend fin dès le départ d’une attaque contre un autre joueur. Un devis, une mission d’espionnage ou une attaque de PNJ la préserve.

Chaque départ d’attaque entre joueurs ouvre à sa cible un droit de riposte contre cet agresseur pendant 24 h dans le même univers. Le rapport de points est levé pour un départ de riposte dans ce délai. Diplomatie, vacances et protections de la cible restent vérifiées. Une sonde détectée donne également un rapport d’observation privé au propriétaire espionné. Ce rapport ne révèle aucune composition ennemie.

## Répartition des prix courante

Les 156 profils par défaut utilisent des recettes propres à chaque fiche et à chaque rang, sans parts de valeur imposées par rang. Voir [les raisons des recettes RP](CATALOGUE_COUTS_RP.md) et [les anciens prix](SAUVEGARDE_CATALOGUE_COUTS_AVANT_RP.md).
La valeur est M×2 + N×4 + A×8 + H ; il ne s’agit pas de quantités brutes ni d’un marché.
Les quantités sont entières et conservent le budget de base propre à chaque profil,
y compris ceux des défenses. Les courbes et arrondis après croissance/remises restent
appliqués. La famille Expansion coloniale (Protocoles de colonisation au rang
Basique) utilise une base de 28 800 s pour tous ses rangs et niveaux. La taxe
électrique suit la durée effective avec la formule existante. Les nouveaux prix concernent les
nouvelles commandes ; les coûts déjà payés restent la référence des annulations et
résolutions. Les réglages personnalisés d’univers gardent leur priorité.
La formule de points reste fondée sur les quantités brutes des prix courants,
sans pondération M/N/A/H : leur redistribution peut donc changer les points au
recalcul, ainsi que les protections qui en dépendent. Les seuils et règles de
protection ne sont pas modifiés. [Profils détaillés et limites des arrondis](COUTS.md).

## Progression économique par rang

Les 19 recherches exigent, pour ouvrir le T2, le niveau 5 du T1 de la même famille, et pour le T3, le niveau 5 du T2. Les autres conditions de déblocage et le laboratoire local s’appliquent également. Un accès déjà acquis reste acquis.

Le T1 utilise les quantités de son profil. Aux rangs avancés, le budget initial vaut 110 % de la valeur du passage au niveau exigé le plus cher. Pour les recherches, seules les recherches du même identifiant de famille comptent. Pour les bâtiments, tous les bâtiments et recherches requis comptent. Chaque référence est le devis en matériaux du seul niveau demandé, avant remise, aux prix de l’univers. La valeur vaut hydrogène + 2 × métal + 4 × nano + 8 × antimatière. Le budget suit les proportions du profil cible. Au niveau N, le devis repart du budget initial non arrondi et arrondit chaque quantité au supérieur.

Les matériaux des recherches progressent de 35 % par niveau. Les durées progressent de 35 % aux niveaux 2–5, 25 % aux niveaux 6–10, 18 % aux niveaux 11–15 et 12 % ensuite. Chaque rang conserve sa durée de départ. La taxe est de 100 unités d’électricité par minute effective entamée.

Pour les quatre extracteurs, la centrale, les cinq dépôts, le laboratoire, le chantier et le bunker, la hausse des matériaux est de 10 % aux niveaux 2–10, puis 9 %, 8 %, 7 %, 6 %, 5 %, 4 %, 3 % et 2 % par tranches de dix niveaux. Dès le niveau 91, elle vaut 1 %. La durée de construction suit sa courbe propre.

Pour chaque rang, la contribution de L niveaux vaut sa base par niveau × L × (1 + taux)^L. Les taux T1/T2/T3 valent 0,25 %, 0,5 % et 1 %. La production industrielle additionne les contributions, avec les facteurs de production 1 / 1,5 / 2,25. Pour l’électricité des quatre extracteurs, on applique la même formule à la consommation de base, avec les mêmes facteurs de rang et des taux propres de 0,125 % en Basique, 0,0625 % en Intermédiaire et 0,03125 % en Supérieur. Les trois consommations s’additionnent. Le piège consomme 4 hydrogènes par antimatière effectivement produite. La capacité additionne les contributions des dépôts (poids 1 / 1,25 / 1,5625) aux 10 000 places initiales de la colonie.

Pour le laboratoire, le chantier et le bunker, les poids sont 1 / 1,25 / 1,5625. On divise la somme des contributions par celle obtenue aux trois maxima. Pour cette proportion p, l’effet vaut plafond × ln(1 + 2p) / ln(3). Le laboratoire et le chantier atteignent **89,5 % à 25/25/25** pour les recherches lancées sur cette colonie et ses constructions de vaisseaux respectivement. Une durée nominale de 10 h passe à 1 h 03 min. Aucun cumul entre ces deux effets : chaque opération utilise sa propre infrastructure. Le bunker atteint +25 % de structure des défenses à 10/10/10, en composition avec le blindage.

## Unités économiques — décision des 15 et 16 novembre 2026

Les deux familles de vaisseaux civils sont détaillées dans
[UNITES_ECONOMIQUES](UNITES_ECONOMIQUES.md) : prix, prérequis, statistiques et
transitions. Chaque rang dispose de ses propres places sur chaque colonie :
un récolteur actif par niveau de raffinerie d’hydrogène, deux satellites par
niveau de centrale du même rang. Les satellites en chantier et les récolteurs
en affectation réservent ces places. Aucun manque de place ne détruit une unité ;
l’excédent présent reste conservé sans production.

Production par unité active, position 5, univers ×1 : récolteur 900 / 1 350 /
2 025 H/h, satellite 450 / 675 / 1 012,5 E/h. Position, univers, événements,
vacances, stockage et surplus existants restent pris en compte. Aucun entretien
horaire ajouté, ni réduction de ces sources par le rendement électrique industriel.
Le récolteur n’a pas de fret et se déplace uniquement par Stationnement entre
colonies possédées ; aucune production en déplacement. Le satellite est immobile.

Ces unités défendent leur colonie, sont ciblables et destructibles, sans aucun tir
même après recherche. Les tirs qui les ciblent utilisent ×1. La production continue
pendant l’engagement et cesse avec les pertes appliquées à la clôture. Les unités
livrées au chantier ou installées par Stationnement pendant une bataille ouverte
rejoignent la défense au prochain round éligible avec tous les vaisseaux locaux
disponibles ; leur flotte et leur carburant restent conservés. Aucun round déjà
résolu n’est réécrit par cette livraison.

Les usines gardent le secours électrique de 10 %, sans garantie absolue de reprise :
le piège reste dépendant de son hydrogène. La reconstruction n’ajoute aucun secours
gratuit. Les [mesures conjointes](SIMULATIONS_PROGRESSION_ECONOMIE.md) du lot 4
comparent des politiques déterministes, sans garantir de délai pour tous les joueurs
ni activer le candidat de prix. Les règles ci-dessus restent inchangées.

## Progression (PDF 0 p.3–9)
1. Socle, authentification session.
2. Univers et cartes JSON, inscription PlayerUniverse, sélection.
3. Ressources, bâtiments, recherches, chantier et queues.
4. Flottes, trajets, exploration, transport, colonisation, orbite.
5. Relations, guildes, combats multi-camps et notifications privées.
6. Variantes, fermeture, conquête, classements et finitions.

## Monde et comptes
Univers → galaxie → système (x,y) → planète position 1..9. Comptes globaux, état par inscription à un univers. Aucune interaction entre univers. Univers fermés conservés mais inaccessibles. Cartes JSON distinctes de la configuration YAML et import explicite idempotent. Carte statique, planètes masquées hors systèmes découverts.

### Suppression volontaire du compte

Depuis la page Suppression du compte de Mon compte, le joueur peut supprimer son compte dans tous les univers
après saisie du mot de passe et confirmation explicite. Les identifiants et
préférences sont effacés et les accès fermés. Les empires deviennent oubliés
immédiatement, sans attendre le délai d’inactivité ; leurs colonies et les
historiques de jeu restent présents. Les vacances prennent fin. Un combat en
cours bloque l’opération entière jusqu’à sa résolution. Le dernier administrateur
actif doit désigner un successeur avant de supprimer son compte.

## Colonie
Métal, électricité, antimatière, hydrogène et nano par planète. La simulation intègre les stocks par segments entre épuisement et capacité. Les usines adaptent leur rendement à l’électricité disponible avec un plancher de 10 % ; l’antimatière exige aussi de l’hydrogène. Coûts et durées sont autoritaires côté serveur. Les coûts des trois rangs suivent des profils par catégorie avec budgets de rang calculés selon la progression économique. Les technologies sont débloquées par joueur et par univers. Chaque nouvelle commande de recherche exige aussi un laboratoire du même rang au niveau 1 ou plus sur la colonie de lancement. La file de recherche est commune au joueur dans l’univers.

### Prérequis par rang — grille actualisée le 18 novembre 2026

Chaque famille utilise la [grille complète des prérequis](DEBLOCAGES.md).
Le métal, le nano et la centrale sont libres au rang Basique. Leur
Intermédiaire exige le même bâtiment Basique 25 et Ingénierie de chantier
automatisée 5. Leur Supérieur exige le même bâtiment Basique 50, son
Intermédiaire 25 et Planification industrielle intégrée 5. L’hydrogène
utilise les mêmes seuils de bâtiments et exige sa recherche industrielle
du même rang au niveau 3, dès le Basique.

Le premier laboratoire Basique coûte 4 800 métal et 2 600 nano et se construit en 30 minutes à vitesse standard avant bonus. Au niveau 1, l’Intermédiaire demande 37 min 30 s et le Supérieur 46 min 53 s.

Le laboratoire reste libre en Basique. Son Intermédiaire exige le laboratoire
Basique 10 et Génie civil colonial 5. Son Supérieur exige les laboratoires
Basique et Intermédiaire 10 et Ingénierie de chantier automatisée 5. La
recherche du rang précédent évite un cycle. Le laboratoire est plafonné à 25.

Chaque stockage exige son producteur du même rang niveau 5 sur la colonie.
L’Intermédiaire exige aussi le même stockage Basique niveau 5. Le Supérieur
exige les deux rangs précédents de ce stockage au niveau 5. Les nouvelles
commandes sont plafonnées à 5 en Basique et en Intermédiaire. Le Supérieur a
une progression sans plafond. Les niveaux historiques, leur capacité et les
commandes déjà payées sont conservés.

Le chantier exige métal 2 et nano 2, et le bunker métal 1 et nano 1,
dans le rang demandé. Pour les rangs avancés, le même bâtiment doit
atteindre le niveau 20 (chantier) ou 8 (bunker) dans chaque rang précédent.
Le Piège à antimatière exige le producteur d’hydrogène 5 sur la colonie et
la recherche industrielle 5 dans l’univers, tous deux du rang demandé.
Pour l’Intermédiaire, le producteur d’antimatière Basique doit aussi atteindre
le niveau 25. Pour le Supérieur, ses rangs Basique et Intermédiaire doivent
atteindre les niveaux 50 et 25, respectivement.
Le laboratoire niveau 1 du même rang sert au déblocage initial de cette recherche.
Les accès déjà acquis restent conservés ; cette révision ne change pas la version
du registre et ne nécessite aucune nouvelle requalification. Coûts, durées,
plafonds et surcharges d’univers restent applicables. L’effet de ces nouveaux
seuils sur les délais de progression n’est pas mesuré.

Après requalification explicite des accès existants, seules les conditions de
cette grille ouvrent les nouvelles commandes. Les niveaux, effets, unités,
flottes, stocks et historiques restent conservés. Les opérations déjà payées se
terminent avec leur paiement et durée enregistrés ; leur achèvement ne suffit
pas à débloquer l’élément. L’accès nouvellement acquis reste ensuite permanent,
par joueur/univers pour les recherches, par joueur/colonie pour les autres catégories.
La [procédure de bascule](DEBLOCAGES.md#activation-après-validation) ne s’exécute
qu’après sauvegarde vérifiée et autorisation. Le [suivi de mission](PROGRESSION_CARBURANT_UNITES.md)
distingue les lots intégrés, les implémentations locales et les unités restant à réaliser.

## Queues (PDF 7 p.1–7)

Les [durées du 15 novembre](DUREES_CARBURANT.md) s’appliquent aux nouvelles commandes,
avec les courbes de niveau conservées et les facteurs de rang ×1 / ×1,25 / ×1,5625.
La taxe des recherches, vaisseaux et défenses reste de 100 E par minute entamée
de durée effective pour le lot entier ; les bâtiments en sont exclus. Les défenses
ne reçoivent pas de réduction de durée du chantier ou du bunker. Les opérations
déjà payées gardent leur durée et leur coût, y compris après la révision du catalogue.

Trois types de file : bâtiments et chantier par planète, recherche commune au joueur dans l’univers. QueueItem : type, slug, niveau cible ou quantité, durée, started_at, queued_at, dispatch_token, depends_on. Ordre id ASC. Échéance non persistée : T0 + somme des durées. Un item actif par type. Prérequis achevés à l'ajout ; exception niveaux successifs via dépendance. Suppression récursive des dépendants. Boost : chaque application retire 25 % du temps restant de l’opération active. Les applications successives se cumulent sur le nouveau temps restant; aucune file en attente n’est accélérée. Ancien message ignoré si token différent ; doublon ignoré après traitement. Persistance et déduction des ressources dans une transaction avec verrou.

## Trajets (PDF 3 p.2–9)

La consommation est différenciée par classe selon le [barème du 15 novembre](DUREES_CARBURANT.md#carburant-par-classe),
identique dans les trois rangs. À 50 % de vitesse, le carburant du trajet est divisé
par deux ; le maintien des missions qui le facturent reste indépendant de ce choix.
Aucun coût horaire supplémentaire pour le stationnement sur une colonie possédée.
Les réservations historiques restent conservées ; les coefficients des nouveaux
départs sont figés pour leurs rappels et replis. Vitesses, réservoirs,
soutes et usages des unités déjà possédées restent conservés.

UA universelle. Intra-système : abs(positionB-positionA) × 10. Inter-systèmes : sortie 100−positionA×10, hyperespace hypot(dx physique,dy physique)×100 selon la spirale réelle de `GalaxyGeometry`, entrée 100−positionB×10. Temps par segment = distance / (vitesse minimale de flotte × ratio). Ratio entre 0.1 et 1. Aller-retour symétrique. Carburant aller + stationnement + retour réservé avant départ. Consommation proportionnelle au ratio de vitesse. Chaque vaisseau possède un petit réservoir exclusivement pour le carburant ; les soutes cargo peuvent embarquer le fret et le carburant supplémentaire. Consommation d'orbite horaire indépendante du ratio. Navigation inter-galaxies non définie et refusée jusqu'à spécification.

Depuis la décision explicite du 18 novembre 2026, les nouveaux trajets utilisent
la géographie physique en spirale. La grille est une disposition de repérage ;
le choix de vue ne change aucun devis. Les coordonnées persistées restent des
identifiants historiques. Les vols existants gardent leurs durées et réservations ;
les nouveaux replis partent de la position physique atteinte.

## Combat (PDF 1 p.6–8, PDF 2 p.2–6)
Cadence confirmée par Kévin le 01/11/2026 : un round réel par minute, jusqu’à 10 rounds; les renforts rejoignent le prochain round non résolu selon leur heure d’arrivée. Le premier round commence à +60 secondes; les suivants à +120, jusqu’au 10e à +600. Une flotte arrivée exactement au début d’un round est traitée avant ce round et peut y participer, notamment à +600 secondes avant le 10e round. Le combat se clôt immédiatement dès qu’un camp est éliminé; toute flotte arrivée après cette clôture repart sans rejoindre le combat. Le moteur persiste et résout les rounds en temps réel selon cette cadence. L’Alpha conserve un round par minute, jusqu’à dix rounds ou clôture anticipée; voir l’audit de combat et les tests récents.
Le point de reprise conserve l’état des unités et du générateur de tirage après chaque round validé. Il est enregistré avec l’historique et la prochaine échéance dans la même transaction. Les combats en cours sans ce point de reprise reconstruisent leur état une fois depuis leur graine. Les rapports historiques restent au même format. Cette reprise ne change pas le plafond technique actuel du moteur ; elle ne valide pas encore les grandes batailles.

Fenêtre fixe dès premier attaquant, renforts échelonnés par rounds. Alliances directes et guildes définissent les camps ; défense extérieure intégrée au camp défendeur même sans alliance. FFA si attaquants non alliés. Unité individuelle, tirs bornés, cible vivante, destruction avant tir possible. Chance de base = clamp(15,95,precision+tracking+correction de l’arme−evasion−15+écart de rang). Chance finale = clamp(15,95,chance de base × coefficient de contre). La signature pondère le choix de cible ; le même coefficient de contre ou de faiblesse multiplie une fois la chance de toucher et une fois les dégâts d’un impact, sans second cumul réciproque. Un contre ×1,30 fait passer 50 % à 65 % de chance de toucher et augmente les dégâts par impact de 30 %. Voir le rééquilibrage du 02/11/2026 ci-dessous. Bonus technologiques figés au début de résolution. Notifications avec identité masquée selon découvertes ; destinataires limités. Après victoire offensive, les attaquants survivants peuvent piller le stock au-delà de 10 % de la capacité de stockage par ressource; leurs soutes libres se partagent le butin au prorata des stocks pillables, carburant de retour protégé. Au retour, le stockage restant de la planète d’origine plafonne le dépôt et le surplus est perdu.

## Architecture (PDF 4)
Controller → service métier → manager → repository. Entités et repositories plats, services regroupés par domaine ; messages et handlers fins ; intégration Mercure indépendante du jeu. Slugs uniquement pour référencer les catalogues. Aucun catalogue dynamique en base.

## Compléments opérationnels appliqués
Les opérations en retard sont rejouées à leurs dates théoriques avant dépense/lecture de colonie, avec production et plafonds successifs. Les notifications utilisent un relais indépendant des échéances du jeu. Une sonde ou un vaisseau-colonie consommé sans flotte survivante termine la mission à l’arrivée ; un transport revient et livre une seule fois.

Guildes : candidature depuis la recherche ou la présentation, à accepter par le commandant ou un grade autorisé ; invitation d’un commandant sans guilde, à accepter par le destinataire. Adhésion exclusive dans chaque univers, transfert du commandement à un autre membre, départ après transfert, dissolution par le dirigeant. Alliances personnelles et alliances de guildes exigent le consentement réciproque. Une hostilité de guilde invalide les contrats personnels incompatibles.

PNJ : les planètes PNJ découvertes du même univers peuvent être combattues par la mission Attaque, sans capture ni butin de ressources PNJ. Leur capture reste réservée à Conquête JcE, avec vaisseau colonie survivant, place disponible et règles d’occupation existantes (24 heures lorsque ce mécanisme est activé). Le récapitulatif de mission affiche le délai de capture après la victoire séparément de la durée du trajet. Les bâtiments et stocks suivent les règles de conservation de la capture. Les planètes vierges restent colonisables ; les joueurs actifs ou simplement inactifs ne sont pas conquérables. Les empires déjà oubliés suivent les règles spécifiques détaillées ci-dessous.

Espionnage : une mission cible une planète connue du même univers autre que celles de l’émetteur. Elle transporte uniquement des sondes, détruites à l’arrivée. La détection avertit le propriétaire, lui donne un rapport d’observation et empêche la collecte de renseignements ; les règles de détection actuelles sont décrites dans la section [Espionnage](#espionnage), les archives conservant les décisions initiales.

Les classements, l’administration, les combats, les missions avancées et l’espionnage sont livrés. Les choix chiffrés de base figurent dans docs/archives/CHOIX_EQUILIBRAGE_2026-11-02.md; leur effet sur le rythme et la difficulté sera mesuré avec les joueurs Alpha.


## Fermeture d’univers temporaires
Un événement Outbox est créé avec la date de fin; le relais le programme via Messenger. Le handler verrouille l’univers, vérifie la date et le jeton courant, puis passe son statut à fermé. Les anciens événements sont ignorés et les données des planètes restent intactes.


## Équilibrage retenu — 01–02/11/2026

Les valeurs initiales des stocks, productions, coûts, durées, vaisseaux, défenses, boucliers, contre-bonus, espionnage, PNJ et protections ont été choisies par Kévin pendant le QCM. Elles constituent la configuration de l’Alpha; elles ne sont pas déclarées équilibrées sur la seule base de leur présence. Les cibles de progression seront mesurées pendant l’Alpha, puis révisées à partir des résultats. La cadence confirmée reste un round par minute pendant dix minutes maximum.


## Progression technologique par rangs — décision du 02/11/2026

Chaque bâtiment, technologie et vaisseau conserve trois progressions distinctes, actives en parallèle : **Basique**, **Intermédiaire** et **Supérieur**. Les niveaux déjà sauvegardés sont conservés en Basique. Un niveau 10 Basique et un niveau 5 Intermédiaire sont donc simultanément possibles. Les défenses stationnaires possèdent aussi les trois rangs, enregistrés et combattus séparément.

| Rang | Facteur commun de référence (coûts et effets hors exceptions ci-dessous) | Durée d’opération |
|---|---:|---:|
| Basique | 1× | 1× |
| Intermédiaire | 1,25× | 1,25× |
| Supérieur | 1,5625× | 1,5625× |

Les profils de coûts par rang sont pré-calibrés par famille dans les catalogues; les coûts par niveau croissent ensuite selon la courbe du bâtiment ou de la technologie. Les opérations arrondissent chaque coût positif à l’entier supérieur; une ressource configurée à zéro reste à zéro. Cette règle évite un coût nul pour une ressource effectivement requise. Les durées sont arrondies à la seconde supérieure, avec un minimum d’une seconde.

La puissance dépend de la spécialité : la production et le stockage utilisent l’effet par niveau propre au bâtiment; les technologies appliquent leur bonus au champ concerné; vaisseaux et défenses renforcent structure, bouclier et attaque; les vaisseaux améliorent aussi vitesse, réservoir et soute. Précision, suivi et esquive gardent leurs valeurs de base; la signature n’est pas divisée. Depuis R14, chaque écart de rang ajoute ou retire 30 points de pourcentage après application du contre, avec une chance bornée entre 15 % et 95 %. Les stats positives entières sont arrondies vers le haut. Le nombre de tirs de base reste entier ; les technologies de cadence accordent une chance de tir supplémentaire, sans bonus implicite de cadence lié au rang.

Les bonus technologiques en pourcentage qui étaient à +5 % par niveau passent à **+1 % par niveau et niveau maximum 25**. Le laboratoire et le chantier spatial passent à **2 % par niveau, maximum 25**, conservant leur maximum Basique de 50 %. Les bonus de rang sont multipliés puis composés : une technologie à 25 niveaux dans chacun des trois rangs produit les facteurs 1,25 × 1,3125 × 1,390625 = **2,281494×**. Les bâtiments de production de ressources et les stockages Supérieurs ont une progression sans plafond. Les stockages Basiques et Intermédiaires sont plafonnés à 5 pour les nouvelles commandes.

Depuis la décision du 6 novembre 2026, les prérequis servent uniquement au déblocage initial de chaque rang, dès le niveau 1. Aucun nouveau prérequis pendant les améliorations. L’accès acquis reste conservé pour le joueur sur cette colonie (bâtiments, vaisseaux, défenses), ou dans cet univers (recherches). Les [conditions par rang et leurs raisons](DEBLOCAGES.md) remplacent les anciens paliers sans cumuler leurs exigences. Les seuils hors plafond sont corrigés ; comptes, niveaux, rangs, unités, files et accès historiques sont conservés par un registre persistant. Coûts, ressources, durées et plafonds restent applicables. Migration et reprise doivent être effectuées avant réouverture ; elles ne sont pas appliquées automatiquement.

Dans la grille historique du 2 novembre, les coûts de recherche par niveau suivaient la croissance ×2 et les durées de recherche la croissance ×1,8. Pour les devis actuels, voir la section « Progression économique par rang ». Les durées de bâtiment suivent leur courbe propre. Ces courbes restent des paramètres distincts des multiplicateurs de rang. La migration attribue les données historiques au rang Basique; les opérations déjà en file conservent leurs coûts et durées enregistrés.

Les technologies de combat couvrent structure, bouclier, attaque, précision, suivi, esquive, signature, cadence de tir, vitesse, réservoir et soute. Le tir supplémentaire est une chance indépendante par tir de base pour conserver une résolution entière et éviter des valeurs de tirs fractionnaires.

Pour la colonisation, chaque niveau de recherche apporte un emplacement, dans la limite de 5 niveaux Basiques, 10 Intermédiaires et 15 Supérieurs ; la limite de colonies configurée par l’univers reste prioritaire. Les productions et capacités de stockage de plusieurs bâtiments-rangs s’additionnent, car chaque rang fonctionne comme une installation distincte. Les coûts des ressources non énergétiques suivent les budgets de valeur par famille; l’électricité n’est pas facturée lors de la construction. Les usines reçoivent un rendement de secours minimal de 10 % en déficit électrique, selon une simulation segmentée hors connexion; la production d’antimatière est limitée par l’hydrogène et s’arrête sans apport. La consommation électrique de référence par niveau Basique est de 0,05/s pour le métal, le nano et l’hydrogène, et de 0,025/s pour le piège à antimatière. Celui-ci consomme aussi 4 H par AM produite.


### Research et art temporaire

Les 19 recherches ont reçu des libellés français distincts et des slugs dédiés, sans modifier leurs effets. Leurs états sauvegardés, files de recherche et surcharges d’univers sont renommés par une migration compatible. Les défenses sont regroupées en quatre tourelles au sol : anti-léger (intercepteur, chasseur, chasseur lourd, chasseur furtif), anti-escorteur (corvette, frégate, destroyer), anti-lourd (croiseur, cuirassé) et anti-capital. Les tourelles laser couvrent les classes légères et escorteurs; les tourelles plasma couvrent les classes lourdes et capitales. Chaque tourelle reçoit un bonus de dégâts contre sa classe. La collection complète des 150 illustrations de catalogue est intégrée aux cartes des 13 bâtiments, 19 recherches, 14 vaisseaux et 4 défenses, aux rangs basic, intermediate et superior. Chaque image est associée au slug et au rang de sa carte; les originaux PNG sont conservés dans les dossiers source de public/images/catalog.


## Rééquilibrage progression et combat — 02/11/2026

Les courbes, bonus communs entre rangs, profils de mobilité, ciblage furtif, retarget après destruction et matrice de contres sont décrits dans [BALANCING_IMPLEMENTATION_2026-11-02.md](archives/PROGRESSION_2026-11-02.md). Ces choix sont validés pour l’implémentation ; les jalons économiques demeurent des objectifs à mesurer en Alpha.

Pour les bonus et les courbes, ce rééquilibrage remplace les formules historiques de la section précédente : les bonus de recherche ne se composent plus par rang. L’investissement pondéré vaut la somme des niveaux de chaque rang, plafonnés à leur maximum et pondérés par 21 / 30 / 49 pour les quinze familles à rendement décroissant, divisée par le maximum pondéré des trois rangs. La progression commune vaut `ln(1 + 2 × investissement) / ln(3)` ; le bonus vaut cette progression multipliée par le plafond configuré. La réduction de durée des bâtiments par la recherche reste plafonnée à 70 % et la réduction de coût à 40 %. Le plafond relevé à 89,5 % ne concerne que le laboratoire et le chantier (voir la progression économique par rang). La cadence accorde une seule chance supplémentaire commune, plafonnée à 50 % par tir de base. Les coûts et durées utilisent les courbes par phase de `config/game/rules.yaml`, sous réserve des décisions plus récentes sur les producteurs. Les statistiques de combat R14 et les compositions de coûts du 5 novembre restent prioritaires sur les anciens exemples chiffrés.


### Tirs secondaires et contres — 4 novembre 2026

Les destroyers, croiseurs, cuirassés et vaisseaux capitaux disposent d’une arme secondaire avec une correction de précision de +50 points depuis R14. Cette correction est ajoutée avant le calcul de la chance de base ; le coefficient de contre est ensuite appliqué relativement à la chance bornée, puis les limites finales de 15–95 % sont réappliquées. Les coefficients concernent les tirs principaux et secondaires ainsi que les défenses.

Depuis R14, les armes secondaires déclenchent aussi les tirs supplémentaires de la recherche de cadence, sans récursion. Leur cible doit correspondre à la catégorie configurée pour l’arme. Les classes admissibles sont décrites dans le catalogue ; une cible inadmissible ne reçoit aucun tir secondaire, ce qui est différent d’une probabilité de toucher de 0 %.


Les noms des cibles de contre sont ceux des familles génériques de coque, communs aux trois rangs. Par exemple, la famille Capital couvre Vaisseau capital, Dreadnought et Vaisseau amiral. Les défenses au sol partagent une famille générique de cible lorsqu’aucune relation particulière à une tourelle n’est définie. La classe cible Chasseur regroupe le Chasseur standard, le Chasseur furtif et l’Intercepteur, à leurs trois rangs. Le Chasseur lourd reste séparé. Un contre exprimé envers ce groupe s’applique à chacun des trois types ; R14 utilise des relations explicites pour distinguer leurs rôles parmi les neuf classes étudiées ; leurs profils de tir offensifs et leurs statistiques restent propres à chaque coque. Lorsque des anciennes relations vers ces membres avaient des coefficients différents pour un même tireur, la classe reprend le coefficient le plus fort, sans cumul.


## R14 Alpha — règles de combat en vigueur depuis le 5 novembre 2026

Cette section remplace les anciens réglages de combat décrits plus haut. Chance = borne15–95(borne15–95(borne15–95(précision + tracking + correction arme − évasion − 15) × contre) + 30 × écart de rang). Le contre multiplie également les dégâts. La signature pondère les cibles, avec priorité aux unités dont structure ou bouclier est entamé. La cible principale reste verrouillée pendant le tour tant qu’elle survit ; les secondaires gardent leur catégorie admissible et bénéficient aussi du tir rapide.

Les dégâts d’un tour sont multipliés par ((HP + bouclier restants)/(HP + bouclier initiaux))². Un bouclier encore positif récupère 5 % de son maximum par round ; à zéro il ne recharge plus pendant cette bataille. Sous 25 % de structure, chaque prochain tir qui touche teste avant ses dégâts une explosion de probabilité max(0, 1 − HP/(0,25 × HP_initial)). Pas d’explosion sur tir raté. Les excédents ne se transfèrent pas à une autre coque.

Les neuf classes R14 ont une croissance de combat 1/1,5/2,25 ; les coûts par rang et les autres facteurs de mobilité/construction restent séparés. Une escorte est une composition financée par remplacement, sans interception ou interposition automatique. Un round reste une minute, dix rounds au maximum ; si les camps subsistent à la limite, le défenseur gagne officiellement. Les HP individuels ne sont pas persistés entre batailles.

Le Bombardier inflige ×1,30 contre les défenses au sol aux trois rangs. Le même coefficient s’applique à la chance de toucher avant l’écart de rang, dans les bornes existantes. Les coefficients antérieurs du Cuirassé (×1,15) et du Capital (×1,30) ne changent pas ; les coûts et contres navals du Bombardier restent inchangés.

Les statistiques, prix, coefficients, preuves de transposition et précautions de déploiement R14 sont détaillés dans [BALANCING_R14.md](EQUILIBRAGE.md#combat-r14).


## Vitesses de voyage — 5 novembre 2026

Le Capital est la classe la plus lente à rang et recherches comparables. La durée dépend des distances réelles de la carte, de la position de départ et d’arrivée, des deux vitesses minimales de la flotte (dans le système et entre systèmes), des recherches, du rang, du pourcentage de départ et du multiplicateur de l’univers. Elle est arrondie à la seconde supérieure. Le retour utilise la même durée de trajet enregistrée à l’aller ; les stationnements, combats et temps de traitement sont supplémentaires.

Un trajet prend au minimum 60 secondes, même dans un univers accéléré ou avec des recherches. Une flotte exclusivement composée de sondes voyageant dans le même système dispose d’un minimum de 30 secondes. Ajouter un autre type de vaisseau rétablit le minimum de 60 secondes et la vitesse du plus lent. Au rang basique sans recherche et à 100 %, le trajet local le plus long (position 1 → 9) prend 60 secondes en Sonde et 3 600 secondes en Capital. Sur les cartes du projet et la grille générée maximale de 100 systèmes, les trajets intersystèmes de référence ne dépassent pas 3 minutes en Sonde et 12 heures en Capital. Les bonus réduisent ces durées ; un départ à vitesse réduite peut les dépasser. Les vitesses et exemples intersystèmes sont détaillés dans [SPEED_BALANCE_2026-11-05.md](EQUILIBRAGE.md#vitesses-et-autonomie).


Ordre de vitesse adopté : Capital → Cuirassé → Colonie → Transport → Bombardier → Destroyer → Croiseur → Frégate → Chasseur lourd → Chasseur → Corvette → Furtif → Intercepteur → Sonde. Chaque classe possède un réservoir dédié de 16 000 H au rang basique, suffisant pour le plus long aller-retour des cartes de référence à 100 %, sans utiliser la soute. Les consommations par distance sont inchangées ; les recherches et rangs peuvent augmenter le réservoir. Le carburant nécessaire doit toujours être présent et chargé au départ ; le stationnement consomme en supplément.


## Soutes des vaisseaux — 5 novembre 2026

Les capacités de fret basiques sans recherche, distinctes des réservoirs dédiés, sont :

| Classe | Capacité de soute |
|---|---:|
| Sonde | 0 |
| Intercepteur | 100 |
| Chasseur | 200 |
| Bombardier | 200 |
| Furtif | 300 |
| Chasseur lourd | 500 |
| Corvette | 1 000 |
| Colonie | 1 000 |
| Frégate | 1 500 |
| Destroyer | 2 500 |
| Croiseur | 4 000 |
| Cuirassé | 8 000 |
| Capital | 15 000 |
| Transport | 50 000 |

Les rangs multiplient ces capacités par 1 / 1,25 / 1,5625 avec arrondi supérieur, puis la recherche Soute compartimentée apporte son bonus. La Sonde conserve une capacité nulle. Les cartes de vaisseaux affichent la capacité effective du rang, recherches comprises. La soute sert au fret et au butin ; le carburant de mission utilise d’abord les réservoirs dédiés et n’emprunte la soute que si leur capacité ne suffit pas.


## Kit de colonisation par rang — 5 novembre 2026

À la fondation d’une colonie vierge, les stocks du kit initial sont multipliés par le rang du vaisseau colonisateur consommé : ×1 / ×1,25 / ×1,5625. Le rang disponible le plus bas est consommé en priorité ; aucun cumul entre vaisseaux. Les stocks conservent les fractions et respectent la capacité de stockage. Aucun bâtiment ou bonus permanent de production n’est ajouté.


## Production des bâtiments — cadence A, 5 novembre 2026

En position 5, dans un univers ×1 et à plein rendement, les bases de production du rang Basique, avant bonus composé, sont de 360 métal/h, 180 nano/h, 720 hydrogène/h, 90 antimatière/h et 1 080 électricité/h pour la centrale. Le métal, le nano et l’hydrogène consomment chacun 180 électricité/h par niveau Basique. Le piège consomme directement 90 électricité/h par niveau Basique et 4 hydrogène par unité d’antimatière effectivement produite. Les rangs s’additionnent et gardent les facteurs de production et consommation **1 / 1,5 / 2,25**. Les bonus de position et la production passive restent appliqués séparément. Les prix du niveau 1 Basique sont de 384 M + 208 N pour les trois producteurs directs, 288 M + 156 N pour le piège et 1 152 M + 624 N pour la centrale. Les durées du niveau 1 Basique sont respectivement 1 min, 2 min et 2 min ; les courbes de niveaux et les réductions sont conservées. Le prix des rangs avancés du réacteur et de la centrale dépend de leurs prérequis. Le [bilan historique du 9 novembre](EQUILIBRAGE.md#référence-historique--production-du-9-novembre-2026) précise les 84,16 jours de construction cumulés jusqu’au niveau 80 et les limites de cette mesure.

Avant le rééquilibrage du 25 novembre, les coûts des seuls producteurs croissaient par niveau de ×1,12 jusqu’à 20, ×1,09 jusqu’à 40, ×1,035 jusqu’à 80, ×1,015 jusqu’à 120, puis ×1,01. Les premiers prix, profils de ressources par rang et arrondis par ressource restent utilisés. Les durées et les effets des laboratoires, chantiers, bunkers et stockages ne reçoivent pas le nouveau facteur de production ; leur calcul reste séparé.

Une usine dont la production ne peut plus être stockée suspend ses consommations inutiles. Les raffineries et centrales continuent de fournir les besoins immédiats des autres usines lorsque leurs propres stocks sont pleins. Les pénuries et remplissages sont calculés pendant toute la période écoulée ; les petites erreurs numériques ne doivent pas interrompre ce calcul. Les débits nets affichés excluent les excédents impossibles à stocker.

Les valeurs et résultats de référence, la distinction entre délai d’accès et remboursement, les données existantes et les vérifications sont détaillés dans [BUILDING_BALANCE_2026-11-05.md](EQUILIBRAGE.md#production-des-batiments). Les surcharges d’univers peuvent toujours remplacer les valeurs de base. Les coûts et durées déjà payés en file restent conservés.

### Bunker de défense — décision du 16 novembre 2026

Dans chacun des trois rangs, le bunker demande uniquement une mine de métal
niveau 1 et un atelier de nanofabrication niveau 1 de ce même rang. Aucun chantier,
laboratoire ou recherche supplémentaire n’est requis. Le chantier spatial conserve
ses conditions distinctes : métal 2 + nano 2 du rang demandé.

Cette décision remplace la grille du 15 novembre (chantier 3 + laboratoire 2).
Coûts, durées, plafonds et bonus de structure des défenses restent conservés.
Les accès déjà acquis restent permanents ; les nouveaux accès suivent les conditions
assouplies, sans réinitialisation ni nouvelle reprise de données. Les surcharges
d’univers restent prioritaires.

### Limites par défaut des univers — 5 novembre 2026

Le nombre maximum de vaisseaux par flotte est fixé par défaut à **1 000 000** et le plafond de planètes à **31**. Ces valeurs s’appliquent aux profils de référence et aux nouveaux univers. Les réglages personnalisés d’un univers restent prioritaires. Le plafond de planètes ne modifie pas les prérequis de recherche de colonisation.

### Capacité d’inventaire — 5 novembre 2026

L’inventaire peut contenir **100 objets disponibles** par joueur et par univers, boosts et boîtes de ressources confondus. Les récompenses supplémentaires restent en attente et rejoignent l’inventaire de la plus ancienne à la plus récente lorsqu’une place se libère. Cette capacité remplace la limite précédente de 3 emplacements.

**Boosts d’inventaire** : seules les commandes actives de la file de construction, de recherche ou de production (vaisseaux et défenses inclus) peuvent être accélérées. Les missions de flotte restent éligibles aux récompenses prévues mais pas à l’utilisation d’un boost. Une accélération de flotte déjà enregistrée n’est ni annulée, ni recalculée, ni remboursée.

#### Témoins d’action et de cadeau

Dans la navigation, le nombre rouge de recrutement signale les candidatures
encore à décider pour le commandant et les titulaires de la permission de
recruter, ou les invitations reçues pour un joueur sans guilde. Lire la page
ou une conversation ne modifie pas ce nombre. Les messages ordinaires gardent
leur indicateur de lecture distinct.

Le nombre à côté d’Inventaire indique les cadeaux nouveaux, encore disponibles
ou en attente, de l’empire actif. Il ne compte ni les anciens objets de stock,
ni les objets utilisés ou dont le délai est passé. « Marquer les cadeaux comme
vus » enlève uniquement le témoin des cadeaux signalés sur cette page, sans
les ouvrir ni les utiliser. Les cadeaux arrivés depuis le rendu restent signalés.

#### Boîtes de ressources — décisions du 8 novembre, réalisation du 9 novembre 2026

Le contenu annoncé est fixe, sans tirage aléatoire, et reste figé dans chaque
boîte attribuée. Les modèles et plafonds sont réglables par univers. Valeurs
initiales :

| Format | Métal | Nano | Hydrogène | Antimatière | Électricité |
| --- | ---: | ---: | ---: | ---: | ---: |
| Petite | 4 000 | 1 000 | 1 000 | 200 | 2 000 |
| Moyenne | 10 000 | 2 500 | 2 500 | 500 | 5 000 |
| Grande | 20 000 | 5 000 | 5 000 | 1 000 | 10 000 |

Les plafonds initiaux par boîte correspondent au grand format. Le joueur ouvre
manuellement la boîte sur une colonie lui appartenant dans le même univers.
Les vacances bloquent l’ouverture. Si un entrepôt manque de place, le joueur voit
les pertes maximales par ressource et peut changer de colonie ou confirmer. Après
confirmation, chaque stock reçoit ce qui tient et le surplus est perdu. Une perte
supérieure à l’avertissement demande une nouvelle validation.
La confirmation indique la perte estimée à l’ouverture de l’avertissement et, si la production la fait évoluer, le plafond calculé une minute plus tard. Ce plafond reste opposable à la confirmation. Une même ouverture ne peut pas être appliquée deux fois.

Un événement attribue au maximum un objet par participant éligible : un boost
ou une boîte. Le plafond quotidien des boosts de missions à risque est conservé.

#### Défis collectifs guidés — 9 novembre 2026

L’administration planifie un défi dans un univers précis, après aperçu des
dates UTC, de l’objectif et de la récompense. Les objectifs utilisent les
mécanismes existants : découvertes personnelles de nouveaux systèmes, dégâts
en combat ou ressources livrées entre ses propres colonies. Les dons entre
joueurs restent exclus des contributions de transport.

La récompense est attribuée à la clôture uniquement si l’objectif collectif
est atteint. Chaque bénéficiaire doit avoir contribué positivement et atteint
le minimum individuel configuré ; zéro comme minimum signifie toute contribution
positive. Chaque bénéficiaire gagne un boost ou une boîte, sans cumul des deux.
Le contenu de la boîte est annoncé et figé dès la planification du défi.

Le démarrage respecte la limite existante de deux événements de boosts simultanés
par univers. Une attente ne décale pas la fin annoncée ; un défi dont la fenêtre
est entièrement passée ne démarre pas tardivement. Les rotations hebdomadaires
conservent leurs règles et récompenses existantes.

#### Bonus et annonces guidés — 9 novembre 2026

Les modèles guidés permettent une annonce ou un bonus temporaire de production,
global ou limité à un univers. Les dates de début doivent être à venir lors de
la confirmation ; aucune production rétroactive ne peut être créée par ce parcours.
Les bonus reprennent les bornes existantes, de 0,01 à 500 %, et leur cumul reste
multiplicatif. La définition annoncée est conservée après publication ; son
remplacement passe par une annulation motivée et un nouvel aperçu.

L’annulation d’un bonus guidé arrête ses effets futurs, tout en conservant la
production acquise avant l’annulation, y compris pour un joueur qui ne s’est
pas connecté pendant cette période. Une annulation de défi ne retire aucun
objet déjà gagné et interrompt aussi l’annonce qui l’accompagne.

### Classement des guildes — 5 novembre 2026

Le classement des guildes additionne les points actuels de bâtiments, recherches et flotte de leurs membres. Le total détermine le rang. La médiane des scores individuels complète le total : pour un effectif pair, elle est la moyenne des deux scores centraux. Une guilde sans membre compte zéro point et une médiane de zéro. Le classement joueur propose les cinq catégories décrites dans « Classements quotidiens » ; les points totaux restent sélectionnés par défaut.


## Historique : répartition des vaisseaux du 5 novembre 2026

La grille actuellement en vigueur figure en tête de ce document. Les paragraphes suivants décrivent uniquement cette étape historique.

Les 14 classes utilisent les parts de valeur économique suivantes : T1 60 % métal / 40 % nano ; T2 45 % métal / 45 % nano / 10 % antimatière ; T3 30 % métal / 50 % nano / 20 % antimatière. Le calcul pondère M×2, N×4, A×8 et H×1. Ces pourcentages ne représentent pas les quantités brutes. Les arrondis des trois ressources sont répartis pour conserver exactement la valeur de chaque profil de rang avant recherche ; les profils réels font foi.

Le carburant des missions est séparé des coûts de construction, qui n’ajoutent pas d’hydrogène. Les recherches de réduction des coûts s’appliquent ensuite avec leurs arrondis par ressource. Les nouvelles commandes utilisent les nouveaux prix ; les coûts déjà payés restent enregistrés. Les profils, budgets conservés et limites sont détaillés dans [SHIP_COSTS_2026-11-05.md](COUTS.md).


## Historique : grille commune des prix du 5 novembre 2026

Les parts ci-dessous sont remplacées par la décision du 16 novembre ; les garanties
de conservation des paiements et de priorité des surcharges restent applicables.

Cette grille remplace les anciennes répartitions des ressources pour tous les bâtiments, vaisseaux, défenses et recherches : **T1 M60/N40 ; T2 M45/N45/A10 ; T3 M30/N45/A20/H5**, en parts de valeur économique, avec M×2, N×4, A×8 et H×1. Les quantités sont entières, et la valeur réelle de chaque profil de rang de base est conservée. Les prix réels aux niveaux suivants ou après recherche suivent les mêmes courbes et arrondis par ressource qu’auparavant ; de petits écarts de valeur dus à ces arrondis sont possibles.

L’hydrogène industriel est payé à la commande et ne remplit pas un réservoir de vaisseau. Les carburants de mission et les consommations d’hydrogène du réacteur restent des dépenses distinctes. Les profils, contrôles et effets sur la progression sont détaillés dans [COUTS.md](COUTS.md). Les coûts déjà payés en file restent enregistrés et les réglages personnalisés d’univers restent prioritaires.

### Prérequis initial du Fighter

Le Fighter basique (Chasseur de patrouille) nécessite un chantier spatial basique niveau 1 pour son déblocage initial. Les accès déjà acquis sont conservés selon les règles du registre de déblocage.


## Transports entre joueurs — décision validée le 7 novembre 2026

Les transports peuvent viser une colonie connue du même univers et de la même galaxie, appartenant au joueur ou à un destinataire qui les autorise. Le réglage de compte accepte tout le monde, les alliés uniquement (défaut) ou personne ; les propres colonies restent autorisées. Les alliances suivent les relations existantes (guilde ou contrat accepté, hors relation ennemie).

L’expéditeur fournit ressources, vaisseaux et carburant aller-retour. L’autorisation est contrôlée au départ et à l’arrivée. Un changement de propriétaire ou un refus à l’arrivée entraîne le retour de la cargaison. La livraison respecte chaque capacité de stockage ; le surplus revient, y compris entre les propres colonies. Au retour, le dépôt reste soumis au stockage de la colonie de départ : un éventuel dépassement y est perdu et indiqué dans le rapport.

Les dons entre joueurs ne donnent aucun boost et ne font progresser aucun objectif. La protection débutant reste inchangée. Aucun plafond de dons, taxe ou sanction automatique sur une adresse IP n’est ajouté. Les deux participants disposent du rapport économique ; l’administration dispose de l’historique paginé des transports pour une vérification humaine.


### Administration des rotations hebdomadaires

Les administrateurs gèrent les modèles par univers dans `/admin/rotations`.
La liste mène à des pages de création et de modification ; une modification du
modèle concerne uniquement les futures programmations, avec refus d’une fiche
devenue périmée. Les valeurs de référence restent inchangées.
Les modèles actifs alternent selon l’indice hebdomadaire du moteur. Une seule
occurrence est réservée par univers et semaine ; une occurrence annulée conserve
cette réservation et ne peut pas être recréée cette semaine. Désactiver un modèle
empêche ses nouvelles programmations mais n’annule pas les occurrences déjà
créées. Leurs règles sont figées ; contributions et récompenses acquises restent
conservées lors d’une annulation, sans nouvelle attribution après annulation.
Le plafond simultané, le lundi à 00 h UTC et les règles de récompense historiques
s’appliquent toujours. Les joueurs retrouvent les rotations dans Événements → Défis et rotations.

## Saisie des quantités et boutons Max

- Construction de vaisseaux et défenses : Max utilise les ressources disponibles et le coût réel du lot, sans limite fixe de 100. Le chantier partage un plafond de 10 commandes à l’ajout entre vaisseaux et défenses, par colonie. La file de bâtiments a son propre plafond de 10 par colonie. Au-delà de ce plafond, les commandes déjà payées restent en place et aucun ajout n’est possible tant que la file ne compte pas moins de 10 commandes.
- Composition d’une flotte : Max sélectionne les unités disponibles d’un type et d’un rang, dans la place restante sous le plafond de flotte de l’univers.
- Fret : un Max par ressource conserve les autres chargements et tient compte du stock, du carburant réservé et de la soute libre. La limite de saisie de 100 000 000 par ressource est conservée.
- Les quantités excessives sont réduites automatiquement, avec un message d’ajustement et une vérification serveur au moment de l’action. Les entrées négatives, non entières ou inconnues restent refusées ; un maximum nul ne permet pas d’achat ni de départ sans vaisseau.

## File de recherche commune

Dans un même univers, toutes les colonies d’un joueur partagent une file de recherche plafonnée à 10 commandes au moment d’un ajout. Chaque ajout exige un laboratoire du même rang au niveau 1 ou plus sur la colonie choisie. Il paie ses coûts sur cette colonie et utilise ses bonus de laboratoire pour le devis. Les niveaux déjà en file sont pris en compte depuis toutes les colonies. Les recherches sont exécutées successivement ; la durée des nouvelles commandes suit les bonus du laboratoire d’origine, jamais celui de la colonie affichée.

Les recherches historiquement actives en parallèle terminent leur travail sans être annulées. La suite de la file attend la fin de toutes ces recherches, puis fonctionne avec une seule recherche active. Les univers restent indépendants. Annulations, remboursements, vacances et captures conservent leurs règles ; la colonie d’origine de chaque commande reste enregistrée.


## Classements quotidiens — 10 novembre 2026

Dans **Profil → Apparence du profil**, le joueur peut équiper jusqu’à deux titres acquis et une bannière. Les titres équipés s’affichent sous son pseudo dans le classement et sur sa fiche publique dès l’enregistrement. La bannière équipée, par exemple « Nuit polaire », apparaît en fond de ligne et dans l’en-tête de la fiche publique, derrière le pseudo et les titres.

Le classement distingue les membres de la même guilde, les guildes alliées, les pactes actifs et les liens personnels. Un pacte peut être affiché avec une alliance ou une rivalité personnelle. Le badge « En vacances » donne accès à l’échéance. Quand le suivi des empires oubliés est activé, « Inactif » signale 14 jours sans visite dans cet univers, hors vacances. Le statut disparaît à la reprise d’activité. Les règles de pillage et de conquête s’appliquent normalement.

Tous les classements (points totaux des joueurs, bâtiments, recherche, flotte, nombre de colonies, crédits et guildes) sont figés entre deux calculs quotidiens, à 00 h et 12 h en Europe/Paris, en tenant compte des changements d’heure. Les points affichés dans l’aperçu de colonie et l’API utilisent le même instantané. Les points sont la valeur des biens actuellement possédés aux prix catalogue courants : (H + 2 M + 4 N + 8 A) / 200, hors électricité, arrondie à l’inférieur séparément pour bâtiments, recherches et militaire (vaisseaux et défenses). En Alpha, le premier calcul pondéré se compare au dernier classement disponible, même établi avec l’ancienne formule. Les scores utilisés pour les protections et combats restent en temps réel : ratio d’attaque 5:1 inchangé ; protection débutant tant que les 7 jours ne sont pas écoulés et que le score reste inférieur à 7 500.

Les évolutions comparent les deux derniers calculs persistés : points actuels moins points précédents ; rang précédent moins rang actuel (une place gagnée est positive). Dans le tableau joueur, gains et pertes sont signés et entre parenthèses sur la même ligne que la valeur ; une valeur inchangée s’affiche « (=) ». Sans référence précédente, l’évolution s’affiche « (—) ». La présentation des guildes et de l’aperçu conserve ses marqueurs existants. Les nouveaux inscrits et guildes entrent au prochain calcul. Les noms, relations diplomatiques et protections restent actuels ; les joueurs supprimés ou devenus empires oubliés et les guildes archivées ne sont plus affichés, sans renuméroter les rangs avant l’échéance suivante.

Le classement joueur propose points totaux (défaut), bâtiments, recherche, flotte, nombre de planètes et crédits disponibles. Chaque catégorie possède son rang et sa variation ; le tri global, décroissant par défaut ou croissant après un second clic sur l’en-tête actif, précède la pagination de 50 lignes. Catégorie et sens sont conservés entre les pages et au retour d’un signalement. Le rang officiel de l’instantané ne change pas avec le sens d’affichage. Les égalités conservent l’ordre des rangs de l’instantané, issu du départage existant par pseudonyme, sans casse. Aucune variation de rang bâtiments/recherche n’est inventée pour les anciens instantanés : elle devient disponible après deux calculs contenant ce rang.

Le relais existant déclenche les calculs même sans visite. Les lectures rattrapent une échéance manquée ; après interruption, le calcul utilise l’état disponible à la reprise et indique son heure réelle, sans inventer les états des jours manqués. Les constructions et recherches déjà terminées sont réconciliées avant le calcul. Les instantanés et leurs dernières évolutions sont conservés en base, avec uniquement des identifiants et valeurs de classement, sans copie des identités.


## Ressources initiales et production passive — décision du 10 novembre 2026

Les valeurs suivantes s’appliquent après activation du nouvel équilibrage. La production horaire de référence est celle d’une position 5, dans un univers ×1, sans bâtiment ni événement.

| Ressource | Première planète | Colonie basique | Intermédiaire | Supérieure | Stockage de base | Production / h |
|---|---:|---:|---:|---:|---:|---:|
| Métal | 10 000 | 5 000 | 6 250 | 7 812,5 | 10 000 | 10 |
| Électricité | 10 000 | 0 | 0 | 0 | 10 000 | 0 |
| Antimatière | 10 000 | 5 000 | 6 250 | 7 812,5 | 10 000 | 10 |
| Hydrogène | 10 000 | 5 000 | 6 250 | 7 812,5 | 10 000 | 80 |
| Nano | 10 000 | 5 000 | 6 250 | 7 812,5 | 10 000 | 10 |

Les taux métal et nano du tableau intègrent la décision du 13 novembre 2026 : 10/h chacun. Les anciennes valeurs 40/20 restent conservées dans l’historique des décisions.

Le rang du vaisseau réellement consommé détermine le kit : ×1, ×1,25 ou ×1,5625 ; les fractions sont conservées et l’électricité reste nulle. Les stocks initiaux ne sont attribués qu’à la création des ressources. Aucun stock existant n’est redistribué ou remis à zéro.

Les capacités et productions de base concernent aussi les planètes existantes. Les usines, consommations, bonus de stockage, modificateurs de position, multiplicateurs d’univers et événements gardent leurs règles. Les taux du fichier de règles sont stockés par seconde (valeur horaire / 3 600).

Transition validée : tout surplus déjà présent au-dessus de la capacité est conservé et utilisable pour les dépenses. Aucun apport positif supplémentaire n’est crédité tant que la ressource dépasse la capacité ; la production reprend sous le plafond. Les remboursements et dépôts ne doivent jamais écrêter le stock déjà présent. Les consommations industrielles continuent selon les règles existantes. Les jauges restent bornées à 100 %, mais la quantité affichée montre tout le stock.

Clarification validée le 10 novembre 2026 : les règles courantes s’appliquent directement à toute la période de production non encore matérialisée, depuis le dernier calcul enregistré, y compris lorsque cette période précède le déploiement. Ce recalcul rétroactif des gains en attente est accepté ; il peut augmenter ou réduire ces gains selon les nouveaux taux. Il ne réinitialise pas les stocks déjà enregistrés. Les frontières d’événements et de vacances restent prises en compte. Cette décision remplace le mécanisme initial de bascule par date : `RESOURCE_BALANCE_EFFECTIVE_AT` n’est plus utilisée et aucun ancien régime n’est maintenu.

Aucune migration ni réécriture globale des stocks n’est requise. Les nouvelles valeurs sont actives dès que le nouveau code est exécuté, pour les univers existants comme pour les nouveaux. Lors du déploiement, arrêter les anciens processus applicatifs puis démarrer tous les services avec la même version ; aucun paramètre d’activation supplémentaire n’est nécessaire.

### Rivalités personnelles et paix — 11 novembre 2026

Une déclaration personnelle suffit à rendre deux joueurs rivaux, sans obligation de guilde. La déclaration remplace leur alliance personnelle éventuelle. La paix exige une proposition puis l'acceptation de l'autre joueur : proposer, retirer ou refuser une offre ne met pas fin à l'hostilité.

L'appartenance à une même guilde ou à des guildes alliées prime : la rivalité personnelle reste enregistrée mais n'autorise pas les combats entre ces alliés. Elle reprend ses effets si ces liens collectifs cessent, tant qu'une paix personnelle n'a pas été acceptée. Inversement, une paix personnelle ne neutralise pas une hostilité entre guildes.

Après la paix, les joueurs sont neutres au niveau personnel et peuvent proposer une nouvelle alliance, avec consentement réciproque. Aucun bonus, butin supplémentaire ou renseignement privé n'est accordé par une rivalité. Les restrictions de combat et les préférences de réception des transports restent applicables. La conversion d'un empire termine aussi les rivalités et offres de paix.

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

## Reconstruction des empires oubliés — 11 novembre 2026

Seuls les nouveaux empires convertis avec une référence enregistrée sont concernés.
Les PNJ classiques et les empires déjà oubliés sans cette référence sont exclus.

- Chaque planète conserve ses effectifs à la conversion par modèle et rang,
  y compris ses propres flottes rappelées ou déjà en retour. Les commandes payées
  antérieures terminent normalement ; elles ne relèvent pas la référence, même
  si leurs résultats la dépassent.
- Le manque est calculé après réparation partielle des défenses, en déduisant les
  survivants, les retours et les unités déjà commandées. Le chantier existant est
  conservé : les anciennes commandes passent avant les nouvelles reconstructions.
- Tous les modèles de défenses et de vaisseaux sont reconstructibles à leur rang
  d'origine, sans croissance autonome au-delà de la référence.
- La reconstruction commence au plus tôt cinq minutes après la résolution du
  dernier combat sur cette planète. Un nouveau combat suspend le travail payé,
  conserve son avancement et invalide les anciennes échéances ; aucun progrès
  ni livraison autonome pendant le combat. La reprise attend à nouveau cinq minutes
  après sa résolution, sans second prélèvement.
- Une unité est commandée à la fois au chantier partagé, avec les coûts et durées
  normaux, les réductions acquises et les paramètres de l'univers. Les stocks de
  la planète paient la commande. S'ils sont insuffisants, la production locale
  peut permettre une nouvelle commande ultérieure.
- Les modèles finançables sont classés par coût effectif unitaire croissant :
  métal × 2 + nano × 4 + antimatière × 8 + hydrogène. À égalité, priorité à la durée
  la plus courte, puis à l'identifiant du modèle, du rang et du groupe. Un modèle
  non finançable n'empêche pas d'en reconstruire un autre. Cette priorité favorise
  la quantité sans garantir une optimisation combinatoire de toutes les ressources.
- La reconstruction continue pendant l'occupation, hors combat. Les forces hostiles
  reconstruites doivent être vaincues avant capture, sans remise à zéro du temps
  acquis par l'occupant victorieux. Aucun départ offensif n'est créé.
- La capture définitive arrête la reconstruction de l'ancien empire et annule
  ses commandes restantes sans remboursement. Les références historiques restent
  conservées et ne s'appliquent jamais au nouveau propriétaire.

Aucune attaque automatique, nouvelle colonisation ou nouvelle recherche n'est
ajoutée. Les recherches déjà payées à la conversion peuvent finir, puis restent figées.


## Carte V2 : confidentialité des flottes

Les trajets d’espionnage, aller comme retour, sont visibles uniquement par le
propriétaire des sondes. Cette restriction ne modifie pas la détection éventuelle
à l’arrivée. Les flottes tierces déjà visibles sur la carte montrent leur nombre
total de vaisseaux et leurs points, sans composition détaillée. Le propriétaire
conserve les types, quantités et rangs de ses propres flottes.

Les points d’une flotte sont la somme des coûts de ses vaisseaux présents,
selon le catalogue de son univers et de chaque rang, divisée par 100 après
totalisation. Les défenses fixes et le fret ne contribuent pas à ces points.
Ce résumé courant d’une flotte est distinct du classement quotidien des joueurs.

Le cercle extérieur d’un système possédé est vert, avec les informations
intérieures conservées. Un bouton de la fiche planète revient à son système ;
la croix permet toujours de revenir à la galaxie.

### Panneau Flottes entrantes — décision du 16 novembre 2026

Les arrivées d’autres commandants vers vos colonies sont regroupées dans un cadre
séparé de vos propres vols : transports, renforts, demandes d’orbite et attaques
détectées. Les règles d’envoi, d’accueil et de détection restent inchangées.
Les sondes restent cachées pendant le trajet, même entre alliés ; composition et
fret tiers restent privés. Aucune flotte alliée visant une autre colonie n’est ajoutée.
Le [détail par mission](FLOTTES_ENTRANTES.md) décrit les origines découvertes, les
anciens états d’orbite et le suivi des missions personnelles.

### Détails privés des colonies et flottes — lot C

Les panneaux de la page Planètes affichent les vaisseaux au repos par rang et
les stocks calculés de ses colonies dans l’univers actif. Le suivi de ses vols
affiche leur composition et leur fret. Les données d’un autre empire ne sont
pas jointes à ces panneaux. Survol, ouverture au clavier et au toucher, fermeture
par Échap ; la lecture des stocks ne les matérialise pas et ne les consomme pas.
Les règles de capacité, production, déplacement et confidentialité restent inchangées.

### Ordre des colonies et journal d’absence — lot C

Une acquisition est datée lors de l’attribution initiale, d’une colonisation,
d’une conquête ou d’une capture après occupation par le propriétaire actuel.
Les anciennes dates inconnues ne sont pas inventées : les colonies concernées
conservent un ordre par identifiant indiqué comme approximatif, avant les
acquisitions datées. Les dates connues vont de la plus ancienne à la plus récente,
avec départage par identifiant. Vue générale, page Planètes et sélecteur latéral
partagent cet ordre ; à l’écran, la lecture va de gauche à droite ou de haut en bas.

Le suivi du journal commence à la première ouverture de la vue générale après
la mise à jour. Les anciennes notifications sont conservées sans être présentées
comme une absence connue. Pour les ouvertures suivantes, le journal propose les
constructions/recherches terminées, arrivées, combats, transports, espionnages
et conquêtes survenus depuis la précédente ouverture, avec maintien des événements
non consultés des absences antérieures. Une actualisation automatique ne constitue
ni une nouvelle ouverture ni une consultation. Les notifications instantanées
restent distinctes du journal d’absence.

Le journal affiche au plus cinq événements. Quand le premier est visible dans
l’onglet actif, toute la sélection de la visite est marquée comme consultée, y
compris les événements non affichés. Sans JavaScript, le bouton « Marquer le
journal comme consulté » réalise la même action. Les lignes affichées restent
lisibles pendant la visite avec JavaScript. Aucun historique n’est supprimé et
les arrivées ultérieures restent en attente. L’état est conservé côté serveur
par participation à l’univers et protégé contre les accès tiers et formulaires expirés.

### Destinations et suggestions des flottes — lot D

Les catégories dépendent de la mission : exploration = systèmes non explorés
(coordonnées seules) ; colonisation = planètes neutres découvertes et colonisables ;
transport, défense et orbite = mes planètes / planètes alliées ; attaque et espionnage =
PNJ / ennemis déclarés ; conquête JcE = PNJ ; stationnement = mes planètes.
Le premier type compatible est sélectionné par défaut. Les alliances sont
filtrées selon les droits propres à chaque mission ; un pacte défensif ne donne
pas les droits du transport. Les empires oubliés sont des PNJ. Une planète
anciennement PNJ appartenant à un joueur actif suit sa relation actuelle.

Destination personnalisée conserve la saisie de coordonnées adaptée au système
ou à la planète. La recherche propose au plus 20 coordonnées de systèmes non
explorés, sans révéler leurs planètes, propriétaires ou ressources ; pour les
missions planétaires elle propose les planètes découvertes et compatibles d’un
pseudonyme. Deux caractères minimum, jamais de résultat d’un autre univers.
Les joueurs neutres autorisés restent accessibles dans ce mode, sans extension
des permissions de la mission.
Les liens préremplis de la carte conservent le contexte de leur cible ; une cible
connue peut rester consultable en formulaire même si la prévisualisation refuse
actuellement l’envoi, notamment un destinataire qui refuse les transports.

Dans Flottes, changer la colonie active depuis le menu conserve la page
Missions, Préparer, Orbites ou À quai. Une préparation passe au hangar de la
nouvelle origine sans reprendre une composition choisie sur l’ancienne. La liste
des missions actives continue de couvrir toutes les colonies de l’univers.

Les listes et suggestions respectent les droits de mission existants. Une liste
ne remplace jamais les contrôles de la prévisualisation et de l’envoi : découverte,
origine, univers, diplomatie, vacances, composition, carburant et capacités restent
appliqués. La garnison PNJ est initialisée une seule fois, sous verrou à l’arrivée.
Une attaque classique n’engage aucune capture, même avec un vaisseau colonie.
Les récompenses générales de combat et les valeurs de jeu restent inchangées.

## Rapports personnels et corrections administratives — lot E

Les rapports visibles (combat, espionnage et transport) sont présentés ensemble,
du plus récent au plus ancien, avec 50 résultats par page après tri et filtrage.
L’archive est personnelle à la participation dans l’univers : retirer un rapport
de sa liste n’affecte pas celle d’un autre participant. Archive/restauration
individuelle ou par sélection, sans supprimer ni altérer les rapports d’origine.
Le retrait définitif depuis les archives masque le rapport pour ce joueur dans les deux listes. Les autres destinataires conservent leur accès. L’archivage n’accorde aucun droit de consultation supplémentaire.

Un administrateur actif peut attribuer un type/rang de vaisseaux existant au
stock au sol d’une colonie du joueur désigné. Motif et confirmation obligatoires,
audit avant/après et protection contre une double soumission. Le joueur, la
propriété et l’univers sont revérifiés sous verrou ; l’opération entière est
refusée si le stock entier dépasse sa capacité technique (2147483647 par ligne).
Aucun vaisseau d’une mission active, ressource, coût ou prérequis n’est modifié.

## Tags et signalements depuis le classement — lot F

Le tag de guilde comprend 2 à 5 lettres/chiffres, normalisés en majuscules. Il
est obligatoire à la création et unique dans chaque univers. Seul le commandant
actuel peut le choisir ou le mettre à jour. Aucun tag ancien n’est inventé.
Le préfixe [TAG] Commandant s’affiche uniquement au classement ; une ancienne
guilde sans tag et un joueur sans guilde ne produisent pas de crochets vides.

Le classement permet de signaler un autre joueur du même univers. Motifs :
Pseudonyme inapproprié, Harcèlement, Suspicion de triche ou Autre. Commentaire
facultatif limité à 1000 caractères. Les informations jointes sont le joueur et
son pseudonyme au moment de l’envoi, l’univers, l’auteur/date et le contexte
catégorie/rang consulté. Le serveur ne reprend pas un rang soumis par le navigateur.
Seule la modération consulte ces signalements et peut les classer ou les marquer
traités ; aucune sanction n’est automatique. Les preuves restent conservées,
y compris après une identité devenue indisponible. Le rejeu du même formulaire
ne crée pas de nouveau signalement. Les signalements de messages sont préservés.

## Rappel volontaire des flottes — lot G

Le propriétaire peut rappeler les huit missions à l’aller, y compris espionnage
et colonisation, ainsi qu’une flotte en orbite ou en attente de décision d’orbite.
Les retours, occupations, combats et arrivées dont l’échéance est atteinte sont
exclus. Un combat en cours interdit le rappel même si le statut de flotte est ancien.

Lorsque l’origine appartient toujours au joueur, le retour suit le chemin déjà
parcouru. La progression réelle tient compte des changements d’échéance et boosts ;
les coûts et temps du trajet de référence sont conservés pour la portion de retour.
Le carburant de l’aller est consommé au prorata des portions parcourues.
En orbite, le carburant de stationnement est consommé au prorata du séjour effectué.

Lorsque l’origine est perdue, le joueur choisit une autre colonie du même univers.
Le retour est recalculé depuis la position atteinte avec les vaisseaux, technologies
et paramètres courants de l’univers. Seul le carburant réservé restant est utilisable :
aucun prélèvement dans le fret d’hydrogène ni à distance dans les stocks de colonies.
Le refus est sans effet si le carburant ou une destination autorisée manque.

Le rappel n’exécute pas la mission initiale et préserve les vaisseaux, le fret
jusqu’au retour. Le carburant inutilisé est perdu à quai. Une nouvelle programmation invalide les anciens messages ;
une double confirmation ne crée aucun double retour. L’origine historique reste
conservée ; propriété et univers de la colonie de retour sont revérifiés à l’arrivée.
Le dépôt applique les capacités de stockage habituelles, avec débordement tracé.

Aucune donnée ancienne n’est inventée : sans progression et paramètres fiables,
le rappel est indisponible avec explication et la mission continue normalement.
Les rappels administratifs et de conversion conservent leurs règles distinctes.
La carte n’élargit pas les droits de visibilité et masque aux tiers la route
d’un repli vers une colonie dont le système n’a pas été découvert.

## Préavis d’attaque et visibilité — lot H

Pour le défenseur, une attaque en trajet aller n’est visible qu’à partir de sa
détection. Niveau effectif = somme simple des niveaux d’Analyse tactique,
Cartographie des opérations et Réseau de connaissance du champ de bataille.
Préavis = min(1 800 + 300 × niveau effectif, 75 % de la durée du trajet aller),
en secondes entières, arrondi vers le bas. Pour un trajet de 100 minutes, le
préavis est de 30 minutes sans recherche, 60 minutes avec 6 niveaux et
75 minutes à partir de 9 niveaux.
Le trajet pris en compte va du départ enregistré à l’arrivée actuelle ;
un boost ou un ajustement d’échéance recalcule donc immédiatement ce seuil.
Aucun coût, temps de voyage ou effet de boost n’est modifié par cette lecture.

La cible, l’échéance/compte à rebours, le commandant, le nombre de vaisseaux
et leurs points sont visibles dès détection. L’origine n’est affichée que si son
système est découvert ; aucun identifiant, nom ou coordonnée d’origine inconnue
n’est transmis. La composition, le fret, le suivi technique et l’heure de départ
d’une attaque entrante restent privés. Une arrivée en retard de traitement reste
signalée comme imminente tant que la flotte est encore à l’aller.
Une flotte rappelée ou passée en combat n’est plus une attaque entrante en trajet ;
les affichages existants du combat et des retours sont conservés.

Le propriétaire suit ses missions complètes. Les sondes restent privées hors de la
carte de leur propriétaire et des membres actuels de la même guilde ; leur
détection à l’arrivée est inchangée. Sur la carte interactive, le filtre
« Afficher les flottes de guilde » couvre toutes les missions ordinaires, à
l’aller, au retour ou sur place, si les deux systèmes sont déjà découverts.
Un point de repli non découvert reste masqué. Le suivi de guilde est limité au
commandant, à la mission, à son trajet et à son échéance ; ni composition,
ni effectifs, ni fret. Les anciens accès du destinataire et des autres alliés
ne changent pas. Les trajets rattachés aux dossiers privés de la campagne pirate
ne sont pas partagés, pas plus que les renseignements du rapport de sonde.
Les mouvements non offensifs sont visibles au propriétaire, au destinataire
et aux alliés du propriétaire dans les limites de découverte. Le destinataire
voit sa propre cible, mais une origine inconnue reste masquée. Pour les alliés
observateurs, les systèmes d’origine et de destination doivent être découverts.
Leur périmètre préexistant de suivi des attaques alliées reste soumis à ces
découvertes ; le seuil protège le destinataire d’une attaque.
Aucun changement aux relations ou conditions d’attaque, défense et stationnement.

Les trois plafonds de référence sont 5/5/5, soit 15 niveaux et 105 minutes de
préavis théorique avant application de la limite liée au trajet.
Les surcharges propres à l’univers sont résolues comme dans la file de recherche.
La somme pour le préavis n’altère pas la pondération actuelle de l’espionnage.


### Demandes d’orbite au départ — lot I
Pour les nouveaux départs, la mission Mise en orbite cible une autre colonie
de votre empire ou une planète d’un joueur actif du même univers, allié ou neutre,
dans un système déjà découvert.
Les planètes ennemies, PNJ ou sans propriétaire sont interdites. L’accord sur vos propres colonies ou chez un allié
est automatique ; le propriétaire neutre doit accepter avant l’arrivée.
La demande expire à l’arrivée réelle, y compris après un boost ou un changement
autorisé d’échéance. Accepter ne déplace pas la flotte : elle poursuit son trajet.
Refuser ou retirer la demande déclenche le retour depuis la position atteinte,
selon les règles de rappel (carburant réservé uniquement, fret protégé).
Sans réponse à l’arrivée, la flotte rentre ; aucune attente indéfinie n’est créée.
Le propriétaire initial et les relations sont revérifiés à l’arrivée. Un accord
automatique exige toujours une alliance ; un accord manuel reste valable entre
joueurs neutres ou alliés, jamais ennemis. Un changement de propriétaire invalide
l’accord. Les protections existantes, dont les vacances, restent appliquées.
Une orbite neutre acceptée défend son hôte comme une orbite alliée ; cela
n’élargit pas les permissions de la mission Défense envoyée directement.
Les missions antérieures, sans demande versionnée enregistrée au départ,
conservent pour ce voyage leur acceptation à l’arrivée, sans échéance inventée.

Pour les vacances de l’hôte, les stations déjà acceptées continuent leur durée
normale. Les anciennes demandes en attente après l’arrivée sont renvoyées
à l’activation des vacances ; les nouvelles demandes en trajet sont
revérifiées à l’arrivée et ne peuvent pas commencer une orbite pendant les vacances.


### Abandon d’une colonie — lot J
L’abandon exige une confirmation par le nom exact de la planète, après bilan des
pertes. La planète initiale peut être abandonnée si une autre colonie reste dans
le même univers ; une planète située dans un autre univers ne compte pas.
La planète redevient vierge : bâtiments, ressources, défenses et vaisseaux présents
sont perdus. Les files locales et leurs dépendances locales sont annulées sans
remboursement. Une commande d’une autre colonie dépendante bloque l’abandon,
sans annulation implicite sur cette autre colonie. Les autres files continuent.
Aucun actif ni stock n’est transféré vers une autre planète. Les recherches
acquises et les accès déjà acquis de la participation conservent leur portée
existante ; ils ne sont pas transmis à un autre propriétaire.
Toute flotte active liée par son origine, sa destination ou son repli bloque
l’abandon, ainsi que les orbites, demandes d’orbite, attaques, occupations et
combats encore ouverts. Un refus ne révèle aucun détail d’une mission invisible.
Une modification des bâtiments, défenses, vaisseaux ou commandes impose de relire
le bilan. Les stocks évoluent jusqu’à la confirmation et sont perdus à cet instant.
La planète et les rapports historiques sont conservés. Les anciennes échéances
ne peuvent pas recréer les commandes ou actifs supprimés. Une recolonisation
reste soumise aux règles normales, dont le vaisseau colonisateur et le quota.

Précision de compatibilité des orbites : une planète autrefois PNJ, actuellement
contrôlée par un joueur actif, est une colonie de ce joueur. Son propriétaire
actuel prime sur le drapeau historique PNJ, comme pour les destinations (lot D).

Après abandon, aucun nouveau kit de ressources n’est attribué si le même joueur
recolonise cette planète dans cet univers, y compris depuis une autre participation
du même compte. Un autre propriétaire conserve le kit normal. Vaisseau colonisateur,
carburant, durée, quota et multiplicateurs des autres fondations restent inchangés.

### Présentation des pages Diplomatie, Profil et Inventaire

Le lot K réorganise les pages sans changer les règles ci-dessus.
La diplomatie garde les périmètres guilde, traités et commandants ; les grades,
permissions et actions existants sont conservés. Les identifiants du profil
restent communs au compte, avec fermeture de session après enregistrement.
L’inventaire permet d’examiner et d’annuler l’utilisation avant confirmation ;
seule la soumission effective peut consommer un objet, sous les contrôles
habituels de propriété, univers, vacances, opération active et stockage.

### Présence des membres de guilde

La liste Membres affiche En ligne en vert si le membre a été actif depuis moins
de cinq minutes dans l’univers courant, sinon Hors ligne en rouge. Les libellés
restent affichés. Cette information est réservée à sa propre guilde et actualisée
lors de l’ouverture ou du rechargement de la liste. Une activité dans un autre
univers ne rend pas ce membre en ligne ici ; heures et durées ne sont pas révélées.

### Revenu de base métal et nano — décision du 13 novembre 2026

Le revenu de base total est désormais de **10 métal/h et 10 nano/h**, à vitesse ×1 en position 5, au lieu de 40 métal/h et 20 nano/h. Ce sont des taux de remplacement, pas un revenu additionnel. Les modificateurs de position, d’univers et d’événement existants s’appliquent ; les vacances suspendent toujours la production. Le revenu de base est nul pour l’électricité, l’antimatière et l’hydrogène, conformément à la précision validée le 13 novembre 2026. Les productions des bâtiments, stocks et capacités restent inchangés. Le calcul des gains non matérialisés suit la règle courante déjà validée, sans migration.

À stocks nuls, sans usine, à vitesse ×1 en position 5, 48 h fournissent 480 métal et 480 nano : assez pour les 384 métal et 208 nano d’un premier producteur direct. Cela ne produit aucune électricité et ne garantit pas la résolution de tous les blocages. Le coût électrique ponctuel suit la décision validée ci-dessous.

### Électricité ponctuelle des opérations — décision du 13 novembre 2026

Les recherches, vaisseaux et défenses paient une fois à l’ajout de chaque commande : **100 × arrondi supérieur de la durée effective en secondes / 60**. La durée retenue inclut les réductions et multiplicateurs applicables au moment de l’ajout ; les quantités sont calculées comme une commande entière, avec un seul arrondi final. Ce coût s’ajoute aux autres ressources et à un éventuel coût électrique défini par le catalogue. Les bâtiments ne paient pas ce nouveau coût. Ce n’est pas une consommation horaire continue.

Les nouvelles commandes reprennent le rythme de leurs bonus acquis : recherche
industrielle de l’empire pour les bâtiments, laboratoire de la colonie d’origine
pour les recherches et chantier local pour les vaisseaux. En cours, seul le
travail restant change de rythme ; une commande en attente utilise les bonus
effectifs à son démarrage. La défense ne reçoit aucun bonus passif supplémentaire.
Les vacances arrêtent le travail ; les boosts déjà utilisés restent acquis.
Les commandes historiques sans suivi et les reconstructions gardent leur
chronomètre antérieur. L’estimation d’une file ne prédit pas les futurs bonus.

Les coûts payés sont figés avec la commande. Une accélération ultérieure ne recalcule pas le prix et ne rembourse aucune électricité. Une annulation rembourse 25 % des coûts effectivement payés, électricité comprise, pour la commande et chaque dépendance annulée, dans la capacité disponible de chaque colonie payeuse. La capture conserve sa règle d’annulation sans remboursement. Les reconstructions automatiques des empires oubliés paient aussi ce coût ; leurs conditions de ressources restent applicables.

Les aperçus et maxima constructibles utilisent le même calculateur que les prélèvements serveur. Les commandes déjà enregistrées gardent leurs coûts payés : aucune taxe rétroactive ni migration. L’[étude chiffrée](ETUDE_COUT_ELECTRIQUE.md) conserve les hypothèses et les risques de blocage des gros lots.

Précision validée le 13 novembre 2026 : le coût électrique initialement approuvé à 1 E par minute entamée est multiplié par 100. L’arrondi porte toujours une seule fois sur la durée entière de la commande, avant multiplication. Une commande de 60 s paie 100 E ; 61 s paient 200 E. Les coûts déjà payés ne sont pas recalculés rétroactivement.


## Outils stratégiques personnels (lot 2)

Le simulateur utilise les statistiques du catalogue de l’univers actif, les rangs,
recherches, bonus de forteresse et le moteur des combats réels. Le résultat est
une série de dix combats avec les mêmes paramètres et dix graines distinctes.
La graine de départ reproduit toute la série. Le pourcentage est celui des
victoires de l’attaquant sur dix : 7 victoires donnent 70 %, sans garantir
cette probabilité réelle. Victoires, défaites, égalités et autres issues restent
distinctes ; les détails des dix combats sont repliables, sans pertes moyennes. Les deux camps, leurs arrivées
et les défenses sont saisis ; occupations, interventions non saisies, voyages,
butin et reconstruction ne sont pas estimés. Aucun ordre, perte ou paiement
réel n’est appliqué. Les scénarios volumineux validés passent en calcul différé, avec un résultat privé consultable jusqu’à 24 heures ou jusqu’au prochain calcul différé. Les limites de calcul sont décrites dans le
[suivi technique](CADRAGE_OUTILS_STRATEGIQUES.md).

Seuls ses vaisseaux disponibles et un rapport d’espionnage encore accessible
peuvent être repris automatiquement. Le rapport est daté ; ses catégories absentes
restent inconnues, même si le formulaire propose des hypothèses à zéro. Une
modification de ses valeurs est présentée comme une hypothèse.

Un empire peut enregistrer 20 compositions au maximum dans son univers, avec
nom, types, rangs et quantités, puis les charger depuis une de ses colonies.
Les manques sont présentés avant toute acceptation explicite des quantités
disponibles. Charger ne construit ni n’envoie de flotte. Le contrôle final
d’envoi conserve les disponibilités, permissions et protections habituelles ;
une composition chargée devenue indisponible n’est pas réduite silencieusement.

La recherche dans les messages reste limitée aux conversations privées du
joueur, à sa guilde actuelle et au Global de son univers. Lire un résultat de
recherche ne marque pas la conversation comme lue ; l’ouverture de la
conversation normale conserve son comportement de lecture. La recherche dans
les rapports conserve leurs droits et archives personnelles. Les filtres par
nom portent sur les noms actuellement conservés, sans reconstituer un historique
absent. Les résultats sont paginés par 50 et les dates utilisent le fuseau choisi.

## Espionnage

Décision du 14 novembre 2026 : ces valeurs sont une base d’essais Alpha, pas un équilibrage confirmé par les joueurs. Configuration : `app/config/game/espionage.yaml`.

Dans la liste des renseignements et dans le rapport, l’expéditeur voit le propriétaire observé à l’arrivée. Pour les anciens rapports, le nom est affiché lorsque la date d’acquisition confirme la possession de la planète. Les coordonnées du rapport ouvrent son système sur la carte. Dans un nouveau rapport de détection reçu, le défenseur voit le nom de l’expéditeur si le système d’origine lui était connu lors de la détection.

Depuis les deux vues de Galaxie, l’envoi rapide permet de combiner les rangs dans la limite de 100 sondes au total et de la taille de flotte autorisée. Le stock de la colonie de départ est vérifié pour chaque rang au départ.

Le risque de détection de base vaut 50 % pour une sonde Basique, 40 % pour une Intermédiaire et 30 % pour une Supérieure. Pour plusieurs rangs, prendre la moyenne pondérée par leurs quantités, sans arrondi intermédiaire. Le rang n’ajoute aucun renseignement.

Le bonus défensif vaut `min(20, floor(sondes présentes / 5))` points : 0 à 4 sondes donnent 0 point ; 5 donnent 1 ; 99 donnent 19 ; 100 ou davantage donnent 20. Chaque sonde compte pour une unité, quel que soit son rang. Le périmètre reste celui du moteur : sondes dans les flottes inactives de la planète, en défense ou en orbite sur elle ; les trajets ne sont pas ajoutés. Une planète PNJ sans propriétaire conserve le décompte de sa garnison de sondes et ses recherches PNJ.

`Détection brute = base pondérée + 10 × Discipline du secret adverse − 10 × Écran de brouillage de l’espion + (sondes envoyées − 1) + bonus défensif`.

Les niveaux effectifs conservent la somme pondérée des rangs par 1 / 1,25 / 1,5625, arrondie comme auparavant. Limiter ensuite la détection brute entre 5 et 95 %, puis arrondir une seule fois à l’entier le plus proche. La chance de réussite vaut `100 − détection finale` : aucun investissement ne garantit automatiquement le verdict.

Exemples à technologies égales : une sonde Supérieure face à 100 sondes défensives donne 30 + 20 = 50 % de détection et 50 % de réussite. Quatre Basiques et une Supérieure donnent une base de 46 %, puis 50 % de détection après les quatre sondes supplémentaires, sans défense locale.

Le score reste `1 + Analyse tactique de l’espion − Analyse tactique adverse + sondes supplémentaires`, avec plancher nul et cinq catégories au maximum : ressources, bâtiments, recherches, flottes, défenses. Une détection ne révèle rien et avertit le propriétaire. Toutes les sondes envoyées sont consommées à l’arrivée, succès ou détection. Les coûts, prérequis, transports, permissions, vacances et protections restent inchangés. Les anciens rapports conservent le risque enregistré lors de leur résolution ; ils ne sont pas recalculés rétroactivement.

## Profils des joueurs et pages de guildes — 14 novembre 2026

Les profils sont consultables par les joueurs connectés du même univers, depuis le classement, les messages et les membres de guilde. Ils affichent le pseudo, la guilde, les titres et la bannière équipés. Les cinq scores publics du profil (total, bâtiments, recherche, flotte et planètes) proviennent du dernier relevé. Les crédits se consultent dans le classement. Un nouveau joueur sans instantané reste « Pas encore classé ». Aucun email, activité précise, coordonnées ou effectif militaire n’est exposé.

Les pages des guildes sont consultables dans le même univers lorsque les guildes y sont activées. Elles montrent nom/tag, description, logo, bannière, commandant et nombre de membres. Seul le commandant actuel peut modifier la présentation, même si des grades disposent d’autres permissions déléguées. Les descriptions restent du texte simple, échappé à l’affichage ; les images sont contrôlées et réencodées. Voir les [contraintes et limites](PROFILS_JOUEURS_GUILDES.md).

## Mise en forme de la messagerie — 14 novembre 2026

Dans les messages privés, de guilde et du Global, Ctrl + Entrée envoie ; Entrée
conserve les sauts de ligne. Les nouveaux messages acceptent gras, italique,
listes, citations, code et liens HTTP/HTTPS. HTML brut et images ne sont pas
exécutés ou chargés. Les anciens messages restent du texte simple.
Le bouton Envoyer reste disponible sans JavaScript ; un envoi répété depuis le
même formulaire ne crée pas de second message ni de seconde notification.
La limite de 1 à 2 000 caractères et les droits par univers restent identiques.
Voir le [guide](MESSAGERIE_MARKDOWN.md).


## Stationnement sur une autre colonie — 14 novembre 2026

La mission Stationnement déplace les vaisseaux vers une autre colonie possédée
et découverte du même univers. La nouvelle base devient effective uniquement
après l’arrivée réussie ; aucune mission de retour ni récompense n’est créée.
Les prochains départs utilisent la nouvelle base et les formules habituelles.

Le stationnement réserve au départ deux fois le carburant aller, intégralement payé
par la colonie de départ. La flotte utilise cette réserve pour le trajet, un rappel
ou un repli. Dès l’installation à quai, le carburant restant est définitivement perdu.
Chaque nouveau départ prélève tout le carburant du devis sur sa colonie.

L’hydrogène chargé comme fret suit les mêmes règles de dépôt que les autres
ressources. Le surplus du stationnement reste en soute et peut être déchargé dans
la limite du stockage disponible. Les vaisseaux laissés à quai doivent pouvoir porter ce fret.

Si la destination est perdue, repli vers l’origine si elle reste possédée et
atteignable avec la réserve, sinon vers la colonie possédée atteignable au trajet
le plus court selon les formules actuelles. Les égalités sont départagées par
identifiant de colonie. Propriété et réserve sont revérifiées à chaque arrivée.
Le fret d’hydrogène ne sert jamais de carburant. Sans repli possible, les
vaisseaux restent indisponibles en attente, sans combat supplémentaire, coût
de séjour, transfert ou perte automatique. Notification unique ; une reprise
manuelle après devis utilise seulement la réserve existante et une colonie
possédée et découverte. Sans solution, l’attente persiste.

## Calendrier de l’Avent

L’administration choisit la période de visibilité dans chaque univers. Les portes du 1er au 24 décembre s’ouvrent à minuit à Paris. Chaque compte peut rattraper les jours passés pendant la période et obtenir un lot par porte pour toute la saison. À l’heure de fermeture, la page et les portes ferment. Les nouveaux lots de matériaux et de vaisseaux deviennent des boîtes à ouvrir depuis l’inventaire, jusqu’au 31 décembre inclus en heure de Paris. Les matériaux d’une boîte de l’Avent peuvent dépasser la capacité des entrepôts ; les dépôts habituels restent plafonnés. Les anciens lots déjà réservés conservent leur retrait sans cette nouvelle échéance. [Détail du calendrier et des réservations](CALENDRIER_AVENT.md).

## Bourse de ressources

La station commerciale A:0:0, ouverte par univers, échange les quatre matériaux sans électricité. Le lot voyage sur les propres cargos du joueur, protégés en mission commerciale. Un même convoi peut réunir les rangs Basique, Intermédiaire et Supérieur dans la limite de flotte de l’univers. Il est échangé en station après 30 minutes, puis livré après les 30 minutes du retour. Au déchargement, le surplus est placé dans une boîte de ressources de l’empire, y compris après un rappel. Si l’inventaire est plein, la boîte attend une place. Les frais d’essai sont de 2 %. Vendredi à 9 h à Paris, les marchands réapprovisionnent une fraction des ventes réelles hebdomadaires de la station (50 % par défaut), sans dépasser la cible. Leur livraison réduit la rareté des ressources livrées. [Détail du parcours et du stock](BOURSE_DE_RESSOURCES.md).

Les cours partent de 1 crédit pour l’hydrogène, 2 pour le métal, 4 pour le nano et 8 pour l’antimatière. Ils augmentent sous la réserve cible et diminuent au-dessus, entre 50 et 150 % du cours initial. Le comptoir délivre un devis pour le lot entier. Le troc applique 2 % de frais. La station paie les ventes en crédits après déduction de 2 % et facture les achats avec 2 % de frais. Chaque empire dispose de son propre solde par univers. Le classement affiche les crédits disponibles lors du relevé de minuit ou midi à Paris. Les crédits sont distincts des points de protection.

Sur le marché des joueurs, le vendeur dépose sa cargaison à A:0:0 et fixe le prix unitaire en crédits. La taxe exigible à la publication est calculée sur le prix total au taux de l’univers (2 % par défaut), puis arrondie au centime supérieur. Elle est détruite immédiatement. Un solde insuffisant empêche la publication. Le vendeur reçoit le prix intégral des unités effectivement achetées. Les achats partiels et le retrait des invendus se règlent en station. Retirer une offre ne rembourse pas sa taxe. Un rapatriement volontaire depuis le dépôt nécessite un convoi. Le retour des invendus à échéance est automatique.

## Bonus quotidiens temporaires

Un retrait quotidien par **compte**, tous univers confondus. Le jour change à minuit, heure de Paris. Une date manquée n’efface pas le cycle des sept cadeaux. Le cadeau reste dans l’inventaire de l’univers actif, sans modifier les stocks d’une colonie. En cas d’inventaire plein, l’objet attend une place. Il expire même en attente sept jours (168 h) après le retrait. L’activation manuelle ouvre une fenêtre de cinq heures ; un seul bonus temporaire peut être actif à la fois. Les boosts instantanés déjà détenus restent utilisables séparément.

| Jour | Cadeau | Effet |
| --- | --- | --- |
| 1 | Moteurs économes | −20 % de carburant de trajet au départ : transport, stationnement, exploration et colonisation ; la réserve et le profil de rappel sont figés. |
| 2 | Soutes modulaires | +20 % de capacité de fret pour transport et stationnement, hors réservoir. Un excédent revenu n’est pas détruit ; il est à décharger avant de redéployer des vaisseaux si la réserve ne tient plus. |
| 3 | Coffres renforcés | Part d’entrepôt protégée du pillage doublée, de 10 % à 20 % avec le réglage habituel, relevée au début d’un combat. |
| 4 | Défense renforcée | +10 % de dégâts en défense PvP ; flotte et défenses au sol. |
| 5 | Offensive renforcée | +10 % de dégâts en attaque PvP. |
| 6 | Rythme accéléré | Travail des files ×1,25 seulement pendant les cinq heures actives, y compris pour une nouvelle tête de file. |
| 7 | Production renforcée | Production brute des colonies de l’empire ×1,15 sur le seul intervalle actif ; consommations et plafonds continuent de s’appliquer. |

L’effet combat est figé à l’ouverture du combat et ne s’applique pas aux combats PNJ. Les trajets spécifiques de la Bourse, de la campagne pirate et des convois du Hall ne prennent pas le bonus de vol.

## Rapports de campagne et fret des convois pirates

La reconnaissance pirate envoie une seule sonde de la colonie de départ, du premier rang disponible dans l’ordre Basique, Intermédiaire, Supérieur. Le devis indique le rang qui sera perdu. La durée et le carburant aller-retour dépendent de la sonde retenue. Sans sonde à quai, le départ est indisponible.

Les rapports privés de reconnaissance et de combat de la campagne rejoignent les rubriques Espionnage et Combats. Chaque trajet du convoi produit un rapport de transport dans Économie. À la livraison, une boîte de ressources contient la totalité du fret annoncé, demi-unités comprises. Le joueur choisit une colonie de son univers à l’ouverture. Le joueur peut confirmer la perte du surplus si les entrepôts manquent de place. Une livraison déjà créditée avant cette évolution reste acquise et ne donne aucune boîte supplémentaire.

## Notifications des boîtes et des demandes d’orbite

Les nouvelles boîtes de convoi pirate et de surplus de Bourse expirent exactement 24 heures après leur livraison, même en attente ou pendant les vacances. À l’échéance, le contenu non retiré est perdu. Chaque boîte porte sa propre date limite. Les boîtes déjà attribuées sans échéance gardent leurs conditions.

Une boîte de convoi pirate devient nouvelle lors de sa livraison effective. Toute nouvelle boîte suit cette règle de signalement, quelle que soit sa provenance. Un objet reçu lorsque l’inventaire est plein est signalé dans les récompenses en attente. Son éventuelle promotion conserve son état de lecture. La consultation de l’onglet Boîtes marque les boîtes affichées comme vues sans les ouvrir. Un premier retrait réussi, même partiel pour l’Avent, ou un marquage comme vu retire le signal de la boîte. Les stocks antérieurs conservent leur état de lecture.

Le badge Flottes compte les demandes d’orbite reçues qui attendent encore une réponse sur les colonies du joueur dans son univers. Le lien ouvre la colonie de la demande à l’échéance la plus proche. Les anciennes demandes à traiter après l’arrivée restent accessibles.

## Édition du jeu par univers

Le badge manuel disparaît à la visite suivante et préserve le calendrier naturel J14/J30. La conversion administrative est distincte et définitive. Une mission pirate supplémentaire peut proposer plusieurs vagues et un fret fixé avant l’espionnage. Les offres nouvelles expirent par défaut à sept jours ; le taux de mise en vente initial est de 2 %, la station conserve une capacité illimitée par défaut et le marchand remplace par défaut 50 % des ventes dans la limite de la cible de stock. Les réglages sont propres à l’univers. Le vendeur choisit la colonie de retour des invendus. À l’expiration, le stock qui excède sa capacité est perdu. Voir les [éditeurs administratifs](EDITEURS_ADMIN.md).
