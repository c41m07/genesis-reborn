# Interfaces et flottes — suivi du 11 novembre 2026

## Point de départ

Dépôt propre ; dev actualisée par fetch autorisé, identique à origin/dev (68ca8d9).
Branche du lot A : feature/interfaces-classements. Travail antérieur préservé.
Lecture ciblée des documents de référence et des services concernés, sans audit global.
Les trois documents Work V4/V2/V1 cités dans AGENTS.md ne sont pas présents dans
le dépôt ; aucun document n’est joint selon l’inventaire du chat. Ils n’ont pas été
consultés. Le wiki intégré utilise docs/wiki.json et les références REGLES/DECISIONS :
ces sources sont actualisées et vérifiées au navigateur. Aucun wiki distant
n’a été modifié.

## Lots et complexités

PR, CI complète et intégration du précédent avant le lot suivant.
Une complexité 4 ou 5 impose l’analyse détaillée des transitions et risques.

| Lot | Sous-tâche | Complexité | État |
| --- | --- | --- | --- |
| A — présentation/classement | Retirer la mention horaire de l’aperçu | 1 | Modifié localement |
| A | Cinq catégories, rangs, variations et pagination globale | 3 | Modifié localement |
| A | Prérequis navigables au clavier ; retrait des accès associés des cartes/fiches | 3 | Modifié localement |
| A | Triangle de signalement privé/guilde et colonnes origine/destination admin | 2 | Modifié localement |
| A | Retirer le journal de mission en conservant suivi et historiques | 1 | Modifié localement |
| B — carte/confidentialité | Cercle vert des systèmes possédés ; retour planète vers son système | 2 | À réaliser |
| B | Masquer les sondes entrantes au serveur ; résumé des flottes tierces visibles | 4 | Points confirmés, à réaliser |
| C — colonies/journal | Acquisition sur toutes les voies de transfert ; ordre commun | 4 | Reprise confirmée, à réaliser |
| C | Journal d’absence, consultation explicite, détails clavier/tactiles | 4 | Principes confirmés, à réaliser |
| D — destinations | Trois modes, découvertes, univers, suggestions de pseudonyme | 3 | Répartition confirmée, à réaliser |
| D | Attaque PNJ, départ/arrivée et liens depuis la carte | 4 | Dépendances métier à traiter |
| E — administration/rapports | Attribution auditée de vaisseaux, planète/type/rang/quantité | 4 | À réaliser |
| E | Archive/restauration personnelle, sélection et conservation des preuves | 4 | Choix confirmés, à réaliser |
| F — guildes/signalements | Tag obligatoire à la création, unicité par univers et classement | 3 | Transition confirmée, à réaliser |
| F | Signalement d’un joueur depuis le classement | 3 | Motifs et contexte validés |
| G — rappel | Missions, trajet partiel, carburant/fret, verrouillage, origine perdue | 5 | Décisions validées, à réaliser |
| H — détection | Analyse tactique, visibilité des mouvements et détection à l’arrivée | 5 | Décisions validées, à réaliser |
| I — orbites | Demande au départ, accords, attente et changements diplomatiques | 5 | Décisions validées, à réaliser |
| J — abandon | Dernière colonie, actifs/files, restrictions et confirmation | 5 | Décisions validées, à réaliser |
| K — refontes | Diplomatie, Profil, Inventaire : maquettes adaptatives, puis validation | 3 | Proposition locale ; intégration non commencée |
| Chaque lot | Documentation, contrôles adaptés, six contrôles avant commit/push, CI avant fusion | 3 | À appliquer |

## Existant et conflits identifiés

- Classements : instantanés quotidiens persistés et comparaisons total/flotte/colonies
  déjà disponibles. Le lot A ajoute bâtiments/recherche et conserve les anciens
  instantanés sans recalcul anticipé des points. Les nouvelles variations de rang
  attendent deux références compatibles. Égalités : pseudonyme sans casse.
- Acquisition : Planet n’a aucune date d’acquisition ; le tri actuel par identifiant
  ne décrit pas les conquêtes. Migration additive et toutes les voies d’affectation
  à préparer. Ne pas inventer les dates des possessions anciennes.
- Journal : GameShell transmet cinq notifications récentes sans fenêtre d’absence.
- Carte : ColonyController::mapData sérialise la composition des flottes visibles,
  y compris tierces. Visibilité actuelle : propriétaire, alliés ou cible de ses
  colonies. Masquer les sondes et limiter les champs avant sérialisation,
  sans élargir la visibilité de base.
- PNJ : FleetController réutilise CombatEventService::canAttack. Hors occupation,
  ce dernier exige un propriétaire : les PNJ classiques sans propriétaire sont
  exclus aussi côté métier. La conquête PNJ existe séparément. L’attaque PNJ
  demande une adaptation serveur et des tests d’arrivée ; conserver protections
  et distinction attaque/conquête.
- Rappel : rappels administratifs et de conversion existent dans FleetService.
  La commande joueur n’existe pas encore ; préserver carburant, jetons et verrous.
- Analyse tactique : catalogue de base à 5 niveaux par rang. L’espionnage pondère
  les niveaux par 1, 1,25 et 1,5625 avant arrondi. À 5/5/5 : 19 effectifs,
  et donc 100 minutes avec la formule proposée, avant limite de 50 % du trajet.
  Une somme simple donnerait 80 minutes. La somme simple est désormais validée pour le préavis uniquement ; elle reste à implémenter.
  Les surcharges de catalogue par univers existent : vérifier les plafonds
  effectifs de l’univers choisi avant implémentation. Lecture locale effectuée :
  abc-1 actif, plafonds effectifs 5/5/5, surcharges incluses. Alpha non vérifiée.
- Orbite : alliances/pactes de défense requis ; demande à l’arrivée, attente
  orbit_pending, décision et retour après refus. Les neutres et demandes au départ
  modifient ces conditions ; préserver vacances et réévaluation à l’arrivée.
- Rapports : chaque liste est déjà décroissante par type. Vérifier aussi la
  chronologie regroupée. L’archive devra être personnelle à chaque participant.
- Guilde : aucun champ tag. Migration et formulaire requis ; ne pas dériver
  silencieusement un tag ni renommer les guildes existantes.
- Administration : ressources avec motif, clé de requête et audit déjà disponibles
  dans AdminInterventionService. Réutiliser les garanties pour les vaisseaux.
- Diplomatie : titre « Alliances » couvrant guildes, traités et commandants.
  Maquette proposée avec périmètres clairs ; préserver projets, grades, permissions,
  recrutement, départ et transmission du commandement.
- Profil : identifiants/mot de passe, fermeture de session à l’enregistrement,
  suppression multi-univers confirmée. Maquette séparant courant et destructif.
- Inventaire : boosts, boîtes, capacité, attente, opérations accélérables,
  restrictions de vacances et stockage. Maquette regroupant choix et vérification.

## Décisions et questions restantes

Choix confirmés dans la section du 11 novembre de [DECISIONS](DECISIONS.md).
Les choix confirmés non implémentés ne doivent pas être annoncés disponibles.

Les principes des groupes métier sont confirmés : rappel, détection,
visibilité, orbite, abandon, archives, tag, destinations et signalement.
Les décisions détaillées restent dans DECISIONS ; elles ne sont pas implémentées
par cette seule consignation.

Les maquettes Diplomatie, Profil et Inventaire restent à valider avant intégration.
Les migrations futures seront préparées séparément de leur application :
base existante uniquement après autorisation explicite et sauvegarde vérifiée.

## Maquettes et accès externes

Figma connecté ; équipe choisie : Kevin Le Stang’s team.
[Fichier créé](https://www.figma.com/design/HVpsVXWBg6foCjQzQ0k9mS).
La page vide et les bibliothèques ont été consultées ; la recherche de composants
est ensuite refusée pour quota MCP Starter atteint. Le fichier reste vide :
aucune maquette créée dans Figma.

Prévisualisation interactive locale des trois pages proposée dans le chat,
navigation et modes ordinateur/mobile, données d’exemple. Contrôles à 1024, 834,
768, 390 et 320 px : pas de débordement, navigation et dialogue fonctionnels.
Captures Diplomatie ordinateur, Profil mobile et Inventaire tablette examinées. Les trois refontes ne sont
pas intégrées aux templates du jeu.

## Vérifications du lot A

Validation locale terminée :
- make validate : réussi, sans affichage des secrets.
- make lint : réussi après les corrections finales, PHP/Twig/YAML, conteneur et
  schéma local compris.
- make assets, équivalent npm run build dans app : réussi après le correctif de focus.
- make test : réussi, 703 tests PHP et 209995 assertions, puis 13 tests Python.
  Le premier passage avait épuisé la limite de 128 Mo. Le Makefile accorde maintenant
  512 Mo au seul processus PHPUnit ; la commande make test elle-même a été rejouée
  avec succès. Aucune configuration globale de PHP ni aucun test affaibli.
- Navigateur : 159 scénarios distincts réussis, couverture consolidée et comparée
  au manifeste. Première passe séquentielle : 32 succès conservés, puis arrêt
  contrôlé pour répartir les 127 cas restants en 32/32/32/31, chacun dans son
  propre projet et sa propre base de test. Ces lots portent à 151 les succès
  distincts. Huit cas liés aux corrections ou aux assertions de l’ancien journal
  sont repris dans un environnement neuf, avec le parcours de prérequis affecté :
  reprise finale 9/9. Le wiki actualisé est ensuite vérifié séparément, 1/1.
  Aucun scénario manquant et aucune répétition comptée comme un nouveau succès.
- Les contrôles axe publics et applicatifs passent ; parcours clavier, noms
  accessibles, états, petits écrans et mises à jour sont vérifiés. Cela ne
  constitue pas un audit complet de conformité RGAA.
- Prévisualisation : trois pages sur cinq largeurs, navigation et dialogue vérifiés.
- git diff --check : réussi avant commit.
- Aucune migration de la base de jeu ; les migrations courantes sont appliquées
  uniquement aux bases jetables des tests navigateur.

Corrections issues de ces vérifications :
- Le focus explicite est limité aux liens de prérequis. Le retour après une
  commande ne bloque plus le rafraîchissement automatique en gardant le focus
  dans la zone à actualiser. Complexité navigation réévaluée de 2 à 3.
- Après retrait du journal, les tests vérifient toujours les états des vols,
  le trajet retour, leur disparition du suivi actif et l’historique réel des
  missions terminées. Les états vides sont vérifiés dans le suivi conservé.
- Le parcours clavier général avait atteint 60 secondes sous charge parallèle ;
  il passe seul en 39,1 secondes, avec le même délai et les mêmes assertions.
- Le wiki corrige sa description ancienne des recherches par planète :
  la file était déjà commune à l’univers, comportement confirmé par les tests.

Les six contrôles locaux ont été exécutés et examinés. La couverture navigateur
est consolidée après reprises ; ce n’est pas une unique exécution monolithique.
Publication, PR, CI GitHub, intégration et déploiement non effectués.
La CI complète sur la dernière version de la PR reste obligatoire avant fusion.

## Fichiers du lot A

- Classements : app/src/Controller/LeaderboardController.php,
  app/src/Service/Player/LeaderboardService.php,
  app/src/Service/Player/DailyLeaderboard.php,
  app/templates/player/leaderboard.html.twig,
  app/templates/ui/ranking_change.html.twig, app/assets/styles/app.css.
- Catalogues : app/src/Service/Colony/CatalogueDetails.php,
  app/assets/controllers/building_catalog_controller.js,
  app/templates/ui/building_cards.html.twig,
  app/templates/ui/catalogue_info.html.twig,
  app/templates/ui/catalogue_benefits.html.twig. Le focus est demandé explicitement
  par les liens de prérequis afin de conserver le rafraîchissement après construction.
- Autres interfaces : app/templates/colony/show.html.twig,
  app/templates/fleet/index.html.twig, app/templates/admin/missions.html.twig,
  app/templates/ui/message_report.html.twig, app/templates/ui/flights.html.twig,
  app/assets/styles/messaging.css.
- Tests : app/tests/Integration/DailyLeaderboardTest.php,
  app/tests/browser/leaderboard-contact.spec.mjs,
  app/tests/browser/catalogue-details.spec.mjs,
  app/tests/browser/fleet-empty-state.spec.mjs,
  app/tests/browser/admin-accounts-flights.spec.mjs, app/tests/browser/colony.spec.mjs,
  app/tests/browser/espionage-report.spec.mjs, app/tests/browser/orbit-request.spec.mjs,
  app/tests/browser/ui-state-coverage.spec.mjs et
  app/tests/browser/support/game-fixtures.mjs.
- Vérifications : Makefile, avec 512 Mo limités au processus PHPUnit pour éviter
  l’épuisement de mémoire de la suite complète, sans changement de PHP pour le jeu.
- Documentation : README.md, docs/README.md, docs/REGLES.md, docs/DECISIONS.md,
  docs/FEUILLE_DE_ROUTE.md, docs/wiki.json et le présent suivi.

La prévisualisation reste dans le dossier dédié au chat, hors du code du jeu.
