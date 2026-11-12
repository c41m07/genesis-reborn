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
| F — guildes/signalements | Tag obligatoire à la création, unicité par univers et classement | 3 | Fusionné dans dev, PR #46 |
| F | Signalement d’un joueur depuis le classement | 4 | Fusionné dans dev, PR #46 |
| G — rappel | Missions, trajet partiel, carburant/fret, verrouillage, origine perdue | 5 | Fusionné dans dev, PR #47 |
| H — détection | Analyse tactique, visibilité des mouvements et détection à l’arrivée | 5 | Fusionné dans dev, PR #48 |
| I — orbites | Demande au départ, accords, attente et changements diplomatiques | 5 | Fusionné dans dev, PR #49 |
| J — abandon | Dernière colonie, actifs/files, restrictions et confirmation | 5 | Fusionné dans dev, PR #50 |
| K — refontes | Diplomatie, Profil, Inventaire : maquettes adaptatives, puis validation | 3 | Implémenté et vérifié localement ; CI complète avant fusion |
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
  Une somme simple donnerait 80 minutes. La somme simple a été validée pour le préavis uniquement, puis implémentée dans le lot H.
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
leur intégration dans les templates est terminée dans le lot K.
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
Captures Diplomatie ordinateur, Profil mobile et Inventaire tablette examinées.
Cette étape décrivait les maquettes seules ; leur intégration est suivie dans le lot K ci-dessous.

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

## Lot F intégré et plan détaillé du lot G — 12 novembre 2026

PR #46 fusionnée dans dev au commit 1818e8e8cf9ecb0279212a374889ef108e04783c.
CI complète 43189015280 réussie sur fa178723ddc48b8672bd9aaf816026db2740d1cb :
cinq quality et GitGuardian verts, étapes Compose/assets/PHP/lint/schéma/
sauvegarde-restauration/navigateur examinées. Protection stricte de dev et PR
obligatoire revérifiées. Aucun Alpha.
Lot G depuis dev actualisée, branche feature/rappel-flottes-et-repli.

Complexité 5 : rappel, progression et repli (transactions, unités et ressources).
1. Examiner les effets existants : arrivée, consommation du carburant, boosts,
   combats, orbites, transports, rapports, traces et représentation des vols.
   Les rappels administratifs/conversion existants gardent leurs règles distinctes.
2. Figer le trajet et suivre sa progression au départ et lors des changements
   d’échéance. Les boosts agissent sur l’opération en cours, sans réécrire le
   chemin déjà parcouru. Préserver les coûts, vitesses, minimums et multiplicateurs
   du calcul de départ ; aucun changement de catalogue ou d’équilibrage.
3. Préparer le retour depuis le point atteint : prévisualisation, droits actuels,
   choix d’une colonie de repli si l’origine est perdue, carburant disponible
   uniquement et fret protégé. Refus entier si route/carburant/droits invalides.
4. Exécuter sous les verrous participation/univers/planètes/flotte, vérifier
   l’absence de combat et l’échéance, reçu idempotent et nouvelle programmation
   invalidant les anciens messages. Ne livrer, espionner ou coloniser lors
   d’aucun rappel ; conserver vaisseaux, fret, carburant inutilisé et historiques.
5. Retour à la colonie autorisée, avec nouveau contrôle de propriété/univers,
   dépôt et stockage existants ; atomicité des crédits et des suppressions de
   lignes de vaisseaux, rejeu sans effet.
6. UI clavier/tactile et affichage privé de la route de repli, sans révéler une
   colonie de repli inconnue aux tiers. Ne pas étendre la visibilité des flottes.
7. Tests de calcul, boosts successifs, missions, refus, arrivée/rappel concurrents,
   ancien jeton, transport et stocks/vaisseaux ; puis contrôles complets et documents.

Décisions complémentaires reçues après reformulation :
- Anciennes flottes : rappel uniquement si le retour peut être calculé avec
  certitude ; sinon message explicite, mission poursuivie sans modification.
  Ne pas inventer de progression ou de paramètres historiques.
- Repli : calculs normaux de l’univers au rappel, carburant disponible seulement,
  fret d’hydrogène et stocks des planètes préservés. Refus sans changement
  si le carburant manque ; aucun prélèvement distant.
Données prévues : navigation nullable (trajet/progression/rappel), colonie de retour
nullable distincte de l’origine historique. Migration additive à préparer ;
l’accord de migration du lot F n’autorise pas celle du lot G.

### Application locale et vérifications du lot G — 12 novembre 2026

Accord explicite reçu pour sauvegarder, vérifier puis appliquer
Version20261112040000 sur la base locale uniquement.
Archive : var/backups/lot-g-20261112/genesis-genesis_reborn_v2-20261112T093735Z-mYBfYtxefrsi.sql.gz.
Archive gzip/manifeste vérifiés deux fois avant migration. Comptages inchangés :
720 planètes, 40 ressources, 15 flottes, 12 lignes de vaisseaux, 3 combats,
4 renseignements, 1 transport, 6 participations, 1 guilde, 1 adhésion,
0 signalement joueur. Empreinte des données historiques de flotte inchangée ;
navigation/return_planet_id des anciennes flottes restent NULL.
Logs : /tmp/genesis-lot-g-backup.log, -migrate.log et -counts-before.json.

Implémentation ciblée : FleetNavigation, FleetRecallService, route privée avec
confirmation et choix de repli, réemploi des verrous/reçus/outbox et du dépôt
existant. Checkpoints avant boosts/ajustements, ancienne origine conservée,
carburant réservé seulement, aucune exécution de la mission annulée.
La carte protège la colonie de repli non découverte et conserve les droits actuels.

71 tests ciblés réussis, 543 assertions : huit missions, orbite/demande, boosts
successifs, ancien message après nouvelle échéance, reçus, repli et livraison,
carburant et stocks protégés, anciennes données fiables ou insuffisantes, combat,
permissions/CSRF et confidentialité. Le premier contrôle navigateur a révélé
une lecture de base avant la fin de la navigation : attente de confirmation
ajoutée au test ; aucune correction métier nécessaire pour ce cas.
Contrôles complets en cours, publication G non autorisée, aucun Alpha.

La première suite complète serveur (792 tests) a identifié un refus d’orbite
en attente dû à un écart d’arrondi entre JSON et DOUBLE après relecture sous verrou.
La comparaison tolère désormais uniquement l’arrondi numérique ; les coûts sont
ensuite plafonnés au carburant réellement présent, sans création d’hydrogène.
Un cas déterministe reproduit ce défaut ; les 28 tests de rappel passent
(209 assertions). Suite complète relancée après cette correction.

Suite complète serveur après correction : make test réussi, 793 tests PHP et
210610 assertions, puis 13 tests Python. make lint réussi sur la base locale
migrée après correction ; mapping et schéma synchronisés. make validate et
make assets réussis ; aucun asset source n’a changé depuis la compilation.
Logs : /tmp/genesis-lot-g-test-final.log, -lint-after-rounding.log,
-assets.log et -validate.log.

Le scénario navigateur existant fleet-preview-submit-state a révélé une course
entre l’estimation automatique et l’estimation dont le test simule la panne réseau.
Le test attend maintenant le résultat initial avant d’intercepter la demande ;
aucun contrôle n’est retiré ni délai augmenté. Reprise ciblée prévue avec le
parcours général au clavier, après les quatre groupes de la suite complète.

Fichiers du lot G (25, aucun fichier généré ni sauvegarde) :

- README.md
- app/assets/app.js
- app/assets/controllers/galaxy_v2_controller.js
- app/assets/styles/fleet-recall.css
- app/migrations/Version20261112040000.php
- app/src/Controller/ColonyController.php
- app/src/Controller/FleetRecallController.php
- app/src/Entity/Fleet.php
- app/src/Service/Fleet/FleetNavigation.php
- app/src/Service/Fleet/FleetRecallService.php
- app/src/Service/Fleet/FleetService.php
- app/src/Service/Fleet/MovementCalculator.php
- app/templates/fleet/recall.html.twig
- app/templates/ui/flights.html.twig
- app/tests/Controller/GalaxyFleetVisibilityTest.php
- app/tests/Integration/FleetRecallTest.php
- app/tests/Service/FleetNavigationTest.php
- app/tests/browser/fleet-preview-submit-state.spec.mjs
- app/tests/browser/fleet-recall.spec.mjs
- docs/ARCHITECTURE.md
- docs/DECISIONS.md
- docs/EVOLUTIONS_INTERFACES_FLOTTES.md
- docs/FEUILLE_DE_ROUTE.md
- docs/REGLES.md
- docs/wiki.json

Continuité Git : le contrôle final a relevé un passage sur dev au même SHA 1818e8e. Retour sur feature/rappel-flottes-et-repli sans modification des fichiers ni commit sur dev.

### Validation locale finale du lot G

Les six contrôles ont été exécutés et examinés : make validate, make lint,
make test, make assets (npm run build), BROWSER_PERSISTENCE=1 make test-browser
et git diff --check réussis. 793 tests PHP, 210610 assertions, 13 tests Python.
La couverture des rapports JSON est comparée au manifeste : 173 scénarios
navigateur distincts réussis, aucun scénario manquant ou supplémentaire.
Les quatre groupes initiaux comprennent 201 réussites et l’échec du test
d’estimation avant correction ; le dernier groupe isolé passe ses trois cas :
estimation corrigée, rappel avec focus réellement visible et navigation générale
au clavier. Aucun délai ni assertion existante n’a été affaibli.
Logs/JSON : /tmp/genesis-lot-g-batch-{1..5}.log et .json ;
manifeste /tmp/genesis-lot-g-manifest.json. Captures privées dans output/playwright.
Contrôles ciblés d’accessibilité : titres, labels/alertes, focus visible, clavier,
tactile, absence de débordement à 390 px et axe du panneau (aucun défaut
grave/critique). Ces vérifications ne constituent pas un audit RGAA complet.

Aucune décision produit bloquante restante pour G. Lots H à K non implémentés.
Les trois sources Work citées en introduction restent indisponibles ; aucun wiki
distant ni fichier Figma modifié pour ce lot. Documentation locale et wiki intégré
synchronisés. Aucun push, PR, CI GitHub du lot G, fusion ni déploiement Alpha.
Publication G soumise à l’accord utilisateur, puis CI complète avant toute fusion.

## Lot G intégré et plan du lot H — 12 novembre 2026

PR #47 fusionnée dans dev au commit 30f548b1a0621d75081e1c49a0ef24e05677f199.
CI 43205470072 complète réussie sur f627c9c1e845ddc3063aad82c9f94dd955db452d :
cinq quality, toutes leurs étapes attendues et GitGuardian verts. Protection stricte,
PR obligatoire et cinq contextes imposés revérifiés avant fusion avec garde du SHA.
publish ignoré comme attendu pour une PR ; déploiement uniquement manuel, aucun Alpha.
Lot H depuis cette dev actualisée, branche feature/detection-attaques-visibilite.

Sous-tâches et complexités :
1. Politique commune de visibilité/DTO privés, univers/propriétaire/découvertes : 5.
2. Préavis min(300 + 300 × somme des trois rangs, 7200, moitié du trajet) : 4.
   Somme distincte des pondérations d’espionnage ; plafonds réels du catalogue
   surchargé vérifiés selon la même résolution que QueueService.
3. Alertes des attaques détectées, compte à rebours et rafraîchissement léger
   sur les pages de jeu, avec carte synchronisée sans changer sa sélection : 4.
4. Relectures sans mutation : pas de notification persistante, de journal consulté,
   ni de présence/inactivité réinitialisée par les appels de surveillance : 4.
5. Tests des seuils, boosts/retours, droits/univers/découvertes, absence de fuite,
   espionnage inchangé et clavier/tactile ; contrôles et documents : 5.

Les attaques du propriétaire restent suivies ; le préavis protège leur destinataire.
Les alliés conservent leur périmètre existant, limité aux systèmes découverts.
Pour le destinataire, une origine inconnue reste masquée, même après détection.
Aucune modification des coûts, vitesses, combats ou détection des sondes à l’arrivée.
Aucune migration prévue : lecture des trajets et recherches existants.

### Premières vérifications du lot H

Lecture locale uniquement : abc-1 actif, aucun remplacement de max_level ou des
plafonds par rang pour tactical_intelligence ; référence 5/5/5 = 15 niveaux,
80 minutes avant limite du demi-trajet. Aucun constat sur un catalogue Alpha.

20 tests ciblés réussis (177 assertions). Les premiers échecs étaient dans les
nouvelles fixtures HTTP : objets Doctrine détachés après requête et valeur
attendue de pondération mal arrondie (17,5 → 18). Fixtures corrigées, aucune
modification de cascade ni du calcul d’espionnage. Le parcours de recherche de
planète ouvre d’abord sa fiche ; le nouveau test tactile clique désormais le
bouton « Vue rapprochée » existant avant de vérifier la conservation de cette vue.
Aucun délai ni contrôle existant affaibli. Contrôles complets en cours.

Suite serveur complète réussie : 809 tests PHP, 210723 assertions et 13 tests
Python (/tmp/genesis-lot-h-test.log). Les deux parcours navigateur ciblés passent,
dont le tactile avec poste décalé de deux heures ; la sélection de planète est
conservée et les sondes sont retirées du suivi tiers. Après deux erreurs du
nouveau scénario tactile, complexité de sa vérification réévaluée à 5 : analyse
du parcours recherche → fiche → vue rapprochée et du journal hors accès direct
dans cette vue, plutôt qu’allonger le délai ou changer la navigation du jeu.
Aucun changement de règle résultant de ces ajustements du test.

Les captures réelles révèlent un libellé de lien de colonie trop petit hérité
du style secondaire. Correctif ciblé à 14 px avec retour à la ligne ; compilation
différée après les quatre groupes navigateur pour préserver leurs assets chargés,
puis reprise des deux parcours concernés et du parcours général au clavier.

Conflit de vérification identifié pendant la suite navigateur : un webpack watch
déjà actif sur ce dépôt remplace public/build/entrypoints.json par les chemins de
développement (/build/runtime.js, /build/app.js), alors que le journal de make
assets confirme des fichiers de production versionnés. Plusieurs premiers tests
échouent au chargement JS, à la connexion ou à la navigation admin. Aucun changement
d’authentification ou d’administration dans H ; ne pas les réécrire sans preuve.
Le processus existant est préservé. Les groupes lancés sont laissés terminer ;
recompilation prévue ensuite, sans édition d’asset pendant les reprises. Les
rapports JSON permettront de reprendre chaque échec et de contrôler les 175
scénarios distincts. Aucun échec inexpliqué ne sera présenté comme validé.

Diagnostic confirmé : les trois chemins JavaScript du fichier d’entrées de
développement n’existent plus, tandis que les fichiers versionnés du dernier
make assets sont toujours présents. Les métadonnées générées (ignorées par Git)
ont été sauvegardées dans var/lot-h-*-before-repair.json puis rétablies à partir
des quatre noms vérifiés dans le journal de compilation. Aucun script chargé
n’est modifié ni supprimé, aucun test ou code d’authentification/admin changé.
Les groupes continuent ; tous les échecs resteront à reprendre. Une compilation
canonique finale regénérera les métadonnées, puis les parcours concernés seront
revérifiés sans nouvelle édition d’asset.

La compilation canonique finale a été exécutée après les quatre groupes.
Empreintes des métadonnées et des quatre assets enregistrées puis vérifiées
inchangées pendant toutes les reprises (/tmp/genesis-lot-h-stable-assets.json).
Les 30 échecs de l’exécution perturbée passent en quatre reprises (8/8/7/7),
sans changer leurs tests ni leurs délais. Couverture déjà confirmée par les JSON :
174 scénarios distincts réussis ; le seul manquant est le parcours général au clavier.
Dernier groupe en cours : clavier général et deux scénarios H avec contraste
clair/sombre, focus visible, nouveau libellé lisible et poste décalé de deux heures.

Aucun processus watch existant arrêté ni configuration globale modifiée. Après
les contrôles, une compilation de développement ponctuelle est prévue pour
rétablir les fichiers attendus par ce watch, sans lancer de second processus.

### Validation locale finale du lot H

Six contrôles exécutés et examinés avec succès : make validate, make lint,
make test, make assets (npm run build), BROWSER_PERSISTENCE=1 make test-browser,
git diff --check. 809 tests PHP, 210723 assertions et 13 tests Python.
Les rapports JSON comparés au manifeste attestent 175 scénarios navigateur
distincts réussis, aucun manquant ni supplémentaire. Les 30 échecs liés aux
références de compilation sont tous repris avec succès ; le dernier groupe
réussit clavier général et deux parcours H, dont le contraste clair/sombre.
Aucun test navigateur existant modifié pour résoudre ce conflit, aucun délai augmenté ni assertion affaiblie. Les tests serveur couvrent les nouvelles conditions de découverte et de préavis.

La compilation de production et ses métadonnées sont restées identiques pendant
les quatre reprises et le groupe final (empreintes vérifiées). Après fin des
tests, npm run dev réussit et restaure tous les fichiers attendus par le watch
préexistant : toutes ses références JS/CSS existent, aucun second watch lancé,
aucun arrêt de processus ni changement de configuration globale.
Les résultats de production précédents restent ceux des sources inchangées.

Logs : /tmp/genesis-lot-h-test.log, -lint-final.log, -validate.log,
-assets-stable.log, -dev-restore.log ; rapports -batch-{1..5}.json et
-retry-{1..4}.json ; manifeste -manifest.json ; empreintes -stable-assets.json.
Captures dans output/playwright/lot-h-5, libellé à 14 px, mobile sans débordement,
focus visible et navigation tactile vérifiés. Axe du panneau : aucun défaut
grave/critique en mode clair ou sombre. Vérifications ciblées, aucun audit RGAA complet.

Aucune décision produit bloquante ni migration requise pour H.
Publication en attente d’accord ; aucune PR/CI GitHub H, fusion ou Alpha.
I, J et K restent à réaliser après intégration de H. Les trois sources Work
précisément listées en introduction restent indisponibles ; aucun wiki distant
ni fichier Figma modifié. Documentation locale et wiki intégré synchronisés.

Fichiers du lot H (26, aucun artefact généré) :

- README.md
- app/assets/app.js
- app/assets/controllers/galaxy_v2_controller.js
- app/assets/controllers/incoming_attacks_controller.js
- app/assets/styles/incoming-attacks.css
- app/config/game/technologies/tactical_intelligence.yaml
- app/src/Controller/ColonyController.php
- app/src/Controller/FleetSurveillanceController.php
- app/src/Repository/GameRepository.php
- app/src/Service/Colony/CatalogueInsights.php
- app/src/Service/Colony/GameShell.php
- app/src/Service/Fleet/AttackDetection.php
- app/src/Service/Fleet/FleetVisibility.php
- app/templates/base.html.twig
- app/templates/ui/incoming_attacks.html.twig
- app/templates/ui/incoming_attacks_list.html.twig
- app/templates/universe/_fleet_log.html.twig
- app/templates/universe/map_v2.html.twig
- app/tests/Controller/GalaxyFleetVisibilityTest.php
- app/tests/Service/AttackDetectionTest.php
- app/tests/browser/fleet-surveillance.spec.mjs
- docs/ARCHITECTURE.md
- docs/EVOLUTIONS_INTERFACES_FLOTTES.md
- docs/FEUILLE_DE_ROUTE.md
- docs/REGLES.md
- docs/wiki.json

### Reprise de la CI du lot H

Publication autorisée ; PR #48 créée vers dev sur 24e6f081fc14ca373a5ab89847c7eb23b606c44e.
CI 43235864806 : PHP et trois groupes navigateur verts ; groupe 4 en échec sur
un seul scénario administratif (41 autres réussis). La lecture SQL immédiate
de ends_at après un clic pouvait précéder la fin de la soumission. L’assertion
attend désormais la même valeur persistée avec expect.poll, sans augmenter le
délai du scénario ni retirer un contrôle. Aucun code métier modifié.
Fusion non effectuée ; nouvelle CI complète exigée sur le correctif.

Reprise locale du scénario calendrier réussie : 1 test navigateur, sans modification
des assets sources ni du code métier. Compilation de production réussie pour
cette reprise ; journaux /tmp/genesis-lot-h-ci-fix-assets.log et
/tmp/genesis-lot-h-ci-fix-browser.log.
Les suites serveur et les autres parcours déjà validés portent sur le même code
applicatif. Publication du correctif autorisée dans le périmètre de H ; nouvelle
CI complète avant toute fusion. Aucun Alpha.

## Lot H intégré et plan du lot I — 12 novembre 2026

PR #48 fusionnée au commit 0a7a96d55e409cc9541254b9206ec857294d0400 après CI
43240025765 intégralement verte sur 04370e6d4af493e6d743f2c6ce9924dd16529c5d.
Cinq quality, leurs étapes attendues et GitGuardian examinés ; protection stricte
de dev revérifiée, fusion avec garde du SHA. Aucun Alpha.
Lot I depuis dev actualisée, branche feature/demandes-orbite-au-depart.

Analyse ciblée : demande actuellement créée à l’arrivée, attente orbit_pending
sans date limite, alliés personnels seuls acceptés automatiquement. La base
locale ne contient aucune flotte de mission orbit ; aucun constat sur Alpha.
Toute orbite autorisée participe actuellement à la défense de l’hôte, sans
condition diplomatique supplémentaire dans registerGarrison/joinOpenDefense.

Sous-tâches et complexités :
1. Réutiliser sans changement le calcul G de retour partiel/fuel dans un planificateur
   indépendant, commun au rappel et au refus d’orbite en trajet : 5.
2. Suivi privé de demande versionné dans Fleet.navigation, déjà disponible depuis G :
   départ, destinataire initial, décision, échéance réelle et fermeture : 4.
   Aucun schéma ni historique ancien modifié, aucune migration prévue.
3. Demandes au départ, auto-accord allié, décision neutre, interdits PNJ/ennemi :
   5 ; transactions/verrous/reçus et nouveaux jetons sur les retours.
4. Échéances après boosts, absence de réponse, retrait, propriétaire/diplomatie
   revérifiés à l’arrivée, aucune duplication ni livraison pendant le retour : 5.
5. Présentation envoyées/reçues avec échéance, focus/clavier/tactile et données
   privées filtrées avant sérialisation, rafraîchissement sans perte du focus : 4.
6. Tests serveur/navigateur, cas limites et refus, documentation et contrôles : 5.

Deux choix complémentaires ont été validés par l’utilisateur le 12 novembre 2026 :
- Conserver pour ce voyage le traitement des départs antérieurs, sans date fictive.
- Conserver la participation normale à la défense pour une orbite neutre acceptée,
  sans élargir les droits de la mission Défense envoyée directement.
Ces deux choix sont consignés dans docs/DECISIONS.md et intégrés aux tests du lot I.


### Lot I — préparation initiale, puis intégration après validation

- Calcul du rappel extrait à comportement inchangé dans `FleetRecallPlanner`,
  partagé avec le futur refus d’orbite en trajet. Vérification ciblée :
  31 tests / 225 assertions réussis (rappel et navigation).
- `OrbitRequestState` prépare un état privé versionné dans `Fleet.navigation`,
  sans modification du schéma et sans reprise automatique des anciennes flottes.
  Le composant a ensuite été branché sur les nouveaux départs après validation
  explicite des deux transitions.
- Les 11 tests / 20 assertions du composant couvrent l’accord automatique,
  l’échéance avancée par un boost, les réponses expirées ou non autorisées,
  le changement de propriétaire/destination, l’impossibilité de réaccepter un
  refus et la conservation de son historique.
- Transitions validées : ancien processus conservé pour les missions antérieures ;
  orbites neutres acceptées participantes à la défense de l’hôte.


### Lot I — bilan local du 12 novembre 2026

- Nouveaux départs : demande privée persistée immédiatement, accord allié
  automatique ou réponse du propriétaire neutre avant l’arrivée réelle.
  PNJ, planètes sans propriétaire, ennemis et autres univers refusés.
- Réponse liée à la planète et à son propriétaire initiaux, clé de demande et
  CSRF. Participation, univers, destination, origine et flotte verrouillés ;
  réponse périmée ou rejouée sans effet supplémentaire.
- Refus et retrait : calcul partagé du rappel G depuis la position atteinte,
  sans consommer le fret ni débiter des stocks à distance. Nouveaux jetons
  d’arrivée ; conservation du refus dans l’historique privé.
- Sans réponse ou accord devenu invalide à l’arrivée : retour sans attente
  indéfinie. Réévaluation des relations, propriétaire et protections existantes.
  Orbite neutre acceptée participante à la défense ; mission Défense inchangée.
- Anciennes missions sans demande au départ : processus conservé pour ce voyage.
  Aucune migration, aucun schéma ni historique ancien modifié.
- Cartes envoyées/reçues, date de Paris et compte à rebours sur horloge serveur ;
  origine non découverte omise. Actualisation privée et respect du focus.
  Boutons nommés, cibles tactiles de 44 px et focus visible.
- Contrôles : make validate, make lint, make test, make assets
  (npm run build), BROWSER_PERSISTENCE=1 make test-browser et git diff --check.
  Suite complète : 835 tests PHP / 210803 assertions et 13 tests Python.
  Complément après revue des verrous et permissions : 54 tests / 289 assertions.
- Navigateur : 176 scénarios distincts couverts, comparés au manifeste complet.
  Quatre groupes : 44, 43, 43, puis 42 réussites et un ancien libellé d’état
  vide attendu à tort. Ce contrôle a été corrigé et repris avec le clavier
  et l’orbite neutre : trois réussites. Les deux parcours d’orbite ont aussi
  réussi dans leur exécution ciblée initiale.
- Échec initial de la suite PHP : univers supposé présent dans une nouvelle
  préparation de test. La préparation crée désormais explicitement l’univers
  isolé ; suite complète relancée et réussie. Aucune assertion supprimée
  ni aucun délai augmenté pour ces corrections.
- Axe ciblé du panneau sous les réglages sombre et clair : aucune violation
  grave ou critique. Clavier, maintien du focus lors de l’actualisation,
  retrait tactile, absence de débordement et captures ordinateur/mobile
  examinés. Ces vérifications ne constituent pas un audit RGAA complet.
- Preuves locales : /tmp/genesis-lot-i-browser-proof.json,
  /tmp/genesis-lot-i-full-test-final.log, /tmp/genesis-lot-i-locked-final.log,
  /tmp/genesis-lot-i-lint-reviewed.log et /tmp/genesis-lot-i-assets.log.
  Captures ignorées par Git dans var/screenshots/lot-i.
- Les fichiers compilés de production sont restés inchangés pendant les
  contrôles navigateur. La compilation de développement a ensuite été
  restaurée pour le watcher existant, sans arrêter ni relancer celui-ci.
- README, règles, décisions, architecture, feuille de route et wiki local
  synchronisés. Les documents Work externes signalés en introduction restent
  inaccessibles ; aucune modification de Figma ni de serveur Alpha dans ce lot.
- Prochaine étape : autorisation de publication, PR vers dev et fusion
  exclusivement après CI complète verte sur sa dernière version, puis lot J.


### Lot I — publication et correction de préparation CI
PR #49 ouverte vers dev sur 6f183028012e67dd49eba1d290f14c7b861420cd.
Le contrôle PHP de la CI 43258172057 a révélé une préparation aléatoire ancienne :
CombatEventTest attribuait au joueur une planète choisie sans filtrer son état PNJ.
La préparation l’identifie désormais explicitement comme colonie de joueur ;
le scénario est nommé « Legacy » pour distinguer son acceptation à l’arrivée.
Les assertions de carburant et de transition restent identiques. Le refus
des véritables planètes PNJ reste couvert séparément par OrbitDepartureRequestTest.
Aucune modification des règles ni du code métier pour cette correction.

Vérification locale de la correction : 29 tests / 233 assertions réussis
(CombatEventTest et OrbitDepartureRequestTest) ; git diff --check réussi.


### Reprise après fusion du lot I
PR #49 fusionnée au commit ed01c4f9b7c089fcb1799467381897674a821d05,
après CI complète 43260133554 verte sur 03aad138a9b876f77840710a1eddb630cf6e3f1a :
cinq jobs quality, étapes de sauvegarde/restauration et GitGuardian examinés.
La première CI 43258172057 a été remplacée et annulée après son échec PHP.
La protection stricte de dev et la PR obligatoire ont été revérifiées.
Aucun déploiement Alpha lancé.

### Lot J — plan détaillé et analyse des risques (complexité 5/5)
Branche feature/abandon-colonie, issue de dev actualisée après fusion de I.

1. Contrôles d’accès, univers et dernière colonie, sous verrous : 5.
2. Blocage de toute flotte active liée (origine, destination, repli), orbite,
   combat ou occupation ; aucun détail sur les missions invisibles : 5.
3. Dépendances de files : blocage validé par l’utilisateur si une commande
   d’une autre colonie dépend des commandes abandonnées ; bilan ciblé : 5.
4. Confirmation par nom et bilan des ressources, bâtiments, défenses, vaisseaux
   et commandes ; clavier, tactile, messages et conservation du contexte : 4.
5. Annulation sans remboursement, perte des actifs présents, monde vierge,
   historique et trace via AdminAuditLog déjà utilisé pour des actions joueur :
   5 ; aucune migration prévue.
6. Tests des refus et rejouements, anciennes échéances, recolonisation normale,
   isolation des univers, documentation et vérifications requises : 5.

Point de compatibilité I identifié pendant cette analyse : OccupationCaptureService
conserve le drapeau historique npcPlanet lors d’une capture. REGLES (lot D)
confirme que le propriétaire actuel prime sur ce drapeau. Le filtre I qui
refusait aussi une ancienne planète PNJ contrôlée par un joueur était donc trop
strict ; la seule correction de préparation du test en 03aad13 ne couvrait
pas ce cas métier. J rétablit cette compatibilité, sans autoriser une orbite sur
un véritable PNJ ou un empire oublié, et ajoute une régression correspondante.

Le risque de répétition du kit de fondation a été identifié dans le catalogue
(kit recréé lors d’une fondation sur une planète redevenue vierge). L’utilisateur
a validé l’absence de nouveau kit pour le compte qui a abandonné cette même
planète dans cet univers ; le nouveau propriétaire conserve le kit normal.
Cette exception est annoncée dans le bilan, protégée par une nouvelle empreinte
et testée dans les deux sens. Aucun coût, quota ou multiplicateur n’est changé.


### Lot J — bilan local et reprise
- Abandon transactionnel avec confirmation par nom et bilan actualisé ; dernière
  planète par univers protégée, y compris deux confirmations simultanées.
- Tous les liens de flottes actifs, combats et occupations bloquent l’action,
  sans divulgation des opérations invisibles. Les dépendances de commandes
  d’autres colonies bloquent également, conformément au choix validé.
- Ressources, bâtiments, défenses et unités présentes perdus ; files annulées
  sans remboursement. Traces et planète conservées. Les anciennes reconstructions
  sont neutralisées avant retrait des commandes, y compris leurs références
  historiques ; leurs inventaires de référence restent conservés.
- Recolonisation par le même compte sur la même planète : pas de nouveau kit ;
  autre propriétaire : kit normal. Coûts, temps, carburant, vaisseau consommé et
  quotas habituels conservés. La conséquence est affichée avant confirmation.
- Blocage des bilans périmés sur les actifs discrets ; stocks variables annoncés.
  Compte verrouillé, autre propriétaire, univers changé, CSRF absent et
  répétitions refusés. La colonie active et l’univers sont préservés ou ajustés
  vers une colonie restante.
- Compatibilité des orbites corrigée pour les colonies conquises dont le
  marqueur de provenance PNJ subsiste : le propriétaire actuel prime.
- Nom long du bandeau limité visuellement, avec texte complet conservé dans le
  sélecteur, le texte accessible et le titre. Identité et SVG inchangés.
- Six contrôles locaux examinés : make validate, make lint, make test,
  make assets (npm run build), BROWSER_PERSISTENCE=1 make test-browser,
  git diff --check. Suite finale : 868 tests PHP / 210927 assertions et
  13 tests Python. Ciblés de transition et kit : 48 tests / 184 assertions ;
  complément d’audit et colonie conquise : 1 test / 22 assertions.
- Navigateur : couverture comparée au manifeste complet, 181 scénarios distincts
  réussis après reprises. Contrôles des modes sombre/clair, erreurs, clavier,
  tactile, noms longs, stockage, historique, dépendances et concurrence.
- Premier passage général : 167 réussites / 7 échecs sur 174 scénarios.
  Trois dépassements de délais, un écart réel label/contenu accessible du
  nouveau lien, et trois échecs de navigation/confirmation, dont HTTP 500 sur
  Préférences. Le libellé visible a été aligné sur le nom accessible.
  Les logs serveur initiaux n’ont pas été conservés par le lanceur ; la cause
  exacte du 500 n’est pas établie. Les sources évoluaient pendant ce passage,
  notamment après validation de la règle du kit : ce passage n’était pas
  une vérification d’un contenu figé. Il ne doit pas être présenté comme vert.
- Sur contenu figé, reprise isolée des sept scénarios, confirmations ordinateur
  et tactile, concurrence, clavier et wiki : 12 réussites. Aucun des échecs de
  navigation ne s’est reproduit ; collecte ciblée des exceptions prévue en cas
  de nouvel échec. Ni assertions supprimées ni délais augmentés.
  La CI complète sur le dernier commit reste indispensable avant fusion.
- La reprise de géométrie mobile a comparé le nom à sa propre cellule :
  la cellule de ressource détaillée choisie initialement est masquée en mode
  compact. Le contrôle conserve la vérification de non-chevauchement.
- Un test de reconstruction a révélé une référence de commande à neutraliser
  avant annulation ; correction vérifiée. Les préparations de tests de
  recolonisation et production ont été ajustées pour fournir du carburant
  suffisant et une marge de stockage, sans changement des valeurs du jeu.
- Preuves locales : /tmp/genesis-lot-j-browser-proof.json,
  /tmp/genesis-lot-j-full-test-kit-final.log, /tmp/genesis-lot-j-kit-policy.log,
  /tmp/genesis-lot-j-lint-kit-final.log, /tmp/genesis-lot-j-assets-reviewed.log.
  Captures examinées dans var/screenshots/lot-j, ignorées par Git.
- Compilation de production inchangée pendant les scénarios ; compilation
  de développement restaurée ensuite, fichiers référencés vérifiés, watcher
  existant préservé. Aucune migration ni opération sur la base Alpha.
- README, règles, décisions, architecture, wiki local et feuille de route
  synchronisés. Les trois sources Work externes signalées au début de ce suivi
  restent inaccessibles ; aucune nouvelle opération Figma.
- L’utilisateur autorise désormais l’intégration de la suite des lots vers dev,
  avec PR et CI complète verte, et demande une checklist de vérification du
  prompt complet après K, puis une revue commune des retouches.


## Lot J intégré et lot K en cours — 12 novembre 2026

Lot J fusionné par PR [#50](https://github.com/c41m07/genesis-reborn/pull/50),
commit d08dda6799df47a9e18d06b1af3d4cf34846165e.
La [CI complète](https://github.com/c41m07/genesis-reborn/actions/runs/43284045964)
réussit sur la dernière version 7a29a3d : cinq jobs quality, compilation,
tests, sauvegarde/restauration isolées et vérification du diff ; GitGuardian
réussit également. Les protections de dev ont été vérifiées avant la fusion.
Aucun déploiement Alpha.

Lot K part de cette dev actualisée, branche
feature/refonte-diplomatie-profil-inventaire. Prévisualisation approuvée relue :
interfaces-genesis.html, dans le dossier local des visualisations du chat.
Lors de l’étape initiale, seul un fichier Figma vide a été créé avant le quota
rencontré ; l’intégration s’appuie sur les maquettes locales approuvées.

| Sous-tâche K | Complexité | Dépendances et précautions |
| --- | --- | --- |
| Diplomatie : titres, trois périmètres, accès à la gestion, recherche et lisibilité | 3 | Conserver toutes les actions et permissions, grades, projets, historiques et canal non marqué lu par son aperçu |
| Profil : identifiants, changement facultatif, confirmation et section destructive | 2 | Conserver validation, erreurs, contrôles du mot de passe, CSRF et fermeture de session |
| Inventaire : capacité, bonus, choix d’opération, examen avant consommation | 3 | Garder les formulaires serveur, requêtes idempotentes, contrôles de stockage/vacances et fonctionnement sans JavaScript |
| Rendu réel, clavier/tactile, thèmes et documentation de recette | 3 | Essais ciblés puis vérifications complètes ; maquettes déjà validées |

Aucune migration ni changement de règle de jeu prévu dans K.
La publication et la fusion après CI complète verte sont autorisées par
l’instruction de finaliser l’ensemble sur dev. Une recette manuelle sera fournie
pour décider des retouches ensemble.

### Vérifications intermédiaires K

Première passe ciblée : 10 scénarios, 8 réussis et 2 échecs de présentation.
Les annulations de boost et boîte ne consomment rien ; confirmation, contrôles
CSRF/stockage/vacances et ouverture sans JavaScript réussissent.
Les deux échecs concernent le contraste de textes du thème clair
et le lien de contact non souligné dans la suppression du compte.
Correction limitée aux styles des trois pages ; aucun contrôle affaibli.

La méthode locale suit les consignes AGENTS actualisées : contrôles adaptés,
sans réexécuter une suite complète inchangée par principe. Les résultats
locaux ciblés et la CI complète seront présentés séparément.

La reprise après correction des contrastes valide le parcours visuel des trois
pages et la modification du profil. Le test de suppression atteint ensuite
une attente d’URL incorrecte : une case obligatoire non cochée bloque le
formulaire dans le navigateur et ne provoque donc aucune redirection serveur.
L’assertion conserve cette protection native sur /profil ; delete=1 est vérifié
après la véritable soumission avec un mot de passe erroné.

Passe locale finale élargie : 30 parcours concernés, 29 réussis, un échec
de navigation inverse dans le dialogue de boost. Le navigateur pouvait faire
sortir le focus à la première tabulation inverse. Le dialogue boucle maintenant
explicitement entre ses deux boutons ; Tab, Maj+Tab, Échap et le retour au
déclencheur sont vérifiés. Ajustement de l’espacement des statistiques à 320 px
après examen des captures réelles. La compilation est restée stable pendant
les trente parcours ; les contrôles PHP/lint n’ont pas tourné en parallèle.

### Bilan local du lot K — 12 novembre 2026

- make validate : réussi, configuration Compose vérifiée sans afficher les secrets.
- make lint : réussi ; dernière reprise complète après correction du dialogue,
  puis lint Twig ciblé du lien de gestion conditionnel.
- make test : réussi, 868 tests PHP et 210925 assertions, puis 13 tests Python.
- make assets (npm run build) : réussi après les dernières corrections JS/CSS.
- Navigateur ciblé : 30 scénarios distincts concernés réussissent après reprises.
  La passe de trente comportait 29 succès et l’échec de boucle de focus ; la
  reprise de huit cas réussit entièrement. Le parcours des trois pages est
  ensuite repris avec le cas d’une guilde existante désactivée dans l’univers :
  l’accès à une gestion absente est masqué, les commandants restent accessibles.
  La première préparation JSON ne créait pas l’objet features absent ; corrigée
  avec une fusion JSON et une assertion de préparation. Dernière reprise 1/1.
- La consolidation est comparée aux trente cas sélectionnés dans le manifeste ;
  aucun doublon n’est compté comme un nouveau scénario. Le manifeste complet
  comporte 183 scénarios, destinés à la CI complète avant fusion.
- Clavier : sélection, boucle Tab/Maj+Tab, Échap, annulation et restitution du
  focus. Confirmation réelle, contrôles serveur et actions sans JavaScript vérifiés.
- Rendus réels examinés : trois pages, deux thèmes, 320/390/768/834/1440 px ;
  dialogues, boîtes, états vides/pleins, erreurs, gestion et désactivation de guilde.
  Les contrôles axe ciblés et applicatifs passent ; ce n’est pas un audit RGAA complet.
- git diff --check : réussi avant commit et publication.

Preuves locales : /tmp/genesis-lot-k-local-proof.json et rapports
/tmp/genesis-lot-k-final-browser.json, /tmp/genesis-lot-k-dialog-browser.json,
/tmp/genesis-lot-k-guild-reviewed-browser.json. Captures ignorées par Git :
var/screenshots/lot-k/. Les manifestes de compilation et leurs fichiers ont été
vérifiés. Le watch existant est préservé.

Documentation du lot : README, architecture, décisions, règles, feuille de route,
index, wiki intégré et [recette manuelle A à K](RECETTE_EVOLUTIONS_INTERFACES.md).
Aucune migration, dépendance ni règle de jeu modifiée. Les sources Work V4/V2/V1
restent non consultées, faute de fichiers accessibles ; aucun wiki distant mis à jour.
La réussite locale n’est pas une validation de la CI GitHub. Cette dernière et
les protections de dev doivent être vérifiées sur la version exacte de la PR
avant fusion. Aucun déploiement Alpha.

### Première CI K — correction du contrat typographique

PR [#51](https://github.com/c41m07/genesis-reborn/pull/51), version ced85ff.
La [première CI](https://github.com/c41m07/genesis-reborn/actions/runs/43297259592)
réussit pour PHP/Python et trois groupes navigateur ; le quatrième relève une
attente ancienne du test ui-routes-responsive : 12 px pour tous les onglets
sur ordinateur, alors que les onglets de Diplomatie sont maintenant à 14 px
conformément à la refonte. Ce groupe compte 44 succès et ce seul échec.

Le contrat est séparé explicitement : trois onglets Diplomatie obligatoires
à 14 px, tailles 12/11 px conservées pour les autres onglets et rangs.
Toutes les vérifications de routes, thèmes, débordement et typographie sont
maintenues. Aucun code produit modifié dans cette reprise ; la dernière
version de la PR doit repasser la CI complète avant fusion.

Le contrôle local corrigé réussit sur toutes les routes dans les deux thèmes
et cinq largeurs, avec toutes les assertions conservées. Il porte la couverture
locale pertinente à 31 scénarios distincts réussis. Les autres contrôles locaux
restent valables sur le code produit inchangé ; seul le test et le suivi sont
corrigés avant de relancer la CI sur la nouvelle version.

## Retouches après recette — infobulles et Diplomatie

Branche fix/infobulles-colonies-flottes, depuis dev actualisée 78e1aa1.
Travail existant propre et préservé. Complexités : retrait de la phrase de
Recherche (1), infobulles sur noms des colonies et encadrés de flotte (3),
nouvelle proposition de Diplomatie par onglets (3, validation avant intégration).
Aucune migration ou règle de jeu modifiée.

La phrase technique sur la somme des rangs, le plafond de deux heures et la moitié
du trajet est retirée de la présentation de Recherche à la demande de l’utilisateur.
Les valeurs de préavis affichées et leur calcul restent inchangés.

Retouches complémentaires demandées pendant la recette : cases de rapports
alignées et libellés conservés seulement pour les lecteurs d’écran (complexité 2) ;
retrait du paragraphe explicatif des archives, sans changer leur fonctionnement (1) ;
classements sélectionnés par les en-têtes plutôt que par le menu (2).
Les cinq classements restent décroissants, calculés globalement avant pagination.

Première vérification : 23 tests PHP (catalogue et détection) et dix parcours
navigateur ciblés réussis. La reprise avec vaisseaux présents et thème clair
a relevé un contraste insuffisant des libellés hérités du tableau Planètes.
Les styles sont désormais limités à la bulle avec textes agrandis et contraste
corrigé ; nouvelle vérification ciblée avant clôture.
La proposition Diplomatie a été contrôlée sur 1024/834/390/320 px : cinq onglets
avec guilde, trois sans guilde, changement de grade et recherche locale de démonstration.
Le fichier Figma accessible a été consulté : page vide. Aucune nouvelle maquette
n’y a été créée ; l’aperçu interactif est présenté dans le chat pour validation.
Référence publique consultée : guide d’alliance OGame (création, membres et droits).

Bilan local après correction : validation Compose, lint complet, compilation,
23 tests PHP et dix parcours navigateur distincts réussis ; le cas des bulles
a été repris avec succès après ajout de vaisseaux et correction du thème clair
(1/1, sans compter cette reprise comme un nouveau parcours).
Les bulles sont lues au survol, au focus et au toucher, restent dans la fenêtre,
ne changent pas la hauteur de la carte et se ferment avec Échap ou leur bouton.
Les captures réelles sont conservées hors versionnement dans var/screenshots/retouches.
La proposition Diplomatie reste extérieure aux templates du jeu et en attente
de validation. Pas de commit, de push, de nouvelle CI ni de déploiement pour
ces retouches à ce stade.

Fichiers de ce lot de retouches :

- README.md
- app/assets/app.js
- app/assets/controllers/hover_details_controller.js
- app/assets/styles/empire-details.css
- app/assets/styles/ranking-sort.css
- app/assets/styles/report-archives.css
- app/src/Service/Colony/CatalogueInsights.php
- app/templates/colony/planets.html.twig
- app/templates/combat/reports.html.twig
- app/templates/player/diplomacy.html.twig
- app/templates/player/leaderboard.html.twig
- app/templates/ui/colony_summaries.html.twig
- app/templates/ui/empire_details.html.twig
- app/templates/ui/empire_details_trigger.html.twig
- app/templates/ui/flights.html.twig
- app/tests/browser/empire-details.spec.mjs
- app/tests/browser/leaderboard-contact.spec.mjs
- app/tests/browser/report-archives-ships.spec.mjs
- docs/ARCHITECTURE.md
- docs/DECISIONS.md
- docs/EVOLUTIONS_INTERFACES_FLOTTES.md
- docs/FEUILLE_DE_ROUTE.md
- docs/RECETTE_EVOLUTIONS_INTERFACES.md
- docs/wiki.json

## Intégration Diplomatie validée — 12 novembre 2026

L’utilisateur valide deux onglets sans guilde : Créer ou rejoindre une guilde,
Relations commandants ; cinq avec guilde : Ma guilde, Membres, Grades et permissions,
Relations guildes, Relations commandants. Recherche et adhésion restent dans le
premier onglet sans guilde. L’ancienne URL tab=diplomacy reste utilisable pour
retrouver une guilde sans en posséder une.

Complexités : navigation selon appartenance et retours après formulaire (3),
déplacement de l’équipage/attributions vers Membres (3), édition et consultation
des grades dans leur onglet (3), accueil/gestion/recherche et présentation
adaptative (3), contrôles des droits et régressions (3).
Services métier, CSRF, versions de grades et contrats, univers, accès et fonctions
existants conservés. Aucun changement de schéma ou migration.
Les retouches locales précédentes restent présentes sur la branche dédiée.

Présence ajoutée à la demande de l’utilisateur (complexité 3) : En ligne vert,
Hors ligne rouge, toujours accompagnés du texte. Le seuil existant de cinq minutes
de PlayerActivityService est réutilisé, sans nouveau suivi ni migration.
La lecture est bornée côté serveur à la guilde actuelle et à l’univers consulté ;
l’activité dans un autre univers, les heures et les durées ne sont pas exposées.
L’état est calculé lors de l’ouverture/actualisation de l’onglet Membres.


### Vérifications de la nouvelle Diplomatie et de la présence

La complexité de la présence est réévaluée à 4 : l’activité administrative
pouvait être agrégée entre univers. GuildPresence utilise une lecture dédiée,
limitée à la guilde et à l’univers, avec appartenance du lecteur revérifiée
dans la requête. La réponse est privée et non stockable. Tests des frontières
299/300 secondes, de l’activité dans un autre univers, d’un lecteur extérieur,
d’une guilde archivée et des refus de modification de grades.

Résultats locaux :
- make validate réussi ; make lint réussi ; make assets réussi ; git diff --check réussi.
- 76 tests PHP ciblés / 298 assertions réussis ; les deux tests HTTP concernés
  ont aussi été repris après ajout des en-têtes privés (2 tests / 34 assertions).
- 18 scénarios navigateur distincts concernés réussis après reprises, sans
  échec restant : création/adhésion, deux ou cinq onglets, grades et droits,
  traités, projets et récompenses, tag/signalements, contact/classement, commandement,
  navigation adaptative et pages joueur. États vert/rouge vérifiés dans les
  deux thèmes, contrôles ciblés Axe et absence de débordement jusqu’à 320 px.
- Les échecs initiaux provenaient d’une fixture d’univers sans guildes et
  de parcours de test encore liés à l’ancien lien de projets ou à l’ancienne
  position de la gestion. Les sélecteurs et la fixture ont été adaptés ;
  aucune assertion métier retirée ni délai augmenté.
- Captures réelles conservées hors versionnement dans var/screenshots/diplomatie.
  Le rétablissement des fichiers de développement après les tests préserve
  le processus de surveillance existant.

Aucune migration, installation ou modification des permissions, valeurs de jeu,
logos ou SVG. Aucun commit, push, fusion, CI GitHub ou déploiement de ces retouches
annoncé : elles sont locales. Les lots A à K déjà livrés restent distincts.
Les trois sources Work externes précédemment signalées restent inaccessibles ;
aucune nouvelle maquette Figma n’a été créée pendant cette intégration.

Fichiers ajoutés ou modifiés pour l’intégration Diplomatie (en plus des
retouches précédentes déjà listées) :
- app/src/Controller/DiplomacyController.php
- app/src/Service/Player/GuildPresence.php
- app/assets/styles/diplomacy-tabs.css
- app/assets/app.js
- app/templates/player/diplomacy.html.twig
- app/templates/player/_guild_hub.html.twig
- app/templates/player/_guild_create.html.twig
- app/templates/player/_guild_search.html.twig
- app/templates/player/_guild_management.html.twig
- app/templates/player/_guild_members.html.twig
- app/templates/player/_guild_ranks.html.twig
- app/tests/Controller/GuildTagPlayerReportTest.php
- app/tests/browser/diplomacy-overview-anchors.spec.mjs
- app/tests/browser/leaderboard-contact.spec.mjs
- app/tests/browser/guild-ranks.spec.mjs
- app/tests/browser/guild-tags-player-reports.spec.mjs
- app/tests/browser/guild-projects.spec.mjs
- app/tests/browser/colony.spec.mjs
- app/tests/browser/player-pages-refinement.spec.mjs
- app/tests/browser/ui-routes-responsive.spec.mjs
- README.md
- docs/ARCHITECTURE.md
- docs/DECISIONS.md
- docs/REGLES.md
- docs/FEUILLE_DE_ROUTE.md
- docs/RECETTE_EVOLUTIONS_INTERFACES.md
- docs/wiki.json

### Publication des retouches autorisée

L’utilisateur autorise la publication vers dev pour tester l’ensemble des retouches.
Complexité 3 : vérification du périmètre et de la base dev, publication de la branche,
PR puis fusion conditionnée à la réussite des cinq jobs quality sur la dernière version.
Activation locale vérifiée après fusion ; aucun déploiement Alpha ni migration.
Les mentions de retouches locales ci-dessus décrivent l’état avant cette publication.
