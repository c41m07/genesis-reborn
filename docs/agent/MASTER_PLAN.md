# Genesis Reborn — plan maître

## Mission et règles
Compléter le jeu existant en conservant les décisions de docs/REGLES.md, docs/DECISIONS.md et les huit spécifications de docs/specifications/. Toute suppression ou incompatibilité doit être annoncée et motivée avant l'édition. La publication est exclue par le prompt; travail local sur la branche dédiée.

## Phases et critères
| Phase | Domaine | Critère de sortie | État au 31/10/2026 |
|---|---|---|---|
| 01 | Audit | Inventaire, état Git, fonctionnalités, architecture, documentation et contrôles initiaux consignés | Terminée — inventaire Git, architecture, documentation et contrôles initiaux actualisés dans PHASE_01_AUDIT.md |
| 02 | Spécifications | Règles, ambiguïtés, critères d'acceptation et valeurs provisoires distingués | Terminée — règles et critères d’acceptation documentés; conquête JcE et espionnage intégrés et validés; coefficients et compositions PNJ identifiés comme provisoires |
| 03 | Architecture et environnement | Installation/reprise reproductibles, architecture cohérente, migrations vérifiées | Terminée — installation vierge, migrations et reprise vérifiées; cible PHP 8.4/Symfony 7.4 LTS choisie et documentée |
| 04 | Comptes et sécurité | Parcours et contrôles d'accès validés, récupération de compte, préférences et sécurité des sessions | PARTIELLE — parcours présents vérifiés; récupération de mot de passe et préférences utilisateur du prompt absentes; à reprendre avant l’audit final |
| 05 | Univers et planètes | Carte, colonies, exploration/colonisation et accès par univers cohérents | Terminée — accès daté, fermeture asynchrone par outbox avec le transport Messenger de test et conservation des planètes; détails dans PHASE_05_UNIVERSE_AUDIT.md |
| 06 | Économie | Calculs serveur, reprise chronologique, stockage et dépenses cohérents | Terminée — production paresseuse, rattrapage chronologique, plafonds, dépense concurrente et remboursement vérifiés; coefficients d’équilibrage restent provisoires (PHASE_06_ECONOMY_AUDIT.md) |
| 07 | Bâtiments et technologies | Prérequis, effets, files et annulations fonctionnels et vérifiés | Terminée — effets de technologies all_ships appliqués aux flottes joueurs et aux vaisseaux PNJ; défenses statiques inchangées; rapports figés avec les niveaux utilisés |
| 08 | Flottes et missions | Trajets, carburant, départ/arrivée/retour et missions vérifiés | Terminée — parcours de flotte, idempotence, consommation et retours vérifiés; limites des règles intergalactiques et valeurs provisoires consignées dans PHASE_08_FLEET_MISSIONS_AUDIT.md |
| 09 | Combat | Résolution reproductible, pertes, butin, rapports et notifications testés | Terminée techniquement — déterminisme, pertes, butin, rapports privés, notifications idempotentes et conquête JcE vérifiés; équilibrage provisoire conservé pour l’audit final (PHASE_09_COMBAT_AUDIT.md) |
| 10 | Social et stratégie | Diplomatie et communications, protections anti-abus | Terminée techniquement — classement, messages privés et canal de guilde livrés et testés; détails dans PHASE_10_SOCIAL_STRATEGY_AUDIT.md |
| 11 | Administration et équilibrage | Gestion des comptes, réglages de jeu, outils d’équilibrage, modération et journaux d’audit | PARTIELLE — administration/modération et réglages d’univers livrés; équilibrage fin des catalogues bâtiments, technologies et unités absent |
| 12 | UI/UX | Refonte complète des parcours, identité visuelle cohérente, responsive et accessibilité vérifiée | EN COURS — la refonte visuelle responsive préexistante est conservée; les contrôles intégrés de tous les parcours et critères restent à terminer |
| 13 | Tests/sécurité/performance | Tests critiques, concurrence et sécurité contrôlés | À faire après la phase 12 — runner isolé disponible; campagne complète, sécurité, concurrence et charge à vérifier |
| 14 | Exploitation/documentation | CI, sauvegarde/restauration et procédures vérifiées | Partielle — documentation présente; CI et restauration à valider |
| 15 | Audit final | Critères production prouvés ou limites bloquantes listées | À faire — application non déclarée prête |

## Ordre de travail
1. Terminer les contrôles de la phase 12 sur la refonte UI/UX existante et corriger les écarts observés.
2. Reprendre les critères incomplets des phases 04 (récupération de compte et préférences) et 11 (outils d’équilibrage).
3. Exécuter la phase 13 sur une base E2E isolée, sans altérer la base locale partagée.
4. Réaliser CI, sauvegarde/restauration et audit final des phases 14–15.
Ne pas créer d’écrans factices ni présenter des données provisoires comme définitives.
