# Fiabilité du catalogue administratif

## Périmètre et risques

Premier lot indépendant du chantier demandé après la PR #99, depuis dev
8be1fd9. Complexité 4/5 : écritures économiques, verrou d'univers, formulaires
concurrents et matérialisation de la production. Aucun changement des règles,
des prix de référence, des files payées ni du schéma SQL.

Les trois documents Work externes cités dans AGENTS.md ne sont pas présents
dans ce worktree. Ils n'ont pas été prétendus lus.

## Réalisé

- Fiche et navigation existantes conservées. Chaque champ distingue référence
  et surcharge de l'univers, avec la valeur de référence.
- Coefficients historiques de croissance retirés des champs modifiables
  seulement quand les courbes communes correspondantes sont actives. Une
  explication dépliable indique la courbe réellement utilisée. Les anciennes
  surcharges restent conservées ; aucune formule n'est changée.
- Motif obligatoire (5–1 000 caractères), confirmation HTTP explicite.
- Empreinte du contexte catalogue/règles vérifiée après acquisition du verrou
  et relecture de l'univers. Un formulaire ancien ou rejoué est refusé.
  Une génération par fiche empêche de réactiver un formulaire après un retour
  aux références. Aucun jeton de session ou secret dans cette empreinte.
- Univers fermé vérifié également après rechargement sous verrou.
- Saisie conservée après erreur avec son empreinte originale : elle ne reçoit
  pas silencieusement un nouveau droit d'écraser une modification concurrente.
  Le lien de rechargement abandonne explicitement cette saisie.
- Journal : acteur, univers, fiche, motif et définitions effectives avant/après.
  Le reset reste un retour aux références, pas une restauration historique.

La matérialisation/actualisation de toutes les colonies n'est pas optimisée
dans ce lot : préserver ses garanties prime sur un gain non mesuré.

## Vérifications

- make configure et make validate réussis dans le worktree dédié.
- Tests PHP ciblés sur une MariaDB éphémère propre au projet
  genesis-admin-catalogue-test : 16 tests, 69 assertions, réussis.
- Le premier essai HTTP a échoué faute d'assets dans le worktree neuf.
  Après montage en lecture seule des assets existants inchangés, les tests
  ci-dessus passent. Dépendances également montées en lecture seule.
- Couverture : isolation des univers, droits HTTP, validation des valeurs,
  matérialisation, conservation des files payées, avant/après, motif,
  formulaires périmés/rejoués, reset périmé et anciens coefficients masqués.
- Pages administratives dédiées : 1 test, 140 assertions réussis.
- Syntaxe PHP des quatre fichiers modifiés, 59 templates Twig admin, 80 YAML,
  injection du conteneur et git diff --check : réussis.
- Les essais de formulaires concurrents sont séquentiels. Ils ne démontrent
  pas une course entre deux processus simultanés.
- Recettes Playwright existantes adaptées aux motifs/confirmations et au
  nouveau nombre de champs ; leur exécution reste à effectuer.
- CI complète, recette visuelle/responsive et performance : non effectuées.
  Aucun push, aucune PR, fusion, migration existante ou publication.

## Suite non livrée par ce lot

Prévisualisation complète et calculée des effets, assistant de grille globale,
brouillons persistants/publication groupée, restauration de versions,
prérequis et nouveaux paramètres, simulations UI, optimisation des lectures,
permissions fines, opérations, support et guildes restent des lots distincts.
Ce premier lot ne signifie pas que le chantier complet est terminé.
