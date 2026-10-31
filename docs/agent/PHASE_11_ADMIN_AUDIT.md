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
- Les catalogues de référence restent des valeurs initiales; les overrides sont validés, limités et enregistrés dans le journal.

## Journal d’audit
Les actions d’administration stockent l’acteur, l’action, le type et l’identifiant de cible, l’horodatage et les détails JSON : accès aux signalements, décisions de modération, suspension/réactivation, calendrier et paramètres d’univers, attribution initiale du rôle. Le contenu privé antérieur au masquage n’est pas copié dans le journal.

## Validation
- make test : 78 tests, 406 assertions, réussite.
- make lint : PHP, 62 YAML, 20 Twig, conteneur et schéma Doctrine valides.
- make validate : Compose valide.
- make verify-install : installation vierge, 14 migrations, import de 146 planètes, réimport idempotent et conservation après redémarrage réussis.

## Limites
- L’attribution initiale du rôle administrateur se fait par commande console; aucune promotion n’est disponible depuis l’interface web.
- Les listes de l’interface d’administration ne sont pas encore paginées.
- Cette phase ne constitue pas un audit RGAA/WCAG, de charge ni une déclaration de préparation à la production.
