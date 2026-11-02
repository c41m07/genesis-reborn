# Équilibrage de Genesis Reborn — décisions du QCM

Date de clôture : 2 novembre 2026. Orientation retenue : stratégie modérée, progression régulière, gains et pertes contrôlés, sans obligation de rester connecté en continu.

Ce document consigne les valeurs choisies par Kévin pendant le QCM. Les durées cibles sont des repères à mesurer auprès des joueurs pendant l’Alpha; elles ne sont pas présentées comme des résultats atteints. Les retours et mesures guideront les ajustements après les sessions.

## Progression visée

| Étape | Cible |
|---|---|
| Première colonie supplémentaire | 3 à 5 jours |
| Flotte spécialisée de milieu de partie | 4 à 6 semaines |
| Premier vaisseau capital | 4 à 6 mois |

La technologie Colonisation niveau 1 prend 72 heures avec un laboratoire niveau 1. Elle ouvre un second emplacement planétaire; les niveaux suivants ajoutent chacun un emplacement jusqu’au plafond de cinq planètes.

## Économie

### Ressources de départ

| Ressource | Planète principale | Nouvelle colonie |
|---|---:|---:|
| Métal | 40 000 | 8 000 |
| Énergie | 20 000 | 3 000 |
| Antimatière | 2 000 | 0 |
| Hydrogène | 10 000 | 1 500 |
| Carbone | 10 000 | 1 000 |

### Production et stockage

| Paramètre | Valeur |
|---|---:|
| Production passive métal | 0,15/s |
| Production passive énergie | 0,15/s |
| Production passive antimatière | 0,015/s |
| Production passive hydrogène | 0,075/s |
| Production passive carbone | 0,1/s |
| Production par niveau d’extracteur | +0,1/s |
| Production par niveau de centrale énergétique | +0,1 énergie/s |
| Consommation par niveau d’extracteur | 0,05 énergie/s |
| Stockage de base par ressource | 200 000 |
| Stockage ajouté par niveau d’entrepôt | +100 000 |

Si la production énergétique ne couvre pas l’entretien des extracteurs, la production minière est réduite proportionnellement. Le facteur de position va de 1,2 à 0,8 pour le métal, l’énergie et le carbone; il évolue en sens inverse pour l’hydrogène. L’antimatière reste à 1,0. Les multiplicateurs de planète PNJ affectent uniquement le stock initial.

La consommation d’énergie continue a été choisie pendant le QCM; son intégration au calcul paresseux des ressources doit respecter le stock déjà accumulé avant l’épuisement de l’énergie.

### Coûts et durées

| Paramètre | Valeur |
|---|---:|
| Progression des coûts des bâtiments | ×1,8 par niveau |
| Progression des coûts des technologies | ×2 par niveau |
| Progression des durées des bâtiments | ×1,6 par niveau |
| Progression des durées des recherches | ×1,8 par niveau |
| Réduction de recherche par niveau de laboratoire | 10 %, plafond 50 % |
| Réduction de construction de vaisseaux par chantier | 5 % par niveau, plafond 50 % |
| Ingénierie fondamentale | −5 % de durée de bâtiment par niveau, plafond 25 % |
| Ingénierie avancée | −5 % du coût de fabrication des vaisseaux par niveau, plafond 25 % |
| Nanoconstruction | −5 % du coût des bâtiments par niveau, plafond 25 % |

Les réductions technologiques s’appliquent au coût ou à la durée de base calculés pour l’opération. Leur ordre exact de calcul doit être conservé et vérifié dans le moteur.

## Bâtiments et technologies

La Forteresse augmente la structure des défenses de 2,5 % par niveau, jusqu’à 25 %.

| Technologie | Effet choisi |
|---|---|
| Robotique | Reconstruit gratuitement après combat 10 % des défenses perdues par niveau, jusqu’à 50 %; arrondi à l’entier inférieur à chaque combat, sans reliquat |
| Propulsion avancée | +5 % de vitesse par niveau, jusqu’à 25 % |
| Ciblage avancé | +2 de suivi par niveau, jusqu’à +10 |
| Tir de précision | +5 de précision par niveau, niveau maximal 3 |
| Technologie de bouclier | +5 % de bouclier maximal par niveau, jusqu’à 25 % |
| Technologie d’armement | +5 % de dégâts par niveau, jusqu’à 25 %, pour les vaisseaux seulement |
| Espionnage | +1 catégorie de renseignement par niveau d’avance sur la cible |
| Furtivité | −10 points de pourcentage de détection par niveau |
| Contre-espionnage | +10 points de pourcentage de détection par niveau |

La technologie de bouclier coûte au niveau 1 : 6 000 métal, 3 000 énergie et 500 antimatière; durée de base 4 heures. Elle requiert un laboratoire niveau 2 et Ingénierie avancée niveau 1. Les cinq niveaux coûtent au total 186 000 métal, 93 000 énergie et 15 500 antimatière.

La technologie d’armement coûte au niveau 1 : 8 000 métal, 4 000 énergie et 1 000 antimatière; durée de base 6 heures. Elle requiert un laboratoire niveau 2, Ciblage avancé niveau 2 et Tir de précision niveau 1. Les cinq niveaux coûtent au total 248 000 métal, 124 000 énergie et 31 000 antimatière.

La technologie Colonisation coûte au niveau 1 : 10 000 métal, 5 000 énergie et 250 antimatière. Sa durée de base est 80 heures, soit 72 heures avec un laboratoire niveau 1. Elle comporte quatre niveaux et ajoute une planète maximale par niveau, jusqu’à cinq.

## Combat

La cadence confirmée reste un round par minute, jusqu’à dix minutes et dix rounds. Les chances de toucher de base ci-dessous s’appliquent sans recherche contre une cible du même type. Les technologies de précision et de ciblage peuvent ajouter ensemble 25 points; le plafond général reste 95 %.

| Catégorie | Chances visées sans recherche |
|---|---:|
| Fighter | 75 % |
| Interceptor | 75 % |
| Stealth fighter | 65 % |
| Heavy fighter | 80 % |
| Bomber | 80 % |
| Corvette et vaisseaux plus lourds | 90 % |

Le suivi calculé à partir de la formule actuelle de toucher est : fighter 76; interceptor 86,4; stealth fighter 83,4; heavy fighter 73; bomber 72; corvette 70; frigate 63; destroyer 56; cruiser 45; battleship 38; capital 26. Le suivi des unités de soutien reste 60.

Les boucliers absorbent les dégâts avant la structure. Un bouclier encore présent se recharge de 5 % de sa valeur maximale entre deux rounds, sans dépasser ce maximum. Les unités survivantes récupèrent leur structure et leur bouclier après le combat.

La reconstruction défensive suit Robotique; les vaisseaux détruits sont perdus sans récupération de leur coût. Le butin reste à 100 % du stock situé au-dessus de 10 % de la capacité de stockage par ressource, comme dans la règle confirmée.

### Matrice de dégâts

Les coefficients explicitement sourcés du PDF sont conservés et priment sur les règles de famille. Les affrontements sans coefficient documenté sont remplis avec le cycle choisi : chasseurs légers > chasseurs lourds > grands vaisseaux > chasseurs légers. Les contre-bonus forts sont ×1,30 et les faiblesses ×0,70; les rencontres neutres valent ×1,00. Les paires explicites du PDF peuvent donc déroger à ce cycle, notamment fighter contre heavy fighter à ×0,85. Les deux interactions défensives conservées sont fighter contre défense au sol ×0,85 et bomber contre défense au sol ×1,30.

### Statistiques des vaisseaux et défenses

La structure ci-dessous reste indépendante du bouclier.

| Unité | Structure | Bouclier | Attaque/tir | Tirs par round | Précision | Suivi | Esquive | Signature |
|---|---:|---:|---:|---:|---:|---:|---:|---:|
| Fighter | 150 | 50 | 40 | 1–3 | 70 | 76 | 60 | 20 |
| Interceptor | 180 | 40 | 35 | 1–3 | 75 | 86,4 | 75 | 18 |
| Stealth fighter | 280 | 75 | 60 | 1–2 | 85 | 83,4 | 90 | 8 |
| Heavy fighter | 250 | 100 | 70 | 1–3 | 65 | 73 | 50 | 35 |
| Bomber | 600 | 150 | 150 | 1–2 | 60 | 72 | 45 | 40 |
| Corvette | 700 | 200 | 160 | 1–3 | 70 | 70 | 40 | 25 |
| Frigate | 1 100 | 300 | 220 | 1–3 | 70 | 63 | 35 | 35 |
| Destroyer | 1 600 | 600 | 300 | 1–3 | 70 | 56 | 30 | 45 |
| Cruiser | 2 500 | 1 000 | 500 | 1–3 | 75 | 45 | 25 | 50 |
| Battleship | 4 000 | 2 000 | 800 | 1–3 | 75 | 38 | 20 | 60 |
| Capital | 10 000 | 5 000 | 1 600 | 1–3 | 80 | 26 | 15 | 70 |
| Transport | 400 | 100 | 0 | — | 70 | 60 | 35 | 30 |
| Colony ship | 500 | 150 | 0 | — | 70 | 60 | 35 | 30 |
| Probe | 10 | 5 | 0 | — | 70 | 60 | 90 | 5 |
| Ground turret | 600 | 100 | 100 | 2 | 65 | 85 | 0 | 60 |

### Coûts de fabrication et temps de construction

Toutes les cases de ressources sont explicites; zéro signifie qu’aucune ressource de ce type n’est requise. L’hydrogène consommé en trajet reste distinct du coût de fabrication.

| Unité | Métal | Énergie | Antimatière | Hydrogène | Carbone | Durée de base |
|---|---:|---:|---:|---:|---:|---:|
| Fighter | 3 000 | 1 000 | 0 | 0 | 0 | 60 s |
| Interceptor | 3 500 | 1 167 | 0 | 0 | 0 | 75 s |
| Heavy fighter | 6 000 | 2 000 | 0 | 0 | 0 | 120 s |
| Stealth fighter | 9 000 | 4 000 | 800 | 0 | 0 | 240 s |
| Bomber | 10 000 | 3 333 | 500 | 0 | 0 | 240 s |
| Corvette | 14 000 | 4 666 | 0 | 0 | 0 | 300 s |
| Frigate | 22 000 | 7 333 | 0 | 0 | 0 | 420 s |
| Destroyer | 30 000 | 10 000 | 0 | 0 | 0 | 600 s |
| Cruiser | 45 000 | 15 000 | 0 | 0 | 0 | 900 s |
| Battleship | 72 000 | 24 000 | 0 | 0 | 0 | 1 500 s |
| Capital | 160 000 | 53 333 | 0 | 0 | 0 | 3 600 s |
| Transport | 12 000 | 4 000 | 0 | 0 | 0 | 180 s |
| Colony ship | 20 000 | 6 666 | 0 | 0 | 0 | 600 s |
| Probe | 500 | 166 | 0 | 0 | 0 | 30 s |
| Ground turret | 8 000 | 2 000 | 0 | 0 | 0 | 180 s |

## Déplacements et carburant

Les vitesses restent 50 en système et 500 entre systèmes. La règle de vitesse est proportionnelle : voler à la moitié de la vitesse coûte la moitié du carburant et double la durée. Le voyage aller-retour est réservé au départ. La consommation en orbite est 0,3 hydrogène par heure et par unité.

| Unité | Réservoir d’hydrogène |
|---|---:|
| Fighter | 700 |
| Interceptor | 800 |
| Heavy fighter | 1 000 |
| Stealth fighter | 1 200 |
| Bomber | 1 800 |
| Corvette | 2 500 |
| Frigate | 3 500 |
| Destroyer | 4 500 |
| Cruiser | 6 000 |
| Battleship | 8 000 |
| Capital | 15 000 |
| Transport | 3 000 |
| Colony ship | 2 000 |
| Probe | 16 000 |

Les coefficients de trajet restent 0,5 hydrogène par unité de distance intra-système et 2 par unité de distance inter-systèmes.

## Planètes PNJ

| Position | Niveau PNJ |
|---|---|
| 1–2 | 1 |
| 3–4 | 2 |
| 5–6 | 3 |
| 7–8 | 4 |
| 9 | 5 |

Les compositions de flottes et de défenses actuelles sont conservées. La conquête donne la planète, avec ses bâtiments et ressources. Les multiplicateurs de stock initial par niveau sont 1; 1,5; 2; 2,75; 3,5. Ils ne multiplient pas la production continue.

## Espionnage

La base de détection est 50 %. Le contre-espionnage ajoute 10 points par niveau, la furtivité enlève 10 points par niveau. Chaque sonde supplémentaire et chaque sonde locale ajoutent 1 point. Le résultat est borné à 5–95 %.

Une sonde avec une technologie au même niveau que la cible révèle une catégorie. Chaque niveau d’avance et chaque sonde supplémentaire ajoutent une catégorie, jusqu’aux cinq catégories disponibles.

## Protection et récupération

| Règle | Valeur |
|---|---|
| Protection des nouveaux joueurs | 7 jours ou 5 000 points, selon le premier seuil atteint |
| Attaquant bloqué pendant cette protection | Score supérieur à 5 fois celui du joueur protégé |
| Protection après lourde défaite | 24 h après la perte d’au moins 70 % de la flotte militaire engagée |
| Réutilisation de la protection après défaite | Au plus une fois tous les 7 jours |
| Récupération des vaisseaux détruits | Aucune |
| Réparation des unités survivantes | Structure et bouclier restaurés |

Le seuil de 70 % porte sur les vaisseaux militaires engagés; les sondes et unités de soutien ne comptent pas. Le score utilisé pour la protection débutant est le score de classement de l’univers.

## Risques et vérifications de cohérence

- Au maximum des technologies Ciblage avancé et Tir de précision, les chances augmentent de 25 points : fighter 95 %, interceptor 95 %, furtif 90 %, heavy fighter 95 %, bomber 95 %, et les vaisseaux plus lourds 95 % après plafonnement.
- Les fortes chances de toucher en fin de progression, les coefficients de dégâts extrêmes conservés du PDF, le pillage élevé et l’absence de récupération des vaisseaux peuvent accentuer les pertes. Les protections de début et d’après-défaite limitent l’enchaînement d’attaques.
- La production minière consomme désormais de l’énergie. Le calcul doit d’abord dépenser le stock énergétique disponible, puis réduire proportionnellement la production minière lorsque ce stock ne peut plus couvrir le déficit.
- Les objectifs de durée de première colonie, flotte de milieu de partie et vaisseau capital restent à mesurer sur des scénarios complets.

## Fichiers de configuration concernés

Les valeurs sont enregistrées dans config/game/rules.yaml, les catalogues config/game/buildings, config/game/technologies, config/game/ships et config/game/defenses, ainsi que config/game/planet_conquest.yaml, config/game/espionage.yaml et config/game/combat_modifiers.yaml.

Le document ne remplace pas les règles de combat confirmées : un round par minute, dix minutes maximum, ratio de pillage 1,0 et part protégée de 0,1 restent en vigueur.


## Application effective dans le runtime

Les paramètres de ce document sont reliés aux services métier : les files de construction appliquent les réductions configurées et les coûts de vaisseaux incluent les cinq ressources; les mines suivent les facteurs de position et consomment l’énergie disponible avant de réduire proportionnellement leur production; les technologies modifient les statistiques de combat et de voyage; les boucliers absorbent les dégâts puis rechargent entre rounds; la forteresse augmente la structure des défenses et la robotique en reconstruit une part après combat. Les limites de colonisation, la détection d’espionnage et les protections de début et d’après-défaite sont également contrôlées au moment des missions et de la résolution des combats.

Les champs de protection après défaite sont persistés par migrations/Version20261102100000.php. Les combats déjà ouverts gardent leur instantané initial afin de préserver le caractère déterministe de leur résolution.


## Extension décidée après le QCM

Les choix du 2 novembre définissent aussi trois rangs de progression indépendants. Les multiplicateurs 1× / 1,25× / 1,5625× sont composés (jamais additionnés). Les bonus statistiques s’appliquent en pourcentage de la valeur de base; les recherches historiques à +5 % passent à +1 %/niveau, niveau 25. Le laboratoire et le chantier spatial apportent 2 %/niveau jusqu’au niveau 25. Les bâtiments de production et de stockage restent sans plafond. La croissance de durée de recherche ×1,8 est conservée, tout comme le coût par niveau ×2. Voir [REGLES.md](../REGLES.md) pour l’arrondi et les conversions de statistiques entières.
