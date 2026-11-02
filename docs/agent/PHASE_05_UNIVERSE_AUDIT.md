# Phase 05 — univers et planètes

État : TERMINÉE le 31/10/2026.

## Résultat

- L’accès reste contrôlé par le statut et les bornes startsAt/endsAt. Sélection, contexte courant et contrôles des planètes utilisent la même règle; le membre d’un univers fermé est renvoyé vers la sélection.
- À la création d’un univers par UniverseImporter, starts_at/ends_at sont lus si présents. Un univers marqué temporary doit fournir ends_at; l’import crée le message de fermeture dans la même transaction que l’univers.
- UniverseLifecycleService::setEndDate met à jour la date et remplace l’événement OutboxMessage dans une transaction. Le relais existant programme UniverseClosureDue sur Messenger; le handler vérifie l’univers, la date d’échéance, le jeton courant et le verrou avant de passer status à closed.
- Les jetons obsolètes sont ignorés quand l’échéance change. Les messages répétés sont idempotents. Les dates du jeu utilisent la précision à la seconde enregistrée par MariaDB.
- Aucune donnée de jeu n’est réinitialisée ou supprimée à la fermeture : colonies, propriétaires et stocks sont conservés.

## Validation

- GamePersistenceTest vérifie l’outbox, l’acheminement asynchrone, l’ancienne échéance ignorée, la fermeture idempotente et la conservation d’une planète et de ses ressources.
- GameplayAccessTest vérifie la redirection HTTP d’un membre vers /univers après fermeture et la présence persistante de la planète.
- make test réussi : 71 tests / 355 assertions.
- make lint et make validate réussis; les 62 fichiers YAML, le conteneur Symfony, le schéma Doctrine et Compose sont valides.
- Pas de migration de schéma : le modèle OutboxMessage existant porte déjà kind, targetId, token et dueAt.

## Limites

Les univers Omega et Turbo actuels sont permanents. Aucune interface admin ne modifie encore endsAt; l’importeur prend en charge les futurs univers temporaires et UniverseLifecycleService fournit le point d’entrée à utiliser par la future administration (phase 11). La livraison RabbitMQ réelle n’a pas été exercée par cette suite, qui utilise son transport Messenger en mémoire.

## Réaudit Work V4 — 31/10/2026

- Comparaison du code reprise : UniverseContext applique l’accessibilité aux memberships courants; UniverseImporter valide les échéances temporaires, programme la fermeture dans l’outbox et conserve les importations idempotentes; UniverseLifecycleService remplace les échéances, ignore les jetons obsolètes et ferme sous verrou en conservant les données.
- Rerun ciblé : GamePersistenceTest 41 tests / 187 assertions, GameplayAccessTest 9 tests / 50 assertions. Résultat combiné : 50 / 237, succès. La suite complète courante passe à 84 tests / 448 assertions.
- Les fermetures sont couvertes avec le transport Messenger de test; la livraison RabbitMQ réelle n’est toujours pas exercée par la suite d’intégration. Aucune migration ou modification métier n’a été nécessaire pour la phase 05.
