# Genesis Reborn — blocages et travaux restants

## Blocages techniques
Aucun blocage n'empêche l'accès au dépôt ou l'exécution des tests PHP. Compose nécessite --env-file .env.local; les cibles Makefile le font déjà. L'appel docker compose ps sans cet argument échoue sur les variables locales obligatoires, mais ce n'est pas un défaut applicatif. Le terminal PowerShell du contexte ne démarre pas; les commandes WSL passent par le runtime du dépôt.

## Conception à documenter sans arrêter les autres tâches
- Réconcilier les règles détaillées de combat et missions avancées avant le code.
- L'équilibrage final manque pour certaines unités et les données sont explicitement provisional. Implémentation possible avec valeurs provisoires étiquetées; mise en production bloquée jusqu'à validation.
- Conquête, fermeture d'univers, classement et droits admin ont des exigences incomplètes.

## Publication et planification
Le prompt interdit publication/déploiement réel sans autorisation; aucun Site n'est créé. Une tâche planifiée ne peut pas coder la suite sans session de développement et accès au dépôt; aucune relance automatique après quota n'est revendiquée. La reprise manuelle figure dans RESUME.md.

## Travail restant
Les éléments de docs/LIVRAISON-DOMAINES-UI.md sont des tâches, pas des blocages techniques. Priorité : missions avancées et combat, puis univers avancés, classement, administration, CI, audit sécurité/accessibilité/performance, sauvegarde et restauration.
