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

Conquête JcE : seules les planètes neutres marquées PNJ sont attaquables; une victoire avec un vaisseau colonie survivant capture la planète. Bâtiments, stocks et développement sont conservés; flotte et défenses PNJ disparaissent. Les planètes vierges restent colonisables et aucune planète détenue par un joueur n’est conquérable.

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

Pour les effets discrets de colonisation, chaque rang contribue son quota pondéré, puis les fractions de colonies sont arrondies vers le bas; la limite de colonies configurée par l’univers reste prioritaire. Les productions et capacités de stockage de plusieurs bâtiments-rangs s’additionnent, car chaque rang fonctionne comme une installation distincte. Les coûts des ressources non énergétiques suivent les budgets de valeur par famille; l’électricité n’est pas facturée lors de la construction. Les usines reçoivent un rendement de secours minimal de 10 % en déficit électrique, selon une simulation segmentée hors connexion; la production d’antimatière est limitée par l’hydrogène et s’arrête sans apport. La consommation électrique nominale des extracteurs est configurée à 0,05/s/niveau; l’usine d’antimatière consomme 8 H par A produite.


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

Cette section remplace les anciennes références de puissance pour les producteurs. La production et les consommations industrielles suivent **1 / 1,5 / 2,25** aux rangs T1/T2/T3. Les rangs s’additionnent. La centrale produit **0,2 électricité/s/niveau basique**, soit 720/h en univers normal à la position de référence. Métal, nano, hydrogène et antimatière conservent leurs productions unitaires de base ; le réacteur convertit toujours 8 H par A.

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

### Classement des guildes — 5 novembre 2026

Le classement des guildes additionne les points actuels de bâtiments, recherches et flotte de leurs membres. Le total détermine le rang. La médiane des scores individuels complète le total : pour un effectif pair, elle est la moyenne des deux scores centraux. Une guilde sans membre compte zéro point et une médiane de zéro. Le classement joueur reste fondé sur les points totaux ; sa présentation détaille bâtiments, recherches, flotte et total.


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
