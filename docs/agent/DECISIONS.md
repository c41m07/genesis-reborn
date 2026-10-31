# Genesis Reborn — décisions agent

## Références
La source de vérité gameplay reste docs/REGLES.md, docs/DECISIONS.md, puis docs/specifications/*.txt et docs/technic/*.pdf. Ne pas annuler un arbitrage explicite utilisateur. Technologies observées : Symfony 7.4, PHP 8.4, Doctrine ORM 3, MariaDB 11.8, RabbitMQ 4.3, Mercure, Twig, Stimulus et Encore.

## Décisions de cette exécution
- Réutiliser feat/genesis-reborn-production-completion; ne pas créer une branche doublon.
- Utiliser le statut Git WSL comme référence. Préserver .idea/ non suivi; un statut Git Windows via UNC différait (modes/binaires) et n'a pas été utilisé pour piloter le dépôt.
- Le dépôt est une application PHP/Symfony locale; aucun .openai/hosting.json n'a été inventorié à la racine. Sites ne sait pas héberger une app Symfony comme Worker/static. Aucune création de Site ni publication; le prompt interdit toute publication sans autorisation spécifique.
- Les tests locaux sont autorisés par le prompt et utilisent database-test isolé en tmpfs. La validation du 31/10 a confirmé 51 tests / 189 assertions.
- Ne pas modifier les valeurs d'équilibrage provisoires avant consolidation des règles.

## Décisions en attente de preuve
- Vérifier si les PDF signalés modifiés diffèrent réellement de leurs extractions texte avant d'y toucher.
- Examiner la chaîne flotte, migrations et tests avant d'introduire les états attaque/orbite/défense.
- Réconcilier les règles d'attaque, renforts et butin entre spécifications 1, 2 et 3 sans inventer de formule manquante.
