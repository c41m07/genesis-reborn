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
