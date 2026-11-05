# Vérification finale des contres — 4 novembre 2026

## Résultat

Aucune anomalie technique détectée dans l’application des contres configurés. Aucun coefficient, regroupement ou comportement de jeu n’a été modifié pendant cette vérification.

## Périmètre et méthode

- 18 types d’unités : 14 vaisseaux et 4 défenses au sol.
- 308 couples admissibles de types : vaisseau vers vaisseau, vaisseau vers défense, défense vers vaisseau. Les défenses ne s’affrontent pas entre elles.
- 9 combinaisons de rangs par couple, sans recherches puis avec les recherches maximisées : **5 544 simulations**.
- **254 contrôles supplémentaires**, avec caractéristiques de contrôle ramenant la probabilité de base à 50 % et une cible résistante sans bouclier. Ces cas vérifient les coefficients hors des plafonds et permettent de comparer les dégâts de chaque impact sans destruction prématurée.
- **41 654 tirs vérifiés**, dont **23 590 impacts** et **216 tirs secondaires**.

Le coefficient attendu a été déterminé depuis les relations génériques de la configuration, indépendamment de la matrice développée transmise au simulateur. La chance attendue a été recalculée séparément avec la formule documentée, puis comparée aux journaux de tirs. Les impacts ont été comparés à l’attaque de l’arme multipliée une seule fois par le coefficient.

## Points vérifiés

- Tous les attaquants et cibles déclarés sont connus ; les coefficients et libellés sont présents et valides.
- Chasseur contient exactement Chasseur standard, Chasseur furtif et Intercepteur, et exclut Chasseur lourd.
- Les relations génériques sont correctement développées vers les trois types et leurs trois rangs.
- Les défenses au sol sont reconnues globalement, notamment pour le contre du Bombardier. Une relation spécifique à une tourelle prime sur la relation générique.
- Les coefficients favorables, neutres et défavorables modifient les dégâts et la chance de toucher une seule fois chacun. Ils ne sont pas ajoutés comme des points de précision.
- Les chances finales restent comprises entre 15 % et 95 %, après multiplication de la chance de base déjà bornée.
- Les tirs supplémentaires utilisent la même relation que les tirs principaux.
- Les quatre armes secondaires ont une correction de précision de +30 points et ne tirent que sur leurs cibles admissibles. Les unités non armées n’émettent aucun tir.
- Les recherches modifiant attaque, précision, suivi ou esquive restent compatibles avec le calcul des contres.
- Les **54 fiches militaires** des trois rangs affichent **177 relations non neutres**, toutes conformes aux relations de configuration.

## Conséquence du regroupement

L’Intercepteur dispose d’un contre ×1,30 envers la classe Chasseur. Il contre donc également les Intercepteurs adverses, puisque ceux-ci appartiennent à cette classe. Ce résultat découle du regroupement demandé et de la consolidation du bonus le plus fort ; ce n’est pas une seconde application de coefficient. Les profils offensifs des autres membres de Chasseur restent distincts.

## Vérifications complémentaires

- `make validate` : réussi.
- `make lint` : réussi, PHP, YAML, Twig, conteneur et schéma.
- Tests ciblés combat, fiches et coûts : **14 tests / 2 825 assertions**, réussis.

La suite générale n’a pas été relancée pendant cette vérification ciblée. Ses échecs déjà consignés ne sont pas déclarés corrigés. Cette vérification porte sur la conformité du fonctionnement aux règles actuelles, pas sur une mesure de victoire ou d’équilibrage économique des flottes.
