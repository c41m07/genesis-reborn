# Mobilité par rôle et autonomie — 5 novembre 2026

La dernière décision utilisateur remplace la référence « Chasseur le plus lent » : **Capital → Cuirassé → Colonie → Transport → Bombardier → Destroyer → Croiseur → Frégate → Chasseur lourd → Chasseur → Corvette → Furtif → Intercepteur → Sonde**. Cet ordre s’applique dans un système et entre systèmes, à rang/recherches comparables.

Les plafonds de référence sont désormais attribués au Capital : 1 h pour le plus long déplacement entre planètes d’un même système et environ 12 h pour le plus long trajet entre systèmes. La Sonde conserve ses plafonds de référence de 1 min et de 3 min. Ces références sont au rang basique, sans recherche, départ à 100 %, univers normal. Les recherches, les rangs et l’accélération des univers restent applicables ; un départ volontairement ralenti peut dépasser ces durées.

## Profils appliqués et durées maximales par aller

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

## Distances, minimums et référence maximale

La géométrie est conservée : écart de positions × 10 dans un système ; sortie et entrée des systèmes, puis distance euclidienne des coordonnées de carte × 100 entre systèmes. Le nombre inscrit dans les coordonnées A:40/A:80 ne mesure pas à lui seul une distance.

Durée brute = (distance normale / vitesse locale + distance hyperspatiale / vitesse intersystèmes) × 3600 × multiplicateur d’univers / pourcentage de départ. Le résultat est arrondi à la seconde supérieure, puis borné à **60 secondes minimum**. Une flotte exclusivement composée de sondes voyageant dans le même système dispose d’un minimum de **30 secondes**. Ajouter une autre classe rétablit le minimum de 60 secondes. Les sondes entre systèmes restent soumises au minimum de 60 secondes. Ces minimums sont appliqués après tous les bonus et multiplicateurs.

Sur la grille maximale de 100 systèmes, le maximum local entre planètes est position 1 → 9. Le maximum intersystèmes correspond notamment à A:1 → A:100, de position 1 à position 1 : 180 unités de distance normale et environ 3 818,38 unités hyperspatiales. Les plafonds sont respectés sans écrêter artificiellement les durées. L’indice entier du Capital donne 11 h 59 min 27 s, soit 33 secondes sous 12 h ; la Sonde atteint 180 secondes.

En Alpha ×40, le maximum intersystèmes de référence prend 18 min en Capital et 1 min en Sonde. Les déplacements locaux les plus longs prennent alors 90 secondes en Capital et 30 secondes en Sonde. Les surcharges de catalogue propres aux univers restent conservées et peuvent remplacer les valeurs de base.

## Réservoirs et autonomie

**Les 14 classes possèdent un réservoir dédié de 16 000 H au rang basique.** Aux rangs intermédiaire et supérieur, il contient respectivement 20 000 H et 25 000 H avant recherche. La recherche de réservoir continue d’augmenter ces capacités. Les soutes restent distinctes des réservoirs et entièrement disponibles pour le fret quand le réservoir finance le trajet. Leurs capacités ont ensuite été validées séparément : voir la section « Soutes des vaisseaux » dans [REGLES.md](REGLES.md).

Les consommations actuelles sont identiques pour toutes les classes : **0,5 H par unité de distance normale**, **2 H par unité hyperspatiale** et **0,3 H par heure de stationnement**. Il en résulte un même besoin maximal pour chaque vaisseau :

- Aller-retour le plus long : **15 453,5065 H** à 100 %, sans stationnement.
- Marge d’un réservoir basique plein : **546,4935 H**, environ 3,5 % du besoin.
- Avec la réserve des dix minutes de combat : **15 453,5565 H**, toujours sous 16 000 H.

Le jeu utilise la politique de carburant proportionnelle au pourcentage de départ : partir à 100 % est donc le cas le plus coûteux. Les tests couvrent également les départs à 10 % et à 50 %. Aucun tarif par distance n’est modifié ici ; la consommation totale n’augmente pas parce qu’un vaisseau voyage plus lentement. Des consommations distinctes selon le rôle nécessiteraient une décision d’équilibrage séparée.

La capacité ne crée pas de carburant : le stock d’hydrogène nécessaire doit être disponible au départ. Le jeu continue de réserver le besoin calculé de la mission ; il ne charge pas automatiquement chaque réservoir au maximum. Un long stationnement consomme en supplément et peut dépasser la marge. Les flottes déjà parties conservent leur durée et leur réserve enregistrées.

## Couverture des distances et vérifications

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

## Fichiers concernés

Les 14 fiches `config/game/ships/*.yaml` restent au format JSON ; seules leurs deux vitesses, leur capacité de carburant et la note de réservoir sont ajustées dans cette dernière décision. Les coûts, consommations, caractéristiques de combat R14, durées de construction, prérequis et assets sont conservés. Les capacités de fret font l’objet d’une décision ultérieure distincte dans [REGLES.md](REGLES.md).

Tests : `tests/Service/MovementCalculatorTest.php`, `tests/Service/MovementMapCoverageTest.php`. Décisions et règles : `docs/DECISIONS.md`, `docs/REGLES.md` et le présent document. Le calculateur de déplacement conserve les minimums adoptés précédemment et ne nécessite pas de nouvelle modification.
