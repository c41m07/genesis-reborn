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
