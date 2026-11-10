# Contrôle Quality Alpha — 9 novembre 2026

Complexité : 3/5. Branche : `feature/correction-quality-alpha-lots-1-3`,
créée depuis `dev` à `565d415`. Aucun changement métier, schéma ou dépendance.
Les captures non suivies déjà présentes dans `output/playwright` sont conservées.
Les trois documents Work nommés dans AGENTS.md ne sont pas présents dans le dépôt.

## Preuves CI

Exécution : https://github.com/c41m07/genesis-reborn/actions/runs/42547852201
sur Alpha `e628f547f0ffefd7bd20ff8f80f01871e26e70d6`.
La comparaison locale de ce commit avec Dev ne montre aucun écart de contenu.

- Lot 1, job `128936642906` : 34 succès, un échec dans
  `admin-player-activity.spec.mjs:84`. Après l'avance de l'horloge et une touche,
  zéro requête de présence au lieu d'une, délai de 5 secondes dépassé.
- Lot 3, job `128936642826` : 26 succès, un échec dans
  `report-feedback-details.spec.mjs:93`. Largeur du document 412 px pour une
  fenêtre de 390 px. Le journal ne permet pas d'identifier l'élément débordant.
- PHP et lots navigateur 2 et 4 réussis ; publication ignorée après ces échecs.

## Corrections ciblées et limites du diagnostic

Le scénario de présence attendait seulement le changement d'URL avant d'avancer
l'horloge de cinq minutes. Il attend maintenant aussi l'événement `load`, pour
laisser les scripts de la nouvelle page initialiser les contrôleurs et minuteries.
Le défaut de synchronisation est identifiable dans le test ; son rôle dans cet
échec CI reste une hypothèse, car l'échec ne s'est pas reproduit localement.

Le scénario de combat vérifie maintenant la largeur avec `expect.poll`, après
chaque redimensionnement. La limite de largeur reste identique ; un débordement
persistant échoue toujours. Aucun style n'est masqué ni assertion retirée.
Deux exécutions locales des cinq scénarios concernés ont réussi, dont une après
recompilation des assets. La mesure locale montre le tableau de pertes plus large
que le mobile, mais contenu dans sa région défilante, sans débordement du document.
Une stabilisation tardive du rendu est une hypothèse, pas une cause démontrée.

## Vérifications locales

- `make validate` : succès.
- `make lint` : succès, schéma synchronisé.
- `make test` : 522 tests PHP, 208936 assertions ; 13 tests Python réussis.
- `npm run build` dans l'image Node 24 locale, sans réseau : succès.
- Deux reprises ciblées de cinq scénarios : 5/5 chacune ; la seconde comprend
  une instrumentation de mesure retirée ensuite.
- Suite navigateur complète sur le correctif final : 125/125 réussis, en deux
  lots isolés simultanés : 68/68 en 31,7 minutes et 57/57 en 33,4 minutes.
  Aucune reprise ciblée nécessaire pour cette exécution complète.
- `git diff --check` : succès, repris avant commit.

Journaux locaux : `/tmp/quality-alpha-{lint,test,build,repro,measure}.log` et
`/tmp/quality-alpha-browser-{1,2}.log` dans Debian. Les migrations et remises à zéro
navigateur utilisent exclusivement les bases jetables autorisées.

Commit local prévu : `test: stabilise les contrôles quality alpha` ; son identifiant
est consultable dans l'historique Git. Aucun push ou déploiement.
Une nouvelle CI sur le correctif publié reste nécessaire pour confirmer les
hypothèses de synchronisation ; les résultats locaux ne prouvent pas la cause
exacte des deux incidents CI intermittents.
