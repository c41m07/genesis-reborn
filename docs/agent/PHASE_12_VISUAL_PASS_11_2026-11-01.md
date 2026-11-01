# Phase 12 — passe visuelle 11 : flottes

Date : 2026-11-01. Parcours observé dans le navigateur sur la route réelle **/flottes**, à 1440×1000, 768×1024 et 390×844.

## Contrôles effectués

- Le formulaire réel accompagne la composition, le choix de destination et la confirmation; revenir à la destination conserve la composition choisie.
- À ces trois largeurs, aucun débordement horizontal du document n’a été observé. La confirmation et son action restent accessibles sur mobile au-dessus de la navigation basse.
- La prévisualisation appelle le backend réel. Pour le compte d’essai observé, le backend refuse la mission d’exploration faute de sonde exploratrice. Le bouton d’envoi restait actif avant le correctif, malgré cette erreur.
- Le contrôleur désactive maintenant l’envoi avant une estimation et le maintient désactivé sur toute erreur. Après une estimation valide, il restaure l’état initial du bouton afin de conserver un verrou métier rendu par le serveur (dont la règle de carburant en attente). Aucun départ de flotte n’a été soumis pendant cette vérification.
- Le test navigateur isolé tests/browser/fleet-preview-submit-state.spec.mjs vérifie le refus serveur faute de sonde et l’état désactivé du bouton. Résultat : 1/1.
- Le test de parcours existant tests/browser/fleet-wizard.spec.mjs a également passé précédemment : 1/1. Il vérifie les trois étapes et la conservation de la composition.

## Preuves

- docs/validation/flottes-audit-20261101-1440.png
- docs/validation/flottes-confirmation-20261101-1440.png
- docs/validation/flottes-confirmation-20261101-768.png
- docs/validation/flottes-confirmation-20261101-390.png
- docs/validation/flottes-audit-mobile-confirm-20261101-390.png

## État

Correction ciblée de sûreté d’interface reliée à la prévisualisation du service existant. Le moteur de flotte et ses règles ne sont pas remplacés. Aucune mission n’a été envoyée.
