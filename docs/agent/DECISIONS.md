# Genesis Reborn — décisions agent

## Références
La source de vérité gameplay reste docs/REGLES.md, docs/DECISIONS.md, puis docs/specifications/*.txt et docs/technic/*.pdf. Ne pas annuler un arbitrage explicite utilisateur. Technologies observées : Symfony 7.4, PHP 8.4, Doctrine ORM 3, MariaDB 11.8, RabbitMQ 4.3, Mercure, Twig, Stimulus et Encore.

## Décisions de cette exécution
- Réutiliser feat/genesis-reborn-production-completion; ne pas créer une branche doublon.
- Utiliser le statut Git WSL comme référence. Préserver .idea/ non suivi; un statut Git Windows via UNC différait (modes/binaires) et n'a pas été utilisé pour piloter le dépôt.
- Le dépôt est une application PHP/Symfony locale; aucun .openai/hosting.json n'a été inventorié à la racine. Sites ne sait pas héberger une app Symfony comme Worker/static. Aucune création de Site ni publication; le prompt interdit toute publication sans autorisation spécifique.
- Les tests locaux sont autorisés par le prompt et utilisent database-test isolé en tmpfs. La validation du 31/10 a confirmé 51 tests / 189 assertions.
- Les décisions utilisateur priment sur les paramètres provisoires : le butin autorisé est le stock supérieur au plancher protégé de 10 % de la capacité de chaque ressource; distribution entre attaquants survivants selon la soute libre. Ces seuils sont désormais configurés.

## Décisions en attente de preuve
- Vérifier si les PDF signalés modifiés diffèrent réellement de leurs extractions texte avant d'y toucher.
- Examiner la chaîne flotte, migrations et tests avant d'introduire les états attaque/orbite/défense.
- Le butin suit le prompt et les choix confirmés par Kévin : tous les attaquants survivants partagent selon leur espace libre, les ressources chargées sont au prorata des stocks pillables; 10 % de capacité reste protégé par ressource. Au retour, le stockage restant plafonne le dépôt et le surplus est perdu.
- Kévin a confirmé la conquête JcE uniquement contre planètes neutres, les flottes et défenses PNJ, le vaisseau colonie survivant, le plafond habituel, la conservation des bâtiments et stocks, la suppression des défenseurs après capture, et la persistance des pertes après échec. Les mondes neutres sont séparés en PNJ (environ 30 %, tirage enregistré dans le catalogue) et vierges colonisables. La règle spatiale reste 1 à 9 planètes par système. L’équilibrage des compositions reste provisoire dans le catalogue.
