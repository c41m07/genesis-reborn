# Génération versionnée du lien d’activation

Complexité 4/5. Lot local additif depuis 3594a06, sans raccordement ni
migration. Les fichiers Work référencés restent absents du dépôt. Pas de
changement de route, formulaire, paramètres, fréquence, inscription, transport,
autorisation ou politique de jeu.

## Contrat du composant

- `VerificationLinkGeneration` : joueur, numéro de génération, version
  d'algorithme, identifiant de clé, nonce, échéance et empreinte. Pas d'entité
  Doctrine et pas de lien en clair ni de clé.
- `VersionedVerificationLink` : génération de métadonnées, reproduction
  déterministe et comparaison de l'empreinte. Le HMAC V1 a un domaine dédié
  et un encodage canonique ; le format 43 caractères base64url correspond à
  l'alphabet et à la longueur habituels du lien historique. La production
  de l'empreinte SHA-256 ne change pas son format.
- `VerificationLinkKeyResolver` : interface uniquement, explicitement passée
  à l'appel. Aucun service concret, secret, valeur d'environnement, accès
  au trousseau ou repli vers APP_SECRET. Les tests injectent des clés
  synthétiques distinctes ; l'entropie et l'isolation du futur trousseau
  relèvent de son fournisseur.

La reproduction ne réémet que si l'algorithme est reconnu, l'échéance
strictement future et la clé correcte ; une clé absente, trop courte ou
différente ne crée pas de génération de remplacement. L'échéance ne se
prolonge pas. La vérification compare l'empreinte d'une génération déjà
désignée comme active : elle n'est pas un contrôle de compte, de droits ni
d'état DB. Elle n'exige pas la présence de la clé de dérivation. Le retrait
de clé seul ne révoque donc pas les liens préparés avec cette clé : pour
une compromission, révoquer explicitement leurs générations en plus de
retirer la clé. Un appelant ne doit jamais fournir une génération archivée.

Le service historique pratique toujours sa lecture par empreinte et conserve
ses anciennes règles ; aucun jeton hérité n'est supposé reproductible. Les
scripts et adaptateurs de preuve précédents ne sont pas inclus dans ce lot.

## Conditions avant intégration

1. Choisir la politique de clé dédiée, d'identifiants immuables utilisables
   par tous les processus, de rétention et de révocation forcée. Appliquer les
   limites de durée au service orchestrateur. Ne pas journaliser ni la clé,
   ni le lien, ni les métadonnées qui ne sont pas nécessaires au support.
2. Préparer la migration additive Doctrine : métadonnées facultatives pour
   les liens hérités, unicité d'une génération active par compte, bornes
   d'horodatage compatibles avec Doctrine et précontrôle des doublons. Aucun
   outil de migration livré ici. Tester l'aller et le rollback applicatif
   sur schéma jetable ; base existante seulement après autorisation explicite
   et sauvegarde vérifiée. Protéger la génération contre la lecture périmée
   sous verrou joueur ; la confirmation doit consommer ce lien sous ce verrou.
3. Intégrer dans un lot distinct réservation + audit durable et coordination
   des voies autorisées, notamment l'inscription pour éliminer l'émetteur
   concurrent de secours avant activation. Distinguer nouvelle émission et
   révocation ; conserver le limiteur public 5/h/IP et les réponses génériques.
   Avec transport factice, tester réellement deux connexions, une révocation
   concurrente, le refus des anciennes générations, et une interruption
   pendant la récupération explicite. Ne pas requalifier un envoi indéterminé
   ni redéclencher un envoi automatiquement.
4. Ne pas faire cohabiter de vrais écrivains anciens et nouveaux. Pour tout
   futur déploiement ou rollback, désactiver et drainer d'abord les émetteurs ;
   conserver les empreintes et l'audit. Le lot présent s'annule sans DDL :
   aucun appelant, donnée ajoutée ni service de clés à désactiver.

## Vérifications

- 5 tests unitaires / 22 assertions : vecteur V1 connu calculé séparément,
  stabilité après reconstruction, alphabet, remplacement explicite, limite
  d'expiration, refus de clé absente/courte/incorrecte, algorithme inconnu et
  écart de métadonnées.
- 17 tests / 101 assertions avec les régressions de confirmation d'adresse
  historique et d'activation admin protégée. MariaDB et réseau de test
  isolés retirés après vérification ; mailer de test/existant, aucun envoi réel.
- Compose, validateur de conteneur Symfony, lint PHP ciblé et diff contrôlés.
  Pas de garantie DB ou concurrence concernant la nouvelle génération, pas
  de navigateur ni CI complète, aucune modification d'env ni publication.

## Persistance additive isolée

La migration `Version20261120000200` ajoute deux tables, sans modifier
les lignes des jetons historiques ni des reçus existants. Elle n'a été
exécutée (montée et retour) que sur MariaDB jetable. Aucune autre base
n'a été ouverte. Ne jamais exécuter le down pour un rollback applicatif :
il efface les générations et l'audit ajoutés. Conserver ces tables pour
un retour arrière compatible, suspendre/drainer les émetteurs et garder
le futur lecteur compatible tant que les liens versionnés peuvent être
utilisés ; le contrôleur historique seul ne sait pas lire la nouvelle table.

- `player_verification_generation` : clé primaire = joueur, version
  monotone à chaque remplacement, état actif/révoqué/consommé. Pas de lien
  ni de clé en base. La préparation garde chaque génération active non
  expirée telle quelle, même si la nouvelle demande propose une échéance
  différente. Si un lien historique valide existe, elle refuse de préparer
  un lien nouveau ; elle ne nettoie pas non plus les empreintes expirées.
  Le composant n'empêche pas l'ancien émetteur de créer un lien *après*
  la préparation versionnée : les émetteurs doivent donc être drainés et
  remplacés ensemble avant d'appeler cette persistance.
- `VerificationGenerationStore` prend le verrou du compte avant toute ligne
  enfant, et relit directement SQL. Les mutations sont réservées à un compte
  non suspendu/non confirmé. Révocation et consommation visent une version,
  pas une génération périmée ; en consommation, confirmer le compte et
  épuiser le lien se fait dans la même transaction. Le service ne doit
  être branché qu'après vérification des autorisations et de la politique
  de révocation. Rafraîchir tout objet Player déjà chargé après l'écriture
  SQL (sans quoi l'EntityManager peut refléter un état périmé).
- `verification_recovery_event` : intention unique par reçu initial,
  enregistrée après le délai explicite et le cooldown du dernier reçu
  `unknown`. Elle contient seulement référence, empreinte de clé de
  demande et horodatage. Même clé = rejeu, autre clé = bloquée. La ligne
  originale n'est jamais réécrite. Ce journal ne réserve, n'enfile ni
  n'envoie de mail ; il ne prétend pas donner une reprise transport sûre.

### Assemblage restant

- Réponse publique neutre et limiteur 5/h/IP inchangés ; choisir/quitter
  la fenêtre des jetons historiques avec le plan de bascule. Le nouveau
  lecteur devra rechercher l'empreinte dans les deux chemins sans rattacher
  le lien au mauvais joueur et vérifier/consommer la génération active
  sous le verrou commun. Examiner un index d'empreinte avant d'ajouter
  un routage qui ignore l'identifiant de joueur.
- Définir l'identité et l'autorisation de l'acteur de récupération/révocation,
  et le cas d'une reprise après interruption avant revendication. Fournir au
  futur orchestrateur une transaction commune pour événement + nouveau reçu
  revendicable et leur audit d'issue ; ne pas simplement enchaîner la méthode
  actuelle avec un envoi. Elle ouvre et clôt sa propre transaction et ne
  garantit que l'intention, pas l'ordre de remise.
- Intégrer une politique de clé et d'expiration approuvée. Le délai de
  récupération est fourni à l'appel interne, pas exposé publiquement ;
  les timestamps DATETIME historiques doivent utiliser le même fuseau
  applicatif dans tous les processus. Idempotence d'envoi, purge et
  compatibilité du lecteur ne sont pas traitées ici.

### Recette additionnelle

32 tests / 214 assertions avec les régressions activation publique,
route admin désactivée, reçu et codec. Quatre tests ajoutés : montée et
retour préservant l'empreinte historique, concurrence de deux préparations
avec échéances distinctes, course révocation/consommation après remplacement
et concurrence de deux intentions après résultat inconnu. Les workers
sont des processus/connecteurs distincts derrière une barrière ; ils ne
font aucun appel mail. Base et réseau privés retirés. Compose, conteneur
Symfony, lint PHP et diff vérifiés. Aucun SMTP, configuration de clés,
appel public, push, intégration ou déploiement.

## Lecteur mixte interne, non raccordé

`MixedVerificationLinkReader::confirm()` recherche l'empreinte dans
les deux familles ; un résultat absent ou ambigu est refusé. Il ne prend
pas un identifiant de joueur fourni par le client et n'a besoin d'aucune
clé de dérivation. Les empreintes versionnées utilisent l'index unique
additif `Version20261120000300`, recette montée/retour sur la base jetable
uniquement. L'index historique est inchangé.

La recherche initiale est non autorisante et hors transaction : elle indique
seulement quel compte verrouiller. La transaction verrouille et relit
le compte, puis les enfants, avec rafraîchissement des objets Doctrine.
Confirmation atomique avec retrait de la ligne du seul lien historique
consommé, ou marquage consommé de la génération active. Si deux familles
existent exceptionnellement pour le compte, les deux confirmations ne
peuvent pas gagner ; la réussite historique révoque aussi la génération
active, une réussite versionnée laisse les empreintes historiques intactes.
Le compte confirmé ne peut consommer aucun autre lien. Un compte suspendu
est refusé par ce lecteur ; le lecteur historique actuellement branché n'a
pas été modifié. Ce refus doit être validé comme règle produit avant branchement.

Le lien brut n'est ni paramètre SQL ni champ d'audit. L'exception synthétique
de transaction externe a vérifié la protection SensitiveParameter dans
la trace PHP. Cette preuve n'audite pas les journaux proxy/serveur HTTP :
il n'y a aucune route nouvelle, et les paramètres SQL portent l'empreinte,
pas le lien brut. La validation d'usage reste la possession du lien ;
un futur contrôleur doit conserver le POST, le CSRF, le no-store/no-referrer
et les réponses neutres existantes.

Recette : **37 tests / 250 assertions**, dont confirmation des deux familles,
courses même lien et deux familles pour un compte, ancien lien révoqué,
expiration, suspension revue après mise à jour SQL, cache d'identité Doctrine,
collision entre familles refusée, traces PHP et régressions historiques.
Courses réelles sur deux processus/connexions ; commandes migrations up/down,
Compose, conteneur, lint PHP et diff vérifiés. DB/réseau jetables retirés.

Aucun raccordement HTTP, résolveur réel, réglage, secret ou envoi réel.
Avant branchement, confirmer la politique de suspension, borner le passage
au lecteur mixte, drainer les anciens émetteurs et valider le plan de rollback.
La migration d'index doit d'abord précontrôler les doublons et être appliquée
après autorisation explicite et sauvegarde si une base existante est concernée.

## Orchestrateur interne de récupération, sans exécution

`VerificationRecoveryOrchestrator` réunit dans une transaction le journal
d'intention, le nouveau reçu distinct et leur relation. La migration
additive `Version20261120000400` ajoute l'identifiant unique (facultatif
pour les anciennes intentions) et la version de génération au journal.
La ligne du reçu d'origine n'est jamais modifiée. Le journal contient
références, horodatage, version et empreinte de la demande ; ni le lien
ni l'empreinte du lien, ni nonce, ni clé ou adresse. Un même identifiant
de demande retrouve le même reçu ; une demande concurrente différente
pour le même reçu initial est bloquée.

Le verrou du compte précède toute lecture verrouillée des reçus et de la
génération. Le reçu d'origine doit être le dernier du compte, indéterminé
et résolu, le délai et le cooldown écoulés pour réserver une première
fois. L'identité du compte est l'entrée de confiance, pas le numéro de
reçu. Un futur parcours doit établir cette autorité sans permettre de
choisir un compte arbitraire ni révéler son existence.

### Refus et reprise

- Un compte suspendu ou confirmé ne produit ni journal ni reçu. Un
  reçu d'un autre compte est refusé sans révéler son état.
- Un lien historique valide suspend la voie nouvelle (`legacy_active`).
  Aucun renvoi ni rotation de secours. Le parcours historique reste
  inchangé par ce lot ; ne pas l'appeler automatiquement depuis cette
  voie lors de la bascule. L'échéance historique passée, réserver
  seulement si la génération courante est déjà active et reproductible.
- Génération absente, expirée, révoquée ou non reproductible : refus,
  aucun remplacement ou prolongement. Un journal ancien sans reçu
  associé n'est pas une preuve de réservation et bloque ; ne pas
  l'adopter automatiquement.
- La réservation est `recovery_reserved`, laquelle n'est pas
  revendicable par le cycle générique. Aucun transport, tâche en file
  ni clé réelle. Ne pas convertir ce nouvel état en `reserved` :
  le prochain incrément devra vérifier sous verrou le lien journal-reçu,
  la version active et l'éligibilité juste avant revendication, et
  traiter la décision de remise et son audit sous le contrat validé.
  La preuve de non-écrasement ne dispense ni de cette vérification ni du
  drainage des anciens émetteurs.

Candidat V5 **sans activation** : chaîne 3594a06 → codec → persistance →
lecteur mixte → ce lot, schéma et tests inclus, sans route, alias réel
de clé, configuration ni worker. Les migrations restent non exécutées
sur une base existante. Pour un rollback applicatif, conserver les
colonnes/tables du journal ; les down ne sont que la preuve sur base
jetable et retireraient l'audit de récupération.

### Recette

**40 tests / 279 assertions** sur MariaDB privée : deux processus
obtiennent une seule réservation ; un INSERT de reçu délibérément
rejeté par déclencheur de fixture annule l'intention déjà insérée ;
le nouvel essai est possible sans écraser `unknown`. Refus compte
tiers/suspendu, avant délai, historique et échéance de génération.
Aucune modification de l'échéance ni fuite de contenu de lien dans
le journal ; le claim générique ne revendique pas le nouveau reçu.
Montée/retour des migrations uniquement sur la base jetable ; base
et réseau retirés, Compose, conteneur, PHP lint et diff contrôlés.
Aucun mail réel, nouveau paramètre, push, merge ou déploiement.

## Claim spécifique, non raccordé

`VerificationRequestLifecycle::claimRecovery()` est l'unique chemin qui
peut passer `recovery_reserved` à `dispatching`. Il utilise la possession
existante (secret bref, uniquement son empreinte en base), mais exige en
plus sous verrou du compte :

- compte en attente, actif ; liaison unique du nouveau reçu au journal,
  même clé de demande et version présente ;
- reçu initial du même compte toujours résolu/indéterminé ;
- génération courante active, version attendue, algorithme connu, échéance
  future ; aucun lien historique encore valable.

Avant toute possession, un prérequis manquant annule seulement le nouveau
reçu (`cancelled`), sans prétendre qu'un envoi ambigu a échoué. Si le
reçu porte des traces incohérentes de possession, son état devient
`unknown` et il n'est pas repris. Aucun
transport n'est possible avant commit réussi du claim. Le bail ne dépasse
pas l'échéance du lien et, une fois expiré, l'état devient `unknown`, jamais
remis en file ou réécrit en succès. Le reçu initial n'est jamais modifié.
Un claim concurrent perd sans obtenir de secret. Le résultat ne contient
que la version et le secret de possession, aucun lien, adresse, clé ou nonce.

Pas de migration, clé, adaptateur applicatif, worker ou chemin HTTP dans
ce palier. Les clés n'interviennent pas dans la revendication, qui n'émet
aucun lien. Le futur adaptateur doit produire le lien uniquement après
vérification du claim et de la même génération, et distinguer la
non-remise certaine d'un résultat ambigu. Un verrou SQL de claim n'arrête
pas un transport déjà engagé : il faut encore décider le contrat pour une
révocation volontaire qui survient après le claim, drainer les anciens
émetteurs, et choisir l'audit de remise et la politique de clés. Ne pas
relier ce palier à V5 si sa recette est figée.

## Adaptateur de remise interne, non raccordé

Le port `VerificationRecoveryTransport` n'a pas d'implémentation réelle.
`VerificationRecoveryDeliveryAdapter` ne possède ni clé, ni destinataire,
ni accès applicatif. Les trois états de son port sont : accepté par
transport (pas reçu), non remis avec preuve positive, ou indéterminé.
Une exception ne prouve jamais l'absence de remise ; aucun message
d'exception du transport n'est répété par l'adaptateur.

La migration additive `Version20261120000500` ajoute au journal le point
de remise, l'issue et sa date. Le claim récupération passe à un état
distinct `recovery_dispatching` : le cycle générique ne peut pas annoncer
un succès à sa place. Sous le verrou compte, la préparation vérifie le
claim courant, sa lease, le journal lié, le reçu initial indéterminé et
la génération active de la même version. Une génération révoquée avant
cette préparation empêche le passage au transport et produit une annulation
auditée `not_dispatched`. Le résolveur factice reconstruit le même lien,
sans réécriture d'échéance. Seul le premier appel inscrit et commit le
point de remise ; aucun autre détenteur du même claim n'obtient le lien.

Le transport reçoit le lien **hors transaction et après commit**. Une
révocation qui survient après ce point, y compris dans l'intervalle
avant l'appel effectif au transport, rend le lien invalide mais ne permet
pas d'empêcher ni d'annuler cette remise. Ne pas annoncer « aucun mail »
dans ce cas. Un arrêt après le point de remise, ou un audit d'issue qui
échoue après un retour de transport, ne réarme jamais le reçu. Après
expiration de la lease, seule l'issue indéterminée est écrite ; aucun
résultat tardif ne peut devenir un succès. L'issue et l'état du nouveau
reçu sont réglés ensemble, sans modifier le reçu initial. Un audit ne
contient ni lien, empreinte du lien, nonce, adresse ou clé.

Ce lot reste sur une branche distincte de V5. Aucun point d'entrée,
configuration, clé réelle, SMTP ou changement public. Une activation
nécessiterait la preuve d'identité/autorisation du parcours, la politique
de clé, la coordination des autres émetteurs, un contrat de révocation
assumé et la revue de traces du vrai transport. La décision de remettre
n'est pas une preuve de livraison, et le marqueur durable n'établit pas
précisément quand un transport externe a commencé.
