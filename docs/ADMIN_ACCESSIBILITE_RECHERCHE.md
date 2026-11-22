# Administration : focus après recherche différée

## Défaut vérifié

Complexité 3/5 : remplacement DOM asynchrone, focus clavier et absence de
vol de focus. Branche `fix/admin-accessibilite-navigation`, dev PR99 `8be1fd9`.

Le contrôleur `admin-list` conserve le formulaire mais remplace les résultats.
Reproduction dans Chromium avec le vrai contrôleur chargé sur une fixture :
1. Saisir une recherche, valider avec Entrée ; retarder la réponse.
2. Tabuler vers le bouton puis un lien des anciens résultats.
3. Libérer la réponse : le lien focalisé est supprimé sans destination clavier.

Le test échoue avant correction ; il réussit ensuite sans modifier son attente.
Ce comportement concerne le composant partagé des listes administratives,
pas les routes ou les droits. Aucun test de charge ni compte réel utilisé.

## Correction

Juste avant le remplacement, relever si le focus est dans les résultats.
Dans ce seul cas, focaliser leur bilan `role=status` actualisé avec tabindex -1
et sans défilement forcé. Le conteneur sert de repli si le bilan manque.
Tab poursuit ensuite vers le premier lien actualisé. Le formulaire, une
sélection ailleurs, les réponses obsolètes et les erreurs ne volent pas le focus.
Aucun élément supplémentaire n’entre dans l’ordre de tabulation normal.

Les libellés, alertes, aria-busy, navigation et styles existants ne sont pas
modifiés : aucun défaut supplémentaire n’est affirmé sans reproduction.
Les changements portent seulement sur le contrôleur commun, sans collision
avec les pages catalogue, support, permissions ou guildes des autres lots.

## Recette et limites

- Six tests navigateur Chromium réussis : trois cas à 1366 × 844 puis 390 × 844.
- Réponse différée après tabulation, maintien du focus dans la saisie ou hors
  résultats, et échec HTTP 503 synthétique conservant résultats/focus.
- Commande : `app/node_modules/.bin/playwright test --config app/playwright.config.mjs admin-list-keyboard --workers=1`.
- `make assets` : compilation réussie. `git diff --check` : réussi.
- Fixture HTML minimale, vrai contrôleur Stimulus (seule adaptation de son
  import/export), requêtes interceptées vers admin-ui.test ; aucun serveur,
  compte, base, courrier ou service du jeu sollicité.

Les largeurs testent le comportement clavier du composant, pas le responsive
visuel complet des pages de production. Aucun audit contraste, lecteur d’écran,
conformité RGAA globale ou recette Edge du site connecté n’est revendiqué.
La suite navigateur complète et la CI restent à effectuer avant fusion.
Aucun push, fusion, migration ou déploiement. Sources Work externes citées
par AGENTS.md absentes du dépôt, non prétendues lues.
