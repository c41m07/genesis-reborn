# Phase 11 — administration, modération et audit

État au 31/10/2026 : partielle au regard du prompt initial. Console sécurisée, rôle administrateur, suspension des comptes, signalements, réglages par univers et journal d’audit sont implémentés; l’équilibrage fin des catalogues de bâtiments, technologies et unités reste à livrer. La phase 12 se poursuit en parallèle comme reprise prioritaire.

## Accès et comptes
- Console sous /admin protégée par ROLE_ADMIN et formulaires CSRF.
- Premier administrateur accordé explicitement en ligne de commande : app:admin:grant adresse@example.test. Cette action est elle-même inscrite au journal.
- Les administrateurs ne peuvent pas suspendre leur compte ni un autre administrateur. Les suspensions bloquent l’authentification et révoquent la session active.

## Modération et vie privée
- Un participant peut signaler un message privé; seuls les participants peuvent soumettre le signalement. Un membre peut signaler un message du canal de sa guilde.
- La console ne révèle le contenu privé qu’après signalement. Les consultations de signalements ouverts sont journalisées.
- L’administrateur peut classer sans suite ou masquer le message; le masquage remplace son contenu et conserve le signalement et la décision.

## Univers et règles de jeu
- La console permet de planifier les dates d’ouverture et de fermeture des univers. Un univers fermé ne peut pas être réouvert.
- Réglages par univers persistés en base et appliqués immédiatement aux services : multiplicateurs de durées, production et trajets (0,01–10); maximum de planètes (1–50); taille de flotte (1–10 000); activation des guildes et de la conquête JcE.
- Les catalogues de référence restent les valeurs initiales. L’administrateur peut régler, par univers, les coûts, durées, niveaux maximum, production/stockage, caractéristiques et consommations des unités; les valeurs sont bornées et validées, avec possibilité de retour aux références.
- Les modifications s’appliquent immédiatement de façon cohérente : la production acquise est matérialisée avant le changement et les taux recalculés sous verrou; les éléments déjà en file gardent leur coût et leur durée enregistrés, tandis que les nouveaux éléments utilisent les valeurs modifiées.
- Le contrôleur et le service limitent l’accès aux administrateurs; les formulaires exigent un jeton CSRF; sauvegardes et réinitialisations sont inscrites au journal d’audit.

## Journal d’audit
Les actions d’administration stockent l’acteur, l’action, le type et l’identifiant de cible, l’horodatage et les détails JSON : accès aux signalements, décisions de modération, suspension/réactivation, calendrier et paramètres d’univers, attribution initiale du rôle. Le contenu privé antérieur au masquage n’est pas copié dans le journal.

## Validation — 01/11/2026
- PHPUnit complet : 93 tests / 477 assertions, réussi. CatalogBalanceTest : 7 tests / 21 assertions; CatalogBalanceControllerTest : 2 / 8.
- Lint Twig : 25 fichiers; YAML : 63 fichiers; conteneur Symfony : réussi. doctrine:schema:validate a terminé avec le code retour 0.
- make lint et make validate ne sont pas disponibles dans le shell Windows du runner (make absent); les contrôles correspondants ont été exécutés directement dans le conteneur avec docker-compose --env-file .env.local.
- Les migrations 18 à 20 et l’installation vierge ne sont pas des changements de cette phase; aucune migration nouvelle n’est nécessaire pour les overrides stockés dans les réglages JSON d’univers.

## Limites
- L’attribution initiale du rôle administrateur se fait par commande console; aucune promotion n’est disponible depuis l’interface web.
- Les listes de l’interface d’administration ne sont pas encore paginées.
- Les listes d’administration ne sont pas encore paginées. Cette phase ne constitue pas un audit RGAA/WCAG, de charge ni une déclaration de préparation à la production.
