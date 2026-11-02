# GalaxieV2

## Accès et périmètre

- Route d’interface : **GET /carte-v2** (app_map_v2).
- L’entrée **GalaxieV2** est ajoutée à la navigation. La route et le gabarit **/carte** restent séparés.
- L’interface est isolée dans **templates/universe/map_v2.html.twig**, **assets/controllers/galaxy_v2_controller.js** et **assets/styles/galaxy-v2.css**.

## Source des données

Le contrôleur réutilise les dépôts et règles de la carte existante. Les systèmes proviennent de l’univers et conservent leurs coordonnées persistées. Seules les planètes des systèmes découverts par le joueur sont exposées. Les propriétaires, mondes PNJ, niveaux PNJ, mondes inhabités et flottes actives sont issus des entités du jeu.

Les textures de surface, couleurs stellaires, nébuleuses, étoiles de fond, poussières, anneaux et lunes de la vue sont cosmétiques et déterministes. Elles ne créent aucune planète, faction, propriété ou mission de jeu.

Les missions rapides réutilisent ui/map_quick_mission.html.twig et ses contrôles serveur. L’espionnage est proposé pour les planètes occupées par un joueur ou un PNJ ; aucune action d’espionnage n’est affichée pour un monde inhabité. Les flottes affichées appartiennent au joueur et leur statut, leur trajectoire et leur arrivée proviennent de leur état persisté.

## Rendu et interactions

Le contrôleur Canvas anime la carte galactique, le système sélectionné et la planète rapprochée. Il fournit recherche sur les coordonnées et les noms connus, zoom molette et boutons, déplacement par glisser-déposer, mini-carte cliquable, panneaux d’information, transitions, trajectoires de flottes, timers et option de réduction des animations. La boucle s’arrête quand la page n’est pas visible ; le redimensionnement ajuste la résolution Canvas au DPR plafonné.

## Vérification

Commandes prévues :

- npm run dev
- php bin/console lint:twig templates/universe/map_v2.html.twig
- docker compose --env-file .env.local exec -T app php bin/phpunit tests/Controller/GameplayAccessTest.php --filter testGalaxyV2UsesPersistentSystemDataAndKeepsClassicGalaxy
- bash scripts/test-browser-isolated.sh tests/browser/galaxy-v2.spec.mjs

Le test Playwright ouvre un compte jetable dans sa base Docker temporaire, parcourt Galaxie → Système → Planète → carte classique, puis vérifie l’absence de débordement à 390 px. Le runner supprime son environnement à la fin.