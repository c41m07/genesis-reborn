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
