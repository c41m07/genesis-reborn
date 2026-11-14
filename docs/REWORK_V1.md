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
| 3 | ColonyController, ColonyView, CatalogueInsights | Quatre catalogues et arbre préparés pour chaque page ; lectures par carte | À traiter |
| 4 | DiplomacyController, GuildProjectService | Onglet choisi après préparation de toutes les relations | À examiner en détail |
| 5 | Rapports, classements, profils, messages | Recherche déjà paginée ; enrichissements à vérifier | À examiner en détail |
| 6 | Carte, planètes, destinations, flottes | Lecture des planètes par système avant sélection de galaxie | À traiter |
| 7 | AdminController et historiques | Toutes les rubriques préparées à chaque ouverture | À examiner en détail |
| 8 | colony-live, fragments | HTML complet et réponses tardives à vérifier | À examiner en détail |
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

## Reprise

- Lot actuel : 2/12.
- Dernière étape validée : fixture isolée et mesure avant changement.
- Contrôle en cours : suite PHP/Python de référence.
- Référence du lot 1 : `9e62541`.
- Lot 2 : imports explicites et constructeur de ColonyView mis en lignes,
  sans changement de comportement ; syntaxe PHP et diff vérifiés. `.editorconfig`
  existant conservé. Pas de formateur ajouté ni de formatage global : les autres
  écarts sont traités seulement avec les domaines réellement modifiés.
- Questions en attente : aucune ; actualisation réseau de dev autorisée et effectuée.
- Prochaine action : préparation ciblée de ColonyView (lot 3), puis attendre
  la fin de la suite PHP avant toute autre suite utilisant sa base.
