# Durées et carburant — décision du 15 novembre 2026

Lot 2 fusionné dans dev par la [PR #70](https://github.com/c41m07/genesis-reborn/pull/70), commit `6fa8823`, après CI complète n° 295 réussie. Il part de `01bc65a` après la [PR #69](https://github.com/c41m07/genesis-reborn/pull/69). Aucun déploiement Alpha. Voir le [suivi](PROGRESSION_CARBURANT_UNITES.md).

## Référence de lecture

**Actualisation du 18 novembre 2026 :** la [décision sur la géographie](DECISIONS.md)
adopte la spirale réelle pour les nouveaux trajets et replis. Les exemples utilisant
les anciens écarts de grille sont des références historiques, pas des devis actuels.
Les coefficients, vitesses et calculs à distance fixée présentés ici restent valables.
Les vols déjà lancés conservent leurs durées et réservations figées.

Les tableaux concernent le Basique, un univers ×1, avant réduction : niveau 1 pour bâtiments et recherches, une unité pour les vaisseaux et défenses. Ce lot de durées/carburant ne modifiait pas les profils M/N/A/H. La décision
ultérieure du 16 novembre adopte la [nouvelle répartition](COUTS.md), sans changer
les durées ni les coefficients présentés ici.

Les trois rangs multiplient la durée une seule fois : ×1, ×1,25 et ×1,5625. Les multiplicateurs d’univers et rendements décroissants existants restent appliqués à la commande. Le plafond de réduction est de 70 %, le minimum d’une seconde et l’arrondi au supérieur restent en place.

## Bâtiments — durées conservées

| Famille | Durée du niveau 1 Basique |
| --- | --- |
| Bunker de défense | 6 min |
| Laboratoire colonial | 4 min |
| Piège à antimatière | 2 min |
| Raffinerie d’hydrogène | 1 min |
| Mine à ciel ouvert | 1 min |
| Atelier de nanofabrication | 1 min |
| Centrale nucléaire | 2 min |
| Chantier d’assemblage | 30 min |
| Cage magnétique | 3 min |
| Batterie de colonie | 3 min |
| Réservoir cryogénique | 3 min |
| Dépôt de minerais | 3 min |
| Coffre de nanomatériaux | 3 min |

La courbe reste ×1,1225 aux niveaux 2–20 ; ×1,122 aux niveaux 21–40 ; ×1,0643 aux niveaux 41–80 ; ×1,0353 aux niveaux 81–120 ; ×1,0373 ensuite. Les frontières 20/21, 40/41, 80/81, 100/101 et 120/121 sont vérifiées dans le calculateur réel. Le découpage interne à 100 utilise le même facteur de part et d’autre.

## Recherches

| Famille | Ancienne durée | Nouvelle durée | Ancienne taxe E | Nouvelle taxe E |
| --- | --- | --- | --- | --- |
| Drones réparateurs | 10 min | 45 min | 1 000 | 4 500 |
| Protocoles de colonisation | 80 h | 80 h | 480 000 | 480 000 |
| Réservoirs cryogéniques | 2 h | 45 min | 12 000 | 4 500 |
| Génie civil colonial | 10 min | 10 min | 1 000 | 1 000 |
| Manœuvres d’évitement | 2 h | 2 h | 12 000 | 12 000 |
| Discipline du secret | 10 min | 45 min | 1 000 | 4 500 |
| Soute compartimentée | 2 h | 30 min | 12 000 | 3 000 |
| Métallurgie des nanomatériaux | 10 min | 20 min | 1 000 | 2 000 |
| Architecture navale | 10 min | 15 min | 1 000 | 1 500 |
| Séquenceur de salves | 6 h | 4 h | 36 000 | 24 000 |
| Générateurs de bouclier accordés | 4 h | 2 h | 24 000 | 12 000 |
| Écran de brouillage | 10 min | 1 h | 1 000 | 6 000 |
| Revêtement absorbant | 2 h | 1 h 30 min | 12 000 | 9 000 |
| Blindage multicouche | 4 h | 1 h | 24 000 | 6 000 |
| Analyse tactique | 10 min | 20 min | 1 000 | 2 000 |
| Télémétrie de poursuite | 10 min | 1 h | 1 000 | 6 000 |
| Propulseurs vectoriels | 1 h | 45 min | 6 000 | 4 500 |
| Viseurs balistiques | 2 h | 45 min | 12 000 | 4 500 |
| Canons à énergie focalisée | 6 h | 30 min | 36 000 | 3 000 |

La durée croît à chaque niveau : ×1,35 de 2 à 5 ; ×1,25 de 6 à 10 ; ×1,18 de 11 à 15 ; ×1,12 ensuite lorsque le plafond l’autorise. Les effets, bonus, quotas et plafonds de recherche restent inchangés.

## Vaisseaux existants

| Famille | Ancienne durée | Nouvelle durée | Ancienne taxe E | Nouvelle taxe E |
| --- | --- | --- | --- | --- |
| Cuirassé de ligne | 48 h | 12 h | 288 000 | 72 000 |
| Bombardier tactique | 20 min | 20 min | 2 000 | 2 000 |
| Vaisseau capital | 72 h | 24 h | 432 000 | 144 000 |
| Vaisseau colonisateur | 2 h | 2 h | 12 000 | 12 000 |
| Corvette de patrouille | 45 min | 30 min | 4 500 | 3 000 |
| Croiseur de combat | 16 h | 6 h | 96 000 | 36 000 |
| Destroyer d’escorte | 6 h | 3 h | 36 000 | 18 000 |
| Chasseur de patrouille | 3 min | 3 min | 300 | 300 |
| Frégate d’escorte | 2 h | 1 h 30 min | 12 000 | 9 000 |
| Chasseur lourd | 10 min | 10 min | 1 000 | 1 000 |
| Intercepteur atmosphérique | 4 min | 4 min | 400 | 400 |
| Sonde de reconnaissance | 2 min | 2 min | 200 | 200 |
| Chasseur furtif | 15 min | 15 min | 1 500 | 1 500 |
| Cargo de ligne | 1 h | 30 min | 6 000 | 3 000 |

Exemple Capital : Basique 24 h, Intermédiaire 30 h, Supérieur 37 h 30 ; avec une réduction effective de 70 %, le Basique dure exactement 7 h 12. La soustraction des pourcentages avant division corrige une imprécision numérique qui ajoutait une seconde à ce dernier cas.

## Nouvelles unités économiques — lot 3

Récolteur et satellite : 180 s Basique / 225 s Intermédiaire / 282 s Supérieur,
soit 300 / 400 / 500 E avant réductions, une unité à vitesse ×1. Le calculateur
des vaisseaux applique les bonus, lots et taxe usuels ; le satellite reste immobile.
Carburant du récolteur : 0,05 normal / 0,20 hyperespace / aucun maintien propre.
Les [profils, conditions et limites](UNITES_ECONOMIQUES.md) complètent les 150
profils existants. La comparaison courante couvre 156 profils ; pour les six
nouveaux, aucune durée historique n’existait. L’archive datée reste inchangée.

## Défenses

| Famille | Ancienne durée | Nouvelle durée | Ancienne taxe E | Nouvelle taxe E |
| --- | --- | --- | --- | --- |
| Canon laser anti-léger | 5 min | 5 min | 500 | 500 |
| Canon laser anti-escorteur | 9 min | 15 min | 900 | 1 500 |
| Canon laser anti-lourd | 15 min | 30 min | 1 500 | 3 000 |
| Canon laser anti-capital | 21 min | 1 h | 2 100 | 6 000 |

Aucune réduction de durée par le chantier, le bunker ou une recherche n’est ajoutée aux défenses. Seuls les multiplicateurs de rang, d’univers et les boosts existants restent applicables.

## Taxe électrique et quantités

La taxe reste `100 × plafond(durée effective enregistrée en secondes / 60)` pour recherches, vaisseaux et défenses. Les bâtiments en sont exclus. La durée totale du lot est calculée et arrondie une fois ; la taxe utilise cette durée totale, pas la somme des taxes de commandes individuelles.

Exemple Sonde Supérieure sans réduction : une unité prend 188 s et 400 E ; deux unités en une commande prennent 375 s et 700 E. Les devis, maxima et paiements utilisent le même calculateur.

Le Capital Basique passe de 432 000 E à 144 000 E sans réduction, soit −288 000 E (−66,67 %). À réduction maximale, ses 7 h 12 coûtent 43 200 E ; le faux dépassement numérique précédent aurait affiché 43 300 E. Les baisses ne sont pas uniformes : l’anti-capital passe de 2 100 à 6 000 E (+185,71 %), la Télémétrie de 1 000 à 6 000 E. Les tableaux exposent aussi les hausses.

## Occupation de la file commune par la colonisation

Protocoles de colonisation conserve 80 h au niveau 1 Basique avant réduction. Les niveaux suivants conservent la croissance de recherche. Les nouvelles recherches partagent une file par joueur et univers : une commande passée depuis une autre colonie attend aussi cette opération. Les éventuelles recherches parallèles déjà acceptées historiquement restent résolues selon leur chronologie enregistrée.

| Scénario ×1 | Occupation par le niveau 1 | Taxe E | Avec Génie civil niveau 3 ensuite |
| --- | --- | --- | --- |
| Référence sans réduction | 80 h | 480 000 | 80 h 18 min 14 s |
| Laboratoire Basique 2 | 77 h 54 min 17 s | 467 500 | 78 h 12 min 2 s |
| Laboratoire Basique 25 seul | 58 h 30 min 12 s | 351 100 | 58 h 43 min 32 s |
| Réduction maximale, laboratoire B/I/S 25 sur la colonie | 24 h | 144 000 | 24 h 5 min 29 s |

La recherche suivante utilise ici les mêmes niveaux de laboratoire sur la même colonie ; une commande ajoutée depuis une autre colonie utilise ses propres bonus locaux. Ces scénarios isolent les durées et la file : le financement est supposé disponible et aucun coût de laboratoire préalable n’est inclus. Ils ne donnent ni un parcours optimal ni un délai garanti depuis un compte neuf. L’accès initial exige les prérequis de la grille ; après acquisition, une autre colonie sans laboratoire peut utiliser cet accès, avec sa réduction locale éventuelle.

## Carburant par classe

Ancien barème uniforme : 0,5 H par distance normale, 2 H par distance hyperspatiale, 0,3 H/h de maintien. Nouveau barème identique dans les trois rangs :

| Famille | H / distance normale | H / distance hyperspatiale | Maintien H/h |
| --- | --- | --- | --- |
| Cuirassé de ligne | 0,4 | 1,6 | 0,24 |
| Bombardier tactique | 0,1 | 0,4 | 0,06 |
| Vaisseau capital | 0,5 | 2 | 0,3 |
| Vaisseau colonisateur | 0,15 | 0,6 | 0,09 |
| Corvette de patrouille | 0,12 | 0,48 | 0,072 |
| Croiseur de combat | 0,3 | 1,2 | 0,18 |
| Destroyer d’escorte | 0,25 | 1 | 0,15 |
| Chasseur de patrouille | 0,05 | 0,2 | 0,03 |
| Frégate d’escorte | 0,18 | 0,72 | 0,108 |
| Chasseur lourd | 0,07 | 0,28 | 0,042 |
| Intercepteur atmosphérique | 0,04 | 0,16 | 0,024 |
| Sonde de reconnaissance | 0,01 | 0,04 | 0,006 |
| Chasseur furtif | 0,06 | 0,24 | 0,036 |
| Cargo de ligne | 0,1 | 0,4 | 0,06 |

Le maintien ne concerne que les missions qui le paient déjà. Le stationnement sur une colonie possédée garde ses règles sans nouveau coût horaire. À 50 % de vitesse, le trajet consomme moitié moins ; le maintien est indépendant de cette vitesse. Distances, géométrie, vitesses, soutes, réservoirs et minimums de trajet restent conservés. Les millièmes de H/h restent visibles dans les fiches.

Pour un aller-retour position 5 → 5 entre deux systèmes séparés d’une unité de coordonnées, sans maintien et à 100 % : Sonde 10 H, Chasseur 50 H, Cargo 100 H, Capital 500 H. Vingt chasseurs sur dix unités de coordonnées passent de 82 000 à 8 200 H ; à 50 %, 4 100 H. Ces références valident les trajets, pas l’équilibrage global.

## Commandes payées et missions historiques

Les coûts et durées enregistrés des commandes restent conservés, sans remboursement de différence. Les boosts modifient uniquement le temps restant selon leurs règles actuelles ; l’annulation reste calculée sur le paiement enregistré.

Les réserves de carburant, coûts des tronçons, retours et rappels sur la route figée restent conservés. Les nouveaux départs enregistrent en plus leurs coefficients par classe et rang dans la navigation existante. Les rappels vers une autre colonie et les replis de stationnement utilisent ces coefficients, même si le catalogue change ensuite. Aucun changement de schéma SQL.

Pour une ancienne mission sans ces coefficients, le catalogue de référence antérieur est repris avec les surcharges d’univers persistées. Une personnalisation modifiée après le départ ne peut pas être reconstituée avec certitude à partir de ce seul état : la vérifier avant une activation sur un environnement concerné. Un instantané présent mais incomplet est refusé ; il n’est pas remplacé silencieusement par le nouveau prix.

Le diagnostic local en lecture seule a trouvé zéro surcharge de durée/carburant et zéro flotte active. Cela ne prouve pas l’état d’Alpha. Avant une activation, coordonner code web et workers et examiner les personnalisations ainsi que les missions anciennes ; aucune reprise ni migration de la base de jeu n’a été exécutée.

## Mesure reproductible et limites

Commande en lecture seule, sans compte ni donnée de jeu nécessaire :

```bash
make -s console CMD="app:balance:compare-operations" > var/mesures-progression-operations.json
```

Elle compare 150 profils de durée, la colonisation suivie d’une recherche et les trajets des classes/rangs dans quatre scénarios. Les valeurs historiques sont bornées à la référence `01bc65a` conservée dans `app/config/legacy/progression-operations-2026-11-15.json`. Les opérations utilisent `OperationCalculator` et les trajets `MovementCalculator`.

La commande du lot 2 compare des devis, sans parcours de financement. Les
[simulations conjointes du lot 4](SIMULATIONS_PROGRESSION_ECONOMIE.md) complètent
ces mesures par les stocks, productions, déficits, unités civiles, missions et
reconstructions. Elles distinguent les références sans événements/vacances des
tests ciblés de projection ; aucune valeur n’est ajustée pour obtenir un délai.
