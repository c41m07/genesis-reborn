# Prompts des illustrations du catalogue

## Format retenu

Les illustrations matricielles ont été retirées du dépôt le 02/11/2026. Ces prompts et conventions de nommage sont conservés pour une recréation ultérieure; aucun atlas ni fichier individuel n’est actuellement chargé par l’application.

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

- Bâtiments : public/art/catalog/buildings/{slug}-{basic|intermediate|superior}.png
- Vaisseaux : public/art/catalog/ships/{slug}-{basic|intermediate|superior}.png
- Recherches : public/art/catalog/research/{slug}-{basic|intermediate|superior}.png
- Défenses : public/art/catalog/defenses/{slug}.png

Les vaisseaux sont isolés dans l’espace avec une vue trois-quarts et des silhouettes distinctes. Les bâtiments et tourelles sont posés sur un sol planétaire. Les recherches montrent un équipement, une expérience ou une technologie représentative dans un laboratoire ou un environnement spatial. Les défenses gardent le même point de vue et gagnent en gabarit selon leur classe.

## Remplacement des atlas

Le contrôleur Stimulus charge l’image individuelle quand elle existe, puis utilise temporairement l’atlas correspondant pour les entrées qui restent à générer. Les atlas et le manifeste de coordonnées ne seront supprimés qu’après la migration complète.

État au 02/11/2026 : 53 illustrations individuelles sur 142 : les bâtiments sont terminés aux trois rangs et les 14 vaisseaux Basique sont terminés. Les autres cartes restent en repli sur atlas en attendant leurs fichiers dédiés.
