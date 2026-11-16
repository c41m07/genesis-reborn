# Étude de la nouvelle grille de coûts proposée

> Étude antérieure à la décision de retenir B40/60, I30/50/20 et S25/35/30/10.
> Les prix désormais retenus sont dans [COUTS.md](COUTS.md). Pour reproduire les
> comparaisons avec leurs anciens prix de référence, utiliser la révision `4b4cd51`.
> Les commandes exécutées sur une autre version lisent le catalogue de cette version.

Mesures du 16 novembre 2026, complément local au commit `0645c6e`.
Dernière proposition utilisateur, en parts de valeur M/N/A/H :

| Rang | Métal | Nano | Antimatière | Hydrogène |
| --- | --- | --- | --- | --- |
| Basique | 40 % | 60 % | 0 % | 0 % |
| Intermédiaire | 30 % | 50 % | 20 % | 0 % |
| Supérieur | 25 % | 35 % | 30 % | 10 % |

Le [profil JSON](PROFIL_COUTS_PROPOSE.json) fixe ces paramètres d’étude. Le
[profil B50 alternatif](PROFIL_COUTS_PROPOSE_B50.json) conserve exactement les mêmes
I/S et remplace seulement le Basique par 50/50. Les valeurs applicatives restent
celles du catalogue courant : ces fichiers ne sont lus que par la commande d’étude.
**Étude conservée en local, sans publication ni activation.**

## Méthode et contrôles

Même moteur, mêmes objectifs, priorités et kits que les [mesures initiales](SIMULATIONS_PROGRESSION_ECONOMIE.md)
et leur [comparaison par rang](SIMULATIONS_COUTS_PAR_RANG.md). Univers ×1, position 5
sauf colonies en positions 1/5/9, sans boost, événement ou vacances de référence.
Paiement au départ, production pendant les opérations, files indépendantes et
chantier commun aux vaisseaux/défenses. Aucun parcours n’est un optimum ou un délai garanti.

Cinq nouveaux scénarios de prix exécutent 270 parcours en comptant les contrôles actuels
répétés. Le [CSV](RESULTATS_GRILLE_PROPOSEE.csv) rapproche neuf grilles, dont les anciennes
références vérifiées : 243 parcours distincts, soit 27 politiques × neuf prix.
Tous terminent. Les contrôles actuels sont strictement identiques aux résultats précédents.
Le bilan de chaque ressource est conservé à moins de 0,000000105 unité, sous la tolérance
0,001 : initial + production − consommation − dépenses − non stocké = final.

Les 156 budgets propres de base sont exactement conservés, sans double facteur de rang,
y compris pour les défenses et le budget impair 1 875 du piège Supérieur. La présence
explicite d’H permet de représenter ce budget avec des quantités entières. Les parts
sont approchées par arrondis conjoints ; la somme des pourcentages doit être 100 et
n’est jamais normalisée silencieusement. Les profils des rangs non sélectionnés restent
strictement inchangés. Les formules de durée, production, carburant et combat ne changent pas.

```bash
mkdir -p output/balance/proposal
make -s console CMD="app:balance:compare-economy --candidate-profile=../docs/PROFIL_COUTS_PROPOSE.json" > output/balance/proposal/all-proposed_all.json
make -s console CMD="app:balance:compare-economy --candidate-profile=../docs/PROFIL_COUTS_PROPOSE_B50.json" > output/balance/proposal/all-proposed_all_b50.json
# Ajouter --candidate-tier=basic, intermediate ou superior pour isoler un rang.
```

Avec un fichier, tous ses rangs sont sélectionnés par défaut ; l’option répétable
`--candidate-tier` permet de les restreindre. Sans fichier, les anciens profils B50/50
et I40/40/20 restent les seuls défauts d’étude ; le Supérieur exige un profil explicite.
Scénarios version 3, PHP 8.4.16. Les mesures de temps CPU concurrentes présentes dans les
résultats bruts ne sont pas utilisées pour une conclusion de performance.

Empreinte de configuration commune : `fd425f965b3af6d457d4968fac02de6a42414cc52343c6ffcca3d92f44b3df69`.

## Comparaison des grilles complètes

L’ancienne recommandation « I40 seul » garde B60/40 et le Supérieur courant.
L’ancienne combinaison garde B50/50, I40/40/20 et le Supérieur courant.
Toutes les durées économiques ci-dessous incluent les achats réellement finançables.

| Parcours, jours | Actuel | I40/40/20 seul | Ancien B50 + I40/40/20 | Proposition complète B40 | Même I/S, Basique B50 |
| --- | --- | --- | --- | --- | --- |
| Économie sans civils | 3,189 | 3,027 | 3,419 | 3,306 | 3,450 |
| Économie avec civils | 3,726 | 3,557 | 3,716 | 4,087 | 3,791 |
| Mixte scientifique/militaire | 10,999 | 10,851 | 11,037 | 11,280 | 10,966 |
| 120 reconnaissances | 32,161 | 32,110 | 32,308 | 32,477 | 32,266 |
| 60 transports | 32,361 | 32,377 | 32,507 | 32,753 | 32,492 |
| Colonie B10, position 1 | 1,805 | 1,805 | 1,548 | 1,744 | 1,548 |
| Colonie B10, position 5 | 2,165 | 2,165 | 1,855 | 2,091 | 1,855 |
| Colonie B10, position 9 | 2,703 | 2,703 | 2,316 | 2,611 | 2,316 |

Avec la proposition complète B40, le développement économique avec civils prend 9,66 %
de plus que les prix actuels, et le mixte 2,55 % de plus. Le parcours sans civils prend
3,68 % de plus. Les colonies B10 progressent 3,41 % plus vite, contre environ 14,3 %
avec B50. Les reconnaissances/transports incluent leur cadence volontaire ; leur durée
n’est pas une mesure universelle d’efficacité de mission.

## Accès aux rangs et constitution militaire

Dates d’accès ci-dessous : parcours économique avec civils, en heures depuis le kit neuf.
Ces dates n’accordent aucune unité ni commande gratuite.

| Accès, heures | Actuel | I40/40/20 seul | Ancien B50 + I40/40/20 | Proposition complète B40 | Même I/S, Basique B50 |
| --- | --- | --- | --- | --- | --- |
| Premier I | 8,75 | 8,75 | 15,35 | 11,33 | 15,35 |
| Premier S | 51,83 | 48,59 | 57,90 | 61,85 | 58,05 |

Les formations suivantes repartent de la même base industrielle déjà développée, issue
du parcours mixte actuel. Leurs délais ne commencent pas à l’inscription. Toutes les
unités demandées et les prérequis manquants sont payés, par lots de dix au plus.
Grande flotte : 500 chasseurs + 50 frégates + 10 cuirassés + 2 capitaux du même rang.

| Formation, heures | Actuel | I40/40/20 seul | Ancien B50 + I40/40/20 | Proposition complète B40 | Même I/S, Basique B50 |
| --- | --- | --- | --- | --- | --- |
| Grande flotte B | 212,76 | 212,76 | 205,40 | 207,62 | 205,40 |
| Grande flotte I | 252,78 | 235,32 | 235,88 | 264,46 | 264,35 |
| Grande flotte S | 305,45 | 305,45 | 305,61 | 339,20 | 339,20 |

La grille complète augmente la formation I de 4,62 % et la formation S de 11,05 %
par rapport aux prix actuels. Les statistiques et résultats physiques des combats
restent les mêmes ; les délais de financement changent. Les budgets de base conservés
ne garantissent pas un délai identique ni une même ressource limitante.

## Isoler chaque rang et comparer B50 à B40

Les autres rangs restent actuels dans les cinq variantes isolées suivantes.

| Grille isolée | Économie sans civils, j | Avec civils, j | Premier I, h | Premier S, h | Grande flotte I, h | Grande flotte S, h |
| --- | --- | --- | --- | --- | --- | --- |
| Actuel | 3,189 | 3,726 | 8,75 | 51,83 | 252,78 | 305,45 |
| B50 seul | 3,420 | 3,726 | 15,35 | 56,92 | 252,66 | 305,61 |
| B40 seul | 3,294 | 4,060 | 11,33 | 59,31 | 252,55 | 305,76 |
| I40/40/20 seul | 3,027 | 3,557 | 8,75 | 48,59 | 235,32 | 305,45 |
| I30/50/20 seul | 2,700 | 3,124 | 8,75 | 43,97 | 264,24 | 305,45 |
| S25/35/30/10 seul | 3,186 | 3,745 | 8,75 | 51,83 | 252,78 | 339,20 |

B40 n’est pas inférieur à B50 sur chaque indicateur : il ouvre I plus tôt dans ce
parcours et termine plus vite l’économie sans civils. B50 est meilleur sur les colonies
et le développement avec civils. La politique choisit le premier objectif finançable ;
les changements de prix peuvent modifier l’ordre des achats et les bonus au paiement.
Les dates ne varient donc pas de façon monotone avec la part de nano.

I30/50/20 seul accélère l’économie de 15,33 % sans civils et de 16,18 % avec civils,
le mixte de 4,62 %, et ouvre S vers 43,97 h contre 51,83 h. En revanche, sa grande
formation I prend 264,24 h, contre 252,78 h actuellement et 235,32 h avec I40/40/20.
C’est un compromis économie/armée, pas une amélioration uniforme.

S25/35/30/10 seul ne change pas les premiers accès I/S, qui précèdent ses achats,
mais augmente la grande formation S de 33 h 45 dans l’expérience militaire.

## Ressources qui limitent les achats

Attentes cumulées par ressource, en heures, pour le développement avec civils :
elles peuvent se chevaucher entre files et ne s’additionnent pas en durée réelle.

| Grille | Métal | Nano | Antimatière | Hydrogène |
| --- | --- | --- | --- | --- |
| Actuel | 112,92 | 0,77 | 0,00 | 0,00 |
| B50 seul | 109,08 | 26,11 | 0,00 | 0,00 |
| B40 seul | 20,11 | 119,13 | 0,00 | 0,00 |
| I30/50/20 seul | 72,46 | 51,49 | 0,23 | 0,00 |
| Proposition complète B40 | 18,64 | 114,08 | 0,00 | 0,00 |
| Même I/S, Basique B50 | 42,78 | 76,38 | 0,00 | 0,00 |

La nouvelle grille B40 déplace fortement l’attente du métal vers le nano. Dans la
formation I, I30/50/20 seul fait passer les attentes nano de 11,94 à 103,05 h ;
I40/40/20 les garde à 6,97 h, avec davantage d’attente d’antimatière.

Sur la grande formation S, le nouveau Supérieur demande 705 459 A et 1 880 184 H,
contre 470 501 A et 940 073 H actuellement. L’attente attribuée à A monte de 63,12
à 227,91 h ; celle de H passe de 0 à 2,40 h. L’antimatière est donc le frein principal
mesuré ici. Doubler la part H des achats ne change pas le carburant des trajets.

## Exemples de prix et reconstruction

Un récolteur ou satellite, au prix de base avant remises et taxe électrique :

| Rang | Prix actuel M/N/A/H | Prix proposé M/N/A/H |
| --- | --- | --- |
| basic | 480 / 160 / 0 / 0 | 320 / 240 / 0 / 0 |
| intermediate | 450 / 225 / 25 / 0 | 300 / 250 / 50 / 0 |
| superior | 374 / 281 / 63 / 124 | 312 / 219 / 94 / 248 |

La reconstruction des défenses B imposées 100/20/10/5 passe de 28,52 h à 24,20 h avec
B40 (25,99 h avec B50). La reconstruction civile avec usines intactes reste à 5,67 h
et les pertes physiques du combat de référence gardent leurs délais proches des valeurs
antérieures. Le CSV contient les autres cas : stocks nuls, stress électrique, petites
flottes, sorties et réparation. Aucun pillage, retour d’attaquant ou secours gratuit
n’est ajouté aux hypothèses de reconstruction.

## Recommandation et limites

La grille complète proposée n’est pas recommandée comme activation générale sur ces
résultats : elle déplace les pénuries vers le nano puis l’antimatière et ralentit
plusieurs objectifs. Elle conserve des avantages ciblés sur la reconstruction B.

Pour un compromis entre développement et armée, conserver B60/40 et le Supérieur actuel,
avec I40/40/20 comme candidat prioritaire, reste la recommandation. Si l’objectif produit
est de privilégier le développement économique au prix d’une constitution militaire I
plus lente, I30/50/20 seul mérite une décision séparée : son gain économique mesuré est
nettement supérieur. Aucune autre répartition non mesurée n’est proposée comme validée.

Avant activation : définir ce compromis, traiter les arrondis des devis et vérifier
d’autres stratégies et positions. Les profils de base sont exacts ; sur les 912 devis
échantillonnés, les écarts après croissance/remises restent jusqu’à 12 unités de valeur.
Les gains ne sont pas des délais garantis, des probabilités de victoire ou une validation
générale de l’équilibre. Les caractéristiques civiles, productions et contres restent
inchangés, ainsi que la limite technique de combat de 5 000 unités.

Les contrôles du complément sont locaux : aucune CI GitHub de cette version, aucune
migration, requalification de base de jeu, modification de prix actif ou livraison Alpha.
Les résultats initiaux publiés restent une référence datée ; cette extension reste locale.
