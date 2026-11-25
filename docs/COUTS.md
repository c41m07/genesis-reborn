# Grille de coûts commune — métal, nano, antimatière et hydrogène

La répartition de valeur **B48/52, I34/36/30, S28/31/31/10**
(métal / nano / antimatière / hydrogène) s’applique aux bâtiments, recherches,
vaisseaux et défenses, y compris les deux familles civiles. Les 156 profils fixent les compositions. Pour un bâtiment ou une recherche avancée, le budget du premier niveau dépend désormais des prérequis. [Calcul des devis](REGLES.md#progression-économique-par-rang). Les 52 profils Basiques sont reconduits à l’identique.

Les [études de progression](EQUILIBRAGE.md) donnent des ordres de grandeur
pour des stratégies et des grilles datées.

Les nouvelles commandes emploient le profil courant. Les commandes déjà payées
conservent coûts, durée, résultat et remboursement calculé sur leur paiement.
Les surcharges d’univers restent prioritaires ; aucune personnalisation n’est effacée.
La [taxe électrique](DUREES_CARBURANT.md) conserve sa formule et les bâtiments restent exclus.
L’état d’intégration et de mise en ligne est séparé dans le [suivi](PROGRESSION_CARBURANT_UNITES.md).

## Règle appliquée par défaut

| Rang | Métal | Nano | Antimatière | Hydrogène |
|---|---:|---:|---:|---:|
| Basique | 48 % | 52 % | 0 % | 0 % |
| Intermédiaire | 34 % | 36 % | 30 % | 0 % |
| Supérieur | 28 % | 31 % | 31 % | 10 % |

Il s’agit de parts de **valeur économique** : métal ×2, nano ×4, antimatière ×8, hydrogène ×1. Ce ne sont pas des pourcentages des quantités brutes. Chaque quantité est entière ; les arrondis sont choisis conjointement pour approcher la répartition et conserver **exactement la valeur réelle du profil de base de chaque rang**, avant croissance des niveaux et recherches.

La grille couvre **52 fiches et 156 profils** : 13 bâtiments, 19 recherches, 16 vaisseaux (dont les deux unités économiques) et 4 défenses. Les profils réels sont lus et conservés en JSON malgré leur extension `.yaml`. Les budgets déclaratifs ne remplacent pas leurs coûts arrondis effectifs.

La décision du 9 novembre 2026 révise volontairement les budgets du réacteur et de la centrale : 1 200 / 1 500 / 1 875 et 4 800 / 6 000 / 7 500. La conservation des budgets de la redistribution du 5 novembre reste une référence historique ; ces montants décrivent les profils avant le calcul des prix d’entrée par prérequis. Voir [EQUILIBRAGE](EQUILIBRAGE.md#production-des-batiments).

## Coûts de base appliqués

Chaque cellule indique **métal / nano / antimatière / hydrogène** pour le niveau 1 ou une unité, sans réduction de recherche.

| Famille | Élément | T1 | T2 | T3 |
|---|---|---|---|---|
| buildings | Bunker de défense | 2 880 / 1 560 / 0 / 0 | 2 549 / 1 350 / 563 / 0 | 2 625 / 1 453 / 727 / 1 876 |
| buildings | Laboratoire colonial | 2 400 / 1 300 / 0 / 0 | 2 127 / 1 126 / 469 / 0 | 2 187 / 1 211 / 606 / 1 562 |
| buildings | Piège à antimatière | 288 / 156 / 0 / 0 | 256 / 135 / 56 / 0 | 262 / 145 / 73 / 187 |
| buildings | Raffinerie d’hydrogène | 384 / 208 / 0 / 0 | 340 / 180 / 75 / 0 | 350 / 194 / 97 / 250 |
| buildings | Mine à ciel ouvert | 384 / 208 / 0 / 0 | 340 / 180 / 75 / 0 | 350 / 194 / 97 / 248 |
| buildings | Atelier de nanofabrication | 384 / 208 / 0 / 0 | 340 / 180 / 75 / 0 | 350 / 194 / 97 / 250 |
| buildings | Centrale nucléaire | 1 152 / 624 / 0 / 0 | 1 020 / 540 / 225 / 0 | 1 049 / 581 / 291 / 750 |
| buildings | Chantier d’assemblage | 4 800 / 2 600 / 0 / 0 | 4 251 / 2 250 / 938 / 0 | 4 375 / 2 422 / 1 211 / 3 126 |
| buildings | Cage magnétique | 1 201 / 650 / 0 / 0 | 1 064 / 563 / 235 / 0 | 1 095 / 606 / 303 / 782 |
| buildings | Batterie de colonie | 1 201 / 650 / 0 / 0 | 1 064 / 563 / 235 / 0 | 1 095 / 606 / 303 / 782 |
| buildings | Réservoir cryogénique | 1 201 / 650 / 0 / 0 | 1 064 / 563 / 235 / 0 | 1 095 / 606 / 303 / 782 |
| buildings | Dépôt de minerais | 1 201 / 650 / 0 / 0 | 1 064 / 563 / 235 / 0 | 1 095 / 606 / 303 / 782 |
| buildings | Coffre de nanomatériaux | 1 201 / 650 / 0 / 0 | 1 064 / 563 / 235 / 0 | 1 095 / 606 / 303 / 782 |
| technologies | Drones réparateurs | 960 / 520 / 0 / 0 | 850 / 450 / 188 / 0 | 877 / 485 / 242 / 626 |
| technologies | Protocoles de colonisation | 5 280 / 2 860 / 0 / 0 | 4 676 / 2 476 / 1 031 / 0 | 4 814 / 2 665 / 1 332 / 3 438 |
| technologies | Réservoirs cryogéniques | 2 400 / 1 300 / 0 / 0 | 2 126 / 1 126 / 469 / 0 | 2 188 / 1 211 / 606 / 1 562 |
| technologies | Génie civil colonial | 960 / 520 / 0 / 0 | 850 / 450 / 188 / 0 | 877 / 485 / 242 / 626 |
| technologies | Manœuvres d’évitement | 1 440 / 780 / 0 / 0 | 1 276 / 676 / 281 / 0 | 1 313 / 727 / 364 / 936 |
| technologies | Discipline du secret | 1 440 / 780 / 0 / 0 | 1 276 / 676 / 281 / 0 | 1 313 / 727 / 364 / 936 |
| technologies | Soute compartimentée | 2 400 / 1 300 / 0 / 0 | 2 126 / 1 126 / 469 / 0 | 2 188 / 1 211 / 606 / 1 562 |
| technologies | Métallurgie des nanomatériaux | 960 / 520 / 0 / 0 | 850 / 450 / 188 / 0 | 877 / 485 / 242 / 626 |
| technologies | Architecture navale | 960 / 520 / 0 / 0 | 850 / 450 / 188 / 0 | 877 / 485 / 242 / 626 |
| technologies | Séquenceur de salves | 5 760 / 3 120 / 0 / 0 | 5 100 / 2 700 / 1 125 / 0 | 5 251 / 2 907 / 1 453 / 3 750 |
| technologies | Générateurs de bouclier accordés | 3 840 / 2 080 / 0 / 0 | 3 400 / 1 800 / 750 / 0 | 3 500 / 1 937 / 969 / 2 500 |
| technologies | Écran de brouillage | 960 / 520 / 0 / 0 | 850 / 450 / 188 / 0 | 877 / 485 / 242 / 626 |
| technologies | Revêtement absorbant | 1 440 / 780 / 0 / 0 | 1 276 / 676 / 281 / 0 | 1 313 / 727 / 364 / 936 |
| technologies | Blindage multicouche | 3 840 / 2 080 / 0 / 0 | 3 400 / 1 800 / 750 / 0 | 3 500 / 1 937 / 969 / 2 500 |
| technologies | Analyse tactique | 1 201 / 650 / 0 / 0 | 1 063 / 563 / 235 / 0 | 1 095 / 606 / 303 / 784 |
| technologies | Télémétrie de poursuite | 960 / 520 / 0 / 0 | 850 / 450 / 188 / 0 | 877 / 485 / 242 / 626 |
| technologies | Propulseurs vectoriels | 2 400 / 1 300 / 0 / 0 | 2 126 / 1 126 / 469 / 0 | 2 188 / 1 211 / 606 / 1 562 |
| technologies | Viseurs balistiques | 5 760 / 3 120 / 0 / 0 | 5 100 / 2 700 / 1 125 / 0 | 5 251 / 2 907 / 1 453 / 3 750 |
| technologies | Canons à énergie focalisée | 5 760 / 3 120 / 0 / 0 | 5 100 / 2 700 / 1 125 / 0 | 5 251 / 2 907 / 1 453 / 3 750 |
| ships | Cuirassé de ligne | 52 921 / 28 665 / 0 / 0 | 46 857 / 24 807 / 10 336 / 0 | 48 236 / 26 702 / 13 351 / 34 456 |
| ships | Bombardier tactique | 5 161 / 2 795 / 0 / 0 | 4 571 / 2 420 / 1 008 / 0 | 4 705 / 2 605 / 1 302 / 3 362 |
| ships | Vaisseau capital | 90 612 / 49 082 / 0 / 0 | 80 230 / 42 475 / 17 698 / 0 | 82 591 / 45 720 / 22 860 / 58 992 |
| ships | Vaisseau colonisateur | 9 600 / 5 200 / 0 / 0 | 8 500 / 4 501 / 1 875 / 0 | 8 750 / 4 844 / 2 422 / 6 250 |
| ships | Corvette de patrouille | 6 720 / 3 640 / 0 / 0 | 5 949 / 3 150 / 1 313 / 0 | 6 125 / 3 391 / 1 696 / 4 374 |
| ships | Croiseur de combat | 29 215 / 15 825 / 0 / 0 | 25 870 / 13 696 / 5 707 / 0 | 26 629 / 14 741 / 7 371 / 19 020 |
| ships | Destroyer d’escorte | 20 213 / 10 949 / 0 / 0 | 17 898 / 9 476 / 3 948 / 0 | 18 424 / 10 199 / 5 100 / 13 158 |
| ships | Chasseur de patrouille | 1 440 / 780 / 0 / 0 | 1 276 / 676 / 281 / 0 | 1 314 / 727 / 363 / 940 |
| ships | Frégate d’escorte | 10 934 / 5 923 / 0 / 0 | 9 684 / 5 126 / 2 136 / 0 | 9 967 / 5 518 / 2 759 / 7 120 |
| ships | Chasseur lourd | 3 552 / 1 924 / 0 / 0 | 3 145 / 1 666 / 694 / 0 | 3 239 / 1 793 / 897 / 2 314 |
| ships | Récolteur atmosphérique | 384 / 208 / 0 / 0 | 340 / 180 / 75 / 0 | 350 / 194 / 97 / 248 |
| ships | Intercepteur atmosphérique | 1 764 / 956 / 0 / 0 | 1 563 / 828 / 345 / 0 | 1 610 / 891 / 446 / 1 150 |
| ships | Sonde de reconnaissance | 240 / 130 / 0 / 0 | 213 / 113 / 47 / 0 | 220 / 122 / 61 / 156 |
| ships | Satellite héliocapteur | 384 / 208 / 0 / 0 | 340 / 180 / 75 / 0 | 350 / 194 / 97 / 248 |
| ships | Chasseur furtif | 5 856 / 3 172 / 0 / 0 | 5 185 / 2 746 / 1 144 / 0 | 5 337 / 2 955 / 1 478 / 3 812 |
| ships | Cargo de ligne | 5 760 / 3 120 / 0 / 0 | 5 100 / 2 701 / 1 125 / 0 | 5 252 / 2 907 / 1 453 / 3 752 |
| defenses | Canon laser anti-capital | 30 720 / 16 640 / 0 / 0 | 21 760 / 11 520 / 4 800 / 0 | 17 920 / 9 920 / 4 960 / 12 800 |
| defenses | Canon laser anti-escorteur | 4 320 / 2 340 / 0 / 0 | 3 060 / 1 621 / 675 / 0 | 2 520 / 1 395 / 698 / 1 800 |
| defenses | Canon laser anti-lourd | 11 280 / 6 110 / 0 / 0 | 7 989 / 4 230 / 1 763 / 0 | 6 580 / 3 642 / 1 821 / 4 702 |
| defenses | Canon laser anti-léger | 1 200 / 650 / 0 / 0 | 851 / 450 / 187 / 0 | 700 / 388 / 194 / 498 |

Exemple : le Chasseur T3 coûte **1 314 M + 727 N + 363 A + 940 H**, pour une valeur toujours égale à **9 380**.

L’hydrogène payé à la construction ou à la recherche est une dépense industrielle consommée lors de la commande. Il ne remplit pas le réservoir d’un vaisseau et ne remplace pas le carburant réservé pour une mission. La conversion d’antimatière consomme toujours son hydrogène séparément.

## Prix réels après croissance et recherches

Les courbes de coût et de durée, les réductions technologiques et les règles d’arrondi restent en vigueur. Chaque ressource est arrondie séparément après application de la croissance et des réductions : conserver exactement la valeur d’un profil de base ne garantit pas une égalité parfaite de tous les prix arrondis aux niveaux suivants.

La référence `app/tests/Fixtures/resource-costs-2026-11-19.json` fige les
profils et la base de recherche de `dev` avant le lot. L’écart maximal aux
nouvelles parts T2/T3 est de 0,147 point de pourcentage. Le budget de chacun
des 156 profils de base est conservé exactement. Sur les 3 636 devis comparés
(frontières des courbes, lots de 1/10/100, avec ou sans réductions), l’écart
pondéré maximal est de 12 pour les bâtiments, 9 pour les recherches, 9 pour
les vaisseaux et 0 pour les défenses. La taxe E est exclue de cette mesure.
Elle suit la [durée effective](DUREES_CARBURANT.md#occupation-de-la-file-commune-par-la-colonisation),
dont la seule nouvelle base est celle de l’Expansion coloniale.

La redistribution porte sur les `cost_profiles`. L’entretien électrique direct
du piège à antimatière conserve le réglage du 18 novembre. Les études datées
gardent leurs prix et références d’origine.

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
budget économique pondéré conservé. Avec cette grille, un chasseur T3 seul passe par exemple de 32 à 33 points.
Les protections qui utilisent ces points peuvent aussi évoluer. Les
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
