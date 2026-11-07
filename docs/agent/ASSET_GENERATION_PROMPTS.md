# Prompts de génération des illustrations du catalogue

> Document historique du 2 novembre 2026 : atlas et noms anciens. Le guide actuel
> est [ARTWORK_PROMPTS.md](../ARTWORK_PROMPTS.md) ; ce relevé ne décrit pas les assets chargés.

Mise à jour : 02/11/2026. Les listes ci-dessous sont relevées dans config/game. Les bâtiments, vaisseaux et technologies suivent l’ordre des fichiers YAML (ordre lexical utilisé par GameCatalog). Les défenses suivent leur champ display_order.

## État du raccordement dans le projet

Le catalogue contient 13 bâtiments, 14 vaisseaux, 19 recherches et 4 défenses. Les atlas restent disponibles uniquement comme repli temporaire. Le contrôleur charge d’abord les fichiers individuels du dossier public/art/catalog.

État actuel : 53/142 fichiers individuels : les 13 bâtiments aux trois rangs et les 14 vaisseaux Basique. Les fichiers suivent la convention {slug}-{tier}.png.

## Direction artistique commune

Illustration pour le jeu de stratégie spatiale Genesis Reborn, en concept art 3D réaliste et cinématique. Palette bleu nuit et noir, éclairages cyan et bleu, quelques accents ambre discrets. Pour chaque planche, crée une grille régulière de 4 colonnes × 4 lignes, avec des cellules paysage de taille identique, sans gouttière ni séparation visible. Centre chaque sujet, garde-le entièrement dans sa cellule et rends sa silhouette lisible sur une petite carte. Aucun texte, libellé, chiffre, logo, interface, cadre ou filigrane. Les cellules inutilisées ne contiennent que le fond spatial ou planétaire sombre. Respecte strictement l’ordre fourni pour la planche.

Pour les rangs Intermédiaire et Supérieur, conserve la forme reconnaissable du sujet Basique et augmente progressivement la sophistication de la coque, les détails énergétiques et l’intensité des effets. Ne change pas la fonction visuelle de l’objet et n’ajoute aucun symbole ou texte.

## Atlas des bâtiments

Prompt :

Crée une planche 4 × 4 d’illustrations indépendantes pour les bâtiments de Genesis Reborn. Chaque cellule montre un bâtiment extraterrestre distinct, centré et entièrement visible sur une surface planétaire sombre. Concept art 3D réaliste, éclairage industriel cyan et touches ambre. Respecte l’ordre ligne par ligne ci-dessous. Les trois dernières cellules ne contiennent que le fond. Applique la direction artistique commune. Génère une planche par rang : Basique, Intermédiaire et Supérieur.

Ordre des cellules :
1. Forteresse
2. Laboratoire
3. Extracteur · Antimatière
4. Extracteur · Hydrogène
5. Extracteur · Métal
6. Extracteur · Nano
7. Centrale énergétique
8. Chantier spatial
9. Entrepôt · Antimatière
10. Stockage · Électricité
11. Entrepôt · Hydrogène
12. Entrepôt · Métal
13. Entrepôt · Nano

## Atlas des vaisseaux du chantier spatial

Prompt :

Crée une planche 4 × 4 de vaisseaux Genesis Reborn. Chaque vaisseau est entièrement visible, isolé sur un fond spatial bleu nuit, vu de trois-quarts et doté d’une silhouette distincte. Concept art 3D réaliste, éclairages cyan et accents moteurs ambre. Respecte l’ordre ligne par ligne ci-dessous. Les deux dernières cellules ne contiennent que le fond spatial. Applique la direction artistique commune. Génère une planche par rang : Basique, Intermédiaire et Supérieur.

Ordre des cellules :
1. Cuirassé
2. Bombardier
3. Vaisseau capital
4. Vaisseau colonie
5. Corvette
6. Croiseur
7. Destroyer
8. Chasseur
9. Frégate
10. Chasseur lourd
11. Intercepteur
12. Sonde exploratrice
13. Chasseur furtif
14. Transporteur

## Atlas des recherches — planche principale

Prompt :

Crée une planche 4 × 4 de scènes de recherche technologique pour Genesis Reborn. Chaque cellule montre un sujet de recherche différent dans un laboratoire spatial réaliste : équipement, expérience ou technologie représentative. Aucune scène ne contient de texte, de chiffres ou d’interface. Éclairage bleu et cyan, petites touches ambre. Garde chaque scène centrée et lisible sur une petite carte. Respecte l’ordre ci-dessous. Les six dernières cellules restent au fond sombre du laboratoire. Applique la direction artistique commune. Génère une planche par rang : Basique, Intermédiaire et Supérieur.

Ordre des cellules :
1. Réparation automatisée des défenses
2. Expansion coloniale
3. Réserves cryogéniques
4. Fondements d’ingénierie
5. Navigation évasive
6. Contre-mesures de renseignement
7. Soutes modulaires
8. Synthèse des nanomatériaux
9. Ingénierie navale
10. Cycle de tir optimisé

## Atlas des recherches — planche complémentaire

Prompt :

Crée la planche complémentaire 4 × 4 de scènes de recherche technologique de Genesis Reborn, avec la même direction artistique que la planche principale : laboratoire spatial réaliste, éclairage bleu et cyan, accents ambre discrets, aucun texte, chiffre ou interface. Représente les neuf sujets ci-dessous dans l’ordre indiqué. Les sept dernières cellules restent au fond sombre du laboratoire. Applique la direction artistique commune. Génère une planche par rang : Basique, Intermédiaire et Supérieur.

Ordre des cellules :
1. Harmoniques de bouclier
2. Masquage de signature
3. Réduction de signature
4. Blindage structurel
5. Renseignement tactique
6. Acquisition de cible
7. Propulsion vectorielle
8. Calibration des armes
9. Ingénierie des armements

## Atlas des défenses

Prompt :

Crée une planche 4 × 4 de tourelles défensives planétaires pour Genesis Reborn. Même point de vue de trois-quarts pour chaque tourelle. Chaque modèle est centré, entièrement visible et reconnaissable sur un sol extraterrestre sombre. Ciel bleu nuit, lumières cyan et détails ambre discrets. Respecte l’ordre de display_order ci-dessous. Les douze dernières cellules ne contiennent que le fond planétaire. Applique la direction artistique commune. Une seule illustration par famille, partagée par les cartes des trois rangs, conformément au contrôleur actuel.

Ordre des cellules :
1. Tourelle au sol anti-léger
2. Tourelle au sol anti-escorteur
3. Tourelle au sol anti-lourd
4. Tourelle au sol anti-capital

## Fichiers et quantités historiques des atlas

| Catégorie | Planches par rang | Entrées par planche | Total de variantes | Remarque |
|---|---:|---:|---:|---|
| Bâtiments | 3 | 13 | 39 | Une planche par rang |
| Vaisseaux | 3 | 14 | 42 | Une planche par rang |
| Recherches | 6 | 10 + 9 | 57 | Deux planches par rang |
| Défenses | 1 | 4 | 4 | Le contrôleur actuel ne distingue pas les rangs |

Les images matricielles et le manifeste de progression ont été retirés du dépôt le 02/11/2026. Les prompts ci-dessus restent des consignes de recréation; ils ne décrivent pas des fichiers actuellement présents.


## Prompt individuel en vigueur

> Crée une illustration individuelle pour une carte du jeu de stratégie spatiale Genesis Reborn. Montre uniquement le sujet indiqué, en concept art 3D réaliste et cinématique, palette bleu nuit/noir avec éclairage cyan/bleu et quelques accents ambre. Cadrage paysage panoramique, sujet centré, complet et lisible à petite taille, décor jusqu’aux bords. Aucun collage, atlas, grille, texte, chiffre, logo, interface, bordure ou filigrane. Pour les rangs Intermédiaire et Supérieur, conserve la silhouette et la fonction de l’objet, avec davantage de détails et de sophistication.
