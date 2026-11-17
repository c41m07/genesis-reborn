# Prompts des illustrations du catalogue

## Illustrations des unités économiques — 16 novembre 2026

Six illustrations originales 4:3, une par rang de chaque famille, sont générées
avec l’outil image intégré à partir du prompt maître ci-dessous et de la direction
artistique des trois cargos existants. Métal graphite, joints industriels, cuivre
et voyants ambre, lumière cyan, vue trois-quarts et colonie à l’arrière-plan :
les rangs évoluent par leur construction, sans simple changement de couleur.

| Rang | Famille solaire | Famille hydrogène |
|---|---|---|
| Basique | Satellite héliocapteur : deux ailes photovoltaïques | Récolteur atmosphérique : filtre frontal et modules cryogéniques |
| Intermédiaire | Plateforme solaire : quatre ailes et conversion renforcée | Collecteur orbital : double filtration et traitement renforcé |
| Supérieur | Couronne héliosolaire : couronne photovoltaïque fermée | Raffineur d’hydrogène : séparation intégrée et modules multiples |

Les [prompts complets et références](artwork/civil-2026-11-16/prompts.json) permettent
la reproduction : concaténer master_prompt, style_prompt et subject_prompt avec
un saut de ligne, puis utiliser la référence de rang précédent lorsqu’elle est indiquée.
Les PNG sources sont conservés dans output/imagegen/civil-2026-11-16/ (hors Git) ;
les six WebP livrés gardent leur résolution 1448 × 1086, qualité 90.
Les 150 illustrations précédentes et les SVG d’identité restent conservés.
Les deux pictogrammes civils SVG initiaux restent présents comme sources historiques ;
les cartes utilisent maintenant le chemin WebP habituel par slug et rang.
Les noms changent dans les fiches, pas les identifiants ni les capacités des unités.

## Format retenu

Les 156 illustrations du catalogue sont servies depuis `app/public/images/catalog/`. Les ressources de conception restent sous `docs/artwork/`. Ces prompts servent à leur recréation ; préserver les illustrations actuellement utilisées.

- Concept art 3D de science-fiction réaliste et cinématique pour Genesis Reborn.
- Univers bleu nuit et noir, éclairage cyan/bleu, touches ambre discrètes.
- Une seule scène et un seul sujet principal par image, sans collage ni planche.
- Cadrage paysage 4:3, sujet centré, entièrement visible et lisible dans une carte.
- L’illustration remplit le cadre sans déformer le sujet.
- Aucun texte, label, chiffre, logo, interface, cadre ou filigrane.
- Les variantes Intermédiaire et Supérieur gardent la silhouette de base et gagnent en sophistication.

## Prompt maître

> Crée une illustration individuelle pour une carte du jeu de stratégie spatiale Genesis Reborn. Montre uniquement le sujet indiqué, dans une scène de concept art 3D réaliste et cinématique. Palette bleu nuit et noir, éclairages cyan/bleu, accents ambre discrets. Vue paysage panoramique, sujet centré, complet et immédiatement reconnaissable à petite taille. Utilise toute la surface du cadre sans étirer le sujet. Pas de planche, grille, collage, texte, libellé, chiffre, logo, interface, bordure ou filigrane. Le décor doit prolonger l’image jusqu’aux bords.

## Nommage et rangement

- Bâtiments : app/public/images/catalog/buildings/{slug}__{basic|intermediate|superior}.webp
- Vaisseaux : app/public/images/catalog/ships/{slug}__{basic|intermediate|superior}.webp
- Recherches : app/public/images/catalog/technologies/{slug}__{basic|intermediate|superior}.webp
- Défenses : app/public/images/catalog/defenses/{slug}__{basic|intermediate|superior}.webp

Les vaisseaux sont isolés dans l’espace avec une vue trois-quarts et des silhouettes distinctes. Les bâtiments et tourelles sont posés sur un sol planétaire. Les recherches montrent un équipement, une expérience ou une technologie représentative dans un laboratoire ou un environnement spatial. Les défenses gardent le même point de vue et gagnent en gabarit selon leur classe.

## Chargement actuel

Twig compose les chemins à partir de la catégorie, du slug et du rang. Une image
n’est pas inutilisée parce que son nom complet n’apparaît pas dans une recherche
textuelle. Les anciens constats d’avancement par atlas ne décrivent plus la
collection actuelle ; les preuves datées restent dans les audits.
