# Validation UI/UX

## Lot 9 — messagerie et formulaires de compte

- Paramètres actuels observés dans Edge, fenêtre 954×710, avant regroupement. Aucun réglage local soumis.
- `make assets` et lint des 49 gabarits Twig réussis.
- Projet isolé `genesis-browser-test-1793843439-145563` : contact et échange privés réussis; signalement replié puis ouvert au clavier, motif envoyé, CSRF invalide refusé, dossier classé et message masqué; profil avec erreur de mot de passe explicite, identifiants conservés, pseudo enregistré, session fermée et reconnexion réussie. Champs de nouveau mot de passe laissés vides.
- Profil sans débordement à 1920/1440/1280/768/390; messagerie sans débordement à 390. Captures du profil, des paramètres sombres et de la messagerie à 390 px inspectées et conservées.
- Première exécution des préférences : persistance thème/fuseau/mouvement/alertes réussie, mais largeur document mesurée à 477 px immédiatement après redimensionnement à 390. Nouveau contrôle après capture stabilisée dans `genesis-browser-test-1793843600-147129` : largeur conforme et retour au thème sombre réussi. Cause exacte de la mesure transitoire non identifiée; aucune correction CSS additionnelle revendiquée.
- Capture finale claire à 390 px inspectée. Images conservées dans `docs/ui-ux/captures/2026-11-05/` sous le préfixe `lot9-`.
- Non couverts : changement réel de mot de passe, conversation de guilde, historique long et toutes les erreurs de préférences. Aucune page marquée VALIDÉE intégralement.

## Lot 8 — rapports et suivi admin

- Observation native Edge du détail du retour existant : contexte et formulaire visibles dans une fenêtre 954×710; textarea gris natif confirmé. Aucun statut ni note locale modifié; journalisation de consultation conservée.
- `make assets`, lint des 48 gabarits Twig et syntaxe PHP du contrôleur des rapports : réussis.
- Premier passage : conquête et sauvegarde admin réussies; fixture espionnage arrêté avant affichage pour trois champs SQL obligatoires omis. Fixture complété sans changement de schéma.
- Second passage dans `genesis-browser-test-1793842493-139331` : trois scénarios réussis. Renseignements par rang et historiques, niveaux, ressources, quantités, catégorie vide et détection; titre admin très long, focus et sauvegarde statut/note; conquête avec filtre Économie/Combats, pertes, confidentialité et responsive.
- Après ce succès, nouvelle exécution du scénario de conquête : expiration de 45 s en attendant la création du rapport. Cause asynchrone non résolue; aucun succès global de ce dernier passage revendiqué.
- Dernier passage ciblé, projet `genesis-browser-test-1793842790-143140` : trois tests de présentation contrôlée réussis (renseignements, filtres et pertes d’un rapport constitué, suivi admin). Ces tests ne remplacent pas le scénario de création asynchrone.
- Captures finales inspectées après stabilisation des transitions : centre de rapports 1280 px, renseignements/pertes/détail admin 390 px. Onglet actif visible, menu joueur replié, tableaux défilants, niveaux et note lisibles. Les quatre images sont conservées dans `docs/ui-ux/captures/2026-11-05/` sous le préfixe `lot8-`.
- Limites : toutes les issues de combat, longue liste de rapports, pièces jointes du retour et contrastes mesurés restent à examiner. Les pages ne passent pas au statut VALIDÉE.

## Rectification de l’isolation et lot 7 — 5 novembre 2026

- La commande directe `npm run test:browser` du lot 6 utilisait l’application locale par défaut. Les mentions de base isolée de ce lot sont annulées. Des comptes `Responsive-*` et `UI-Routes-*` sont visibles dans le classement local et sont conservés. Les deux scénarios ont été rejoués avec succès via le lanceur isolé.
- Le helper `isolated-test.mjs` exige désormais un projet `genesis-browser-test-*` et un hôte correspondant, avant toute inscription.
- Classement observé avant modification dans une fenêtre Edge de 954×710 : ajout de flèches et d’un fond sur le tri actif, avec sémantique préservée. Le rendu final et le contrôle clavier complet restent à confirmer.
- Retours admin initialement en HTTP 500 : `attachment_filename` absent alors que la migration initiale est marquée exécutée. Sur accord explicite de l’utilisateur, migration corrective conditionnelle appliquée par `make migrate`; colonne nullable confirmée, page locale fonctionnelle et retour existant visible après rechargement.
- `doctrine:schema:validate` : mapping correct, schéma global encore désynchronisé. Aucune synchronisation automatique effectuée.
- Build réussi. Lanceur `scripts/test-browser-isolated.sh`, projet `genesis-browser-test-1793841697-133974` : quatre scénarios réussis (filtres admin, classement/contact/messagerie entre comptes jetables, responsive général et routes). Filtres à 1920/1440/1280/930/768/390; routes à 1440/1280/768/390.
- Après réduction des marges des filtres, build et test admin rejoués avec succès dans `genesis-browser-test-1793841815-135305`. Capture mobile inspectée avant cet ultime ajustement; capture finale non conservée, donc non revue.
- `make lint` : syntaxe PHP, YAML, Twig et conteneur réussis; échec uniquement au contrôle final du schéma local déjà désynchronisé.
- La migration corrective ne génère aucune requête sur une base neuve dont la colonne existe déjà, comportement confirmé par les deux créations temporaires.
- Les pages ne sont pas déclarées entièrement validées : navigation admin mobile volumineuse, états de données alternatifs et accessibilité complète restent à traiter.

## Session initiale — 5 novembre 2026

- Inventaire des routes : `php bin/console debug:router --show-controllers` exécuté avec succès; 48 lignes listées dont route d’erreur preview, endpoints techniques, API et actions POST. L’inventaire les regroupe au niveau des écrans.
- Services : conteneurs Docker applicatifs et bases principale/test signalés actifs; aucune donnée n’a été modifiée.
- Compte joueur/admin : non vérifié dans le navigateur. Aucun compte n’a été créé.
- Session Edge déjà ouverte : colonie `/planetes/1499?section=buildings` et `/admin` chargées sous un compte joueur avec rôle administrateur. L’onglet Modération a également été ouvert. Captures consultées pour les vues à 1920 px.
- Captures avant enregistrées comme artefacts : non; captures seulement inspectées dans la session.
- Vérification desktop 1920 : observation visuelle faite pour colonie/bâtiments et administration/vue d’ensemble. 1440/1280, tablette 768, mobile 390 : non faites.
- Comptes distincts joueur/admin : non établis. Le seed `app:seed-demo` existe et est limité à `APP_ENV=dev`; il n’a pas été lancé.
- Tests fonctionnels, accessibilité clavier et validation visuelle : non faits.
- Build, lint et suites de tests : non lancés; aucun code UI de cette mission n’a été modifié.

## Contrôles responsive ciblés — 5 novembre 2026

- `responsive-breakpoints.spec.mjs` : réussi. Parcours joueur et navigation mobile contrôlés à 1440, 768 et 390 px; absence de débordement horizontal vérifiée par le test; ouverture/fermeture du menu mobile testée.
- `ui-routes-responsive.spec.mjs` : réussi. Les routes de navigation joueur récupérées depuis la sidebar ont été ouvertes à 1440, 768 et 390 px; codes HTTP et débordements vérifiés.
- `admin-catalogue.spec.mjs` : réussi. Accès admin, édition, sauvegarde/restauration et parcours responsive validés dans la base isolée.
- Exécution : environnement Docker jetable `genesis-browser-test-*`; migrations appliquées à sa base éphémère uniquement. 3 scénarios réussis en 56,7 s.
- Ces résultats sont des preuves de parcours et de responsive pour les scénarios ciblés. Ils ne remplacent pas l’inspection visuelle par page et ne suffisent pas à marquer toutes les routes VALIDÉES.

## Après le lot 1 — 5 novembre 2026

- `make assets` : compilation Encore réussie après les changements CSS/Stimulus.
- `moderation-flow.spec.mjs` : réussi après la correction; le parcours de signalement/modération reste fonctionnel.
- `responsive-breakpoints.spec.mjs` : réussi après la correction à 1440/768/390 px.
- Inspection visuelle après changement : bloquée par une interface d’extension Edge ouverte; aucune page modifiée n’est donc marquée VALIDÉE.
- Revue visuelle colonie après ajustement : réussie à 1920 px; hauteur d’illustration réduite, coûts et actions visibles plus haut. Les interactions et tailles restent couvertes par les scénarios navigateur, mais l’audit accessibilité complet manque encore.
- Revue visuelle admin après ajustement : toujours bloquée par la fenêtre d’extension Edge; le scénario de modération passe en base isolée.
- `ui-routes-responsive.spec.mjs` rejoué après le CSS : réussi, routes joueur à 1440/768/390 px sans débordement.
- Revue visuelle admin Modération : accès rétabli; état vide unique confirmé à 1920 px. La recherche n’a pas été testée visuellement.
- Administration Événements : description, grille et case de visibilité contrôlées à 1920 px après modification. Formulaire live non soumis pour préserver les données locales.
- `make assets` et `lint:twig templates` après le lot admin Événements : réussis.
- `make lint` : vérifications PHP, YAML (77 fichiers), Twig (48 fichiers), conteneur et mapping Doctrine réussies; échec à la vérification finale, car le schéma de la base locale n’est pas synchronisé avec le mapping. Aucune migration lancée.
- Flottes `/flottes` : état sans vaisseau observé visuellement à 1920 px; aucune action de mission soumise.
- `admin-events-responsive.spec.mjs` : réussi; formulaire admin sans débordement à 1440/768/390 px, description visible, case 18×18 px, bouton activé. Aucun événement créé.
- `fleet-wizard.spec.mjs` : réussi en base isolée; composition, destination, résumé, navigation entre étapes et mobile 390 px vérifiés. Aucune flotte envoyée.
- Après le dernier réglage de largeur du bouton admin : `make assets` et `admin-events-responsive.spec.mjs` réussis de nouveau. La capture visuelle correspondante n’a pas pu être reprise, Edge étant de nouveau bloqué par une interface d’extension.

## Carte V2 et inventaire d’authentification — reprise

- `galaxy-v2.spec.mjs` : réussi en environnement isolé. Recherche d’un système, ouverture du système et d’une planète, retour entre niveaux, bascule vers la carte classique et absence de débordement à 390 px vérifiés.
- La carte V2 n’est pas déclarée validée : contraste, présentation visuelle, navigation clavier des cibles et autres tailles restent à contrôler.
- Écrans d’authentification, états de réinitialisation et pages d’erreur : contrôlés par lecture des gabarits et des contrôleurs seulement; aucun parcours navigateur exécuté dans cette reprise.
- L’onglet Edge `/admin/events` est toujours bloqué par une fenêtre d’extension; les captures et contrôles visuels en attente n’ont pas été inventés ni remplacés par une validation statique.
- Le périmètre inventorié a été ajusté à 35 surfaces en comptant séparément le lien de réinitialisation valide et son état invalide/expiré. Les rôles proviennent de `security.yaml` et des attributs de contrôleur; validation réelle d’un compte joueur distinct toujours en attente.

## Focus des zones live — accueil

- Premier essai : `ui-state-coverage.spec.mjs` a expiré après ajout d’une attente de rafraîchissement à son parcours couvrant 17 routes; le scénario dédié a remplacé cette attente afin d’isoler le comportement.
- `live-refresh-focus.spec.mjs` : réussi en base temporaire. Vérifie que le lien « Gérer les flottes » garde le focus lors d’une actualisation, puis que la zone est remplacée quand le focus la quitte.
- `make assets` : compilation réussie avant le test.
- Ce test valide le comportement clavier ciblé; l’accueil n’a pas encore été inspecté visuellement et ne passe pas à l’état VALIDÉE.

## Annuaire Diplomatie — état vide

- Capture navigateur de référence à 1920 px : navigation et résumé lisibles; formulaire de création isolé au centre, sans indication explicite d’une liste vide.
- `diplomacy-empty-state.spec.mjs` : réussi en base isolée, confirme l’annonce de l’absence, l’accès au champ et au bouton, et aucun débordement à 390 px. Aucun formulaire soumis.
- `diplomacy-overview-anchors.spec.mjs` : réussi, les quatre liens d’ancre mènent à leur section et le layout ne déborde pas à 390 px.
- La première vue de l’onglet conservait l’ancien document; une navigation complète via le lien Alliances a rechargé la page et affiché le nouveau message. Confirmation visuelle à 1920 px réussie. Le champ de guilde et le bouton restent visibles; aucune guilde créée.
- Les tests de l’état vide et des quatre ancres diplomatiques passent en base isolée; responsive 390 px couvert. Le parcours complet et les états avec une guilde existante n’ont pas été testés.

## Vue principale — première inspection desktop

- Session authentifiée existante, page `/planetes/1499`, 1920×860 : bandeau ressources, hero de colonie, quatre raccourcis (colonies, classement, flottes, alliance), puis activité, flottes et journal. L’état sans flotte est clair; le journal montre des activités récentes.
- Aucun formulaire ni aucune action de jeu utilisés pendant l’inspection.
- `live-refresh-focus.spec.mjs` avait réussi en base isolée : un lien focalisé reste connecté au DOM durant le rafraîchissement, puis la mise à jour reprend après déplacement du focus.
- Responsive, parcours complet et contrôle clavier du reste de la page non vérifiés; la vue n’est pas déclarée validée.

## Colonies — contrôles responsives

- `/planetes` observée à 1920 px : résumé de l’empire, bouton de colonisation, carte sélectionnée, coordonnées, statut, panneau de renommage replié et raccourcis vers gestion/bâtiments/chantier/flottes.
- Première exécution des deux scénarios responsive échouée à la connexion au monde, car les tests ciblaient « Turbo » alors que la base isolée ne contient que `abc`. Les helpers concernés choisissent désormais le premier univers visible.
- `responsive-breakpoints.spec.mjs` et `ui-routes-responsive.spec.mjs` : réussis après correction, puis réussis de nouveau après ajout de 1280 px. Les tests vérifient 1440/1280/768/390 px, absence de débordement et menu mobile.
- Les captures à 1280/768/390 px, le renommage et le parcours de colonisation n’ont pas été inspectés/soumis; les pages restent non validées.

## Critères à appliquer par lot

Consigner les routes, rôles, tailles vérifiées, parcours/interactions joués, constats corrigés et éventuels blocages. Une page ne passe à VALIDÉE qu’après vérification visuelle, navigation, principales interactions, responsive pertinent, accessibilité clavier et absence de régression connue.
