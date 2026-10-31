# Genesis Reborn — plan maître

## Mission et règles
Compléter le jeu existant en conservant les décisions de docs/REGLES.md, docs/DECISIONS.md et les huit spécifications de docs/specifications/. Toute suppression ou incompatibilité doit être annoncée et motivée avant l'édition. La publication est exclue par le prompt; travail local sur la branche dédiée.

## Phases et critères
| Phase | Domaine | Critère de sortie | État au 31/10/2026 |
|---|---|---|---|
| 01 | Audit | Inventaire, état Git, fonctionnalités, architecture, documentation et contrôles initiaux consignés | Terminée — inventaire Git, architecture, documentation et contrôles initiaux actualisés dans PHASE_01_AUDIT.md |
| 02 | Spécifications | Règles, ambiguïtés, critères d'acceptation et valeurs provisoires distingués | Terminée — règles et critères d’acceptation documentés; conquête JcE et espionnage intégrés et validés; coefficients et compositions PNJ identifiés comme provisoires |
| 03 | Architecture et environnement | Installation/reprise reproductibles, architecture cohérente, migrations vérifiées | Terminée — installation vierge, migrations et reprise vérifiées; cible PHP 8.4/Symfony 7.4 LTS choisie et documentée |
| 04 | Comptes et sécurité | Parcours et contrôles d'accès validés, sécurité des comptes et sessions | Partielle — inscription, profil et accès de base réalisés; audit complet restant |
| 05 | Univers et planètes | Carte, colonies, exploration/colonisation et accès par univers cohérents | Partielle — base jouable; carte à trois états PNJ/vierge/joueur; fermeture asynchrone à faire |
| 06 | Économie | Calculs serveur, reprise chronologique, stockage et dépenses cohérents | Partielle — économie corrigée; équilibrage provisoire à valider |
| 07 | Bâtiments et technologies | Prérequis, effets, files et annulations fonctionnels et vérifiés | Partielle — fonctionnalités principales; effets de combat liés restants |
| 08 | Flottes et missions | Trajets, carburant, départ/arrivée/retour et missions vérifiés | Partielle — exploration, espionnage, colonisation, transport, attaque, défense temporisée et orbite intégrés; audit fonctionnel restant |
| 09 | Combat | Résolution reproductible, pertes, butin, rapports et notifications testés | Partielle — événements persistés, résolution, pertes, journal, rapports, flottes défenseuses, orbite et butin intégrés; conquête JcE avec flotte PNJ, défenses fixes, pertes persistantes et rapports intégrés; compositions provisoires |
| 10 | Social et stratégie | Diplomatie et communications, protections anti-abus | Partielle — guildes/alliances réalisées; classements et communications restant |
| 11 | Administration | Paramètres et modération sécurisés, journaux d'audit | À faire |
| 12 | UI/UX | Parcours complets, responsive et audit accessibilité satisfaisant | Partielle — refonte existante; audit RGAA/WCAG complet à faire |
| 13 | Tests/sécurité/performance | Tests critiques, concurrence et sécurité contrôlés | Partielle — suite PHP passante; couverture et charge restantes |
| 14 | Exploitation/documentation | CI, sauvegarde/restauration et procédures vérifiées | Partielle — documentation présente; CI et restauration à valider |
| 15 | Audit final | Critères production prouvés ou limites bloquantes listées | À faire — application non déclarée prête |

## Ordre de travail
1. Phase 03 : vérifier l’architecture et la reproductibilité des installations/migrations à partir de l’audit de phase 01.
2. Implémenter et vérifier la fermeture asynchrone des univers temporaires.
3. Poursuivre par les classements/statistiques et les outils d’administration.
4. Compléter CI, accessibilité, sécurité, charge et sauvegarde/restauration.
5. Terminer par l’audit de production; garder les valeurs PNJ/espionnage explicitement provisoires jusque-là.
Ne pas créer d'écrans factices ni présenter des données provisoires comme définitives.
