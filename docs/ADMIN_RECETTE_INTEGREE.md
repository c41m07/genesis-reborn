# Recette intégrée locale du panneau administratif

## Assemblage et périmètre

Branche locale recette/admin-integration dans un worktree distinct, créée depuis
origin/dev 8be1fd9 après fetch. Aucun changement du checkout utilisateur, aucun
push, fusion dans dev, déploiement ou correction des six points d’audit produit.

Références assemblées :
- Catalogue : 89c10342d0eb25e21d306cde33a1d4d4fd520259.
- Support comptes et fondation réservation : 6c93274b5bd5cc5f61a1177654b613977bd2171f.
- Permissions : 78763ba, incluant 340b7cc.
- Exploitation : ed9277b.
- Guildes : 2815647.
- Accessibilité recherche : 3ed5378.

L’assemblage de code terminé est 7ba6598. Les commits sources sont conservés,
pas réécrits. Les conflits documentaires correspondent à des ajouts indépendants,
tous conservés. Dans AdminPlayerController::show, l’attribut PLAYER_VIEW et
l’injection du diagnostic support ont été conservés ensemble après validation
de cette résolution. Le layout garde les routes permissions et guildes dans
leurs espaces de présentation, sans modifier les contrôles serveur.

## Vérifications réussies

- make configure puis make validate dans le nouveau worktree.
- make assets : compilation réussie (bundle app annoncé 738 KiB).
- Six tests Chromium clavier, à 1366 et 390 px, sur fixture du vrai contrôleur.
- Suite ciblée intégrée : 129 tests PHP / 1 110 assertions, 76,483 secondes.
  Contrôleurs, intégration et unités admin, catalogue, comptes, confirmation,
  réinitialisation, alertes et réservation.
- Droits anonyme/joueur/admin, fiche et diagnostic support, matrice de capacités,
  interventions, guildes et isolation, opérations, historique/restauration.
- Le test de migration Version20261119235900 recrée la table puis utilise le
  service de réservation dans la seule DB jetable ; aucun schéma réel touché.
- Le test de réservation couvre deux processus/connexions simultanés, avec
  clé identique puis différente. Il ne prouve pas le transport d’email.
- 64 templates Twig et injection du conteneur valides.
- git diff --check réussi.

Tests dans genesis-admin-integration-test, MariaDB dédiée, caches tmpfs,
vendor partagé en lecture seule, assets recompilés du worktree intégré.
Les erreurs 404/405 et account_mail.failed imprimées sont les scénarios négatifs
attendus ; la suite finale est verte. Base et réseau privés supprimés.
Le lien temporaire node_modules vers les dépendances existantes a été retiré,
sans toucher aux dépendances du checkout utilisateur.

## Limites et suite

C’est une recette ciblée intégrée, pas la CI complète ni une revue globale.
Pas de recette navigateur de toutes les pages ou de conformité RGAA complète.
Le fichier généré app/config/reference.php peut rester signalé modifié après
le démarrage Symfony ; il est exclu du commit et n’a pas été restauré.

Le panel complet n’est pas déclaré terminé :
- renvoi administratif non raccordé aux routes/transport ; fondation seulement ;
- aucune délégation de rôle activée ; quatre capacités pilotes ;
- reprise ciblée des traitements différés non ajoutée ;
- restauration catalogue limitée aux nouvelles versions complètes compatibles,
  sans restauration d’états joueurs ni publication groupée ;
- revue finale du dépôt à effectuer séparément et en lecture seule.

Aucune migration sur une base existante n’est autorisée par cette recette.
La CI complète et les validations requises par AGENTS restent nécessaires
avant toute proposition de fusion.

## Recette V2 locale : cycle support et orientation des relais

La référence verte b3bc705 reste conservée sur recette/admin-integration.
La branche recette/admin-integration-v2 utilise le même worktree dédié et
assemble localement, sans réécrire les sources :
- support fa996dd35941ea681fd51115436bb2138b08900f, incluant 1475301 ;
- exploitation 4d0cb811f63e8a74e3f6788a8d922e241fa1f9eb ;
- assemblage de code V2 : 7838ab6.

Seul conflit supplémentaire : ajouts indépendants du README, tous conservés.
Aucun changement de permission ni arbitrage de règle métier dans cette recette.

Vérifications de la V2 :
- Compose valide ; suite admin/catalogue/comptes intégrée : 136 tests,
  1 173 assertions en 80,728 secondes.
- Diagnostic outbox additionnel : 2 tests, 120 assertions.
  Total des deux exécutions PHP distinctes : 138 tests, 1 293 assertions.
- Les migrations Version20261119235900 puis Version20261120000100 sont
  exécutées ensemble dans le test gardé sur database-test/genesis_v2_test.
  La réservation est utilisable après ces deux migrations. Aucun schéma réel
  n’est modifié ; ceci ne vaut pas répétition d’une migration de production.
- Concurrence entre processus, propriété du reçu, expiration sans remise en
  file et interruptions avant/après faux transport sont couvertes sans envoi.
- Lint PHP global src/tests/migrations, 80 fichiers YAML, 214 templates Twig,
  injection du conteneur, diff : réussis.
- 13 tests Python de sauvegarde réussis. Les états failed/timeout imprimés
  sont les fixtures attendues, pas un contrôle de sauvegarde réelle.
- Base et réseau privés genesis-admin-integration-test supprimés à l’issue.

La compilation des assets et les six tests clavier de la recette initiale
restent le dernier passage frontend : ces sources n’ont pas changé, ils ne
sont pas annoncés comme rejoués en V2. Le template diagnostic modifié est
couvert par les tests HTTP et le lint, sans nouvelle recette visuelle.

Le périmètre complet reste ouvert : renvoi administratif non raccordé,
délégation inactive, aucune reprise/replay de traitement réel, CI complète
non exécutée et revue globale différée en lecture seule. Aucun push, fusion
dans dev, déploiement ou correction audit. Le fichier généré reference.php
reste exclu et intact. La référence b3bc705 demeure disponible indépendamment
de cette nouvelle recette.

## Complément navigateur V2 et préparation de publication

Dev distant vérifié par git ls-remote : 8be1fd9bbc574c5839d1d9a72874ac9bceb164a6,
inchangé au moment de cette recette. Aucun fetch forcé ni modification de dev.

- Six contrôles clavier Chromium rejoués sur V2 : tous réussis (2,4 s),
  à 1366 et 390 px.
- Recette navigateur réelle additionnelle : un parcours réussi (9,1 s)
  à 1366 et 390 px. Compte synthétique inscrit puis vérifié/promu uniquement
  dans la DB jetable. Matrice de permissions, exploitation, diagnostic de
  fiche joueur, historique catalogue vide et retour vers la fiche consultés.
- Comparaison de devis soumise puis recalculée sans sauvegarde. Valeurs
  game_settings et nombre de traces catalogue identiques avant/après.
- Huit captures générées sous app/test-results ; ces artefacts temporaires
  ne sont pas versionnés et pourront être remplacés par un prochain test.
- Premier essai corrigé : URL de test /admin/operations erronée, remplacée
  par la vraie route /admin/exploitation. Ce n’était pas une panne applicative.
- Second essai : une attente de persistance du devis au rechargement a échoué.
  Vérification du contrôleur : le brouillon est consommé au premier GET.
  La recette finale constate sa disparition puis un recalcul réussi.
  Limite UX observée : recharger perd l’aperçu temporaire ; aucune correction
  de comportement n’a été faite dans cette campagne.
- La restauration historique complète n’a pas été parcourue dans Chromium :
  aucune trace compatible préparée dans cette fixture. Sa validation reste
  celle des tests HTTP/PHP précédents, pas une validation navigateur.

Isolation : projet Docker genesis-browser-test-1795126346-220163, app/DB/broker
privés, port loopback 18799, aucun réseau habituel raccordé, mailer null,
pas de worker/relay/notifier. Les 60 migrations ont été appliquées sur la base
vide en mémoire jusqu’à Version20261120000100 avant import synthétique.
Ce parcours n’atteste pas la migration d’une base existante ni le temps réel.
Projet, réseau et lien node_modules temporaire supprimés après test.
Le scénario ad hoc n’est pas ajouté à la CI : il dépend de ce montage privé.

### Avant publication autorisée

1. Confirmer le périmètre accepté et les limites : renvoi non raccordé,
   délégation inactive, aucun replay métier ; ne pas annoncer le panel complet.
2. Réconcilier la branche de publication avec dev distant et les lots locaux ;
   préserver les historiques, refaire les tests concernés après toute évolution.
3. Préparer une PR en brouillon uniquement avec autorisation de publication,
   puis CI complète sur son dernier SHA avant toute fusion.
4. Vérifier sur une copie autorisée la compatibilité des deux migrations,
   sauvegarde/restauration et procédure de retour ; aucune base réelle modifiée ici.
5. Compléter la recette navigateur d’une trace compatible/restauration préparée,
   ainsi que les parcours temps réel requis. La revue globale reste séparée,
   strictement en lecture seule. Les corrections audit restent hors de ce lot.
6. Obtenir l’autorisation distincte de fusion/déploiement ; aucun push réalisé.

## Recette V3 : inspection préalable à la reprise

Branche locale recette/admin-integration-v3, worktree distinct. Assemblage
2e59447 : parents 623959c (recette V2) et a4fb6a0 (inspection en lecture seule).
Aucun conflit dans ce premier assemblage. Branches V1/V2 inchangées, aucun
push, fusion vers dev ou changement de CI.

### Première marche du candidat queue : preuve automatique, sans nouvelle route

Le service existant QueueService::complete traite une demande jetonnée mais
réconcilie tout le membre, toutes colonies et files confondues. Cette portée
est nécessaire à l’ordre chronologique des fins, aux dépendances, aux
consommations de ressources et au démarrage de la recherche suivante.
Ne pas remplacer ce service par un achèvement direct de l’élément.

La prochaine marche consiste à éprouver un unique membre synthétique en
lançant, sur le service existant et sans admin ni transport réel, deux workers
sur le même couple identifiant de commande / génération de dispatch. Prévoir
deux colonies et plusieurs files, une opération liée et une voie de traitement
normale concurrente. Établir une photographie des résultats métier, pas
seulement un compteur d’appels : niveaux/quantités, ressources, commandes
restantes et nouveaux événements. Comparer ordre et résultat à un traitement
ordinaire unique, y compris génération périmée, cible retirée et transaction
annulée. Un succès du test prouverait ce scénario précis, pas un replay.

Une reprise future doit ensuite décider si l’interface indique honnêtement
« réconcilier le membre » ou renonce à une action strictement par élément.
La preuve doit porter sur état courant sous les verrous du métier, jeton et
périmètre inchangés, absence de double effet et séparation entre relai publié,
effet SQL durable et état indéterminé. Un reçu durable lié à la génération
(et pas seulement au clic) et une décision/audit cohérents avec la transaction
sont nécessaires. Une interruption après publication ne doit jamais
déclencher de republication automatique ; tester cette coupure et une écriture
refusée. Conserver droits existants, CSRF, confirmation et motif au futur
raccordement seulement.

Coût estimé : niveau 5/5. Premier incrément de preuves : environ 1–2 jours
de développement et recette isolée ; raccordement sûr conditionnel : 2–4
jours supplémentaires, hors CI complète, selon les écarts de concurrence et
la nécessité d’une migration de reçu. Estimation de travail, pas engagement.
La V3 n’ajoute ni reçu, ni bouton, ni replay, ni changement de règle métier.

### Vérifications et ajout support protégé

Phase diagnostic seule (2e59447) : 140 tests PHP / 1 383 assertions, Twig
admin (65), injection du conteneur et Compose réussis. Six tests clavier
Chromium et un parcours réel d’inspection à 1366 et 390 px réussis : lien
depuis Exploitation, absence de commande, aucun bouton de relance ou jeton,
aucun débordement horizontal, navigation retour/rechargement et relevés
outbox/journal inchangés. Base navigateur jetable migrée en mémoire (60
migrations). Aucun service de transport réel.

Après cette validation, intégration autorisée du support
3594a064fba044731037f2316619983c4b2c2041 (parent fa996dd) en 00919d9.
Conflit de signature sur la fiche joueur : maintien de PLAYER_VIEW ET
des dépendances diagnostic et activation. Activation désactivée par défaut
(ADMIN_ACTIVATION_ENABLED=0) et transport indisponible câblé, sans SMTP.
Aucun incrément de coordination public/admin supplémentaire.

Après intégration support : parcours Chromium d’inspection rejoué et
diagnostic d’un compte non confirmé vérifié dans le montage jetable. Le
formulaire est absent, la route POST retourne 404, aucun reçu n’est écrit.
Recette PHP de régression ciblée : 30 tests / 383 assertions, Twig admin
(65), injection et diff réussis. Les chiffres 140/1383 concernent le SHA
avant cet assemblage, pas le dernier SHA. Les projets navigateur, DB et
réseaux de recette ont été nettoyés. Le fichier Symfony généré reference.php
reste seul modifié, hors commit. Pas de CI complète.

## Recette V4 : aperçu fictif des permissions

Branche chore/recette-integration-admin-v4, worktree dédié. Assemblage
365b12052a66166d8147badbabfab882fdb8c2b6, parents
bc9f97f0228def00fdcdc81f616b37fee9575669 (V3) et
f5934f00436d0e2f3ed4f2fb58463d2a69635b7f (aperçu).
Seuls les conflits additifs README/layout ont été résolus : les entrées
existantes de guildes restent intactes et l’aperçu relève d’Exploitation.
Aucune expérience multi-liens ni évolution de support supplémentaire intégrée.

- Suite PHP ciblée agrégée admin/catalogue, comptes et outbox : 147 tests et
  1 477 assertions en 85,040 s. Lint des 66 Twig admin, conteneur, Compose et
  diff réussis. La suite vérifie aussi l’accès anonyme/joueur/admin.
- Six scénarios de recherche au clavier Chromium réussis, 2,6 s.
- Parcours Chromium réel, 5,8 s : matrice à quatre capacités, lien vers
  l’aperçu, A puis B, rechargement et retour à la matrice, à 1366 et 390 px.
  Aucune autre fixture affichée hors du périmètre actif, aucun formulaire,
  aucun défilement horizontal global ; portée inconnue 404 et POST 405.
  La page annonce explicitement une simulation ; les trois exemples
  n’activent aucun profil. Rôles stockés et total du journal inchangés.
- MariaDB de recette et projet navigateur distincts et jetables, exécutés
  séquentiellement ; 60 migrations sur base navigateur vide, sans SMTP ni
  donnée réelle. Les deux projets et leurs réseaux ont été retirés.
  Les quatre captures de l’aperçu sont des artefacts locaux de test non
  versionnés. Le scénario ad hoc n’est pas ajouté à la CI.

Aucune écriture d’attribution, politique, rôle ou ouverture de donnée réelle.
La projection pure ne lit aucun compte ni univers. La délégation opérationnelle,
le renvoi réel et la reprise métier demeurent non raccordés. Pas de CI complète,
répétition de migration sur base existante, push, fusion dev ni déploiement.

## Recette V5 : socle serveur de récupération

Branche chore/recette-integration-admin-v5, worktree distinct et V4 conservée.
Assemblage ba8ca872881a2aea10d27970b0e8777d41e4eebb, parents
911cb282a310b4be8d670ebb48117e7259402bc5 et
702896cdbd9456ba01efca6afc8387c086db2d12. Chaîne support vérifiée :
3594a06 → f9f6bea → d9b9c4b → 5fed895 → 702896c.
Aucun conflit. Aucun lot multi-liens ou expérimental d’exploitation.
Pas de diff dans configuration, contrôleurs, Twig ou CI : parcours public
inchangé ; activation admin désactivée par défaut et transport indisponible.
Aucune clé d’activation fournie et aucun SMTP réel.

- Suite PHP exhaustive locale : 1 533 tests, 4 444 165 assertions,
  1 échec et 1 ignoré, 11 min 48,672 s. Le seul échec est
  GameplayAccessTest::testNotificationCookieAuthorizesOnlyCurrentMembership :
  le lanceur de test laissait le placeholder Mercure de 232 bits, insuffisant.
- Après passage de la valeur Mercure générée pour le projet jetable, la recette
  agrégée parcours admin/catalogue, comptes, relais et GameplayAccessTest
  est verte : 177 tests / 1 773 assertions en 101,708 s. Le scénario autrefois
  en échec y passe. La suite exhaustive n’a pas été rejouée après cette
  correction de lancement ; elle ne doit pas être annoncée comme verte.
- make validate, make assets (après recréation du dossier build vide dans
  le seul worktree V5), lint PHP global, 216 Twig, 80 YAML, conteneur et
  git diff --check réussis. 13 tests Python de sauvegarde réussis.
- Chromium : six scénarios clavier et un parcours réel de l’aperçu fictif
  à 1 366 et 390 px réussis ; pas de campagne navigateur complète.

### Migrations et synchronisation du schéma

Sur base navigateur en mémoire : 63 migrations jusqu’à Version20261120000400,
sans exécution indisponible. Les trois migrations additives V5 (00200, 00300,
00400) ont été annulées vers 00100, puis réappliquées. Deux tables, deux
colonnes de récupération et l’index unique attendus vérifiés. L’empreinte,
le rattachement et l’expiration d’un lien historique synthétique restent
identiques ; Doctrine confirme zéro migration en attente. La descente DDL
peut supprimer les nouvelles traces : cette recette ne constitue pas une
procédure de rollback de production.

doctrine:schema:validate : mapping valide, base non synchronisée (exit 2).
Comparaison distincte en lecture seule avec l’API SchemaTool : une proposition
commune déjà présente en V4 et une seule supplémentaire en V5, le renommage
de l’index unique existant de verification_recovery_event.recovery_request_id,
de verification_recovery_request_unique vers UNIQ_7A5471A947B645A9.
Pas de colonne, table ou contrainte unique manquante dans le delta V5.
Aucun schema:update exécuté, aucune proposition SQL appliquée. Examiner
l’alignement du nom d’index et l’écart hérité avant de déclarer le contrôle vert.

Bases, réseaux et liens temporaires V5 retirés. Le fichier Symfony généré
reference.php reste hors commit. La sauvegarde/restauration end-to-end
officielle et la CI complète restent à faire, de même qu’une suite PHP
complète avec la configuration correcte. Pas de migration réelle, émission,
rejeu, attribution de droits, push, fusion dev ou déploiement.

### Correctif de mapping et nouveau passage PHP complet

Branche distincte fix/admin-index-recuperation depuis V5 9da06af.
Commit d’implémentation 75c3fedcce99855b49424952dacfd26158ae2b89 :
l’entité déclare le nom de l’index unique que la migration avait déjà
posé. Relation OneToOne, nullable et ON DELETE SET NULL inchangés.
Aucune migration historique réécrite et aucune migration ajoutée.

Sur base MariaDB jetable migrée, une seule contrainte unique nommée
verification_recovery_request_unique couvre recovery_request_id ; le
comportement SET NULL est conservé. La comparaison SchemaTool en lecture
seule ramène V4 et V5 corrigée au même seul écart : la table
doctrine_migration_versions, historique géré par Doctrine Migrations
et non mappé comme entité. Ne pas supprimer cette table. Aucun SQL du
comparateur appliqué et aucun schema:update lancé.

Après transmission correcte de la valeur Mercure éphémère dans le lanceur
privé, le passage exhaustif est réussi : 1 533 tests recensés, 4 444 168
assertions, aucun échec, un test ignoré (mesure optionnelle activable par
REWORK_MEASURE=1), en 11 min 41,713 s. Les assertions d’idempotence du
socle de récupération sont incluses (réservations répétées et concurrence
limitée). Lint PHP global, 216 Twig, 80 YAML, conteneur et Compose réussis.
Toutes les bases et réseaux temporaires de cette étape ont été retirés.

La suite navigateur exhaustive, la vérification officielle de
sauvegarde/restauration de bout en bout et la CI complète restent à faire.
Le contrôle de schéma générique peut toujours voir l’historique non mappé ;
ne pas l’annoncer vert. Les preuves navigateur/actifs de V5 ne sont pas
annoncées comme rejouées pour cet attribut de mapping. Aucun claim support
ultérieur inclus, aucune clé d’activation, SMTP, modification de CI, réelle
donnée, fusion, push ou déploiement.

### Sauvegarde/restauration séquentielle et limite navigateur

Référence de code testée : a261395 (V5 corrigée), aucune modification
des scripts du dépôt. La suite navigateur exhaustive officielle n’a pas
été lancée. Son Compose branche l’app et Mercure sur Traefik partagé
publié sur toutes les interfaces au port 80. Ses fixtures attendent
ce routage et certaines appellent 127.0.0.1:80 directement. Le faire
fonctionner à l’identique sur un port loopback dédié exigerait de modifier
le montage ou le lanceur ; aucun changement de routage ni de CI.
Le dernier navigateur local reste la preuve ciblée V5 (6 clavier + 1 parcours).

L’enveloppe officielle make test-backup a été tentée et s’arrête avant
migration, dump ou restauration : Docker ne permet pas le second réseau
simultané, « all predefined address pools have been fully subnetted ».
Aucun réseau existant ni pool modifié ; nettoyage du test contrôlé.

Une variante locale séquentielle a conservé scripts/db-backup.sh et
scripts/db-restore.sh et leur contrôle RESTORE_CONFIRM. Elle n’est pas
l’enveloppe officielle. Avec deux noms de projet jetables distincts,
sans port exposé, sans Traefik et sans consommateur :
- source synthétique migrée et alimentée avec les fixtures du test ;
- archive privée gzip vérifiée, empreinte de la source inchangée après
  sauvegarde, puis source et réseau retirés avant de créer la cible ;
- restauration uniquement sur la cible jetable portant la confirmation
  de son nom ; la table marqueur de la cible n’est pas dans l’archive ;
- **63 tables identiques** par empreinte ; compte synthétique, mot de
  passe, stocks, niveaux de bâtiment/recherche, vaisseaux, construction
  en attente et outbox vérifiés après restauration ;
- aucun consommateur ou SMTP, bases/networks/archives temporaires retirés.

Le résultat atteste un aller-retour synthétique local, pas un résultat GitHub,
une recette de données réelles ni un succès de l’enveloppe simultanée.
Aucun push, fusion ou déploiement. Le code et la référence a261395 sont
préservés.

## Recette V6 : remise de récupération sans transport réel

Branche chore/recette-integration-admin-v6, worktree distinct.
Assemblage 9c6f8704fc5dd0f0c617c5aeb40d81483c4bf18b :
96d6a6ce8b1022e5e68b06dabc9f7a58fee07b75 (V5 corrigée et recette sauvegarde)
et ab7ae053f10354bfca95552b66f4bcb0adf02f14 (preuve de bout en bout).
Chaîne vérifiée depuis 702896c : 7a3c20c, 9797e50, a298f64, ab7ae05.
Pas de conflit, l’index explicite V5 reste présent. Ni prototype exploitation
ni multi-liens. Aucun changement de configuration, contrôleur, Twig ou CI ;
activation admin indisponible par défaut et parcours public inchangé.
Le nouveau transport et le fournisseur de clés ne sont pas raccordés.

- E2E synthétique seul : 1 test / 30 assertions en 1,669 s. Recette choisie
  agrégée support/panneau/droits : 198 tests / 1 949 assertions en 115,929 s,
  comprenant l’E2E. Lints PHP, 216 Twig, 80 YAML, conteneur, Compose, diff OK.
- Chromium réel synthétique sur loopback dédié : 1 scénario à 1 366 et 390 px,
  8,1 s. Compte non confirmé, renvoi admin indisponible, aucun formulaire,
  POST 404, aucun reçu ni événement créé ; matrice et aperçu fictif intacts.
  Aucun Traefik partagé, transport ni clé réelle.
- Base neuve migrée : 64 migrations jusqu’à 00500. Descente à 00400 retirant
  les trois champs de remise, puis remontée à 00500 ; 64 migrations et
  aucune restante. Empreintes des liens historiques synthétiques inchangées.
  Le comparateur read-only ne propose toujours que l’historique Doctrine
  non mappé ; cette table est conservée et aucun schema:update n’a été lancé.
- Les projets PHP et navigateur privés ont été retirés après vérification.

| Prêt localement | À sécuriser avant activation | Preuves manquantes |
| --- | --- | --- |
| Diagnostic, aperçu fictif et socle serveur : réservation, remise unique, résultat audité, lecteur mixte en E2E factice. | Valider un fournisseur de clés et son cycle de rotation, raccorder un transport autorisé, maintenir neutralité publique et désactivation par défaut, documenter les incidents sans réessai automatique. | Pas de preuve SMTP ou de réception réelle, ni de raccordement public/admin, ni de migration sur copie de base existante. |
| Recette ciblée PHP/Chromium V6, migration 00500 aller-retour, sauvegarde séquentielle et suite PHP complète sur la seule V5 corrigée. | Préparer CI de la version publiée, politique de rollback applicatif préservant l’historique, contrôles d’accès et d’alerte avant ouverture. | PHP exhaustif V6 non rejoué, navigateur officiel exhaustif et enveloppe sauvegarde à deux réseaux toujours non validés localement, CI GitHub non exécutée. |

Aucune délégation opérationnelle, reprise métier ni activation réelle.
Aucun push, fusion dev, déploiement, utilisation d’une base réelle ou SMTP.

## Recette V7 : contexte protégé du formulaire d’activation

Branche chore/recette-integration-admin-v7, worktree dédié.
Assemblage 9a32dcbe7ab86f866a0d7afcd98ae9baa1a87136, parents directs V6
cb81b0f9fa65f017685282d85b4d2cdd2baa971f et support
b3e1c2ec227359fdcc06e74d7d9a936fef276a13, chaîne vérifiée
e319179 → 1647a49 → 56dfb087 → b3e1c2e. Aucun conflit.

Le formulaire conserve une clé opaque ; sa variante contextuelle est liée
à la session, à l’acteur, au compte et au reçu attendu, avec fenêtre de
120 secondes. Le contrôle partagé du compte administrateur sous verrous
vérifie rôle courant, adresse confirmée et absence de suspension. Ce contexte
de session n’accorde aucun droit à lui seul. Aucune nouvelle route, migration,
permission, clé ou transport concret ; la configuration lie toujours
l’interface au service par défaut, désactivé par défaut et avec transport
indisponible. Variante contextuelle active uniquement dans la substitution
de test. Aucun prototype de relance ou de délégation intégré.

- Recette formulaire/permissions : **10 tests, 161 assertions** (6,233 s).
  HTML du formulaire, clé sans identifiant de reçu ni lien exposé,
  refus sans confirmation et CSRF invalide, refus d’une clé empruntée
  avec CSRF valide dans une autre session, refus à l’expiration et
  rechargement du formulaire, remise factice unique après confirmation.
  La seconde soumission ne renvoie rien ; le reçu initial reste inchangé.
  Le service désactivé répond 404 et ne crée aucun reçu.
- Recette ciblée agrégée : **204 tests, 2 008 assertions** (115,262 s),
  incluant comptes, droits, récupération en concurrence, panel, catalogue
  et diagnostic. Lint PHP, 216 Twig, 80 YAML, conteneur, Compose et diff OK.
- Un seul projet MariaDB jetable pour les tests, supprimé après exécution.
  Les vérifications d’interface sont des tests HTTP/DOM ; Chromium n’est
  pas annoncé comme rejoué en V7. Aucun SMTP ou configuration réelle.

La suite PHP exhaustive (1 554 tests, 4 444 352 assertions, mesure
optionnelle ignorée) reste une preuve de V6 seulement. La recette navigateur
officielle et la CI GitHub ne sont pas acquises sur V7. Aucun push,
fusion dev, déploiement, transport réel ni ouverture des droits.

## Recette V8 : composition inactive et aperçu défensif

Branche chore/recette-integration-admin-v8, worktree distinct.
Assemblages fd18ad6435b3e71edc594d0672cdc19adb43af48 et ccf52480688b0ef2323f5c16f2a787710fffdfe0 ; seuls les parents directs
de V7 58484375e98e4b042dc30f6f1ba346cbbcaf8a83, du support
cdcf9b2b7301b4206d4f836794e7857dadcb5e3a et de l’aperçu
a2aeedf1b7985e4e76a18a419bde5a42bfe99b4b sont inclus. Aucun conflit.

Composition : les deux bascules versionnées sont à 0. Le fournisseur
livré est null, son type exclu de l’autodécouverte, et le transport du
chemin ordinaire reste indisponible. Aucun fournisseur de clé, destinataire,
secret d’activation ni SMTP réel configuré ou utilisé. Un opt-in récupération
sans le fournisseur complet ferme aussi le formulaire, sans reprendre la
voie ordinaire. Le parcours public, les routes, le voter et le schéma
n’ont pas changé. Aucun droit ni rôle créé ou attribué.

- HTTP ciblé/permissions : 14 tests et 179 assertions (8,011 s). Absence
  de fournisseur, impossibilité d’en monter une moitié seulement, refus
  en configuration incomplète, confirmation, CSRF, fraîcheur et remise
  unique avec fournisseur de test uniquement.
- Agrégée PHP : 210 tests et 2 068 assertions (116,450 s). PHP, 216 Twig,
  80 YAML, conteneur, Compose et diff verts. Les erreurs attendues des
  scénarios de refus n’ont conduit à aucun échec.
- Chromium réel ciblé : un scénario (9,8 s) sur 1 366 et 390 px. Renvoi
  indisponible, pas de formulaire, POST 404, aucun reçu ou événement ajouté.
  L’aperçu distingue les trois refus et le statut du cas actif par univers
  fictif, sans statut d’un univers refusé, formulaire ni attribution.
- Base navigatrice neuve à 00500 (64 migrations), aucun Traefik partagé.
  Bases et réseaux de recette successifs et liens temporaires nettoyés.

### Entre la candidate locale et la mise en service

Pour publier une version encore désactivée : valider la cible et le diff
final, autoriser la publication séparément, exécuter la CI du dernier SHA,
vérifier les migrations et le retour applicatif sur une copie autorisée
avec sauvegarde/restauration. La suite PHP exhaustive sur V6 n’est pas une
preuve de V8 ; navigateur exhaustif, sauvegarde officielle à deux réseaux
et CI GitHub n’ont pas été rejoués pour V8.

Pour ouvrir le renvoi : décider explicitement du fournisseur de clés, de
la conservation et rotation des générations et du transport autorisé ;
préparer un essai de préproduction avec comptes destinataires autorisés,
journal et alertes sans secret, preuve d’acceptation distincte de la
réception, exercice du délai, de la révocation, des coupures et du mécanisme
d’arrêt sans duplicata ni effacement de l’historique. Ne pas allumer les
bascules avant ce contrôle. Aucune activation par ce lot. Toute évolution
du parcours public reste un chantier distinct.

Pour une vraie délégation, la simulation ne remplace pas l’accord sur
délégants, bénéficiaires, données, durée, révocation et audit. Le diagnostic
d’exploitation reste sans relance. Aucun push, fusion dev, déploiement,
CI modifiée, SMTP ou donnée non synthétique.

## Recette V9 : lecteur mixte désactivé, liens historiques conservés

Branche chore/recette-integration-admin-v9, worktree distinct.
Assemblage e44514527725c000214ce437162cf679521de555, parents exacts
99f57429c960b7cc160c5c24b732e8772ad2f765 (V8) et
6de3fa3e31fa971c4f945a6f62c4ad84ea12632d (lecteur et journal).
Aucun autre lot, conflit, nouvelle route, migration ou modification CI.

- Le lecteur mixte est à 0 dans la configuration versionnée ; le POST
  historique reste le défaut. Le GET ne confirme rien. Fermeture par défaut
  et compatibilité historique vérifiées avec compte suspendu ; la suspension
  demeure après confirmation. La recette du lecteur activé en test confirme
  aussi cette compatibilité et refuse les générations suspendues, révoquées,
  ambiguës, expirées ou déjà utilisées.
- Test du RouterListener : le contexte de la confirmation n’envoie ni URL
  ni paramètres au journal applicatif de routage ; les diagnostics des
  autres routes sont conservés. Le conteneur compilé confirme l’injection
  de VerificationRouteLogRedactor dans RouterListener. Le lien n’est
  pas rendu dans le HTML testé ; no-store et no-referrer sont conservés.
  Cela ne prouve pas la confidentialité des accès Apache/proxy, APM,
  historique navigateur ou journaux d’infrastructure, à examiner avant
  toute ouverture réelle.
- Recette confirmation/journal : 11 tests et 115 assertions (10,106 s).
  Agrégée support/panneau/droits : 215 tests et 2 141 assertions
  (132,021 s). PHP, 216 Twig, 80 YAML, conteneur, Compose et diff verts.
- Chromium réel ciblé, 1 scénario aux largeurs 1 366 et 390 px (6,4 s),
  sur compte suspendu synthétique : GET sans effet et en-têtes vérifiés,
  POST historique réussi, suspension conservée, seconde soumission 400.
  Base jetable neuve à 00500 (64 migrations déjà existantes) et loopback
  privé sans Traefik partagé. Bases/réseaux successifs nettoyés.

Pas d’écrivain versionné ni de transport réel ajouté, aucune vraie clé.
La suite PHP exhaustive reste un résultat de V6 seulement. Navigateur
exhaustif, CI GitHub, activation, push, fusion dev et déploiement non faits.


## Recette V10 : émission versionnée désactivée par défaut

Branche chore/recette-integration-admin-v10, worktree distinct. Assemblage
00a354ee963cbba7ff6ba2103da0466730a90ea1, parents 804369c et 89b1203
(avec 3dca301). Aucun autre lot.

- Les routes inscription/renvoi gardent le comportement historique tant que
  l’écrivain est désactivé. Les deux bascules publiques valent 0 et le
  fournisseur versionné est absent de la configuration livrée. Pas d’envoi
  versionné automatique, ni de modification de droits ou de route. Les
  envois historiques déjà prévus par les formulaires ne sont pas supprimés.
- Dans les tests HTTP, une configuration incomplète refuse l’émission,
  sans repli historique ni génération/reçu. Avec transport fictif, le
  même lien peut être renvoyé après le délai ; l’absence d’accusé n’autorise
  pas une seconde remise ambiguë. Après remplacement de l’adresse, l’ancien
  lien est refusé et le compte reste non confirmé. Cette preuve ne couvre pas
  le retour à l’adresse initiale (A vers B vers A).
- MariaDB jetable : 64 migrations jusqu’à 00500, montée 00600, descente puis
  remontée. Les trois nouvelles colonnes apparaissent uniquement à 00600 ;
  le lien historique synthétique est inchangé, 65 migrations au dernier état.
  La descente technique retire les colonnes versionnées : ce n’est pas une
  recommandation de rollback sur des données réelles. La suite HTTP
  reconstruit son propre schéma sur une seconde base jetable séquentielle.
- 18 tests ciblés / 192 assertions, 227 agrégés / 2 254 assertions.
  Lints PHP, 216 Twig, 80 YAML, conteneur, Compose et diff verts. Le
  conteneur et le réseau privés sont retirés.

Candidat local seulement. Pas de fournisseur, de clé ou de SMTP réel, de
test Chromium supplémentaire, de suite exhaustive V10, de CI GitHub, de
push, de fusion dev ni de déploiement. La recette PHP exhaustive citée
précédemment concerne V6 uniquement.


## Recette V11 : révocation après changement d’adresse

Branche chore/recette-integration-admin-v11, worktree distinct. Assemblage
04674aa94afd3733168f35b1f071aabd61a8b689, parents V10 (12a0288) et
f34e554. Aucun autre lot.

- Sur fixtures, une génération révoquée lors d’un changement d’adresse ne
  redevient pas utilisable quand l’adresse initiale revient. Les tests
  vérifient aussi le verrou concurrent, le retour arrière atomique
  (adresse et état du lien conservés), et l’anonymisation (génération
  révoquée, suppression des jetons historiques inchangée).
- Formulaires et mode historique vérifiés de nouveau ; les bascules restent
  désactivées et aucun fournisseur versionné n’est configuré. La preuve
  d’ancien schéma reste verte. Aucun changement dans les migrations, les
  routes, les permissions ni la CI. L’aller-retour de 00600 reste la preuve
  V10, portant sur le même fichier de migration.
- Tests ciblés : 58 / 485 assertions. Agrégée support/panneau/profil :
  240 / 2 317 assertions. Lints PHP, 216 Twig, 80 YAML, conteneur,
  Compose et diff verts. Base et réseau jetables retirés.

Recette locale seulement. Pas de Chromium ou PHP exhaustif V11, CI GitHub,
transport réel, clé, push, fusion dev ni déploiement. Ce lot ne met pas en
service l’émission ou la récupération d’activation.


## Recette V12 : adaptateur e-mail testé, activation toujours fermée

Branche chore/recette-integration-admin-v12, worktree distinct. Code
3ca1020c3c98b2acd8da81658609b1431b5d0df3, parents 6a910cc (V11) et
1f96fb2 (support). Aucun autre lot produit.

- L’adaptateur Symfony est vérifié avec transport factice et transport nul.
  Le transport versionné n’est pas appelé en configuration par défaut :
  écrivain et lecteur versionnés restent désactivés, fournisseur absent,
  aucun vrai secret ni DSN utilisé. Les formulaires historiques restent
  en place. Le port historique de renvoi admin reste indisponible ;
  l’adaptateur de remise ne le raccorde pas.
- 27 tests ciblés / 228 assertions et 249 agrégés / 2 353 assertions.
  Lints PHP, 216 Twig, 80 YAML, conteneur, Compose et diff verts.
- Suite PHP exhaustive sur le même code, après redémarrage d’une base
  jetable vierge : 1 597 tests, 4 444 708 assertions, zéro échec, 1 mesure
  facultative ignorée (REWORK_MEASURE), 12 min 27 s. Bases et réseaux
  séquentiels retirés. Ni navigateur exhaustif (Traefik partagé exclu)
  ni CI GitHub ; pas de nouveau scénario Chromium ou de migration.

Avant un vrai flux : décider le fournisseur et les clés/rotation,
vérifier le câblage du parcours autorisé, confidentialité des journaux
proxy/application/APM, non-duplication, observation et arrêt en
préproduction autorisée et synthétique. Le runbook indépendant
docs/PREPRODUCTION_ACTIVATION.md est documenté à
e072aae11f663db47283d8d79ed52083b13f4495 ; aucun pilote exécuté ou
autorisé par V12. Reprise d’exploitation et délégation d’accès réelles
restent fermées. Pas de changement de droits, de push, de fusion dans
dev, ni de déploiement.
