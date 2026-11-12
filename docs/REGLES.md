# Synthèse de conception

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

Depuis le profil, le joueur peut supprimer son compte dans tous les univers
après saisie du mot de passe et confirmation explicite. Les identifiants et
préférences sont effacés et les accès fermés. Les empires deviennent oubliés
immédiatement, sans attendre le délai d’inactivité ; leurs colonies et les
historiques de jeu restent présents. Les vacances prennent fin. Un combat en
cours bloque l’opération entière jusqu’à sa résolution. Le dernier administrateur
actif doit désigner un successeur avant de supprimer son compte.

## Colonie
Métal, électricité, antimatière, hydrogène et nano par planète. La simulation intègre les stocks par segments entre épuisement et capacité. Les usines adaptent leur rendement à l’électricité disponible avec un plancher de 10 % ; l’antimatière exige aussi de l’hydrogène. Coûts et durées sont autoritaires côté serveur. Les coûts des trois rangs suivent des profils par catégorie avec budgets de valeur constants. Technologies par PlayerUniverse avec laboratoire local. Même technologie interdite simultanément sur des planètes différentes.

## Queues (PDF 7 p.1–7)
Trois queues indépendantes par planète. QueueItem : type, slug, niveau cible ou quantité, durée, started_at, queued_at, dispatch_token, depends_on. Ordre id ASC. Échéance non persistée : T0 + somme des durées. Un item actif par type. Prérequis achevés à l'ajout ; exception niveaux successifs via dépendance. Suppression récursive des dépendants. Boost : chaque application retire 25 % du temps restant de l’opération active. Les applications successives se cumulent sur le nouveau temps restant; aucune file en attente n’est accélérée. Ancien message ignoré si token différent ; doublon ignoré après traitement. Persistance et déduction des ressources dans une transaction avec verrou.

## Trajets (PDF 3 p.2–9)
UA universelle. Intra-système : abs(positionB-positionA) × 10. Inter-systèmes : sortie 100−positionA×10, hyperespace hypot(dx,dy)×100, entrée 100−positionB×10. Temps par segment = distance / (vitesse minimale de flotte × ratio). Ratio entre 0.1 et 1. Aller-retour symétrique. Carburant aller + stationnement + retour réservé avant départ. Consommation proportionnelle au ratio de vitesse. Chaque vaisseau possède un petit réservoir exclusivement pour le carburant ; les soutes cargo peuvent embarquer le fret et le carburant supplémentaire. Consommation d'orbite horaire indépendante du ratio. Navigation inter-galaxies non définie et refusée jusqu'à spécification.

## Combat (PDF 1 p.6–8, PDF 2 p.2–6)
Cadence confirmée par Kévin le 01/11/2026 : un round réel par minute, jusqu’à 10 rounds; les renforts rejoignent le prochain round non résolu selon leur heure d’arrivée. Le premier round commence à +60 secondes; les suivants à +120, jusqu’au 10e à +600. Une flotte arrivée exactement au début d’un round est traitée avant ce round et peut y participer, notamment à +600 secondes avant le 10e round. Le combat se clôt immédiatement dès qu’un camp est éliminé; toute flotte arrivée après cette clôture repart sans rejoindre le combat. Le moteur persiste et résout les rounds en temps réel selon cette cadence. L’Alpha conserve un round par minute, jusqu’à dix rounds ou clôture anticipée; voir l’audit de combat et les tests récents.
Fenêtre fixe dès premier attaquant, renforts échelonnés par rounds. Alliances directes et guildes définissent les camps ; défense extérieure intégrée au camp défendeur même sans alliance. FFA si attaquants non alliés. Unité individuelle, tirs bornés, cible vivante, destruction avant tir possible. Chance de base = clamp(15,95,precision+tracking+correction de l’arme−evasion−15+écart de rang). Chance finale = clamp(15,95,chance de base × coefficient de contre). La signature pondère le choix de cible ; le même coefficient de contre ou de faiblesse multiplie une fois la chance de toucher et une fois les dégâts d’un impact, sans second cumul réciproque. Un contre ×1,30 fait passer 50 % à 65 % de chance de toucher et augmente les dégâts par impact de 30 %. Voir le rééquilibrage du 02/11/2026 ci-dessous. Bonus technologiques figés au début de résolution. Notifications avec identité masquée selon découvertes ; destinataires limités. Après victoire offensive, les attaquants survivants peuvent piller le stock au-delà de 10 % de la capacité de stockage par ressource; leurs soutes libres se partagent le butin au prorata des stocks pillables, carburant de retour protégé. Au retour, le stockage restant de la planète d’origine plafonne le dépôt et le surplus est perdu.

## Architecture (PDF 4)
Controller → service métier → manager → repository. Entités et repositories plats, services regroupés par domaine ; messages et handlers fins ; intégration Mercure indépendante du jeu. Slugs uniquement pour référencer les catalogues. Aucun catalogue dynamique en base.

## Compléments opérationnels appliqués
Les opérations en retard sont rejouées à leurs dates théoriques avant dépense/lecture de colonie, avec production et plafonds successifs. Les notifications utilisent un relais indépendant des échéances du jeu. Une sonde ou un vaisseau-colonie consommé sans flotte survivante termine la mission à l’arrivée ; un transport revient et livre une seule fois.

Guildes : recrutement ouvert provisoire, adhésion exclusive dans chaque univers, transfert du commandement à un autre membre, départ après transfert, dissolution par le dirigeant. Alliances personnelles et alliances de guildes exigent le consentement réciproque. Une hostilité de guilde invalide les contrats personnels incompatibles.

PNJ : les planètes PNJ découvertes du même univers peuvent être combattues par la mission Attaque, sans capture ni butin de ressources PNJ. Leur capture reste réservée à Conquête JcE, avec vaisseau colonie survivant, place disponible et règles d’occupation existantes (24 heures lorsque ce mécanisme est activé). Les bâtiments et stocks suivent les règles de conservation de la capture. Les planètes vierges restent colonisables ; les joueurs actifs ou simplement inactifs ne sont pas conquérables. Les empires déjà oubliés suivent les règles spécifiques détaillées ci-dessous.

Espionnage : une mission cible une planète connue du même univers autre que celles de l’émetteur. Elle transporte uniquement des sondes, détruites à l’arrivée. La détection avertit le propriétaire et bloque le rapport; les renseignements et rapports suivent les niveaux et règles résumés dans docs/archives/DECISIONS_INITIALES.md.

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

Les bonus technologiques en pourcentage qui étaient à +5 % par niveau passent à **+1 % par niveau et niveau maximum 25**. Le laboratoire et le chantier spatial passent à **2 % par niveau, maximum 25**, conservant leur maximum Basique de 50 %. Les bonus de rang sont multipliés puis composés : une technologie à 25 niveaux dans chacun des trois rangs produit les facteurs 1,25 × 1,3125 × 1,390625 = **2,281494×**. Les bâtiments de production de ressources et de stockage n’ont plus de niveau maximum.

Depuis la décision du 6 novembre 2026, les prérequis servent uniquement au déblocage initial de chaque rang, dès le niveau 1. Aucun nouveau prérequis pendant les améliorations. L’accès acquis reste conservé pour le joueur sur cette colonie (bâtiments, vaisseaux, défenses), ou dans cet univers (recherches). Les [conditions par rang et leurs raisons](DEBLOCAGES.md) remplacent les anciens paliers sans cumuler leurs exigences. Les seuils hors plafond sont corrigés ; comptes, niveaux, rangs, unités, files et accès historiques sont conservés par un registre persistant. Coûts, ressources, durées et plafonds restent applicables. Migration et reprise doivent être effectuées avant réouverture ; elles ne sont pas appliquées automatiquement.

Les coûts de recherche par niveau conservent la croissance ×2 et les durées de recherche la croissance ×1,8. Les durées de bâtiment conservent ×1,6. Ces courbes restent des paramètres distincts des multiplicateurs de rang. La migration attribue les données historiques au rang Basique; les opérations déjà en file conservent leurs coûts et durées enregistrés.

Les technologies de combat couvrent structure, bouclier, attaque, précision, suivi, esquive, signature, cadence de tir, vitesse, réservoir et soute. Le tir supplémentaire est une chance indépendante par tir de base pour conserver une résolution entière et éviter des valeurs de tirs fractionnaires.

Pour les effets discrets de colonisation, chaque rang contribue son quota pondéré, puis les fractions de colonies sont arrondies vers le bas; la limite de colonies configurée par l’univers reste prioritaire. Les productions et capacités de stockage de plusieurs bâtiments-rangs s’additionnent, car chaque rang fonctionne comme une installation distincte. Les coûts des ressources non énergétiques suivent les budgets de valeur par famille; l’électricité n’est pas facturée lors de la construction. Les usines reçoivent un rendement de secours minimal de 10 % en déficit électrique, selon une simulation segmentée hors connexion; la production d’antimatière est limitée par l’hydrogène et s’arrête sans apport. La consommation électrique nominale des extracteurs est configurée à 0,05/s/niveau; depuis la décision du 9 novembre, l’usine d’antimatière consomme 4 H par A produite.


### Research et art temporaire

Les 19 recherches ont reçu des libellés français distincts et des slugs dédiés, sans modifier leurs effets. Leurs états sauvegardés, files de recherche et surcharges d’univers sont renommés par une migration compatible. Les défenses sont regroupées en quatre tourelles au sol : anti-léger (intercepteur, chasseur, chasseur lourd, chasseur furtif), anti-escorteur (corvette, frégate, destroyer), anti-lourd (croiseur, cuirassé) et anti-capital. Les tourelles laser couvrent les classes légères et escorteurs; les tourelles plasma couvrent les classes lourdes et capitales. Chaque tourelle reçoit un bonus de dégâts contre sa classe. La collection complète des 150 illustrations de catalogue est intégrée aux cartes des 13 bâtiments, 19 recherches, 14 vaisseaux et 4 défenses, aux rangs basic, intermediate et superior. Chaque image est associée au slug et au rang de sa carte; les originaux PNG sont conservés dans les dossiers source de public/images/catalog.


## Rééquilibrage progression et combat — 02/11/2026

Les courbes, bonus communs entre rangs, profils de mobilité, ciblage furtif, retarget après destruction et matrice de contres sont décrits dans [BALANCING_IMPLEMENTATION_2026-11-02.md](archives/PROGRESSION_2026-11-02.md). Ces choix sont validés pour l’implémentation ; les jalons économiques demeurent des objectifs à mesurer en Alpha.

Pour les bonus et les courbes, ce rééquilibrage remplace les formules historiques de la section précédente : les bonus de recherche ne se composent plus par rang. L’investissement pondéré vaut la somme des niveaux de chaque rang, plafonnés à leur maximum et pondérés par 1 / 1,25 / 1,5625, divisée par le maximum pondéré des trois rangs. La progression commune vaut `ln(1 + 2 × investissement) / ln(3)` ; le bonus vaut cette progression multipliée par le plafond configuré. Les plafonds de réduction de durée et de coût sont respectivement 70 % et 40 %. La cadence accorde une seule chance supplémentaire commune, plafonnée à 50 % par tir de base. Les coûts et durées utilisent les courbes par phase de `config/game/rules.yaml`, sous réserve des décisions plus récentes sur les producteurs. Les statistiques de combat R14 et les compositions de coûts du 5 novembre restent prioritaires sur les anciens exemples chiffrés.


### Tirs secondaires et contres — 4 novembre 2026

Les destroyers, croiseurs, cuirassés et vaisseaux capitaux disposent d’une arme secondaire avec une correction de précision de +50 points depuis R14. Cette correction est ajoutée avant le calcul de la chance de base ; le coefficient de contre est ensuite appliqué relativement à la chance bornée, puis les limites finales de 15–95 % sont réappliquées. Les coefficients concernent les tirs principaux et secondaires ainsi que les défenses.

Depuis R14, les armes secondaires déclenchent aussi les tirs supplémentaires de la recherche de cadence, sans récursion. Leur cible doit correspondre à la catégorie configurée pour l’arme. Les classes admissibles sont décrites dans le catalogue ; une cible inadmissible ne reçoit aucun tir secondaire, ce qui est différent d’une probabilité de toucher de 0 %.


Les noms des cibles de contre sont ceux des familles génériques de coque, communs aux trois rangs. Par exemple, la famille Capital couvre Vaisseau capital, Dreadnought et Vaisseau amiral. Les défenses au sol partagent une famille générique de cible lorsqu’aucune relation particulière à une tourelle n’est définie. La classe cible Chasseur regroupe le Chasseur standard, le Chasseur furtif et l’Intercepteur, à leurs trois rangs. Le Chasseur lourd reste séparé. Un contre exprimé envers ce groupe s’applique à chacun des trois types ; R14 utilise des relations explicites pour distinguer leurs rôles parmi les neuf classes étudiées ; leurs profils de tir offensifs et leurs statistiques restent propres à chaque coque. Lorsque des anciennes relations vers ces membres avaient des coefficients différents pour un même tireur, la classe reprend le coefficient le plus fort, sans cumul.


## R14 Alpha — règles de combat en vigueur depuis le 5 novembre 2026

Cette section remplace les anciens réglages de combat décrits plus haut. Chance = borne15–95(borne15–95(borne15–95(précision + tracking + correction arme − évasion − 15) × contre) + 30 × écart de rang). Le contre multiplie également les dégâts. La signature pondère les cibles, avec priorité aux unités dont structure ou bouclier est entamé. La cible principale reste verrouillée pendant le tour tant qu’elle survit ; les secondaires gardent leur catégorie admissible et bénéficient aussi du tir rapide.

Les dégâts d’un tour sont multipliés par ((HP + bouclier restants)/(HP + bouclier initiaux))². Un bouclier encore positif récupère 5 % de son maximum par round ; à zéro il ne recharge plus pendant cette bataille. Sous 25 % de structure, chaque prochain tir qui touche teste avant ses dégâts une explosion de probabilité max(0, 1 − HP/(0,25 × HP_initial)). Pas d’explosion sur tir raté. Les excédents ne se transfèrent pas à une autre coque.

Les neuf classes R14 ont une croissance de combat 1/1,5/2,25 ; les coûts par rang et les autres facteurs de mobilité/construction restent séparés. Une escorte est une composition financée par remplacement, sans interception ou interposition automatique. Un round reste une minute, dix rounds au maximum ; si les camps subsistent à la limite, le défenseur gagne officiellement. Les HP individuels ne sont pas persistés entre batailles.

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

Depuis la décision confirmée du 9 novembre 2026, les productions Basiques par niveau à vitesse ×1 et position 5 sont : métal 360/h, nano 180/h, hydrogène 720/h, antimatière 90/h et centrale 1 080 électricité/h. Chaque extracteur consomme 180 électricité/h ; le réacteur consomme aussi 4 H par A produite. Les rangs s’additionnent et gardent les facteurs de production et consommation **1 / 1,5 / 2,25**. Les bonus de position et la production passive restent appliqués séparément. Les prix du niveau 1 sont 480 M + 160 N pour les trois producteurs directs, 360 M + 120 N pour le réacteur et 1 440 M + 480 N pour la centrale. Les durées du niveau 1 Basique sont respectivement 1 min, 2 min et 2 min ; les courbes de niveaux et les réductions sont conservées. Les budgets du réacteur et de la centrale sont déclinés sur les autres rangs avec les facteurs de coût existants, sans changer les parts de ressources. Le [bilan courant](EQUILIBRAGE.md#production-des-batiments) précise les 84,16 jours cumulés jusqu’à 80 confirmés par l’utilisateur, leur périmètre et leurs limites.

Les coûts des seuls producteurs croissent par niveau de ×1,12 jusqu’à 20, ×1,09 jusqu’à 40, ×1,035 jusqu’à 80, ×1,015 jusqu’à 120, puis ×1,01. Les premiers prix, profils de ressources par rang et arrondis par ressource restent utilisés. Les durées et les effets des laboratoires, chantiers, bunkers et stockages ne reçoivent pas le nouveau facteur de production ; leur calcul reste séparé.

Une usine dont la production ne peut plus être stockée suspend ses consommations inutiles. Les raffineries et centrales continuent de fournir les besoins immédiats des autres usines lorsque leurs propres stocks sont pleins. Les pénuries et remplissages sont calculés pendant toute la période écoulée ; les petites erreurs numériques ne doivent pas interrompre ce calcul. Les débits nets affichés excluent les excédents impossibles à stocker.

Les valeurs et résultats de référence, la distinction entre délai d’accès et remboursement, les données existantes et les vérifications sont détaillés dans [BUILDING_BALANCE_2026-11-05.md](EQUILIBRAGE.md#production-des-batiments). Les surcharges d’univers peuvent toujours remplacer les valeurs de base. Les coûts et durées déjà payés en file restent conservés.

### Limites par défaut des univers — 5 novembre 2026

Le nombre maximum de vaisseaux par flotte est fixé par défaut à **1 000 000** et le plafond de planètes à **31**. Ces valeurs s’appliquent aux profils de référence et aux nouveaux univers. Les réglages personnalisés d’un univers restent prioritaires. Le plafond de planètes ne modifie pas les prérequis de recherche de colonisation.

### Capacité d’inventaire — 5 novembre 2026

L’inventaire peut contenir **100 objets disponibles** par joueur et par univers, boosts et boîtes de ressources confondus. Les récompenses supplémentaires restent en attente et rejoignent l’inventaire de la plus ancienne à la plus récente lorsqu’une place se libère. Cette capacité remplace la limite précédente de 3 emplacements.

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
L’ouverture est refusée pendant les vacances ou si un seul des entrepôts manque
de place ; aucune ressource n’est déposée et la boîte est conservée intacte.
Une même ouverture ne peut pas être appliquée deux fois.

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


## Répartition des coûts des vaisseaux — 5 novembre 2026

Les 14 classes utilisent les parts de valeur économique suivantes : T1 60 % métal / 40 % nano ; T2 45 % métal / 45 % nano / 10 % antimatière ; T3 30 % métal / 50 % nano / 20 % antimatière. Le calcul pondère M×2, N×4, A×8 et H×1. Ces pourcentages ne représentent pas les quantités brutes. Les arrondis des trois ressources sont répartis pour conserver exactement la valeur de chaque profil de rang avant recherche ; les profils réels font foi.

Le carburant des missions est séparé des coûts de construction, qui n’ajoutent pas d’hydrogène. Les recherches de réduction des coûts s’appliquent ensuite avec leurs arrondis par ressource. Les nouvelles commandes utilisent les nouveaux prix ; les coûts déjà payés restent enregistrés. Les profils, budgets conservés et limites sont détaillés dans [SHIP_COSTS_2026-11-05.md](COUTS.md).


## Grille commune des prix — 5 novembre 2026

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
Les modèles actifs alternent selon l’indice hebdomadaire du moteur. Une seule
occurrence est réservée par univers et semaine ; une occurrence annulée conserve
cette réservation et ne peut pas être recréée cette semaine. Désactiver un modèle
empêche ses nouvelles programmations mais n’annule pas les occurrences déjà
créées. Leurs règles sont figées ; contributions et récompenses acquises restent
conservées lors d’une annulation, sans nouvelle attribution après annulation.
Le plafond simultané, le lundi à 00 h UTC et les règles de récompense historiques
s’appliquent toujours. Les joueurs retrouvent les rotations dans Événements.

## Saisie des quantités et boutons Max

- Construction de vaisseaux et défenses : Max utilise les ressources disponibles et le coût réel du lot, sans limite fixe de 100. La limite de 30 commandes par file reste applicable.
- Composition d’une flotte : Max sélectionne les unités disponibles d’un type et d’un rang, dans la place restante sous le plafond de flotte de l’univers.
- Fret : un Max par ressource conserve les autres chargements et tient compte du stock, du carburant réservé et de la soute libre. La limite de saisie de 100 000 000 par ressource est conservée.
- Les quantités excessives sont réduites automatiquement, avec un message d’ajustement et une vérification serveur au moment de l’action. Les entrées négatives, non entières ou inconnues restent refusées ; un maximum nul ne permet pas d’achat ni de départ sans vaisseau.

## File de recherche commune

Dans un même univers, toutes les colonies d’un joueur partagent une file de recherche de 30 commandes maximum. Chaque ajout paie ses coûts sur la colonie choisie et utilise son laboratoire pour calculer la durée à cet instant. Les niveaux déjà en file sont pris en compte depuis toutes les colonies ; les commandes sont exécutées successivement, sans nouveau calcul de leurs coûts ou durées au démarrage.

Les recherches historiquement actives en parallèle terminent leur travail sans être annulées. La suite de la file attend la fin de toutes ces recherches, puis fonctionne avec une seule recherche active. Les univers restent indépendants. Annulations, remboursements, vacances et captures conservent leurs règles ; la colonie d’origine de chaque commande reste enregistrée.


## Classements quotidiens — 10 novembre 2026

Tous les classements (points totaux des joueurs, bâtiments, recherche, flotte, nombre de colonies et guildes) sont figés entre deux calculs quotidiens, à 00 h en Europe/Paris, en tenant compte des changements d’heure. Les points affichés dans l’aperçu de colonie et l’API utilisent le même instantané. Le calcul des points reste inchangé ; les scores utilisés pour les protections et combats restent en temps réel.

Les évolutions comparent les deux derniers calculs persistés : points actuels moins points précédents ; rang précédent moins rang actuel (une place gagnée est positive). Dans le tableau joueur, gains et pertes sont signés et entre parenthèses sur la même ligne que la valeur ; une valeur inchangée s’affiche « (= 0) ». Sans référence précédente, l’évolution s’affiche « (—) ». La présentation des guildes et de l’aperçu conserve ses marqueurs existants. Les nouveaux inscrits et guildes entrent au prochain calcul. Les noms, relations diplomatiques et protections restent actuels ; les joueurs supprimés ou devenus empires oubliés et les guildes archivées ne sont plus affichés, sans renuméroter les rangs avant l’échéance suivante.

Le classement joueur propose points totaux (défaut), bâtiments, recherche, flotte et nombre de planètes. Chaque catégorie possède son rang et sa variation ; le tri décroissant global précède la pagination de 50 lignes. Les égalités reprennent le départage existant par pseudonyme, sans casse. Aucune variation de rang bâtiments/recherche n’est inventée pour les anciens instantanés : elle devient disponible après deux calculs contenant ce rang.

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

Le bouton « Marquer ces événements comme consultés » porte uniquement sur les
événements affichés, au plus 50 par affichage. Les suivants deviennent accessibles
après consultation, sans suppression d’historique ; les arrivées ultérieures ne
sont jamais marquées par une ancienne sélection. L’état est conservé côté serveur
par participation à l’univers et protégé contre les accès tiers et formulaires expirés.

### Destinations et suggestions des flottes — lot D

Mes planètes et alliés est sélectionné par défaut sur un nouveau formulaire.
Ce menu regroupe ses colonies et les mondes alliés découverts, compatibles avec
la mission. Ennemis regroupe les planètes ennemies, PNJ et empires oubliés connus.
Une ancienne planète PNJ détenue par un joueur neutre relève de la destination
personnalisée, car son propriétaire actuel prime sur son ancien drapeau PNJ.

Destination personnalisée conserve les coordonnées et propose une recherche de
pseudonyme : deux caractères minimum, au plus 20 résultats, sans planète inconnue
ni donnée d’un autre univers. Les joueurs neutres sont accessibles dans ce mode.
L’exploration d’un système inconnu garde sa saisie personnalisée habituelle.
Les liens préremplis de la carte conservent le contexte de leur cible ; une cible
connue peut rester consultable en formulaire même si la prévisualisation refuse
actuellement l’envoi, notamment un destinataire qui refuse les transports.

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
L’archivage n’accorde aucun droit de consultation supplémentaire.

Un administrateur actif peut attribuer un type/rang de vaisseaux existant au
stock au sol d’une colonie du joueur désigné. Motif et confirmation obligatoires,
audit avant/après et protection contre une double soumission. Le joueur, la
propriété et l’univers sont revérifiés sous verrou ; l’opération entière est
refusée si le stock entier dépasse sa capacité technique (2147483647 par ligne).
Aucun vaisseau d’une mission active, ressource, coût ou prérequis n’est modifié.
