# Navigation par espaces et pages dédiées

## État du chantier — 16 novembre 2026

Lot 1 intégré sur dev par la [PR #75](https://github.com/c41m07/genesis-reborn/pull/75),
merge `a279cd1`, après les cinq jobs quality réussis sur `d8849aa`
([CI 43942224638](https://github.com/c41m07/genesis-reborn/actions/runs/43942224638)).
La copie dev utilisée par localhost a été avancée sans écraser son fichier
`app/config/reference.php` préexistant ; assets recompilés et cache applicatif
vidé. Aucune migration de sa base ni livraison Alpha.

Lot 2 intégré sur dev par la [PR #77](https://github.com/c41m07/genesis-reborn/pull/77),
merge `52030d2`, après les cinq jobs quality réussis sur `b6d1aaf`
([CI 43958951002](https://github.com/c41m07/genesis-reborn/actions/runs/43958951002)).
Dev local avancé avec assets recompilés, cache vidé, routes et assets servis
vérifiés, modification locale préservée. Le bilan ci-dessous conserve la recette
et les reprises précédant cette intégration.

Lot 3 intégré sur dev par la [PR #78](https://github.com/c41m07/genesis-reborn/pull/78),
merge `3eb4df0`, après les cinq jobs quality réussis sur `7643609`
([CI 43966787382](https://github.com/c41m07/genesis-reborn/actions/runs/43966787382)).
Dev local actualisé, assets et routes vérifiés, fichier local préservé.
Le bilan du lot 3 ci-dessous conserve la recette précédant l’intégration.

Lot 4 réalisé sur `feature/navigation-compte-vacances`, depuis dev `3eb4df0` ;
[PR #79](https://github.com/c41m07/genesis-reborn/pull/79). Le bilan ci-dessous
décrit la recette locale avant intégration ; la PR donne l’état de la CI et de la fusion.

Les trois documents Work V4 (prompt maître), V2 (brief Figma) et V1 (protocole
adaptatif) n’ont pas été trouvés dans le dépôt ni les sources connectées
accessibles. Aucune lecture intégrale ni validation de leurs phases n’est revendiquée.

## Découpage et complexité

1. Guildes / Diplomatie : 4/5. Routes, retours après formulaire, appartenance,
   données privées, compatibilité des adresses et liens entrants.
2. Flottes : 5/5. Périmètres colonie/empire, actualisations, demandes d’orbite,
   composition continue et compatibilité des liens.
3. Inventaire : 4/5 après examen ; capacité commune, lectures ciblées, attente
   paginée et retours après consommation, sans modifier les services métier.
4. Mon compte / vacances : 5/5 après examen ; authentification, suppression
   définitive, état de l’empire sélectionné et compatibilité des formulaires.
5. Projets collectifs : estimation 4/5 ; contribution, historique et permissions.
6. Événements : estimation 3/5 ; bonus actifs, défis et rotations.
7. Administration : estimation 4/5 ; droits, formulaires et parcours guidés existants.
8. Wiki : estimation 4/5 ; recherche globale, chapitres et fragments historiques.

Les estimations des lots 3 à 8 seront confirmées sur leurs dépendances au début
du lot, après intégration du précédent.

La complexité et les risques des lots suivants seront attribués après lecture
ciblée de leurs dépendances. Aucun lot suivant ne démarre avant l’intégration
validée du précédent.

## Lot 1 — choix de navigation

| Destination | Adresse | Objectif |
| --- | --- | --- |
| Ma guilde / Trouver une guilde | `/guildes` | Présentation et aperçu privé pour un membre ; recherche sinon. |
| Créer une guilde | `/guildes/creer` | Formulaire autonome ; un membre revient à sa guilde. |
| Membres | `/guildes/membres` | Équipage, présence dans cet univers et attributions autorisées. |
| Grades et permissions | `/guildes/grades` | Consultation et édition selon les droits existants. |
| Gestion | `/guildes/gestion` | Tag, transfert, départ ou dissolution selon le rôle. |
| Relations entre guildes | `/diplomatie` | Recherche, traités et offres reçues avec guilde. |
| Relations entre commandants | `/diplomatie/commandants` | Alliances et rivalités personnelles avec ou sans guilde. |

Sans guilde, `/diplomatie` affiche les relations entre commandants. Les pages
privées de membres/grades/gestion renvoient vers la recherche sans guilde.
La désactivation des guildes conserve la consultation des relations personnelles.

Rapports rejoint le groupe Opérations du menu principal, et Événements le groupe Communauté.
Chaque espace possède une navigation secondaire commune en liens serveur,
avec état actif et retours à la ligne sur mobile. Les projets et la
personnalisation conservent leurs routes et services existants ; leur navigation
commune est raccordée, sans entamer la séparation des listes de projets du lot 5.
La personnalisation reste réservée au commandant actuel. Les autres membres
conservent l’accès à la gestion de leur départ.

L’accueil de guilde valorise le logo, la bannière et la description existants.
L’aperçu garde les trois messages récents sans marquer le canal comme lu.
Les formulaires de gestion quittent cet accueil pour une tâche autonome.
Le sommaire automatique est désactivé sur les nouvelles pages ciblées ; les
ancres des autres documents, notamment les rapports de combat, sont conservées.

## Compatibilité et architecture

Les anciens GET `/diplomatie?tab=...` redirigent vers les destinations adaptées,
en conservant `q` et `rank`. Les POST historiques restent acceptés et reviennent
sur la page concernée. Les jetons CSRF, versions et services métier ne changent
pas. Les restrictions d’univers, les règles de relations et les transactions
restent celles des services existants. La colonie active reste mémorisée dans
la session utilisée par `GameShell`.

Les fragments `guild-management`, `guild-management-title`, `guild-members-title`,
`guild-ranks-title`, `guild-search` et `alliance-members` sont orientés côté
navigateur vers leur page lors de l’ouverture d’une ancienne adresse.
Sans JavaScript, les liens serveur de la navigation restent disponibles ; le
serveur ne peut pas lire le fragment pour effectuer lui-même cette transition.

`DiplomacyController` conserve les actions, valide la destination et délègue
les lectures à `DiplomacyPageView`. Une page de grades ne charge ni équipage,
ni historique diplomatique, ni messages. La recherche limite les guildes à 20
avant chargement de leurs détails ; les relations personnelles utilisent les
contrats et guildes concernés, avec pages de 50 et recherche indépendante.
Aucun chargement de toutes les identités de l’univers n’est nécessaire à cette
page. Les gains de performance ne sont pas mesurés.

Aucune migration, dépendance, valeur d’équilibrage ni règle d’accès nouvelle.
Les essais utilisent exclusivement des conteneurs et bases jetables distincts
de la base habituelle. Le manuel embarqué est actualisé pour ces destinations ; le wiki externe n’a pas été modifié.

## Vérifications locales

- `make validate`, `make assets` et `make lint` réussis sur la pile isolée,
  y compris validation du mapping et synchronisation du schéma jetable.
- La première suite `make test` a exécuté 1 358 cas. Deux échecs de la nouvelle
  requête ont été corrigés ; un alias Doctrine réservé empêchait sa lecture.
  Les autres échecs provenaient de la configuration de cette pile : diagnostic
  Doctrine désactivé et domaine Mercure différent du domaine des tests HTTP.
- Reprise des cinq classes métier concernées : 88 tests / 563 assertions réussis.
  Après ajout du scénario de pagination, les 33 tests du contrôleur / 236
  assertions réussissent. La reprise technique couvre 78 cas ; après correction
  du domaine de test, les 13 tests GameplayAccess / 179 assertions réussissent.
  Tous les échecs de la suite initiale sont ainsi repris. La mesure facultative
  qui exige `REWORK_MEASURE=1` reste ignorée comme dans la suite existante.
  Ce bilan est une consolidation, pas une suite complète finale en un passage.
- 13 tests Python de sauvegarde réussis.
- Les reprises navigateur suivent les nouvelles URL, sans retirer les contrôles
  de permissions, de concurrence, de CSRF ou de consentement. Bilan : 34 parcours
  distincts réussis par consolidation (4 du premier lot de tests, 14 du deuxième
  dont un déjà couvert, 16 du troisième et la dernière reprise de rivalité).
  Les deux parcours des profils ont ensuite été rejoués avec persistance activée,
  vérifiant aussi l’accueil de guilde avec logo/bannière et leur conservation
  après redémarrage du seul service web jetable.
- Contrôles directs, rechargement, retour arrière, recherche, navigation active,
  anciens paramètres et fragments, droits, propositions, grades, transfert,
  départ/dissolution et aperçu sans lecture implicite réussis. Navigation et
  création/gestion utilisables sans JavaScript. Rendu à 320/390/768/834/1440 px,
  thèmes clair/sombre et Axe ciblé ; captures représentatives examinées.
- Vérifications de syntaxe Twig/PHP finales, validité du JSON du manuel et
  `git diff --check` réussis.

Les bases des tests sont isolées. Les choix `APP_DEBUG=1` et le domaine Mercure
concernent uniquement les commandes PHP de reprise, sans configuration globale
ni changement de la base habituelle.

## Limites et intégration

Les contrôles visuels, clavier, focus et Axe sont ciblés : ils ne constituent
pas un audit de conformité RGAA complet. Les cinq jobs quality de la PR #75 ont
réussi avant sa fusion. La protection de dev impose une PR, les cinq jobs,
la mise à jour avec dev et s’applique aussi aux administrateurs ; ni force push
ni suppression autorisés. Le job publish n’est pas requis.
Le détail a été vérifié via l’accès Git existant, après le refus HTTP 403 du
connecteur sur cet endpoint. Aucun déploiement n’a été effectué.


## Fichiers du lot

- `README.md`
- `app/assets/controllers/page_sections_controller.js`
- `app/assets/styles/diplomacy-tabs.css`
- `app/src/Controller/DiplomacyController.php`
- `app/src/Repository/GameRepository.php`
- `app/src/Service/Colony/GameShell.php`
- `app/src/Service/Player/DiplomacyPageView.php`
- `app/templates/base.html.twig`
- `app/templates/colony/show.html.twig`
- `app/templates/player/_guild_create.html.twig`
- `app/templates/player/_guild_diplomacy_card.html.twig`
- `app/templates/player/_guild_hub.html.twig`
- `app/templates/player/_guild_ranks.html.twig`
- `app/templates/player/_guild_search.html.twig`
- `app/templates/player/_guild_settings.html.twig`
- `app/templates/player/_relation_card.html.twig`
- `app/templates/player/diplomacy.html.twig`
- `app/templates/player/guild_profile_edit.html.twig`
- `app/templates/player/guild_project.html.twig`
- `app/templates/player/guild_projects.html.twig`
- `app/templates/player/messages.html.twig`
- `app/templates/player/public_guild.html.twig`
- `app/templates/ui/guild_navigation.html.twig`
- `app/tests/Controller/GuildTagPlayerReportTest.php`
- `app/tests/browser/colony.spec.mjs`
- `app/tests/browser/dark-dashboard.spec.mjs`
- `app/tests/browser/diplomacy-empty-state.spec.mjs`
- `app/tests/browser/diplomacy-overview-anchors.spec.mjs`
- `app/tests/browser/guild-projects.spec.mjs`
- `app/tests/browser/guild-ranks.spec.mjs`
- `app/tests/browser/guild-tags-player-reports.spec.mjs`
- `app/tests/browser/guild-treaties.spec.mjs`
- `app/tests/browser/leaderboard-contact.spec.mjs`
- `app/tests/browser/message-markdown.spec.mjs`
- `app/tests/browser/orbit-request.spec.mjs`
- `app/tests/browser/personal-rivalries.spec.mjs`
- `app/tests/browser/player-pages-refinement.spec.mjs`
- `app/tests/browser/public-profiles.spec.mjs`
- `app/tests/browser/ui-journey.spec.mjs`
- `app/tests/browser/ui-routes-responsive.spec.mjs`
- `app/tests/browser/wireframe-theme-screens.spec.mjs`
- `docs/ARCHITECTURE.md`
- `docs/DECISIONS.md`
- `docs/FEUILLE_DE_ROUTE.md`
- `docs/GESTION_GUILDES_ALLIANCES.md`
- `docs/GUIDE_GUILDES_ALLIANCES.md`
- `docs/NAVIGATION_PAGES_DEDIEES.md`
- `docs/README.md`
- `docs/wiki.json`


## Lot 2 — Flottes (complexité 5/5)

### Pages et choix

| Adresse | Objectif |
| --- | --- |
| `/flottes` | Missions actives de la colonie, par pages de 30 |
| `/flottes/preparer` | Composition → Destination → Confirmation, avec résumé de trois missions |
| `/flottes/orbites` | Demandes envoyées et reçues de la colonie active |
| `/flottes/quai` | Vaisseaux et réserves de la colonie, stationnements en attente de tout l’empire |
| `/flottes/compositions` | Liste personnelle, chargement et suppression confirmée |
| `/flottes/compositions/creer` | Création d’une composition |
| `/flottes/compositions/{id}/modifier` | Modification avec version et propriété contrôlées |
| `/flottes/compositions/{id}/charger` | Disponibilités, manques et accord explicite avant préparation |

La tâche autonome devient une page ; les étapes d’un seul envoi restent dans
le même formulaire. Le menu principal garde une seule entrée Flottes. Une
navigation secondaire commune se replie en plusieurs lignes sur mobile et
identifie la page active, y compris dans les formulaires de composition.
Sur mobile, le formulaire précède son résumé et les arrivées étrangères.
Les nouveaux écrans n’ajoutent aucun sommaire automatique. Les ancres de vol
restent utiles dans la liste détaillée et les rapports de combat ne changent pas.

### Lectures, risques et compatibilité

`FleetPageView` prépare uniquement les lectures de la page choisie. La préparation
charge les vaisseaux de la colonie, les choix de destination et trois missions
sans composition, fret ni combats détaillés. La liste enrichit au plus 30
missions. La requête des orbites filtre la colonie avant la préparation des lignes. Les orbites et stationnements sont préparés sur leur destination.
La limite de 20 compositions par empire/univers reste inchangée. Aucun gain
chiffré de performance n’est revendiqué.

Les services métier, contrôles serveur, synchronisation, transactions, versions,
CSRF et clés d’idempotence sont réutilisés. Les détails des stations en attente
restent accessibles après perte de l’origine. Les arrivées étrangères conservent
leur périmètre et leur confidentialité ; le polling d’orbites porte toujours la
colonie active. Les fragments de mise à jour sont propres à chaque page et le
client conserve les saisies modifiées, le focus et les protections contre les
réponses périmées.

Les anciens GET `/flottes?mission=…`, `system`, `planet` ou `composition`
redirigent vers la préparation en conservant les paramètres. L’ancien `colony`
redirige vers les orbites après contrôle d’appartenance à l’arrivée. Le paramètre
`origin` des raccourcis de colonies sélectionne uniquement une colonie autorisée. Le GET
`/flottes/compositions?edit=…` mène à la modification dédiée ; les POST
historiques de composition restent acceptés. Les liens de carte, rapports,
inventaire et défis ciblent désormais la préparation. Les actions de réserve
reviennent à quai, les décisions d’orbite aux demandes et l’envoi aux missions.

Les anciens fragments `prepare-mission`, `active-missions`,
`orbit-requests-title` et `held-fleets-title` sont orientés côté navigateur.
Le serveur ne reçoit pas ces fragments : sans JavaScript, la navigation serveur
permet de rejoindre la page. Les liens de résumé comportent `fleet={id}` et une
ancre pour sélectionner la bonne page de missions même sans JavaScript ; les
anciens liens `#fleet-{id}` bénéficient de la transition côté navigateur.
Les vérifications du vol ciblé restent bornées au joueur et à sa colonie.

### Vérifications et livraison

- `make validate`, `make lint`, `make assets`, syntaxes JavaScript, validité du
  JSON du manuel et `git diff --check` réussis.
- Tests PHP ciblés : première série de 87 tests / 1 001 assertions, avec une
  mesure facultative ignorée ; reprises réussies des destinations/transports
  (24 tests / 319 assertions), compositions/destinations/orbites
  (42 / 419), destinations/occupation JcE (42 / 582). Ces séries se recouvrent.
  Les scénarios de reconstruction des empires oubliés ont aussi réussi ; les
  erreurs de chemins et de préparation des fixtures repérées ont été corrigées.
- 27 parcours navigateur distincts réussis par consolidation des reprises :
  envoi, estimation, ajustement, colonie, compositions, concurrence, stationnement,
  orbites, rappels, surveillance, états vides, quantités et navigation responsive.
  Le dernier parcours contrôlant toutes les nouvelles pages passe en clair et
  sombre, à 320 et 1 440 px, avec Axe sur `main`, focus, mise à jour sans perte
  de saisie, rechargement, retour arrière et anciens fragments. La recette
  responsive générale couvre aussi 390, 834 et 1 024 px. Les compositions et
  leurs disponibilités restent utilisables sans JavaScript.
- Les captures représentatives ont été examinées. La hiérarchie des titres,
  les contrastes du formulaire clair et la priorité du formulaire sur mobile
  ont été corrigés. Les anciens fragments réagissent aussi à un changement
  d’ancre conservant le document, sans boucle avec les paramètres de préparation.
- La première CI de brouillon a réussi sur `e5f0c90`. Elle ne constitue pas
  la CI complète ; les cinq jobs quality sont obligatoires sur la dernière
  version prête à relire de la PR #77 avant toute fusion.
- La première CI complète a validé PHP et le groupe navigateur 3, puis a signalé
  trois tests utilisant encore l’ancien accès direct au formulaire. Les tests
  suivent désormais Flottes → Préparer une mission et distinguent les menus
  principal et mobile, en conservant leurs assertions métier. Reprise locale
  réussie des neuf parcours de `colony.spec.mjs` et `espionage-report.spec.mjs`,
  y compris l’arrivée réelle sans accélération. Les groupes restants ont été
  interrompus par le retour en brouillon ; une nouvelle CI complète est requise.


Aucune migration sur une base existante, installation locale de dépendance ni
modification d’équilibrage.
Les tests écrivent uniquement dans des bases jetables distinctes de localhost.
Les vérifications RGAA sont ciblées ; lecteur d’écran réel et audit complet
restent hors de cette recette. Les trois sources Work citées en début de suivi
restent introuvables ; le wiki externe n’a pas été modifié.

Les fichiers principaux du lot sont `FleetController`, `FleetPageView`,
`FleetCompositionController`, `FleetStationingController`, `GameRepository`,
les templates `fleet/`, `ui/fleet_navigation`, le menu principal et les liens
entrants, `colony_live_controller`, `page_sections_controller`, les styles,
les tests HTTP/intégration/navigateur et les documents de navigation/flottes.


### Fichiers du lot 2

- `README.md`
- `app/assets/controllers/colony_live_controller.js`
- `app/assets/controllers/page_sections_controller.js`
- `app/assets/styles/fleet-destinations.css`
- `app/src/Controller/FleetCompositionController.php`
- `app/src/Controller/FleetController.php`
- `app/src/Controller/FleetRecallController.php`
- `app/src/Controller/FleetStationingController.php`
- `app/src/Repository/GameRepository.php`
- `app/src/Service/Fleet/FleetPageView.php`
- `app/src/Service/Fleet/OrbitRequestView.php`
- `app/templates/base.html.twig`
- `app/templates/boosts/index.html.twig`
- `app/templates/combat/simulation.html.twig`
- `app/templates/fleet/_available.html.twig`
- `app/templates/fleet/_composition_editor.html.twig`
- `app/templates/fleet/_counts.html.twig`
- `app/templates/fleet/_dock.html.twig`
- `app/templates/fleet/_mission_summary.html.twig`
- `app/templates/fleet/_pagination.html.twig`
- `app/templates/fleet/composition_load.html.twig`
- `app/templates/fleet/compositions.html.twig`
- `app/templates/fleet/index.html.twig`
- `app/templates/fleet/recall.html.twig`
- `app/templates/fleet/resume.html.twig`
- `app/templates/player/challenge.html.twig`
- `app/templates/ui/fleet_navigation.html.twig`
- `app/templates/ui/flights.html.twig`
- `app/templates/ui/incoming_attacks_list.html.twig`
- `app/templates/ui/map_conquest_actions.html.twig`
- `app/templates/ui/orbit_requests.html.twig`
- `app/templates/universe/map.html.twig`
- `app/tests/Controller/FleetDestinationTest.php`
- `app/tests/Controller/GameplayAccessTest.php`
- `app/tests/Controller/StrategicToolsTest.php`
- `app/tests/Integration/FleetStationingTest.php`
- `app/tests/Integration/ForgottenEmpireJourneyTest.php`
- `app/tests/browser/colony.spec.mjs`
- `app/tests/browser/conquest-report.spec.mjs`
- `app/tests/browser/economic-units.spec.mjs`
- `app/tests/browser/espionage-report.spec.mjs`
- `app/tests/browser/fleet-destinations.spec.mjs`
- `app/tests/browser/fleet-empty-state.spec.mjs`
- `app/tests/browser/fleet-preview-submit-state.spec.mjs`
- `app/tests/browser/fleet-recall.spec.mjs`
- `app/tests/browser/fleet-stationing.spec.mjs`
- `app/tests/browser/fleet-surveillance.spec.mjs`
- `app/tests/browser/fleet-wizard.spec.mjs`
- `app/tests/browser/orbit-request.spec.mjs`
- `app/tests/browser/quantity-max.spec.mjs`
- `app/tests/browser/strategic-tools.spec.mjs`
- `app/tests/browser/ui-journey.spec.mjs`
- `app/tests/browser/ui-routes-responsive.spec.mjs`
- `app/tests/browser/ui-state-coverage.spec.mjs`
- `app/tests/browser/wireframe-theme-screens.spec.mjs`
- `docs/ARCHITECTURE.md`
- `docs/CADRAGE_OUTILS_STRATEGIQUES.md`
- `docs/DECISIONS.md`
- `docs/DESTINATIONS_STATIONNEMENT.md`
- `docs/FEUILLE_DE_ROUTE.md`
- `docs/FLOTTES_ENTRANTES.md`
- `docs/NAVIGATION_PAGES_DEDIEES.md`
- `docs/PARCOURS_UI_UX.md`
- `docs/wiki.json`


## Lot 3 — Inventaire (complexité 4/5)

### Pages et compatibilité

- `/boosts` : entrée de l’espace, stock de boosts et utilisation sur les opérations
  actives de tout l’empire. Le choix de l’opération est un filtre d’une même tâche.
- `/inventaire/boites` : contenu disponible, regroupement par contenu identique,
  choix explicite d’une colonie, confirmation et ouverture.
- `/inventaire/recompenses` : récompenses conservées, type, origine et date,
  présentées dans l’ordre d’attribution et paginées par 25.

Chaque tâche a son titre, son URL et la navigation secondaire commune, avec
espace et page actifs. Le menu principal garde une seule entrée Inventaire.
La capacité partagée (boosts et boîtes) reste visible sur les trois pages. L’aide
complémentaire reste dépliable et aucun sommaire automatique ne recrée les sections.
Les fragments historiques `inventory-boosts`, `inventory-boxes`,
`inventory-boxes-title`, `inventory-pending`, `inventory-pending-title`,
`available-bonuses-title` et `boost-use-title` rejoignent leur destination côté
navigateur, y compris sans rechargement du document. Sans JavaScript, la
navigation serveur donne accès aux mêmes pages ; le serveur ne reçoit pas les fragments.

Les POST existants et leurs jetons restent inchangés. L’ouverture revient aux
Boîtes, avec message de succès ou de refus ; le boost revient à Boosts. Les
confirmations existantes et le retour du focus sont conservés. Aucun changement
de règle, de capacité, de permissions, de configuration ou de données persistées.

### Préparation et risques

`InventoryPageView` utilise une agrégation par statut et type pour les compteurs.
Boosts ne charge pas les objets utilisés, ni les boîtes, ni les récompenses en
attente. Les opérations restent celles de tout l’empire, préparées seulement si
un boost est disponible et hors vacances. Boîtes ne prépare pas les opérations ;
les objets disponibles sont bornés par la capacité maximale existante de 100.
Attente hydrate au plus 25 objets privés à la fois ; son stockage reste illimité.
Les regroupements ouvrent toujours le plus ancien objet disponible de même contenu.

La synchronisation initiale est conservée. Les transactions, verrous, contrôles
d’univers, de propriété, de vacances, de capacité de stockage et les reçus
idempotents restent dans les services métier existants. La promotion du plus
ancien objet en attente reste automatique lors d’une consommation. Aucune mesure
globale de performance n’est annoncée.

### Vérifications et livraison

- `make validate`, `make lint`, `make assets`, contrôles de syntaxe JavaScript,
  JSON du manuel et `git diff --check` réussis.
- 17 tests des services de boosts/boîtes et cinq tests HTTP dédiés réussis par
  reprises. Ces derniers couvrent 128 assertions : accès directs, titres,
  navigation active, capacité commune, absence de données tierces ou d’historique
  utilisé, lectures bornées, pagination, CSRF, propriété et rejeu sans double effet.
  Le scénario d’ouverture valide prépare explicitement des entrepôts disponibles ;
  les tests métier distincts conservent le refus lorsque le stockage est plein.
- 16 parcours navigateur distincts réussis : 13 métier (événements, boosts,
  boîtes, vacances, stockage, protections, confirmations et consommation native
  sans JavaScript), puis trois de navigation/états/accessibilité. Les nouvelles
  pages sont contrôlées à 320/390/1 440 px, dans les deux thèmes, avec Axe sur
  `main`, liens directs, rechargement, retour arrière, focus clavier, anciens
  fragments et pagination. La navigation et la lecture paginée fonctionnent
  aussi sans JavaScript. Les captures représentatives ont été examinées ; le
  compteur d’attente reste sur une ligne sur mobile.
- La première CI légère a réussi sur `2f52096`. Elle ne remplace pas les cinq
  jobs quality obligatoires sur la dernière version prête à relire avant fusion.

Les tests utilisent uniquement des bases jetables distinctes de la base locale habituelle. Aucun déploiement ni migration d’une base existante.
Les contrôles d’accessibilité sont ciblés, sans audit de conformité complet.
Les trois sources Work citées plus haut restent introuvables ; le wiki externe
n’a pas été actualisé. Le manuel embarqué et la documentation du dépôt sont synchronisés.


### Fichiers du lot 3

- `README.md`
- `app/assets/controllers/page_sections_controller.js`
- `app/assets/styles/player-pages.css`
- `app/src/Controller/BoostController.php`
- `app/src/Repository/GameRepository.php`
- `app/src/Service/Boost/InventoryPageView.php`
- `app/templates/base.html.twig`
- `app/templates/boosts/_boosts.html.twig`
- `app/templates/boosts/_pending.html.twig`
- `app/templates/boosts/_review_dialog.html.twig`
- `app/templates/boosts/index.html.twig`
- `app/templates/ui/inventory_navigation.html.twig`
- `app/tests/Controller/InventoryPagesTest.php`
- `app/tests/browser/admin-event-wizard.spec.mjs`
- `app/tests/browser/catalogue-inventory.spec.mjs`
- `app/tests/browser/player-pages-refinement.spec.mjs`
- `app/tests/browser/resource-boxes.spec.mjs`
- `docs/ARCHITECTURE.md`
- `docs/DECISIONS.md`
- `docs/FEUILLE_DE_ROUTE.md`
- `docs/NAVIGATION_PAGES_DEDIEES.md`
- `docs/PARCOURS_UI_UX.md`
- `docs/wiki.json`


## Lot 4 — Mon compte et Mode vacances (complexité 5/5)

### Pages et périmètres

- `/profil` : Mes identifiants, email, pseudo et changement de mot de passe
  facultatif. La saisie du mot de passe actuel et la fermeture de session après
  enregistrement restent obligatoires comme auparavant.
- `/profil/preferences` : préférences communes du compte, dont thème, horloge,
  confort, réception des transports et vitesses des raccourcis de galaxie.
- `/profil/vacances` : état et commandes de l’empire de l’univers sélectionné,
  nommé dans l’en-tête. Sans empire accessible, information et lien vers les univers.
- `/profil/supprimer` : conséquences visibles, mot de passe et confirmation
  explicite avant la suppression de tout le compte. Le GET ne supprime rien.

Une navigation secondaire commune relie ces vraies pages et marque la page
active ; le menu principal conserve Mon compte actif. Le sommaire automatique
est désactivé sur ces tâches et les composants/couleurs existants sont conservés.

### Compatibilité et protections

Les routes POST de profil, préférences, suppression et vacances gardent leurs
adresses, noms, champs et jetons. Les actions de vacances reviennent à leur page,
les refus de suppression à la suppression, les préférences à Préférences ; les
modifications d’identifiants et la suppression réussie conservent la déconnexion.
Le GET historique `/profil?delete=1` rejoint la suppression côté serveur.
Les fragments `vacances`, `vacances-info`, `vacation-heading` et
`account-delete-title` sont orientés côté navigateur, à l’ouverture ou lors d’un
changement de fragment. Sans JavaScript, la navigation serveur reste disponible.

`VacationController` reprend les contrôles existants de propriétaire, participation,
univers accessible, identifiant d’empire sélectionné et CSRF. Les conditions de
fonctionnalité, durée minimale, délai de réactivation, flottes et combat restent
celles de `VacationEconomyService` et `VacationPolicy`. La suppression conserve
la protection du dernier administrateur, du combat en cours et toutes ses
transactions/conversions dans `AccountDeletionService`, non modifié.

Préférences ne prépare plus l’état des vacances et Vacances ne construit aucun
formulaire de préférences. Le GET de suppression ne construit aucun formulaire
d’identifiants. L’affichage des vacances lit seulement la dernière période,
sans retirer l’historique nécessaire aux calculs et sans toucher aux écritures
sous verrou. Aucun gain chiffré de performance n’est annoncé.

### Vérifications et livraison

- `make validate`, `make lint`, `make assets`, syntaxe JavaScript, JSON du manuel
  et `git diff --check` réussis.
- 75 tests PHP / 447 assertions réussis : préférences, vacances, accès de jeu,
  suppression de compte, économie et missions pendant les vacances. Puis
  13 tests du contrôleur / 125 assertions réussis, dont quatre nouveaux cas :
  pages et formulaires séparés, état actif, accès sans empire et anonyme,
  ancien raccourci, formulaire obsolète après changement d’univers et lecture
  d’une seule période parmi 40 sans retirer l’historique. Les deux séries se recouvrent.
- Neuf parcours navigateur distincts réussis par consolidation : préférences,
  erreur et conservation des champs, reconnexion après modification, suppression
  et fermeture des autres sessions, quatre pages directes, thèmes et navigation,
  accès clavier, rechargement, retour arrière, anciens fragments, aperçu général,
  carte et thème clair. Les nouvelles pages passent Axe sur `main` à
  320/390/1 440 px, dans les deux thèmes ; des captures ont été examinées.
- Le parcours natif sans JavaScript se connecte dans une session neuve : cloner
  une session déjà connectée avec JavaScript déclenche la protection CSRF existante
  contre le déclassement de double-submit vers l’origine seule. Aucun mécanisme
  CSRF n’a été modifié. Préférences sauvegardées, sortie d’une période active et
  suppression confirmée réussissent dans cette session native.
- Les cinq jobs quality de la dernière version prête à relire restent requis
  avant fusion. Les contrôles locaux ne sont pas une validation CI complète.

Les essais utilisent exclusivement des bases jetables distinctes de la base habituelle. La configuration navigateur garde le drapeau existant de vacances
désactivé ; les tests HTTP contrôlent aussi son activation autorisée. Le cas
navigateur d’une période déjà active conserve la sortie autorisée même quand
le drapeau est désactivé. Aucune modification de configuration globale, règle,
dépendance ou schéma. Aucun déploiement ni migration d’une base existante.
Les contrôles RGAA sont ciblés ; les sources Work restent introuvables et le wiki
externe n’a pas été modifié. Le manuel embarqué est synchronisé.


### Fichiers du lot 4

- `README.md`
- `app/assets/controllers/page_sections_controller.js`
- `app/assets/styles/player-pages.css`
- `app/src/Controller/PreferencesController.php`
- `app/src/Controller/ProfileController.php`
- `app/src/Controller/VacationController.php`
- `app/src/Repository/GameRepository.php`
- `app/src/Service/Player/VacationEconomyService.php`
- `app/templates/base.html.twig`
- `app/templates/player/account_delete.html.twig`
- `app/templates/player/preferences.html.twig`
- `app/templates/player/profile.html.twig`
- `app/templates/player/vacation.html.twig`
- `app/templates/ui/account_navigation.html.twig`
- `app/templates/ui/colony_queues.html.twig`
- `app/tests/Controller/GameplayAccessTest.php`
- `app/tests/Controller/VacationPreferencesTest.php`
- `app/tests/browser/galaxy-v2.spec.mjs`
- `app/tests/browser/player-pages-refinement.spec.mjs`
- `app/tests/browser/preferences-accessibility.spec.mjs`
- `app/tests/browser/profile-forms.spec.mjs`
- `app/tests/browser/ui-journey.spec.mjs`
- `app/tests/browser/wireframe-theme-screens.spec.mjs`
- `docs/ARCHITECTURE.md`
- `docs/DECISIONS.md`
- `docs/FEUILLE_DE_ROUTE.md`
- `docs/NAVIGATION_PAGES_DEDIEES.md`
- `docs/PARCOURS_UI_UX.md`
- `docs/REGLES.md`
- `docs/wiki.json`
