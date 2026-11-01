# Phase 12 — revalidation Playwright et correction mobile

Date : 01/11/2026. Addendum à l’audit phase 12, sans clôturer la phase entière. Application locale visitée dans Microsoft Edge via Playwright; compte de démonstration utilisé pour vérifier le flux de préférence.

## Défaut corrigé

La barre de navigation fixe à 390 px masquait la partie basse du formulaire Paramètres, en particulier le bouton « Enregistrer les paramètres ». La page n’avait pas de débordement horizontal, ce qui laissait passer le contrôle responsive existant. Le shell authentifié réserve désormais une marge basse mobile compatible avec la barre fixe et le scroll de la fenêtre tient compte de cette zone. La sauvegarde du thème est maintenant cliquable après défilement mobile.

## Preuves de vérification

- php bin/phpunit tests/Controller/PreferencesTest.php : 2 tests / 11 assertions réussis.
- php bin/phpunit tests/Service/PreferencesServiceTest.php : 1 test / 4 assertions réussis, y compris le cas d’une propriété preferences non initialisée.
- Runner isolé : preferences-accessibility.spec.mjs et ui-routes-responsive.spec.mjs réussis (2/2, base MariaDB temporaire).
- Edge + Playwright sur l’application : 16 routes aux largeurs 1440, 768 et 390 px (48 combinaisons); 0 réponse HTTP en erreur, 0 débordement horizontal, 0 erreur JavaScript.
- Préférence dark → light → rechargement → dark vérifiée sur le backend; le compte de démonstration a été remis sur dark.
- La page Paramètres a été capturée à 1440, 768 et 390 px; à 390 px, le bouton a aussi été capturé une fois défilé au-dessus de la navigation.
- Compilation production des assets réussie : 5 fichiers écrits, Webpack compilé. git diff --check ne signale aucune erreur de whitespace.

## Captures

- docs/validation/preferences-route-fixed-20261101-desktop.png
- docs/validation/preferences-route-fixed-20261101-tablet.png
- docs/validation/preferences-route-fixed-20261101-mobile-action.png

## Limites

Cette passe valide les routes listées et la sauvegarde des préférences; elle ne clôt pas l’audit exhaustif des 15 écrans, des états d’erreur/chargement ou de la conformité WCAG/RGAA. Les blocages de règles de jeu et le moteur de combat temps réel restent hors périmètre de cette correction UI.
