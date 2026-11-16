# Grille de coûts commune — métal, nano, antimatière et hydrogène

Décision utilisateur du 16 novembre 2026 : appliquer la répartition de valeur
**B40/60, I30/50/20, S25/35/30/10** (métal / nano / antimatière / hydrogène)
aux bâtiments, recherches, vaisseaux et défenses. Les deux familles civiles sont incluses.
Les budgets réels de base de chacun des 156 profils sont conservés exactement.

Les [simulations avant décision](SIMULATIONS_GRILLE_PROPOSEE.md) exposent les compromis
acceptés : attente de nano, besoin d’antimatière plus élevé et formations avancées
plus lentes dans les parcours étudiés, avec des gains sur certaines reconstructions.
Ces stratégies ne garantissent pas les délais de tous les joueurs.

Les nouvelles commandes emploient le profil courant. Les commandes déjà payées
conservent coûts, durée, résultat et remboursement calculé sur leur paiement.
Les surcharges d’univers restent prioritaires ; aucune personnalisation n’est effacée.
La [taxe électrique](DUREES_CARBURANT.md) conserve sa formule et les bâtiments restent exclus.
L’état d’intégration et de mise en ligne est séparé dans le [suivi](PROGRESSION_CARBURANT_UNITES.md).

## Règle appliquée par défaut

| Rang | Métal | Nano | Antimatière | Hydrogène |
|---|---:|---:|---:|---:|
| Basique | 40 % | 60 % | 0 % | 0 % |
| Intermédiaire | 30 % | 50 % | 20 % | 0 % |
| Supérieur | 25 % | 35 % | 30 % | 10 % |

Il s’agit de parts de **valeur économique** : métal ×2, nano ×4, antimatière ×8, hydrogène ×1. Ce ne sont pas des pourcentages des quantités brutes. Chaque quantité est entière ; les arrondis sont choisis conjointement pour approcher la répartition et conserver **exactement la valeur réelle du profil de base de chaque rang**, avant croissance des niveaux et recherches.

La grille couvre **52 fiches et 156 profils** : 13 bâtiments, 19 recherches, 16 vaisseaux (dont les deux unités économiques) et 4 défenses. Les profils réels sont lus et conservés en JSON malgré leur extension `.yaml`. Les budgets déclaratifs ne remplacent pas leurs coûts arrondis effectifs.

La décision du 9 novembre 2026 révise volontairement les budgets du réacteur et de la centrale : 1 200 / 1 500 / 1 875 et 4 800 / 6 000 / 7 500. La conservation des budgets de la redistribution du 5 novembre reste une référence historique ; les nouveaux budgets et leurs parts de ressources font désormais foi pour ces deux familles. Voir [EQUILIBRAGE](EQUILIBRAGE.md#production-des-batiments).

## Coûts de base appliqués

Chaque cellule indique **métal / nano / antimatière / hydrogène** pour le niveau 1 ou une unité, sans réduction de recherche.

| Famille | Élément | T1 | T2 | T3 |
|---|---|---|---|---|
| buildings | Bunker de défense | 2 400 / 1 800 / 0 / 0 | 2 251 / 1 875 / 375 / 0 | 2 345 / 1 641 / 703 / 1 876 |
| buildings | Laboratoire colonial | 2 000 / 1 500 / 0 / 0 | 1 877 / 1 563 / 313 / 0 | 1 954 / 1 367 / 586 / 1 564 |
| buildings | Piège à antimatière | 240 / 180 / 0 / 0 | 226 / 188 / 37 / 0 | 235 / 164 / 70 / 189 |
| buildings | Raffinerie d’hydrogène | 320 / 240 / 0 / 0 | 300 / 250 / 50 / 0 | 312 / 219 / 94 / 250 |
| buildings | Mine à ciel ouvert | 320 / 240 / 0 / 0 | 300 / 250 / 50 / 0 | 312 / 219 / 94 / 248 |
| buildings | Atelier de nanofabrication | 320 / 240 / 0 / 0 | 300 / 250 / 50 / 0 | 312 / 219 / 94 / 250 |
| buildings | Centrale nucléaire | 960 / 720 / 0 / 0 | 900 / 750 / 150 / 0 | 938 / 656 / 281 / 752 |
| buildings | Chantier d’assemblage | 4 000 / 3 000 / 0 / 0 | 3 751 / 3 126 / 625 / 0 | 3 906 / 2 735 / 1 172 / 3 124 |
| buildings | Cage magnétique | 1 001 / 750 / 0 / 0 | 940 / 783 / 156 / 0 | 978 / 684 / 293 / 784 |
| buildings | Batterie de colonie | 1 001 / 750 / 0 / 0 | 940 / 783 / 156 / 0 | 978 / 684 / 293 / 784 |
| buildings | Réservoir cryogénique | 1 001 / 750 / 0 / 0 | 940 / 783 / 156 / 0 | 978 / 684 / 293 / 784 |
| buildings | Dépôt de minerais | 1 001 / 750 / 0 / 0 | 940 / 783 / 156 / 0 | 978 / 684 / 293 / 784 |
| buildings | Coffre de nanomatériaux | 1 001 / 750 / 0 / 0 | 940 / 783 / 156 / 0 | 978 / 684 / 293 / 784 |
| technologies | Drones réparateurs | 800 / 600 / 0 / 0 | 750 / 626 / 125 / 0 | 782 / 547 / 235 / 624 |
| technologies | Protocoles de colonisation | 4 400 / 3 300 / 0 / 0 | 4 124 / 3 438 / 688 / 0 | 4 298 / 3 009 / 1 289 / 3 438 |
| technologies | Réservoirs cryogéniques | 2 000 / 1 500 / 0 / 0 | 1 876 / 1 563 / 313 / 0 | 1 954 / 1 368 / 586 / 1 562 |
| technologies | Génie civil colonial | 800 / 600 / 0 / 0 | 750 / 626 / 125 / 0 | 782 / 547 / 235 / 624 |
| technologies | Manœuvres d’évitement | 1 200 / 900 / 0 / 0 | 1 124 / 938 / 188 / 0 | 1 172 / 821 / 352 / 938 |
| technologies | Discipline du secret | 1 200 / 900 / 0 / 0 | 1 124 / 938 / 188 / 0 | 1 172 / 821 / 352 / 938 |
| technologies | Soute compartimentée | 2 000 / 1 500 / 0 / 0 | 1 876 / 1 563 / 313 / 0 | 1 954 / 1 368 / 586 / 1 562 |
| technologies | Métallurgie des nanomatériaux | 800 / 600 / 0 / 0 | 750 / 626 / 125 / 0 | 782 / 547 / 235 / 624 |
| technologies | Architecture navale | 800 / 600 / 0 / 0 | 750 / 626 / 125 / 0 | 782 / 547 / 235 / 624 |
| technologies | Séquenceur de salves | 4 800 / 3 600 / 0 / 0 | 4 500 / 3 750 / 750 / 0 | 4 688 / 3 282 / 1 406 / 3 752 |
| technologies | Générateurs de bouclier accordés | 3 200 / 2 400 / 0 / 0 | 3 000 / 2 500 / 500 / 0 | 3 125 / 2 188 / 937 / 2 502 |
| technologies | Écran de brouillage | 800 / 600 / 0 / 0 | 750 / 626 / 125 / 0 | 782 / 547 / 235 / 624 |
| technologies | Revêtement absorbant | 1 200 / 900 / 0 / 0 | 1 124 / 938 / 188 / 0 | 1 172 / 821 / 352 / 938 |
| technologies | Blindage multicouche | 3 200 / 2 400 / 0 / 0 | 3 000 / 2 500 / 500 / 0 | 3 125 / 2 188 / 937 / 2 502 |
| technologies | Analyse tactique | 1 001 / 750 / 0 / 0 | 939 / 783 / 156 / 0 | 978 / 685 / 293 / 782 |
| technologies | Télémétrie de poursuite | 800 / 600 / 0 / 0 | 750 / 626 / 125 / 0 | 782 / 547 / 235 / 624 |
| technologies | Propulseurs vectoriels | 2 000 / 1 500 / 0 / 0 | 1 876 / 1 563 / 313 / 0 | 1 954 / 1 368 / 586 / 1 562 |
| technologies | Viseurs balistiques | 4 800 / 3 600 / 0 / 0 | 4 500 / 3 750 / 750 / 0 | 4 688 / 3 282 / 1 406 / 3 752 |
| technologies | Canons à énergie focalisée | 4 800 / 3 600 / 0 / 0 | 4 500 / 3 750 / 750 / 0 | 4 688 / 3 282 / 1 406 / 3 752 |
| ships | Cuirassé de ligne | 44 101 / 33 075 / 0 / 0 | 41 345 / 34 453 / 6 891 / 0 | 43 068 / 30 148 / 12 920 / 34 456 |
| ships | Bombardier tactique | 4 301 / 3 225 / 0 / 0 | 4 033 / 3 361 / 672 / 0 | 4 201 / 2 941 / 1 260 / 3 362 |
| ships | Vaisseau capital | 75 510 / 56 633 / 0 / 0 | 70 790 / 58 993 / 11 799 / 0 | 73 741 / 51 619 / 22 123 / 58 992 |
| ships | Vaisseau colonisateur | 8 000 / 6 000 / 0 / 0 | 7 500 / 6 251 / 1 250 / 0 | 7 812 / 5 469 / 2 344 / 6 250 |
| ships | Corvette de patrouille | 5 600 / 4 200 / 0 / 0 | 5 251 / 4 375 / 875 / 0 | 5 469 / 3 829 / 1 641 / 4 374 |
| ships | Croiseur de combat | 24 347 / 18 259 / 0 / 0 | 22 828 / 19 023 / 3 804 / 0 | 23 776 / 16 643 / 7 133 / 19 022 |
| ships | Destroyer d’escorte | 16 845 / 12 633 / 0 / 0 | 15 792 / 13 161 / 2 632 / 0 | 16 451 / 11 515 / 4 935 / 13 160 |
| ships | Chasseur de patrouille | 1 200 / 900 / 0 / 0 | 1 124 / 938 / 188 / 0 | 1 172 / 821 / 352 / 936 |
| ships | Frégate d’escorte | 9 112 / 6 834 / 0 / 0 | 8 544 / 7 120 / 1 424 / 0 | 8 899 / 6 230 / 2 670 / 7 120 |
| ships | Chasseur lourd | 2 960 / 2 220 / 0 / 0 | 2 775 / 2 313 / 463 / 0 | 2 892 / 2 025 / 868 / 2 312 |
| ships | Récolteur d’hydrogène | 320 / 240 / 0 / 0 | 300 / 250 / 50 / 0 | 312 / 219 / 94 / 248 |
| ships | Intercepteur atmosphérique | 1 470 / 1 103 / 0 / 0 | 1 379 / 1 150 / 230 / 0 | 1 438 / 1 007 / 431 / 1 150 |
| ships | Sonde de reconnaissance | 200 / 150 / 0 / 0 | 189 / 157 / 31 / 0 | 196 / 138 / 59 / 156 |
| ships | Satellite solaire | 320 / 240 / 0 / 0 | 300 / 250 / 50 / 0 | 312 / 219 / 94 / 248 |
| ships | Chasseur furtif | 4 880 / 3 660 / 0 / 0 | 4 575 / 3 813 / 763 / 0 | 4 766 / 3 336 / 1 430 / 3 814 |
| ships | Cargo de ligne | 4 800 / 3 600 / 0 / 0 | 4 500 / 3 751 / 750 / 0 | 4 688 / 3 282 / 1 407 / 3 748 |
| defenses | Canon laser anti-capital | 25 600 / 19 200 / 0 / 0 | 19 200 / 16 000 / 3 200 / 0 | 16 000 / 11 200 / 4 800 / 12 800 |
| defenses | Canon laser anti-escorteur | 3 600 / 2 700 / 0 / 0 | 2 700 / 2 251 / 450 / 0 | 2 251 / 1 575 / 675 / 1 802 |
| defenses | Canon laser anti-lourd | 9 400 / 7 050 / 0 / 0 | 7 051 / 5 875 / 1 175 / 0 | 5 875 / 4 113 / 1 762 / 4 700 |
| defenses | Canon laser anti-léger | 1 000 / 750 / 0 / 0 | 749 / 625 / 125 / 0 | 625 / 437 / 188 / 500 |

Exemple : le Chasseur T3 coûte désormais **1 172 M + 821 N + 352 A + 936 H**, pour une valeur toujours égale à **9 380**.

L’hydrogène payé à la construction ou à la recherche est une dépense industrielle consommée lors de la commande. Il ne remplit pas le réservoir d’un vaisseau et ne remplace pas le carburant réservé pour une mission. La conversion d’antimatière consomme toujours son hydrogène séparément.

## Prix réels après croissance et recherches

Les courbes de coûts, durées, réductions technologiques et règles d’arrondi restent inchangées. Chaque ressource est arrondie séparément après application de la croissance et des réductions : conserver exactement la valeur d’un profil de base ne garantit pas une égalité parfaite de tous les prix arrondis aux niveaux suivants.

La référence `tests/Fixtures/resource-costs-2026-11-16.json` conserve les profils
avant/après et leur provenance. Les tests contrôlent les 156 budgets, les frontières
des courbes, les lots de 1/10/100 et les réductions. Les 3 636 devis échantillonnés gardent les durées et taxes E
identiques à bonus égal. Les écarts maximaux de valeur observés par commande sont
12 pour les bâtiments, 10 pour les recherches, 9 pour les vaisseaux et 0 pour les défenses. Deux arrondis interviennent pour les groupes à remise
(croissance puis réduction) ; l’égalité des budgets de base ne supprime pas les
petits écarts des devis futurs. Aucun nouvel arrondi ou remboursement correctif ajouté.

Tous les champs hors `cost_profiles` restent conservés. Les anciennes études sont
rejouées avec leurs prix par les helpers de test ; leurs fixtures du 5/9 novembre
ne sont pas régénérées avec la nouvelle grille.

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
budget économique pondéré conservé. Un chasseur B seul passe par exemple de 24 à
21 points ; les protections qui utilisent ces points peuvent aussi évoluer. Les
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
