# Navigation par espaces et pages dédiées

## État du chantier — 16 novembre 2026

Lot 1 réalisé localement sur `feature/navigation-guildes-diplomatie`, depuis
`origin/dev` `c6b72d1`. CI, publication, fusion et déploiement non effectués.
Les modifications juridiques de la copie principale sont préservées.

Les trois documents Work V4 (prompt maître), V2 (brief Figma) et V1 (protocole
adaptatif) n’ont pas été trouvés dans le dépôt ni les sources connectées
accessibles. Aucune lecture intégrale ni validation de leurs phases n’est revendiquée.

## Découpage et complexité

1. Guildes / Diplomatie : 4/5. Routes, retours après formulaire, appartenance,
   données privées, compatibilité des adresses et liens entrants.
2. Flottes : à examiner après intégration du lot 1 ; préserver les périmètres,
   actualisations, demandes d’orbite et préparation continue des missions.
3. Inventaire : à examiner après le lot 2.
4. Mon compte / vacances : à examiner après le lot 3 ; séparer compte et empire.
5. Projets collectifs : à examiner après le lot 4 ; conserver contributions et fiches.
6. Événements : à examiner après le lot 5.
7. Administration : à examiner après le lot 6, en poursuivant l’organisation existante.
8. Wiki : à examiner après le lot 7, avec transition explicite des fragments historiques.

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
pas un audit de conformité RGAA complet. Les contrôles CI complets restent
obligatoires sur la dernière version de la PR avant fusion. La lecture des
protections de dev retourne HTTP 403 avec la connexion GitHub disponible ; leur
activation doit être confirmée avec un accès autorisé à l’administration du dépôt.

Publication et PR à autoriser après ce bilan ; aucune fusion ni aucun déploiement.
Le lot 2 Flottes reste en attente de l’intégration validée du lot 1.


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
