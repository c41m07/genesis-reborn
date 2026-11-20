# Pilote local jetable : inscription et confirmation V12

Cette fiche remplace la cible préproduction supposée : le propriétaire
teste localement et n'a pas d'instance stage. Le candidat vient de
V12 code `3ca1020c3c98b2acd8da81658609b1431b5d0df3`, bilan
`e64cf2cf3ba988213016507f39908d8b7addf285`; aucune bascule de la branche
dev, Alpha ou prod. La fiche de préproduction précédente reste une
référence de limites, pas un prérequis à ce pilote local.

## Résultat observé

**1 test, 26 assertions réussis**, avec un véritable échange SMTP
interne vers un **MailHog dédié et jetable**, pas Mailpit (aucune image
Mailpit en cache). Le conteneur utilise l'image MailHog déjà disponible,
sans pull. Ce n'est ni le MailHog partagé, ni une remise Internet,
ni la preuve d'un SMTP production, de TLS ou de réception en boîte réelle.

- Réseau Docker inspecté interne, un seul réseau raccordé à chaque
  conteneur, zéro port publié. MariaDB vierge en tmpfs, capture MailHog
  en mémoire. Aucun volume de données ni projet existant partagé.
- Les vrais contrôleurs Symfony ont servi l'inscription et la
  confirmation, via KernelBrowser dans le processus de test (pas de
  parcours TCP du navigateur). Une adresse de domaine réservé aux
  tests, un seul compte. Le port SMTP a réellement déposé un mail de
  confirmation et une bienvenue distincte, sans lien dans celle-ci.
- Le lien MIME n'a été lu qu'en mémoire puis soumis au vrai formulaire.
  GET ne confirme pas. POST avec CSRF confirme et consomme une fois ;
  rejeu refusé. Aucun renvoi admin ni inscription additionnelle.
- Clé de génération fictive créée dans le processus, aucune valeur
  enregistrée dans la configuration de l'utilisateur ni affichée.
  Aucune copie du lien, du MIME ou de la capture réseau conservée
  dans le bilan. Les logs proxy réels n'ont pas été audités.
- Nettoyage vérifié après fermeture : processus de test éphémère,
  conteneurs DB/capture supprimés, réseau supprimé ; aucune trace de
  ces objets dans les listes Docker. La clé et les données jetables
  ne survivent pas. Ce n'est pas un rollback testé de production.

## Conditions d'usage et paramètres

Depuis un worktree distinct basé sur le bilan V12 et cette recette.
Exiger des images **déjà en cache** `mariadb:11.8`,
`local/genesis-web:dev` et, pour le vrai SMTP privé,
`mailhog/mailhog:latest`. La variante en mémoire ne demande pas
d'image MailHog. Ne pas installer, télécharger, publier un port
ou réutiliser le projet dev. Les dépendances PHP et assets locaux
déjà construits peuvent être montés en lecture seule.

- Variables de recette : `GENESIS_LOCAL_ACTIVATION_PILOT` (opt-in
  explicite du test), `GENESIS_LOCAL_ACTIVATION_CAPTURE` (choix entre
  SMTP privé et capture Symfony en mémoire). Sans opt-in, le test
  est ignoré avant toute préparation, vérifié dans un conteneur sans
  réseau ; la variante mémoire
  n'appelle aucun SMTP. Le SMTP privé utilise uniquement l'alias
  fixe du conteneur de capture, sans DSN configurable.
- Variables injectées seulement dans le processus de test :
  `EMAIL_VERIFICATION_MIXED_READER_ENABLED`,
  `EMAIL_VERIFICATION_VERSIONED_WRITER_ENABLED`,
  `ADMIN_ACTIVATION_ENABLED`, `ADMIN_ACTIVATION_RECOVERY_ENABLED`.
  Les deux premières sont exercées par le test et l'admin reste fermé.
- Variables du conteneur jetable DB : `MARIADB_ROOT_PASSWORD`,
  `MARIADB_DATABASE`, `MARIADB_USER`, `MARIADB_PASSWORD`. Utiliser
  exclusivement les identifiants synthétiques du bootstrap de tests,
  jamais ceux du projet habituel. `MH_STORAGE` sélectionne la
  mémoire de la capture. Le test monte le modèle d'env **versionné**,
  pas un `.env.local` utilisateur ; le mailer historique est un
  double interdit d'appel. Aucun coffre ni secret réel requis.

## Rejouer dans l'ordre, sous réserve du slot privé

1. Vérifier que les noms réseau/conteneurs ci-dessous n'existent pas et
   qu'aucune recette de test ne détient le créneau Docker. Ne jamais
   supprimer un projet trouvé préexistant. Réserver les identifiants
   des objets créés par ce lancement pour le nettoyage ciblé.
2. Créer le réseau `genesis-activation-pilot-local` avec
   `docker network create --internal`. Inspecter et exiger
   `Internal=true` avant de démarrer les services. **Si non, arrêter.**
3. Démarrer MariaDB seule avec `--pull=never`, `--network` de cet
   essai et `--network-alias database-test`, stockage
   `--tmpfs /var/lib/mysql` et aucun port publié. Attendre la
   santé de l'InnoDB. Au démarrage du test, hôte et nom de DB jetable
   sont vérifiés ; une base non vierge entraîne un refus et aucune
   suppression.
4. Pour le SMTP privé seulement, démarrer MailHog nommé
   `genesis-activation-pilot-mail` sur le même réseau, alias
   `pilot-capture`, `--pull=never`, stockage mémoire, aucun port
   publié. Par `docker inspect`, confirmer aucune publication
   et un seul réseau pour les deux services. L'API de capture
   est utilisée uniquement depuis ce réseau. Pas de téléchargement
   si l'image manque : préférer la variante mémoire du test,
   et la décrire comme factice.
5. Exécuter un conteneur PHP `--rm --pull=never` sur le réseau
   interne, avec `--tmpfs /app/var` et les montages en lecture
   seule `app:/app`, `.env.example:/app/.env`, dépendances
   `/app/vendor` et assets `/app/public/build`. Injecter seulement
   les variables d'usage ci-dessus et exécuter depuis `/app` :

   ```sh
   php bin/phpunit --do-not-record-test-run-history tests/Controller/LocalActivationPilotTest.php
   ```

   Pour le parcours SMTP, le mode de capture est `smtp-private` ;
   sinon ne pas définir ce mode. L'opt-in explicite du test est
   requis dans les deux cas.
6. Après réussite **ou échec**, arrêter et supprimer uniquement
   les deux conteneurs détenus et le réseau privé. Vérifier par
   leurs noms/identifiants qu'ils ne sont plus présents. Ne pas
   lancer `docker system prune`, un down global ou toucher aux
   projets partagés. Ne pas conserver de messages, mots de passe,
   lien, clé, HAR ou trace brute ; rapporter seulement les états
   et nombres observés.

**No-go** : réseau non interne, port publié, base non vierge,
capture/dépendance absente sans fallback, host SMTP différent, mode
admin visible, contenu ou destinataire inattendu. Ne pas compenser
par un SMTP externe. Le vrai pilote d'une boîte contrôlée à distance
ou d'une préproduction, si un jour demandé, reste hors de ce test.
