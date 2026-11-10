# Emails de compte — investigation du 9 novembre 2026

Périmètre transversal : phase 04 (comptes), phase 13 (non-régression), phase 14
(exploitation). Aucune phase globale n’est déclarée validée dans cette intervention.

## Preuves locales

- `EmailVerificationService` et `PasswordResetService` capturaient les exceptions
  de transport puis poursuivaient ; l’inscription annonçait le succès de façon
  systématique après création du compte.
- `app/config/packages/messenger.yaml` ne route aucun email vers `async`.
  Les cinq retries du worker concernent les événements de jeu, pas ces emails.
- Le renvoi `/inscription/renvoyer` existe, avec limitation et réponse neutre.
- Le Compose serveur reçoit sa configuration privée via `APP_ENV_FILE`.
  Le dépôt ne permet pas de déduire le transport réellement utilisé par l’alpha.
- Les tentatives signalées sont situées vers 09:58 et 10:04, heure de Paris,
  le 9 novembre 2026 (créneau à confirmer). Rechercher aussi 08:58–09:04 UTC.

La cause exacte de l’incident alpha reste indéterminée faute de configuration
effective et de journaux serveur/fournisseur. Les constats locaux expliquent
le manque de visibilité et de reprise, pas l’origine certaine de la panne.

## Garde-fous locaux

`AccountMailDelivery` réalise trois tentatives synchrones au total, espacées
de 200 puis 400 ms après exception de transport. Il conserve le même message
et le même jeton. Les timeouts SMTP restent ceux du transport configuré ;
trois timeouts peuvent allonger sensiblement la requête. Une réponse SMTP
perdue après acceptation peut provoquer un doublon lors d’une reprise.

Les événements `account_mail.retrying`, `account_mail.transport_accepted` et
`account_mail.failed` portent un identifiant de corrélation aléatoire, le type,
le numéro de tentative et éventuellement la classe de l’exception. Ni son
message, ni l’adresse, ni le contenu, ni le jeton ne sont journalisés par ce
service. L’acceptation par le transport ne prouve pas la réception ; un transport
`null://null` peut notamment accepter et jeter les messages.

L’échec final est de niveau critical, destiné à la surveillance des journaux.
Aucune notification externe indépendante n’est installée par ce correctif.
Les états sont des traces, pas un historique persistant consultable par compte.
Il n’existe toujours aucune file durable ni reprise après arrêt du processus
pour ces emails. Ces exigences restent ouvertes ; ne pas router brutalement
les messages contenant des liens secrets vers la file des événements de jeu.

L’inscription conserve le compte non confirmé et redirige vers le renvoi avec
un avertissement si l’email de confirmation échoue. Un échec du seul message
de bienvenue n’empêche pas de confirmer. Les réponses publiques du renvoi et
de la récupération restent neutres pour protéger l’existence des comptes.

## Vérifications alpha à effectuer avec accès autorisé

1. Identifier l’image et la révision déployées ; comparer aux fichiers inspectés.
2. Examiner sans divulgation le schéma de `MAILER_DSN`, sa présence effective,
   l’expéditeur autorisé `MAILER_FROM`, l’URL HTTPS `DEFAULT_URI` et le fichier
   chargé dans le conteneur app. Ne pas publier les valeurs privées ni utiliser
   une commande affichant l’environnement complet ou les secrets Compose.
3. Chercher les deux avertissements historiques d’envoi dans les journaux app
   sur 09:50–10:15 Paris ; collecter uniquement des diagnostics expurgés.
4. Vérifier les événements du fournisseur (acceptation, rejet, bounce, quota,
   blocage sandbox), l’autorisation de l’expéditeur et SPF/DKIM/DMARC.
5. Examiner les workers et files en lecture seule ; un worker sain ne prouve
   pas le fonctionnement du SMTP synchrone. Ne pas relancer une file globale.
6. Après preuve de la cause, préparer la correction de configuration ; toute
   connexion réseau, modification serveur ou tentative d’envoi réelle requiert
   l’autorisation correspondante. Ne pas copier de liens privés dans le rapport.

## Reprise sur dev — 9 novembre 2026

Lot repris depuis la sauvegarde locale sur une branche dédiée
`feature/fiabilisation-emails-compte`, créée depuis `dev` au commit `7c27753`.
Seuls les dix fichiers du lot emails sont repris. Le README ne reçoit que
l'ajout relatif aux emails ; `docs/DECISIONS.md` reste identique à dev.
La sauvegarde complète est conservée sans être appliquée globalement.

Fichiers du lot : `AccountMailDelivery.php`, `EmailVerificationService.php`,
`PasswordResetService.php`, `SecurityController.php`, `AccountMailDeliveryTest.php`,
`EmailVerificationTest.php`, `PasswordResetTest.php`, `README.md`,
`docs/ARCHITECTURE.md` et le présent document.

Vérifications locales de cette reprise :

- `make validate` : réussi.
- `make lint` : réussi (PHP, YAML, Twig, conteneur et schéma).
- `make assets` exécutant `npm run build` : réussi.
- `make test` : exécution complète réussie, 555 tests PHP, 209187 assertions,
  puis 13 tests Python. Les traces `account_mail.failed` correspondent aux
  échecs de transport simulés par les tests.
- `make test-browser` : exécution complète réussie, 130 scénarios sur 130
  en une seule exécution (42,2 minutes), sans reprise ciblée.
- `git diff --check` : réussi, y compris après actualisation documentaire.

Les tests HTTP vérifient la conservation du compte et l'avertissement suivi
du formulaire de renvoi ; les réponses de récupération et de renvoi restent
neutres. Aucun template ni route publique n'est modifié. Les contrôles ciblés
ne constituent pas un audit RGAA global.

Aucun commit, push ou déploiement effectué. Les migrations de la suite
navigateur concernent uniquement sa base jetable ; aucune migration de la
base de jeu n'est appliquée. Ces résultats locaux ne prouvent ni une validation
CI GitHub, ni la réception des messages en production.

La cause de l'incident alpha reste à déterminer avec un accès autorisé ou
des journaux expurgés ; ce lot améliore la reprise et la visibilité des erreurs
sans établir cette cause ni ajouter une file durable d'envoi.
