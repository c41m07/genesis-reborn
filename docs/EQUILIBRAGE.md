# Réglages et études d’équilibrage

Références datées des choix validés. Les décisions les plus récentes de
[DECISIONS.md](DECISIONS.md) et [REGLES.md](REGLES.md) priment sur les exemples
historiques. Les valeurs réellement appliquées sont dans `app/config/game/`,
avec les éventuelles surcharges d’univers. Aucun réglage n’est modifié par ce regroupement.

## Combat R14

### Équilibrage R14 — branche Alpha, 5 novembre 2026

R14 est adopté explicitement pour les tests Alpha. Il reprend le candidat final de l’étude, sans recalibrage pendant son intégration. Il couvre les neuf classes ci-dessous. Les statistiques et les prix des furtifs, corvettes, unités de soutien et défenses restent conservés ; les nouvelles mécaniques communes les concernent aussi.

#### Statistiques effectives au rang basique, sans recherche

| Classe | Structure | Bouclier | Attaque | Tirs | Précision | Tracking | Évasion | Signature | Secondaire : attaque × tirs |
|---|---:|---:|---:|---|---:|---:|---:|---:|---|
| Chasseur | 150 | 50 | 24 | 2–3 | 55 | 75 | 60 | 20 | — |
| Intercepteur | 215 | 40 | 35 | 1–3 | 30 | 85 | 75 | 18 | — |
| Chasseur lourd | 500 | 200 | 26 | 4–4 | 65 | 75 | 50 | 35 | — |
| Bombardier | 632 | 158 | 146 | 1–2 | 65 | 80 | 45 | 40 | — |
| Frégate | 1048 | 286 | 32 | 18–19 | 75 | 80 | 35 | 35 | — |
| Croiseur | 3509 | 1404 | 160 | 7–9 | 100 | 65 | 14 | 50 | 254 × 2 |
| Destroyer | 1600 | 600 | 467 | 2–4 | 100 | 70 | 8 | 45 | 100 × 6 |
| Cuirassé | 5079 | 2540 | 160 | 17–18 | 100 | 65 | 11 | 60 | 224 × 4 |
| Capital | 6349 | 3175 | 160 | 42–43 | 100 | 70 | 8 | 70 | 240 × 8 |

Les secondaires visent la catégorie `light` (chasseur, intercepteur, furtif), avec +50 points de précision avant le coefficient. Le chasseur lourd appartient à une autre catégorie. Pour le bombardier, la base brute d’attaque est 145,5 : l’arrondi supérieur par rang donne 146 / 219 / 328, avant recherche.

Structure, bouclier et dégâts suivent 1 / 1,5 / 2,25. Les autres valeurs de mobilité et les facteurs de coût/durée de rang restent séparés. Aucun tir rapide intrinsèque au rang n’est ajouté : seuls les effets technologiques existants en accordent.

#### Valeurs économiques de construction, sans recherche

Valeur = 2 × métal + 4 × nano + 8 × antimatière + hydrogène, calculée sur les coûts réellement arrondis de chaque ressource. Les profils complets sont dans `config/game/ships/*.yaml`, au format JSON. Les recherches de réduction des coûts s’appliquent ensuite normalement.

| Classe | Basique | Intermédiaire | Supérieur |
|---|---:|---:|---:|
| Chasseur | 6000 | 7504 | 9380 |
| Intercepteur | 7352 | 9198 | 11502 |
| Chasseur lourd | 14800 | 18506 | 23140 |
| Bombardier | 21502 | 26886 | 33608 |
| Frégate | 45560 | 56960 | 71198 |
| Croiseur | 121730 | 152180 | 190210 |
| Destroyer | 84222 | 105284 | 131602 |
| Cuirassé | 220502 | 275630 | 344544 |
| Capital | 377552 | 471944 | 589934 |

Un vaisseau survivant endommagé ne perd pas sa valeur de construction. Le carburant, le fret, le butin et les dommages de structure doivent être suivis séparément. Aucun coût de réparation n’est ajouté.

#### Coefficients de spécialisation

Le coefficient multiplie les dégâts et la chance de toucher, avec les bornes décrites plus bas. Toutes les autres relations entre les neuf classes, y compris contre elles-mêmes, valent 1. Il n’y a aucune faiblesse réciproque automatique. Les relations historiques vers les classes extérieures sont conservées.

| Tireur | Cibles et coefficients |
|---|---|
| Chasseur | Intercepteur × 2.04, Bombardier × 2.42 |
| Intercepteur | Chasseur lourd × 2.35, Bombardier × 2.52 |
| Chasseur lourd | Destroyer × 4.03, Frégate × 4.44, Chasseur × 4.15 |
| Bombardier | Capital × 4.38, Destroyer × 4.16, Cuirassé × 4.35 |
| Frégate | Intercepteur × 4.5, Bombardier × 4.6, Chasseur × 8 |
| Croiseur | Chasseur lourd × 6, Frégate × 4.59 |
| Destroyer | Croiseur × 5.31, Capital × 5.06, Cuirassé × 4.53 |
| Cuirassé | Croiseur × 4.5, Frégate × 7.3 |
| Capital | Croiseur × 8, Cuirassé × 4.8 |

Le groupe historique `fighters` garde ses trois membres (chasseur, furtif, intercepteur). Les neuf tireurs R14 utilisent désormais des relations explicites, ce qui permet de différencier les rôles de ces membres. La corvette conserve son groupe historique. Cela n’accorde pas de nouveaux rôles au furtif ou à la corvette.

#### Mécaniques appliquées

- Chance de base = borne 15–95(précision + tracking + correction de l’arme − évasion − 15). Puis borne 15–95(base × coefficient), puis ajout de 30 points par écart de rang et nouvelle borne 15–95.
- Lors d’un choix de cible, les unités dont la structure ou le bouclier est entamé sont prioritaires. La signature pondère ensuite les cibles admissibles. La cible principale est conservée pendant la salve tant qu’elle survit.
- Puissance de toute la salve = ((structure + bouclier restants)/(structure + bouclier initiaux))², mesurée au début du tour du tireur.
- Bouclier positif : recharge de 5 % du maximum en fin de round. Bouclier épuisé : aucune recharge pendant cette bataille. La restauration après combat reste inchangée.
- Sous 25 % de structure, le prochain tir qui touche teste avant ses dégâts une explosion : probabilité = max(0, 1 − structure/(0,25 × structure initiale)). Exemples : 0 % à 25 % de structure ; 20 % à 20 % ; 60 % à 10 %. Le tir qui fait entrer dans la zone critique ne déclenche pas lui-même cette explosion.
- Les tirs supplémentaires de recherche s’appliquent aux tirs principaux et secondaires, sans récursion. Les excédents de dégâts restent perdus.
- Dix rounds maximum ; élimination possible avant la limite. Si les deux camps subsistent à la limite, le défenseur gagne officiellement. Les états individuels ne sont pas persistés entre batailles.

Une escorte est une partie du budget remplacée par d’autres vaisseaux adaptés. Il n’existe pas de protection automatique des alliés, d’interposition, d’interception de missiles ou de bonus de commandement ajouté par R14.

#### Vérification et limites

Le fichier `tests/Fixtures/combat-r14.json` conserve les entrées figées et les empreintes des résultats complets de 192 combats calculés avec le simulateur de référence de l’étude : six compositions, quatre graines distinctes, deux positions, deux profils de recherche et deux couples de rangs. Ces 192 combats ne représentent donc pas 192 graines indépendantes. Les tests vérifient aussi les coûts réels et les statistiques de neuf classes × trois rangs × trois profils de recherche, ainsi que les 81 coefficients. Les graines de cette vérification servent à contrôler la transposition du moteur, pas à certifier un nouvel équilibre.

L’étude économique préalable portait notamment sur 439 configurations à demi-budget ; son minimum de rendement moyen observé était de 1,4153 valeur adverse détruite par valeur propre détruite, pour un filtre proposé de 1,25. Ce résultat moyen ne garantit pas chaque bataille, notamment dans les petits combats. Le métajeu, les configurations non échantillonnées, les furtifs, corvettes et défenses restent à mesurer en Alpha.

Les surcharges de catalogue propres à chaque univers sont conservées : elles peuvent remplacer les prix et statistiques R14. Les constructions déjà payées conservent leurs coûts enregistrés. Les combats déjà commencés gardent leurs instantanés de statistiques et coefficients, mais ne possèdent pas de version du moteur : terminer les batailles actives avant de déployer cette version pour éviter de recalculer leurs anciens rounds avec les nouvelles mécaniques.

L’intégration n’effectue aucune migration, réinitialisation d’univers ni modification de sauvegarde. Les temps, prérequis, carburants, cargaisons, vitesses, noms et assets sont conservés.

Candidat final SHA-256 : `0fd323710f8a7fbe5b5b12d99e52524988d390fc55b96e85fe576720f0e066c4`.

##### Contrôles lors de l’intégration

- `make validate` : réussi.
- Tests ciblés R14, simulateur, contres, progression de rang et isolation des surcharges : **23 tests réussis, 7 407 assertions**, dont la concordance des 192 journaux complets.
- `make test` : **158 tests, 5 erreurs et 14 échecs**. Une copie témoin externe du catalogue et du moteur précédents reproduit les mêmes tests en échec, avec 5 erreurs et 18 échecs avant actualisation des attentes de combat. Aucun nouveau test en échec n’est introduit par R14. Les problèmes restants concernent notamment les boosts/files, les prérequis, des attentes de recherche et l’entrée dans un combat pour le test de butin ; ils ne sont pas corrigés dans cette intégration.
- `make lint` : syntaxe PHP, YAML/JSON, Twig, injection des services et mapping Doctrine réussis. La dernière étape échoue car le schéma de la base n’est pas synchronisé. Aucune migration n’a été exécutée.
- `git diff --check` : réussi. Aucune modification d’interface apportée par R14 ; compilation front et tests navigateur non relancés pour ce changement.

La transposition de R14 est contrôlée ; la branche complète ne peut pas être présentée comme entièrement validée tant que ces vérifications générales restent rouges.

##### Fichiers du changement R14

- Moteur : `src/Service/Combat/CombatSimulator.php`, `src/Service/Catalog/GameCatalog.php`.
- Catalogue : `config/game/combat_modifiers.yaml` et, dans `config/game/ships/`, `fighter.yaml`, `interceptor.yaml`, `heavy_fighter.yaml`, `bomber.yaml`, `frigate.yaml`, `cruiser.yaml`, `destroyer.yaml`, `battleship.yaml`, `capital.yaml`.
- Tests : `tests/Service/CombatSimulatorTest.php`, `tests/Service/CombatCountersTest.php`, `tests/Service/BalanceMechanicsTest.php`, `tests/Integration/CatalogBalanceTest.php`, `tests/Service/R14RegressionTest.php`, `tests/Fixtures/combat-r14.json`.
- Documentation : `docs/DECISIONS.md`, `docs/REGLES.md`, `docs/BALANCING_R14.md`.

## Production des bâtiments

### Bâtiments — cadence A et centrale ×2

> Historique : la répartition des ressources et les délais de progression de ce bilan sont remplacés, lorsqu’ils diffèrent, par la [grille commune avec hydrogène T3](COUTS.md). Les moteurs, courbes et statistiques restent conservés.

La décision utilisateur valide une cadence de remboursement d’environ 2–3 semaines pour une amélioration de mine de métal T1 vers le niveau 80 en univers normal, puis la centrale ×2 avec intérêt à développer ses rangs T2/T3. Les paramètres ci-dessous sont intégrés pour les tests Alpha.

#### Paramètres appliqués

| Paramètre | T1 | T2 | T3 |
|---|---:|---:|---:|
| Production des producteurs | ×1 | ×1,5 | ×2,25 |
| Consommation électrique et intrants industriels | ×1 | ×1,5 | ×2,25 |
| Durée de construction, facteur de rang | ×1 | ×1,25 | ×1,5625 |
| Stockage ajouté par niveau | ×1 | ×1,25 | ×1,5625 |

La centrale produit **0,2 électricité/s/niveau basique**, soit 720/h, contre 360/h auparavant. Ses niveaux T2 et T3 apportent respectivement 1 080 et 1 620/h en univers normal à la position de référence. Le facteur propre à la production est configuré dans `building_production_tier_factors` ; il ne remplace pas le facteur partagé des coûts, durées, recherches, vitesses ou stockages.

Les productions de base métal/nano/hydrogène et antimatière restent conservées. Le réacteur consomme toujours 8 H par A produite. Les bonus et plafonds des laboratoires, chantiers et bunkers restent conservés.

#### Courbe des coûts des producteurs

| Niveau atteint | Multiplicateur par rapport au niveau précédent |
|---|---:|
| 2 à 20 | ×1,12 |
| 21 à 40 | ×1,09 |
| 41 à 80 | ×1,035 |
| 81 à 120 | ×1,015 |
| Après 120 | ×1,01 |

Cette courbe, `production_building_cost`, concerne uniquement les bâtiments ayant une ressource de production : métal, nano, hydrogène, antimatière et électricité. Les profils de coûts par rang et les arrondis supérieurs de chaque ressource restent utilisés. Les prix du premier niveau et ceux jusqu’au niveau 40 ne sont pas changés par la nouvelle courbe. Les réductions de recherche s’appliquent normalement.

Les stockages, laboratoires, chantiers et bunkers conservent la courbe `building_cost`. Les recherches et leurs courbes propres restent conservées. Les temps de construction ne sont pas abaissés avec les prix.

#### Usines et stockage plein

Une usine de métal, nano ou antimatière dont la sortie ne peut plus être stockée suspend sa production et ses consommations. Le calcul s’arrête au moment d’un remplissage ou d’un épuisement, puis recalcule les activités pour le temps restant.

Un stockage d’hydrogène plein ne coupe pas l’approvisionnement immédiat du réacteur : la raffinerie fournit le débit utile restant après la production gratuite de base. Sans besoin en aval, elle cesse sa consommation électrique. La centrale continue à alimenter les usines quand le stockage électrique est plein. Les débits affichés n’ajoutent pas les excédents qui ne peuvent pas être stockés.

Les petits résidus numériques proches de zéro ou d’un plafond sont normalisés à une tolérance de 10⁻⁷ unité pour éviter d’abandonner une période de production. Si le calcul ne progresse pas ou ne peut pas intégrer toute la période dans sa borne de segments, il signale explicitement l’échec au lieu de retourner un résultat incomplet. Le rendement de secours électrique reste de 10 % et ne permet pas de fabriquer de l’antimatière sans hydrogène.

#### Résultats de référence

Mine de métal T1 vers le niveau 80, position 5, univers normal, sans réduction de coût par recherche :

- Prix proposé de la mine : **152 890 métal**, soit 305 780 unités économiques.
- Gain nominal : **360 métal/h**, soit 720 unités économiques/h.
- Remboursement nominal de la mine seule après livraison : **17,70 jours**.
- En allouant la capacité électrique ajoutée dans le plan de référence utilisant les centrales T1/T2/T3 : **18,22 jours**.
- Pour la même amélioration en T3, la méthode avec allocation électrique donne **14,80 jours**.

L’allocation électrique est un outil de comparaison : le joueur achète des niveaux entiers de centrale. Les prérequis, les stockages et les délais de financement ne sont pas tous imputés au seul dernier niveau. Une colonie conservant uniquement des centrales T1 n’a pas le même rendement : la progression des rangs énergétiques est utile. Les positions, les pénuries, le stockage saturé et les usages de ressources peuvent changer les résultats. Ces délais ne sont pas des garanties pour toutes les situations.

Une trajectoire financée depuis les stocks initiaux, sans bâtiment ni recherche, atteint les trois mines métal/nano/hydrogène au niveau 80 en **25,11 jours en normal**, ou **15,07 h en Alpha ×40**, avec développement énergétique T2/T3. Elle utilise une planète en position 5, un réacteur niveau 1, aucun raid ni échange, et lance immédiatement chaque action possible, une par une. Ce n’est pas une stratégie optimale ni une promesse de temps de progression pour un joueur réel. Le délai d’accès au niveau est distinct du remboursement de sa dernière amélioration.

#### Vérifications de l’intégration

- Le fichier `tests/Fixtures/building-balance-a.json` fige les références calculées hors dépôt avant modification : **450 mesures nominales, 64 simulations et 18 contrôles historiques de coûts**, avec empreintes de provenance.
- Les tests comparent les vrais coûts, durées, gains, consommations, stocks et débits du code intégré à ces références. Ils contrôlent aussi l’égalité entre calcul continu et pas horaires, les surcharges d’univers, les stockages pleins et les consommations utiles en aval.
- Les **quatre trajectoires centrale ×2** ont été rejouées avec le code réellement intégré, en pas horaires : **1 260 achats** retrouvent les coûts, prérequis, délais et stocks du candidat, avec un écart maximal de stock inférieur à 2 × 10⁻¹⁰ unité.
- Le bloc des gains et la fiche détaillée utilisent les nouveaux facteurs et affichent leurs consommations nominales. Un test contrôle notamment les 1 620 électricité/h du niveau T3 de centrale et les 6 480 H/h d’un niveau T3 de réacteur.
- `make validate` réussi. Syntaxe PHP, JSON/YAML, Twig, injection et mapping Doctrine réussis dans `make lint`. La dernière étape reste en échec sur le schéma de base désynchronisé ; aucune migration exécutée.
- `make test` : **180 tests, 14 échecs et 6 erreurs restantes**, dont des problèmes déjà observés sur les boosts, prérequis, recherches et espionnage. La branche globale n’est pas entièrement verte. Les tests ciblés de ce changement passent.
- Le test d’intégration du réservoir sans fret utilise désormais une Sonde, dont la soute reste nulle, plutôt qu’un Chasseur doté de la soute validée précédemment.

#### Données existantes et limites

Les prix et durées des constructions déjà payées restent enregistrés ; il n’y a ni remboursement automatique ni réécriture des files. Les surcharges de catalogue par univers restent prioritaires et peuvent masquer les nouvelles valeurs de base. Aucune migration ni remise à zéro de sauvegarde n’est effectuée.

Les productions sont recalculées avec les règles chargées au moment de la lecture ou de la matérialisation. Il n’existe pas de version historique des règles de production : lors d’un déploiement sur un univers actif, la période écoulée depuis sa dernière matérialisation peut être recalculée avec les nouveaux paramètres. La transition d’un univers actif doit être organisée séparément ; elle n’est pas automatisée ici.

Les simulations ne valident pas encore le métajeu complet : plusieurs planètes, rythmes de connexion humains, files prépayées, pillage, coûts militaires, équilibre des pénuries et durée de vie réelle des univers restent à mesurer en Alpha.

#### Fichiers de ce changement

- Paramètres : `config/game/rules.yaml`, `config/game/buildings/power_plant.yaml` (format JSON conservé).
- Calculs et présentation : `src/Service/Catalog/GameCatalog.php`, `src/Service/Colony/ResourceService.php`, `src/Service/Colony/FactoryEconomy.php`, `src/Service/Colony/CatalogueDetails.php`.
- Tests : `tests/Service/BuildingBalanceTest.php`, `tests/Fixtures/building-balance-a.json`, `tests/Service/FactoryEconomyTest.php`, `tests/Service/CatalogueDetailsTest.php`, `tests/Integration/CatalogBalanceTest.php`, `tests/Integration/GamePersistenceTest.php`.
- Documentation : `docs/DECISIONS.md`, `docs/REGLES.md`, le présent document.

Les profils de ressources des vaisseaux ont ensuite été modifiés par décision distincte, à budget économique avant recherche conservé : voir [SHIP_COSTS_2026-11-05.md](COUTS.md). Les références historiques des tests restent figées ; leurs assertions de prix naval utilisent le nouveau fichier de référence.

## Vitesses et autonomie

### Mobilité par rôle et autonomie — 5 novembre 2026

La dernière décision utilisateur remplace la référence « Chasseur le plus lent » : **Capital → Cuirassé → Colonie → Transport → Bombardier → Destroyer → Croiseur → Frégate → Chasseur lourd → Chasseur → Corvette → Furtif → Intercepteur → Sonde**. Cet ordre s’applique dans un système et entre systèmes, à rang/recherches comparables.

Les plafonds de référence sont désormais attribués au Capital : 1 h pour le plus long déplacement entre planètes d’un même système et environ 12 h pour le plus long trajet entre systèmes. La Sonde conserve ses plafonds de référence de 1 min et de 3 min. Ces références sont au rang basique, sans recherche, départ à 100 %, univers normal. Les recherches, les rangs et l’accélération des univers restent applicables ; un départ volontairement ralenti peut dépasser ces durées.

#### Profils appliqués et durées maximales par aller

Le retour utilise la même durée enregistrée. Les stationnements, combats et délais de traitement sont supplémentaires. Les vitesses sont les deux indices utilisés par le moteur pour les segments locaux et hyperspatiaux.

| Classe, du plus lent au plus rapide | Vitesse locale | Vitesse entre systèmes | Maximum local | Maximum entre systèmes |
|---|---:|---:|---|---|
| Capital | 80 | 392 | 1 h   | 11 h 59 min 27 s |
| Cuirassé | 88 | 436 |  54 min 33 s | 10 h 48 min 12 s |
| Colonie | 96 | 476 |  50 min  | 9 h 53 min 49 s |
| Transport | 104 | 515 |  46 min 10 s | 9 h 8 min 43 s |
| Bombardier | 112 | 555 |  42 min 52 s | 8 h 29 min 14 s |
| Destroyer | 120 | 594 |  40 min  | 7 h 55 min 42 s |
| Croiseur | 128 | 634 |  37 min 30 s | 7 h 25 min 45 s |
| Frégate | 136 | 674 |  35 min 18 s | 6 h 59 min 20 s |
| Chasseur lourd | 144 | 713 |  33 min 20 s | 6 h 36 min 20 s |
| Chasseur | 152 | 753 |  31 min 35 s | 6 h 15 min 19 s |
| Corvette | 176 | 872 |  27 min 17 s | 5 h 24 min 6 s |
| Furtif | 192 | 1056 |  25 min  | 4 h 33 min 13 s |
| Intercepteur | 224 | 1320 |  21 min 26 s | 3 h 41 min 47 s |
| Sonde | 4800 | 305471 |  1 min  |  3 min  |

Les vitesses conservent les facteurs de rang 1 / 1,25 / 1,5625, avec arrondi supérieur, puis la recherche de propulsion. Une flotte utilise la vitesse effective la plus faible de sa composition pour chaque segment. La hiérarchie est vérifiée aux trois rangs ; des rangs ou recherches différents peuvent naturellement changer l’ordre entre deux unités.

#### Distances, minimums et référence maximale

La géométrie est conservée : écart de positions × 10 dans un système ; sortie et entrée des systèmes, puis distance euclidienne des coordonnées de carte × 100 entre systèmes. Le nombre inscrit dans les coordonnées A:40/A:80 ne mesure pas à lui seul une distance.

Durée brute = (distance normale / vitesse locale + distance hyperspatiale / vitesse intersystèmes) × 3600 × multiplicateur d’univers / pourcentage de départ. Le résultat est arrondi à la seconde supérieure, puis borné à **60 secondes minimum**. Une flotte exclusivement composée de sondes voyageant dans le même système dispose d’un minimum de **30 secondes**. Ajouter une autre classe rétablit le minimum de 60 secondes. Les sondes entre systèmes restent soumises au minimum de 60 secondes. Ces minimums sont appliqués après tous les bonus et multiplicateurs.

Sur la grille maximale de 100 systèmes, le maximum local entre planètes est position 1 → 9. Le maximum intersystèmes correspond notamment à A:1 → A:100, de position 1 à position 1 : 180 unités de distance normale et environ 3 818,38 unités hyperspatiales. Les plafonds sont respectés sans écrêter artificiellement les durées. L’indice entier du Capital donne 11 h 59 min 27 s, soit 33 secondes sous 12 h ; la Sonde atteint 180 secondes.

En Alpha ×40, le maximum intersystèmes de référence prend 18 min en Capital et 1 min en Sonde. Les déplacements locaux les plus longs prennent alors 90 secondes en Capital et 30 secondes en Sonde. Les surcharges de catalogue propres aux univers restent conservées et peuvent remplacer les valeurs de base.

#### Réservoirs et autonomie

**Les 14 classes possèdent un réservoir dédié de 16 000 H au rang basique.** Aux rangs intermédiaire et supérieur, il contient respectivement 20 000 H et 25 000 H avant recherche. La recherche de réservoir continue d’augmenter ces capacités. Les soutes restent distinctes des réservoirs et entièrement disponibles pour le fret quand le réservoir finance le trajet. Leurs capacités ont ensuite été validées séparément : voir la section « Soutes des vaisseaux » dans [REGLES.md](REGLES.md).

Les consommations actuelles sont identiques pour toutes les classes : **0,5 H par unité de distance normale**, **2 H par unité hyperspatiale** et **0,3 H par heure de stationnement**. Il en résulte un même besoin maximal pour chaque vaisseau :

- Aller-retour le plus long : **15 453,5065 H** à 100 %, sans stationnement.
- Marge d’un réservoir basique plein : **546,4935 H**, environ 3,5 % du besoin.
- Avec la réserve des dix minutes de combat : **15 453,5565 H**, toujours sous 16 000 H.

Le jeu utilise la politique de carburant proportionnelle au pourcentage de départ : partir à 100 % est donc le cas le plus coûteux. Les tests couvrent également les départs à 10 % et à 50 %. Aucun tarif par distance n’est modifié ici ; la consommation totale n’augmente pas parce qu’un vaisseau voyage plus lentement. Des consommations distinctes selon le rôle nécessiteraient une décision d’équilibrage séparée.

La capacité ne crée pas de carburant : le stock d’hydrogène nécessaire doit être disponible au départ. Le jeu continue de réserver le besoin calculé de la mission ; il ne charge pas automatiquement chaque réservoir au maximum. Un long stationnement consomme en supplément et peut dépasser la marge. Les flottes déjà parties conservent leur durée et leur réserve enregistrées.

#### Couverture des distances et vérifications

Le contrôle visite toutes les paires de planètes de la grille maximale générée et des cartes JSON du projet :

| Carte | Paires de planètes |
|---|---:|
| Grille générée : 100 systèmes × 9 planètes | 404 550 |
| Carte importée Omega | 3 240 |
| Carte importée Turbo | 2 080 |
| Total | **409 870** |

Ces paires correspondent à **872 distances distinctes**, regroupées uniquement lorsque les distances locales et hyperspatiales sont identiques. Les durées des 14 classes sont contrôlées dans les deux sens, soit **24 416 devis**. L’autonomie est vérifiée sur toutes ces distances, pour chaque classe et aux trois rangs, avec la réserve de combat. Les tests vérifient que le carburant ne prend aucune place dans la soute, que la recherche de réservoir augmente l’autonomie, et qu’une flotte mixte conserve sa capacité de fret.

Cette couverture concerne les déplacements entre planètes de ces cartes et la grille maximale permise actuellement. Elle ne prétend pas couvrir des coordonnées modifiées directement en base ou des surcharges personnalisées. L’exploration vise un bord de système conventionnel à la position 10, qui n’est pas une planète : les plafonds locaux entre planètes ne lui sont pas appliqués artificiellement. Les trajets intergalaxies restent non définis par le moteur.

- `make validate` réussi.
- **19 tests ciblés, 172 902 assertions réussies** : déplacements, couverture des cartes, autonomie, ordre des classes aux trois rangs, caractéristiques affichées avec recherche et concordance des combats R14.
- `make test` : **171 tests, 5 erreurs et 14 échecs préexistants**, sans nouveau test en échec. Les contrôles ciblés ci-dessus passent.
- `make lint` : syntaxe PHP, configuration JSON/YAML, Twig, injection des services et mapping Doctrine réussis. La dernière étape échoue encore sur le schéma de base désynchronisé ; aucune migration exécutée.
- `git diff --check` réussi. Aucune compilation d’assets nécessaire.

#### Fichiers concernés

Les 14 fiches `config/game/ships/*.yaml` restent au format JSON ; seules leurs deux vitesses, leur capacité de carburant et la note de réservoir sont ajustées dans cette dernière décision. Les coûts, consommations, caractéristiques de combat R14, durées de construction, prérequis et assets sont conservés. Les capacités de fret font l’objet d’une décision ultérieure distincte dans [REGLES.md](REGLES.md).

Tests : `tests/Service/MovementCalculatorTest.php`, `tests/Service/MovementMapCoverageTest.php`. Décisions et règles : `docs/DECISIONS.md`, `docs/REGLES.md` et le présent document. Le calculateur de déplacement conserve les minimums adoptés précédemment et ne nécessite pas de nouvelle modification.
