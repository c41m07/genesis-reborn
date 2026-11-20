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
