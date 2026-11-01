# Phase 12 — intégration visuelle, écran Planètes

Date : 01/11/2026. Passe indépendante de l’audit exhaustif phase 12. Référence comparée au site Figma publié et à la page réelle du jeu.

## Ajustement

La page Planètes possédait déjà un catalogue réel avec les colonies du joueur, les positions orbitale et les raccourcis métier. Sur l’univers de démonstration, aucune position colonisable n’est disponible, mais l’action principale affichait encore « Coloniser un monde ». Le libellé est maintenant « Explorer la galaxie » quand le serveur ne retourne aucune position libre; il redevient « Coloniser un monde » dès qu’une position est disponible. Le lien utilise dans les deux cas la vraie carte du jeu. L’action principale a reçu le style doré partagé avec le thème spatial. Aucune donnée ou règle de colonisation fictive n’a été ajoutée.

## Vérification

- Twig : lint réussi sur templates/colony/planets.html.twig.
- Edge + Playwright : /planetes renvoie HTTP 200 aux largeurs 1440, 768 et 390; aucun débordement horizontal.
- Dans l’univers de démonstration (0 position libre), le CTA « Explorer la galaxie » est visible aux trois formats; le clic ouvre /carte.
- Captures post-correction : docs/validation/planetes-empty-space-20261101-1440.png, -768.png et -390.png.
- Le site de référence publié répond HTTP 200; le tableau de bord de référence a été inspecté à 1440 px, sans erreur JavaScript. Ses noms et chiffres de démonstration ne sont pas réutilisés.

## Suite

L’alignement reste à vérifier pour tous les états (catalogues avec position libre, colonies multiples et retours d’action). La phase 12 demeure ouverte pour la revue exhaustive des parcours et de l’accessibilité.
