# Rééquilibrage progression et combat — 2 novembre 2026

**Statut :** validé par Kévin, intégré aux règles du projet. Les jalons de campagne sont des objectifs de conception ; leur atteinte dépendra des retours de l’Alpha.

## Progression économique

Le profil de référence est constitué des extracteurs de la planète principale, chacun au niveau indiqué. Les autres colonies et le maintien de l’énergie sont à intégrer aux simulations de campagne. La production actuelle reste linéaire : niveau pondéré par rang × production par niveau × facteur de position × multiplicateur d’univers, puis rendement ajusté à l’énergie disponible.

Pour une mine Basique dont le coût initial est 800 Métal, le facteur de coût appliqué à chaque niveau cible est :

| Niveau cible | Croissance du coût par niveau |
|---|---:|
| 2–20 | ×1,12 |
| 21–80 | ×1,09 |
| 81–120 | ×1,03 |
| 121 et plus | ×1,02 |

Les profils de coût propres à chaque extracteur et rang restent appliqués avant cette courbe. Pour une mine de référence, les montants et durées brutes calculés sont :

| Niveau | Coût d’amélioration | Durée brute |
|---:|---:|---:|
| 1 | 800 | 20 s |
| 10 | 2 219 | 57 s |
| 20 | 6 891 | 3 min |
| 40 | 38 616 | 29 min 57 s |
| 80 | 1 212 893 | 6 h 2 min |
| 100 | 2 190 619 | 12 h 4 min |
| 120 | 3 956 502 | 24 h 10 min |
| 150 | 7 166 654 | 72 h 31 min |

Les durées suivent les multiplicateurs par phase définis dans config/game/rules.yaml. La formule d’une opération est :

durée = base × courbe(niveau) × quantité × multiplicateur d’univers × facteur de rang × (1 − réduction).

Les recherches utilisent une croissance de coût et de durée plus modérée : ×1,35 jusqu’au niveau 5, ×1,25 jusqu’au 10, ×1,18 jusqu’au 15, puis ×1,12. L’Alpha devra mesurer le délai réel des jalons de mines 20 (1–3 jours), 80 (3–6 semaines) et 120 (3–6 mois), avec des sessions courtes quotidiennes.

## Bonus technologiques

Les rangs Basique, Intermédiaire et Supérieur améliorent les mêmes unités. Un effet technologique est calculé une seule fois après agrégation des rangs, avec :

progression = ln(1 + 2 × investissement pondéré) / ln(3)

L’investissement est normalisé entre 0 et 1 à partir des niveaux investis et des facteurs de rang (1 ; 1,25 ; 1,5625). Le bonus final est plafond × progression. Les rendements décroissent et le maximum n’est atteint qu’avec les trois branches maximisées.

- Réduction totale de durée : **70 % maximum** par opération.
- Réduction de coûts par technologie : **40 % maximum**.
- Reconstruction défensive : **40 % maximum**.
- Les bonus aux statistiques sont plafonnés par technologie tous rangs confondus. Précision et suivi : 15 % chacun ; esquive : 20 % ; attaque, structure, bouclier, propulsion, soute et réservoir gardent leurs plafonds de catalogue.
- Cycle de tir optimisé : chance maximale de tir supplémentaire **50 % par tir de base**. Le rang de vaisseau ne donne plus de tir supplémentaire automatique.

## Profils de vaisseaux

Les statistiques de structure, bouclier, attaque, précision, suivi, esquive et signature partent des profils du QCM. Les vitesses système/inter-systèmes et tirs principaux ont été différenciés. Les durées de construction ci-dessous sont les bases du chantier ; les rangs, recherches et multiplicateurs d’univers s’appliquent ensuite.

| Vaisseau | Vitesse système / inter-systèmes | Tirs principaux | Base de construction |
|---|---:|---:|---:|
| Chasseur | 65 / 650 | 1–2 | 3 min |
| Intercepteur | 100 / 1 000 | 1–3 | 4 min |
| Chasseur furtif | 75 / 800 | 2 | 15 min |
| Chasseur lourd | 55 / 550 | 1–2 | 10 min |
| Bombardier | 40 / 400 | 1–2 | 20 min |
| Corvette | 70 / 700 | 2–3 | 45 min |
| Frégate | 55 / 550 | 2–3 | 2 h |
| Destroyer | 45 / 450 | 2–4 | 6 h |
| Croiseur | 38 / 380 | 3–4 | 16 h |
| Cuirassé | 30 / 300 | 4–5 | 2 jours |
| Vaisseau capital | 22 / 220 | 5–6 | 3 jours |
| Transporteur | 45 / 450 | — | 1 h |
| Vaisseau-colonie | 35 / 350 | — | 2 h |
| Sonde exploratrice | 120 / 1 200 | — | 2 min |

Le Chasseur furtif utilise une attaque de base de 85 et deux tirs principaux fixes ; cette valeur a été relevée après simulation exploratoire pour que sa spécialisation contre le Vaisseau capital ait un effet mesurable à coût comparable. Ce réglage reste à confirmer par des simulations plus larges. Destroyer, Croiseur, Cuirassé et Vaisseau capital disposent d’un unique tir secondaire anti-léger, moins précis et de puissance limitée. Il ne remplace pas leur salve principale.

## Combat et contres

La signature n’ajoute plus de précision à la formule de touche. Elle pondère le choix de cible : une unité de faible signature a moins de chances d’être sélectionnée. La chance de touche reste précision + suivi − esquive − 15 + écart de rang, plafonnée entre 15 % et 95 %.

Une salve principale conserve sa cible tant qu’elle survit. Après sa destruction, le tir suivant peut choisir une nouvelle cible. Les tirs secondaires sélectionnent séparément une cible de leur catégorie.

Chaque unité combattante a exactement un super contre et un contre simple. Les paires définissent simultanément la faiblesse et la super faiblesse inverses. Le bonus de dégâts n’est appliqué qu’une fois : +30 % ou +15 % à l’attaquant favorisé, équivalent au dégât reçu par sa cible.

| Vaisseau | Super contre | Contre | Faiblesse | Super faiblesse |
|---|---|---|---|---|
| Chasseur | Bombardier | Cuirassé | Chasseur lourd | Intercepteur |
| Bombardier | Cuirassé | Croiseur | Intercepteur | Chasseur |
| Cuirassé | Croiseur | Destroyer | Chasseur | Bombardier |
| Croiseur | Destroyer | Frégate | Bombardier | Cuirassé |
| Destroyer | Frégate | Corvette | Cuirassé | Croiseur |
| Frégate | Corvette | Chasseur furtif | Croiseur | Destroyer |
| Corvette | Chasseur furtif | Vaisseau capital | Destroyer | Frégate |
| Chasseur furtif | Vaisseau capital | Chasseur lourd | Frégate | Corvette |
| Vaisseau capital | Chasseur lourd | Intercepteur | Corvette | Chasseur furtif |
| Chasseur lourd | Intercepteur | Chasseur | Chasseur furtif | Vaisseau capital |
| Intercepteur | Chasseur | Bombardier | Vaisseau capital | Chasseur lourd |

Toutes les paires non indiquées sont normales. Les unités utilitaires sont des cibles vulnérables, sans contre dédié.

## Défenses

La tourelle anti-léger utilise 3 tirs de 45 dégâts, précision 78 et suivi 170. Cela relève fortement sa probabilité de toucher les petites unités agiles sans lui donner une salve lourde. Les quatre tourelles gardent leur spécialisation de dégâts. La Forteresse et la technologie de réparation ont maintenant des bonus plafonnés communs aux rangs.

## Points de mesure Alpha

Les estimations de campagne doivent être vérifiées avec la production réelle, les coûts multi-ressources, les files indépendantes, les colonies et les améliorations de recherche accessibles. Pour le combat, les prochaines simulations doivent comparer coût égal et nombre égal, puis des flottes mixtes ; mesurer pertes, dégâts par round, survie et durée. Les taux de victoire ne sont pas considérés validés par cette modification seule.
