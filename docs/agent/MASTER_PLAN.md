# Genesis Reborn — plan maître

## Mission et règles
Compléter le jeu existant en conservant les décisions de docs/REGLES.md, docs/DECISIONS.md et les huit spécifications de docs/specifications/. Toute suppression ou incompatibilité doit être annoncée et motivée avant l'édition. La publication est exclue par le prompt; travail local sur la branche dédiée.

## Phases et critères
| Phase | Domaine | Critère de sortie | État au 31/10/2026 |
|---|---|---|---|
| 01 | Audit | Inventaire, état Git, fonctionnalités, architecture, documentation et contrôles initiaux consignés | Partielle — audit antérieur conservé; état courant revalidé, revue complète à reconfirmer |
| 02 | Spécifications | Règles, ambiguïtés, critères d'acceptation et valeurs provisoires distingués | Partielle — règles de simulation documentées; renforts, orbite, butin et conquête à préciser |
| 03 | Architecture et environnement | Installation/reprise reproductibles, architecture cohérente, migrations vérifiées | Partielle — stack en place; contrôles d'installation à refaire dans l'environnement courant |
| 04 | Comptes et sécurité | Parcours et contrôles d'accès validés, sécurité des comptes et sessions | Partielle — inscription, profil et accès de base réalisés; audit complet restant |
| 05 | Univers et planètes | Carte, colonies, exploration/colonisation et accès par univers cohérents | Partielle — base jouable; fermeture asynchrone et conquête restantes |
| 06 | Économie | Calculs serveur, reprise chronologique, stockage et dépenses cohérents | Partielle — économie corrigée; équilibrage provisoire à valider |
| 07 | Bâtiments et technologies | Prérequis, effets, files et annulations fonctionnels et vérifiés | Partielle — fonctionnalités principales; effets de combat liés restants |
| 08 | Flottes et missions | Trajets, carburant, départ/arrivée/retour et missions vérifiés | Partielle — exploration, colonisation, transport et attaque intégrés; escorte, défense autonome, orbite, espionnage et renforts restent à faire |
| 09 | Combat | Résolution reproductible, pertes, butin, rapports et notifications testés | Partielle — événements persistés, résolution, pertes, journal et rapports à accès contrôlé; butin, orbite et conquête à faire |
| 10 | Social et stratégie | Diplomatie et communications, protections anti-abus | Partielle — guildes/alliances réalisées; classements et communications restant |
| 11 | Administration | Paramètres et modération sécurisés, journaux d'audit | À faire |
| 12 | UI/UX | Parcours complets, responsive et audit accessibilité satisfaisant | Partielle — refonte existante; audit RGAA/WCAG complet à faire |
| 13 | Tests/sécurité/performance | Tests critiques, concurrence et sécurité contrôlés | Partielle — suite PHP passante; couverture et charge restantes |
| 14 | Exploitation/documentation | CI, sauvegarde/restauration et procédures vérifiées | Partielle — documentation présente; CI et restauration à valider |
| 15 | Audit final | Critères production prouvés ou limites bloquantes listées | À faire — application non déclarée prête |

## Ordre de travail
1. Consolider les règles des phases 08/09 à partir des spécifications 1, 2 et 3.
2. Vérifier les états et invariants de flotte actuels; consolider les tests du combat livré.
3. Renforcer les rapports de combat à partir des retours; leur accès et la confidentialité de l’identité sont en place.
4. Définir et implémenter renforts, défense autonome et orbite selon les règles explicites.
5. Terminer butin, conquête et variantes d’univers.
6. Traiter variantes d'univers, classements, administration, accessibilité, CI, sauvegarde et audit final.
Ne pas créer d'écrans factices ni présenter des données provisoires comme définitives.
