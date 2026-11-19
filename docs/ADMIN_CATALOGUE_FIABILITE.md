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

## Incrément : devis par fiche sans publication

Complexité 3/5, même branche. « Comparer le devis » partage la validation
numérique avec l'enregistrement, copie le catalogue en mémoire et utilise
OperationCalculator pour comparer l'actuel et la proposition. Coûts, taxe
électrique, durées et multiplicateurs restent ceux des calculateurs existants.

Scénario : un rang, un niveau 1–100 pour bâtiments/recherches ou une quantité
1–1 000 pour unités, sans bonus de bâtiment/recherche. L'interface expose
le coefficient de durée de l'univers et précise les limites : ni stocks,
ni prérequis/plafonds, ni production planétaire vérifiés. Ce n'est pas un devis
de colonie réelle ni une garantie de publication.

CSRF, droits admin et empreinte courante restent requis. Aucun motif ni
confirmation pour la simulation, qui n'écrit aucune donnée de jeu.
Seule la session garde proposition/résultat pour le retour à la fiche.
La publication reste distincte et conserve motif, confirmation et verrou.

Validation finale : **23 tests, 263 assertions** réussis sur MariaDB jetable,
puis Twig (59) et injection du conteneur réussis. Quatre familles/trois rangs,
taxe électrique, non-mutation après flush/relecture, absence d'audit et de file,
scénarios/valeurs invalides, contexte périmé, CSRF et refus joueur couverts.
La première assertion CSRF attendait 403 ; le projet utilise déjà la redirection
302 vers /acces-refuse, désormais vérifiée. Base/réseau jetables supprimés.
La recette navigateur reste à exécuter.

## Suite non livrée par ce lot

Prévisualisation avec contexte réel des colonies et production, assistant de grille globale,
brouillons persistants/publication groupée, application de versions historiques,
prérequis et nouveaux paramètres, simulations UI, optimisation des lectures,
permissions fines, opérations, support et guildes restent des lots distincts.
Ce premier lot ne signifie pas que le chantier complet est terminé.

## Incrément : historique consultable par fiche

Complexité 4/5 à l’analyse, ramenée à 3/5 en excluant les écritures.
Une page dédiée GET, accessible depuis la fiche et protégée par ROLE_ADMIN,
présente les traces du journal pour le seul triplet univers/famille/identifiant.
Le repository filtre en SQL avant LIMIT (20 + 1) et utilise un curseur d’ID
décroissant. Les comparaisons dépliables rapprochent avant/après/actuel pour
les champs numériques actuellement modifiables. Aucun acteur ou autre détail
personnel n’est chargé. Les motifs sont échappés par Twig.

Les anciennes traces sans instantané affichent « Non enregistré », jamais zéro.
La comparaison n’est ni une preuve que toute modification a été auditée, ni un
ancien devis reproductible : règles globales, prérequis, contexte des colonies
et anciennes définitions non modifiables ne sont pas historisés par ce lot.
L’actuel est celui de la requête ; aucun rafraîchissement ni mutation implicite.

Pas de restauration, publication ni brouillon persistant ajouté. Une future
application devra sélectionner strictement les champs encore compatibles,
revalider limites/plafonds et contexte sous verrou, garder l’empreinte concurrente,
le motif, la confirmation et l’audit atomique existants. Elle ne devra jamais
injecter une ancienne définition complète dans gameSettings. Le journal n’est
pas un système de versions immuables ni une sauvegarde autonome.

Aucune migration ou modification des services métier d’écriture. L’index
existant est temporel ; le coût de recherche JSON sur un gros journal reste
non mesuré. Un index dédié sera à étudier avant d’annoncer une optimisation.
Accès direct, navigation paginée, absence de formulaire d’écriture, droits,
isolation univers/fiche, traces incomplètes et échappement sont couverts par
les tests ciblés. Recette visuelle/clavier, CI complète et charge réelle restent
à effectuer.

Validation de cet incrément : make validate, 25 tests / 284 assertions
(catalogue HTTP, intégration et pages dédiées), 60 templates Twig et injection
du conteneur réussis. Premier essai bloqué par les caches préexistants non
inscriptibles : remplacés uniquement dans les conteneurs par des tmpfs éphémères,
sans changer les permissions locales. L’assertion d’absence de formulaire POST
a été limitée au contenu principal pour exclure la déconnexion globale.
Les réponses 404 des identifiants invalides sont attendues et testées.
