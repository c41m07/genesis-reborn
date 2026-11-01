# Phase 12 — passe visuelle 12 : thèmes clair et spatial sombre

Date : 2026-11-01. Vérification du thème sur le shell d’accueil et dans les paramètres du compte.

## Contrôles effectués

- Pour un compte sans préférence explicite, le shell expose data-theme=dark; le CSS clair n’est pas chargé.
- Le mode spatial sombre est le défaut visuel. La feuille du wireframe clair n’est chargée qu’après sélection explicite du mode Clair.
- Le test Playwright isolé tests/browser/preferences-accessibility.spec.mjs passe 1/1 : thème par défaut sombre, bascule et persistance du clair, retour au sombre, puis persistance du fuseau horaire, de la réduction du mouvement et des alertes temps réel.
- Les deux captures desktop ont été inspectées visuellement. Le mode clair conserve son apparence claire, et le mode sombre conserve le shell bleu nuit spatial.

## Preuves visuelles

- docs/validation/colonie-theme-dark.png — mode sombre spatial
- docs/validation/colonie-desktop.png — mode clair préservé
- docs/validation/colonie-theme-dark-mobile.png — mode sombre à 390 px

## État

Thème par défaut, bascule et persistance vérifiés. La passe porte sur le shell et le parcours de préférences; elle ne revendique pas la vérification pixel par pixel de chaque composant des autres pages dans les deux thèmes.
