# Traçabilité et reprise

État au 21 octobre 2026, après finalisation des domaines existants et refonte UI. [Bilan actuel](LIVRAISON-DOMAINES-UI.md). L’audit initial reste un historique antérieur aux corrections. Les valeurs d’équilibrage explicitement provisoires restent à valider ; les six phases du jeu ne sont pas toutes achevées.

| Source (PDF, page physique) | Règle / fonctionnalité | Composants | Tests / vérification | État actuel |
|---|---|---|---|---|
| 0 p.3–4 ; 6 p.2–6,11–13 | Symfony, Doctrine, Twig, Docker, authentification session | Compose, SecurityController, RegistrationType, Dockerfile | AuthenticationTest ; navigateur inscription/connexion ; installation vierge | Réalisé et vérifié |
| Prompt utilisateur, profil | Profil avec confirmation du mot de passe et reconnexion | ProfileData, ProfileType, ProfileService, ProfileController | GameplayAccessTest::testProfileRequiresCurrentPasswordThenLogsOutAfterSave ; GamePersistenceTest::testProfileUpdateHashesPasswordAndPreservesMembership | Réalisé |
| 0 p.5 ; 5 p.2–8 | Univers indépendants, cartes JSON, import idempotent | UniverseImporter, UniverseService, UniverseContext | GamePersistenceTest::testImportIsIdempotentAndPreservesOwnership ; tests HTTP croisés et inscription navigateur | Réalisé pour les univers accessibles |
| 5 p.3–4,9–10 | Dates d’accès et fermeture logique | Universe::isAccessible, UniverseContext | GameRulesTest::testClosedAndFutureUniverses | Accès réalisé ; fermeture asynchrone restante |
| 1 p.2–3 ; 5 p.9 | Production paresseuse, capacités, chronologie des changements de taux | ResourceService, QueueService, resource_controller | testDelayedMineCompletionAndSpendingUseActualEventChronology ; testCatchupRespectsStorageCapBeforeStorageUpgrade ; testPowerPlantActuallyIncreasesEnergyProduction | Défaut A1 corrigé ; équilibrage provisoire |
| 1 p.3–4,14–15 | Bâtiments uniques, niveaux, coûts, prérequis par palier | QueueService, GameCatalog, RequirementChecker, OperationCalculator | Tests dépendances/annulation/doublons ; coûts et durées ; construction navigateur réelle | Réalisé |
| 1 p.5,11,15–16 ; 7 p.1–7 | Recherche par planète, technologie par inscription, exclusivité multi-planètes | PlayerTechnology, QueueService | testResearchSameTechnologyCannotRunOnTwoPlanets ; testDifferentResearchQueuesCanCompleteOnDifferentPlanets | Réalisé ; bonus de combat à brancher au futur resolver |
| 1 p.4–5,12–14,23–24 | Vaisseaux sans niveaux, chantier, coûts linéaires et quantités | FleetShip, QueueService, OperationCalculator | testShipyardAndDefensesAreDeliveredExactlyOnce ; navigateur production de 3 vaisseaux | Réalisé pour production ; combats restants |
| 1 p.7–8 | Défenses via forteresse, inventaire | PlanetDefense, QueueService, ColonyView | testShipyardAndDefensesAreDeliveredExactlyOnce ; schéma et templates | Production et inventaire réalisés ; usage en combat restant |
| 7 p.1–4 | Trois files, ordre, dépendances et annulation récursive | QueueItem, QueueService, QueueTiming | GameRulesTest ; GamePersistenceTest ; GameplayAccessTest (durée totale) ; navigateur file intégrée, +x et annulation | Réalisé |
| 7 p.4–5 | Boost cumulable sur temps restant, redispatch | QueueService::boost, QueueTiming | testBoostOnlyRemainingAndCumulativePdf7Page5 ; testBoostRedispatchesOnlyHeadAndIgnoresOldMessage | Application persistée interne réalisée ; acquisition/prix non spécifiés, aucune offre joueur inventée |
| 7 p.5–7 | Doublons, jetons périmés, reprise, transactions | ActionReceipt, OutboxRelay, handlers, GameManager | testCompletionIsIdempotentAndRejectsStaleOrEarlyMessages ; rattrapage ; concurrence réelle à deux processus ; testMercureFailureDoesNotBlockGameplayOutbox | Réalisé dans les scénarios testés |
| 3 p.2–9 | Distance segmentée, vitesse minimale, symétrie et temps | MovementCalculator | MovementCalculatorTest, exemple chiffré PDF et cas limites | Réalisé ; inter-galaxies explicitement non défini |
| 2 p.7–10 ; 3 p.4–9 ; décisions utilisateur | Consommation proportionnelle au ratio, réservoirs distincts, réserve retour | MovementCalculator, FleetService, fleet_preview_controller | Tests politiques, soutes, réserves, transport, preview serveur ; navigateur | Réalisé, capacité des réservoirs provisoire |
| 0 p.7 ; 1 p.3,5 | Exploration de système, colonisation, transport et retour | FleetService, FleetController, ColonyView | Tests MariaDB des missions, doublons et réserves ; parcours navigateur complet, sélecteur multi-colonies et vol actif | Réalisé pour ces trois missions |
| 2 p.2 ; 5 p.2–3 | Guildes et alliances réciproques par univers | DiplomacyService, RelationResolver, DiplomacyController | Tests alliances, consentement, hostilités, droits, univers, transfert/dissolution ; navigateur deux comptes | Réalisé ; recrutement ouvert provisoire |
| 1 p.6–8 ; 2 p.3–6 | Combat multi-camps, renforts, butin et rapports | À créer | À écrire | À réaliser, hors des domaines terminés dans cette livraison |
| 6 p.7–10 ; 0 p.8 | Notifications privées, découplage et mises à jour | MercurePublisher, NotificationService, OutboxRelay, events/colony_live | Confidentialité JWT ; panne de publication isolée ; vraie construction avec notification Chromium | Réalisé |
| 0 p.9 ; 5 p.2–4 | Conquête, classements et variantes avancées | À étendre | À écrire | À réaliser |
| 4 p.2–20 | Contrôleurs, services métier, manager et repositories | src/, DTO, GameShell, GameUiExtension | lint PHP/Twig/YAML/conteneur/schéma ; tests HTTP et services | En place, repository générique proportionné au périmètre |
| Prompt utilisateur ; 0 p.9 | Refonte UI française, responsive, clavier et parcours réels | Twig, Stimulus, CSS, public/art | Chromium desktop/390px, onglets clavier, menu mobile, opérations réelles ; références dans UI-RECHERCHE | Réalisé ; aucun audit RGAA exhaustif revendiqué |

## Prochaine étape du jeu complet

1. Spécifier les valeurs d’équilibrage encore provisoires et la source/prix des boosts, sans bloquer les parcours actuels.
2. Missions d’orbite/défense/espionnage et résolution persistée des combats : camps, graine, bonus technologiques, renforts, butin, rapports et confidentialité.
3. Fermeture asynchrone des univers temporaires, conquête, classements/statistiques, CI, tests de charge et audit d’accessibilité complet.

Historique : suppression de l’ancien dossier, conteneurs et deux images applicatives autorisée par l’utilisateur ; volumes conservés, PDF copiés vérifiés SHA-256. MariaDB nouvelle application publiée uniquement sur 127.0.0.1:3309.
