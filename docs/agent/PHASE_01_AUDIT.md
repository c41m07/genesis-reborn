# Phase 01 — audit actualisé du dépôt

> Audit historique : les nombres et statuts des sections datées décrivent leur date de collecte. L’état actuel est indexé dans docs/README.md, MASTER_PLAN.md et ALPHA_READINESS.md.

Audit initial : 31/10/2026. Réexamen du lancement Work V4 : 01/11/2026 (Europe/Paris). Branche : feat/genesis-reborn-production-completion.

## Périmètre contrôlé

- Inventaire Git et historique : commit courant 6a2d483; commits récents incluent conquête JcE (a6ce231) et espionnage (6a2d483). Les fichiers captures de validation supprimees et .idea/ existaient hors des changements applicatifs; ils ont été préservés et exclus des commits.
- Architecture réelle : Symfony 7.4, PHP 8.4, Doctrine ORM 3, Twig, Stimulus/Encore, MariaDB, RabbitMQ, Mercure et Docker Compose. Responsabilités contrôleur/service/repository, transactions métier et messages/outbox observés dans src/.
- Documentation inventoriée : huit spécifications extraites des PDF, décisions et règles, roadmap, traçabilité, livraison/UI, recherche UI, historique de l’audit initial, notes agent et résultats de validation.
- Fonctionnalités actuelles : comptes/profil, multi-univers, carte/découvertes, économie, bâtiments/recherches/files, exploration/colonisation/transport, défense/orbite, combat persistant/butin/rapports, conquête JcE PNJ et espionnage. Les fonctions non terminées figurent dans MASTER_PLAN.md et BLOCKERS.md.
- Contrôles réalisés après la phase 02 : make test (67 tests / 335 assertions), make lint (PHP, 62 YAML, 17 Twig, conteneur et schéma), make validate et make verify-install (11 migrations, 146 planètes, ré-import idempotent, reprise après redémarrage).

## Écarts documentaires corrigés

- La matrice REQUIREMENTS_MATRIX.md indiquait encore que l’espionnage et la conquête étaient à faire; elle est alignée sur l’implémentation et les tests courants.
- REGLES.md, ROADMAP.md et DECISIONS.md distinguent désormais les décisions actuelles des états historiques du 21 octobre.
- La spécification multi-univers précise l’évolution du drapeau planet_conquest : conquête JcE de planètes PNJ uniquement, jamais vol de planètes détenues par les joueurs.
- Les rapports de livraison et le contrôle croisé obsolètes ont été retirés; leurs preuves fonctionnelles sont conservées dans les audits de phase. La documentation courante est indexée dans docs/README.md.

## Limites non bloquantes de cette phase

L’audit de base est terminé, mais le jeu entier n’est pas déclaré prêt pour une mise en production. Les valeurs signalées provisoires, la fermeture asynchrone d’univers, les classements, l’administration, l’accessibilité approfondie, la charge, la CI et la restauration restent des étapes ultérieures du plan.


## Réexamen du lancement officiel Work V4 — 01/11/2026

### Dépôt et Git

- Branche active confirmée : `feat/genesis-reborn-production-completion`; aucune autre branche locale ou distante n’est listée. HEAD : `3a5ba20` (`test: check accessible names across UI routes`). La clôture d’audit initiale existe dans l’historique (`ce30754`); l’audit de conformité plus récent est `e8273b1`.
- L’arbre de travail est volontairement sale : 35 chemins suivis modifiés et 4 chemins non suivis. Les changements couvrent le runner/test UI, le template et styles, les suivis d’agent, les PDF de référence et captures; `.idea/`, deux WebP originaux et un test navigateur séquentiel sont non suivis. Aucun n’a été restauré, supprimé ni incorporé dans un commit pendant le réexamen. Détail exact au lancement : sortie de `git status --short --branch` dans la conversation du 01/11.
- Les PDF de `docs/technic/` sont marqués modifiés dans l’arbre de travail (même taille affichée par Git); leur contenu n’a pas été réécrit dans ce réexamen. Ils ont été préservés.

### Cartographie contrôlée

- Structure réelle : Symfony 7.4 / PHP 8.4; 105 fichiers sous `src/` répartis entre contrôleurs, services, entités, dépôts, sécurité, gestionnaires d’événements/messages et intégration Mercure. Le projet utilise Doctrine ORM/Migrations, Twig, Stimulus, Webpack Encore, MariaDB, RabbitMQ, Mercure et Docker Compose; lockfiles PHP/JavaScript présents.
- Inventaires : 29 routes Symfony (authentification, colonies/économie, carte, flottes/missions, combat et espionnage, social/classements/messages, notifications, profil et administration); 14 migrations versionnées; 14 fichiers sous `tests/`; 20 templates Twig; 17 fichiers d’assets applicatifs. Les listes de routes et modules ont été relevées via `debug:router`, `rg --files` et le dépôt, et rapprochées de `UI_SCREEN_MATRIX.md`.
- Documentation : 8 spécifications texte extraites de 8 PDF conservés, règles et décisions, roadmap, traçabilité, livraison, recherche UI, contrôles de mission, audits par phase, états de tests, inventaires UX et résultats de validation. Les travaux antérieurs sont consignés dans `PHASE_01_AUDIT.md`; les gaps connus sont détaillés dans `REQUIREMENTS_MATRIX.md`, `BLOCKERS.md` et les audits 04/11/12.
- État fonctionnel classé par preuves existantes : socle et phases 01–03, 05–10 déclarés validés dans les audits; phase 04 partielle (récupération de compte et préférences absentes); phase 11 partielle (réglage fin des catalogues absent); phase 12 en cours (refonte existante et tests partiels, inventaire des états/parcours/accessibilité incomplet); phases 13–15 ouvertes. Les coefficients de gameplay signalés provisoires ne sont pas des règles d’équilibrage finales.

### Référence Figma et état UX

- Le document publié `https://chili-sunset-36220944.figma.site/` répond HTTP 200; son HTML ne contient qu’un shell React et charge le bundle `assets/index-BCW2nAuC.js`. Le bundle téléchargé expose les vues et libellés (planètes, bâtiments, technologies, flottes, conquête PNJ, diplomatie, rapports) ainsi que des avis explicites de prototype et plusieurs actions simulées en mémoire.
- L’inspection visuelle par navigateur n’a pas pu être reproduite dans cet environnement : Playwright 1.64.1 est installé, mais son exécutable Chromium manque; `cua.getState()` échoue avant d’ouvrir une surface. Je ne prétends donc pas avoir inspecté le rendu visuel pendant ce réexamen. Les comptes-rendus Playwright antérieurs sont conservés comme preuves historiques dans `FIGMA_HANDOFF.md` et `PHASE_12_UI_ACCESSIBILITY_AUDIT.md`; leurs limites (UX-01/UX-15, états, détails des rapports, clavier et structure native Figma) restent ouvertes. Aucun accès natif Figma n’est établi.

### Tests et vérifications exécutés le 01/11/2026

- PHPUnit dans le conteneur applicatif : **78 tests, 406 assertions — OK**, PHP 8.4.26, PHPUnit 13.4.3. Les logs d’erreurs attendus par les cas négatifs ont été émis; le code de sortie est réussi.
- `lint:container` : réussi. `doctrine:schema:validate` : mapping correct, schéma synchronisé. `doctrine:migrations:status` : 14/14 migrations exécutées, aucune nouvelle migration; la base locale est à la dernière version `Version20261031214752`.
- `git diff --check` : code de sortie 0; avertissements Git seulement sur conversion CRLF/LF pour des fichiers suivis, sans erreur whitespace signalée.
- Docker Engine et les services Compose sont disponibles; les services applicatifs, MariaDB/test, RabbitMQ et Mercure sont indiqués sains. Le profil d’environnement local `.env.local` a été utilisé sans afficher ni modifier ses secrets.

### Conclusion de phase 01

L’audit couvre les zones du dépôt requises et identifie les limites inconnues au lieu de les confondre avec des fonctions absentes ou validées. La phase 01 est **TERMINÉE** sur le plan de l’audit; cela ne clôt aucune phase fonctionnelle incomplète et ne qualifie pas l’application pour la production. Le suivi V4 place la phase 04 comme prochaine phase incomplète après confirmation documentaire des phases 02–03, tout en préservant les travaux UI anticipés de phase 12.
