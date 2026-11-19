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
