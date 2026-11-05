# Progression UI/UX

**PHASE 5/11 — Refonte progressive, lots initiaux**

| Indicateur | Valeur actuelle |
|---|---:|
| Pages découvertes (gabarits/surfaces) | 35 |
| Pages auditées | 25 (14 visuelles navigateur, 11 statiques) |
| Pages modifiées dans le cadre UI/UX de cette mission | 11 |
| Pages conservées après audit | 0 |
| Pages validées selon les critères exhaustifs | 0 |
| Pages bloquées | 0 |
| Problèmes UI/UX non résolus | 1 confirmé; axes d’audit supplémentaires ouverts |
| Fonctionnalités supprimées par cette mission | 0 |
| Régressions connues par cette mission | 0 |

## Lot 1 — catalogue colonie et état vide de modération

- Colonie : ratio des images de cartes Bâtiments ajusté de 4:3 à 16:9, sans changer les informations/actions affichées.
- Administration : état vide de la modération distingue l’absence de signalements d’une recherche sans résultat.
- Fichiers de code : `assets/styles/app.css`, `assets/controllers/admin_filter_controller.js`, `templates/admin/index.html.twig`.
- Vérifications : build d’assets réussi; `moderation-flow.spec.mjs` réussi; `responsive-breakpoints.spec.mjs` et `ui-routes-responsive.spec.mjs` réussis à 1440/768/390. Revue visuelle post-changement de la colonie à 1920 px réussie. Revue visuelle admin bloquée par une interface d’extension Edge.
- Pas de commit effectué : la branche partagée contient de nombreux changements préexistants non commités.

## Lot 2 — formulaire d’événements admin

- Constat navigateur : zone de description minuscule, case de visibilité mal alignée et longue colonne de champs.
- Correction : textarea avec hauteur utile, case native de 18 px alignée au libellé, grille deux colonnes desktop et une colonne sous 760 px.
- Vérifications : compilation assets et Twig lint réussis; rendu visuel à 1920 px confirme description lisible, champs répartis et case alignée. `admin-events-responsive.spec.mjs` passe à 1440/768/390, sans soumission ni altération de données.
- Validation fonctionnelle de création/édition et audit clavier restent à faire dans l’environnement isolé.

## Lot 3 — audit du parcours Flottes

- État visuel réel inspecté : compte sans vaisseau, état vide utile avec accès direct au chantier, panneaux vols/orbite et journal.
- `fleet-wizard.spec.mjs` réussi en base isolée : composition, erreurs d’étape, destination, résumé de confirmation, retour à l’étape précédente et contrôle mobile à 390 px; aucune flotte envoyée.
- La branche avec flotte disponible a été exercée fonctionnellement mais pas observée en capture; aucune validation complète de page revendiquée.

## Lot 4 — focus lors des mises à jour live

- Constat par lecture du contrôleur : lors d’un événement de progression ou de notification, une zone live pouvait être remplacée alors que le focus clavier était sur un lien, un bouton ou un résumé.
- Correction : différer uniquement la zone qui contient le focus; relancer l’actualisation lorsqu’il sort de cette zone. Les autres zones continuent à se mettre à jour.
- Fichiers : `assets/controllers/colony_live_controller.js`, `tests/browser/live-refresh-focus.spec.mjs`.
- Vérifications : `make assets` réussi; scénario isolé réussi, vérifiant le maintien du focus puis le remplacement après sortie. Premier essai dans `ui-state-coverage.spec.mjs` expiré à cause d’une attente réseau ajoutée au parcours multi-routes; cette attente a été déplacée dans un test dédié.

## Lot 5 — annuaire vide de guildes

- Observation réelle à 1920 px : la section Guildes présentait le formulaire centré sans expliquer que l’annuaire ne contenait aucune guilde.
- Correction limitée au texte : état `role="status"` conditionnel dans le formulaire de création; aucune donnée, action ou permission modifiée.
- Fichiers : `templates/player/diplomacy.html.twig`, `tests/browser/diplomacy-empty-state.spec.mjs`.
- Vérifications : test isolé du message, formulaire actif et absence de débordement à 390 px réussi; test isolé existant des quatre ancres diplomatiques réussi. Après navigation complète depuis la sidebar, état vide confirmé visuellement à 1920 px; formulaire non soumis.

## Lot 6 — liste des colonies et responsive de la vue principale

- Observation navigateur à 1920 px : résumé global, CTA de colonisation et carte de la colonie sont clairement séparés; la carte sélectionnée garde les raccourcis et le renommage dans son détail.
- Deux tests responsives échouaient avant d’atteindre les écrans : lancés directement sur la base locale, ils recherchaient « Turbo » alors que seul « abc » y existe. L’isolation avait été annoncée à tort; des comptes de test ont été créés localement et sont conservés.
- Breakpoints étendus à 1280 px. Vérifications locales réussies à 1440, 1280, 768 et 390 px; elles ont été rejouées dans un environnement réellement isolé au lot 7.
- Aucun changement de mise en page jugé nécessaire sur la seule observation desktop; les captures compactes et l’audit clavier de la carte restent à faire.

Le dépôt comporte déjà de nombreuses modifications préexistantes, non attribuées à cette mission. Les compteurs ne les assimilent pas à des pages traitées. Couverture d’audit : **71 %** (25/35), dont onze audits uniquement statiques. Ce pourcentage ne mesure pas la fin de la refonte.

## Couverture

- Joueur : 20 surfaces découvertes; 12 auditées (10 avec rendu réel, 2 statiques), aucune validée selon les critères exhaustifs.
- Administration : 6 surfaces découvertes; 4 auditées, aucune validée.
- Authentification/erreurs : 9 surfaces découvertes (dont 2 états du lien de réinitialisation); 9 auditées statiquement, aucune vérifiée visuellement.
- Accès de test : la session navigateur déjà connectée a accès aux écrans joueur et à `/admin` en rôle administrateur. Aucun compte de test créé; le compte joueur distinct reste non vérifié.
- Scénarios navigateur isolés : breakpoints généraux, routes joueur, catalogue admin, formulaire événements, parcours flotte, carte V2, état vide de diplomatie et ancres diplomatiques réussis. Les scénarios V2 et diplomatie couvrent aussi le débordement à 390 px. Cela n’élève pas les pages à VALIDÉE sans audit visuel et accessibilité complets.
- Branche dédiée : `refactor/global-ui-ux-overhaul`, créée au lot 8 depuis le même commit que `feature/catalogue-ui-research-defenses`; toutes les modifications présentes ont été conservées.

## Prochaine action concrète

Auditer la messagerie et les écrans de profil/préférences; traiter ensuite la navigation admin mobile volumineuse. Les écarts de schéma locaux restent à diagnostiquer séparément.

## Lot 7 — classement et filtres des retours administrateur

- Classement : indicateurs de tri visibles et en-tête actif mis en évidence; liens et attributs `aria-sort` conservés.
- Retours admin : grille de filtres fluide et marges des champs réduites. Page locale observée dans une fenêtre de 954 px de large.
- Sur accord explicite de l’utilisateur, migration corrective `Version20261105012000` appliquée localement : ajout nullable de `attachment_filename` si absent. La page s’ouvre et présente le retour existant. Le schéma global reste désynchronisé.
- Le helper commun refuse maintenant tout hôte ne correspondant pas au projet Docker temporaire, avant toute inscription de test.
- Build réussi. Lanceur isolé : quatre tests réussis (filtres admin, classement/contact, responsive général et routes), puis filtres admin rejoués avec succès après réduction des marges. Projets temporaires terminés normalement.

## Lot 8 — rapports et détail d’un retour

- Espionnage : lecture des formats par rang et historique, niveaux de recherche, quantités formatées, ressources en listes de définitions.
- Combat : tableau de pertes avec en-têtes de ligne, légende et nombres alignés/formatés.
- Centre de rapports : catégorie Économie ne montre plus les combats, onglet actif souligné.
- Suivi admin : textarea sombre lisible, marges des labels corrigées, titre long sécable. Aucun retour local modifié; la consultation ajoute le journal d’audit prévu par le contrôleur.
- Build et Twig lint réussis. Premier fixture espionnage incomplet corrigé; trois tests ensuite réussis en base temporaire (conquête/confidentialité, renseignements, sauvegarde admin).
- Le scénario asynchrone de conquête a ensuite expiré avant création du rapport au dernier essai; ce résultat reste ouvert. Trois tests de présentation contrôlée passent dans le projet `genesis-browser-test-1793842790-143140` : renseignements, filtres/pertes et suivi admin. Les quatre captures stabilisées sont revues et conservées dans `docs/ui-ux/captures/2026-11-05/`.
