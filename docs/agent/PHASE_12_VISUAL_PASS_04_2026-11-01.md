# Phase 12 — bannière et navigation Diplomatie

Date : 01/11/2026. Passe visuelle complémentaire; la phase 12 reste en cours.

## Ajustement

La navigation Diplomatie présentait un lien « Vue d’ensemble » vers une cible qui n’existait que si le commandant appartenait déjà à une guilde. La page rend maintenant toujours une bannière dans cet emplacement : guilde réelle, absence de guilde dans un univers qui les autorise, ou état « Diplomatie directe » quand les guildes sont désactivées. Les quatre liens de section ont ainsi une cible stable. Les formulaires de relations et de guildes restent les actions backend réelles.

## Vérifications

- Twig : lint réussi sur templates/player/diplomacy.html.twig.
- Test Playwright isolé du parcours diplomatique : 1/1; quatre ancres vérifiées sur un commandant sans guilde avant de poursuivre les contrats réciproques, création/adhésion, transfert et dissolution.
- Edge local, état guildes désactivées : /diplomatie répond 200 à 1440/768/390 px, aucune largeur de document supérieure au viewport; chaque lien modifie le hash et cible un élément présent.
- Captures : docs/validation/diplomatie-empty-guilds-20261101-1440.png, -768.png et -390.png.

## Limites

Le test couvre l’univers Omega avec guilde activée et l’univers Turbo sans guilde. Une revue visuelle complète des états négatifs, du rôle de membre non-dirigeant et de la liste de guildes avec plusieurs alliances reste à faire.
