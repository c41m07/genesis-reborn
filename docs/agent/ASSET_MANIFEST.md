# Genesis Reborn — inventaire des assets

Mise à jour : 01/11/2026. Deux illustrations originales ont été intégrées depuis leurs versions WebP compressées; les PNG sources sont conservés. Les quatre SVG hérités ont été remplacés le 01/11/2026 par des tracés originaux, sans import de formes tierces; leurs identifiants de symboles sont conservés pour les appels Twig.

| Asset | Chemin | Usage observé/prévu | Format / dimensions | Provenance / licence | Optimisation / texte alternatif | Statut |
|---|---|---|---|---|---|---|
| Icônes interface | public/art/icons.svg | Icônes de navigation, ressources et actions | Sprite SVG original ; 23 symboles | Redessiné dans ce projet le 01/11/2026; aucune forme ou source tierce importée; les IDs Twig sont conservés | Tracés currentColor, décoratifs masqués aux lecteurs d’écran car le texte voisin porte le nom | Intégré, provenance connue |
| Emblème Genesis Reborn | public/art/genesis-reborn-emblem.webp | Marque dans la navigation authentifiée et publique | WebP RGBA, 256 × 256, 28 KB; les fichiers PNG source sont conservés | Généré originalement pour le projet le 01/11/2026 | Image décorative alt vide, wordmark textuel adjacent; WebP qualité 88 | Intégré |
| Monde habité | public/art/genesis-reborn-planet.webp | Illustration de fond de la bannière de colonie | WebP RGBA, 768 × 723, 155 KB; les fichiers PNG source sont conservés | Généré originalement pour le projet le 01/11/2026 | Image décorative alt vide; chargement différé; WebP qualité 88; image décorative alt vide et wordmark adjacent | Intégré |
| Structures | public/art/structures.svg | Illustrations/catalogue de bâtiments, vaisseaux et défenses | Sprite SVG original ; 7 symboles | Redessiné dans ce projet le 01/11/2026; IDs historiques conservés pour le macro Twig | Tracés adaptatifs currentColor; décoratifs | Intégré, provenance connue |
| Colonie | public/art/colony.svg | Ciel orbital et horizon de la bannière; illustration WebP originale superposée | SVG original, viewBox 1200 × 420 | Nouvelle scène vectorielle créée pour le projet le 01/11/2026; aucune source tierce | Illustratif et décoratif; alt vide sur les deux couches | Intégré, provenance connue |
| Favicon | public/art/favicon.svg | Identité navigateur | SVG original, viewBox 64 × 64 | Redessiné pour le projet le 01/11/2026; aucune ressource externe | Fichier autonome, aucune dépendance réseau | Intégré, provenance connue |
| Prototype Figma Make | Site public | Référence UX uniquement | Rendu web dynamique, pas un asset local | Figma Make publié par l’utilisateur | Ne pas incorporer le rendu sans export/licence/source vérifiés | Référence |
| Visuels de capture Playwright | /tmp/genesis-figma-make.png, /tmp/genesis-figma-mobile.png | Preuve temporaire de l’audit de preview | PNG générés en local | Captures d’un rendu public fourni par l’utilisateur | Non suivis par Git ; non intégrés à l’application | Temporaire |

Les illustrations raster principales ont été générées pour le projet, puis converties en WebP. Les quatre SVG hérités ont été remplacés par des assets vectoriels originaux documentés ici; les anciens chemins de symboles nécessaires aux vues ont été conservés. Aucun SVG actif ne référence de ressource externe. Les WebP livrés pèsent 186 KB au total.

## Atlas de progression — 02/11/2026

Les atlas Basique PNG existants sont conservés. Les rangs Intermédiaire et Supérieur ajoutent des planches SVG au même canevas 1672×941 et à la même grille CSS 4×4. Les planches suivent la palette bleu/cyan/violet et ajoutent des anneaux et détails d’énergie progressifs. Les recherches sans correspondance visuelle auparavant (armement, bouclier, colonisation et six nouvelles recherches) utilisent des planches supplémentaires.

Le manifeste complet des chemins et coordonnées est dans public/art/progression-atlas-manifest.json. Les coordonnées utilisées par Twig sont dans templates/colony/show.html.twig; les chemins de rang sont définis dans assets/styles/app.css.

| Rang/catégorie | Atlas | Entrées | État |
|---|---|---:|---|
| Basique bâtiments | buildings-atlas.png | 13 | Conservé |
| Intermédiaire bâtiments | buildings-atlas-intermediate.svg | 13 | Ajouté |
| Supérieur bâtiments | buildings-atlas-superior.svg | 13 | Ajouté |
| Basique vaisseaux | ships-atlas.png | 14 | Conservé |
| Intermédiaire vaisseaux | ships-atlas-intermediate.svg | 14 | Ajouté |
| Supérieur vaisseaux | ships-atlas-superior.svg | 14 | Ajouté |
| Recherche Basique | research-atlas.png + research-atlas-basic-extra.svg | 10 + 9 | Atlas conservé, correspondances complétées |
| Recherche Intermédiaire | research-atlas-intermediate.svg + research-atlas-intermediate-extra.svg | 10 + 9 | Ajouté |
| Recherche Supérieur | research-atlas-superior.svg + research-atlas-superior-extra.svg | 10 + 9 | Ajouté |

Les trois PNG historiques restent inchangés. Les planches SVG gardent les dimensions d’affichage et le découpage CSS; les entrées sans rapport restent conservées.

Capture de validation des trois familles d’illustrations : docs/validation/progression-ranks-atlas-20261102.png. Elle montre les silhouettes Basique préservées, les halos Intermédiaire/Supérieur et une recherche ajoutée.
