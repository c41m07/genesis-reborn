# Administration — diagnostic des comptes

## Périmètre de ce lot

Branche indépendante `feature/admin-support-comptes`, issue de `origin/dev`
`8be1fd9` (PR #99). Complexité 3/5 : lecture de données de sécurité,
confidentialité du rendu, contrôle d’accès et absence d’effets d’envoi.

La fiche `/admin/joueurs/{id}` comporte un panneau Diagnostic du compte :
confirmation de l’adresse, suspension, état et échéance du dernier lien de
confirmation et de réinitialisation encore conservé. Les états distinguent
absence, expiration (y compris exactement à l’échéance), non-expiration et
confirmation déjà acquise. Le titre ne qualifie plus un compte non confirmé
de « compte actif ».

Le panneau reste dans la fiche : c’est une consultation courte du même compte,
sans formulaire d’intervention propre. Titre h2, liste de définitions, liens
natifs et composants de la refonte existante sont réutilisés. Aucun nouveau
JavaScript, CSS, écran autonome ou onglet artificiel n’est nécessaire.

## Garanties et limites

- `ROLE_ADMIN` et audit de consultation de la fiche conservés.
- Deux lectures bornées à une échéance chacune, filtrées par compte.
  Aucun hachage ni contenu de jeton n’est sélectionné par le repository.
- Ni mot de passe, ni adresse email, ni URL privée d’activation affichés
  par ce panneau ; aucune configuration SMTP exposée.
- Aucune création, invalidation ou consommation de lien ; aucun mail envoyé.
  La lecture du diagnostic seule n’écrit rien. La fiche conserve son audit
  et les traitements déjà présents de préparation des stocks.
- Une date non expirée n’est pas une preuve d’envoi ou de réception.
  Les logs actuels n’offrent pas d’historique persistant par compte.
  L’absence d’un lien ne prouve pas l’absence de demande passée.
- La fiche oriente vers le formulaire public de renvoi existant sans
  préremplir ni transmettre une adresse. Son ouverture n’envoie rien.
- Aucune migration, modification économique, modification des droits ou
  intervention sur une base habituelle/production.

## Sous-lots restant à réaliser

Ce diagnostic est une première tranche, pas la livraison de tout le support.
Le renvoi administratif doit intégrer motif, confirmation, idempotence,
limitation par compte et journal de résultat expurgé. Réutiliser le service
public sans traiter ses courses avec un renvoi simultané invaliderait des
liens sans garantir la cohérence de l’envoi ; aucun bouton direct n’est ajouté.
L’historique persistant des tentatives demande une conception dédiée (rétention,
confidentialité, états précis, migration préparée séparément). L’acceptation
SMTP et la délivrabilité ne doivent jamais être confondues.

Les corrections de niveaux, retrait d’unités, défenses et files/remboursements,
ainsi que la réponse externe aux feedbacks, restent hors de cette tranche.
Leurs invariants et leur recette ne sont pas annoncés comme acquis.

## Vérifications

Contrôles ciblés réussis le 19 novembre 2026 :

- `make configure` et `make validate` dans le worktree dédié.
- Syntaxe PHP des quatre fichiers PHP touchés/ajoutés, Twig du panneau,
  conteneur Symfony (injection).
- 41 tests PHP / 398 assertions : `AdminAccountDiagnosticTest`,
  `AdminInterventionTest`, `AdminDedicatedPagesTest`, `EmailVerificationTest`
  et `PasswordResetTest`. Le diagnostic couvre notamment l’échéance exacte,
  un autre compte, l’absence d’envoi, la confidentialité, le 404 et les accès.
- `git diff --check`.

Exécution PHP 8.4 dans l’image locale existante, dépendances et assets PR99
montés en lecture seule, base MariaDB tmpfs et réseau propres au projet
`genesis-admin-support-test`. Aucun service applicatif habituel redémarré.
Le projet jetable a été retiré après vérification.
Une première fixture HTTP détachée entre requêtes a été corrigée. Un lancement
mal quoté a laissé deux tests concurrents dans cette seule base jetable ;
les erreurs de schéma ont été suivies d’une vérification d’absence de processus
puis d’une reprise séquentielle complète réussie. Aucune base partagée touchée.
Les traces 404 et `account_mail.failed` sont les refus/échecs simulés attendus.
Aucun envoi réel, push, fusion, déploiement ou CI déclenché dans ce lot.
La recette visuelle/clavier et la CI complète restent distinctes des tests HTTP.
Les trois sources Work citées par AGENTS.md sont absentes du dépôt ; aucune
lecture de ces sources externes ni synchronisation du wiki n’est revendiquée.
