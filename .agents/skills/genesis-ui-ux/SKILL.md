---
name: genesis-ui-ux
description: "Auditer ou harmoniser l’UI et l’UX de Genesis Reborn à partir des écrans du jeu : cohérence graphique, navigation, maquettes comparables, responsive et accessibilité. Ne pas utiliser pour une demande exclusivement métier ou infrastructure."
---

# UX/UI de Genesis Reborn

Répondre en français. Partir du jeu existant et de l’objectif du joueur, pas d’une interface idéale sans contraintes. Un audit ou une demande de maquettes n’autorise ni modification de l’application, ni action de jeu, ni publication. Implémenter séparément seulement après validation de la direction, ou si le joueur a déjà choisi et demandé le changement précis. Respecter `AGENTS.md` et conserver le travail en cours.

## Retrouver l’identité réelle

- Lire `docs/ui-ux/GUIDE_INTERFACE.md` et, si utile au parcours, `docs/NAVIGATION_PAGES_DEDIEES.md`. Pour une page déjà revue, retrouver le lot et les choix validés dans `docs/REFONTE_VISUELLE_INTERFACES.md` et les rapprocher du rendu actuel : une décision propre à un écran ne devient pas une règle globale. Vérifier `docs/DECISIONS.md` et `docs/REGLES.md` si un geste, un état ou un droit est incertain.
- Vérifier la cascade selon `app/assets/app.js`, les tokens dans `app/assets/styles/app.css`, la typographie dans `game-typography.css` et le clair dans `wireframe-theme.css`. Reprendre le langage SF sombre et sobre : fond bleu nuit, panneaux et séparateurs, cyan `--accent` pour liens et focus, or `--gold` pour l’accent d’action là où il existe. Vérifier les couleurs et tailles calculées de la page plutôt que réappliquer une palette brute. Conserver le thème clair et les états sémantiques.
- Réutiliser les compositions de `app/templates/base.html.twig` ou `app/templates/admin/layout.html.twig`, les icônes de `app/templates/ui/macros.html.twig`, les cartes, onglets, filtres, tableaux et boutons existants. Préserver logos, SVG d’identité et illustrations de jeu utiles. Garder une densité stratégique lisible : l’admin et le jeu partagent leurs fondations, pas forcément leur densité ou navigation. Pas de React, de réécriture ni de nouveau design system pour corriger une incohérence locale.

## Observer et comparer

1. Prendre une référence de la vraie page et d’un écran comparable. Noter route, version/surface, rôle, thème, colonie/univers utile, état et dimensions **réelles du viewport CSS**. Commencer à **1920 × 1080** pour une revue ou maquette desktop, sauf taille demandée ; préciser recadrage, export ou échelle sans les faire passer pour la taille du viewport. Si accès, taille ou rendu indisponibles, qualifier la limite et ne pas inventer de capture.
2. Rejouer le geste qui motive la demande. Selon le risque, montrer état nominal, vide, attente, refus/erreur et retour après action ; pour actions persistantes, seulement sur copie autorisée à données synthétiques. Comparer : repère dans le shell, hiérarchie titres/actions, taille et rythme des textes, densité et alignements, usage cyan/or, lisibilité des coûts/temps, lisibilité des statuts et focus, intérêt des illustrations, comportement tactile et menus/panneaux qui masquent une action. Distinguer **constat vérifié**, **risque à vérifier** et **préférence** ; indiquer preuve, impact et ce qui marche déjà.
3. Pour une demande de conception, livrer la **référence actuelle** et **deux propositions** réellement distinctes sur le même scénario, avec leur compromis et une recommandation. Garder les mêmes données fictives, les mêmes fonctions utiles et le même viewport pour comparer ; par exemple continuité plus structurée d’un côté, priorité visuelle différente de l’autre. Étiqueter séparément capture réelle, maquette et prototype. Ne pas annoncer une validation ou un lien Figma absent. Présenter la décision à prendre sans refaire tout le site.

## Recette et livraison

- Prioriser les écrans et états concernés : desktop, tablette (repère 768 px), mobile (390 px, 320/360 si le risque le justifie), sombre/clair. Si le brief actif impose aussi 1440 px, le couvrir. Tester la structure des titres et l’ordre du focus au clavier, Tab/Maj+Tab, Entrée/Espace et Échap si applicable, retour du focus, noms accessibles, textes/contrastes, zones tactiles, absence de défilement horizontal de page et de contrôle masqué. Préserver alternatives d’image, mouvement réduit et les parcours natifs sans JS qui existent. Un test automatisé ne prouve pas à lui seul la conformité RGAA.
- Après validation et demande d’implémentation, travailler en lot local dédié dans la pile Symfony/Twig/Stimulus/CSS. Garder URL, permissions, contexte, retours d’erreur, saisies, actualisations, règles et valeurs du jeu. Vérifier le ou les consommateurs communs du composant et mesurer seulement si une amélioration de performance est annoncée. Suivre les contrôles du dépôt et comparer avant/après dans les mêmes conditions.
- Dire clairement ce qui change pour le joueur, ce qui est conservé, ce qui a été vérifié et les limites restantes. Rattacher les captures ou états testés aux actions effectivement observées ; ne pas annoncer fusion ou mise en ligne sur la seule base d’une maquette ou de tests locaux.
