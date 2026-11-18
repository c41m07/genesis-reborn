# Grille de coûts commune — métal, nano, antimatière et hydrogène

La répartition de valeur **B48/52, I35/35/30, S25/35/30/10**
(métal / nano / antimatière / hydrogène) s’applique aux bâtiments, recherches,
vaisseaux et défenses, y compris les deux familles civiles. Les budgets réels
des 156 profils de base sont conservés exactement. Les prix Supérieurs sont
reconduits à l’identique.

Les [simulations du nouvel équilibrage](EQUILIBRAGE.md#équilibrage-des-prix-basiques-et-intermédiaires)
comparent les attentes de métal, de nano et d’antimatière. Les parcours
simulés donnent un ordre de grandeur pour des stratégies définies.

Les nouvelles commandes emploient le profil courant. Les commandes déjà payées
conservent coûts, durée, résultat et remboursement calculé sur leur paiement.
Les surcharges d’univers restent prioritaires ; aucune personnalisation n’est effacée.
La [taxe électrique](DUREES_CARBURANT.md) conserve sa formule et les bâtiments restent exclus.
L’état d’intégration et de mise en ligne est séparé dans le [suivi](PROGRESSION_CARBURANT_UNITES.md).

## Règle appliquée par défaut

| Rang | Métal | Nano | Antimatière | Hydrogène |
|---|---:|---:|---:|---:|
| Basique | 48 % | 52 % | 0 % | 0 % |
| Intermédiaire | 35 % | 35 % | 30 % | 0 % |
| Supérieur | 25 % | 35 % | 30 % | 10 % |

Il s’agit de parts de **valeur économique** : métal ×2, nano ×4, antimatière ×8, hydrogène ×1. Ce ne sont pas des pourcentages des quantités brutes. Chaque quantité est entière ; les arrondis sont choisis conjointement pour approcher la répartition et conserver **exactement la valeur réelle du profil de base de chaque rang**, avant croissance des niveaux et recherches.

La grille couvre **52 fiches et 156 profils** : 13 bâtiments, 19 recherches, 16 vaisseaux (dont les deux unités économiques) et 4 défenses. Les profils réels sont lus et conservés en JSON malgré leur extension `.yaml`. Les budgets déclaratifs ne remplacent pas leurs coûts arrondis effectifs.

La décision du 9 novembre 2026 révise volontairement les budgets du réacteur et de la centrale : 1 200 / 1 500 / 1 875 et 4 800 / 6 000 / 7 500. La conservation des budgets de la redistribution du 5 novembre reste une référence historique ; les nouveaux budgets et leurs parts de ressources font désormais foi pour ces deux familles. Voir [EQUILIBRAGE](EQUILIBRAGE.md#production-des-batiments).

## Coûts de base appliqués

Chaque cellule indique **métal / nano / antimatière / hydrogène** pour le niveau 1 ou une unité, sans réduction de recherche.

| Famille | Élément | T1 | T2 | T3 |
|---|---|---|---|---|
| buildings | Bunker de défense | 2 880 / 1 560 / 0 / 0 | 2 625 / 1 312 / 563 / 0 | 2 345 / 1 641 / 703 / 1 876 |
| buildings | Laboratoire colonial | 2 400 / 1 300 / 0 / 0 | 2 189 / 1 095 / 469 / 0 | 1 954 / 1 367 / 586 / 1 564 |
| buildings | Piège à antimatière | 288 / 156 / 0 / 0 | 264 / 131 / 56 / 0 | 235 / 164 / 70 / 189 |
| buildings | Raffinerie d’hydrogène | 384 / 208 / 0 / 0 | 350 / 175 / 75 / 0 | 312 / 219 / 94 / 250 |
| buildings | Mine à ciel ouvert | 384 / 208 / 0 / 0 | 350 / 175 / 75 / 0 | 312 / 219 / 94 / 248 |
| buildings | Atelier de nanofabrication | 384 / 208 / 0 / 0 | 350 / 175 / 75 / 0 | 312 / 219 / 94 / 250 |
| buildings | Centrale nucléaire | 1 152 / 624 / 0 / 0 | 1 050 / 525 / 225 / 0 | 938 / 656 / 281 / 752 |
| buildings | Chantier d’assemblage | 4 800 / 2 600 / 0 / 0 | 4 375 / 2 188 / 938 / 0 | 3 906 / 2 735 / 1 172 / 3 124 |
| buildings | Cage magnétique | 1 201 / 650 / 0 / 0 | 1 096 / 547 / 235 / 0 | 978 / 684 / 293 / 784 |
| buildings | Batterie de colonie | 1 201 / 650 / 0 / 0 | 1 096 / 547 / 235 / 0 | 978 / 684 / 293 / 784 |
| buildings | Réservoir cryogénique | 1 201 / 650 / 0 / 0 | 1 096 / 547 / 235 / 0 | 978 / 684 / 293 / 784 |
| buildings | Dépôt de minerais | 1 201 / 650 / 0 / 0 | 1 096 / 547 / 235 / 0 | 978 / 684 / 293 / 784 |
| buildings | Coffre de nanomatériaux | 1 201 / 650 / 0 / 0 | 1 096 / 547 / 235 / 0 | 978 / 684 / 293 / 784 |
| technologies | Drones réparateurs | 960 / 520 / 0 / 0 | 876 / 437 / 188 / 0 | 782 / 547 / 235 / 624 |
| technologies | Protocoles de colonisation | 5 280 / 2 860 / 0 / 0 | 4 814 / 2 407 / 1 031 / 0 | 4 298 / 3 009 / 1 289 / 3 438 |
| technologies | Réservoirs cryogéniques | 2 400 / 1 300 / 0 / 0 | 2 190 / 1 094 / 469 / 0 | 1 954 / 1 368 / 586 / 1 562 |
| technologies | Génie civil colonial | 960 / 520 / 0 / 0 | 876 / 437 / 188 / 0 | 782 / 547 / 235 / 624 |
| technologies | Manœuvres d’évitement | 1 440 / 780 / 0 / 0 | 1 314 / 657 / 281 / 0 | 1 172 / 821 / 352 / 938 |
| technologies | Discipline du secret | 1 440 / 780 / 0 / 0 | 1 314 / 657 / 281 / 0 | 1 172 / 821 / 352 / 938 |
| technologies | Soute compartimentée | 2 400 / 1 300 / 0 / 0 | 2 190 / 1 094 / 469 / 0 | 1 954 / 1 368 / 586 / 1 562 |
| technologies | Métallurgie des nanomatériaux | 960 / 520 / 0 / 0 | 876 / 437 / 188 / 0 | 782 / 547 / 235 / 624 |
| technologies | Architecture navale | 960 / 520 / 0 / 0 | 876 / 437 / 188 / 0 | 782 / 547 / 235 / 624 |
| technologies | Séquenceur de salves | 5 760 / 3 120 / 0 / 0 | 5 250 / 2 625 / 1 125 / 0 | 4 688 / 3 282 / 1 406 / 3 752 |
| technologies | Générateurs de bouclier accordés | 3 840 / 2 080 / 0 / 0 | 3 500 / 1 750 / 750 / 0 | 3 125 / 2 188 / 937 / 2 502 |
| technologies | Écran de brouillage | 960 / 520 / 0 / 0 | 876 / 437 / 188 / 0 | 782 / 547 / 235 / 624 |
| technologies | Revêtement absorbant | 1 440 / 780 / 0 / 0 | 1 314 / 657 / 281 / 0 | 1 172 / 821 / 352 / 938 |
| technologies | Blindage multicouche | 3 840 / 2 080 / 0 / 0 | 3 500 / 1 750 / 750 / 0 | 3 125 / 2 188 / 937 / 2 502 |
| technologies | Analyse tactique | 1 201 / 650 / 0 / 0 | 1 095 / 547 / 235 / 0 | 978 / 685 / 293 / 782 |
| technologies | Télémétrie de poursuite | 960 / 520 / 0 / 0 | 876 / 437 / 188 / 0 | 782 / 547 / 235 / 624 |
| technologies | Propulseurs vectoriels | 2 400 / 1 300 / 0 / 0 | 2 190 / 1 094 / 469 / 0 | 1 954 / 1 368 / 586 / 1 562 |
| technologies | Viseurs balistiques | 5 760 / 3 120 / 0 / 0 | 5 250 / 2 625 / 1 125 / 0 | 4 688 / 3 282 / 1 406 / 3 752 |
| technologies | Canons à énergie focalisée | 5 760 / 3 120 / 0 / 0 | 5 250 / 2 625 / 1 125 / 0 | 4 688 / 3 282 / 1 406 / 3 752 |
| ships | Cuirassé de ligne | 52 921 / 28 665 / 0 / 0 | 48 235 / 24 118 / 10 336 / 0 | 43 068 / 30 148 / 12 920 / 34 456 |
| ships | Bombardier tactique | 5 161 / 2 795 / 0 / 0 | 4 705 / 2 353 / 1 008 / 0 | 4 201 / 2 941 / 1 260 / 3 362 |
| ships | Vaisseau capital | 90 612 / 49 082 / 0 / 0 | 82 590 / 41 295 / 17 698 / 0 | 73 741 / 51 619 / 22 123 / 58 992 |
| ships | Vaisseau colonisateur | 9 600 / 5 200 / 0 / 0 | 8 752 / 4 375 / 1 875 / 0 | 7 812 / 5 469 / 2 344 / 6 250 |
| ships | Corvette de patrouille | 6 720 / 3 640 / 0 / 0 | 6 125 / 3 062 / 1 313 / 0 | 5 469 / 3 829 / 1 641 / 4 374 |
| ships | Croiseur de combat | 29 215 / 15 825 / 0 / 0 | 26 632 / 13 315 / 5 707 / 0 | 23 776 / 16 643 / 7 133 / 19 022 |
| ships | Destroyer d’escorte | 20 213 / 10 949 / 0 / 0 | 18 426 / 9 212 / 3 948 / 0 | 16 451 / 11 515 / 4 935 / 13 160 |
| ships | Chasseur de patrouille | 1 440 / 780 / 0 / 0 | 1 314 / 657 / 281 / 0 | 1 172 / 821 / 352 / 936 |
| ships | Frégate d’escorte | 10 934 / 5 923 / 0 / 0 | 9 968 / 4 984 / 2 136 / 0 | 8 899 / 6 230 / 2 670 / 7 120 |
| ships | Chasseur lourd | 3 552 / 1 924 / 0 / 0 | 3 239 / 1 619 / 694 / 0 | 2 892 / 2 025 / 868 / 2 312 |
| ships | Récolteur atmosphérique | 384 / 208 / 0 / 0 | 350 / 175 / 75 / 0 | 312 / 219 / 94 / 248 |
| ships | Intercepteur atmosphérique | 1 764 / 956 / 0 / 0 | 1 609 / 805 / 345 / 0 | 1 438 / 1 007 / 431 / 1 150 |
| ships | Sonde de reconnaissance | 240 / 130 / 0 / 0 | 219 / 110 / 47 / 0 | 196 / 138 / 59 / 156 |
| ships | Satellite héliocapteur | 384 / 208 / 0 / 0 | 350 / 175 / 75 / 0 | 312 / 219 / 94 / 248 |
| ships | Chasseur furtif | 5 856 / 3 172 / 0 / 0 | 5 339 / 2 669 / 1 144 / 0 | 4 766 / 3 336 / 1 430 / 3 814 |
| ships | Cargo de ligne | 5 760 / 3 120 / 0 / 0 | 5 252 / 2 625 / 1 125 / 0 | 4 688 / 3 282 / 1 407 / 3 748 |
| defenses | Canon laser anti-capital | 30 720 / 16 640 / 0 / 0 | 22 400 / 11 200 / 4 800 / 0 | 16 000 / 11 200 / 4 800 / 12 800 |
| defenses | Canon laser anti-escorteur | 4 320 / 2 340 / 0 / 0 | 3 152 / 1 575 / 675 / 0 | 2 251 / 1 575 / 675 / 1 802 |
| defenses | Canon laser anti-lourd | 11 280 / 6 110 / 0 / 0 | 8 225 / 4 112 / 1 763 / 0 | 5 875 / 4 113 / 1 762 / 4 700 |
| defenses | Canon laser anti-léger | 1 200 / 650 / 0 / 0 | 875 / 438 / 187 / 0 | 625 / 437 / 188 / 500 |

Exemple : le Chasseur T3 coûte désormais **1 172 M + 821 N + 352 A + 936 H**, pour une valeur toujours égale à **9 380**.

L’hydrogène payé à la construction ou à la recherche est une dépense industrielle consommée lors de la commande. Il ne remplit pas le réservoir d’un vaisseau et ne remplace pas le carburant réservé pour une mission. La conversion d’antimatière consomme toujours son hydrogène séparément.

## Prix réels après croissance et recherches

Les courbes de coûts, durées, réductions technologiques et règles d’arrondi restent inchangées. Chaque ressource est arrondie séparément après application de la croissance et des réductions : conserver exactement la valeur d’un profil de base ne garantit pas une égalité parfaite de tous les prix arrondis aux niveaux suivants.

La référence `tests/Fixtures/resource-costs-2026-11-16.json` conserve les profils
historiques et la grille précédant ce rééquilibrage. Les tests comparent les
156 budgets de base et les 3 636 devis aux frontières des courbes, en lots de
1/10/100 et avec ou sans réductions. À bonus égal, les durées et la taxe électrique
restent identiques. Les deux arrondis par ressource (croissance puis réduction)
peuvent entraîner de petits écarts de valeur après application des courbes.

La redistribution porte sur les `cost_profiles`. Le même lot réduit l’entretien
électrique direct du piège à antimatière. Les anciennes études sont rejouées
avec leurs prix et références de l’époque.

## Démarrage et déblocages

Les stocks de départ actuels permettent de payer les premiers extracteurs T1 de métal, nano, hydrogène et antimatière sans rareté préalable : ces quatre premiers achats ne demandent ni antimatière ni hydrogène. Une nouvelle colonie ne peut pas nécessairement acheter les quatre extracteurs et la centrale immédiatement ; la centrale devient finançable avec la production. Les tests reproduisent les achats, les temps de construction et la production aux positions 1, 5 et 9 avec les deux kits de départ réels. La production gratuite de métal et nano reste positive, même sans usine.

**Référence historique du 5 novembre : les délais ci-dessous précèdent la décision du 9 novembre et ne valident pas le candidat courant.**

Les quatre trajectoires financées de progression avaient été recalculées avec les prix réellement appliqués, y compris l’hydrogène du T3. Elles n’utilisent pas de marché, de raid ou d’aide extérieure. Une politique déterministe alterne les mines métal/nano/hydrogène jusqu’à 80, ajoute l’énergie et les stockages nécessaires, et débloque les centrales T3 à l’étape 20 dans la stratégie avancée.

| Stratégie | Atteindre les trois mines niveau 80 en normal | Alpha ×40 |
|---|---:|---:|
| Centrales T1 seulement | 37.56 jours | 22.55 h |
| Centrales T2/T3 développées | 17.29 jours | 10.38 h |

Les **1 256 achats** de ces quatre trajectoires sont rejoués en pas horaires : coûts et durées recalculés, fonds présents, prérequis et capacités vérifiés, niveaux successifs et résultats finaux identiques à la tolérance numérique. La trajectoire avancée passe d’environ 25,1 jours dans l’ancienne simulation à 17,3 jours dans ce scénario : la répartition des ressources change le rythme de financement même lorsque leur valeur pondérée est conservée.

Ces délais supposent des actions lancées immédiatement, une planète en position 5 et une politique non optimale avec une action à la fois. Ils ne constituent pas une promesse pour un joueur réel, ni un délai de remboursement de la dernière amélioration. Les anciens délais d’accès des rapports précédents sont historiques. Les effets du pillage, des absences, de plusieurs planètes et de la concurrence entre usages industriels et carburant restent à mesurer en Alpha.

## Compatibilité et vérifications

Les nouvelles commandes utilisent les nouveaux prix de base. Les coûts déjà payés dans les files restent enregistrés, et leurs remboursements se calculent à partir de ces paiements. Le test d’annulation modifie réellement un profil de prix après paiement, puis vérifie les remboursements par ressource et leur absence de double application. Le test de dépenses concurrentes utilise un stock suffisant pour une seule commande selon le prix actuel.

La formule de classement reste inchangée : un point par tranche complète de 100
ressources brutes de profil, sans pondération M/N/A/H. La redistribution peut donc
changer les points des possessions existantes lors des nouveaux calculs, malgré un
budget économique pondéré conservé. Un chasseur B seul passe par exemple de 21 à
22 points ; les protections qui utilisent ces points peuvent aussi évoluer. Les
classements quotidiens déjà calculés restent figés jusqu’à leur actualisation normale,
sans réécriture de données par ce lot.

Les surcharges de catalogue propres aux univers restent prioritaires champ par champ. Une surcharge partielle peut conserver certains anciens montants tout en héritant d’un nouveau champ d’hydrogène : ces profils personnalisés ne sont pas inclus dans la garantie de conservation des budgets par défaut. Aucune surcharge ni donnée existante n’est effacée automatiquement. Aucune migration de base de jeu effectuée pour cette redistribution.

Les productions, consommations par seconde, statistiques de combat, contres, vitesses, réservoirs, soutes, prérequis, durées, identités et assets ne sont pas changés par cette grille. Les 192 journaux de combat R14 restent identiques ; les coûts historiques des études sont conservés dans leurs fichiers de référence, tandis que les assertions de prix courant utilisent le nouveau fichier.

## Contrôles et livraison du lot du 16 novembre

Les résultats ciblés, la suite complète et la CI de publication sont consignés dans
le [suivi de mission](PROGRESSION_CARBURANT_UNITES.md). Les chiffres historiques des
études précédentes ne sont pas une validation automatique de la nouvelle version.
Aucune migration SQL ni reprise de données n’est nécessaire pour changer ces profils.
La mise en ligne du code et les réglages propres aux univers restent distincts.
