# Rework V1 — suivi et reprise

Première passe validée localement le 15 novembre 2026, sur le code applicatif
`6dd33b7`. Le chantier global reste ouvert : voir les exceptions et la recette
manuelle ci-dessous. Aucun push, PR, fusion ou déploiement effectué.

## Cadre

- Branche unique : `chore/rework-v1`, créée depuis `origin/dev` actualisée à `48c9a71`.
- Arbre initial propre ; huit worktrees existants recensés et conservés.
- Douze lots conservés. Aucun push, PR, fusion, déploiement ou changement d’équilibrage.
- Barre de ressources exclue : endpoint, calculs, templates et fréquence inchangés. L’extension de ResourceService pour les cartes est optionnelle et n’est pas utilisée par le bandeau.
- Aucune dépendance nouvelle, migration ou suppression prévue.
- Tests PHP sur `database-test/genesis_v2_test` exclusivement ; tests navigateur dans un projet jetable distinct.
- Ne pas exécuter deux suites PHP en parallèle : elles reconstruisent le même schéma synthétique.

## Sources

Consignes racine présentes et lues ; aucune consigne imbriquée trouvée dans les
répertoires applicatifs, scripts et documentation. README, index, architecture,
feuille de route, règles et décisions consultés pour cadrer les domaines ; leurs
longs historiques ne constituent pas une preuve de l’état courant.
Les consignes supplémentaires de `docs/conventions-code-architecture:AGENTS.md`
ont aussi été examinées : elles sont présentes sur cette branche locale, pas
dans `dev`. Leurs principes figurent dans le prompt du chantier ; aucun commit
ou fichier de cet autre chantier n’a été intégré silencieusement. Leur application
au code ancien reste ciblée, sans revendication de conformité exhaustive.
Les documents Work suivants n’ont pas été trouvés dans le dépôt ni dans les
pièces jointes accessibles ; ils n’ont pas été lus :

- `GENESIS_REBORN_PROMPT_MAITRE_WORK_V4_COMPLET.md` ;
- `GENESIS_REBORN_BRIEF_FIGMA_WORK_V2_COMPLET.md` ;
- `GENESIS_REBORN_PROTOCOLE_DEVELOPPEMENT_ADAPTATIF_WORK_V1.md`.

Le wiki embarqué est `docs/wiki.json` ; aucune source distante n’a été synchronisée.

## Inventaire initial

Inventaire structurel : 45 contrôleurs, 130 fichiers de services, 3 repositories,
1 manager, 7 handlers, 161 templates, 51 contrôleurs Stimulus, 209 fichiers de
tests et 15 scripts. Dépendances, fichiers générés, médias d’identité, autres
worktrees et archives historiques exclus des transformations mécaniques.

| Lot | Domaine et points d’entrée | Constat initial | État |
| --- | --- | --- | --- |
| 1 | Inventaire, mesures, isolation | Référence et garde de base nécessaires | Socle ciblé validé, inventaire détaillé à compléter |
| 2 | Conventions et lisibilité | ColonyView très condensé ; aucun formateur PHP installé | Dépendances clarifiées sur le périmètre prioritaire |
| 3 | ColonyController, ColonyView, CatalogueInsights | Préparation ciblée et quantités regroupées | Implémentation et tests ciblés validés ; recette finale à faire |
| 4 | DiplomacyController, GuildProjectController | Onglet validé avant listes ; dons personnels séparés du détail | Tests ciblés validés |
| 5 | Rapports, classements, profils, messages | Rapports de page et contacts regroupés ; comptage SQL de guilde | Tests ciblés validés, exceptions documentées |
| 6 | Carte, planètes, destinations, flottes | Mondes découverts et compositions privées regroupés | Tests ciblés validés |
| 7 | AdminController et historiques | Compteurs SQL, aperçus bornés, préparations par écran | Tests ciblés validés ; pagination encore ouverte |
| 8 | colony-live, fragments | Fragments partagés, réponses obsolètes ignorées | Tests ciblés validés |
| 9 | Assets, HTML, accessibilité | Fragments strictement identiques ; entrée compilée 494 Kio | Contrôles automatiques locaux validés ; recette manuelle ouverte |
| 10 | Comptes, boosts, événements, handlers, exploitation | Sept contrats revus ; lectures du simulateur regroupées | Revue ciblée et tests validés ; concurrence réelle non démontrée |
| 11 | Documentation et écarts transversaux | Index réparé, documentation et relecture des changements | Effectué sur le périmètre modifié |
| 12 | Contrôles et recette cumulée | Six contrôles, mesures comparables et parcours manuels requis | Contrôles locaux du périmètre livré validés ; recette et exceptions ouvertes |

Cet inventaire recense les domaines, sans prétendre à une revue intégrale de
chaque fichier. Un domaine non vérifié reste explicitement ouvert.

## Référence de performance

`ColonyPreparationTest` construit un univers, un joueur, une colonie position 5,
un laboratoire niveau 2, une recherche niveau 1, une flotte au sol contenant
7 chasseurs et 3 défenses. Cinq ressources initialisées, aucune commande,
aucun événement ni combat. Horloge fixée au 14 novembre 2026 à 12 h UTC.
PHP 8.4.26, Symfony test/debug, MariaDB 11.8, conteneurs locaux existants.
Collecteur Doctrine existant, avec instrumentation de débogage conservée avant/après.
Mesure du service seul : aucun rendu Twig, HTTP, transfert ou navigateur inclus.
Premier appel séparé de dix appels chauds dans le même processus et la même
identity map ; caches applicatifs existants, sans remise à zéro entre appels.

Activation : `docker compose --env-file .env.local exec -T --user 1000:1000 -e REWORK_MEASURE=1 app php bin/phpunit tests/Integration/ColonyPreparationTest.php`.
Ne pas lancer cette mesure pendant `make test`.

| Page/service | Scénario | SQL avant | SQL après | Temps avant | Temps après | Mémoire | Résultat fonctionnel |
| --- | --- | --- | --- | --- | --- | --- | --- |
| ColonyView (toutes pages avant ciblage) | Premier appel | 471 | non mesuré | 218,27 ms serveur / 103,39 ms SQL | non mesuré | 52,5 Mio | 150 cartes, quatre catalogues |
| ColonyView (toutes pages avant ciblage) | Dix appels chauds | 468 ; médiane | non mesuré | 238,98 ms serveur [222,74–321,23] ; SQL 111,31 ms [104,43–135,42] | non mesuré | 56,5 Mio alloués, pic 56,5 Mio | Stocks, défenses et état économique caractérisés |

La référence historique d’environ 520 requêtes n’est pas reproduite à l’identique :
la fixture et le périmètre sont différents. Les 468 requêtes concernent le service
isolé, pas une page HTTP complète. Aucun pourcentage global n’est déduit.

## Comparaison stabilisée des préparations

Deux projets PHP jetables utilisant la même image et la même fixture ont été
mesurés successivement : avant, le fichier ColonyView de `48c9a71` est monté
en lecture seule dans le seul conteneur de référence ; après, le fichier courant.
Aucun fichier applicatif du checkout n’a été remplacé pour mesurer l’ancien code.
Mesure finale du 15 novembre, après les suites : caches Symfony préchauffés
avant chaque série. Pour chaque scénario : un premier appel du service, puis
dix passages chauds. Tableau des médianes et étendues serveur en millisecondes ;
allocations mémoire du processus
(cumulatives entre scénarios), pas mémoire HTTP par visite. Instrumentation de
débogage identique. Les autres services sont ceux du checkout courant ; avec
la fixture sans flotte active, les changements de compositions ne modifient pas
le travail comparé. Valeurs de transfert, rendu et temps HTTP : non mesurés.

| Page | Scénario | SQL avant | SQL après | Temps avant | Temps après | Mémoire avant → après | Résultat fonctionnel |
| --- | --- | --- | --- | --- | --- | --- | --- |
| Vue générale | 10 passages chauds | 468 | 35 | 227,08 [212,59–261,40] | 13,77 [12,29–18,50] | 54,5 → 42,5 Mio | Aucune carte ; files et économie conservées |
| Bâtiments | 10 passages chauds | 468 | 275 | 226,75 [208,52–330,11] | 108,58 [104,41–137,61] | 54,5 → 46,5 Mio | 39 cartes, coûts et prérequis conservés |
| Recherche | 10 passages chauds | 468 | 35 | 248,17 [212,30–281,67] | 49,12 [43,89–83,89] | 56,5 → 46,5 Mio | 57 cartes et recherche commune conservées |
| Chantier | 10 passages chauds | 468 | 37 | 228,00 [213,86–240,42] | 17,33 [15,80–21,53] | 56,5 → 46,5 Mio | 42 cartes et stocks par rang conservés |
| Défenses | 10 passages chauds | 468 | 36 | 226,90 [218,61–279,89] | 14,05 [13,00–17,84] | 56,5 → 46,5 Mio | 12 cartes et stocks par rang conservés |
| Guide | 10 passages chauds | 468 | 25 | 226,52 [201,69–248,52] | 12,25 [11,40–17,19] | 57,5 → 48,5 Mio | Arbre préparé sans cartes ni flottes |

Temps SQL médians [minimum–maximum] avant/après :

- Vue générale : 104,92 [102,21–113,95] / 7,94 [7,22–10,20] ms.
- Bâtiments : 105,27 [99,91–163,03] / 57,72 [56,13–70,68] ms.
- Recherche : 110,87 [99,85–119,00] / 8,77 [7,92–12,18] ms.
- Chantier : 103,59 [97,05–111,90] / 8,37 [7,92–10,49] ms.
- Défenses : 108,16 [99,02–112,97] / 7,74 [7,26–9,37] ms.
- Guide : 105,15 [99,75–113,86] / 5,64 [5,17–6,71] ms.

Les temps ne constituent pas des seuils CI.
Les échantillons bruts et le lanceur temporaire restent dans `var/rework-measures.json`
et `var/measure-rework.py`, ignorés par Git. Les budgets testés restent larges.

## Vérifications et incidents (journal)

- `make validate` réussi sur l’état initial.
- Caractérisation et mesure initiales : 2 tests / 5 assertions réussis.
- Première suite PHP de référence invalidée : lancement accidentel de la mesure
  pendant la suite, puis arrêt du seul processus PHPUnit de ce chantier après
  vérification de son identité. Aucun service partagé arrêté ; base habituelle intacte.
- Une nouvelle suite de référence a été lancée seule ; son erreur de fixture est détaillée ci-dessous.
- Contrôle en lecture seule de la base locale habituelle : schéma cohérent,
  sans migration ni modification des données.
- Première suite finale : 1 026 cas / 212 589 assertions, une erreur de montage
  du conteneur jetable (fichier `/docker/compose.prod.yaml` absent), benchmark
  optionnel ignoré. Montage Docker en lecture seule ajouté au seul environnement
  temporaire et cache préparé après recréation. Suite complète relancée sans
  modifier les sources ou les tests pour contourner ce défaut d’environnement.
- Navigateurs : deux dépassements de délai global observés sous charge (clavier
  sur toutes les routes et actualisation des flottes), sans échec d’assertion
  identifié lors de ces délais. Les deux reprises isolées réussissent ensuite
  (flottes 40,6 s ; clavier 38,0 s), sans changement de test ni de délai.
- La régression de réponse tardive et d’erreur réseau obsolète passe dans la
  suite complète ; correction ciblée enregistrée dans `6dd33b7`.
- Reprise PHP/Python complète du 15 novembre réussie : 1 026 cas PHP,
  212 591 assertions, un benchmark facultatif ignoré dans la suite (mesuré
  séparément), 13 tests Python réussis. Aucun échec PHP restant. Durée de cette
  passe sous charge : 17 min 30 s ; il ne s’agit pas d’un budget de performance.
- Lot 3 : 8 cas de caractérisation et mesure / 28 assertions réussis, puis
  16 tests d’accès, coûts, files communes et barre de ressources / 101 assertions.
  Après ajout des budgets larges de requêtes : 7 cas / 33 assertions réussis ;
  seule la mesure facultative est ignorée sans `REWORK_MEASURE=1`.
- Un projet Compose PHP jetable distinct, `genesis-rework-php-20261114`, isole
  base et cache des nouveaux tests. Sa configuration temporaire est dans `var/`
  ignoré par Git. Aucun service habituel arrêté ou reconfiguré.
- Le premier passage complet n’est pas une référence immuable : des fichiers
  ont évolué pendant son exécution. Ces résultats n’ont pas servi de validation
  finale ; la passe finale a porté sur un contenu stabilisé.
- Ce passage complet se termine avec 1 erreur sur 1 015 tests : la fixture de
  `FleetStationingTest::testNoHomeUsesShortestReachableOwnedColony` déplace une
  planète sur une position occupée (`planet_unique`). Fixture corrigée dans
  `5d0417a`, sans changer les règles de stationnement. La suite Python n’avait
  pas été atteinte lors de cette passe.
- Bâtiments garde des lectures dans les calculs économiques partagés (275 SQL
  sur la première mesure ciblée). Leur suppression nécessite une préparation
  économique dédiée et vérifiée ; aucun cache ajouté dans les services de la
  barre pour masquer ces lectures. Les combats restent à regrouper ; les
  compositions des flottes ont été regroupées au lot 6.

## Validation finale — 15 novembre 2026

| Contrôle | Résultat |
| --- | --- |
| `make validate` | Réussi, sans affichage des secrets |
| `make lint` | PHP, 78 YAML, 164 Twig, conteneur et schéma synthétique réussis |
| Schéma local habituel | Cohérent, contrôle en lecture seule, aucune migration |
| `make test` | Réussi sur le projet PHP isolé : 1 026 cas, 212 591 assertions, benchmark facultatif ignoré ; 13 Python réussis |
| Mesure facultative | Exécutée séparément avant/après sur six scénarios, avec caches préchauffés et dix passages chauds chacun |
| `make assets` | Réussi ; compilation finale également réussie sous Node 24.17.0 |
| `BROWSER_PERSISTENCE=1 make test-browser` | Couverture complète : 201 scénarios distincts réussis après deux reprises isolées, sans changer les tests ni leurs délais |
| `git diff --check` | Réussi sur le lot cumulé |

Les groupes comptent 55, 54, 43 et 49 cas. Première passe : 199 réussites et
deux délais dépassés ; reprise ciblée : 2/2. Comparaison des résultats au
manifeste : 201/201, aucun scénario manquant ni supplémentaire.
Les tests couvrent aussi les deux abandons simultanés ; cela ne remplace pas
une étude de concurrence de tous les traitements sensibles.

Les fichiers du bandeau, de ResourceService, de FactoryEconomy et de GameShell
sont identiques à `48c9a71`. Le corps de l’endpoint ressources est également
identique. Aucun coût, durée, équilibrage, verrou ou contrat persistant modifié.
Les environnements PHP et navigateur temporaires ont été fermés ; les services
habituels sont préservés. Les preuves et configurations temporaires restent
dans `var/` et `/tmp/genesis-rework-browser-*`, sans données réelles ni secrets.
La CI GitHub n’a pas été exécutée ; elle restera obligatoire sur une future PR.

## Complément du lot 3 — économie des bâtiments, 15 novembre 2026

Les cartes partagent désormais un contexte immuable de vacances et de bonus
d’événements, créé après la synchronisation des commandes et relu à chaque
préparation. Aucun cache de service ni copie des formules économiques.
ResourceService accepte ce contexte uniquement comme argument optionnel de
`rate` et `factoryConsumption`. Ses appels existants, son snapshot, les
capacités, la simulation et les écritures conservent leurs traitements.
Le contexte refuse une utilisation avec une autre colonie.

Comparaison isolée avec `9332789`, même fixture et cache Symfony préchauffé,
premier passage séparé puis dix passages chauds :

| Mesure Bâtiments | Avant | Après |
|---|---|---|
| Requêtes SQL | 275 | 37 |
| Temps serveur médian [min–max] | 97,59 ms [95,23–120,49] | 39,26 ms [33,97–53,58] |
| Temps SQL médian [min–max] | 53,04 ms [52,35–67,24] | 8,64 ms [7,66–10,07] |
| Mémoire allouée médiane du processus | 46,5 Mio | 42,5 Mio |

Les 39 cartes et leurs détails sont conservés. Il s’agit de mesures du service,
pas d’un temps HTTP ou navigateur. Données brutes :
`var/rework-economy-measures.json` (ignoré). Les autres sections gardent leurs
nombres de requêtes. Le test de caractérisation compare toutes les cartes aux
appels sans contexte, après changement d’événement, vacances et reprise ; il
vérifie aussi la fin d’une commande et l’égalité du snapshot économique.
Contrôles : 62 tests, 2 475 assertions, un benchmark optionnel ignoré puis
exécuté séparément ; `make lint` réussi. La validation cumulative finale reste
à relancer après les autres compléments.

Les combats des flottes actives sont aussi lus en une requête, filtrée par
propriétaire et univers. Le premier participant historique reste la référence,
y compris si une flotte possède plusieurs participations : aucune sélection
silencieuse d’un autre combat. Test sur douze flottes : une lecture groupée,
cas vide et historique résolu conservés ; 12 cas / 370 assertions, dont le
benchmark optionnel ignoré. Les projections des compositions restent inchangées.

## Complément du lot 4 — relations et contributeurs

Les contrats personnels, relations des guildes et identités des commandants
sont lus par groupes pour l’affichage de Diplomatie. Les autres onglets ne
préparent pas la liste des commandants. Les priorités guildes/contrats, les
propositions entrantes communes et l’ordre des résultats de recherche sont
conservés. Aucun cache partagé ni modification des validations d’actions.
Les contributeurs de la fiche de projet sont hydratés ensemble, avec les
anciens membres et le filtre d’univers ; la liste des projets garde le seul
don personnel. Vérification : 69 tests / 418 assertions réussis, dont
25 combinaisons comparées à RelationResolver. Deux erreurs de préparation
(fixture sans proposant et alias DQL réservé) corrigées avant la reprise.

## Complément du lot 5 — résumés de combats et profil quotidien

La liste des rapports de combat lit des résumés scalaires : résultat, date,
planète et nombre de participants. Ni les rounds ni le snapshot complet ne
sont hydratés. Les entités détaillées restent complètes pour la lecture et
l’archivage. Les droits sont vérifiés avant pagination et à nouveau dans la
lecture des résumés ; les refus interunivers et tiers sont testés.

Le profil lit sa ligne dans le snapshot quotidien existant. Le rattrapage des
files, le verrou et le recalcul de minuit sont conservés ; les nouveaux joueurs
attendent toujours le prochain calcul. Les vieux snapshots sans les rangs
bâtiments/recherche utilisent le chemin de compatibilité existant. À chaud,
le test exige moins de quatre requêtes pour la lecture du score individuel.
Contrôles : 19 tests / 133 assertions réussis, preuve détaillée conservée,
dates et contexte de retour, nouveaux joueurs et minuit couverts.

## Complément du lot 7 — pagination et historique administratif

Joueurs, journal, feedbacks, événements et patchnotes administratifs sont
paginés par 50. Les recherches et filtres s’appliquent avant pagination.
Les compteurs des feedbacks et leur curseur de consultation conservent la
sélection complète ; le filtre d’archives des événements conserve la sémantique
des anciens JSON. Aucun historique ni preuve supprimé.

Joueurs/Journal conservent la recherche immédiate avec fragments partagés,
focus et sélection ; les réponses obsolètes sont écartées. Le formulaire GET
fonctionne aussi sans JavaScript. L’export des lignes visibles reste disponible ;
un export de tous les résultats filtrés lit 100 lignes à la fois, avec plafond
d’identifiant et protection contre les formules CSV.

Contrôles : 4 tests PHP / 65 assertions, fixtures de 175 comptes, feedbacks,
événements, notes et actions ; recherches hors page, filtres combinés, archives,
comptages, curseur de consultation, export complet et refus d’accès couverts.
Les cinq scénarios navigateur du fichier comptes/flottes réussissent par
consolidation : quatre scénarios, dont le nouveau parcours de pagination, puis reprise
du scénario rubriques/journal après correction d’une sélection avant connexion
Stimulus et du texte « aucun résultat ». Aucun test ni délai affaibli.
`make assets` réussi (495 Kio bruts, contre 494 Kio avant ce complément).

Les modèles de projets/rotations et les catalogues de paramètres restent des
listes de configuration complètes : leurs écrans affichent et éditent réellement
l’ensemble, contrairement aux historiques de joueurs. Pas de pagination
automatique qui ferait perdre les liens d’édition et la vue d’ensemble. La
modération garde ses consultations journalisées existantes ; son éventuelle
pagination doit aussi définir la portée des consultations avant changement.

## Complément du lot 6 — compositions, combats et activités

Flottes réutilise la liste à quai pour les réserves et lit ses compositions
ensemble ; les quantités nulles restent exclues du formulaire. Les combats
utilisent la même lecture groupée que la colonie. Le périmètre de la colonie
active, les préremplissages et les demandes d’orbite sont conservés.
La liste des colonies compte les commandes par groupe SQL au lieu d’hydrater
chaque file. Filtrage propriétaire/univers conservé même avec des identifiants
forgés. Contrôles : 19 tests / 525 assertions, un benchmark optionnel ignoré.
Les snapshots par planète restent des appels complets au moteur existant :
leur regroupement nécessiterait une extraction plus large du moteur partagé
avec la barre exclue, sans bénéfice mesuré ici qui justifierait ce risque.

## Complément du lot 8 — fragments des flottes

Flottes répond aussi en JSON à X-Genesis-Live + Accept JSON : sélecteur de
colonie, ressources, compteurs, flottes disponibles, vols et demandes d’orbite.
Le HTML complet reste le repli. Les formulaires et compteurs proviennent de
fragments extraits sans changement de leur contenu. GameShell et le template
de ressources conservent leur préparation et leur cycle.

Contrôles : 7 tests du contrôleur Destinations réussis, puis comparaison des
six régions pour Exploration/Espionnage, incluant le bandeau économique
identique avec horloge figée. Les jetons de formulaire et de rejeu restent
présents ; leur valeur renouvelée est exclue de la comparaison textuelle.
Les cinq scénarios navigateur Destinations/Focus réussissent ensemble :
colonie active, compteurs, destination, requêtes rapprochées et réponse tardive.
Le JS de remplacement des régions n’a pas nécessité de modification.

## Reprise

- Branche : `chore/rework-v1`, base `48c9a71`, code applicatif validé `6dd33b7`.
- Lot actuel : 12/12, validation locale de la passe ciblée terminée.
- Dernière étape validée : suites complètes, reprises, couverture 201/201 et mesures finales.
- Contrôles en cours : aucun.
- Modifications non commitées après enregistrement du bilan : aucune.
- Questions en attente : aucune ; recette manuelle et exceptions restent ouvertes.
- Prochaine action précise : caractériser les lectures économiques de Bâtiments
  sur plusieurs volumes et événements, puis préparer une optimisation dédiée
  aux cartes qui conserve intégralement les traitements du bandeau.
- Conserver cette branche unique ; aucune PR, fusion ou publication automatique.

## Travaux par lot et contrôles ciblés

- Référence du lot 1 : `9e62541`.
- Lot 2 : imports explicites et constructeur de ColonyView mis en lignes,
  sans changement de comportement ; syntaxe PHP et diff vérifiés. `.editorconfig`
  existant conservé. Pas de formateur ajouté ni de formatage global : les autres
  écarts sont traités seulement avec les domaines réellement modifiés.
- Lot 4 : onglet validé avant les chargements ; recherche de joueurs uniquement
  dans Relations commandants ; membres et historiques des guildes chargés pour
  les fiches affichées ; grades/attributions seulement sur leurs onglets. Les
  compteurs de propositions restent communs, sans enrichir tous les joueurs.
  La liste des projets ne résout plus les identités des autres contributeurs ;
  la fiche garde le bilan complet. Permissions et écritures inchangées.
- Vérifications du lot 4 : 80 tests / 328 assertions, puis 44 / 211 après le
  ciblage des commandants ; régression du compteur sur cinq onglets : 1 / 15.
  Aucun style ni interaction modifié ; les listes diplomatiques affichées et les
  détails des projets gardent encore des lectures par élément, à regrouper ensuite.
- Lot 5 : hydratation des rapports regroupée par type après les droits et la
  pagination (50 maximum), aucun enrichissement des lignes joueurs sur l’onglet
  Guildes, compteur SQL des membres pour le profil public, identités des contacts
  chargées ensemble avec filtre d’univers. Classements quotidiens, protections
  en temps réel et écritures de messagerie conservés.
- Rapports/profils/classement : 31 tests / 192 assertions réussis. Les rounds
  des rapports sont encore hydratés dans la liste et les profils passent encore
  par le classement complet : exceptions restantes, pas d’amélioration revendiquée.
- Messagerie et classements : 31 tests / 176 assertions réussis après regroupement
  des contacts ; aucune recherche paginée ni aucun état de lecture modifié.
- Lot 6 : lecture groupée des planètes découvertes dans la galaxie sélectionnée,
  avec filtre d’univers serveur ; suggestions limitées aux mondes découverts
  avant préparation ; stationnement limité aux colonies propres. La liste libre
  inutilisée de Planètes est retirée après recherche de tous ses usages.
  EmpireDetails regroupe les compositions après filtrage des flottes autorisées.
  Les snapshots économiques, trajets et décisions de mission sont inchangés.
- Validation : carte/destinations 16 tests / 309 assertions ; caractérisation
  et accès 9 cas / 40 assertions (mesure optionnelle ignorée) ; stationnement
  20 / 98, puis repli renforcé 1 / 5. Correction de fixture distincte `5d0417a`.
  Le regroupement des combats et les snapshots par colonie restent à approfondir.
- Lot 7 : AdminReadRepository sépare compteurs, incidents et aperçu des dernières
  actions. Les détails de modération, joueurs, univers et exploitation ne sont
  chargés que sur leurs écrans. L’accueil lit cinq actions ; Sécurité lit les
  cinquante incidents affichés et conserve un compteur/curseur sur l’ensemble
  des incidents. Aucune preuve ou action d’administration modifiée.
- Validation : 7 tests HTTP/métier / 53 assertions ; régression sur 75 incidents
  et un examen : 1 test / 7 assertions, avec conservation des 76 traces.
  Les pages Joueurs et Journal et certaines listes spécialisées restent non
  paginées : chantier ouvert, filtres et export complet à préserver.
- Lot 8 : les actualisations JSON des colonies rendent les régions partagées
  (sélecteur, ressources, files, vols, journal ou catalogue), sans document HTML
  complet. Les lectures/synchronisations de classement et GameShell sont
  conservées ; le bandeau utilise son template inchangé et garde son remplacement
  habituel. Les vues initiales réutilisent exactement les fragments extraits.
  Une génération de requête et le contexte d’URL empêchent les réponses tardives
  de remplacer un état récent ; abort, nettoyage des écouteurs et formulaires
  modifiés/focus conservés. Flottes garde encore la réponse HTML complète.
- Validation : 7 tests HTTP / 124 assertions, puis extension journal non vide
  1 / 42 ; deux scénarios navigateur réussis (focus puis réponse inversée).
  Le premier essai du nouveau scénario envoyait son événement avant connexion
  de Stimulus ; attente d’un état DOM observable ajoutée, sans délai fixe.
- Lot 9 : extraction comparée à la source avant changement, contenu des trois
  fragments strictement conservé. Aucun CSS, image, police, logo, métadonnée ou
  réglage de compilation changé. Nettoyage des listeners/minuteries vérifié pour
  colony-live, building-catalog et catalog-sticky. Focus vérifié au lot 8.
  Compilation réussie : runtime 1,41 Kio, chunk partagé 93,6 Kio, CSS 251 Kio,
  JS applicatif 148 Kio (tailles brutes, pas le transfert compressé).
  Pas d’optimisation supplémentaire sans coût démontré. Clavier, responsive,
  contrastes automatiques et métadonnées validés dans la suite finale ; cela
  ne constitue pas un audit RGAA/WCAG complet ni une revue de tous les styles.
- Lot 10 : sept handlers et contrats de messages, routage/retries, outbox,
  garde des boosts, suppression de compte et accès du simulateur examinés de
  manière ciblée. Compositions du simulateur regroupées après contrôle de la
  colonie. Commentaire du relais traduit et précisé sans promesse d’exactement
  une livraison. Guide asynchrone corrigé pour les sept natures et guild_project.
  Aucun verrou, ordre de verrouillage, contrat persistant ou retry modifié.
- Validation : 57 tests / 539 assertions (outils stratégiques, boosts, suppression
  de compte, reconstruction et capture). Les rejoués séquentiels ne démontrent
  pas la concurrence réelle. Pas d’audit exhaustif de comptes/inventaire,
  d’infrastructure ou de dépendances ; pas d’installation ni de service externe.
- Lot 11 : index réparé, architecture/README/feuille de route reliés au suivi.
  Contrôleur JS remis en forme dans un commit distinct, AST vérifié identique
  avant l’ajout séparé du refus d’erreurs réseau devenues obsolètes. Pas de
  suppression de fichiers, formatage global, migration ou dépendance ajoutée.
- Validation déjà terminée : Compose, lint PHP/Twig/YAML/conteneur et schéma sur
  la base synthétique ; `make assets` réussi sous Node WSL 20.19.2, complété par
  une compilation de production réussie sous Node Windows 24.17.0, conforme à la
  version majeure documentée. Aucun téléchargement ni installation nécessaire.

## Exceptions et travaux encore ouverts

Cette passe n’est pas présentée comme l’achèvement intégral du prompt de
nettoyage. Tous les domaines du plan sont inventoriés, mais la profondeur de
revue reste ciblée ; tous les composants existants n’ont pas été relus ligne
par ligne. Les écarts suivants restent explicitement ouverts :

1. Préparation économique des bâtiments traitée dans le complément du lot 3 :
   37 requêtes sur la fixture ; mesures à plusieurs volumes encore à compléter.
2. Résumés de rapports évitant l’hydratation des rounds ; accès au classement
   quotidien pour un seul profil sans enrichir tout l’univers.
3. Regroupement complémentaire des identités/relations diplomatiques, des
   contributeurs dans les fiches de projet et des combats des flottes actives.
4. Pagination serveur des joueurs, du journal et des listes administratives
   spécialisées, avec recherche, export et accès à tout l’historique.
5. Fragments propres à Flottes et mesures HTTP/navigateur sur plusieurs volumes.
6. Revue plus large des conventions existantes, accessibilité manuelle complète
   et tests réellement concurrents des traitements sensibles.

Pas de cache partagé improvisé ni de projection partielle d’entité pour masquer
ces coûts ; ces évolutions demandent leurs scénarios de caractérisation et de
mesure. Les règles et valeurs restent strictement hors arbitrage de ce chantier.

## Fichiers du lot cumulé

| Périmètre | Fichiers modifiés ou ajoutés |
| --- | --- |
| Contrôleurs | `AdminController`, `ColonyController`, `DiplomacyController`, `GuildProjectController`, `LeaderboardController` dans `app/src/Controller/` |
| Lectures | `app/src/Repository/AdminReadRepository.php`, `app/src/Repository/GameRepository.php` |
| Préparation des pages | `Colony/ColonyView.php`, `Colony/EmpireDetails.php`, `Combat/PlayerCombatSimulation.php`, `Combat/ReportArchiveService.php`, `Fleet/DestinationCatalogue.php`, `Player/SocialProfileView.php` sous `app/src/Service/` |
| Asynchrone | `app/src/Service/Notification/OutboxRelay.php` (commentaire seulement) |
| Navigateur | `app/assets/controllers/colony_live_controller.js` |
| Templates | `app/templates/base.html.twig`, `colony/show.html.twig`, `colony/_catalog.html.twig`, `colony/_journal.html.twig`, `player/diplomacy.html.twig`, `ui/colony_selector.html.twig` sous `app/templates/` |
| Tests HTTP | `app/tests/Controller/AbsenceJournalTest.php`, `GuildTagPlayerReportTest.php` dans le même dossier |
| Tests intégrés | `AdminReadRepositoryTest.php`, `CatalogAccessTest.php`, `ColonyPreparationTest.php`, `FleetStationingTest.php`, `GamePersistenceTest.php` dans `app/tests/Integration/` |
| Test navigateur | `app/tests/browser/live-refresh-focus.spec.mjs` |
| Documentation | `README.md`, `docs/ARCHITECTURE.md`, `docs/FEUILLE_DE_ROUTE.md`, `docs/README.md`, `docs/REWORK_V1.md`, `docs/WORKER_NOTIFIER.md` |

## Recette manuelle à effectuer

Utiliser un environnement de recette et des comptes synthétiques pour les actions
modifiant l’état ; les essais automatisés ne portent pas sur la base habituelle.

- Comptes : inscription, confirmation et connexion ; préférences, changement
  d’univers et sélection de colonie. Les emails externes demandent un environnement autorisé.
- Quatre catalogues : rangs, coûts/durées, prérequis, détails, filtres mémorisés,
  liens directs et Max ; constructions et recherche commune depuis deux colonies.
- Actualisations : garder une saisie modifiée et le focus, terminer une commande,
  changer rapidement de colonie, couper/rétablir le réseau ; vérifier le journal
  non marqué lu et la barre de ressources avec ses détails, pénuries et vacances.
- Flottes : compositions, aperçu/envoi, orbites, rappel, stationnement et repli ;
  carte classique/V2, galaxies, destinations connues et masquage des inconnues.
- Rapports/messages : filtres, dates, pagination, archives/restauration, anciens
  rapports, canaux privé/guilde/Global, Ctrl + Entrée et Markdown sécurisé.
- Guildes/projets : cinq onglets, propositions reçues, grades et refus de droits,
  contribution personnelle, fiche des dons et récompense unique ; inventaire/boosts.
- Administration : accueil, comptes, univers, modération, sécurité/examen,
  exploitation et historique complet ; confirmations et refus des non-administrateurs.
- Mobile, clavier et deux thèmes : focus visible, libellés, erreurs, détails,
  mouvements réduits et parcours sans JavaScript disponibles. Les tests automatisés
  ne remplacent pas cette recette et ne prouvent pas une conformité RGAA complète.
