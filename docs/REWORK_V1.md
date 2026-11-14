# Rework V1 — suivi et reprise

## Cadre

- Branche unique : `chore/rework-v1`, créée depuis `origin/dev` actualisée à `48c9a71`.
- Arbre initial propre ; huit worktrees existants recensés et conservés.
- Douze lots conservés. Aucun push, PR, fusion, déploiement ou changement d’équilibrage.
- Barre de ressources exclue : endpoint, services de calcul, templates et fréquence inchangés.
- Aucune dépendance nouvelle, migration ou suppression prévue.
- Tests PHP sur `database-test/genesis_v2_test` exclusivement ; tests navigateur dans un projet jetable distinct.
- Ne pas exécuter deux suites PHP en parallèle : elles reconstruisent le même schéma synthétique.

## Sources

Consignes racine présentes et lues ; aucune consigne imbriquée trouvée dans les
répertoires applicatifs, scripts et documentation. README, index, architecture,
feuille de route, règles et décisions consultés pour cadrer les domaines ; leurs
longs historiques ne constituent pas une preuve de l’état courant.
Les trois documents Work nommés dans AGENTS.md sont absents du dépôt et des pièces
jointes accessibles à ce chantier ; ils ne sont pas présentés comme lus.
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
| 9 | Assets, HTML, accessibilité | Fragments strictement identiques ; entrée compilée 494 Kio | Revue ciblée, contrôles navigateur complets au lot 12 |
| 10 | Comptes, boosts, événements, handlers, exploitation | Sept contrats revus ; lectures du simulateur regroupées | Revue ciblée et tests validés ; concurrence réelle non démontrée |
| 11 | Documentation et écarts transversaux | Index réparé, documentation et relecture des changements | Effectué sur le périmètre modifié |
| 12 | Contrôles et recette cumulée | Six contrôles, mesures comparables et parcours manuels requis | Suites complètes en cours |

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
Pour chaque scénario : un premier appel, puis dix passages chauds. Tableau des
médianes et étendues serveur en millisecondes ; allocations mémoire du processus
(cumulatives entre scénarios), pas mémoire HTTP par visite. Instrumentation de
débogage identique. Les autres services sont ceux du checkout courant ; avec
la fixture sans flotte active, les changements de compositions ne modifient pas
le travail comparé. Valeurs de transfert, rendu et temps HTTP : non mesurés.

| Page | Scénario | SQL avant | SQL après | Temps avant | Temps après | Mémoire avant → après | Résultat fonctionnel |
| --- | --- | --- | --- | --- | --- | --- | --- |
| Vue générale | 10 passages chauds | 468 | 35 | 207,91 [199,52–265,22] | 12,29 [11,55–15,81] | 86,5 → 42,5 Mio | Aucune carte ; files et économie conservées |
| Bâtiments | 10 passages chauds | 468 | 275 | 213,51 [196,75–236,50] | 98,57 [94,73–109,82] | 87,5 → 46,5 Mio | 39 cartes, coûts et prérequis conservés |
| Recherche | 10 passages chauds | 468 | 35 | 214,13 [196,77–301,02] | 44,12 [41,66–104,25] | 88,5 → 46,5 Mio | 57 cartes et recherche commune conservées |
| Chantier | 10 passages chauds | 468 | 37 | 211,08 [195,76–232,34] | 15,73 [15,15–17,07] | 88,5 → 46,5 Mio | 42 cartes et stocks par rang conservés |
| Défenses | 10 passages chauds | 468 | 36 | 235,91 [202,37–247,70] | 14,39 [12,80–15,98] | 88,5 → 46,5 Mio | 12 cartes et stocks par rang conservés |
| Guide | 10 passages chauds | 468 | 25 | 210,01 [196,75–229,29] | 12,70 [10,99–14,19] | 90,5 → 48,5 Mio | Arbre préparé sans cartes ni flottes |

Temps SQL médians avant/après : vue générale 98,01/7,21 ms ; Bâtiments
99,98/54,12 ms ; Recherche 99,17/7,77 ms ; Chantier 100,05/7,78 ms ; Défenses
106,87/8,08 ms ; Guide 100,00/5,93 ms. Les temps ne constituent pas des seuils CI.
Les échantillons bruts et le lanceur temporaire restent dans `var/rework-measures.json`
et `var/measure-rework.py`, ignorés par Git. Les budgets testés restent larges.

## Vérifications et incidents (journal)

- `make validate` réussi sur l’état initial.
- Caractérisation et mesure initiales : 2 tests / 5 assertions réussis.
- Première suite PHP de référence invalidée : lancement accidentel de la mesure
  pendant la suite, puis arrêt du seul processus PHPUnit de ce chantier après
  vérification de son identité. Aucun service partagé arrêté ; base habituelle intacte.
- Nouvelle suite de référence lancée seule ; résultat à consigner.
- Lot 3 : 8 cas de caractérisation et mesure / 28 assertions réussis, puis
  16 tests d’accès, coûts, files communes et barre de ressources / 101 assertions.
  Après ajout des budgets larges de requêtes : 7 cas / 33 assertions réussis ;
  seule la mesure facultative est ignorée sans `REWORK_MEASURE=1`.
- Un projet Compose PHP jetable distinct, `genesis-rework-php-20261114`, isole
  base et cache des nouveaux tests. Sa configuration temporaire est dans `var/`
  ignoré par Git. Aucun service habituel arrêté ou reconfiguré.
- Le premier passage complet n’est pas une référence immuable : des fichiers
  ont évolué pendant son exécution. Ses résultats ne seront pas annoncés comme
  validation finale ; la suite finale devra partir d’un contenu stabilisé.
- Ce passage complet se termine avec 1 erreur sur 1 015 tests : la fixture de
  `FleetStationingTest::testNoHomeUsesShortestReachableOwnedColony` déplace une
  planète sur une position occupée (`planet_unique`). À reproduire et corriger
  sans modifier les règles de stationnement. La suite Python n’a pas été atteinte.
- Bâtiments garde des lectures dans les calculs économiques partagés (275 SQL
  sur la première mesure ciblée). Leur suppression nécessite une préparation
  économique dédiée et vérifiée ; aucun cache ajouté dans les services de la
  barre pour masquer ces lectures. Combats et détails des flottes restent à regrouper.

## Reprise

- Lot actuel : 12/12.
- Dernière étape validée : fixture isolée et mesure avant changement.
- Contrôles en cours : suite PHP/Python finale (1 026 cas PHP), quatre groupes
  navigateur indépendants couvrant les 201 scénarios (55 + 54 + 43 + 49).
- Référence du lot 1 : `9e62541`.
- Lot 2 : imports explicites et constructeur de ColonyView mis en lignes,
  sans changement de comportement ; syntaxe PHP et diff vérifiés. `.editorconfig`
  existant conservé. Pas de formateur ajouté ni de formatage global : les autres
  écarts sont traités seulement avec les domaines réellement modifiés.
- Questions en attente : aucune ; actualisation réseau de dev autorisée et effectuée.
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
  contrastes automatiques et métadonnées à vérifier dans la suite finale ; cela
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
- Prochaine action : attendre la compilation finale et les suites cumulées ;
  mesures comparables et validation cumulée à reprendre au lot 12.

## Exceptions et travaux encore ouverts

Cette passe n’est pas présentée comme l’achèvement intégral du prompt de
nettoyage. Tous les domaines du plan sont inventoriés, mais la profondeur de
revue reste ciblée ; tous les composants existants n’ont pas été relus ligne
par ligne. Les écarts suivants restent explicitement ouverts :

1. Préparation économique propre aux cartes de bâtiments : 275 requêtes sur la
   fixture, sans modifier le moteur ni les traitements du bandeau exclu.
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
