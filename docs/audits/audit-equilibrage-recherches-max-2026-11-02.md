# Audit d’équilibrage — recherches au niveau maximal

> **Statut : audit historique.** Ces résultats décrivent une simulation datée, pas une décision de jeu ni une preuve de conformité aux règles. Pour les décisions confirmées et leur intégration, voir [BALANCING_IMPLEMENTATION_2026-11-02.md](../agent/BALANCING_IMPLEMENTATION_2026-11-02.md); le [QCM](../agent/BALANCING_QCM_2026-11-02.md) conserve les propositions remplacées et celles encore à mesurer.

**Date :** 2 novembre 2026  
**Périmètre :** 13 bâtiments au rang Supérieur niveau 1, 14 vaisseaux et 4 tourelles au rang Supérieur, avec toutes les recherches maximisées dans chacun des rangs Basique, Intermédiaire et Supérieur.

## Hypothèses de simulation

- Univers Omega standard : multiplicateurs de temps à ×1. Les valeurs de Turbo, qui réduit les temps à 1 %, ne sont pas utilisées.
- Un exemplaire par construction, rang Supérieur. Les durées n’incluent pas de file d’attente ni de bonus temporaire.
- Pour calculer les temps, chantier spatial et laboratoire sont au niveau 25 dans les trois rangs. Pour les défenses, la forteresse est au niveau 10 dans les trois rangs.
- L’Ingénierie navale est maximisée : sa remise s’applique aux vaisseaux, pas aux défenses. Les coûts ci-dessous sont arrondis comme le jeu (arrondi supérieur par ressource).
- Les statistiques de combat utilisent les effets des technologies actives. La puissance brute des vaisseaux suppose 100 % de touches et un modificateur de dégâts neutre ; la défense est évaluée contre ses cibles prévues avec la précision calculée. Les coûts et durées propres aux recherches ne sont pas recalculés ici : les technologies sont prises comme déjà maximisées.

## Effets cumulés au maximum

| Effet | Valeur obtenue par le cumul des trois rangs |
|---|---:|
| Blindage, bouclier et esquive (unités combattantes) ; attaque, précision, suivi, propulsion, soute et réservoir (vaisseaux) | ×2,281, soit +128,1 % |
| Réduction de signature | ×0,314, soit −68,6 % |
| Réduction du coût des vaisseaux | −68,6 % |
| Réduction du temps de construction des bâtiments par Fondements d’ingénierie | −68,6 % |
| Réduction du temps des vaisseaux par chantier spatial max | −95,9 % |
| Réduction du temps des recherches par laboratoire max | −95,9 % |
| Tirs supplémentaires par Cycle de tir optimisé | +0,953 tir attendu par tir de base ; au rang Supérieur, le bonus de rang porte le facteur moyen à ×2,516 tirs |
| Reconstruction des défenses après combat | 95,9 % des unités perdues reconstruites, en moyenne selon la règle actuelle |
| Structure des défenses par forteresse max | ×2,281, en plus du blindage structurel ×2,281 |

Le facteur de tirs indiqué est une moyenne probabiliste. Pour les vaisseaux Supérieurs à 1–3 tirs, cela donne **5,03 tirs moyens par round**, contre 2 sans ces bonus.

## Bâtiments — rang Supérieur, niveau 1

Les coûts et durées supposent Synthèse des nanomatériaux et Fondements d’ingénierie maximisés. Le niveau 1 sert de base de comparaison ; les niveaux suivants appliquent leur facteur de croissance propre. Les bâtiments affichés « — » n’ont pas de maximum déclaré dans leur définition.

| Bâtiment | Coût (M / N / AM) | Temps | Niveau max déclaré |
|---|---:|---:|---:|
| Extracteur · Hydrogène | 256 / 69 / 0 | 59 s | — |
| Chantier spatial | 2 210 / 982 / 185 | 14 min 44 s | 25 |
| Extracteur · Antimatière | 158 / 69 / 25 | 59 s | — |
| Forteresse | 1 326 / 590 / 111 | 2 min 57 s | 10 |
| Stockage · Électricité | 553 / 246 / 47 | 1 min 29 s | — |
| Entrepôt · Hydrogène | 553 / 246 / 47 | 1 min 29 s | — |
| Extracteur · Métal | 393 / 0 / 0 | 59 s | — |
| Extracteur · Nano | 197 / 99 / 0 | 59 s | — |
| Entrepôt · Nano | 553 / 246 / 47 | 1 min 29 s | — |
| Entrepôt · Antimatière | 553 / 246 / 47 | 1 min 29 s | — |
| Laboratoire | 1 105 / 492 / 93 | 1 min 58 s | 25 |
| Entrepôt · Métal | 553 / 246 / 47 | 1 min 29 s | — |
| Centrale énergétique | 1 915 / 516 / 0 | 2 min 57 s | — |

## Vaisseaux — rang Supérieur et technologies maximales

Les coûts sont affichés en **Métal / Nano / Antimatière**. La structure est la valeur de PV, suivie du bouclier. La puissance brute par round est une moyenne théorique avant précision et affinité contre la cible.

| Vaisseau | Coût (M / N / AM) | Temps | PV / bouclier | Attaque par tir | Tirs moyens / round | Dégâts bruts moyens / round |
|---|---:|---:|---:|---:|---:|---:|
| Croiseur | 9 942 / 4 419 / 829 | 58 s | 8 914 / 3 566 | 1 784 | 5,03 | 8 976 |
| Corvette | 3 094 / 1 375 / 258 | 20 s | 2 496 / 714 | 570 | 5,03 | 2 870 |
| Vaisseau colonie | 4 419 / 1 964 / 369 | 39 s | 1 784 / 536 | — | — | — |
| Chasseur lourd | 1 326 / 590 / 111 | 8 s | 892 / 358 | 251 | 5,03 | 1 263 |
| Frégate | 4 861 / 2 161 / 406 | 27 s | 3 922 / 1 070 | 785 | 5,03 | 3 949 |
| Destroyer | 6 628 / 2 946 / 553 | 39 s | 5 704 / 2 140 | 1 070 | 5,03 | 5 384 |
| Chasseur | 663 / 295 / 56 | 4 s | 536 / 180 | 144 | 5,03 | 723 |
| Cuirassé | 15 907 / 7 070 / 1 326 | 1 min 37 s | 14 259 / 7 130 | 2 852 | 5,03 | 14 348 |
| Transporteur | 2 652 / 1 179 / 222 | 12 s | 1 426 / 358 | — | — | — |
| Chasseur furtif | 2 696 / 1 199 / 225 | 16 s | 999 / 269 | 214 | 3,77 | 809 |
| Intercepteur | 774 / 344 / 65 | 5 s | 643 / 144 | 125 | 5,03 | 631 |
| Bombardier | 2 652 / 1 179 / 222 | 16 s | 2 140 / 536 | 536 | 3,77 | 2 023 |
| Vaisseau capital | 35 349 / 15 711 / 2 946 | 3 min 51 s | 35 648 / 17 825 | 5 704 | 5,03 | 28 697 |
| Sonde exploratrice | 111 / 50 / 10 | 2 s | 37 / 18 | — | — | — |

### Mobilité et logistique des vaisseaux

| Vaisseau | Précision / suivi | Esquive / signature | Vitesse système / inter-systèmes | Réservoir H | Soute |
|---|---:|---:|---:|---:|---:|
| Croiseur | 171,1 / 102,7 | 57,0 / 15,7 | 180 / 1 784 | 21 389 | 0 |
| Corvette | 159,7 / 159,7 | 91,3 / 7,9 | 180 / 1 784 | 8 914 | 0 |
| Vaisseau colonie | 159,7 / 136,9 | 79,9 / 9,4 | 180 / 1 784 | 7 130 | 0 |
| Chasseur lourd | 148,3 / 166,5 | 114,1 / 11,0 | 180 / 1 784 | 3 566 | 0 |
| Frégate | 159,7 / 143,7 | 79,9 / 11,0 | 180 / 1 784 | 12 477 | 0 |
| Destroyer | 159,7 / 127,8 | 68,4 / 14,1 | 180 / 1 784 | 16 043 | 0 |
| Chasseur | 159,7 / 173,4 | 136,9 / 6,3 | 180 / 1 784 | 2 496 | 0 |
| Cuirassé | 171,1 / 86,7 | 45,6 / 18,9 | 180 / 1 784 | 28 519 | 0 |
| Transporteur | 159,7 / 136,9 | 79,9 / 9,4 | 180 / 1 784 | 10 696 | 178 242 |
| Chasseur furtif | 193,9 / 190,3 | 205,3 / 2,5 | 180 / 1 784 | 4 278 | 0 |
| Intercepteur | 171,1 / 197,1 | 171,1 / 5,7 | 180 / 1 784 | 2 852 | 0 |
| Bombardier | 136,9 / 164,3 | 102,7 / 12,6 | 180 / 1 784 | 6 418 | 0 |
| Vaisseau capital | 182,5 / 59,3 | 34,2 / 22,0 | 180 / 1 784 | 53 474 | 0 |
| Sonde exploratrice | 159,7 / 136,9 | 205,3 / 1,6 | 180 / 1 784 | 57 037 | 0 |

## Défenses — rang Supérieur et technologies maximales

Les PV incluent le blindage structurel et la forteresse maximisés. Les dégâts attendus sont ceux d’un round contre chaque cible prévue, en intégrant la précision, le bonus de dégâts anti-classe et les statistiques de la cible Supérieure maximisée. Pour plusieurs cibles, la dernière colonne donne la plage observée parmi les vaisseaux de la classe.

| Tourelle | Coût (M / N / AM) | Temps | PV / bouclier | Attaque / tir × tirs moyens | Précision contre cible(s) | Dégâts attendus / round |
|---|---:|---:|---:|---:|---:|---:|
| Tourelle au sol anti-léger | 1 125 / 500 / 94 | 7 min 49 s | 3 498 / 340 | 113 × 3,13 | 15,0 %–15,0 %–31,1 %–15,0 % | 69–69–143–69 |
| Tourelle au sol anti-capital | 28 800 / 12 800 / 2 400 | 32 min 49 s | 61 000 / 6 240 | 1 377 × 3,13 | 61,2 % | 3 424 |
| Tourelle au sol anti-escorteur | 4 050 / 1 800 / 338 | 14 min 04 s | 9 760 / 1 426 | 344 × 3,13 | 35,3 %–47,3 %–59,4 % | 493–661–830 |
| Tourelle au sol anti-lourd | 10 575 / 4 700 / 881 | 23 min 27 s | 22 773 / 3 210 | 782 × 3,13 | 57,1 %–69,1 % | 1 814–2 195 |
## Simulation de duel indicative

Un vaisseau Supérieur maximisé contre une seule tourelle de sa classe, 2 000 combats aléatoires par paire, jusqu’à 10 rounds, avec bonus de classe, précision, salves, tirs supplémentaires et recharge de bouclier. Le simulateur reproduit les règles de combat pour comparer les tendances ; le générateur aléatoire n’est pas celui du moteur PHP, donc les pourcentages ne sont pas un résultat reproductible combat par combat.

| Vaisseau | Tourelle prévue | Victoire du vaisseau |
|---|---|---:|
| Intercepteur | Anti-léger | 88,9 % |
| Chasseur | Anti-léger | 93,0 % |
| Chasseur lourd | Anti-léger | 99,3 % |
| Chasseur furtif | Anti-léger | 100 % |
| Corvette | Anti-escorteur | 87,7 % |
| Frégate | Anti-escorteur | 99,4 % |
| Destroyer | Anti-escorteur | 100 % |
| Croiseur | Anti-lourd | 99,1 % |
| Cuirassé | Anti-lourd | 100 % |
| Vaisseau capital | Anti-capital | 100 % |

## Écarts et points à arbitrer

### Priorité haute — les bonus de rang rendent les technologies beaucoup plus puissantes qu’un plafond global

Le plafond de 25 % est appliqué séparément à chaque rang, et le résultat se multiplie. Une technologie de statistique atteint donc +128,1 % au total, pas +25 % ou +75 %. Le rang Supérieur ajoute aussi une chance de tir de 56,25 % par tir de base, en plus des trois rangs de Cycle de tir optimisé. Combiné à +128,1 % d’attaque, le potentiel de tir d’un vaisseau de combat Supérieur est environ **9 fois** celui de la même unité Basique sans recherche, avant affinités et précision. À confirmer comme intention de design.

### Priorité haute — les tourelles ne suivent pas la progression offensive des vaisseaux

Les recherches de précision, de suivi, d’attaque et de tirs supplémentaires ciblent les vaisseaux, pas les défenses. Les défenses Supérieures gagnent toutefois le bonus de tir propre au rang. À l’inverse, les vaisseaux maximisés gagnent fortement en esquive et en signature réduite. Ainsi, la tourelle anti-léger ne touche qu’à **15–31,1 %** ses cibles prévues au rang Supérieur maximisé ; les autres tourelles vont de **35,3 à 69,1 %** selon la cible. Dans les duels indicatifs, le vaisseau prévu bat presque toujours la tourelle seule. Cela ne prouve pas qu’une défense doit gagner en 1 contre 1, mais le rôle « anti-… » perd une grande partie de son effet au maximum.

### Priorité haute — la construction des vaisseaux devient presque instantanée

Avec chantier spatial niveau 25 dans les trois rangs, les réductions composées totalisent 95,9 %. Le Chasseur Supérieur sort en **4 s**, le Chasseur lourd en **8 s**, et le Vaisseau capital en **3 min 51 s**. Vérifier si la réduction doit se cumuler ainsi entre rangs ou si un plafond commun est attendu.

### Priorité moyenne — la précision atteint son plafond contre presque tous les vaisseaux

Sur les 121 affrontements dirigés entre les 11 types de vaisseaux de combat au rang Supérieur maximisé, **111 (91,7 %) atteignent le plafond de touche de 95 %**. Une grande partie des bonus de précision et de suivi ne change donc plus le taux de touche dans ces confrontations.

### Priorité moyenne — la propulsion ne différencie aucun vaisseau

Les 14 définitions partent toutes de 50 en vitesse système et 500 en vitesse inter-systèmes. Avec rang Supérieur et Propulsion vectorielle au maximum, elles atteignent toutes 180 et 1 784. Le choix de vaisseau ne change donc jamais la vitesse de déplacement.

### À vérifier — le réservoir de la sonde dépasse celui du vaisseau capital

Après Réserves cryogéniques maximisées, la Sonde exploratrice a **57 037 H**, contre **53 474 H** pour le Vaisseau capital. La note de conception justifie une grande autonomie de sonde ; ce n’est pas nécessairement une erreur, mais l’écart mérite validation.

## Conclusion

Les données sont cohérentes côté calcul et arrondis, mais plusieurs résultats sont probablement trop extrêmes pour un équilibre final : les bonus cumulatifs entre rangs, le temps de chantier, la précision saturée et les défenses anti-léger. Les deux premières décisions de design à prendre sont le plafond global des bonus technologiques et la façon dont les défenses évoluent face aux technologies offensives.
