# Genesis Reborn — blocages et travaux restants

## Blocages techniques
Aucun blocage n'empêche l'accès au dépôt ou l'exécution des tests PHP. Compose nécessite --env-file .env.local; les cibles Makefile le font déjà. L'appel docker compose ps sans cet argument échoue sur les variables locales obligatoires, mais ce n'est pas un défaut applicatif. Le terminal PowerShell du contexte ne démarre pas; les commandes WSL passent par le runtime du dépôt.

## Règles et choix encore à confirmer
- Les règles de conquête JcE ont été confirmées par Kévin : cibles neutres, flottes et défenses PNJ, victoire plus vaisseau colonie survivant, limite habituelle de colonies, bâtiments/stocks conservés, défenses PNJ retirées, pertes PNJ persistantes. Les compositions de départ par niveau sont provisoires et modifiables dans le catalogue.
- Les règles d’espionnage sont désormais consolidées et implémentées. La fermeture asynchrone d’univers est implémentée et vérifiée.
- L'équilibrage final manque pour certaines unités et les données sont explicitement provisional. Implémentation possible avec valeurs provisoires étiquetées; mise en production bloquée jusqu'à validation.
- Les classements/statistiques et droits admin ont des exigences incomplètes.

## Publication et planification
Le prompt interdit publication/déploiement réel sans autorisation; aucun Site n'est créé. Une tâche planifiée ne peut pas coder la suite sans session de développement et accès au dépôt; aucune relance automatique après quota n'est revendiquée. La reprise manuelle figure dans RESUME.md.

## Travail restant
Les éléments de docs/LIVRAISON-DOMAINES-UI.md sont des tâches, pas des blocages techniques. Priorité : phase 09 — audit du combat; ensuite classements, classements, administration, CI, audits sécurité/accessibilité/performance, sauvegarde et restauration.
