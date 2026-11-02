# Économie, progression et atlas — Genesis Reborn

Date : 2 novembre 2026
Statut : implémentation terminée sur `feature/economy-rare-resources`.

## Règles retenues et livrées

- La chaîne des ressources est maintenant métal, électricité, antimatière, hydrogène et nano. Carbone devient nano et énergie devient électricité par migration 1:1 des stocks existants; aucune valeur de construction ne dépend de l’électricité.
- Les coûts de catalogue sont définis par budget de valeur (M=2, N=4, A=8, H=1), famille d’objets et rang. Ils conservent leurs unités par niveau et le facteur général de progression. Les profils d’usines restent spécifiques : l’extracteur de métal ne coûte que du métal; ceux de nano et d’antimatière utilisent leurs intrants.
- Les usines fonctionnent avec un plancher électrique de 10 %. Une simulation segmentée intègre la production à travers épuisement des stocks et plafonds de stockage, sans dépendre du nombre de consultations. L’antimatière consomme 8 H par unité et sa production tombe à zéro sans hydrogène.
- Le facteur de rang n’augmente plus précision, suivi, esquive ou signature. La chance de toucher reçoit 10 points par écart de rang, bornée à 15–95 %. Les défenses stationnaires ont désormais trois rangs distincts en catalogue, file, stockage, espionnage et combat.
- Le manifeste et l’interface utilisent les noms d’assets et de stockages alignés sur les nouvelles ressources. Une planche de trois tourelles est fournie pour les rangs.

## Exemple de budge

Le chasseur conserve un budget de valeur de 6 000 points, ancré sur son ancien coût métal, sans valoriser l’électricité :

| Rang | Métal | Nano | Antimatière | Valeur approximative |
|---|---:|---:|---:|---:|
| Basique | 2 700 | 150 | 0 | 6 000 |
| Intermédiaire | 2 438 | 563 | 47 | 7 504 |
| Supérieur | 2 110 | 938 | 176 | 9 380 |

Les valeurs sont arrondies ressource par ressource. Les coûts effectifs par niveau utilisent ensuite la courbe du catalogue.

## Vérifications effectuées

- Sauvegarde de la base locale avant migration; migration `Version20261102130000` appliquée avec succès sur la base locale et dans la stack de test isolée.
- PHPUnit complet : **124 tests réussis, 2 277 assertions**.
- Lint projet : PHP, YAML (74 fichiers), Twig (39 fichiers), conteneur Symfony et schéma Doctrine réussis.
- Compilation de production des assets réussie.
- Test navigateur dédié : **1/1** réussi. Il vérifie l’image, les trois rangs, leur cadrage CSS et l’absence de débordement à 1440 px et 390 px.
- Captures : [desktop](../validation/defense-tiers-atlas-desktop.png) · [mobile](../validation/defense-tiers-atlas-mobile.png).

## À mesurer pendant l’Alpha

Les tests vérifient la mécanique et la cohérence des profils, pas les objectifs de rythme de campagne. Il reste à mesurer avec des parties réelles le délai de deuxième colonie, de flotte moyenne et de vaisseau capital; les budgets de valeur et les coefficients de production pourront alors être réajustés.
