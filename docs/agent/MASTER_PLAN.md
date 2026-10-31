# Genesis Reborn — plan maître

## Mission et règles
Compléter le jeu existant en conservant les décisions de docs/REGLES.md, docs/DECISIONS.md et les huit spécifications de docs/specifications/. Toute suppression ou incompatibilité doit être annoncée et motivée avant l'édition. La publication est exclue par le prompt; travail local sur la branche dédiée.

## Phases et critères
| Phase | Domaine | Critère de sortie | État au 31/10/2026 |
|---|---|---|---|
| 01 | Audit | Inventaire, état Git, fonctionnalités, architecture, documentation et contrôles initiaux consignés | Terminée — inventaire Git, architecture, documentation et contrôles initiaux actualisés dans PHASE_01_AUDIT.md |
| 02 | Spécifications | Règles, ambiguïtés, critères d'acceptation et valeurs provisoires distingués | Terminée — règles et critères d’acceptation documentés; conquête JcE et espionnage intégrés et validés; coefficients et compositions PNJ identifiés comme provisoires |
| 03 | Architecture et environnement | Installation/reprise reproductibles, architecture cohérente, migrations vérifiées | Terminée — installation vierge, migrations et reprise vérifiées; cible PHP 8.4/Symfony 7.4 LTS choisie et documentée |
| 04 | Comptes et sécurité | Parcours et contrôles d'accès validés, sécurité des comptes et sessions | Terminée — inscription, accès, CSRF, limitation des tentatives, profil sensible et session vérifiés; détails dans PHASE_04_SECURITY_AUDIT.md |
| 05 | Univers et planètes | Carte, colonies, exploration/colonisation et accès par univers cohérents | Terminée — accès daté, fermeture asynchrone par outbox avec le transport Messenger de test et conservation des planètes; détails dans PHASE_05_UNIVERSE_AUDIT.md |
| 06 | Économie | Calculs serveur, reprise chronologique, stockage et dépenses cohérents | Terminée — production paresseuse, rattrapage chronologique, plafonds, dépense concurrente et remboursement vérifiés; coefficients d’équilibrage restent provisoires (PHASE_06_ECONOMY_AUDIT.md) |
| 07 | Bâtiments et technologies | Prérequis, effets, files et annulations fonctionnels et vérifiés | Terminée — effets de technologies all_ships appliqués aux flottes joueurs et aux vaisseaux PNJ; défenses statiques inchangées; rapports figés avec les niveaux utilisés |
| 08 | Flottes et missions | Trajets, carburant, départ/arrivée/retour et missions vérifiés | Partielle — exploration, espionnage, colonisation, transport, attaque, défense temporisée et orbite intégrés; audit fonctionnel restant |
| 09 | Combat | Résolution reproductible, pertes, butin, rapports et notifications testés | Partielle — événements persistés, résolution, pertes, journal, rapports, flottes défenseuses, orbite et butin intégrés; conquête JcE avec flotte PNJ, défenses fixes, pertes persistantes et rapports intégrés; compositions provisoires |
| 10 | Social et stratégie | Diplomatie et communications, protections anti-abus | Partielle — guildes/alliances réalisées; classements et communications restant |
| 11 | Administration | Paramètres et modération sécurisés, journaux d'audit | À faire |
| 12 | UI/UX | Parcours complets, responsive et audit accessibilité satisfaisant | Partielle — refonte existante; audit RGAA/WCAG complet à faire |
| 13 | Tests/sécurité/performance | Tests critiques, concurrence et sécurité contrôlés | Partielle — suite PHP passante; couverture et charge restantes |
| 14 | Exploitation/documentation | CI, sauvegarde/restauration et procédures vérifiées | Partielle — documentation présente; CI et restauration à valider |
| 15 | Audit final | Critères production prouvés ou limites bloquantes listées | À faire — application non déclarée prête |

## Ordre de travail
1. Phase 08 : auditer les parcours des flottes et missions déjà livrés.
2. Phase 09 : compléter l’audit des missions et du combat autour des parcours déjà livrés.
3. Traiter classements/statistiques et administration; la fermeture asynchrone des univers est livrée en phase 05.
4. Compléter CI, accessibilité, sécurité, charge et sauvegarde/restauration avant l’audit de production.
Ne pas créer d'écrans factices ni présenter des données provisoires comme définitives.
