# Simulations de progression et d’économie

> Étude antérieure à la décision de retenir B40/60, I30/50/20 et S25/35/30/10.
> Les prix désormais retenus sont dans [COUTS.md](COUTS.md). Pour reproduire les
> comparaisons avec leurs anciens prix de référence, utiliser la révision `4b4cd51`.
> Les commandes exécutées sur une autre version lisent le catalogue de cette version.

Mesures du 16 novembre 2026, après intégration des lots 1 à 3 dans dev (`4192821`).
Ce rapport compare le système courant et une variante de prix **inactive**.
Il ne décide ni une activation du candidat ni un déploiement Alpha.
Le [complément par rang](SIMULATIONS_COUTS_PAR_RANG.md) isole ensuite B50/50 et
I40/40/20 : il distingue leurs effets et actualise la recommandation, sans activation.

## Reproduire les résultats

```bash
mkdir -p output/balance
make -s console CMD="app:balance:compare-economy --section=all" > output/balance/progression-economie.json
```

La commande produit du JSON et écrit sa progression sur la sortie d’erreur.
`--section=costs|economy|military` limite l’étude ; `--trace` conserve les opérations
individuelles des parcours économiques. Aucun compte, stock ou univers de jeu n’est lu
ou modifié. Les univers comparés sont créés en mémoire. Aucun prix candidat n’est enregistré.
Le fichier brut local est ignoré par Git ; les résultats ci-dessous et la
[comparaison des 156 profils](COMPARAISON_COUTS_CANDIDATS.csv) sont versionnés.

Empreinte SHA-256 de la configuration : `fd425f965b3af6d457d4968fac02de6a42414cc52343c6ffcca3d92f44b3df69`.
Version des scénarios : 1. Mesure complète : 17,35 s sur PHP 8.4.16
sous WSL Debian ; ce temps dépend de la machine et ne mesure pas les accès HTTP/SQL.

## Méthode et limites communes

- Univers ×1, position 5 sauf mention contraire, aucun boost, événement ni vacances
  dans les parcours de référence. Le kit neuf vaut 10 000 de chaque ressource ;
  une colonie reçoit 5 000 de chacune sauf électricité 0, dans ses capacités réelles.
- Prérequis, accès permanents, devis, durées, bonus acquis, places civiles,
  consommation industrielle, plafonds et simulation segmentée utilisent les services réels.
  Les flux cumulés sont mesurés dans ces mêmes segments, sans autre formule de production.
- Politique déterministe : priorité aux premiers objectifs finançables, paiement au départ
  seulement, un bâtiment, une recherche et une commande du chantier en parallèle ;
  vaisseaux et défenses partagent ce chantier. Une seule flotte de mission est réutilisée.
  Aucun achat anticipé dans une file occupée, aucune recherche d’optimum.
- Développement économique : centrale, métal, nano, hydrogène et laboratoire entrelacés,
  jusqu’à B20, I20 et S5 ; piège jusqu’à B10/I10/S3, chantier au moins 2.
  Variante civile : ajouts de 5/5/10 récolteurs et 10/10/20 satellites aux étapes 5/10/20,
  dans chaque rang atteint. Les prérequis et stockages nécessaires sont réellement financés.
- Les accès sont datés dès que leurs conditions sont acquises, indépendamment du financement
  et de la première construction. Les trois notions restent distinctes dans le JSON.
- Chaque devis est figé lors de son paiement. Les bonus gagnés ensuite ne raccourcissent
  pas une opération acceptée. La projection respecte les stocks excédentaires existants.
- Les 54 parcours financés terminent. Pour chaque ressource : stock final = stock initial
  + production − consommation − dépenses − non stocké, avec écart inférieur à 0,001 unité.
  « Non stocké » désigne un débit potentiel non crédité au plafond, jamais un stock possédé.
- Les attentes par ressource peuvent se chevaucher entre files. Elles ne s’additionnent
  pas en durée réelle. L’occupation, l’attente de financement et celle des dépendances/cadences
  sont séparées dans les résultats.
- Les expériences militaires repartent du bilan industriel et technologique du parcours
  mixte courant, identique pour les deux prix. Ce capital initial est donné et signalé ;
  leurs délais ne sont pas des temps depuis une inscription.
- Les autorisations de destination, l’absence d’interception et la place du fret livré
  sont des hypothèses. Les tests fonctionnels des lots précédents couvrent les contrôles serveur.
  Les scénarios ne modélisent ni adversaire adaptatif, ni marché, ni multi-colonies concurrentes,
  ni tous les usages possibles du carburant. Une période de vacances qui interrompt un vol
  est rejetée plutôt que de figer fictivement sa navigation. Les calendriers fournis sont des périodes
  expérimentales explicites, sans simulation de l’éligibilité ou du délai du mode vacances.

## Comparaison de répartition des coûts

Pondération : V = 2 M + 4 N + 8 A + H. Il ne s’agit pas d’un taux d’échange.
Le candidat vise B 50/50 et I 40/40/20 en **parts de valeur**, S inchangé.
Les 156 budgets de base effectifs sont conservés exactement, y compris ceux des défenses,
sans nouvelle multiplication de rang. Exemple civil : B 400 M + 200 N ; I 400 M + 200 N + 50 A.
L’écart maximal aux parts cibles dû aux quantités entières est de 0,267 point.

Sur 912 devis réels, après croissance, quantités et remises, l’arrondi supérieur par ressource
introduit encore un écart de V allant jusqu’à 12 unités (bunker I10 avec remise maximale : −12).
Les durées et taxes E restent identiques à bonus égal. **Ce candidat ne garantit donc pas
une conservation exacte de chaque futur paiement arrondi** : cette question doit être réglée
avant une éventuelle activation. Aucun coût déjà payé n’est recalculé.

## Parcours depuis les kits réels

Durées en jours de 24 h, avec financement et opérations, pour les objectifs annoncés.

| Politique | Actuel (j) | Candidat (j) |
| --- | --- | --- |
| Économie sans civils | 3,189 | 3,419 |
| Économie avec civils | 3,726 | 3,716 |
| Mixte science/militaire | 10,999 | 11,037 |
| 120 reconnaissances | 32,161 | 32,308 |
| 60 transports | 32,361 | 32,507 |
| Colonie position 1, B10 | 1,805 | 1,548 |
| Colonie position 5, B10 | 2,165 | 1,855 |
| Colonie position 9, B10 | 2,703 | 2,316 |

Premiers accès de rang, en heures, sans achat automatique d’unité :

| Politique | Actuel I / S (h) | Candidat I / S (h) |
| --- | --- | --- |
| Économie sans civils | 9,24 / 42,09 | 12,05 / 54,30 |
| Économie avec civils | 8,75 / 51,83 | 15,35 / 57,90 |

La variante civile demande davantage d’investissements ; elle n’accélère pas ce parcours
économique complet. Le candidat accélère les colonies B10 d’environ 14 %, mais retarde
les premiers accès I/S des deux parcours économiques. Il ne résout donc pas globalement
la progression par une simple redistribution de prix.

Les reconnaissances emploient une sonde neuve, consommée à l’arrivée, toutes les six heures
au plus tôt après le précédent départ (distance normale 50 + hyperspatiale 100).
Les transports réutilisent deux cargos, avec 1 000 M + 500 N livrés, vitesse 50 %, distance
100 + 100, espacés d’au moins douze heures. Leur durée totale inclut cette cadence imposée.
Le JSON donne aussi l’accès et le premier départ pour chaque unité utile.

Attentes attribuées au métal / nano, en heures, pour les parcours économiques :

| Politique | Actuel M / N | Candidat M / N |
| --- | --- | --- |
| Économie sans civils | 44,31 / 0,00 | 49,78 / 31,41 |
| Économie avec civils | 112,92 / 0,77 | 111,91 / 33,41 |

Le métal reste limitant ; le candidat donne aussi un débouché au nano et augmente les
attentes associées. Les plafonds, productions et prix réels expliquent ce résultat ;
les valeurs n’ont pas été ajustées pour obtenir un délai souhaité.

### Accès et première livraison utile

Heures depuis le kit neuf : accès / fin du premier lot. Chasseur, frégate, cuirassé,
capital et colonisateur utilisent la politique mixte ; sonde et cargo leur politique
de missions ; civils la politique économique avec civils. Le premier lot peut
contenir plusieurs unités : aucune livraison partielle n’est anticipée. La priorité
productive choisie retarde ici le chantier et les premiers chasseurs ; les 58 h
observées ne sont pas un minimum imposé par la grille à un joueur privilégiant l’armée.

| Unité | Actuel : accès / livraison (h) | Candidat : accès / livraison (h) |
| --- | --- | --- |
| Chasseur B | 58,34 / 83,79 | 61,99 / 63,66 |
| Sonde B | 57,81 / 57,85 | 61,33 / 61,36 |
| Cargo B | 58,36 / 64,11 | 61,93 / 67,62 |
| Colonisateur B | 134,22 / non construit | 136,24 / non construit |
| Récolteur B | 58,36 / 59,12 | 61,88 / 62,63 |
| Satellite B | 58,36 / 58,60 | 61,88 / 62,12 |
| Frégate B | 138,67 / 139,75 | 139,74 / 140,81 |
| Cuirassé B | 170,83 / 179,41 | 171,90 / 180,48 |
| Capital B | 245,10 / 262,42 | 246,17 / 263,33 |

Le colonisateur est accessible mais pas construit par la politique mixte : le temps
de recherche seul ne constitue pas une première colonisation financée.

### Recherche longue et file commune

Les Protocoles de colonisation B1 restent à 80 h brutes. Dans le parcours mixte courant,
ils sont payés à 73,02 h, prennent 61,20 h avec le laboratoire alors acquis et coûtent
367 300 E. La recherche suivante commence exactement à leur fin, à 134,22 h.
Avec le candidat : paiement à 75,96 h, durée 60,29 h, coût 361 800 E, fin à 136,24 h.
La différence vient des bonus disponibles au paiement ; aucune commande n’est recalculée.
Le laboratoire minimal B2 donne toujours 77 h 54 min 17 s et 467 500 E, comme au lot 2.

## Flottes, cadence, taxe électrique et carburant

Petite flotte : 20 chasseurs + 2 cargos. Grande flotte : 500 chasseurs + 50 frégates
+ 10 cuirassés + 2 capitaux. Tous les vaisseaux d’une expérience sont du même rang.
Les constructions suivantes sont nouvelles et payées depuis le bilan industriel donné.

| Formation | Actuel (h) | Candidat (h) |
| --- | --- | --- |
| small_basic | 1,43 | 1,43 |
| small_intermediate | 1,79 | 1,79 |
| small_superior | 2,24 | 2,24 |
| large_basic | 212,76 | 205,40 |
| large_intermediate | 252,78 | 235,88 |
| large_superior | 305,45 | 305,61 |

Pour la grande flotte B, retirer les civils du même état initial ne change pas le délai
(212,76 h avec les prix actuels) : métal/nano dominent ce parcours. Les prérequis ou recherches
supplémentaires des formations sont financés, et leurs bonus finaux servent au devis de vol.

Sur l’état technologique de référence fixé, distance normale 100 + hyperspatiale 1 000,
vitesse 100 %, deux heures d’orbite autorisée :

| Flotte B | Carburant actuel / ancien (H) | Taxe formation actuelle / ancienne (E) | Cycles/j max |
| --- | --- | --- | --- |
| small | 9 841,44 / 90 213,20 | 8600 / 12900 | 3,17 |
| fast_wing | 205 030,00 / 2 050 300,00 | 107300 / 107300 | 4,13 |
| large | 319 846,80 / 2 304 537,20 | 1150000 / 3213700 | 2,58 |

Ces cycles sont des maxima cinématiques, sans financement, combat ni pertes. Les 36 devis
couvrent trois rangs, 100/50 % et 0/2 h de maintien. L’ancien carburant et les anciennes durées
viennent de l’archive immuable du lot 2, à bonus identiques. Le JSON indique explicitement
si chaque devis tient dans les réservoirs. Une division de taxe n’est pas une division
équivalente du délai de financement. Ces devis fixes utilisent un lot par classe ;
les parcours financés ci-dessus achètent par lots de dix au plus et incluent les
prérequis manquants. Les arrondis électriques et temps ne sont donc pas interchangeables.

### Hydrogène : surplus et besoin militaire

| Parcours courant | H produit | H consommé par usines | Carburant H | H non stocké |
| --- | --- | --- | --- | --- |
| Économie avec civils | 1 136 637 | 136 432 | 0 | 982 278 |
| 120 reconnaissances | 38 755 070 | 136 432 | 1 080 | 38 599 631 |
| 60 transports | 39 011 928 | 136 432 | 6 000 | 38 851 569 |

Ces petites missions n’absorbent pas l’apport des récolteurs du parcours choisi. Le piège
cesse de consommer H lorsque son stockage A est plein. Un débit H non crédité élevé
ne signifie ni production infiniment stockable ni carburant offert à toute flotte.

Contre-épreuve : après la grande formation B ou S, douze sorties de 500 chasseurs,
distance 100 + 1 000, vitesse 100 %, deux heures d’orbite chacune, sans nouveau combat.
La flotte revient entre chaque sortie. Les 2 460 360 H de carburant et les stockages
supplémentaires nécessaires sont financés ; les autres industries restent identiques.

| Rang | Avec civils (h) | Sans civils (h) | Attente H avec / sans (h) |
| --- | --- | --- | --- |
| basic | 71,73 | 74,19 | 1,92 / 4,37 |
| superior | 52,30 | 55,39 | 0,00 / 3,09 |

Les résultats de ces sorties sont identiques avec les deux prix. Un besoin militaire
soutenu réduit le surplus et réintroduit de l’attente H ; les récolteurs gardent ici un
intérêt mesuré. Aucune conclusion universelle de sous-consommation n’est retenue.

### Transfert de récolteurs

Cinq récolteurs B quittent la référence : la source passe de 55 125 à 50 625 H/h civils.
Ils ne produisent ni pendant le vol ni à destination avant l’arrivée. Une colonie possédée
avec raffinerie B5 reçoit 3 600 / 4 500 / 5 400 H/h aux positions 1 / 5 / 9.
Les trajets durent 5 319 / 3 991 / 2 663 s, et coûtent 270 / 250 / 230 H réservés.
À destination, 135 / 125 / 115 H restent embarqués, sans dépôt fictif dans le stock local.

## Masse civile et combat

Attaque de 40 chasseurs contre 10 chasseurs + 10 anti-légers, recherches de référence
identiques, graines fixes 7, 19, 42, 73, 101. Les civils sont donnés, leur budget est
chiffré séparément. Les satellites respectent les places réelles ; les récolteurs excédentaires
restent légalement présents, inactifs économiquement, mais ciblables au combat.

| Civils B ajoutés | Victoires défenseur / 5 | Tirs civils | Temps moteur, 5 graines (s) |
| --- | --- | --- | --- |
| 0 | 1 | 0 | 0,037 |
| 20 | 4 | 0 | 0,049 |
| 200 | 5 | 0 | 0,142 |
| 2000 | 5 | 0 | 1,162 |

Ces décomptes ne sont pas des probabilités de victoire. Ajouter gratuitement des cibles
change la bataille, mais aussi fortement la valeur investie. Le contrôle à budget proche
remplace des chasseurs par 14 civils : V 103 838 contre V 105 306 de référence
(reliquat 1 468, soit 1,39 %, taxe E distincte). La défense gagne 0/5 au lieu de 1/5.
L’effet de masse seul ne démontre donc pas une meilleure efficacité économique.

Les essais de rang avec 200 civils donnent : B contre I ou S, défense 5/5 ; I contre B,
défense 5/5 ; S contre B, attaque 5/5. Aucun nouveau coefficient de contre n’est ajouté.
Sur 200 graines de premier ciblage, dix récolteurs et dix satellites reçoivent respectivement
86 et 114 premiers tirs ; leurs signatures sont 30 et 40. Les unités civiles ne tirent jamais.

Le temps moteur augmente avec la masse : environ 1,162 s pour cinq combats avec 2 000 civils
contre 0,037 s sans civils sur cette machine. Le supplément de mémoire allouée mesuré est 0
au niveau des pages PHP déjà réservées ; cela ne signifie pas une mémoire utilisée nulle.
Lors de cette mesure historique, **5 060 unités étaient refusées** par le plafond
technique de 5 000 unités et le simulateur était limité à 500. Ce n’étaient pas
des limites de possession. Le moteur compact dépasse désormais ce plafond :
le test de volume vérifie que les 5 060 unités fournies sont toutes comptées,
sans modifier les valeurs ni les règles étudiées. Les chronos ci-dessus ne
mesurent pas ce nouveau moteur.

## Reconstruction et socle au sol

Les pertes civiles sont appliquées à la clôture à +600 s, avec production jusqu’à cette date.
Le délai de reconstruction ci-dessous commence ensuite. Usines intactes, sans pillage :

| Reconstruction | Actuel (h) | Candidat (h) |
| --- | --- | --- |
| Tous les civils de référence | 5,67 | 5,67 |
| Même perte, stocks imposés à zéro | 5,76 | 5,75 |
| Stress : centrale B1, attente | 64,08 | 64,07 |
| Stress : centrale B36 financée d’abord | 31,92 | 27,17 |
| Défenses 100/20/10/5 sans réparation | 28,52 | 25,99 |
| Même perte, après réparation B5 | 25,88 | 23,46 |
| Pertes offensives du combat graine 7 | 1,43 | 1,43 |
| Pertes défensives du même combat | 1,68 | 1,68 |

L’état de stress est imposé : stocks nuls, civils détruits, centrale réduite à B1,
autres infrastructures et accès conservés. Objectif limité à deux satellites et un
récolteur B. Le rendement initial observé vaut 12,51 % ; le plancher reste 10 %.
Financer d’abord la centrale B36 coûte beaucoup plus de métal/nano, mais réduit ici
le temps de reprise. Ce choix n’est pas un optimum ni un secours gratuit.
Un état sans centrale, électricité et satellite peut rester bloqué : le test conserve
ce résultat, sans création gratuite de ressource. L’absence absolue de blocage n’est pas démontrée.

Les quatre types de défenses restent dans le chantier commun. La réparation B5 réduit
la reconstruction à 25,88 h, mais il faut ajouter 3,92 h et 23 800 E pour financer
et terminer cette recherche avant la perte ; la préparation n’est pas gratuite.
Le JSON sépare cette préparation dans les deux variantes. Les réparations automatiques
sont arrondies par type avec le moteur courant. La comparaison offensive/défensive issue
à la graine 7 conserve les survivants et les pertes réelles : 1,43 h contre 1,68 h
avec les prix actuels, sur un combat gagné par la défense, sans butin.
Le retour des attaquants n’est pas inclus dans ce chronomètre de remplacement.

### Usines et places face aux unités destructibles

Au bilan de référence, gains nominaux à énergie disponible et hors plafonds :

| Producteur / prochain niveau | Gain au sol / h | Places nouvelles et gain civil / h |
| --- | --- | --- |
| mine_hydrogen basic 21 | 720 | 1 × hydrogen_collector : 900 |
| mine_hydrogen intermediate 21 | 1 080 | 1 × hydrogen_collector : 1 350 |
| mine_hydrogen superior 6 | 1 620 | 1 × hydrogen_collector : 2 025 |
| power_plant basic 21 | 1 080 | 2 × solar_satellite : 900 |
| power_plant intermediate 21 | 1 620 | 2 × solar_satellite : 1 350 |
| power_plant superior 6 | 2 430 | 2 × solar_satellite : 2 025 |

Les devis M/N/A/H, E et durées de ces améliorations et de leurs unités figurent dans le JSON.
Le gain nominal industriel demande son entretien et n’est pas un gain garanti en déficit.
L’usine donne à la fois un socle durable et les places civiles ; les unités ajoutent un apport
sans entretien mais exposé au combat. Les faibles prix marginaux des civils n’annulent
ni leur perte possible ni la nécessité des usines pour les places et la reprise.

## Décisions et activation restantes

- Le candidat réduit certains délais B et les formations I testées, mais ralentit des accès
  avancés et n’améliore pas tous les parcours. **Aucune activation n’est décidée.** Une décision
  séparée doit accepter/refuser le compromis et régler les écarts de valeur après arrondi.
- Le surplus H observé doit être confronté aux usages Alpha, aux grandes flottes répétées
  et aux positions ; aucune baisse arbitraire de rendement ou hausse de carburant n’est appliquée.
- La masse de civils peut modifier le ciblage et atteindre le plafond technique de combat.
  Le contrôle à valeur proche et les cinq graines ne suffisent pas à décider un nouveau contre.
- Les résultats déterministes sont reproductibles pour ces politiques, sans délai garanti
  pour tous les joueurs, ni validation générale de l’équilibre ou de la capacité serveur.
- Aucune migration SQL nouvelle. Pour les accès historiques, suivre la sauvegarde et la
  requalification explicitement autorisée du [guide de bascule](DEBLOCAGES.md).
  Ni cette reprise sur une base de jeu, ni un déploiement Alpha ne sont réalisés par l’étude.

Les règles et le wiki local restent ceux des lots intégrés ; les chiffres de cette étude
sont un outil de décision et ne deviennent pas une promesse de délai destinée aux joueurs.
Aucun wiki hébergé n’est déclaré synchronisé.
