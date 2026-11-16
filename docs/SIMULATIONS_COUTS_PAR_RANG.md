# Comparaison séparée des coûts Basiques et Intermédiaires

Mesures du 16 novembre 2026, outil issu de dev `4bbb0cf`, sans activation.
Cette étude complète le [rapport conjoint](SIMULATIONS_PROGRESSION_ECONOMIE.md)
en isolant les deux changements de prix. Ses stratégies, kits initiaux, files,
stockages adaptatifs, production et limites restent les mêmes.

## Quatre variantes

| Variante | Basique : métal / nano | Intermédiaire : métal / nano / antimatière | Supérieur |
| --- | --- | --- | --- |
| Actuel | 60 / 40 | 45 / 45 / 10 | Inchangé |
| B seul | 50 / 50 | 45 / 45 / 10 | Inchangé |
| I seul | 60 / 40 | 40 / 40 / 20 | Inchangé |
| B + I | 50 / 50 | 40 / 40 / 20 | Inchangé |

Les parts portent sur V = 2 M + 4 N + 8 A + H, sans marché ni échange.
La variante simple modifie exactement 52 profils, celle combinée 104 ; les profils
non sélectionnés restent strictement identiques, y compris les défenses et les civils.
Chaque budget de base propre reste conservé. Les mêmes priorités sont exécutées ;
les achats réalisables et les dates de paiement peuvent varier selon leurs prix.

## Reproduire sans lire ni modifier les comptes

```bash
mkdir -p output/balance/ranks
make -s console CMD="app:balance:compare-economy --candidate-tier=basic" > output/balance/ranks/all-basic.json
make -s console CMD="app:balance:compare-economy --candidate-tier=intermediate" > output/balance/ranks/all-intermediate.json
make -s console CMD="app:balance:compare-economy" > output/balance/ranks/all-combined.json
```

`--candidate-tier` est répétable ; sans option les deux rangs restent sélectionnés,
comme dans l’étude initiale. `--section` et `--trace` conservent leur rôle.
Les résultats bruts sont ignorés par Git. Le [CSV synthétique](RESULTATS_COUTS_PAR_RANG.csv)
versionné rassemble les 108 parcours distincts : 27 politiques × quatre variantes.
Les trois commandes exécutent 162 parcours en comptant leurs contrôles actuels répétés.

Les contrôles actuels sont strictement identiques entre les trois exécutions.
La variante combinée reproduit exactement les bilans économiques et militaires
financés du rapport initial. Tous les parcours terminent et conservent les ressources
(stock initial + production − consommation − dépenses − non stocké = stock final),
avec un écart maximal de 0,00000009 unité, inférieur à la tolérance de 0,001.
Les budgets, durées, bonus, quantités et caractéristiques militaires ne sont pas ajustés.

Empreinte de configuration commune : `fd425f965b3af6d457d4968fac02de6a42414cc52343c6ffcca3d92f44b3df69`.
Scénarios version 2, PHP 8.4.16 ; mêmes graines de combat que le rapport initial.

## Économie depuis les kits réels

Durées en jours de 24 h. Les cinq parcours complets visent B20/I20/S5 avec les
objectifs annoncés dans la méthode initiale ; les colonies visent B10 uniquement.

| Parcours (j) | Actuel | B seul | I seul | B + I |
| --- | --- | --- | --- | --- |
| Économie sans civils | 3,189 | 3,420 | 3,027 | 3,419 |
| Économie avec civils | 3,726 | 3,726 | 3,557 | 3,716 |
| Mixte science/militaire | 10,999 | 10,934 | 10,851 | 11,037 |
| 120 reconnaissances | 32,161 | 32,242 | 32,110 | 32,308 |
| 60 transports | 32,361 | 32,349 | 32,377 | 32,507 |
| Colonie B10, position 1 | 1,805 | 1,548 | 1,805 | 1,548 |
| Colonie B10, position 5 | 2,165 | 1,855 | 2,165 | 1,855 |
| Colonie B10, position 9 | 2,703 | 2,316 | 2,703 | 2,316 |

I seul accélère les parcours économiques de 5,06 % sans civils et de 4,53 % avec
civils, ainsi que le mixte de 1,35 %. Les transports gagnent ou perdent peu : I seul
ajoute environ 23 minutes sur 32,36 jours (+0,05 %), avec une cadence de douze heures
imposée par la stratégie. Ce résultat n’est pas une mesure de fréquence optimale.

B seul accélère les colonies B10 d’environ 14,3 %, mais ralentit le parcours
économique sans civils de 7,25 %. Le parcours avec civils est presque inchangé.
Les effets ne s’additionnent pas simplement : les paiements, bonus et concurrences
entre files peuvent évoluer différemment lorsque les deux rangs changent ensemble.

## Accès aux rangs : indépendants de la première livraison

Dates en heures depuis un empire neuf ; un accès ne construit aucune unité gratuite.

| Premier accès (h) | Actuel | B seul | I seul | B + I |
| --- | --- | --- | --- | --- |
| Économie sans civils → I | 9,24 | 12,05 | 9,24 | 12,05 |
| Économie sans civils → S | 42,09 | 53,16 | 38,99 | 54,30 |
| Économie avec civils → I | 8,75 | 15,35 | 8,75 | 15,35 |
| Économie avec civils → S | 51,83 | 56,92 | 48,59 | 57,90 |
| Mixte science/militaire → I | 8,75 | 15,35 | 8,75 | 15,35 |
| Mixte science/militaire → S | 51,83 | 57,61 | 48,59 | 58,20 |

Le retard du premier accès I observé dans la variante combinée provient du changement
Basique dans ces politiques : B seul reproduit ce retard, tandis que I seul garde
exactement la date actuelle. Avec civils, I reste à 8 h 45 ; S arrive à 48 h 36
au lieu de 51 h 50 avec I seul. La variante B seule porte ces dates à 15 h 21 et 56 h 55.
Ces résultats ne garantissent pas le délai d’une autre stratégie de joueur.

## Attentes de financement

Heures attribuées aux ressources pour le parcours avec civils. Des files peuvent
attendre simultanément : ces valeurs ne s’additionnent pas en durée réelle.

| Ressource | Actuel | B seul | I seul | B + I |
| --- | --- | --- | --- | --- |
| metal | 112,92 | 109,08 | 106,96 | 111,91 |
| nano | 0,77 | 26,11 | 1,59 | 33,41 |
| antimatter | 0,00 | 0,00 | 0,35 | 0,00 |
| electricity | 47,18 | 38,79 | 45,76 | 41,42 |

B seul demande davantage de nano et les attentes de nano augmentent nettement dans
la politique choisie. I seul répartit mieux la pression mesurée sur métal et nano,
en consommant plus d’antimatière : 59 176 A dépensés contre 34 108 A sur ce parcours,
avec environ 21 minutes d’attente A cumulée. L’étude ne démontre pas l’absence de
pénurie A dans toutes les stratégies, positions ou univers.

## Formation militaire et reconstruction

Heures depuis le même bilan industriel donné, issu du parcours mixte actuel.
Les différences ci-dessous isolent les prix ; elles n’incluent pas le temps nécessaire
pour développer cette base depuis une inscription. Les forces sont identiques et payées.

| Expérience (h) | Actuel | B seul | I seul | B + I |
| --- | --- | --- | --- | --- |
| Grande flotte B | 212,76 | 205,40 | 212,76 | 205,40 |
| Grande flotte I | 252,78 | 252,66 | 235,32 | 235,88 |
| Grande flotte S | 305,45 | 305,61 | 305,45 | 305,61 |
| Reconstruire les civils, usines intactes | 5,67 | 5,67 | 5,67 | 5,67 |
| Même perte civile, stocks nuls | 5,76 | 5,75 | 5,75 | 5,75 |
| Stress : financer la centrale B36 puis les civils | 31,92 | 27,17 | 31,92 | 27,17 |
| Reconstruire les défenses B, sans réparation | 28,52 | 25,99 | 28,52 | 25,99 |
| Remplacer les pertes offensives, graine 7 | 1,43 | 1,43 | 1,43 | 1,43 |
| Remplacer les pertes défensives, graine 7 | 1,68 | 1,68 | 1,68 | 1,68 |

Grande flotte : 500 chasseurs + 50 frégates + 10 cuirassés + 2 capitaux du même rang.
I seul réduit sa formation I de 17 h 27 (6,90 %). B seul réduit la formation B de
7 h 22 et la reconstruction des défenses B d’environ 2 h 32, mais conserve son compromis
défavorable sur les premiers accès de la politique économique.

Les petites formations, les sorties de chasseurs et leurs besoins H sont identiques
entre variantes dans les expériences retenues. Les caractéristiques et résultats
physiques du combat sont inchangés. Le délai S peut néanmoins varier malgré des prix S
identiques, car le financement de prérequis antérieurs peut changer.

Les reconstructions après combat excluent le temps de retour des attaquants et le butin,
comme précédemment. Le stress B36 est un état donné avec des infrastructures dégradées,
pas une garantie de reprise, un optimum ni une aide gratuite. Le plafond technique de
5 000 unités en combat reste inchangé et n’est pas corrigé par cette étude de prix.

## Arrondis et recommandation

Sur 912 devis par variante, l’écart maximal de valeur après croissance/remises vaut
4 unités avec B seul, 12 avec I seul, 12 avec B + I. Les budgets de base sont exacts ;
les arrondis par ressource ne garantissent pas un budget identique pour tous les
paiements futurs. À bonus égaux, durée et taxe E ne changent pas ; leurs totaux d’un
parcours peuvent varier si les dates de paiement ou les bonus acquis diffèrent.

La recommandation issue de ces essais est de **conserver B60/40 et privilégier I40/40/20
comme candidat à valider**, avec le Supérieur inchangé. I seul améliore la progression
et la grande formation I mesurées sans retarder le premier accès I. B50/50 garde un intérêt
pour les colonies et reconstructions B, mais son ralentissement des premiers déblocages
ne justifie pas son adoption générale avec les objectifs actuels.

Avant une éventuelle activation : décider du compromis recherché, traiter les écarts
d’arrondi et confirmer le candidat I seul sur des stratégies plus précoces de chantier
ou de colonisation et des situations de faible production d’antimatière. Ces propositions
ne sont pas encore des résultats mesurés. **Aucun changement de prix n’est activé.**

La sélection de rang n’utilise que des univers transitoires, sans lecture/écriture de
compte, sauvegarde de variante ou modification du catalogue applicatif. Aucune migration,
requalification d’accès historique ni livraison Alpha n’est exécutée.
