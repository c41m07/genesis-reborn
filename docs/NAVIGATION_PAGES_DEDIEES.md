# Navigation par espaces et pages dédiées

## État du chantier — 17 novembre 2026

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

Lot 4 intégré sur dev par la [PR #79](https://github.com/c41m07/genesis-reborn/pull/79),
merge `5b072cb`, après les cinq jobs quality réussis sur `afe19b8`
([CI 43975029731](https://github.com/c41m07/genesis-reborn/actions/runs/43975029731)).
Application locale actualisée avec assets, cache et routes vérifiés ; modification
préexistante conservée. Le bilan ci-dessous garde la recette avant intégration.

Lot 5 intégré sur dev par la [PR #80](https://github.com/c41m07/genesis-reborn/pull/80),
merge `4fa63a7`, après les cinq jobs quality réussis sur `6f969da`
([CI 43982486963](https://github.com/c41m07/genesis-reborn/actions/runs/43982486963)).
Localhost actualisé avec assets, cache et routes vérifiés ; modification préexistante
conservée. Le bilan du lot 5 ci-dessous garde sa recette avant intégration.

Lot 6 intégré sur dev par la [PR #82](https://github.com/c41m07/genesis-reborn/pull/82),
merge `8f1bc6e`, après les cinq jobs quality réussis sur `25bb0ae`
([CI 44001350739](https://github.com/c41m07/genesis-reborn/actions/runs/44001350739)).
La PR #81 conserve les premières validations et est fermée sans fusion.
Application locale actualisée, modification préexistante conservée. La recette
ci-dessous garde les étapes précédant l’intégration.

Lot 7 intégré sur dev par la [PR #83](https://github.com/c41m07/genesis-reborn/pull/83),
merge `7b7d92d`, après les cinq jobs quality réussis sur `e7616ed`
([CI 44048538481](https://github.com/c41m07/genesis-reborn/actions/runs/44048538481)).
Localhost actualisé avec compilation, cache, routage et assets servis vérifiés ;
modification locale préservée. Le bilan du lot 7 garde sa recette et ses reprises
avant intégration.

Lot 8 intégré sur dev par la [PR #84](https://github.com/c41m07/genesis-reborn/pull/84),
merge `dff8e11`, après les cinq jobs quality réussis sur `fad20ad`
([CI 44057167152](https://github.com/c41m07/genesis-reborn/actions/runs/44057167152)).
Localhost actualisé avec compilation, cache, six routes et quatre assets servis
vérifiés ; modification locale préservée. Les huit lots fonctionnels sont intégrés.

Les bilans ci-dessous conservent les vérifications locales et les reprises de
chaque lot. Leur état d’intégration est donné ici ; les échecs intermédiaires
sont des traces historiques, pas des contrôles encore manquants sur ces versions.
La recette transversale finale et ses limites figurent en fin de document.

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
5. Projets collectifs : 4/5 après examen ; contributions, historiques paginés,
   permissions, versions et isolation des univers.
6. Événements : 4/5 après examen ; visibilité, filtres JSON avant pagination,
   périmètres d’univers et panneau commun conservé.
7. Administration : 4/5 après examen ; droits, formulaires, édition des modèles
   de rotations sous verrou et parcours guidés existants.
8. Wiki : 4/5 confirmé ; pages serveur, recherche globale, références inchangées
   et compatibilité des paramètres/fragments avec ou sans JavaScript.

Ces complexités ont été confirmées lors de l’examen ciblé de chaque lot.
Chaque lot dépendant a démarré après l’intégration du précédent dans dev.
La recette transversale finale est de complexité 4/5 : huit espaces, contextes
de colonie/univers, droits et compatibilité des adresses à vérifier ensemble.

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
- La première CI de brouillon a réussi sur `e5f0c90`. La CI complète finale
  a ensuite validé les cinq jobs quality sur `b6d1aaf`, avant la fusion de la PR #77.
- La première CI complète a validé PHP et le groupe navigateur 3, puis a signalé
  trois tests utilisant encore l’ancien accès direct au formulaire. Les tests
  suivent désormais Flottes → Préparer une mission et distinguent les menus
  principal et mobile, en conservant leurs assertions métier. Reprise locale
  réussie des neuf parcours de `colony.spec.mjs` et `espionage-report.spec.mjs`,
  y compris l’arrivée réelle sans accélération. Les groupes restants ont été
  interrompus par le retour en brouillon ; la CI finale citée en tête de suivi
  a validé la reprise avant fusion.


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
- La première CI légère a réussi sur `2f52096`. Les cinq jobs quality de la
  CI complète finale ont réussi sur `7643609` avant la fusion de la PR #78.

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
- `app/tests/Integration/AdminInterventionTest.php`
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
- Les cinq jobs quality ont réussi sur `afe19b8` avant la fusion de la PR #79.
  Les contrôles locaux et la CI GitHub restent distingués dans le relevé initial.

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


## Lot 5 — Projets collectifs

Complexité 4/5 confirmée : droits délégués, formulaires périmés, confidentialité
et isolation d’univers ; les dons restent des écritures sensibles confiées aux
services existants. Analyse des routes, contrôleur, préparations, fragments,
liens entrants, styles et tests du domaine avant modification. Aucun rafraîchissement
périodique des projets n’était présent ; aucun n’est introduit.

| Page | Adresse | Objectif |
| --- | --- | --- |
| Projets en cours | `/guildes/projets` | Entrée, progression et accès aux fiches de contribution. |
| Projets disponibles | `/guildes/projets/disponibles` | Conditions et lancement selon les droits existants. |
| Historique des projets | `/guildes/projets/historique` | Projets terminés ou abandonnés et accès aux bilans. |
| Fiche d’un projet | `/guildes/projets/{id}` | Détail, dons, conditions figées et contributions paginées. |

### Compatibilité et préparation

Les routes et champs POST, jetons CSRF et versions restent identiques. Un lancement
réussi ouvre la fiche ; une erreur revient aux projets disponibles. Les retours
des fiches suivent leur état actif ou historique. Les anciennes ancres
`ongoing-projects-title`, `available-projects-title` et `completed-projects-title`
sont orientées à l’ouverture ou au changement du fragment, avec paramètres
conservés. Le serveur ne reçoit pas le fragment ; sans JavaScript, les liens
communs restent disponibles. Le menu principal garde Ma guilde actif, sa navigation
Projets collectifs, puis la navigation du projet sa page active.

`GuildProjectPageView` prépare la seule destination demandée. Les listes sont
bornées à 25 et un témoin de suite, avec curseur d’identifiant décroissant.
Les modèles déjà lancés sont exclus en base, sans hydrater l’historique. Dons
personnels et antécédents de récompenses sont lus ensemble pour les lignes visibles.
La fiche sépare contribution personnelle complète et page de contributeurs,
puis charge les identités de cet univers. Seule la fiche active prépare les
colonies de don. Le contexte commun de colonie reste celui de GameShell.
Les états actifs, terminés et abandonnés ne changent pas ; les permissions,
vacances, coûts figés, transactions, verrous, versions, idempotence et protections
de don/récompense restent dans les services actuels. Aucun modèle n’est activé.
Aucune mesure comparative de performance n’est revendiquée.

### Vérifications et livraison

Validation Compose, lint et compilation réussis ; 43 tests PHP ciblés / 226
assertions, puis 8 tests HTTP / 155 assertions (séries partiellement communes).
Une fixture détachée après requête a été rechargée pour tester le refus entre
guildes sans modifier les contrôles serveur. Quatre parcours navigateur distincts
réussis : modèles administratifs existants, deux paliers et boosts, parcours joueur
sans JavaScript, parcours transversal. La reprise du deuxième parcours utilise
la préférence de thème réellement persistée, après examen des premières captures ;
elle réussit. Les trois listes et la fiche passent Axe sur `main` dans les deux
thèmes à 320/390/1 440 px, sans débordement. Navigation active à ses trois niveaux,
clavier, liens directs, rechargement, retour arrière et anciens fragments vérifiés.
Captures réelles sombre sur ordinateur et claire sur mobile examinées.

Les essais utilisent des bases jetables distinctes de la base habituelle.
La [PR #80](https://github.com/c41m07/genesis-reborn/pull/80) a été fusionnée après
les cinq jobs quality réussis sur `6f969da`. La CI et l’actualisation locale sont
consignées séparément en tête de suivi.

Les documents Work V4/V2/V1 ne sont pas retrouvés par les recherches locales et
connectées ; aucune lecture ou validation de leurs phases n’est revendiquée.
Manuel embarqué mis à jour ; wiki externe non synchronisé. Les contrôles RGAA
restent ciblés. Aucune dépendance, migration, règle de jeu ou valeur modifiée.
Aucune migration de base existante ni livraison Alpha.


### Fichiers du lot 5

- `README.md`
- `app/assets/controllers/page_sections_controller.js`
- `app/assets/styles/player-pages.css`
- `app/src/Controller/GuildProjectController.php`
- `app/src/Repository/GameRepository.php`
- `app/src/Service/Boost/BoostGrantService.php`
- `app/src/Service/Player/GuildProjectPageView.php`
- `app/templates/base.html.twig`
- `app/templates/player/_guild_project.html.twig`
- `app/templates/player/_guild_project_summary.html.twig`
- `app/templates/player/guild_project.html.twig`
- `app/templates/player/guild_projects.html.twig`
- `app/templates/ui/guild_navigation.html.twig`
- `app/templates/ui/guild_project_navigation.html.twig`
- `app/tests/Controller/GuildProjectPagesTest.php`
- `app/tests/browser/guild-projects.spec.mjs`
- `docs/ARCHITECTURE.md`
- `docs/DECISIONS.md`
- `docs/FEUILLE_DE_ROUTE.md`
- `docs/GESTION_GUILDES_ALLIANCES.md`
- `docs/GUIDE_GUILDES_ALLIANCES.md`
- `docs/NAVIGATION_PAGES_DEDIEES.md`
- `docs/PARCOURS_UI_UX.md`
- `docs/wiki.json`


## Lot 6 — Événements

Complexité 4/5 après examen du contrôleur, des préparations de listes/fiches,
du panneau commun et des comptes à rebours, des liens entrants, tests et suivis.
Le risque principal est de changer les critères de visibilité en séparant les
listes, ou de perdre progression, place et récompense dans le panneau commun.
Les décisions de retrait/archivage, d’annulation, de dates et d’attribution ont
été relues ; aucun traitement métier correspondant n’est modifié.

| Destination | Adresse | Objectif |
| --- | --- | --- |
| Événements et bonus | `/evenements` | Entrée, annonces et bonus en cours ou à venir. |
| Défis et rotations | `/evenements/defis` | Défis annoncés, rotations et derniers bilans. |
| Fiche d’un défi | `/evenements/defis/{id}` | Objectif, dates, récompense et participation personnelle. |

### Compatibilité et préparation

Les deux listes partagent des liens serveur avec état actif ; les fiches gardent
Événements actif dans le menu principal et Défis et rotations dans la navigation.
Les annonces courantes et futures conservent leurs ancres utiles. Le fragment
`#page-section-0` du titre des défis de l’ancien sommaire est orienté vers
`/evenements/defis#challenges-list`, avec les paramètres, à l’ouverture et au
changement de fragment. Sans JavaScript, les liens serveur restent disponibles.
Le sommaire automatique est désactivé sur ces pages ; les rapports restent inchangés.

Les critères de `GameRepository` sont partagés entre pages et panneau. Les
valeurs JSON fausses ou vides gardent la sémantique PHP antérieure, et le préfixe
`weekly-` garde sa sensibilité à la casse. Ces filtres sont appliqués avant les
comptages et limites ; des occurrences hors parcours ne masquent plus une page
de défis. Le mapping Doctrine conserve les types sur une seule lecture filtrée.
Les annonces sont paginées par 25 dans chacun des deux groupes ; leurs paramètres
se conservent mutuellement. Les défis sont paginés par 25, sans nouveau plafond
de conservation. La limite historique de `forPlayer` reste 50 pour ses consommateurs.

Le panneau garde son périmètre commun en cours/à venir, ses informations personnelles,
son compteur visible fermé et ses durées. Aucun historique de défis n’y est chargé.
Il s’agit de données communes déjà présentes, distinctes des nouvelles listes paginées.
Les anciennes fiches restent consultables selon leurs gardes existants, y compris
les fiches archivées exclues des listes courantes. Aucune nouvelle actualisation
périodique ; aucune modification de production, contribution ou récompense.
Aucun gain de performance n’est annoncé sans mesure comparative.

### Vérifications et livraison

Validation Compose et compilation réussies. Lint complet réussi sur pile isolée
avec `APP_DEBUG=1` : le conteneur non-debug gardait un cache antérieur à la nouvelle
injection du repository. Le cache debug a été régénéré ; aucune configuration
globale ni protection n’a été modifiée pour cette vérification.
28 tests métier / 104 assertions, puis 6 tests HTTP / 74 assertions réussis :
visibilité, types JSON, casse du préfixe, groupes paginés et paramètres conservés,
filtrage avant limites, univers, anonymes, participation, métadonnées privées et
ancienne fiche archivée. Douze parcours navigateur réussis : assistants, défis,
récompenses, retraits protégés, rotations, panneau fermé, pages dédiées et écrans
joueur. Les trois écrans concernés passent Axe sur `main` en thèmes réellement
enregistrés clair/sombre à 320/390/1 440 px. Clavier, liens directs, rechargement,
retour arrière, changement de fragment et paramètres de l’ancien lien vérifiés.
Connexion native sans JavaScript, navigation des deux listes et fiche réussies.
Captures sombre ordinateur et claire mobile examinées. Les essais utilisent des
bases jetables distinctes de la base habituelle. La [PR #81](https://github.com/c41m07/genesis-reborn/pull/81)
a été fermée sans fusion ; la PR #82 a intégré le lot après les cinq jobs quality
réussis sur `25bb0ae`. Les reprises ci-dessous expliquent cette succession.

README, architecture, décisions, règles de navigation, parcours, feuille de route
et manuel embarqué synchronisés. Les trois documents Work V4/V2/V1 ne sont pas
retrouvés dans les recherches locales et connectées ; aucune lecture intégrale
ni validation de leurs phases n’est revendiquée. Wiki externe non synchronisé.
Contrôles RGAA ciblés, sans audit complet. Aucune dépendance, migration, valeur
ou règle de jeu modifiée. Aucune migration de base existante ni livraison Alpha.


### Reprise de la première CI complète

La [CI 43990158030](https://github.com/c41m07/genesis-reborn/actions/runs/43990158030)
sur `f987b36` exécute 1 386 tests PHP, avec deux échecs dans les parcours existants
Transport et Intervention administrative. Reproduction isolée : mêmes deux échecs.
Leurs requêtes suivantes redémarraient le noyau, remplaçant l’horloge simulée du
16 novembre par l’heure réelle du 17 novembre ; la protection normale de session
à 24 h invalidait alors l’authentification. Les deux seuls scénarios concernés
conservent désormais leur horloge entre requêtes. Aucune assertion n’est retirée,
aucun code d’expiration, droit ou traitement métier n’est changé. Reprise locale :
40 tests / 179 assertions réussis. Une nouvelle CI complète reste obligatoire sur
le commit corrigé ; les résultats de la première exécution ne permettent pas de fusionner.

### Reprise du test clavier du wiki

La [deuxième CI](https://github.com/c41m07/genesis-reborn/actions/runs/43993199017)
sur `be9ea53` réussit le PHP et trois lots navigateur ; le quatrième compte
51 réussites et un échec intermittent dans le parcours clavier du wiki.
La reprise isolée de ce scénario réussit. Le test pouvait valider le premier
chapitre encore visible avant l’initialisation du contrôleur, puis enchaîner
ses actions trop tôt. Il attend désormais le signal `wiki-chapters-ready` et
vérifie l’URL de chaque chapitre après activation. Les assertions de contenu,
clavier, thèmes, formules, sources et accès natif restent présentes.
Le wiki de l’application n’est pas modifié. Trois reprises consécutives du
scénario renforcé réussissent. Une nouvelle CI complète reste requise sur ce commit.

### Identifiants synthétiques des tests

Le contrôle GitGuardian signale le mot de passe fictif de la nouvelle fixture HTTP,
présent depuis le premier commit de la PR #81. Ces comptes n’existent que sur les
bases jetables, mais leurs mots de passe sont désormais générés à l’exécution,
également dans le parcours navigateur. Aucune exception de détection n’est ajoutée.
Une branche de remplacement repart de dev avec le contenu final corrigé, sans
reprendre le littéral dans ses commits. La branche et la PR #81 restent conservées
pour la traçabilité, sans reset, rebase ni push forcé. La nouvelle PR et sa CI complète
donnent l’état de livraison ; les contrôles précédents ne valent pas validation
sur le nouvel historique. Reprise locale avec identifiants générés : 6 tests HTTP /
74 assertions et le parcours navigateur dédié réussissent. La [PR #82](https://github.com/c41m07/genesis-reborn/pull/82)
remplace la PR #81, fermée sans fusion ; le scan de secrets et les cinq jobs quality
doivent réussir sur sa dernière version avant intégration.

### Fichiers du lot 6

- `README.md`
- `app/assets/controllers/page_sections_controller.js`
- `app/assets/styles/player-pages.css`
- `app/src/Controller/GameEventsController.php`
- `app/src/Repository/GameRepository.php`
- `app/src/Service/Event/ChallengeQueryService.php`
- `app/src/Service/Event/EventPanelService.php`
- `app/templates/base.html.twig`
- `app/templates/player/challenge.html.twig`
- `app/templates/player/challenges.html.twig`
- `app/templates/player/events.html.twig`
- `app/templates/ui/event_drawer.html.twig`
- `app/templates/ui/event_navigation.html.twig`
- `app/templates/ui/event_pagination.html.twig`
- `app/tests/Controller/EventPagesTest.php`
- `app/tests/Controller/TransportTest.php`
- `app/tests/browser/admin-event-wizard.spec.mjs`
- `app/tests/browser/event-pages.spec.mjs`
- `app/tests/browser/player-support-worlds.spec.mjs`
- `app/tests/browser/wiki.spec.mjs`
- `docs/ARCHITECTURE.md`
- `docs/DECISIONS.md`
- `docs/FEUILLE_DE_ROUTE.md`
- `docs/NAVIGATION_PAGES_DEDIEES.md`
- `docs/PARCOURS_UI_UX.md`
- `docs/REGLES.md`
- `docs/wiki.json`

## Lot 7 — Administration — 17 novembre 2026

Complexités : univers 3/5, catalogue 4/5, événements 3/5, projets 3/5,
rotations 4/5. Risques : contexte d’univers, données préparées, formulaires
périmés, CSRF, redirections et compatibilité des fragments. La console générale
refactorisée n’est pas recommencée.

| Espace | Destinations |
| --- | --- |
| Univers | `/admin/univers`, `/admin/universes/nouveau`, `/admin/universes/{id}/gerer` |
| Catalogue | `/admin/universes/{id}/catalogue`, `…/{group}`, `…/{group}/{slug}` ; recherche GET `q` dans toutes les familles |
| Événements | `/admin/events`, assistant existant, `/admin/events/nouveau`, `/admin/events/{id}/edit` |
| Projets | `/admin/projets-guilde`, `…/nouveau`, `…/{id}/modifier` |
| Rotations | `/admin/rotations?universe={id}`, `…/nouveau?universe={id}`, `…/{id}/{key}/modifier` |

Les anciennes routes POST et leurs jetons restent valables. `?edit=` des modèles
de projets redirige vers la fiche. Les fragments univers et catalogue sont
interprétés dans le navigateur, également après changement de fragment ; sans
JavaScript, les listes offrent des liens vers les fiches. Les GET de liste ne
préparent plus leurs formulaires ; les univers et modèles de projets sont paginés
par 50. Les références du catalogue, règles, conditions d’accès, droits et
confirmations sensibles sont conservés. L’édition de rotation garde clé/ordre,
valide une empreinte sous verrou et préserve les occurrences existantes.

Validation locale : `make validate`, `make lint-static` (PHP, YAML, Twig et
conteneur), `make assets` et `git diff --check` réussis. Les bases de test sont
jetables et distinctes de l’application locale normale. Pas de suite complète
PHP/navigateur locale revendiquée : 22 tests PHP distincts et 28 parcours navigateur
distincts réussissent par consolidation des exécutions et reprises ciblées.
Le test HTTP de navigation compte 132 assertions ; il dépasse une page de listes,
vérifie les filtres avant pagination, les liens directs, 404 et refus d’accès.
Les tests du catalogue couvrent sauvegarde, restauration et erreur avec saisie
conservée. Ceux des rotations couvrent la configuration figée d’une occurrence,
le refus d’une version périmée et d’une clé appartenant à un autre univers.
Deux appels séquentiels ne constituent pas une preuve de concurrence réelle.

Les parcours navigateur couvrent les formulaires natifs sans JavaScript, les
brouillons, les validations et redirections, les anciennes ancres, le clavier,
le focus, les titres et libellés, le retour arrière et le rechargement. Les pages
sont contrôlées en clair/sombre et à 390/1 440 px ; plusieurs parcours existants
couvrent aussi tablette et 1 920 px. Axe est utilisé sur les nouvelles fiches.
Le contrôle visuel corrige le sommaire automatique quand la désactivation est
portée par `main`, ainsi que le contraste des états d’univers et de la zone
sensible en thème clair. La navigation de groupe reste accessible dans le menu ;
sa rangée redondante est retirée des nouvelles pages. Les alertes de fermeture
proche et d’univers fermé sont également vérifiées par Axe dans les deux thèmes. Les formulaires ne sont
pas remplacés périodiquement ; une saisie catalogue en cours reste intacte.

La [PR #83](https://github.com/c41m07/genesis-reborn/pull/83) a été fusionnée dans dev
par `7b7d92d`, après le scan de secrets et les cinq jobs quality réussis sur
`e7616ed` (CI 44048538481). L’application locale a ensuite été actualisée avec
préservation de sa modification préexistante. Aucune migration de base existante
ni livraison Alpha. Les deux reprises suivantes sont historiques et ont été
validées par cette CI finale.

Les documents Work V4, brief Figma V2 et protocole adaptatif V1 ne sont pas
retrouvés dans les sources locales et connectées accessibles ; aucune lecture
intégrale ni validation de leurs phases n’est revendiquée. Wiki externe non
synchronisé. Les vérifications d’accessibilité ciblées ne constituent pas un
audit RGAA complet. Aucun gain de performance mesuré n’est revendiqué.

### Dernière reprise avant CI complète

La [première CI complète](https://github.com/c41m07/genesis-reborn/actions/runs/44042306526)
sur `40cd20f` réussit le job PHP, incluant les contrôles de schéma et de sauvegarde.
Elle est interrompue au retour en brouillon pour une dernière correction ciblée :
un scénario de refus CSRF ouvrait encore l’ancien tiroir d’univers. Il rejoint
maintenant la fiche dédiée, en conservant tous ses contrôles de refus et de
non-écriture. Les alertes conditionnelles de fermeture reçoivent des couleurs
lisibles en thème clair. Les deux parcours concernés réussissent après correction.
Une nouvelle CI complète sur le dernier commit reste requise ; le résultat PHP
de cette première exécution n’autorise pas la fusion de la version suivante.

### Reprise du contrôle navigateur des paramètres

La [CI suivante](https://github.com/c41m07/genesis-reborn/actions/runs/44044536180)
sur `48fa0a5` valide le PHP et trois lots navigateur. Le quatrième réussit
51 scénarios et échoue sur les dimensions d’une case à cocher : mesure native de
13 px avant les 18 px attendus. Une autre capture du même fichier constate aussi
le profil sélectionné avant son application par Stimulus. Le test attend désormais
le chargement complet après redirection, puis les dimensions exactes, sans marge
supplémentaire. La première capture vérifie aussi les cinq vitesses à ×2 au lieu
de les journaliser seulement. Aucune assertion de sauvegarde, erreur, stock,
permission, accessibilité ou mode natif n’est retirée. Les quatre scénarios du
fichier réussissent localement ; le parcours de sauvegarde est également vérifié
avec une feuille de style volontairement retardée. Aucun service métier ou CSS
n’est modifié par cette dernière reprise. La dernière version exige sa propre
CI complète avant fusion.

### Fichiers du lot 7

- `README.md`
- `app/assets/controllers/page_sections_controller.js`
- `app/assets/styles/admin-dashboard.css`
- `app/src/Controller/AdminController.php`
- `app/src/Controller/AdminGameEventController.php`
- `app/src/Controller/AdminGuildProjectController.php`
- `app/src/Controller/AdminWeeklyRotationController.php`
- `app/src/Repository/AdminReadRepository.php`
- `app/src/Service/Admin/CatalogBalanceService.php`
- `app/src/Service/Boost/WeeklyRotationService.php`
- `app/templates/admin/_catalogue_form.html.twig`
- `app/templates/admin/_list_pagination.html.twig`
- `app/templates/admin/_navigation.html.twig`
- `app/templates/admin/_page_navigation.html.twig`
- `app/templates/admin/_universe_creation.html.twig`
- `app/templates/admin/_universe_settings.html.twig`
- `app/templates/admin/catalogue.html.twig`
- `app/templates/admin/event_form.html.twig`
- `app/templates/admin/events.html.twig`
- `app/templates/admin/guild_project_list.html.twig`
- `app/templates/admin/guild_projects.html.twig`
- `app/templates/admin/layout.html.twig`
- `app/templates/admin/sections/universes.html.twig`
- `app/templates/admin/universe_manage.html.twig`
- `app/templates/admin/universe_new.html.twig`
- `app/templates/admin/weekly_rotation_form.html.twig`
- `app/templates/admin/weekly_rotations.html.twig`
- `app/tests/Controller/AdminDedicatedPagesTest.php`
- `app/tests/Controller/CatalogBalanceControllerTest.php`
- `app/tests/Integration/WeeklyRotationTest.php`
- `app/tests/browser/access-denied-validation-gates.spec.mjs`
- `app/tests/browser/admin-catalogue.spec.mjs`
- `app/tests/browser/admin-event-wizard.spec.mjs`
- `app/tests/browser/admin-events-responsive.spec.mjs`
- `app/tests/browser/admin-pages.spec.mjs`
- `app/tests/browser/catalogue-inventory.spec.mjs`
- `app/tests/browser/guild-projects.spec.mjs`
- `app/tests/browser/universe-colonies-ui.spec.mjs`
- `app/tests/browser/universe-settings-ui.spec.mjs`
- `docs/ARCHITECTURE.md`
- `docs/DECISIONS.md`
- `docs/FEUILLE_DE_ROUTE.md`
- `docs/NAVIGATION_PAGES_DEDIEES.md`
- `docs/PARCOURS_UI_UX.md`
- `docs/REFONTE_ADMINISTRATION.md`
- `docs/REGLES.md`

## Lot 8 — Wiki — 17 novembre 2026

Complexité 4/5. Risques : anciennes ancres non reçues par le serveur, retours
arrière, lecture au clavier et sans JavaScript, recherche globale, références
échappées et contexte de l’univers sélectionné. Le contenu du manuel et ses
règles ne sont pas réécrits.

| Destination | Adresse | Préparation |
| --- | --- | --- |
| Accueil | `/wiki` | Titres, parties et liens des chapitres ; aucun corps complet. |
| Chapitre | `/wiki/chapitres/{chapter}` | Sommaire et corps du seul chapitre demandé. |
| Recherche | `/wiki/recherche?q=…` | Résultats avec extraits bornés et liens, sans anciens chapitres complets. |
| Références | `/wiki/references/regles`, `/wiki/references/decisions` | Document source inchangé et échappé, sections natives. |

Le manuel reste privé et accessible sans participation à un univers ; aucune
nouvelle condition d’accès. `docs/wiki.json`, `REGLES.md` et `DECISIONS.md` restent
les sources uniques. `WikiManual` prépare les seules données de la vue à partir
du fichier de référence fini, sans données de jeu ou nouvelle persistance.

Les anciens GET `?chapter=` et `?q=` redirigent. Pour `#wiki-{id}` et
`#wiki-title-{id}`, une transition côté navigateur choisit la fiche dans une liste
fermée de destinations et remplace l’entrée d’historique. Sans JavaScript, les
ancres repèrent les cartes de l’accueil et leurs liens de lecture ; les résultats
portent aussi leurs repères. Les fragments inconnus restent sur leur page, sans
redirection arbitraire. Aucun chapitre n’est masqué pour simuler une URL.

Le sommaire et la navigation commune conservent les états actifs. Les formules
et exemples se déplient au clavier sans dépendre de JavaScript ; le sélecteur
mobile soumet un GET. La recherche se trouve dans la navigation et son formulaire
est réservé à l’accueil et aux résultats pour alléger les pages de lecture.
Les logos et SVG d’identité sont conservés. Les pages gardent `noindex`, leur
protection étant inchangée ; robots et sitemap publics ne sont pas modifiés.

Validation locale : `make validate`, `make lint-static` (PHP, Twig, YAML et
conteneur), `make assets`, contrôle Twig ciblé et `git diff --check` réussis.
Huit tests PHP distincts réussissent par consolidation (Wiki et SEO privé/public),
dont trois tests Wiki / 208 assertions sur les dernières vérifications ciblées.
Ils couvrent les liens directs, les 16 chapitres, les données réellement rendues,
les anciennes requêtes, la recherche vide/sans résultat, l’échappement, la limite
de saisie, les références inconnues et l’accès sans connexion/sans participation.

Quatre scénarios navigateur réussissent par consolidation des reprises : lecture complète de chaque
chapitre, formules au clavier, intégralité des références comparée aux fichiers,
recherche et navigation serveur, fragments historiques (dont les inconnus), retour
arrière, rechargement, mode natif, thèmes clair/sombre et largeurs 390/768/1 440 px.
Le parcours transversal conserve les contrôles des autres espaces et attend la
navigation réelle avant le retour arrière. Les références restent du texte
échappé. Les audits Axe et les contrôles de débordement passent ; les captures
réelles bureau et mobile ont été examinées. Une reprise ciblée vérifie aussi
que la participation à l’univers sélectionné reste identique sur chaque page.
Aucune suite complète PHP/navigateur locale n’est revendiquée.

La [PR #84](https://github.com/c41m07/genesis-reborn/pull/84) a été fusionnée dans dev
par `dff8e11`, après le scan de secrets et les cinq jobs quality réussis sur
`fad20ad` (CI 44057167152). Localhost a été actualisé et sa modification locale
préservée. Les deux scénarios de contenu et de compatibilité ont aussi été repris
après la synchronisation des décisions :
ils réussissent, avec comparaison intégrale des références aux sources finales. Aucune migration de base existante ni livraison Alpha dans ce lot.
Les trois sources Work V4/V2/V1 et le wiki documentaire externe restent
inaccessibles ; aucune lecture ou synchronisation n’est revendiquée. Les
contrôles d’accessibilité ciblés ne valent pas audit RGAA complet. Performance
non mesurée.

### Fichiers du lot 8

- `README.md`
- `app/assets/controllers/wiki_chapters_controller.js`
- `app/assets/styles/approved-pages.css`
- `app/src/Controller/WikiController.php`
- `app/src/Service/Wiki/WikiManual.php`
- `app/templates/base.html.twig`
- `app/templates/wiki/_summary.html.twig`
- `app/templates/wiki/chapter.html.twig`
- `app/templates/wiki/index.html.twig`
- `app/templates/wiki/layout.html.twig`
- `app/templates/wiki/reference.html.twig`
- `app/templates/wiki/search.html.twig`
- `app/tests/Controller/WikiPagesTest.php`
- `app/tests/browser/ui-journey.spec.mjs`
- `app/tests/browser/wiki.spec.mjs`
- `docs/ARCHITECTURE.md`
- `docs/DECISIONS.md`
- `docs/FEUILLE_DE_ROUTE.md`
- `docs/NAVIGATION_PAGES_DEDIEES.md`
- `docs/PARCOURS_UI_UX.md`
- `docs/README.md`


## Recette transversale finale

### Périmètre et risques

Recette de complexité 4/5, depuis dev `dff8e11`, sur une branche dédiée.
Aucune nouvelle page ni règle métier : ce lot consolide les tests des pages
livrées, corrige le thème des champs de description administratifs et clôt les
mentions documentaires de CI/fusion encore provisoires.
Les décisions de navigation et l’architecture des huit lots restent applicables.

| Risque | Vérification transversale |
| --- | --- |
| Perte du contexte | Seconde colonie de fixture sélectionnée ; participation et colonie conservées sur chaque page joueur |
| Destination ou navigation active incorrecte | Réponses HTTP, titre propre, entrée principale et sous-page actives, absence de sommaire automatique inutile |
| Régression mobile ou de thème | Largeurs 320/390/1 440 px côté joueur et 390/1 440 px côté administration ; préférences clair/sombre réellement enregistrées |
| Fonctions dépendantes du JavaScript | Lecture des pages serveur sans JavaScript ; parcours clavier, rechargement et retour arrière |
| Confusion des droits | Diplomatie personnelle avec/sans guilde ; administration refusée au joueur puis accessible au compte de test autorisé |
| Effets sur les données habituelles | Créations et modifications de fixtures exclusivement dans le projet Docker jetable contrôlé par le support de test |

La matrice `app/tests/browser/navigation-final.spec.mjs` couvre 22 destinations
sans guilde, 10 avec guilde et 13 administratives (certaines adresses sont communes
aux états avec/sans guilde). Axe contrôle les contenus principaux représentatifs
dans les deux thèmes. Les captures réelles accompagnent les résultats Playwright.
Les tests propres à chaque lot restent la référence pour les actions métier,
refus d’accès détaillés, isolation des univers, versions et actualisations.

### Correction et résultats locaux

Les champs de description des événements, modèles de projets et rotations
imposaient un fond sombre dans le thème clair. Les captures ont motivé un
contrôle ciblé : le contraste calculé passait, mais le contrôle du fond adapté
au thème reproduisait le défaut. La règle commune `.admin-event-form textarea`
réutilise maintenant les tokens de fond, texte et bordure existants. La reprise
réussit dans les deux thèmes ; aucun formulaire ni traitement serveur modifié.
Les captures corrigées mobile claire et ordinateur sombre ont été examinées,
ainsi que les vues de guilde et du wiki.

Contrôles locaux réussis :

- `make validate`, `node --check app/tests/browser/navigation-final.spec.mjs`,
  `make assets` et `git diff --check`.
- Trois scénarios navigateur distincts réussis par consolidation des reprises :
  espaces sans guilde (22 destinations), guilde et diplomatie/projets (10),
  administration et refus d’accès (13). Le dernier inclut le contrôle du fond
  et du contraste des descriptions, après reproduction avant correction.
- Liens directs, titres, navigation active, colonie/participation, absence de
  débordement et thèmes vérifiés dans la matrice. Clavier, focus, rechargement,
  retour arrière et lecture sans JavaScript vérifiés sur le parcours joueur.
- Les deux erreurs de préparation de la nouvelle recette ont été corrigées :
  titre de personnalisation incluant le nom de guilde et audits Axe exécutés
  dans les sessions avec JavaScript, distincts du parcours natif.

La validation locale reste ciblée ; aucune suite complète PHP/navigateur locale
n’est revendiquée. Le relevé de livraison est tenu dans la
[PR #86](https://github.com/c41m07/genesis-reborn/pull/86) : dernière version validée
par la CI complète, intégration dev et actualisation du checkout réellement servi.
Ces étapes sont distinguées des résultats locaux ci-dessus et de la recette
manuelle du compte habituel encore à effectuer.

### Fichiers du lot de clôture

- `app/assets/styles/admin-dashboard.css`
- `app/tests/browser/navigation-final.spec.mjs`
- `README.md`
- `docs/README.md`
- `docs/NAVIGATION_PAGES_DEDIEES.md`
- `docs/FEUILLE_DE_ROUTE.md`
- `docs/REFONTE_ADMINISTRATION.md`
- `docs/RECETTE_EVOLUTIONS_INTERFACES.md`

### Limites explicites

La vérification automatique du compte habituel n’a pas pu être menée : l’outil
Edge a d’abord signalé un panneau d’extension ouvert, puis arrêté le contrôle
car il ne pouvait pas vérifier l’URL avec assez de certitude, malgré l’onglet
localhost ouvert et connecté confirmé par l’utilisateur. Aucun contournement
ni accès à la session habituelle par un autre moyen. Sa recette de confort
reste donc à effectuer manuellement ; les essais isolés ne la remplacent pas.
La liste manuelle des évolutions A à K n’est pas cochée par cette recette.

Les trois sources Work V4/V2/V1 sont encore introuvables dans le dépôt et les
sources connectées accessibles ; le wiki documentaire externe n’est pas synchronisé.
Les contrôles d’accessibilité sont ciblés, sans audit complet RGAA ni lecteur
d’écran réel. Performance non mesurée. Aucune migration de base existante,
installation de dépendance ou livraison Alpha autorisée ou réalisée par ce lot.
