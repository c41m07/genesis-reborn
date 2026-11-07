# Grille de coûts commune — métal, nano, antimatière et hydrogène

Décision utilisateur du 5 novembre 2026 : appliquer la progression des ressources à l’ensemble des bâtiments, du chantier spatial (vaisseaux), des défenses et des recherches. Le chantier d’assemblage lui-même fait partie des bâtiments concernés. Les boosts et les réglages d’univers ne font pas partie de cette modification.

## Règle appliquée par défaut

| Rang | Métal | Nano | Antimatière | Hydrogène |
|---|---:|---:|---:|---:|
| T1 | 60 % | 40 % | 0 % | 0 % |
| T2 | 45 % | 45 % | 10 % | 0 % |
| T3 | 30 % | 45 % | 20 % | 5 % |

Il s’agit de parts de **valeur économique** : métal ×2, nano ×4, antimatière ×8, hydrogène ×1. Ce ne sont pas des pourcentages des quantités brutes. Chaque quantité est entière ; les arrondis sont choisis conjointement pour approcher la répartition et conserver **exactement la valeur réelle du profil de base de chaque rang**, avant croissance des niveaux et recherches.

La grille couvre **50 fiches et 150 profils** : 13 bâtiments, 19 recherches, 14 vaisseaux et 4 défenses. Les profils réels sont lus et conservés en JSON malgré leur extension `.yaml`. Les budgets déclaratifs ne remplacent pas leurs coûts arrondis effectifs.

## Coûts de base appliqués

Chaque cellule indique **métal / nano / antimatière / hydrogène** pour le niveau 1 ou une unité, sans réduction de recherche.

| Famille | Élément | T1 | T2 | T3 |
|---|---|---|---|---|
| buildings | Bunker de défense | 3 600 / 1 200 / 0 / 0 | 3 375 / 1 687 / 188 / 0 | 2 813 / 2 110 / 469 / 936 |
| buildings | Laboratoire colonial | 3 000 / 1 000 / 0 / 0 | 2 815 / 1 408 / 156 / 0 | 2 344 / 1 758 / 391 / 780 |
| buildings | Piège à antimatière | 480 / 160 / 0 / 0 | 450 / 225 / 25 / 0 | 376 / 282 / 63 / 124 |
| buildings | Raffinerie d’hydrogène | 480 / 160 / 0 / 0 | 450 / 225 / 25 / 0 | 375 / 281 / 63 / 124 |
| buildings | Mine à ciel ouvert | 480 / 160 / 0 / 0 | 450 / 225 / 25 / 0 | 374 / 281 / 63 / 124 |
| buildings | Atelier de nanofabrication | 480 / 160 / 0 / 0 | 450 / 225 / 25 / 0 | 375 / 281 / 63 / 124 |
| buildings | Centrale nucléaire | 3 600 / 1 200 / 0 / 0 | 3 376 / 1 688 / 187 / 0 | 2 813 / 2 109 / 469 / 938 |
| buildings | Chantier d’assemblage | 6 000 / 2 000 / 0 / 0 | 5 625 / 2 813 / 313 / 0 | 4 688 / 3 516 / 781 / 1 564 |
| buildings | Cage magnétique | 1 501 / 500 / 0 / 0 | 1 410 / 704 / 78 / 0 | 1 174 / 880 / 195 / 392 |
| buildings | Batterie de colonie | 1 501 / 500 / 0 / 0 | 1 410 / 704 / 78 / 0 | 1 174 / 880 / 195 / 392 |
| buildings | Réservoir cryogénique | 1 501 / 500 / 0 / 0 | 1 410 / 704 / 78 / 0 | 1 174 / 880 / 195 / 392 |
| buildings | Dépôt de minerais | 1 501 / 500 / 0 / 0 | 1 410 / 704 / 78 / 0 | 1 174 / 880 / 195 / 392 |
| buildings | Coffre de nanomatériaux | 1 501 / 500 / 0 / 0 | 1 410 / 704 / 78 / 0 | 1 174 / 880 / 195 / 392 |
| technologies | Drones réparateurs | 1 200 / 400 / 0 / 0 | 1 126 / 562 / 63 / 0 | 939 / 704 / 156 / 314 |
| technologies | Protocoles de colonisation | 6 600 / 2 200 / 0 / 0 | 6 188 / 3 094 / 344 / 0 | 5 156 / 3 868 / 860 / 1 718 |
| technologies | Réservoirs cryogéniques | 3 000 / 1 000 / 0 / 0 | 2 816 / 1 407 / 156 / 0 | 2 344 / 1 758 / 391 / 782 |
| technologies | Génie civil colonial | 1 200 / 400 / 0 / 0 | 1 126 / 562 / 63 / 0 | 939 / 704 / 156 / 314 |
| technologies | Manœuvres d’évitement | 1 800 / 600 / 0 / 0 | 1 688 / 844 / 94 / 0 | 1 407 / 1 055 / 235 / 468 |
| technologies | Discipline du secret | 1 800 / 600 / 0 / 0 | 1 688 / 844 / 94 / 0 | 1 407 / 1 055 / 235 / 468 |
| technologies | Soute compartimentée | 3 000 / 1 000 / 0 / 0 | 2 816 / 1 407 / 156 / 0 | 2 344 / 1 758 / 391 / 782 |
| technologies | Métallurgie des nanomatériaux | 1 200 / 400 / 0 / 0 | 1 126 / 562 / 63 / 0 | 939 / 704 / 156 / 314 |
| technologies | Architecture navale | 1 200 / 400 / 0 / 0 | 1 126 / 562 / 63 / 0 | 939 / 704 / 156 / 314 |
| technologies | Séquenceur de salves | 7 200 / 2 400 / 0 / 0 | 6 750 / 3 375 / 375 / 0 | 5 625 / 4 219 / 938 / 1 874 |
| technologies | Générateurs de bouclier accordés | 4 800 / 1 600 / 0 / 0 | 4 500 / 2 250 / 250 / 0 | 3 751 / 2 812 / 625 / 1 250 |
| technologies | Écran de brouillage | 1 200 / 400 / 0 / 0 | 1 126 / 562 / 63 / 0 | 939 / 704 / 156 / 314 |
| technologies | Revêtement absorbant | 1 800 / 600 / 0 / 0 | 1 688 / 844 / 94 / 0 | 1 407 / 1 055 / 235 / 468 |
| technologies | Blindage multicouche | 4 800 / 1 600 / 0 / 0 | 4 500 / 2 250 / 250 / 0 | 3 751 / 2 812 / 625 / 1 250 |
| technologies | Analyse tactique | 1 501 / 500 / 0 / 0 | 1 409 / 704 / 78 / 0 | 1 172 / 880 / 196 / 390 |
| technologies | Télémétrie de poursuite | 1 200 / 400 / 0 / 0 | 1 126 / 562 / 63 / 0 | 939 / 704 / 156 / 314 |
| technologies | Propulseurs vectoriels | 3 000 / 1 000 / 0 / 0 | 2 816 / 1 407 / 156 / 0 | 2 344 / 1 758 / 391 / 782 |
| technologies | Viseurs balistiques | 7 200 / 2 400 / 0 / 0 | 6 750 / 3 375 / 375 / 0 | 5 625 / 4 219 / 938 / 1 874 |
| technologies | Canons à énergie focalisée | 7 200 / 2 400 / 0 / 0 | 6 750 / 3 375 / 375 / 0 | 5 625 / 4 219 / 938 / 1 874 |
| ships | Cuirassé de ligne | 66 151 / 22 050 / 0 / 0 | 62 017 / 31 009 / 3 445 / 0 | 51 681 / 38 761 / 8 614 / 17 226 |
| ships | Bombardier tactique | 6 451 / 2 150 / 0 / 0 | 6 049 / 3 025 / 336 / 0 | 5 042 / 3 781 / 840 / 1 680 |
| ships | Vaisseau capital | 113 266 / 37 755 / 0 / 0 | 106 188 / 53 094 / 5 899 / 0 | 88 490 / 66 368 / 14 748 / 29 498 |
| ships | Vaisseau colonisateur | 12 000 / 4 000 / 0 / 0 | 11 252 / 5 625 / 625 / 0 | 9 375 / 7 031 / 1 563 / 3 124 |
| ships | Corvette de patrouille | 8 400 / 2 800 / 0 / 0 | 7 875 / 3 937 / 438 / 0 | 6 564 / 4 922 / 1 094 / 2 188 |
| ships | Croiseur de combat | 36 519 / 12 173 / 0 / 0 | 34 242 / 17 120 / 1 902 / 0 | 28 532 / 21 399 / 4 755 / 9 510 |
| ships | Destroyer d’escorte | 25 267 / 8 422 / 0 / 0 | 23 690 / 11 844 / 1 316 / 0 | 19 741 / 14 805 / 3 290 / 6 580 |
| ships | Chasseur de patrouille | 1 800 / 600 / 0 / 0 | 1 688 / 844 / 94 / 0 | 1 406 / 1 055 / 235 / 468 |
| ships | Frégate d’escorte | 13 668 / 4 556 / 0 / 0 | 12 816 / 6 408 / 712 / 0 | 10 679 / 8 010 / 1 780 / 3 560 |
| ships | Chasseur lourd | 4 440 / 1 480 / 0 / 0 | 4 165 / 2 082 / 231 / 0 | 3 470 / 2 603 / 579 / 1 156 |
| ships | Intercepteur atmosphérique | 2 206 / 735 / 0 / 0 | 2 069 / 1 035 / 115 / 0 | 1 724 / 1 294 / 288 / 574 |
| ships | Sonde de reconnaissance | 300 / 100 / 0 / 0 | 281 / 141 / 16 / 0 | 236 / 177 / 39 / 80 |
| ships | Chasseur furtif | 7 320 / 2 440 / 0 / 0 | 6 865 / 3 432 / 381 / 0 | 5 720 / 4 290 / 953 / 1 906 |
| ships | Cargo de ligne | 7 200 / 2 400 / 0 / 0 | 6 752 / 3 375 / 375 / 0 | 5 626 / 4 219 / 938 / 1 876 |
| defenses | Canon laser anti-capital | 38 400 / 12 800 / 0 / 0 | 28 800 / 14 400 / 1 600 / 0 | 19 200 / 14 400 / 3 200 / 6 400 |
| defenses | Canon laser anti-escorteur | 5 400 / 1 800 / 0 / 0 | 4 052 / 2 025 / 225 / 0 | 2 700 / 2 026 / 450 / 900 |
| defenses | Canon laser anti-lourd | 14 100 / 4 700 / 0 / 0 | 10 575 / 5 287 / 588 / 0 | 7 050 / 5 287 / 1 175 / 2 350 |
| defenses | Canon laser anti-léger | 1 500 / 500 / 0 / 0 | 1 125 / 563 / 62 / 0 | 750 / 563 / 125 / 250 |

Exemple : le Chasseur T3 coûte désormais **1 406 M + 1 055 N + 235 A + 468 H**, pour une valeur toujours égale à **9 380**.

L’hydrogène payé à la construction ou à la recherche est une dépense industrielle consommée lors de la commande. Il ne remplit pas le réservoir d’un vaisseau et ne remplace pas le carburant réservé pour une mission. La conversion d’antimatière consomme toujours son hydrogène séparément.

## Prix réels après croissance et recherches

Les courbes de coûts, durées, réductions technologiques et règles d’arrondi restent inchangées. Chaque ressource est arrondie séparément après application de la croissance et des réductions : conserver exactement la valeur d’un profil de base ne garantit pas une égalité parfaite de tous les prix arrondis aux niveaux suivants.

Le fichier de référence `tests/Fixtures/catalog-costs-2026-11-05.json` fige **2 267 devis** calculés avant modification en comparant le catalogue courant à la nouvelle grille en mémoire : débuts et frontières de courbes, niveaux élevés autorisés, profils sans recherche/intermédiaire/maximal pour les familles concernées, et lots de 1, 10 ou 100 unités. Les valeurs de base restent exactement conservées. Les écarts maximaux observés après croissance et/ou recherche sont : bâtiments 20 unités économiques, recherches 6, vaisseaux 5, défenses 0, par commande testée. Ils ne sont pas multipliés arbitrairement par le nombre d’unités du lot.

Les durées de tous les devis sont identiques. Les caractéristiques et tous les champs hors `cost_profiles` sont comparés à leur état antérieur. Les anciens champs de prix hérités restent inchangés : le moteur utilise les `cost_profiles` lorsqu’ils existent.

## Démarrage et déblocages

Les stocks de départ actuels permettent de payer les premiers extracteurs T1 de métal, nano, hydrogène et antimatière sans rareté préalable : ces quatre premiers achats ne demandent ni antimatière ni hydrogène. Une nouvelle colonie ne peut pas nécessairement acheter les quatre extracteurs et la centrale immédiatement ; la centrale devient finançable avec la production. Les tests reproduisent les achats, les temps de construction et la production aux positions 1, 5 et 9 avec les deux kits de départ réels. La production gratuite de métal et nano reste positive, même sans usine.

Les quatre trajectoires financées de progression sont également recalculées avec les prix réellement appliqués, y compris l’hydrogène du T3. Elles n’utilisent pas de marché, de raid ou d’aide extérieure. Une politique déterministe alterne les mines métal/nano/hydrogène jusqu’à 80, ajoute l’énergie et les stockages nécessaires, et débloque les centrales T3 à l’étape 20 dans la stratégie avancée.

| Stratégie | Atteindre les trois mines niveau 80 en normal | Alpha ×40 |
|---|---:|---:|
| Centrales T1 seulement | 37.56 jours | 22.55 h |
| Centrales T2/T3 développées | 17.29 jours | 10.38 h |

Les **1 256 achats** de ces quatre trajectoires sont rejoués en pas horaires : coûts et durées recalculés, fonds présents, prérequis et capacités vérifiés, niveaux successifs et résultats finaux identiques à la tolérance numérique. La trajectoire avancée passe d’environ 25,1 jours dans l’ancienne simulation à 17,3 jours dans ce scénario : la répartition des ressources change le rythme de financement même lorsque leur valeur pondérée est conservée.

Ces délais supposent des actions lancées immédiatement, une planète en position 5 et une politique non optimale avec une action à la fois. Ils ne constituent pas une promesse pour un joueur réel, ni un délai de remboursement de la dernière amélioration. Les anciens délais d’accès des rapports précédents sont historiques. Les effets du pillage, des absences, de plusieurs planètes et de la concurrence entre usages industriels et carburant restent à mesurer en Alpha.

## Compatibilité et vérifications

Les nouvelles commandes utilisent les nouveaux prix de base. Les coûts déjà payés dans les files restent enregistrés, et leurs remboursements se calculent à partir de ces paiements. Le test d’annulation modifie réellement un profil de prix après paiement, puis vérifie les remboursements par ressource et leur absence de double application. Le test de dépenses concurrentes utilise un stock suffisant pour une seule commande selon le prix actuel.

Les surcharges de catalogue propres aux univers restent prioritaires champ par champ. Une surcharge partielle peut conserver certains anciens montants tout en héritant d’un nouveau champ d’hydrogène : ces profils personnalisés ne sont pas inclus dans la garantie de conservation des budgets par défaut. Aucune surcharge ni donnée existante n’est effacée automatiquement. Aucune migration exécutée.

Les productions, consommations par seconde, statistiques de combat, contres, vitesses, réservoirs, soutes, prérequis, durées, identités et assets ne sont pas changés par cette grille. Les 192 journaux de combat R14 restent identiques ; les coûts historiques des études sont conservés dans leurs fichiers de référence, tandis que les assertions de prix courant utilisent le nouveau fichier.

**24 tests ciblés et 22 635 assertions réussissent.** `make validate`, les vérifications PHP, JSON/YAML, Twig, injection des services et mapping passent. La dernière étape de `make lint` reste en échec sur le schéma désynchronisé ; aucune migration n’est lancée. La suite générale `make test` compte 191 tests, avec 12 échecs et 3 erreurs restants déjà présents avant cette grille. Le contrôle des espaces est limité aux fichiers de ce changement afin de préserver les modifications d’interface présentes en parallèle.

## Fichiers modifiés

- Les 50 fiches dans `config/game/buildings/`, `config/game/technologies/`, `config/game/ships/`, `config/game/defenses/` : seul `cost_profiles` est modifié.
- `tests/Fixtures/catalog-costs-2026-11-05.json`, `tests/Service/CatalogCostCompositionTest.php`.
- Adaptation des références historiques : `tests/Service/ShipCostCompositionTest.php`, `tests/Service/R14RegressionTest.php`, `tests/Service/BuildingBalanceTest.php`, `tests/Service/BalanceMechanicsTest.php`.
- Tests d’intégration liés au paiement : `tests/Integration/GamePersistenceTest.php`.
- `docs/DECISIONS.md`, `docs/REGLES.md`, ce document et des précisions de provenance dans les bilans précédents des vaisseaux et des bâtiments.
