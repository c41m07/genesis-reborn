# Phase 01 — audit actualisé du dépôt

Date : 31/10/2026. Branche vérifiée : feat/genesis-reborn-production-completion.

## Périmètre contrôlé

- Inventaire Git et historique : commit courant 6a2d483; commits récents incluent conquête JcE (a6ce231) et espionnage (6a2d483). Les fichiers docs/validation/*.png et .idea/ existaient hors des changements applicatifs; ils ont été préservés et exclus des commits.
- Architecture réelle : Symfony 7.4, PHP 8.4, Doctrine ORM 3, Twig, Stimulus/Encore, MariaDB, RabbitMQ, Mercure et Docker Compose. Responsabilités contrôleur/service/repository, transactions métier et messages/outbox observés dans src/.
- Documentation inventoriée : huit spécifications extraites des PDF, décisions et règles, roadmap, traçabilité, livraison/UI, recherche UI, historique de l’audit initial, notes agent et résultats de validation.
- Fonctionnalités actuelles : comptes/profil, multi-univers, carte/découvertes, économie, bâtiments/recherches/files, exploration/colonisation/transport, défense/orbite, combat persistant/butin/rapports, conquête JcE PNJ et espionnage. Les fonctions non terminées figurent dans MASTER_PLAN.md et BLOCKERS.md.
- Contrôles réalisés après la phase 02 : make test (67 tests / 335 assertions), make lint (PHP, 62 YAML, 17 Twig, conteneur et schéma), make validate et make verify-install (11 migrations, 146 planètes, ré-import idempotent, reprise après redémarrage).

## Écarts documentaires corrigés

- La matrice TRACEABILITE.md indiquait encore que l’espionnage et la conquête étaient à faire; elle est alignée sur l’implémentation et les tests courants.
- REGLES.md, ROADMAP.md et DECISIONS.md distinguent désormais les décisions actuelles des états historiques du 21 octobre.
- La spécification multi-univers précise l’évolution du drapeau planet_conquest : conquête JcE de planètes PNJ uniquement, jamais vol de planètes détenues par les joueurs.
- Les rapports de livraison du 21 octobre restent conservés comme comptes-rendus datés, avec renvoi explicite vers la traçabilité actualisée.

## Limites non bloquantes de cette phase

L’audit de base est terminé, mais le jeu entier n’est pas déclaré prêt pour une mise en production. Les valeurs signalées provisoires, la fermeture asynchrone d’univers, les classements, l’administration, l’accessibilité approfondie, la charge, la CI et la restauration restent des étapes ultérieures du plan.
