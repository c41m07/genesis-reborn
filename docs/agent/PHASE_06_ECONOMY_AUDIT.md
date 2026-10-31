# Phase 06 — économie

État : TERMINÉE le 31/10/2026 pour les mécaniques et invariants économiques.

## Fonctionnement vérifié

- Les ressources sont calculées à la demande côté serveur à partir du stock, du taux et de l’instant du dernier calcul; une horloge qui recule ne peut pas faire baisser ou réécrire le stock.
- Les files sont rattrapées chronologiquement avant dépense ou lecture. Lorsqu’un bâtiment change production ou stockage, le stock est d’abord matérialisé à la date de fin avec les anciennes règles; le nouveau taux/plafond ne s’applique qu’ensuite.
- La capacité de base et les entrepôts imposent un plafond. Le rattrapage ne permet pas de conserver une production excédentaire antérieure à l’amélioration du stockage.
- Les coûts et durées sont calculés côté serveur à partir des catalogues et multiplicateurs d’univers. La dépense s’effectue sous verrou; les remboursements après annulation sont plafonnés par l’espace disponible.
- La centrale énergétique augmente effectivement la production d’énergie; les dépendances économiques affichées sont comparées au calcul serveur.

## Preuves de test dans la suite actuelle

- Chronologie d’une mine achevée avant une dépense, sans double traitement d’un ancien message.
- Stockage plafonné avant la fin de son amélioration.
- Dépense concurrente : deux commandes ne peuvent pas consommer plus que le stock disponible.
- Remboursement plafonné au stockage restant.
- Production énergétique réelle et avantages de catalogue cohérents.
- Résultat de la suite courante : 71 tests / 355 assertions; lint, schéma et configuration Compose valides (voir TEST_STATUS.md).

## Limites conservées

Les coefficients de production, coûts, durées, stocks initiaux, kit de colonie et ratio de remboursement sont explicitement marqués provisoires dans les catalogues. Cette phase clôt les invariants de calcul, pas l’équilibrage de partie réelle; ces valeurs restent à observer puis valider avant de déclarer le jeu prêt pour production. Le coût et l’acquisition des boosts restent à spécifier.
