# Unités économiques — lot 3

Implémentation publiée dans la [PR #71](https://github.com/c41m07/genesis-reborn/pull/71),
fusionnée dans dev au commit `4192821` après CI complète n° 299.
Les prix ci-dessous suivent la décision de répartition du 16 novembre.
Les [lots et contrôles](PROGRESSION_CARBURANT_UNITES.md) distinguent développement,
intégration et activation. Aucun déploiement Alpha.

## Noms et illustrations par rang — 16 novembre 2026

| Rang | Famille solaire | Famille hydrogène |
|---|---|---|
| Basique | Satellite héliocapteur | Récolteur atmosphérique |
| Intermédiaire | Plateforme solaire | Collecteur orbital |
| Supérieur | Couronne héliosolaire | Raffineur d’hydrogène |

Ces noms distinguent les niveaux de conception sans changer les identifiants,
les prix ni les règles des deux familles. Les six illustrations 4:3 suivent la
[direction artistique du catalogue](ILLUSTRATIONS.md). Les termes génériques
« récolteur » et « satellite » ci-dessous désignent leur fonction.

Les cadres verts et les fiches détaillées affichent la production nominale d’une
unité active sur la colonie consultée : position, multiplicateur d’univers et
événement en cours inclus ; zéro pendant les vacances. Cette valeur ne multiplie
pas les effectifs possédés. Les places déterminent les unités actives et le stockage
limite les ressources effectivement créditées.

## Les six profils

Deux familles du catalogue des vaisseaux, chacune avec trois rangs. Elles sont
sans armement, destructibles et présentes dans la défense de leur colonie.
Les unités conservées mais inactives faute de place restent des vaisseaux présents :
l’inactivité de production ne les rend pas invulnérables.

| Famille | Prérequis complets, tous du rang demandé |
|---|---|
| Récolteur d’hydrogène | Chantier 2, raffinerie d’hydrogène 5, Propulseurs vectoriels 1 |
| Satellite solaire | Chantier 2, centrale 5, Génie civil colonial 2 |

Accès initial permanent sur la colonie après acquisition, selon le registre du
lot 1. La possession d’une unité n’accorde pas cet accès. Une unité déjà possédée
peut fonctionner et se déplacer selon ses règles, même si sa construction reste
verrouillée sur la colonie où elle se trouve.

## Prix et construction

Les deux familles partagent les profils suivants, avant réductions :

| Rang | Métal | Nano | Antimatière | Hydrogène industriel | Valeur économique | Durée ×1 | Taxe E |
|---|---|---|---|---|---|---|---|
| Basique | 320 | 240 | 0 | 0 | 1 600 | 180 s | 300 |
| Intermédiaire | 300 | 250 | 50 | 0 | 2 000 | 225 s | 400 |
| Supérieur | 312 | 219 | 94 | 248 | 2 500 | 282 s | 500 |

Valeur = M×2 + N×4 + A×8 + H. Les quantités des profils de rang intègrent déjà
leur budget : aucun second facteur de rang sur ces prix. La [grille retenue](COUTS.md)
remplace les anciens prix pour les commandes futures ; les paiements antérieurs restent conservés.

Le calculateur commun applique les réductions du chantier et d’Architecture
navale, les multiplicateurs d’univers, les plafonds et les arrondis habituels.
Le facteur de durée de rang est appliqué une seule fois. La taxe électrique
utilise la durée effective de la commande entière. Devis, maximum et paiement
suivent le même calcul. Les commandes payées gardent leurs coûts et leur durée.

Un réservoir donne une capacité de carburant ; il n’est pas rempli gratuitement
à la construction. L’hydrogène industriel payé au rang Supérieur reste distinct
du carburant réservé pour une mission.

## Statistiques avant recherches

| Récolteur | Basique | Intermédiaire | Supérieur |
|---|---|---|---|
| Structure | 100 | 150 | 225 |
| Bouclier | 20 | 30 | 45 |
| Attaque / tirs | 0 / 0 | 0 / 0 | 0 / 0 |
| Esquive | 20 | 20 | 20 |
| Signature | 30 | 30 | 30 |
| Soute | 0 | 0 | 0 |
| Réservoir H | 1 600 | 2 000 | 2 500 |
| Production H/h | 900 | 1 350 | 2 025 |

Vitesses identiques au Cargo de ligne du même rang avant recherches. Carburant :
0,05 H par distance normale, 0,20 H par distance hyperspatiale dans les trois
rangs, proportionnel au pourcentage de vitesse. Aucun maintien sur sa colonie.
Les récolteurs se déplacent par Stationnement entre colonies possédées ; aucune
autre mission n’est proposée pour ces unités, même dans une flotte mixte.
Leur soute reste nulle ; les éventuelles marchandises d’une flotte mixte utilisent
les soutes de ses autres vaisseaux.

| Satellite | Basique | Intermédiaire | Supérieur |
|---|---|---|---|
| Structure | 40 | 60 | 90 |
| Bouclier | 0 | 0 | 0 |
| Attaque / tirs | 0 / 0 | 0 / 0 | 0 / 0 |
| Esquive | 0 | 0 | 0 |
| Signature | 40 | 40 | 40 |
| Soute / réservoir | 0 / 0 | 0 / 0 | 0 / 0 |
| Production E/h | 450 | 675 | 1 012,5 |

Satellite explicitement immobile : vitesses nulles, aucun départ ni transfert,
même par envoi direct d’un formulaire. Sa construction reste une commande de
vaisseau avec les réductions correspondantes ; ce classement ne lui donne pas
de déplacement ni de bonus de bunker.

Structure et bouclier des deux familles progressent à ×1 / ×1,5 / ×2,25 avant
recherches. La production utilise ses trois références explicites. Les effets
technologiques applicables conservent les zéros de tir, de soute et de mobilité
du satellite ; le Séquenceur de salves ne crée aucune arme.

## Production

Les références sont celles d’une unité déployée en position 5, univers ×1,
sans événement. S’appliquent ensuite la position de la ressource, la production
de l’univers, les frontières d’événements et de vacances, et le stockage local.
Les unités n’ajoutent aucun entretien horaire.

L’hydrogène et l’électricité vont directement dans les stocks locaux. Ces apports
autonomes ne subissent pas le déficit électrique des usines. Ils entrent dans la
même simulation segmentée : ils peuvent alimenter les consommateurs existants,
sans double crédit au-delà des stockages ni retrait des surplus déjà présents.
Les sources restent identifiées dans les détails des ressources.

Les vacances suspendent leur production selon les périodes enregistrées.
Le plancher industriel reste à 10 %. L’antimatière conserve son besoin d’hydrogène
et peut s’arrêter faute d’apport. L’ensemble ne constitue pas une garantie de
financement ou de reconstruction : les [mesures du lot 4](SIMULATIONS_PROGRESSION_ECONOMIE.md)
donnent des délais pour des états et politiques précis, sans garantie générale.

## Capacités et réservations

Capacités par colonie et par rang, sans conversion entre rangs :

- Récolteurs actifs : un par niveau de raffinerie d’hydrogène du même rang.
- Satellites : deux par niveau de centrale du même rang.

Les récolteurs à quai sont activés automatiquement dans les places disponibles.
Une construction de récolteur peut dépasser ces places : les excédents restent
conservés et inactifs. Sa file de construction ne promet pas une affectation.
Les satellites en file réservent chacun une place ; leur maximum de construction
tient compte des satellites présents et des commandes déjà acceptées.

Les arrivées de récolteurs réservées et les constructions de satellites ont
priorité sur une nouvelle activation à quai. Une baisse de capacité conserve
les unités et paiements : les réservations effectives sont bornées par le
nouveau plafond, les excédents attendent et les unités sans place ne produisent
pas. La remontée de capacité permet leur réactivation, sans production rétroactive.

| Transition | Places et production |
|---|---|
| Fin de construction | Ancien effectif matérialisé jusqu’à la fin enregistrée ; ajout des unités, libération de la réservation de satellite, activation dans les places restantes |
| Attribution administrative | Droits, propriétaire, reçu et audit existants ; commandes échues livrées, ancien effectif soldé avant ajout, production à partir de la correction, excédents conservés inactifs et renfort local commun |
| Annulation | Remboursement habituel sur paiement enregistré ; retrait de la commande et libération de sa réservation, sans second effet au rejeu |
| Départ de récolteurs | Vérification de toutes les places demandées à destination ; production d’origine soldée avant retrait ; place d’origine libérée et destination réservée dans la même transaction |
| Vol ou attente | Aucune production des unités en mouvement ou en attente de nouvelle base |
| Installation | Production antérieure soldée, réservation libérée et unités ajoutées au stock local ; production à partir de l’installation effective |
| Rappel | Ancienne réservation libérée ; réservation de retour limitée aux places encore disponibles, excédents conservés inactifs à l’arrivée |
| Destination perdue | Aucune réservation chez le nouveau propriétaire ; repli de Stationnement habituel, avec réservation des places disponibles sur une colonie possédée |
| Reprise volontaire | Propriété, univers, carburant et places revérifiés à la confirmation ; refus expliqué si l’affectation n’est plus possible |
| Destruction | Application des pertes à la clôture du combat ; fin de production et libération des places des unités effectivement retirées |

Les rappels et replis gardent les profils de carburant figés du lot 2. Ils ne
créent aucune ressource ni place et ne détruisent aucune unité pour résoudre un
manque de capacité. Les réservations reposent sur la navigation existante et
ne représentent pas une seconde copie des vaisseaux.

## Combat et renforts locaux

Décisions explicites complémentaires : coefficient neutre ×1 pour les tirs visant
ces unités, application des pertes et arrêt de production à la clôture du combat.
L’engagement seul ne suspend pas leur production. Les unités ne tirent pas,
restent ciblables et ne bénéficient d’aucune évacuation automatique.

Les livraisons du chantier et du Stationnement pendant une fenêtre de combat
ouverte raccordent la flotte locale au mécanisme existant de renfort. Cette
règle commune concerne aussi les autres vaisseaux présents à quai. La flotte,
ses vaisseaux et son carburant embarqué sont conservés, sans déplacement fictif
du satellite ni duplication dans une affectation séparée.

Une livraison de file conserve sa date de fin payée pour déterminer son round
éligible. Les renforts locaux ne réécrivent aucun round déjà résolu. Une unité
achevée avant une attaque reçue en retard reste éligible au premier round ;
une livraison traitée après plusieurs rounds rejoint au plus tôt le suivant.

Inventaires et rapports utilisent les mêmes unités et libellés. Le simulateur
permet de les saisir côté Défenseur ; l’importation des forces offensives et
les champs Attaquant les excluent. Leurs signatures et leur nombre peuvent
absorber des tirs destinés aux autres défenseurs : les [essais de masse](SIMULATIONS_PROGRESSION_ECONOMIE.md#masse-civile-et-combat)
le mesurent, sans bénéfice économique démontré à budget comparable ni nouveau
coefficient de contre. Le moteur refuse actuellement plus de 5 000 unités par combat.

## Architecture et activation

`EconomicUnitCapacity` lit les stocks locaux, les stationnements entrants et la
file locale. Les données préparées sont réutilisées entre rangs et segments de
production. `EconomicUnitDeployment` matérialise l’ancien état avant de changer
les réservations. Les transactions et verrous de participation/univers/planète
existants rendent ces contrôles autoritaires.

`LocalGarrison` raccorde les livraisons aux renforts du moteur de combat. Une
fermeture de service Symfony évite le cycle de construction entre files et
combats ; aucun nouveau format de message différé n’est nécessaire.

Aucune colonne ni migration SQL nouvelle. Les réservations sont des données
additionnelles de la navigation JSON ; une réservation devenue obsolète par
statut, destination ou propriétaire ne consomme plus une place. Code web et
workers doivent utiliser la même version avant activation. La requalification
des accès du lot 1 reste une opération séparée soumise à autorisation et
sauvegarde vérifiée sur une base existante.

Deux pictogrammes SVG originaux de famille sont fournis, partagés par leurs
trois rangs : `hydrogen_collector.svg` et `solar_satellite.svg`. Les logos et SVG
d’identité existants sont conservés.

## Vérification et limites

Les tests ciblés couvrent les six profils, réductions, devis/paiements, rangs,
stockages, événements/vacances, production sans entretien, commandes historiques,
transferts/rappels/replis, unités inactives, combat, renforts, retards et rejeux.
Deux connexions réellement bloquées simultanément sur le verrou de participation
vérifient les dernières places, en construction et en transfert.

Les résultats complets locaux et la CI du lot sont consignés dans le
[suivi de reprise](PROGRESSION_CARBURANT_UNITES.md). Les vérifications ciblées
d’accessibilité ne constituent pas un audit RGAA complet. Les [simulations du lot 4](SIMULATIONS_PROGRESSION_ECONOMIE.md)
complètent ces tests fonctionnels par des mesures financées. Leurs politiques,
états initiaux et limites ne valident aucun avantage économique global garanti.
