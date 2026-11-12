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
| A — présentation/classement | Retirer la mention horaire de l’aperçu | 1 | Fusionné dans dev, PR #41 |
| A | Cinq catégories, rangs, variations et pagination globale | 3 | Fusionné dans dev, PR #41 |
| A | Prérequis navigables au clavier ; retrait des accès associés des cartes/fiches | 3 | Fusionné dans dev, PR #41 |
| A | Triangle de signalement privé/guilde et colonnes origine/destination admin | 2 | Fusionné dans dev, PR #41 |
| A | Retirer le journal de mission en conservant suivi et historiques | 1 | Fusionné dans dev, PR #41 |
| B — carte/confidentialité | Cercle vert des systèmes possédés ; retour planète vers son système | 2 | Fusionné dans dev, PR #42 |
| B | Masquer les sondes entrantes au serveur ; résumé des flottes tierces visibles | 4 | Fusionné dans dev, PR #42 |
| C — colonies/journal | Acquisition sur toutes les voies de transfert ; ordre commun | 4 | Fusionné dans dev, PR #43 |
| C | Journal d’absence, consultation explicite, détails clavier/tactiles | 4 | Fusionné dans dev, PR #43 |
| D — destinations | Trois modes, découvertes, univers, suggestions de pseudonyme | 4 | Fusionné dans dev, PR #44 |
| D | Attaque PNJ, départ/arrivée et liens depuis la carte | 4 | Fusionné dans dev, PR #44 |
| E — administration/rapports | Attribution auditée de vaisseaux, planète/type/rang/quantité | 4 | Fusionné dans dev, PR #45 |
| E | Archive/restauration personnelle, sélection et conservation des preuves | 4 | Fusionné dans dev, PR #45 |
| F — guildes/signalements | Tag obligatoire à la création, unicité par univers et classement | 3 | Vérifié sur bases isolées, publication à autoriser |
| F | Signalement d’un joueur depuis le classement | 4 | Vérifié sur bases isolées, publication à autoriser |
| G — rappel | Missions, trajet partiel, carburant/fret, verrouillage, origine perdue | 5 | Décisions validées, à réaliser |
| H — détection | Analyse tactique, visibilité des mouvements et détection à l’arrivée | 5 | Décisions validées, à réaliser |
| I — orbites | Demande au départ, accords, attente et changements diplomatiques | 5 | Décisions validées, à réaliser |
| J — abandon | Dernière colonie, actifs/files, restrictions et confirmation | 5 | Décisions validées, à réaliser |
| K — refontes | Diplomatie, Profil, Inventaire : maquettes adaptatives, puis validation | 3 | Maquettes validées ; intégration non commencée |
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

Les maquettes Diplomatie, Profil et Inventaire sont validées par l’utilisateur ;
leur intégration n’a pas commencé.
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


## Publication du lot A et démarrage du lot B

L’utilisateur valide les trois maquettes puis autorise le push, la PR vers dev et
la fusion uniquement après CI complète verte, suivie du lot B.
La validation du formulaire du connecteur n’avait pas créé de PR côté GitHub ;
la création a été faite via l’accès Git autorisé, après vérification anti-duplication.

[PR #41](https://github.com/c41m07/genesis-reborn/pull/41), commit testé
7fe5f1d386ce875e07254f7ae717ce4da908fb5c.
[CI complète](https://github.com/c41m07/genesis-reborn/actions/runs/43107155705) :
les cinq jobs quality et GitGuardian réussissent ; les étapes de sauvegarde et
restauration isolées sont confirmées réussies. Publish est ignoré normalement
sur une PR. Protection dev relue : PR requise, branche à jour, cinq jobs requis,
règles appliquées aux administrateurs.

Fusion vérifiée dans dev : b58b3b41ca121649093248ba0539921ae30da3ab.
Aucun déploiement Alpha effectué. Le dépôt est propre avant le nouveau lot.

### Analyse ciblée du lot B

Branche feature/carte-confidentialite-flottes, issue de dev actualisée à b58b3b4.
- Navigation et cercle : complexité 2. La croix de la fiche planète appelle
  closePlanet puis toGalaxy. Le nouveau bouton dans cette fiche doit appeler
  un retour distinct au système réel de la planète, avec restauration du focus
  et du cadrage galaxie ; la croix garde son retour galaxie.
- Confidentialité : complexité 4. Les restrictions sont appliquées dans mapData
  avant création du JSON et du journal HTML. Une sonde est visible par son seul
  propriétaire. Les autres critères d’accès existants restent appliqués :
  univers, propriétaire, destination possédée et alliance. Aucun nouveau groupe
  de flottes n’est rendu visible.
- Résumé : quantité totale et points des seuls vaisseaux positifs de la flotte,
  coûts de son univers et de ses rangs ; division par 100 après totalisation.
  Réutilisation de unitCost du classement, sans défense fixe ni fret.
  Composition complète avec rangs réservée au propriétaire.
- Données : lecture seule, aucun changement d’état ni migration. Les transactions,
  arrivées, carburant, captures et détections d’espionnage à l’arrivée restent
  hors de ces modifications. Le préavis d’attaque relève du lot H.
- Risques : fuite dans le JSON même si le HTML masque les types, divulgation
  aux alliés ou au destinataire d’une sonde, mauvaise portée d’univers,
  arrondis par unité au lieu de la flotte, changement de couleurs des quarts,
  retour au mauvais système ou perte du focus sur mobile.
- Régressions : tests HTTP pour propriétaire/destinataire/allié/tiers,
  aller et retour des sondes, contexte d’univers adverse, systèmes inconnus,
  points multi-rangs avec surcharge de catalogue et défenses exclues ;
  contrôle du cercle réellement dessiné et du bouton au clavier.
  La première passe serveur réussit : 3 tests, 57 assertions.
  La suite serveur complète réussit ensuite : 706 tests PHP, 210052 assertions
  et 13 tests Python. Validation Compose, lint/schéma, compilation et contrôle
  du diff réussis. Les 25 tests de transitions passent après adaptation de
  l’ancienne fixture de dessin ; le parcours complet ajoute le retour tactile.
  Suite navigateur complète terminée, résultats ci-dessous ; publication non autorisée à ce stade.

### Fichiers du lot B

- app/assets/controllers/galaxy_v2_controller.js, app/assets/styles/galaxy-v2.css
  et app/templates/universe/map_v2.html.twig : cercle, légende, bouton et focus.
- app/src/Controller/ColonyController.php : filtre des sondes et données de flotte.
- app/src/Service/Player/LeaderboardService.php : calcul réutilisant les coûts existants.
- app/tests/Controller/GalaxyFleetVisibilityTest.php et les deux scénarios
  galaxy-transition.spec.mjs / galaxy-v2.spec.mjs : confidentialité, calcul et navigation.
- README.md, docs/REGLES.md, docs/DECISIONS.md, docs/wiki.json,
  docs/FEUILLE_DE_ROUTE.md et le présent suivi : règles, aide et état réel.

### Validation finale locale du lot B — 12 novembre 2026

Les six contrôles requis ont été exécutés et leurs résultats examinés :
- make validate : réussi.
- make lint : réussi, y compris schéma et mapping.
- make test : 706 tests PHP, 210052 assertions ; 13 tests Python réussis.
- make assets (npm run build) : compilation réussie.
- BROWSER_PERSISTENCE=1 make test-browser : 161 scénarios distincts réussis,
  couverture exacte du manifeste vérifiée. Quatre groupes isolés : 41/41,
  39/40, 40/40 et 40/40 ; le seul échec initial est le dépassement de la durée
  globale de 60 secondes du parcours clavier général pendant le parallélisme.
  Reprise seule, sans modification ni assouplissement : réussite en 45,4 secondes
  (46,5 secondes pour le lancement Playwright). Aucun scénario manquant.
- git diff --check : réussi.

Les nouveaux parcours contrôlent le cercle réellement dessiné, le bouton dans
la fiche planète, le retour au système au clavier et au toucher, la réouverture
du panneau mobile et le focus sur la planète consultée. La croix conserve son
retour galaxie. Les contrôles ciblés d’accessibilité et les scénarios généraux
réussis ne constituent pas un audit complet de conformité RGAA.

Rapports locaux : /tmp/genesis-lot-b-batch-1.json à -4.json,
/tmp/genesis-lot-b-keyboard-retry.json et /tmp/genesis-lot-b-coverage.json.
Le lot est prêt pour un commit local ; aucun push, aucune PR, aucune CI GitHub
du lot B et aucun déploiement Alpha ne sont annoncés comme réalisés.
La publication du lot B attend l’accord explicite prévu par les consignes.

## Intégration du lot B et préparation du lot C — 12 novembre 2026

Le lot B est fusionné par la [PR #42](https://github.com/c41m07/genesis-reborn/pull/42)
dans dev, commit fe39a001340548e32ad0b52ef00f686f5a004bd6.
La CI complète [43120982563](https://github.com/c41m07/genesis-reborn/actions/runs/43120982563)
réussit sur le commit 3efa1a82d3a27ff90b94b8a428254866f140066e : les cinq jobs
quality, les étapes Compose/diff/assets/PHP/Python/lint/schéma et sauvegarde/
restauration isolées sont verts. GitGuardian réussit ; publish est normalement
absent des PR (skipped). Protection de dev revérifiée avant fusion.
Aucun déploiement Alpha.

Le lot C part de cette dev actualisée sur feature/colonies-journal-absence.
- Acquisition et ordre commun : complexité 4. Quatre transferts réels identifiés :
  arrivée dans l’univers, colonisation, conquête PNJ et capture après occupation.
  Préserver les transactions, verrous, conditions et gardes de rejeu existants.
  Trier les dates connues puis départager par identifiant ; conserver d’abord
  l’ordre par identifiant des anciennes dates inconnues, signalé comme approximatif.
- Journal : complexité 4. Distinguer l’ouverture de la vue générale des requêtes
  automatiques. Consultation explicite côté serveur, contrôles propriétaire/univers
  et CSRF ; ne pas consulter des événements non affichés ni supprimer l’historique.
  Inclure seulement les catégories validées, avec données de notification existantes.
- Détails privés : complexité 3. Stocks calculés en lecture seule, unités/rangs
  de ses planètes et composition/fret de ses flottes. Survol, clavier et toucher.
  Réutiliser les composants des colonies et vols sans exposer de données tierces.
- Schéma : migration additive Version20261112010000 préparée, sans reprise de dates
  fictives ni suppression. Six colonnes nullables et deux index, répartis entre
  planet, player_universe et notification. Aucune application à la base de jeu.
  L’application sur base existante exige accord explicite et sauvegarde vérifiée.
- Reprise du journal : en l’absence de date de visite historique, proposition d’un
  point de départ à la première ouverture après mise à jour ; les anciennes
  notifications restent conservées sans être présentées comme une absence connue.
  Ce choix de transition reste à confirmer avant intégration du journal.

### État de reprise du lot C

- Détails intégrés : EmpireDetails (données limitées à la participation et l’univers),
  ColonyController, FleetController et ColonyView ; partial ui/empire_details,
  intégration aux cartes Planètes et ui/flights ; contrôleur hover_details,
  stylesheet empire-details et import app.js.
- Tests : EmpireDetailsTest (1 test, 15 assertions) vérifie confidentialité, rangs,
  fret et absence d’écriture des stocks ; empire-details.spec.mjs (1 scénario)
  vérifie survol, clavier, focus, Échap et appui tactile à 390 px.
- La migration préparée s’exécute dans le projet navigateur jetable ; les 25
  scénarios galaxy-transition réussissent. Aucun champ du modèle n’a encore été
  ajouté : les composants actifs restent compatibles avec la base de jeu existante.
- make assets et make lint (mapping/schéma inclus) réussis. Ces contrôles sont
  ciblés : ni suite complète du lot C, ni CI GitHub, ni publication.
- Deux réponses attendues : autorisation de sauvegarde vérifiée puis migration
  locale ; transition du journal à sa première ouverture sans inventer de visite.
  Aucun de ces choix n’est déduit du simple accord pour poursuivre le lot C.
- À reprendre après ces réponses : modèles et acquisition sur les quatre voies,
  ordre partagé et indication approximative ; catégories de notification,
  fenêtre d’absence et consultation explicite serveur ; tests de concurrence,
  accès et actualisation, puis les six contrôles avant tout commit.
- README, règles, décisions, feuille de route et wiki intégré synchronisés pour
  la partie réellement intégrée. Aucun document Work externe n’a été rendu
  accessible ; aucun wiki distant ni fichier Figma n’a été modifié dans ce lot.

### Reprise autorisée et implémentation du lot C

Les deux réponses sont explicites : sauvegarde/vérification/migration locale
autorisées ; suivi du journal à la première ouverture confirmé.
La sauvegarde dédiée est sous var/backups/lot-c-20261112 (archive et manifeste),
vérifiée deux fois par backup-tools. Aucun ancien backup purgé. La seule migration
en attente était Version20261112010000 ; make migrate l’a appliquée localement.
Comptages inchangés : 720 planètes, 40 lignes de ressource, 15 flottes,
89 notifications. Logs locaux /tmp/genesis-lot-c-backup.log et -migrate.log.

Les modèles, acquisitions des quatre parcours, tri et présentation commune sont
implémentés. Les anciennes dates restent NULL, aucun remplissage arbitraire.
JournalService et les catégories explicites traitent le suivi par participation.
La première ouverture retire la seule catégorie de suivi des notifications déjà
présentes ; les événements précédents restent non consultés et non supprimés. Les événements de la même seconde que le démarrage ne
sont pas perdus par une comparaison de dates tronquées. Mercure transporte le
booléen journal ; le toast conserve sa fonction distincte.
Les actualisations portent X-Genesis-Live ; elles ne visitent pas le journal.
Seule la consultation protégée marque les événements effectivement affichés.
Les verrous et UPDATE conditionnels préservent les notifications nouvelles et
rendent un rejeu idempotent. Pagination de 50, historique non supprimé.

Les 16 tests ciblés du journal, des dates et de la capture réussissent avec
127 assertions. Les erreurs initiales étaient des références de fixtures
détachées entre requêtes et une comparaison d’identité d’objets DateTime après
rechargement ; corrections des fixtures, avec rejeu après délai pour la date.
Les refus CSRF, identifiant tiers et changement d’univers sont vérifiés.
Les vérifications complètes sont en cours ; aucun commit/push/PR du lot C.

Revue renforcée de l’initialisation : ne pas conditionner la catégorie à une
lecture de journal_started_at dans une ancienne transaction Repeatable Read.
JournalService, déjà sous verrou de participation, retire la catégorie des
notifications présentes lors de la première ouverture ; NotificationService
catégorise les événements suivants sans requête ni verrou supplémentaire.
Les notifications anciennes ne sont ni consultées ni supprimées. data retourne
un état vide avant initialisation ; le booléen Mercure reste distinct du toast.
Ce raffinement est revérifié par la suite serveur et les parcours ciblés.

### Fichiers du lot C

- README.md
- app/assets/app.js
- app/assets/controllers/colony_live_controller.js
- app/assets/controllers/events_controller.js
- app/assets/controllers/hover_details_controller.js
- app/assets/controllers/menu_notifications_controller.js
- app/assets/styles/empire-details.css
- app/migrations/Version20261112010000.php
- app/src/Controller/ColonyController.php
- app/src/Controller/FleetController.php
- app/src/Entity/Notification.php
- app/src/Entity/Planet.php
- app/src/Entity/PlayerUniverse.php
- app/src/Repository/GameRepository.php
- app/src/Service/Colony/ColonyView.php
- app/src/Service/Colony/EmpireDetails.php
- app/src/Service/Colony/GameShell.php
- app/src/Service/Combat/CombatEventService.php
- app/src/Service/Combat/OccupationCaptureService.php
- app/src/Service/Fleet/EspionageService.php
- app/src/Service/Fleet/FleetService.php
- app/src/Service/Fleet/TransportService.php
- app/src/Service/Notification/JournalService.php
- app/src/Service/Notification/NotificationService.php
- app/src/Service/Notification/OutboxRelay.php
- app/src/Service/Queue/QueueService.php
- app/src/Service/Universe/UniverseService.php
- app/templates/base.html.twig
- app/templates/colony/planets.html.twig
- app/templates/colony/show.html.twig
- app/templates/ui/colony_summaries.html.twig
- app/templates/ui/empire_details.html.twig
- app/templates/ui/flights.html.twig
- app/tests/Controller/AbsenceJournalTest.php
- app/tests/Controller/EmpireDetailsTest.php
- app/tests/Integration/GamePersistenceTest.php
- app/tests/Integration/OccupationCaptureTest.php
- app/tests/Unit/PlanetAcquisitionTest.php
- app/tests/browser/absence-journal.spec.mjs
- app/tests/browser/empire-details.spec.mjs
- app/tests/browser/leaderboard-contact.spec.mjs
- app/tests/browser/live-refresh-focus.spec.mjs
- app/tests/browser/ui-state-coverage.spec.mjs
- docs/ARCHITECTURE.md
- docs/DECISIONS.md
- docs/EVOLUTIONS_INTERFACES_FLOTTES.md
- docs/FEUILLE_DE_ROUTE.md
- docs/REGLES.md
- docs/WORKER_NOTIFIER.md
- docs/wiki.json

La sauvegarde locale reste ignorée par Git et ne fait pas partie de cette liste.

Le parcours Conversations vérifie toujours les messages non lus et leurs témoins.
Sa dernière étape simulait une notification sans catégorie puis attendait une
consultation automatique en ouvrant l’accueil : cette attente est remplacée par
la catégorie queue, le booléen journal du flux et la consultation explicite.
Le témoin doit rester visible à l’ouverture, puis disparaître après cette action.
Les assertions de messagerie restent intactes.

Le test de focus live utilisait l’ancien texte d’état vide du journal ; il vérifie
maintenant le nouvel état d’absence. Ses contrôles du focus conservé, puis du
remplacement après sortie de la zone, restent inchangés. Reprise ciblée nécessaire.

### Validation finale locale du lot C — 12 novembre 2026

Les six contrôles sont réellement exécutés et leurs résultats examinés :
- make validate : réussi.
- make lint : réussi, y compris mapping et schéma de la base locale migrée.
- make assets (npm run build) : compilation réussie.
- make test : 713 tests PHP, 210134 assertions et 13 tests Python réussis sur
  la version finale. Première suite globale avant simplification : également
  réussie (210131 assertions) ; reprise justifiée par le risque de lecture
  périmée au tout premier démarrage et trois assertions ajoutées.
- BROWSER_PERSISTENCE=1 make test-browser : 163 scénarios distincts réussis.
  Quatre groupes principaux : 39/41, 40/41, 40/40 et 40/40 initialement ;
  les trois attentes liées à l’ancien journal sont corrigées puis reprises
  avec succès : Conversations, focus live et couverture générale des écrans.
  Aucun contrôle de messagerie, de focus ou de chargement n’est supprimé.
  Cinquième groupe sans concurrence : journal d’absence, construction avec
  notification Mercure réelle et clavier général, tous réussis (3/3).
  Le rapprochement des rapports couvre exactement les 163 cas attendus,
  sans cas manquant ni supplémentaire.
- git diff --check : réussi ; contrôle des nouveaux fichiers préparé avant commit.

Rapports : /tmp/genesis-lot-c-batch-1.json à -5.json et les trois rapports
-conversations.json, -live-focus.json, -ui-state.json ; rapprochement
/tmp/genesis-lot-c-coverage.json. Suites serveur et lint finaux :
/tmp/genesis-lot-c-php-final.log et /tmp/genesis-lot-c-lint-final.log.

Le nouveau test du journal attend le chargement de la page avant d’émettre
son événement simulé et vérifie le débordement après stabilisation du chargement.
Les assertions d’absence de défilement horizontal et les actions clavier/tactiles
restent exigées. Relecture visuelle de la capture mobile des colonies à 390 px :
noms longs lisibles, données d’ancienneté et accès aux détails présents.
Les contrôles ciblés (contrastes, noms accessibles, focus, clavier, toucher et
tailles d’écran) réussissent, sans prétendre à un audit complet RGAA.

La sauvegarde locale est vérifiée ; la migration est appliquée sur cette seule
base avec comptages inchangés. Worker, relay et notifier sont rechargés sur le
code final et tous les services locaux sont healthy.
Le lot est validé localement. Aucun push, PR, CI GitHub du lot C ni déploiement
Alpha n’est réalisé ; publication soumise à l’accord explicite du dépôt.

## Lot C intégré et analyse du lot D — 12 novembre 2026

Le lot C est fusionné dans dev via PR #43 au commit
607971469628ca4155c821b18a370c4be53b43ff. La CI complète 43134524413 est réussie
sur 86978c21699873e92909b28af2251c1b9ff2c725 : cinq jobs quality, sauvegarde/
restauration isolées, Compose, diff, assets, tests et lint/schéma vérifiés.
Protection de dev avec PR et cinq jobs obligatoires revérifiée. Aucun Alpha.

Le lot D part de dev actualisée, branche feature/destinations-flottes-pnj.
- Catalogue et suggestions : complexité 4 (confidentialité et dépendances de mission).
  Seulement les planètes découvertes dans l’univers actif, avec propriétaire/
  diplomatie et conditions de mission existantes. Mes planètes/alliés par défaut,
  ennemis/PNJ dans Ennemis, joueurs neutres dans Destination personnalisée.
  Suggestions limitées à la recherche de pseudonyme de destination, 20 résultats.
- Formulaire et accessibilité : complexité 4. Trois positions, parcours clavier et
  tactile, annonces des résultats et gestion des requêtes obsolètes ; maintien de
  la prévisualisation, du fret, des rangs et du contexte prérempli depuis la carte.
  Compatibilité des soumissions historiques sans mode explicite conservée.
- Attaques PNJ : complexité 4. Besoin absent côté serveur, pas seulement inaccessible
  dans l’interface. Choix explicite confirmé : deux missions ; Attaque sans capture
  ni nouveau butin PNJ, Conquête JcE avec ses règles actuelles. Initialiser la garnison
  une seule fois sous verrou, revérifier l’autorisation à l’arrivée, conserver les
  gardes de rejeu et la distinction entre attaque et conquête après combat.
- Données : aucune migration prévue. Conserver les transactions, protections,
  coûts, règles de carburant, cargaison, slots et conquête existants.
- Risques ciblés : suggestions d’un autre univers ou de planètes inconnues, recherche
  générique réutilisée sans filtre de découverte, destination ancienne après changement
  de mode/mission, UI autorisant une capture via Attaque, réinitialisation de garnison,
  sélection d’une origine ou d’un allié incompatible, rupture du wizard/preview.

Le contrôle GameplayAccess a identifié une dépendance à préserver : l’ouverture
d’un formulaire prérempli de transport vers une planète connue reste autorisée,
même si son destinataire refuse actuellement les livraisons. canPrefill conserve
cet accès en lecture ; allows filtre les listes/suggestions et la prévisualisation/
l’envoi vérifient toujours les droits métier. Le préremplissage incompatible reste
visible en mode personnalisé pour expliquer le refus, sans donner de droit d’envoi.
Le test existant n’est pas assoupli.

### Implémentation et premiers contrôles du lot D

Catalogue serveur et route JSON privés, trois positions natives et contrôleur
de suggestions ; wizard/preview/envoi utilisent le champ du mode actif.
Compatibilité coordinates des anciens clients conservée. Les profils neutres
restent personnalisés, y compris les mondes d’origine PNJ déjà capturés.
Une relation incohérente entre propriétaire et univers est filtrée avant données.

Les contrôles ciblés passent :
- 32 tests combat/occupation, 314 assertions : victoire classique avec colonie
  survivante sans capture/occupation/butin, garnison initialisée une fois,
  arrivée et résolution rejouées sans duplication ; conquêtes existantes préservées.
- 14 tests de destinations/accès, 154 assertions : groupes, recherches littérales,
  découverte, univers, modes forgés, accès anonyme, préremplissages historiques.
- Wizard et état du bouton de prévisualisation : réussis après choix explicite
  du mode personnalisé dans les parcours de coordonnées.
- Deux nouveaux parcours : trois modes + suggestions clavier/toucher avec rejet
  de l’ancienne liste après changement de mission ; envoi d’une attaque PNJ via
  Ennemis avec nombre total de vaisseaux conservé et aucune capture déclenchée.
- Build et lint ciblés réussis. Suites complètes non encore annoncées.

Les anciens parcours qui saisissent des coordonnées choisissent désormais
Destination personnalisée avant la saisie ; leurs actions d’envoi et assertions
métier restent conservées. Aucun test de protection n’est supprimé ou affaibli.

La première suite complète a relevé une anomalie du montage négatif : le propriétaire
d’un autre univers était injecté dans tous les cas, déclenchant le classement
partagé lors des rendus HTML (données volontairement incohérentes). L’injection
est limitée au test JSON de confidentialité, où le catalogue doit la rejeter.
Les contrôles positifs utilisent les relations valides du jeu ; la garde du
catalogue reste testée et aucun code de classement hors périmètre n’est modifié.
Aucune anomalie similaire n’est annoncée comme constatée dans la base de jeu.
### Validation finale locale du lot D — 12 novembre 2026

Les six contrôles sont exécutés et examinés :
- make validate : réussi.
- make lint : réussi, mapping et schéma inclus ; relancé avec succès après
  l’ajustement final du template.
- make assets (npm run build) : réussi, recompilé après l’ajustement visuel.
- make test : 718 tests PHP, 210198 assertions et 13 tests Python réussis.
- BROWSER_PERSISTENCE=1 make test-browser : 165 scénarios distincts réussis.
  Quatre groupes principaux de 41 cas chacun ; puis cinq parcours sans
  concurrence sur la présentation finale (deux destinations/PNJ, preview,
  wizard et clavier général). Quatre cas sont revérifiés après le seul
  ajustement CSS/template. Rapprochement des cinq rapports : exactement
  165 cas attendus, aucun manquant ou supplémentaire.
- git diff --check : réussi, puis contrôle des fichiers préparés avant commit.

Rapports locaux : /tmp/genesis-lot-d-batch-1.json à -5.json ; couverture
/tmp/genesis-lot-d-coverage.json ; suites /tmp/genesis-lot-d-php-final.log,
-lint-final.log et -assets-final.log. La suite initiale a échoué sur la fixture
négative décrite ci-dessus ; la suite finale complète réussit après sa correction.

Relecture réelle de destinations-mobile.png à 390 px : choix alignés, texte
lisible, focus visible, suggestions tactiles et absence de débordement.
Les contrôles ciblés de noms accessibles, navigation clavier, focus, contrastes,
annonces et toucher sont vérifiés ; ils ne constituent pas un audit complet RGAA.
Aucune migration ni dépendance du lot D ; aucune modification des logos/SVG.
Aucun push, PR, CI GitHub du lot D ni déploiement Alpha : publication à autoriser.
Les trois documents Work externes cités en début de suivi restent inaccessibles ;
aucun wiki distant ni fichier Figma modifié. Les références et le wiki intégrés
sont synchronisés ; leur parcours navigateur réussit.


### Fichiers du lot D

- README.md
- app/assets/app.js
- app/assets/controllers/fleet_destination_controller.js
- app/assets/controllers/fleet_wizard_controller.js
- app/assets/styles/fleet-destinations.css
- app/src/Controller/FleetController.php
- app/src/Service/Combat/CombatEventService.php
- app/src/Service/Fleet/DestinationCatalogue.php
- app/templates/fleet/index.html.twig
- app/templates/ui/fleet_destination.html.twig
- app/tests/Controller/FleetDestinationTest.php
- app/tests/Integration/OccupationStartTest.php
- app/tests/browser/colony.spec.mjs
- app/tests/browser/conquest-report.spec.mjs
- app/tests/browser/espionage-report.spec.mjs
- app/tests/browser/fleet-destinations.spec.mjs
- app/tests/browser/fleet-preview-submit-state.spec.mjs
- app/tests/browser/fleet-wizard.spec.mjs
- app/tests/browser/orbit-request.spec.mjs
- app/tests/browser/quantity-max.spec.mjs
- docs/ARCHITECTURE.md
- docs/DECISIONS.md
- docs/EVOLUTIONS_INTERFACES_FLOTTES.md
- docs/FEUILLE_DE_ROUTE.md
- docs/REGLES.md
- docs/wiki.json

## Lot D intégré et plan du lot E — 12 novembre 2026

Lot D fusionné via PR #44, commit 63e380f41d9ce6a55a0f8ef86dc3000a9d25b199.
CI complète 43169578878 réussie sur 760662195248847bd78c3791bd570a2ec0475cf3 :
cinq jobs quality et GitGuardian verts, sauvegarde/restauration isolées vérifiées.
Protection stricte de dev et PR obligatoire vérifiées. Aucun déploiement Alpha.

Lot E depuis cette dev actualisée, branche feature/administration-vaisseaux-archives.
- Attribution administrative : complexité 4. Réutiliser les verrous et reçus
  existants ; contrôler rôle administrateur actif, joueur/propriétaire/univers,
  type/rang du catalogue et limites de stockage entier. Créditer seulement le
  stock au sol, avec motif, confirmation, audit avant/après et rejeu sans effet.
  Aucun coût ni prérequis de construction modifié.
- Rapports : complexité 4. Archive personnelle distincte des preuves conservées.
  Vérifier les droits existants de chaque rapport et l’univers côté serveur.
  Transactions et verrou de participation pour archive/restauration idempotentes.
  Tri chronologique commun aux trois types avant pagination ; filtres et vues
  archives conservés en navigation ; actions individuelles ou par sélection.
- Données : table additive report_archive (participation, type, identifiant,
  date d’archivage nullable), unicité par participation/type/rapport. Aucune
  suppression de rapport. Préparer la migration avant de demander l’application
  locale, avec sauvegarde vérifiée ; aucun accord local du lot C réutilisé.
- Tests : autorisations, propriété changée, rang/type/quantité invalides,
  dépassement entier, rejeu et audit ; archives isolées par destinataire/univers,
  refus partiel atomique, restauration, tri mixte/pagination et preuves intactes.
  Vérifier clavier, focus, libellés, états vides et toucher.

### État de reprise du lot E

Les routes, services, formulaires et styles sont ajoutés. L’archive se filtre avant
le tri/pagination communs aux trois types ; les rapports source restent intacts.
La migration locale est autorisée, puis appliquée après deux vérifications de
l’archive/manifeste : var/backups/lot-e-20261112 (ignoré par Git).
Comptages avant/après identiques : 720 planètes, 40 ressources, 15 flottes,
12 lignes de vaisseaux, 3 combats, 4 espionnages, 1 transport et 6 participations.
make lint initial (schéma compris), make assets et make validate réussis.
Tests ciblés en cours. Les deux reprises du montage de test concernent la flotte
obligatoire d’un participant, la redirection CSRF existante et la soumission
de cases à cocher groupées par le client de test ; les contrôles ne sont pas affaiblis.
Les documents Work V4/V2/V1 restent inaccessibles ; aucun wiki distant ni Figma modifié.

Après les reprises du client de test, complexité réévaluée et maintenue à 4 :
les dépendances session/CSRF et les fixtures complètes sont désormais vérifiées.
34 tests ciblés réussissent (162 assertions). Le parcours navigateur des archives
réussit ; celui d’attribution/rejeu réussit après remplacement de la requête HTTP
Node (résolution *.localhost absente) par fetch dans le navigateur configuré.
Les assertions de stock inchangé et d’un seul audit restent exigées.

Suite serveur finale : 738 tests PHP, 210294 assertions, 13 tests Python réussis.
make lint final réussit avec schéma à jour ; make assets final et make validate
réussissent. Suites navigateur complètes en cours ; aucun commit/push du lot E.

### Validation finale locale du lot E — 12 novembre 2026

Les six contrôles sont exécutés et leurs résultats examinés :
- make validate : réussi.
- make lint : réussi, mapping et schéma de la base locale migrée inclus.
- make assets (npm run build) : réussi, recompilé après le seul ajustement final
  de taille du texte et de hauteur des boutons des archives.
- make test : 738 tests PHP, 210294 assertions et 13 tests Python réussis.
- BROWSER_PERSISTENCE=1 make test-browser : 167 scénarios distincts réussis.
  Quatre groupes principaux : 42/42, 42/42, 41/41 et 41/41.
  Dernier groupe sans concurrence : clavier général et les deux parcours du lot E,
  tous réussis sur les styles finaux (3/3). Les deux parcours répétés ne sont pas
  comptés deux fois ; rapprochement exact des 167 cas attendus, sans manquant ni extra.
- git diff --check : réussi ; contrôle des fichiers préparés avant commit.

Rapports locaux : /tmp/genesis-lot-e-batch-1.json à -5.json et
/tmp/genesis-lot-e-coverage.json. Suites serveur/lint/assets finales :
/tmp/genesis-lot-e-php-final.log, -lint-final.log et -assets-final.log.
Les reprises ciblées initiales sont décrites ci-dessus ; aucune assertion métier
n’a été supprimée ou affaiblie, la suite finale complète réussit.

Relecture des captures mobiles à 390 px : formulaire d’attribution lisible,
archives sans débordement, boutons avec texte de 14 px et hauteur minimale 44 px.
Navigation clavier générale, noms accessibles, focus, contraste et parcours tactiles
vérifiés de façon ciblée ; aucun audit complet RGAA n’est revendiqué.
Les archives restent accessibles via les mêmes permissions, les preuves intactes,
et l’attribution/rejeu ne produit qu’un crédit et une trace d’audit.
Le wiki intégré et les vues dans les deux thèmes passent leurs parcours.

Migration locale autorisée, sauvegarde/manifeste vérifiés deux fois avant
application, comptages inchangés. Aucun serveur Alpha touché, aucune dépendance
ajoutée, aucun logo ou SVG d’identité modifié.
Lot E prêt localement ; aucun push, PR ou CI GitHub du lot E.
Publication à autoriser avant le démarrage du lot F.
Les documents Work externes V4/V2/V1 restent inaccessibles (noms en début de suivi).
Aucun wiki distant ni Figma modifié.

### Fichiers du lot E

- README.md
- app/assets/app.js
- app/assets/styles/report-archives.css
- app/migrations/Version20261112020000.php
- app/src/Controller/AdminPlayerController.php
- app/src/Controller/CombatReportController.php
- app/src/Entity/ReportArchive.php
- app/src/Service/Admin/AdminInterventionService.php
- app/src/Service/Combat/ReportArchiveService.php
- app/templates/admin/_ship_intervention.html.twig
- app/templates/admin/player.html.twig
- app/templates/combat/reports.html.twig
- app/tests/Controller/ReportArchiveTest.php
- app/tests/Integration/AdminInterventionTest.php
- app/tests/browser/report-archives-ships.spec.mjs
- docs/ARCHITECTURE.md
- docs/DECISIONS.md
- docs/EVOLUTIONS_INTERFACES_FLOTTES.md
- docs/FEUILLE_DE_ROUTE.md
- docs/REGLES.md
- docs/wiki.json

## Lot E intégré et plan du lot F — 12 novembre 2026

Lot E fusionné via PR #45, commit bbbd72ed7e67904eb69c467d2b7bc1fae3886fbe.
CI complète 43177729961 réussie sur c964de8f7f8f5c12f84b020e6494a25491383a44 :
cinq jobs quality et GitGuardian verts, Compose, assets, tests, lint/schéma,
sauvegarde/restauration isolées vérifiés. Protection stricte et PR obligatoire
revérifiées. Aucun déploiement Alpha.

Lot F depuis cette dev actualisée : feature/tags-guildes-signalements-joueurs.
- Tags de guilde : complexité 3. Champ nullable pour les guildes anciennes,
  obligatoire à toute création, normalisé en majuscules (2 à 5 lettres/chiffres),
  unique par univers. Choix/mise à jour par le commandant, permissions existantes
  et verrou d’univers. Aucun tag historique inventé. [TAG] Commandant uniquement
  dans le classement ; pseudonymes et autres écrans préservés.
- Signalement joueur : complexité 4. Quatre motifs validés, commentaire, contexte
  serveur de la catégorie/rang consultés, univers, cible/pseudonyme, auteur/date.
  Contexte mémorisé en session au formulaire, non accepté depuis des champs forgés.
  POST CSRF, droits de participation/univers, reçu propre au formulaire pour éviter
  le doublon. Accès aux signalements réservé à la modération ; audit de consultation
  et traitement, preuves et contexte conservés. Aucune sanction automatique.
- Données : complexité 4. Ajouter guild.tag et une unicité univers/tag ; nouvelle
  table player_report distincte de message_report. Liens auteur/cible/modérateur
  nullables après suppression avec contexte conservé. Migration additive à préparer,
  puis accord local et sauvegarde vérifiée requis avant application.
- Risques/tests : créations anciennes sans tag, unicité et concurrence, rôles de
  guilde/transfert, tag vide/invalide, confidentialité, faux rang/catégorie/univers,
  rejeu, traitement concurrent et conservation des preuves. Adapter uniquement
  les fixtures de création aux nouvelles données obligatoires ; conserver leurs
  assertions métier. Vérifier clavier, focus, contrastes et toucher.

### État de reprise du lot F

Services, formulaires et modération ajoutés. Fixtures des huit fichiers PHP et
six parcours navigateur de création complétées avec un tag explicite, sans retrait
d’assertion. Le premier test de préfixe sélectionnait le premier commandant du
classement ; il cible maintenant la ligne du commandant concerné et conserve
l’exigence exacte [TAG] Commandant. Reprise : 64 tests, 207 assertions réussis.

make validate et make assets réussissent. Syntaxe PHP et trois templates ciblés
valides. Suites complètes serveur et parcours navigateur isolés en cours.
Aucune application de Version20261112030000 à la base locale : accord demandé,
encore attendu à ce stade. Aucun accord de migration des lots C/E n’est réutilisé.
Sources Work V4/V2/V1 toujours inaccessibles ; aucun wiki distant ni Figma modifié.

### Validation du lot F sur bases isolées — 12 novembre 2026

Les six contrôles sont exécutés et examinés :
- make validate : réussi.
- make lint : réussi sur database-test avec APP_ENV=test et la configuration de
  connexion des tests PHP, transmise seulement à cette commande. Syntaxe PHP,
  YAML, Twig, conteneur, mapping et synchronisation du schéma vérifiés.
  Les contrôles YAML/Twig/conteneur ont aussi réussi dans l’environnement habituel.
  Le premier essai isolé héritait encore de la connexion Compose : accès refusé
  au suffixe de base de test ; configuration explicite reprise depuis .env.test,
  sans changement de configuration globale ni migration sur la base de jeu.
- make assets (npm run build) : réussi sur les styles finaux, avant la suite navigateur.
- make test : suite complète réussie, 761 tests PHP, 210374 assertions, 13 tests Python.
  Complément final : 23 tests ciblés, 84 assertions ; quatre assertions ajoutées
  vérifient l’indépendance des curseurs de signalements messages/joueurs et
  l’absence de compteur privé pour un joueur ordinaire. Aucun code applicatif
  modifié après la suite serveur complète.
- BROWSER_PERSISTENCE=1 make test-browser : 170 scénarios distincts réussis.
  Groupes principaux 43/43, 42/42, 42/42, 42/42 ; clavier général seul 1/1.
  Les trois nouveaux parcours passent sur les styles finaux. Rapprochement exact
  des 170 cas attendus, sans scénario manquant ou supplémentaire.
- git diff --check : réussi ; contrôle des fichiers préparés avant commit.

Rapports : /tmp/genesis-lot-f-batch-1.json à -5.json,
-coverage.json, -php-final.log, -context-notifications.log,
-lint-isolated-final.log et -assets-final.log.
Le scénario de signalement attendait initialement un nouveau joueur absent de
l’instantané quotidien. Sa fixture expire l’instantané comme les tests de classement
existants ; aucun calcul quotidien ni assertion métier n’est modifié ou affaibli.

Relecture réelle à 390 px des captures de tag ancien et signalement : libellés,
boutons lisibles (14 px, hauteur minimale 44 px), focus et navigation clavier,
commentaire cohérent avec les couleurs du jeu et utilisation tactile vérifiés.
Les contrôles ciblés d’accessibilité ne constituent pas un audit complet RGAA.
Grades, traités, signalements de messages et wiki intégré restent vérifiés par
leurs scénarios existants.

Version20261112030000 est appliquée uniquement dans les bases jetables des tests.
Aucune sauvegarde/migration du lot F sur la base de jeu locale : l’autorisation
explicite demandée reste attendue. Son application devra être précédée d’une
sauvegarde vérifiée et suivie du contrôle du schéma local. Les anciennes guildes
devront garder tag NULL ; aucun tag ne sera inventé. Aucun Alpha concerné.

Code du lot F vérifié sur bases isolées ; aucun push, PR ou CI GitHub du lot F.
La publication et la migration de la base de jeu restent deux actions à autoriser.
Les documents Work V4/V2/V1 restent inaccessibles (noms en début de suivi) ;
aucun wiki distant ni fichier Figma modifié.

### Fichiers du lot F

- README.md
- app/assets/app.js
- app/assets/styles/player-reports.css
- app/migrations/Version20261112030000.php
- app/src/Controller/AdminController.php
- app/src/Controller/DiplomacyController.php
- app/src/Controller/LeaderboardController.php
- app/src/Controller/PlayerReportController.php
- app/src/Entity/Guild.php
- app/src/Entity/PlayerReport.php
- app/src/Service/Admin/AdminNotificationService.php
- app/src/Service/Player/DiplomacyService.php
- app/src/Service/Player/PlayerReportService.php
- app/templates/admin/sections/reports.html.twig
- app/templates/player/_guild_hub.html.twig
- app/templates/player/leaderboard.html.twig
- app/templates/player/report.html.twig
- app/tests/Controller/GlobalMessageTest.php
- app/tests/Controller/GuildTagPlayerReportTest.php
- app/tests/Integration/EmpireConversionTest.php
- app/tests/Integration/GamePersistenceTest.php
- app/tests/Integration/GuildProjectTest.php
- app/tests/Integration/GuildRankTest.php
- app/tests/Integration/GuildTreatyTest.php
- app/tests/Integration/MigrationHistoryCompatibilityTest.php
- app/tests/Integration/PersonalDiplomacyTest.php
- app/tests/browser/colony.spec.mjs
- app/tests/browser/guild-projects.spec.mjs
- app/tests/browser/guild-ranks.spec.mjs
- app/tests/browser/guild-tags-player-reports.spec.mjs
- app/tests/browser/guild-treaties.spec.mjs
- app/tests/browser/leaderboard-contact.spec.mjs
- app/tests/browser/orbit-request.spec.mjs
- docs/ARCHITECTURE.md
- docs/DECISIONS.md
- docs/EVOLUTIONS_INTERFACES_FLOTTES.md
- docs/FEUILLE_DE_ROUTE.md
- docs/REGLES.md
- docs/wiki.json

### Migration locale du lot F autorisée et vérifiée — 12 novembre 2026

Les deux accords explicites sont reçus : sauvegarde/migration locales et
publication du lot F, fusion uniquement après CI complète verte, puis lot G.
La sauvegarde dédiée var/backups/lot-f-20261112 est créée sans purge d’ancien
backup (0 paire retirée) ; archive gzip et manifeste vérifiés deux fois avant
make migrate. Seule Version20261112030000 était en attente.

Comptages inchangés avant/après : 720 planètes, 40 ressources, 15 flottes,
12 lignes de vaisseaux, 3 combats, 4 espionnages, 1 transport, 6 participations,
1 guilde, 1 adhésion et 0 archive personnelle. L’ancienne guilde conserve tag NULL ;
aucun nouveau signalement (0), aucun tag inventé. Aucun Alpha.
make lint est maintenant aussi réussi sur la base locale migrée, mapping et schéma
inclus : /tmp/genesis-lot-f-lint-local-final.log. Logs de sauvegarde/migration
/tmp/genesis-lot-f-backup.log et -migrate.log, comptages -counts-before.json.
Les suites serveur/navigateur déjà réussies portent sur le code applicatif inchangé ;
seul ce suivi documentaire est actualisé. Publication autorisée, CI à vérifier.
