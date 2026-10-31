# Genesis Reborn — état des tests

Dernière exécution : 31/10/2026, dépôt /home/caim/Projets/Genesis_Reborn.

| Commande | Résultat |
|---|---|
| make validate | Réussi — configuration Docker Compose valide. |
| make test | Réussi — 78 tests / 406 assertions, exécuté pendant la reprise le 31/10/2026. |
| make lint | Réussi pendant la reprise — PHP; 62 YAML; 20 Twig; conteneur Symfony et schéma Doctrine valides. |
| make verify-install | Réussi — installation isolée; 14 migrations appliquées, import de 146 planètes, ré-import idempotent, services prêts après redémarrage; environnement temporaire nettoyé. |
| Playwright campagne complète | Appel agrégé expiré à 300 s sans rapport restitué. Les six scénarios du fichier ont ensuite tous réussi en exécution individuelle; aucun score agrégé n’est revendiqué. |
| Playwright ciblé responsive/navigation | Réussi — 1/1, inscription/connexion, navigation clavier, menus, stocks visibles, largeur 390 px et rapport vide. Runner isolé, MariaDB tmpfs. |
| Playwright ciblé construction/toast | Réussi — 1/1 après ajout de MERCURE_PUBLIC_URL=http://$BROWSER_HOST/.well-known/mercure à l’overlay de test. La cause était l’URL hub publique statique qui empêchait le cookie d’abonnement sur le sous-domaine E2E dynamique. |
| Playwright colonisation complète | Réussi — scénario chantier, estimation, colonisation, transport et exploration 1/1 en 3,1 min. Base et services isolés, détruits en fin de run. |
| Playwright responsive multi-breakpoints | Réussi — compte neuf et shell colonie à 1440/768/390 px, sans débordement horizontal; menu mobile accessible au clavier. 1/1. |
| Playwright routes UI responsive | Réussi — plus de dix routes authentifiées; statut HTTP < 400, main et titre h1 visibles, aucune erreur JavaScript et aucune largeur de document supérieure au viewport à 1440/768/390. 1/1. |

Les tests PHP utilisent la base database-test isolée (profil test, base en tmpfs). Les parcours de connexion, CSRF, profil et isolation des comptes sont maintenant testés. Les audits sécurité élargi, concurrence, accessibilité et performance restent à vérifier. Les résultats datés du 21/10 dans docs/LIVRAISON-DOMAINES-UI.md sont historiques et ne remplacent pas la validation des changements actuels.


## Mise à jour — reprise Figma et tests ciblés, 31/10/2026

- Playwright a ouvert le site public Figma fourni par Kévin : HTTP 200. Figma Make URL directe : HTTP 403/login dans Chromium isolé. Le preview est documenté dans FIGMA_HANDOFF.md.
- Scénario responsive/navigation : 1/1 réussi. Scénario construction et notification : d’abord 500 sur /notifications/abonnement; après correction de MERCURE_PUBLIC_URL dans compose.browser-test.yaml, 1/1 réussi.
- Le lancement agrégé a dépassé la limite de restitution de l’outil (300 s). Les six tests sont validés individuellement; deux tests responsive dédiés confirment le shell et plus de dix routes à 1440/768/390. Aucun résultat agrégé n’est déduit.

## Mise à jour — espionnage, 31/10/2026

- Migration 11 créée et appliquée sur la base locale.
- make lint relancé après les changements : PHP valide; 62 YAML; 17 Twig; conteneur Symfony et schéma Doctrine valides.
- Les tests PHPUnit incluent maintenant trois parcours d’espionnage : réussite et confidentialité, détection et alerte, initialisation d’une garnison PNJ; le test de pertes PNJ vérifie désormais les unités par slug.


Résultats post-espionnage : `make test` réussi — 67 tests / 335 assertions; `make lint` réussi — 62 YAML, 17 Twig, PHP, conteneur et schéma; `make validate` réussi; `make verify-install` réussi — 11 migrations, 146 planètes, ré-import idempotent et redémarrage sain.


## Mise à jour — comptes et sécurité, 31/10/2026

- make test : 69 tests / 339 assertions. Couverture ajoutée pour le rejet d’un jeton CSRF de connexion invalide et l’absence de création si le formulaire d’inscription n’a pas de jeton CSRF.
- make lint et make validate réussis après les changements : PHP, 62 YAML, 17 Twig, conteneur Symfony, schéma Doctrine et configuration Compose.


## Mise à jour — phase 09, 31/10/2026

- make test réussi : 72 tests / 365 assertions. Un test de combat confirme qu’un participant et le propriétaire reçoivent une seule notification de fin malgré deux appels de résolution.
- make lint et make validate réussis; la résolution, les rapports, le butin, les pertes JcE PNJ et les notifications restent couverts.
- Les statistiques de combat et compositions PNJ explicitement provisoires restent à équilibrer avant production.

## Mise à jour — phase 08, 31/10/2026

- Audit des trajets et missions terminé à partir des tests existants; aucune modification métier requise. La suite globale est à 72 tests / 363 assertions.
- Limites explicites : règles intergalactiques non définies; coefficients carburant, réservoirs et durées de trajet provisoires; parcours navigateur espionnage à relancer.

## Mise à jour — phase 07, 31/10/2026

- make test réussi : 72 tests / 363 assertions. CombatEventTest vérifie les bonus Tir de précision à deux niveaux différents pour les flottes du défenseur et de l’attaquant; la tourelle conserve sa précision de base.
- make lint réussi : PHP, 62 YAML, 17 Twig, conteneur Symfony et schéma Doctrine valides; make validate réussi.
- Les niveaux et statistiques effectifs sont inclus dans l’instantané du rapport. Le catalogue PNJ ne définit actuellement aucun niveau de Tir de précision; ses niveaux de recherche déclarés sont transmis au calculateur.

## Mise à jour — phase 05, 31/10/2026

- make test réussi : 71 tests / 355 assertions. Le test d’intégration vérifie l’outbox avec le transport Messenger de test, l’invalidation des anciens jetons, la fermeture idempotente et la conservation du propriétaire et des ressources de planète. Le test HTTP vérifie la redirection vers la sélection des univers fermés.
- make lint et make validate réussis après l’ajout du routage et du handler de fermeture. Aucune migration de schéma n’est requise; OutboxMessage existait déjà.
