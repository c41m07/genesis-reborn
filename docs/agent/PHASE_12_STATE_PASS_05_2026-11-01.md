# Phase 12 — chargement de la prévisualisation de flotte

Date : 2026-11-01. Nouvelle preuve P12-STATE-01 ciblée; exigence toujours PARTIELLE.

## Correction et comportement

Quand une estimation est demandée ou que la composition change, la région de résultat aria-live annonce désormais « Estimation en cours… ». L’envoi reste désactivé jusqu’au retour du service. Le message est remplacé par l’estimation réelle ou par l’erreur existante; aucun calcul métier client n’a été ajouté.

## Vérification Playwright

- fleet-preview-submit-state.spec.mjs : runner Docker isolé, 1 test / 1 réussi.
- La réponse d’estimation est suspendue pour contrôler le chargement et le bouton verrouillé, puis relâchée pour vérifier le succès. Le même scénario couvre l’erreur métier sonde requise et l’échec réseau.
- À 390 × 844 px : navigation fermée après transition, document sans débordement horizontal et état de chargement visible.
- Capture : docs/validation/flottes-estimation-chargement-mobile-20261101.png.

La couverture reste limitée au wizard de flotte. Les autres états des routes et services, ainsi que l’audit complet des erreurs et chargements, restent ouverts; P12-STATE-01 demeure partiel.
