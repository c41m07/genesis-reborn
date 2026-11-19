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


## Reprise : état d’action explicite et contrat du renvoi

Complexité 2/5 pour l’incrément de lecture livré ; 5/5 pour le futur cycle
d’envoi partagé. La fiche distingue maintenant renvoi inutile (adresse déjà
confirmée), compte suspendu (le renvoi ne résout pas cette restriction), et
renvoi administratif indisponible. Le lien public n’est proposé que pour une
adresse non confirmée sans suspension. Ce filtre d’affichage n’est pas un
nouveau contrôle du service public et ne modifie aucune permission.

### Faisabilité et défauts à traiter ensemble

Le renvoi est réalisable, mais pas en branchant un nouveau contrôleur sur le
service existant. Relecture de `sendWelcomeAndVerification` : si le compte est
confirmé entre le premier contrôle et l’acquisition du verrou, le retour de la
closure n’arrête pas le code appelant ; celui-ci peut encore construire puis
envoyer le lien non enregistré. Risque statique, pas course reproduite ici.
`AccountMailDelivery` réessaie trois fois une exception de transport ; une
exception après acceptation distante reste ambiguë. Un reçu local ne peut pas
rendre SMTP exactement-une-fois. Ces points complètent les limites précédentes.

Contrat proposé pour un prochain lot, non implémenté dans cette tranche :

1. Une demande publique ou admin passe par le même service. Sous verrou du
   compte, relire confirmation/suspension, contrôler un cooldown par compte
   et une clé de demande unique. Même clé : retourner le même reçu ; autre
   clé pendant le cooldown : aucun nouveau jeton ni nouvel envoi. La durée du
   cooldown reste une décision à fixer, pas une valeur inventée dans le code.
2. Persister atomiquement un reçu et une intention d’envoi. Le reçu contient
   identifiant interne, acteur/cible, origine, motif borné, dates et état ; ni
   adresse, token, URL, corps de mail, ni exception SMTP brute dans l’audit.
   La charge confidentielle nécessaire à l’envoi est distincte du journal.
   Sa protection, rétention et suppression doivent être définies avant stockage.
3. Un worker revendique la demande par transition conditionnelle. Relecture
   d’éligibilité avant transport ; une demande devenue inutile est annulée.
   Un message livré deux fois au worker ne crée pas deux envois simultanés.
4. États distincts : demandée, prise en charge, acceptée par le transport,
   annulée, résultat indéterminé. Ne jamais afficher « reçu par le joueur ».
   Après interruption autour de SMTP : résultat indéterminé sans réessai
   automatique aveugle. Un identifiant idempotent côté fournisseur peut réduire
   ce risque seulement si son contrat et sa disponibilité sont vérifiés.
5. Le renvoi public doit utiliser ce même cycle dès l’ouverture de l’action
   admin, sinon cooldown et invalidations restent contournables entre parcours.
   Revoir aussi les appels d’inscription et les confirmations concurrentes.
6. Tests requis avant activation : deux processus synchronisés en base isolée,
   doubles requêtes publiques/admin, double consommation worker, confirmation
   ou suspension concurrente, panne avant/après transaction et transport,
   refus CSRF/droits, confidentialité du rendu/audit. Mailer factice uniquement.

Aucune migration ni outbox de mail n’a été ajoutée ici. Aucun bouton d’envoi,
worker, modification du service public ou promesse de concurrence garantie.
La nouvelle donnée `activation_action` est un état de présentation, jamais
une autorisation réutilisable pour écrire. Les droits ROLE_ADMIN et le journal
de consultation existants sont inchangés.

Validation de cette reprise : `make validate`, lint Twig et `git diff --check`
réussis ; `AdminAccountDiagnosticTest` et `AdminDedicatedPagesTest` : 4 tests,
212 assertions. Mailer mock avec `send` attendu zéro fois, comptes synthétiques
et base tmpfs privée supprimée après recette. Les 404 attendus sont les tests
d’accès absent. Pas de test de concurrence nouveau : aucun cycle d’envoi n’a
été modifié. Pas de recette visuelle, CI complète ni publication.


## Fondation technique : préparation avant transport

Complexité 3/5. `EmailVerificationService` prépare maintenant le jeton et un
instantané privé du destinataire dans une méthode transactionnelle distincte.
L’état confirmé est relu après verrou/refresh. Un retour nul de cette étape
arrête réellement le transport : aucun lien non enregistré ni message de
bienvenue n’est envoyé. Génération et échéance du jeton sont calculées après
l’acquisition du verrou. L’expiration de 24 heures et le contrat booléen public
sont conservés ; `true` sur compte déjà confirmé reste un résultat sans envoi,
pas une preuve d’acceptation SMTP. Aucun jeton clair n’est persisté ou journalisé.

La régression est reproduite de manière déterministe : une mise à jour SQL de
la confirmation laisse l’objet Doctrine périmé avant l’appel du service. Le
même test avec l’ancien service échoue car le mailer factice est appelé ;
avec le correctif il réussit et aucun jeton n’est créé. Cela ne prouve pas la
sécurité de deux renvois simultanés, dont la course reste ouverte.

Contrôles : 16 tests / 171 assertions réussis (confirmation, réinitialisation,
diagnostic admin), syntaxe PHP, Compose et diff. Les échecs `account_mail.failed`
sont simulés dans les tests existants. Base privée tmpfs et mailer factice ;
aucun mail réel, migration, serveur production ou service habituel utilisé.

Reste avant ouverture du bouton admin : reçu persistant idempotent, cooldown
atomique commun aux appels publics/admin/inscription, état inconnu en cas de
crash autour de SMTP, transport séparé revendiqué une seule fois et tests
multi-processus. Aucun de ces invariants n’est déclaré acquis par ce correctif.
Pas de migration ni nouvelle table dans cette tranche. Le panneau continue
honnêtement d’afficher le renvoi administratif comme indisponible.
