# Genesis Reborn — journal de l'agent

## 2026-10-31 — audit du prototype Figma et correction du runner E2E
- Lien public figma.site inspecté avec Playwright : HTTP 200, navigation et vues principales accessibles; prototype annoncé comme 15 écrans/7 parcours, données fictives et actions simulées. Vérification mobile 390 px sans débordement horizontal mesuré. Figma Make direct reste bloqué par l’authentification/API non supportée. Détails : FIGMA_HANDOFF.md.
- Correction ciblée de compose.browser-test.yaml : MERCURE_PUBLIC_URL suit désormais BROWSER_HOST. Le toast de construction passe en Playwright isolé après avoir échoué en HTTP 500 sur l’abonnement Mercure avec un host dynamique.
- Deux scénarios Playwright ciblés réussis (responsive/navigation 1/1, construction/toast 1/1). Run complet expiré à 300 s, aucun résultat total annoncé.
- Ajout de REQUIREMENTS_MATRIX.md, UI_SCREEN_MATRIX.md, FIGMA_HANDOFF.md, UX_FLOWS.md, ASSET_MANIFEST.md et UI_AUDIT.md. Phase 12 reste en cours; avancement global 9/15 phases validées.

## 2026-10-31 — audit du prompt et reprise de phase 12
- La clôture précédente était prématurée : les corrections ciblées clavier/mobile ne réalisent pas la refonte UI/UX prévue (parcours complets, responsive et accessibilité). Phase 12 rouverte et active.
- Les 78 tests / 406 assertions, le lint, le schéma, Compose et la compilation assets restent les dernières validations PHP rapportées. Campagne Playwright isolée : 4/6 passent; notification temps réel absente et trajet colonisation encore en cours à 60 s. Runner configuré pour isoler DB/services et captures; configuration Compose valide.
- Avancement : 9/15 phases validées; phases 04 et 11 partielles; phase 12 en cours. Voir PROMPT_PHASE_CROSSCHECK.md et PHASE_12_UI_ACCESSIBILITY_AUDIT.md.

## 2026-10-31 — clôture phase 11
- Console ROLE_ADMIN avec suspension/réactivation, révocation de sessions actives, signalements de messages privés/de guilde, paramètres effectifs par univers et journal d’audit. La première attribution admin passe par app:admin:grant.
- La consultation du contenu privé est réservée aux signalements et enregistrée. Tests permissions, confidentialité, réglages et migrations vierges réussis. Audit : PHASE_11_ADMIN_AUDIT.md.
- Validation : 78 tests / 406 assertions; lint, schéma et Compose valides; installation vierge avec 14 migrations vérifiée. Phase 12 active (11/15).

## 2026-10-31 — clôture phase 10
- Classement par univers selon la règle validée : recherche + flotte (vaisseaux et défenses fixes) + bâtiments; 100 ressources de coût catalogue = 1 point. Les actifs perdus cessent de compter.
- Ajout des messages privés, du canal de guilde et des notifications associées, avec périmètre par univers/membership, CSRF, limite 10/minute et 2 000 caractères. Audit : PHASE_10_SOCIAL_STRATEGY_AUDIT.md.
- Validation : 75 tests / 384 assertions; lint, schéma et Compose valides. Phase 11 active (10/15).

## 2026-10-31 — clôture phase 09
- Audit de simulation, pertes, butin, rapports, notifications et conquête JcE consigné dans PHASE_09_COMBAT_AUDIT.md. Le test de combat vérifie désormais l’unicité des notifications de fin après résolution répétée.
- Validation : 72 tests / 365 assertions; lint, schéma et Compose valides. Avancement : 9/15, phase 10 active.

## 2026-10-31 — clôture phase 08
- Audit des distances, carburant, départs, retours et missions consigné dans PHASE_08_FLEET_MISSIONS_AUDIT.md. Les missions sont déjà couvertes par les tests d’intégration existants.
- Validation globale : 72 tests / 363 assertions; phase 09 active (8/15).

## 2026-10-31 — clôture phase 07
- Application des effets de technologie déclarés aux flottes joueurs et aux vaisseaux PNJ à la résolution; instantané enrichi des niveaux de recherche. Les défenses statiques restent inchangées.
- Validation : 72 tests / 363 assertions; lint et configuration Compose valides. Avancement : 7/15, phase 08 active.

## 2026-10-31 — clôture phase 06
- Audit des calculs de production, chronologie, capacités, dépenses et remboursements consigné dans PHASE_06_ECONOMY_AUDIT.md. Les équilibres non spécifiés restent provisoires.
- La suite actuelle reste à 71 tests / 355 assertions; phase 07 active (6/15).

## 2026-10-31 — clôture phase 05
- Ajout du cycle de vie asynchrone des univers via Outbox/Messenger, avec gestion des dates révisées et jetons idempotents. Les planètes et leurs stocks sont conservés à la fermeture.
- Tests : 71 / 355; lint et Compose valides. Suivi avancé à la phase 06 (5/15).

## 2026-10-31 — clôture phase 04
- Audit des contrôles de compte/session consigné dans PHASE_04_SECURITY_AUDIT.md. Ajout de deux tests CSRF; 69 tests / 339 assertions, lint et Compose valides.
- Suivi avancé à la phase 05 (4/15).

## 2026-10-31 — clôture phase 03
- Vérifications architecture, installation vierge, migrations et reprise consignées dans PHASE_03_AUDIT.md.
- Après arbitrage utilisateur, cible PHP 8.4/Symfony 7.4 LTS documentée dans la spécification 6, README et DECISIONS.md. Suivi avancé à la phase 04 (3/15).




## 2026-10-31 — audit phase 01 actualisé

- Revue de l’état Git, de l’architecture, des spécifications/règles et des docs actuelles; synchronisation de la traçabilité après livraison espionnage/conquête.
- Confirmation des contrôles post-phase 02 : 67 tests / 335 assertions, lint, validate et installation vierge avec 11 migrations.
- Phase 01 clôturée; phase 03 est la prochaine étape du plan.

## 2026-10-31 — espionnage (implémentation initiale)

- Ajout d’une mission sondes uniquement sur toute planète connue non possédée par l’émetteur; les sondes sont consommées à l’arrivée.
- Ajout du calcul configurable de détection et de profondeur d’information, des alertes au propriétaire et des rapports privés par auteur.
- Ajout de l’affichage de mission depuis la carte, de l’index des rapports et de la page de détail; migration 11 créée et appliquée à la base locale.
- Les règles et coefficients sont documentés dans PHASE_02_DECISIONS.md. Les profils PNJ et le réglage d’espionnage restent provisoires.
- Validation finale : 67 tests / 335 assertions; make lint (PHP, 62 YAML, 17 Twig, conteneur et schéma); make validate et make verify-install réussis avec 11 migrations. Ajout de trois tests d’intégration espionnage; correction du test de pertes PNJ pour vérifier le vaisseau par slug.

## 2026-10-31 — conquête JcE (tranche intégrée)

- Conquête uniquement contre les planètes neutres, activée dans Omega et Turbo; les planètes vierges restent colonisables et les planètes détenues par un joueur sont exclues. Une victoire avec un vaisseau colonie survivant permet la prise, la limite habituelle de colonies s’applique.
- Environ 30 % des planètes ont été réparties aléatoirement comme PNJ dans les cartes JSON et le reste reste vierge; ce tirage est figé. Les niveaux 1–5 sont inscrits dans les catalogues d’univers. Les préréglages créent bâtiments, ressources, flotte PNJ et défenses fixes au premier combat; les valeurs de composition sont provisoires et modifiables.
- À la prise, bâtiments et stocks restent en place; flotte PNJ et défenses disparaissent. Les pertes PNJ persistent après une attaque échouée. Deux tests d’intégration couvrent ces parcours.
- À ce point du journal, l’espionnage restait en attente et la phase 02 n’était pas encore close; la validation finale est consignée dans l’entrée espionnage ci-dessus.

## 2026-10-31 — butin de combat (tranche livrée)

- Ajout du pillage des stocks au-delà du plancher protégé de 10 % de la capacité de stockage propre à chaque ressource, selon le choix de Kévin.
- Après victoire des attaquants, le fret libre des flottes survivantes se répartit selon leur capacité restante, carburant réservé au retour compris dans l’espace occupé.
- Le rapport joueur n’affiche que le butin de ses propres flottes; tests d’intégration et lint réussis : 61 tests / 274 assertions.

## 2026-10-31
- Reprise du dépôt sur feat/genesis-reborn-production-completion, HEAD 80668fa. Le statut Git WSL ne signalait au départ aucun changement suivi; .idea/ non suivi a été préservé.
- Création des documents de suivi sous docs/agent/. Les fichiers préexistants sont préservés.
- Validation Compose réussie. PHPUnit initial : 51 tests, 189 assertions; après le simulateur : 57 tests, 200 assertions, réussite. Lint : PHP valide; 58 YAML; 14 Twig; conteneur Symfony et schéma Doctrine validés.
- Les anciens résultats de docs/LIVRAISON-DOMAINES-UI.md sont datés du 21/10 et ne remplacent pas le contrôle courant.
- Aucune suppression ou substitution de fonctionnalité. Le commit de suivi de la documentation est enregistré dans l'historique local.

## 2026-10-31 — simulateur de combat (tranche incomplète)

- Ajout de CombatSimulator, moteur pur déterministe sur snapshots, avec camps/roles, arrivées différées, choix de cible, tirs, formule de précision, matchups, pertes et résultats.
- Ajout de tests unitaires couvrant répétabilité, délais d'arrivée, destruction avant le tour, bornes de précision, défenses statiques et validation des statistiques.
- Le service n'est pas relié aux flottes ou à la base; aucun parcours d'attaque n'est déclaré opérationnel.
- Après ajout : make test = 57 tests / 200 assertions; make lint réussi.
