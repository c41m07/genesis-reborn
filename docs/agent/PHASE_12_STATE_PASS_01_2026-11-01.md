# Phase 12 — revue des états et limites de preuve 01

Date : 2026-11-01.

## Parcours flotte vérifié

Le test navigateur isolé tests/browser/fleet-preview-submit-state.spec.mjs vérifie maintenant, dans un seul parcours, qu’une estimation valide active le bouton d’envoi, puis qu’après retour à la composition, l’absence de sonde exploratrice est refusée par le backend et désactive ce bouton. Résultat : 1/1. La flotte n’a pas été envoyée.

Le test couvre un état de réussite de prévisualisation et un état d’erreur métier; il ne valide pas à lui seul les autres branches d’erreur ni l’ensemble du critère P12-STATE-01. Le runner détruit sa stack temporaire après le scénario; aucune capture image de cet état n’est donc conservée comme preuve.

## Provenance des ressources héritées

L’historique Git des quatre SVG du manifeste — public/art/icons.svg, public/art/structures.svg, public/art/colony.svg et public/art/favicon.svg — ne remonte qu’au snapshot initial 80668fa du 31/10/2026, attribué à Caim. Cet historique n’identifie ni leur auteur d’origine ni leur licence. Les SVG ne contiennent pas de référence externe détectée d’après l’inspection déjà inscrite au manifeste, mais cela ne prouve pas le droit de réutilisation.

Le critère P12-ART-01 reste partiel et le blocage de provenance avant production demeure. Il faudra obtenir la source/licence ou remplacer les SVG par des ressources documentées avant de clore ce critère.

## Limite d’audit accessibilité

Axe-core n’est pas présent dans les dépendances du projet lors de cette revue. Les tests existants de navigation clavier, noms accessibles et contraste échantillonné restent des vérifications ciblées; aucun audit automatisé axe ni essai avec lecteur d’écran tiers n’est attesté ici. P12-A11Y-01 reste partiel.

## Statut

Cette passe ajoute une preuve ciblée et confirme deux limites déjà listées dans REQUIREMENTS_MATRIX.md. Elle ne clôt pas P12-STATE-01, P12-ART-01 ou P12-A11Y-01.
