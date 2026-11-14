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
| 9 | Assets, HTML, accessibilité | Coûts non mesurés ; préserver présentation et identité | À examiner en détail |
| 10 | Comptes, boosts, événements, handlers, exploitation | Contrats et idempotence à vérifier sans toucher aux verrous | À examiner en détail |
| 11 | Documentation et écarts transversaux | Index contient un séparateur littéral incorrect | À traiter |
| 12 | Contrôles et recette cumulée | Six contrôles, mesures comparables et parcours manuels requis | À faire |

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

## Vérifications et incidents

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

- Lot actuel : 8/12.
- Dernière étape validée : fixture isolée et mesure avant changement.
- Contrôle en cours : suite PHP/Python de référence.
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
- Prochaine action : lot 9, assets et accessibilité ;
  mesures comparables et validation cumulée à reprendre au lot 12.
