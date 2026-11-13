# Étude du coût électrique des opérations — 13 novembre 2026

Statut : coefficient, remboursement à 25 % et application aux reconstructions automatiques validés explicitement le 13 novembre 2026. Intégration locale ; aucun déploiement annoncé.

## Méthode et hypothèses

Calculs exécutés avec `GameCatalog` et `OperationCalculator` actuels, sans base de données. Univers configurés Omega et Turbo ; trois rangs et quantités 1 et 100. Début : niveau de recherche 1 et laboratoire/chantier Basique 1 ; milieu : 10/10 ; fin : recherche 25 et laboratoire/chantier Basique 80. Les installations des autres rangs sont absentes. Ces paliers servent de comparaison ; ce ne sont pas des historiques de joueurs, ni une garantie que tous les prérequis sont remplis. Les autres réductions et les paramètres personnalisés des univers ne sont pas supposés actifs.

La règle validée est `coût E = ceil(durée effective en secondes / 60)` pour la commande entière. Paiement une fois à l’ajout, durée calculée avec les bonus présents. Une accélération ultérieure ne modifie ni le paiement ni le remboursement. Aucun coût sur les bâtiments. Le service actuel n’applique pas de réduction de temps aux défenses ; cette étude conserve ce comportement.

## Exemples calculés

| Univers | Phase | Opération | Rang | Niveau / quantité | Durée (s) | E proposé |
|---|---|---|---|---:|---:|---:|
| omega-1 | début | engineering_foundations | basic | 1 | 593 | 10 |
| omega-1 | début | probe | basic | 1 | 119 | 2 |
| omega-1 | début | colony_ship | basic | 1 | 7105 | 119 |
| omega-1 | début | battleship | basic | 100 | 17051356 | 284190 |
| omega-1 | début | ground_turret_anti_light | basic | 1 | 300 | 5 |
| omega-1 | début | ground_turret_anti_capital | basic | 100 | 126000 | 2100 |
| omega-1 | milieu | engineering_foundations | basic | 10 | 5344 | 90 |
| omega-1 | milieu | probe | basic | 1 | 106 | 2 |
| omega-1 | milieu | colony_ship | basic | 1 | 6327 | 106 |
| omega-1 | milieu | battleship | basic | 100 | 15182714 | 253046 |
| omega-1 | milieu | ground_turret_anti_light | basic | 1 | 300 | 5 |
| omega-1 | milieu | ground_turret_anti_capital | basic | 100 | 126000 | 2100 |
| omega-1 | fin | engineering_foundations | basic | 25 | 31603 | 527 |
| omega-1 | fin | probe | basic | 1 | 88 | 2 |
| omega-1 | fin | colony_ship | basic | 1 | 5266 | 88 |
| omega-1 | fin | battleship | basic | 100 | 12636694 | 210612 |
| omega-1 | fin | ground_turret_anti_light | basic | 1 | 300 | 5 |
| omega-1 | fin | ground_turret_anti_capital | basic | 100 | 126000 | 2100 |
| omega-1 | fin | engineering_foundations | superior | 25 | 49379 | 823 |
| omega-1 | fin | probe | superior | 1 | 138 | 3 |
| omega-1 | fin | colony_ship | superior | 1 | 8228 | 138 |
| omega-1 | fin | battleship | superior | 100 | 19744835 | 329081 |
| omega-1 | fin | ground_turret_anti_light | superior | 1 | 469 | 8 |
| omega-1 | fin | ground_turret_anti_capital | superior | 100 | 196875 | 3282 |
| turbo-1 | début | engineering_foundations | basic | 1 | 6 | 1 |
| turbo-1 | début | probe | basic | 1 | 2 | 1 |
| turbo-1 | début | colony_ship | basic | 1 | 72 | 2 |
| turbo-1 | début | battleship | basic | 100 | 170514 | 2842 |
| turbo-1 | début | ground_turret_anti_light | basic | 1 | 3 | 1 |
| turbo-1 | début | ground_turret_anti_capital | basic | 100 | 1260 | 21 |
| turbo-1 | milieu | engineering_foundations | basic | 10 | 54 | 1 |
| turbo-1 | milieu | probe | basic | 1 | 2 | 1 |
| turbo-1 | milieu | colony_ship | basic | 1 | 64 | 2 |
| turbo-1 | milieu | battleship | basic | 100 | 151828 | 2531 |
| turbo-1 | milieu | ground_turret_anti_light | basic | 1 | 3 | 1 |
| turbo-1 | milieu | ground_turret_anti_capital | basic | 100 | 1260 | 21 |
| turbo-1 | fin | engineering_foundations | basic | 25 | 317 | 6 |
| turbo-1 | fin | probe | basic | 1 | 1 | 1 |
| turbo-1 | fin | colony_ship | basic | 1 | 53 | 1 |
| turbo-1 | fin | battleship | basic | 100 | 126367 | 2107 |
| turbo-1 | fin | ground_turret_anti_light | basic | 1 | 3 | 1 |
| turbo-1 | fin | ground_turret_anti_capital | basic | 100 | 1260 | 21 |
| turbo-1 | fin | engineering_foundations | superior | 25 | 494 | 9 |
| turbo-1 | fin | probe | superior | 1 | 2 | 1 |
| turbo-1 | fin | colony_ship | superior | 1 | 83 | 2 |
| turbo-1 | fin | battleship | superior | 100 | 197449 | 3291 |
| turbo-1 | fin | ground_turret_anti_light | superior | 1 | 5 | 1 |
| turbo-1 | fin | ground_turret_anti_capital | superior | 100 | 1969 | 33 |

## Production, stockage et blocages

À vitesse ×1, position 5, sans événement, une centrale Basique produit 1 080 E/h par niveau. Les extractions déduisent chacune 180 E/h par niveau ; raisonner sur le solde disponible, pas seulement sur la production brute. Le stockage électrique vaut 10 000 E + 100 000 E par niveau Basique de stockage ; les poids de stockage Intermédiaire et Supérieur sont 1,25 et 1,5625.

Une heure d’opération coûte ici 60 E : 5,56 % d’une heure de production brute d’une centrale Basique 1. À solde de 1 080 E/h, un colonisateur initial à 119 E représente environ 6 min 37 s de production. Sans centrale et avec une réserve électrique nulle, le revenu métal/nano ne suffit pas à payer l’électricité : il faut construire une centrale (bâtiment non taxé), dont le budget actuel est 1 440 M + 480 N. À 10 M/h et 10 N/h depuis zéro, cela nécessite au moins 144 h pour réunir les ressources, hors autres dépenses. Aucune garantie de déblocage général.

En fin de comparaison, 100 cuirassés Supérieurs demanderaient 329 081 E, au-delà du stockage de base et de celui d’un stockage Basique 1 (110 000 E). Un stockage Basique 4 atteint 410 000 E ; à centrale Basique 80 et sans autre consommation, reconstituer 329 081 E prend environ 3 h 49 min. Un lot plus petit reste possible. Le coefficient de 1 E/10 s multiplierait ces budgets par six ; 1 E/600 s les diviserait environ par dix, avec plus de petits coûts arrondis à 1 E.

Turbo réduit les durées à 1 % : les coûts suivent la durée réduite, sans seconde multiplication par la vitesse de production. Les arrondis des petites commandes limitent cette proportion. Par exemple le colonisateur initial passe de 119 E à 2 E. Les bonus de laboratoire/chantier sont déjà inclus dans les durées ci-dessus. Une durée réduite de 70 % doit réduire l’assiette du coût de 70 %, sous réserve d’arrondi ; aucune réduction après mise en file ne le recalcule.

## Annulation et lots : décisions confirmées

L’annulation actuelle rembourse 25 % de chaque ressource effectivement payée pour la commande et tous ses descendants, dans la place disponible sur chaque planète payeuse. L’électricité pourrait suivre cette règle via les coûts enregistrés, et son application au nouveau coût a été confirmée explicitement. Une capture suit une autre règle : annulation sans remboursement.

Décision validée : appliquer aussi les 25 % à l’électricité payée, y compris aux dépendances, et conserver la limite de stockage. Les quantités restent payées et annulées par commande complète. Arrondir le coût électrique une seule fois sur la durée totale du lot ; le fractionnement en petites commandes peut augmenter le total à cause des arrondis. Ne jamais calculer le remboursement depuis la durée accélérée.

Vérifications de l’intégration : tests serveur de paiement, aperçu, maximum constructible, double soumission, dépendances, remboursement plafonné, vacances, univers et accélération. Examiner aussi le chemin automatique de reconstruction des empires oubliés, qui partage le calculateur, pour éviter un effet métier non décidé.

## Complément : expansion coloniale et opérations longues

Mêmes hypothèses que le tableau principal, univers Omega, rang Supérieur. Ces coûts soulignent aussi le besoin de stockage pour les recherches longues, indépendamment des lots.

| Phase | Opération | Niveau / quantité | Durée (s) | E |
|---|---|---:|---:|---:|
| début | colonial_expansion | 1 | 444046 | 7401 |
| début | weapons_engineering | 1 | 33304 | 556 |
| début | capital | 100 | 39964116 | 666069 |
| fin | colonial_expansion | 15 | 7631281 | 127189 |
| fin | weapons_engineering | 25 | 1777620 | 29627 |
| fin | capital | 100 | 29617252 | 493621 |
