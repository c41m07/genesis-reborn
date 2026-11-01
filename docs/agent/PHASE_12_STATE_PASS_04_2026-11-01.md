# Phase 12 — retours d’erreur d’accès

Date : 2026-11-01. Complément à l’audit des états, sans clôturer P12-STATE-01 ni P12-A11Y-01.

## Défaut corrigé

Le contrôle navigateur a confirmé que le refus d’identifiants était déjà annoncé dans une alerte, mais que l’erreur de mot de passe répété lors d’une inscription rejetée en HTTP 422 était seulement rendue dans une liste sans rôle accessible. Un thème de formulaire appliqué uniquement à l’inscription rend maintenant ces erreurs dans un élément role=alert. Le texte métier et le statut 422 restent inchangés.

## Preuves

- auth-validation-states.spec.mjs : runner Docker isolé, 1 test / 1 réussi.
- Identifiants invalides : le message générique de connexion est visible dans une alerte.
- Confirmation différente : réponse 422, erreur lisible et rendue dans role=alert.
- Capture : docs/validation/inscription-erreur-mots-de-passe-20261101.png.

Cette passe couvre deux erreurs d’accès. Les autres formulaires, erreurs réseau/métier, états de chargement, retours des écrans protégés et la vérification par lecteur d’écran restent à auditer; P12-STATE-01 et P12-A11Y-01 demeurent partiels.
