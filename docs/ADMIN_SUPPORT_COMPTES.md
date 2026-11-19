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


## Incrément : retrouver les inscriptions à confirmer

Complexité 3/5 : filtre serveur et pagination de comptes privés.
La liste propose désormais Adresse email : Toutes / Confirmée / À confirmer.
Ce critère se combine au rôle, à la recherche et à la suspension avant LIMIT.
La valeur `status=active` conserve son contrat « non suspendu » et son libellé
est précisé en conséquence. Une ligne non suspendue mais non confirmée indique
« Adresse à confirmer » plutôt que « Actif ». Aucun statut de compte n’est changé.
Le formulaire GET, les fragments AJAX et les liens de pagination conservent
le critère ; aucun chargement global ni nouvelle colonne n’est ajouté.

### Pourquoi le renvoi administratif attend un lot de fiabilisation

Lecture de `SecurityController::resendVerification` et
`EmailVerificationService::sendWelcomeAndVerification` :

1. La limite publique est attachée à l’IP dans le contrôleur, pas au compte
   dans un service partagé. Un appel admin direct la contournerait.
2. L’invalidation des anciens jetons et la création du suivant se font sous
   verrou, mais le transport intervient ensuite, hors transaction. Deux
   renvois peuvent donc créer/invalider des liens dans un ordre différent
   de leur réception. C’est un risque déduit du code, pas une course reproduite.
3. Aucun reçu persistant de demande admin, ni clé idempotente commune, ni état
   d’envoi par compte n’existe. Un arrêt après acceptation SMTP ne permet pas
   de décider sans risque si un réessai serait un doublon.
4. Le service retourne un booléen d’acceptation de transport, pas une preuve
   de réception. Les logs sont expurgés et non corrélables au compte depuis
   cette fiche. Réutiliser ces logs comme un historique serait trompeur.

Le lot préalable devra définir un cycle de demande commun public/admin,
un cooldown atomique par compte, les transitions et reçus idempotents,
les états inconnus après interruption, puis tests de concurrence réelle et
transport simulé. Une éventuelle migration sera préparée, non appliquée sur
base existante sans sauvegarde et autorisation. L’audit conservera motif,
acteur, cible et résultat sans token, adresse, corps ou erreur SMTP brute.
Pas de faux bouton « envoyé », de contournement du parcours public ou de
modification opportuniste de l’authentification dans cet incrément.

Vérifications de l’incrément : syntaxe PHP des deux fichiers applicatifs et du
test, Twig des deux fragments réussis ; 5 tests / 252 assertions réussis
(`AdminAccountDiagnosticTest`, `AdminPaginationTest`, `AdminDedicatedPagesTest`).
Fixture de 52 comptes en attente : 50 + 2 résultats, critères combinés, retour
fragmentaire sans cache et conservation du filtre dans la pagination.
Recette visuelle/clavier à effectuer, aucune conformité globale revendiquée.
