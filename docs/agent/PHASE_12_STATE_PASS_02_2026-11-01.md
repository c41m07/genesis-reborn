# Phase 12 — revue des états et accessibilité 02

Date : 2026-11-01.

## Prévisualisation de flotte

Le scénario navigateur isolé tests/browser/fleet-preview-submit-state.spec.mjs couvre maintenant trois états de la prévisualisation du service réel :

- estimation valide : le bouton d’envoi est activé;
- erreur métier backend (sonde requise absente) : l’erreur apparaît et l’envoi reste désactivé;
- panne réseau simulée : l’estimation prend l’état d’erreur et l’envoi reste désactivé.

Le résultat de prévisualisation possède aria-live="polite"; le test vérifie cette annonce accessible. Résultat du run isolé : 1/1. Aucun départ de flotte n’a été soumis.

## Limite de portée

Ces états ajoutent une preuve de transition réussite/erreur et de panne asynchrone, mais ne couvrent pas toutes les routes, tous les retours serveur, ni les états de chargement du site. P12-STATE-01 demeure partiel jusqu’à une revue systématique de ces branches.
