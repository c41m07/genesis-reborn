# Progression UI/UX

**PHASE 5/11 — Refonte progressive, lots initiaux**

| Indicateur | Valeur actuelle |
|---|---:|
| Pages découvertes (gabarits/surfaces) | 35 |
| Pages auditées | 30 (18 visuelles navigateur, 12 statiques) |
| Pages modifiées dans le cadre UI/UX de cette mission | 16 |
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

Le dépôt comporte déjà de nombreuses modifications préexistantes, non attribuées à cette mission. Les compteurs ne les assimilent pas à des pages traitées. Couverture d’audit : **86 %** (30/35), dont douze audits uniquement statiques. Le décompte visuel a été réconcilié au lot 9 pour retirer un doublon avant/après. Ce pourcentage ne mesure pas la fin de la refonte.

## Couverture

- Joueur : 20 surfaces découvertes; 16 auditées (13 avec rendu réel, 3 statiques), aucune validée selon les critères exhaustifs.
- Administration : 6 surfaces découvertes; 5 auditées, aucune validée.
- Authentification/erreurs : 9 surfaces découvertes (dont 2 états du lien de réinitialisation); 9 auditées statiquement, aucune vérifiée visuellement.
- Accès de test : la session navigateur déjà connectée a accès aux écrans joueur et à `/admin` en rôle administrateur. Aucun compte de test créé; le compte joueur distinct reste non vérifié.
- Scénarios navigateur isolés : breakpoints généraux, routes joueur, catalogue admin, formulaire événements, parcours flotte, carte V2, état vide de diplomatie et ancres diplomatiques réussis. Les scénarios V2 et diplomatie couvrent aussi le débordement à 390 px. Cela n’élève pas les pages à VALIDÉE sans audit visuel et accessibilité complets.
- Branche dédiée : `refactor/global-ui-ux-overhaul`, créée au lot 8 depuis le même commit que `feature/catalogue-ui-research-defenses`; toutes les modifications présentes ont été conservées.

## Prochaine action concrète

Auditer le catalogue admin et les écrans joueur encore découverts : inventaire de boosts, événements, envoi de retour et choix d’univers. Puis confirmer les audits statiques et les variantes non testées. Les écarts de schéma locaux et l’attente asynchrone du rapport restent ouverts.

## Lot 10 — navigation admin et notes de version

- Navigation admin inline repliable sous 760 px : bouton explicite, Échap, restitution du focus et fermeture après navigation; permanente sur desktop. Sans JavaScript, les liens restent accessibles.
- Notes joueur : en-tête harmonisé, titres longs sécables, détail natif contextualisé.
- Notes admin : création/édition en volets, champs sombres lisibles, statut/date conservés après erreur et suppression séparée avec confirmation existante. Aucun contrôleur ni règle de publication modifié.
- Build et 49 gabarits Twig valides. Trois scénarios réussis dans `genesis-browser-test-1793844166-148637` : événements responsive, navigation clavier/sans JS, notes brouillon/publication/lecture. Notes rejouées après finition visuelle destructive dans `genesis-browser-test-1793844344-149941` : succès.
- Trois captures 390 px revues et conservées sous `lot10-`. Contrastes théoriques du bouton destructif : 7,89:1 en sombre et 7,38:1 en clair avec les fonds prévus; rendu clair admin non inspecté.
- Les templates de notes appartiennent à une fonctionnalité préexistante non suivie par Git. Leurs modifications restent dans l’arbre de travail; les fichiers métier associés ne sont pas absorbés dans le commit UI.

## Lot 9 — messagerie, profil et préférences

- Messagerie : signalement dans un volet natif accessible au clavier, même composant Twig en privé/guilde; rédaction thématique et noms longs sécables.
- Profil : groupes identifiants/mot de passe facultatif/confirmation, avertissement sur la déconnexion existante et résumé d’erreur.
- Préférences : groupes affichage/horloge, raccourcis et confort, présentation sombre/claire partagée et erreur en bloc d’alerte.
- Build et lint des 49 gabarits réussis. Dans `genesis-browser-test-1793843439-145563`, contact privé, modération clavier/CSRF et profil erreur/sauvegarde/reconnexion réussis; mesure initiale du thème clair à 477 px pour un viewport de 390 px.
- Contrôle clair rejoué dans `genesis-browser-test-1793843600-147129` : succès après stabilisation par la capture, sans changement CSS supplémentaire. Cause de la première mesure transitoire non établie. Quatre captures à 390 px conservées et revues sous le préfixe `lot9-`.
- Aucun compte local ni réglage utilisateur existant modifié. Tests réalisés avec des comptes jetables; nouveau mot de passe laissé vide lors du test de profil.

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
- Commit ciblé `e58c1f5` sur la branche dédiée : rapports, style du suivi admin, protection des tests et documentation/captures. Les changements préexistants, les lots antérieurs encore non commités et les modifications mixtes du scénario de conquête restent dans l’arbre de travail; éviter un ajout Git global.
