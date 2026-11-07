# Prompts des illustrations du catalogue

## Format retenu

Les 150 illustrations du catalogue sont servies depuis `app/public/images/catalog/`. Les ressources de conception restent sous `docs/artwork/`. Ces prompts servent à leur recréation ; préserver les illustrations actuellement utilisées.

- Concept art 3D de science-fiction réaliste et cinématique pour Genesis Reborn.
- Univers bleu nuit et noir, éclairage cyan/bleu, touches ambre discrètes.
- Une seule scène et un seul sujet principal par image, sans collage ni planche.
- Cadrage paysage panoramique, sujet centré, entièrement visible et lisible dans une carte.
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
