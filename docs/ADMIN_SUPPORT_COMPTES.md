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


## Fondation persistante de réservation (non exposée)

Complexité 5/5. `VerificationRequestReservation` et le reçu
`VerificationDeliveryRequest` préparent le cycle commun sans ouvrir de route,
sans créer de jeton et sans envoyer de mail. L’ancien parcours public ne les
appelle pas encore : aucune protection du renvoi public actuel n’est annoncée.

La réservation exige une transaction propre. Elle relit le compte sous verrou
pessimiste puis effectue des lectures courantes verrouillées des reçus, y compris
en isolation REPEATABLE READ. Un compte inexistant, confirmé ou suspendu est
refusé avant toute création. La clé aléatoire de 64 caractères hexadécimaux est
hachée ; unicité SQL par compte et clé. Une répétition retourne le même reçu,
sans prolonger le délai. Une autre clé est bloquée par toute demande non
terminale ou tout cooldown non expiré. Seuls `transport_accepted` et `cancelled`
sont terminaux : état inconnu, réservé ou indéterminé reste bloquant même après
expiration du délai. Cela privilégie l’absence de doublon à une reprise aveugle.

La durée de cooldown est un paramètre interne obligatoire borné entre 1 et
86 400 secondes, pas un paramètre de formulaire ni une nouvelle règle produit.
L’intégration devra fixer une politique commune et non modifiable par le
client. Les 300 secondes des tests ne constituent pas cette décision.

Le reçu ne stocke qu’identité interne du compte, hachage de clé, état et dates.
Ni adresse, jeton, contenu, URL ni erreur de transport. Il ne représente ni une
autorisation admin ni une preuve d’envoi. Aucun changement de droit ou nouveau
point d’accès. Le motif/acteur d’une future commande admin reste à intégrer au
journal autorisé existant sans transformer ce reçu en journal de contenu.

Migration `Version20261119235900` préparée, aucune application à une base
existante. Pas de purge automatique : effacer trop tôt les reçus supprimerait
l’idempotence. Avant activation restent nécessaires : politique de rétention,
revendication/transition du worker, résultat indéterminé après crash, circuit
public/inscription commun, autorisation et CSRF admin, présentation et tests
complets. Ne pas mettre le bouton admin en service avec cette seule fondation.


### Recette de la réservation

13 tests / 142 assertions réussis : réservation, confirmation et diagnostic
admin. La réservation couvre replay, nouvelle clé bloquée, frontière exacte du
cooldown, état réservé/indéterminé toujours bloquant après délai, compte absent,
confirmé/suspendu, entrées invalides et refus de transaction externe.
Deux processus PHP distincts, deux connexions MariaDB, barrière de départ
commune : une seule ligne avec la même clé (réservé/rejoué), puis avec deux clés
(réservé/bloqué), même identifiant de reçu retourné. C’est un test de concurrence
réelle de réservation, pas de transport ni de reprise après crash.

La première version commençait par une lecture non verrouillée et le test a
révélé SQLSTATE 1020 ; la première lecture est désormais elle-même verrouillée.
La suite finale passe sans changement d’isolation serveur ni retry masquant
l’erreur. Le service inutilisé est construit explicitement dans les tests
(plutôt que rendu public dans le conteneur). La migration `up` a été exécutée
uniquement dans la base jetable, puis réservation/replay vérifiés dessus.
La fixture retire sa table de migration avant la reconstruction ORM suivante,
car leurs noms de contrainte FK diffèrent.

PHP des six nouveaux fichiers, conteneur Symfony, Compose et diff réussis.
Le réseau/base `genesis-admin-support-test` ont été supprimés après recette.
Aucun mail réel, migration d’une base habituelle, push, fusion ou déploiement.
La CI complète et l’intégration des lots restent à effectuer séparément.


## Cycle de revendication du reçu (toujours sans transport)

Incrément séparé après `6c93274`, complexité 5/5. Aucune route, commande ou
consommateur Messenger n’utilise encore ce service. Les réservations sont
revendiquées atomiquement par `VerificationRequestLifecycle` : état réservé
vers traitement, secret aléatoire remis une seule fois au demandeur et hachage
seul conservé. Durée interne de lease explicite, entre 1 et 3 600 secondes.

Ordre de verrouillage commun : compte puis reçu. L’orientation compte depuis
l’identifiant du reçu est une lecture hors transaction ; existence, compte et
états sont revérifiés sous verrou. Une suspension ou confirmation acquise
avant revendication annule la demande sans produire de secret propriétaire.
Une seconde revendication ne reçoit jamais le secret du premier worker.

La finalisation exige le secret correspondant et un résultat explicite :
acceptation du transport, annulation avant transport, ou indéterminé. Un appel
répété du propriétaire avec le même résultat est sans effet ; un résultat
contradictoire ou un autre propriétaire est refusé. Les tests simulent ces
résultats : ils ne prouvent aucun envoi ou acceptation SMTP réelle.

À l’échéance exacte, une tentative en traitement devient indéterminée, jamais
réservée de nouveau. La méthode `expire` est explicite ; claim/complete contrôlent
aussi cette échéance. Un worker tardif ne peut plus inscrire un succès après
expiration. L’indéterminé continue de bloquer toute nouvelle réservation.
Une demande jamais revendiquée reste réservée : aucune purge ni annulation
arbitraire de ce cas n’est ajoutée. Le secret n’est ni loggé ni inclus dans les
résultats du worker de test de compétition.

Migration additionnelle `Version20261120000100` préparée et appliquée seulement
dans la base jetable après la migration de reçu. Aucun schéma habituel changé.
Les deux migrations restent à appliquer selon les règles de sauvegarde et
validation du dépôt lors d’une future intégration autorisée.

Recette : 15 tests / 162 assertions réussis (cycle/réservation, confirmation,
diagnostic). Parmi eux, deux processus synchronisés revendiquent le même reçu :
un seul obtient `claimed`, l’autre `unavailable`. Couverture du mauvais secret,
replay et résultat contradictoire, seuil d’expiration, refus du succès tardif,
compte devenu suspendu et absence de reprise après délai. PHP, Compose,
conteneur et diff vérifiés séparément ; aucun mail réel.

Prochain pas concret : un adaptateur de transport factice liant une revendication
à la préparation du jeton, avec pannes injectées avant/après commit/transport.
Ne pas connecter le mailer existant directement : il réessaie les exceptions
et peut avoir accepté un message avant l’exception. Restent aussi la relecture
d’éligibilité immédiatement avant transport, stratégie de confidentialité du
payload, intégration du parcours public/inscription, droits/CSRF/audit admin,
et résolution opérateur des états indéterminés. Le bouton reste fermé.


## Preuve supplémentaire par pannes injectées (tests seulement)

Aucun adaptateur de production inutilisé n’est ajouté. Le harnais de tests
simule les frontières du futur adaptateur autour du cycle existant. Une panne
Doctrine `onFlush` avant commit de revendication vérifie un rollback réel :
aucun secret propriétaire retourné, état réservé en base, aucune lease ni
empreinte propriétaire persistée ; nouvelle unité de travail puis revendication
possible. Un objet Doctrine muté avant rollback ne sert jamais de preuve.

Quatre scénarios supplémentaires : arrêt avant préparation, après préparation
fictive mais avant transport, exception ambiguë pendant l’appel factice, et
arrêt après acceptation fictive avant finalisation. Les deux premiers comptent
zéro appel de transport ; les deux suivants exactement un. Après passage en
indéterminé, deux reprises ne peuvent plus revendiquer et n’effectuent aucun
appel supplémentaire ; une nouvelle clé reste bloquée après cooldown. Les
arrêts ne produisent pas de faux succès et le propriétaire tardif ne peut plus
faire passer le reçu en accepté.

Ce harnais ne génère pas de vrai jeton, ne prépare pas de vrai email et
n’appelle pas le service public. Il prouve les transitions du reçu et leur
résistance aux scénarios simulés, pas la délivrabilité ou le raccordement au
mailer historique. `VerificationRequestReservationTest` : **11 tests / 83
assertions réussis**, comprenant migrations jetables et compétition réelle
multiprocessus déjà ajoutées. Base et réseau dédiés supprimés après recette.
Aucun fichier applicatif ni migration supplémentaire dans cette passe.

### Décisions opérationnelles encore nécessaires avant raccordement

- Politique commune : durée de cooldown par compte et éventuel quota glissant
  additionnel, appliqués à inscription/public/admin ; ne pas reprendre les
  300 secondes de fixture comme décision de production.
- Lease du worker : durée bornée compatible avec timeout transport et arrêt
  du worker ; l’expiration ne déclenche jamais automatiquement un nouvel envoi.
- Rétention : durée des reçus terminaux et fenêtre contractuelle de replay.
  Pas de purge avant définition de cette fenêtre ; les reçus indéterminés ne
  doivent pas être purgés pour débloquer implicitement un compte.
- Résolution manuelle : qui peut examiner un résultat indéterminé, quelles
  preuves de transport sont disponibles et quelle confirmation autorise un
  nouvel essai avec risque explicite de doublon. Ni « reçu par le joueur » ni
  « certainement non envoyé » ne peuvent être déduits d’une simple exception.
- Données privées : décider si la préparation reste éphémère ou si un payload
  durable est indispensable ; protection et suppression à définir avant de
  stocker une adresse ou un lien d’activation dans une file.
- Intégration : motif/acteur/audit admin, droits/CSRF, réponse publique neutre,
  remplacement du retry aveugle du mailer pour ce nouveau circuit, sauvegarde
  vérifiée et application autorisée des deux migrations. Aucune de ces actions
  n’est activée par les tests présents.

## Raccordement administratif expérimental, désactivé par défaut

Complexité 4/5. La route POST `/admin/joueurs/{id}/activation` est désormais
testable avec le cycle de reçu. Le paramètre `ADMIN_ACTIVATION_ENABLED` vaut
0 par défaut : formulaire absent et POST 404. Même avec ce paramètre à 1,
le transport livré `UnavailableAdminActivationTransport` ne construit aucun
jeton et n'appelle aucun mailer : il retourne strictement `not_dispatched`.
Ce lot n'est donc **pas un renvoi opérationnel** et ne doit pas être présenté
comme tel. Le transport acceptant n'existe que comme double de test.

ROLE_ADMIN existant, CSRF et confirmation explicite restent requis. Le service
revérifie rôle courant, suspension et confirmation du compte administrateur
sous verrou ; les verrous acteur/cible sont pris dans l'ordre des identifiants.
La cible doit rester non confirmée et non suspendue. La réservation et son
audit initial sont atomiques ; motif fermé et identifiant de reçu seulement,
sans email, jeton, clé d'idempotence ni message d'exception dans l'audit.
Le résultat fait l'objet d'un second audit. Si cet audit échoue après la
transition, aucun succès n'est garanti au demandeur : le reçu fait foi et
l'idempotence interdit un second appel du transport pour la même demande.

Le prototype admin utilise 300 secondes de refroidissement et une revendication
de 120 secondes. Ce ne sont pas de nouveaux paramètres publics.
Inscription, service de confirmation et messages publics sont **inchangés**.
La cadence publique existante est de cinq demandes par heure et par IP
(fenêtre glissante, clé verification + IP), pas un délai par compte.

### Limite avant tout transport réel

La voie publique peut remplacer le jeton pendant un envoi administratif.
Le verrou de réservation seul ne protège pas la période SMTP. Il faut donc
une coordination commune du cycle public/admin avant raccordement d'un vrai
transport, sans imposer arbitrairement cinq minutes au parcours public.
Il faut aussi décider quotas, rétention et résolution humaine des reçus,
puis autoriser séparément toute migration sur une base existante.

`not_dispatched` n'est admis que si l'adaptateur prouve qu'aucun appel de remise
n'a eu lieu. Il devient cancelled, sans annonce de mail envoyé.
Une exception, un délai expiré ou un résultat inattendu devient unknown :
aucune relance automatique ni reclassement en « non envoyé ». Un transport
acceptant signifie seulement prise en charge, jamais livraison au destinataire.

Récupération manuelle : le diagnostic existant et le formulaire public restent
accessibles, mais il n'existe pas encore de commande sûre de résolution d'un
reçu unknown. Ne pas effacer le reçu ni le réarmer pour débloquer une relance.
Avant un transport réel, il faut un protocole d'arrêt/contrôle du worker et de
rapprochement avec le fournisseur, puis une décision auditée de nouvelle
demande. Une absence de trace SMTP ne prouve pas la non-remise. Le formulaire
public peut renouveler un lien, mais invalide l'ancien : il ne constitue pas
une résolution fiable d'une remise concurrente indéterminée. Tant que ce
contrat n'est pas défini, le garde-fou désactivé et l'adaptateur sans envoi
empêchent de créer ce risque pour les comptes réels.

### Recette locale de ce lot

- Compose, conteneur Symfony et Twig ciblé valides ; diff sans erreur.
- Activation : 6 tests / 37 assertions (garde-fou, anonyme/joueur, CSRF,
  confirmation, double soumission, refroidissement, audit, exception ambiguë,
  absence de secret, rôle révoqué en base malgré objet périmé, cible confirmée,
  preuve de non-départ du transport par défaut).
- Fondation reçus : 11 tests / 83 assertions, y compris processus réellement
  concurrents et pannes injectées précédemment documentées.
- Confirmation publique et diagnostic : 9 tests / 114 assertions.
- Total : **26 tests / 234 assertions**, base MariaDB jetable et mailer factice ;
  base et réseau privés retirés après recette.
- Aucune recette navigateur ou CI complète revendiquée. Aucun mail réel,
  rôle attribué, changement public/signup, migration sur base existante,
  publication, fusion ou déploiement. La modification préexistante de
  `app/config/reference.php` est conservée hors commit.
