# Progression UI/UX

**PHASE 5/11 — Refonte progressive et validation par lots**

| Indicateur | Valeur actuelle |
|---|---:|
| Pages découvertes (gabarits/surfaces) | 35 |
| Pages auditées | 35 avec rendu observé (500 via aperçu) |
| Pages modifiées dans le cadre UI/UX de cette mission | 34 |
| Pages conservées après audit | 1 (404) |
| Sous-interfaces admin détaillées | 8 (7 exercées, 1 non exercée) |
| Pages vérifiées visuellement au moins partiellement | 35 |
| Pages validées fonctionnellement selon leur grille UI | 8 |
| Pages validées selon la grille applicable à leur périmètre | 8 (accès/authentification/erreurs; 500 encore partielle) |
| Pages inaccessibles | 0 (incident réel 500 non provoqué) |
| Pages bloquées | 0 |
| Observations intermittentes non closes | 2 : filtre arbre inactif une fois; connexion refusée une fois |
| Fonctionnalités supprimées par cette mission | 0 |
| Régressions connues par cette mission | 0 |

Progression globale de validation : **22,9 % (8/35)**, calculée par `pages VALIDÉES / 35 surfaces principales connues`. Les clôtures concernent les périmètres UI, navigateur et états décrits aux lots 17–20; elles ne constituent pas une certification d’accessibilité ni une validation de délivrabilité email. Les parcours ciblés réussis sur les autres pages ne ferment pas leur grille. Couverture d’implémentation : 34/35 pages modifiées et une conservée; elle ne représente pas la progression de validation. Le total des problèmes UI/UX sur les états restant à auditer n’est pas encore consolidé; les deux observations intermittentes ci-dessus ne sont pas nécessairement deux causes distinctes.

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

Le dépôt comporte déjà de nombreuses modifications préexistantes ou parallèles, non attribuées à cette mission. Les compteurs ne les assimilent pas à des pages traitées. Les **35 surfaces connues** ont un constat documenté et une observation de rendu, incluant la 500 via un aperçu de développement. Le décompte visuel a été réconcilié au lot 9 pour retirer un doublon avant/après. Cela ne couvre pas tous les états, sous-interfaces, parcours et contrôles d’accessibilité : aucune couverture exhaustive ni fin de refonte n’est revendiquée.

## Couverture

- Joueur : 20 surfaces découvertes; 20 auditées avec rendu réel et améliorées, aucune validée selon les critères exhaustifs.
- Administration : 6 surfaces découvertes; 6 auditées et améliorées, aucune validée selon les critères exhaustifs.
- Authentification/erreurs : 9 surfaces découvertes (dont 2 états du lien de réinitialisation); 9 auditées avec rendu navigateur, 8 modifiées et une conservée. Huit grilles UI closes aux lots 17–20 : connexion, inscription, demande, nouveau mot de passe, refus dédié, 403, 404 et lien invalide. 500 encore partielle, observée dans l’aperçu de développement; aucune panne réelle provoquée.
- Accès de test : session navigateur existante joueur/admin et comptes distincts créés par les scénarios dans les environnements temporaires. Les comptes locaux créés accidentellement au lot 6 restent conservés; les nouveaux scénarios sont protégés contre une exécution hors environnement isolé.
- Scénarios navigateur isolés : breakpoints généraux, routes joueur, catalogue admin, formulaire événements, parcours flotte, carte V2, état vide de diplomatie et ancres diplomatiques réussis. Les scénarios V2 et diplomatie couvrent aussi le débordement à 390 px. Cela n’élève pas les pages à VALIDÉE sans audit visuel et accessibilité complets.
- Branche dédiée créée au lot 8 : `refactor/global-ui-ux-overhaul`. Au lot 11, une opération extérieure a déplacé le checkout sur `dev-2026-11-projet` et créé des commits regroupant les travaux présents. Aucun changement de branche ni manipulation de cet historique par ce lot; ses nouvelles modifications sont laissées non commitées pour éviter de perturber ces opérations concurrentes.

## Prochaine action concrète

Revenir aux parcours joueur en univers : diplomatie/guildes, avec états membre/gestionnaire et erreurs des formulaires, en préservant les changements parallèles de messagerie et de carte. Tous les profils admin, suppression d’univers, fins de missions, grand empire, 500 réelle et lecteur d’écran restent ouverts. Recontrôler les deux observations intermittentes. L’écart de schéma local et l’attente asynchrone du rapport restent documentés. Avant un prochain commit, vérifier branche/index; garder un dossier de résultats propre au lot.

## Lot 20 — refus d’accès et reprise

- Défaut reproduit : CSRF admin invalide redirigé vers une carte réservant pourtant la console aux administrateurs. Notification rendue neutre et carte adaptée à la session : connexion anonyme, retour jeu joueur, explication de formulaire potentiellement expiré et retour console admin. Conditions du refus, permissions et consignation inchangées.
- Deux défauts Axe corrigés : paragraphes clairs à 4,41:1 (token text désormais à 14,32:1 sur blanc, calcul de palette) et nom accessible du lien Admin ne reprenant pas son texte visible. Le composant d’en-tête est aussi contrôlé sur la colonie administrateur.
- Après deux passages intermédiaires révélant ces défauts, cinq scénarios réussis ensemble. Trois sessions aux cinq tailles, Axe à trois tailles, évitement/focus et retours réels. 403 générique avant/après entrée en univers, au clavier et sans JS; anonyme sans accès admin. Configuration et rôles préservés dans les fixtures.
- Sept captures avant et onze finales conservées; desktop/mobile, clair et sans JS revus selon les états détaillés dans validation.md. Huit grilles UI closes sur 35, soit 22,9 %; aucune validation globale de permissions ou d’accessibilité revendiquée.
- Build et lint Twig final réussis. `make lint` : PHP, 77 YAML, 53 Twig, conteneur et mapping valides, échec final du schéma local déjà connu. Le 53e Twig est un composant de messagerie parallèle, pas une nouvelle page ni un travail attribué à ce lot.
- Fichiers de code : message du handler AdminAccessDeniedHandler, carte access_denied, nom du lien Admin dans base, règle claire dans admin-dashboard.css et nouveau scénario access-denied-validation-gates. Cinq documents UI/UX mis à jour. Aucun commit, migration, installation ou modification de règle métier; checkout partagé et index préservés.

## Lot 19 — paramètres conditionnels et nouveau mot de passe

- Vitesses héritées : avertissement des valeurs enregistrées hors de la plage éditable, identifiants liés aux champs concernés, valeurs et bornes conservées. La notice partage les styles existants et occupe toute la largeur de la grille. Aide noscript sur la saisie manuelle des cinq vitesses.
- Fixture ×100 : aucun changement après consultation ou refus natif/serveur. Le passage à Alpha nécessite un choix puis une sauvegarde explicites; seuls les paramètres temporaires du scénario sont modifiés. Univers fermé : clavier/sans JS, commandes désactivées et refus serveur/CSRF vérifiés, configuration et statut inchangés.
- Nouveau mot de passe : grille UI close après cinq tailles dans les états valide/erreur, Axe à trois tailles, clavier sans JS et effet réel. Mot de passe précédent refusé, nouveau accepté; liens expirés/utilisés et autre jeton antérieur refusés. Page conservée avec les améliorations du lot 18, sans retouche supplémentaire.
- Cinq scénarios réussis ensemble; build et 52 Twig valides. Le premier essai fermé avait échoué sur la résolution du domaine par le client HTTP du test, corrigée en passant par le navigateur. L’état admin global reste partiel, sans clôture de toute la page ni changement des sept sous-interfaces exercées.
- Après finition pleine largeur et aide noscript, scénario hors plage enrichi et rejoué avec succès : formulaire réellement enregistré sans JS après saisie manuelle des cinq vitesses. Deux assertions intermédiaires de texte agrégé ne trouvaient pas l’aide pourtant présente; cible corrigée vers le paragraphe visible, sans assouplir le contrôle. Quatre captures avant et treize finales conservées/revues selon les états décrits dans validation.md.
- Total : 6/35 surfaces principales validées (17,1 %); 34 modifiées et une conservée. Pas de migration, installation, modification de service ou de configuration de jeu. Pas de commit : checkout partagé et index préservés.

## Lot 18 — validation des formulaires publics

- Défaut confirmé sans JS : après refus d’inscription ou de demande, focus sur BODY. Correction ciblée dans le thème Twig : formulaire invalide focalisé et nommé, contour visible et Tab vers le premier champ. Formulaires initiaux et mécanismes serveur inchangés. Nouveau mot de passe, troisième consommateur du thème, rejoué sans JS après erreur.
- Connexion/inscription/demande : cinq tailles, cibles ≥44 px, audit Axe initial à trois tailles, captures desktop/mobile et erreurs revues. Inscription puis correction/doublon, connexion réelle et demande connue/inconnue jouées au clavier sans JS; valeurs non sensibles conservées et mots de passe non réaffichés.
- Compléments en base jetable : compte suspendu refusé; mot de passe correct refusé après tentatives répétées, récupération toujours disponible. Sixième demande avec confirmation neutre et jeton précédent inchangé après quota. Aucun réglage, compte réel ou protection modifié.
- Dix scénarios réussis ensemble, puis deux ciblés (demande enrichie et suspension/limitation) : onze scénarios distincts au total, sans annoncer toute la suite du dépôt. Build, 52 Twig et contrôle du diff réussis. Schéma local toujours hors synchronisation; pas de migration ni nouvelle vérification PHP pour ce lot Twig/CSS.
- Quatre captures avant et quatorze finales conservées sous lot18. Les trois grilles UI publiques sont closes : total 5/35, soit 14,3 %. Livraison email réelle, lecteurs d’écran et autres moteurs de navigateur non validés. Deux observations intermittentes historiques toujours ouvertes.
- Fichiers de code de ce lot : thème registration_errors, une règle dans layout-alignment.css, nouveau scénario auth-form-validation-gates et assertion dans auth-ui-responsive. Documentation UI/UX mise à jour. Pas de commit : branche dev-2026-11-projet et index vide préservés avec les modifications parallèles.

## Lot 17 — paramètres d’univers et premières grilles closes

- Profil de vitesse : correction de l’inversion JavaScript qui affichait 0,5 dans quatre champs après sélection de ×2. Les cinq champs affichent désormais 2; le service conserve sa conversion des vitesses en coefficients de durée. Le résumé identifie le profil par les valeurs effectives, et affiche Personnalisé après édition manuelle.
- Paramètres extraits en composant Twig : groupes vitesses/limites/fonctionnalités, deux colonnes puis une, cases alignées, explication ×N. Erreur et valeurs saisies conservées au retour, onglet et volet rouverts; succès explicite. Aucun service, borne, permission ou réglage réel modifié.
- Base jetable : profil ×2 sauvegardé et relu, valeur 0 refusée avec saisies conservées et configuration inchangée; correction à ×3, limites et case sauvegardées; override catalogue témoin préservé. Cinq largeurs et audit Axe du formulaire réussis.
- 404 conservée et lien de récupération invalide : grilles applicables closes après responsive aux cinq tailles, clavier, liens réels, contrastes et captures desktop/mobile. Lien invalide également fonctionnel sans JS; statuts et en-têtes protecteurs conservés. Correction ciblée d’un retour de 28 px, désormais au moins 44 px, qui était écrasé par un sélecteur plus spécifique.
- Six scénarios distincts réussis au fil des passages : cinq sur six dans la première exécution, puis les deux grilles publiques rejouées avec succès après correction. Audit public Axe existant également réussi. Build et syntaxe PHP/77 YAML/52 Twig/conteneur/mapping valides; lint global en échec uniquement sur le schéma local connu. Pas de migration supplémentaire.
- Deux captures avant et huit finales conservées sous lot17. Sept des huit sous-interfaces admin exercées; la suppression reste non exercée. Pas de commit : checkout partagé sur dev-2026-11-projet, index vide et modifications parallèles préservés.

## Lot 16 — univers et colonies multiples

- Création/calendrier séparés en composants Twig : saisies et causes d’erreur conservées au retour, volet de calendrier rouvert, confirmation de succès et ancres vers le bon contexte. Le contrôleur ne change ni les services ni leurs validations métier; les brouillons excluent les CSRF.
- Filtre Planifié ajouté. Univers fermé : calendrier désactivé et explication. L’ouverture immédiate et la configuration résolue sont reflétées dans les résumés, sans faux avertissement sur un univers valide.
- Colonies : noms longs non tronqués, actions de renommage contextualisées, sélection et raccourcis préservés. État vide rendu sans lien vers une planète nulle. Bloc de bonus de position préexistant conservé.
- Six scénarios distincts réussis au fil des passages : trois nouveaux et trois régressions admin/flottes. Le test d’état fermé a nécessité un vrai rechargement après modification de fixture; le parcours a ensuite réussi, puis a été rejoué après finition des résumés. Quatre captures avant et onze finales conservées; sombre/clair et état vide revus côté joueur.
- `make lint` : syntaxe PHP, 77 YAML, 51 Twig, conteneur et mapping valides; échec final sur le décalage connu entre schéma local et mapping. Aucune migration ou synchronisation de base effectuée.
- 34 surfaces principales modifiées, une conservée; six des huit sous-interfaces admin détaillées exercées. Cela ne clôt pas les grilles exhaustives de validation. Aucun commit : branche partagée et modifications parallèles préservées.

## Lot 15 — administration secondaire et flottes actives

- Huit sous-interfaces de `/admin` détaillées dans l’inventaire : navigation, comptes, sécurité, journal et quatre formulaires d’univers encore statiques. Elles complètent la surface 26 sans gonfler le total de 35.
- Onglets clavier corrigés; confirmations contextualisées sur rôles et suspensions, compte courant protégé et formes de statut préservées. Sécurité/journal : messages vides distingués, bouton d’examen harmonisé; droits, CSRF et journalisation serveur inchangés.
- Flottes actives : colonne et noms longs contenus sur mobile, composant contrôlé sur l’accueil et Flottes, journal de retour orienté vers la colonie d’origine.
- Compilation et 49 Twig valides; cinq scénarios réussis, dont comptes/CSV et régressions navigation/notes. Après correction du sens de retour et finition des commandes, collecte et scénario de flotte rejoués avec succès. Huit captures avant et douze finales conservées.
- Une reprise intermédiaire a échoué à la connexion avant d’atteindre les flottes, avec le message générique de connexion impossible; aucune cause attribuée sans preuve. Dernier passage réussi. Cette observation s’ajoute au chargement ponctuel des filtres au lot 14 et reste à qualifier.
- Aucun traitement métier, permission, réglage d’univers ou donnée réelle changé. Travail conservé sans commit sur la branche partagée modifiée en parallèle.

## Lot 14 — arbre technologique et cartes

- Arbre : état vide expliquant la préférence existante, compteur des nœuds affichés, filtres réutilisés, défilement clavier, connecteurs associés aux nœuds visibles et conservation des fiches ouvertes. Correction des couleurs claires et du défilement après filtre à la suite de la revue visuelle.
- Carte classique : formulaires rapides moins empilés dans le tableau, corps de texte renforcé, défilement horizontal expliqué; données, missions et contrôles d’accès inchangés.
- V2 : recherche à boutons natifs avec flèches/Entrée/Échap, restitution du focus, un seul panneau de détails à la fois, zoom déplacé pour ne pas recouvrir sa fermeture et lien de repli sans JS.
- Sept scénarios isolés réussis au premier passage, incluant les régressions V2 et catalogue/inventaire. Finitions rejouées : carte classique et V2 réussies; un défilement natif persistant a été reproduit et corrigé dans l’arbre. Dernier passage arbre/carte classique réussi, première fiche non coupée. Six captures avant et douze finales conservées; détails dans `validation.md`.
- Les captures temporaires ont été effacées par un test extérieur simultané; journaux conservés et captures relancées avec un dossier de sortie séparé. Aucun processus extérieur interrompu.
- Un chargement des filtres de l’arbre n’a pas abouti pendant la collecte concurrente; cause non établie et incident non reproduit au dernier essai. À contrôler hors compilation simultanée, en plus des variantes de jeu et contrôles d’accessibilité restants.
- Les 35 surfaces connues ont désormais un rendu observé; cela ne valide pas exhaustivement les états secondaires. Équilibrage, fichiers de combat et nouveaux travaux d’empire présents dans l’arbre de travail sont extérieurs au lot et conservés. Aucun commit ni changement de branche.

## Lot 13 — accès et erreurs

- Inscription : identifiants d’erreur alignés sur les références ARIA générées par Symfony, aide au pseudo, carte et liens harmonisés. Le thème est partagé avec les deux formulaires de récupération.
- Connexion : erreur neutre reliée aux champs, introduction mobile moins haute; présentation desktop préservée. Accès refusé au vouvoiement. Lien expiré : explication et chemins de reprise.
- 403 et 500 : textes centrés sur la reprise. La 404 et le layout d’erreur autonome sont conservés avec justification après inspection.
- Assets compilés et 49 Twig valides. Six scénarios isolés réussis : affichage responsive, erreurs/clavier, réinitialisation sans JS/expiration, erreurs HTTP, régression connexion/inscription et changement réel du mot de passe/jeton à usage unique.
- Douze captures avant conservées; rendu final revu sur mobile et connexion desktop. Une finition corrige aussi la zone cliquable du lien principal de la page d’accès refusé; son contrôle de taille est rejoué aux cinq largeurs.
- Contrôle final réussi après correction d’une ancienne règle CSS concurrente : actions principales des cartes d’accès d’au moins 44 px aux cinq largeurs. Vingt-deux captures finales conservées sous `lot13-after-`.
- Trois surfaces restent uniquement statiques : technologie, carte classique et carte V2. Aucun contrôle exhaustif ni validation d’un incident 500 réel revendiqué. Travail non commité sur la branche extérieure existante.

## Lot 12 — événements, retour joueur et univers

- Événements : hiérarchie regroupée, statuts et types lisibles, dates début/fin UTC, portée, effet et description conservés, état vide avec retour à la colonie.
- Retour : champs regroupés, textarea thématique, aides et résumé des erreurs; type/priorité conservés après POST invalide. Aucun traitement métier changé.
- Univers : règles comparatives plus lisibles, univers actuel identifié, date absolue d’ouverture disponible sans JS et état fermé expliqué; jointure et permissions conservées.
- Captures avant à 1440/390 px conservées sous `lot12-before-`. Assets compilés et 49 gabarits Twig valides. Les scénarios et limites détaillés figurent dans `validation.md`.
- Cinq nouveaux scénarios réussis après correction de deux hypothèses de test; deux scénarios existants admin/adhésion également réussis. Cinq largeurs contrôlées sans débordement, douze captures finales conservées, thèmes sombre et clair mobile revus.
- Les 35 surfaces connues ont un constat, mais 12 restent statiques; 21 pages modifiées. Aucune validation exhaustive revendiquée.
- La branche reste `dev-2026-11-projet`; de nouveaux commits extérieurs ont été observés pendant ce lot. Travail conservé sans commit ni changement d’historique pour éviter un conflit avec ces opérations.

## Lot 11 — catalogue admin et inventaire joueur

- Catalogue : recherche nom/identifiant/rang, filtre famille, compteur, état vide et remise à zéro. Les saisies restent dans le DOM; édition, CSRF et restauration préservés. Sans JS, toutes les entrées restent visibles.
- Inventaire : effet issu du service existant, capacité visible, états vide/plein/attente et règles expliquées. Consultation conservée, aucune nouvelle action de consommation ni modification d’équilibrage.
- Assets compilés et 49 Twig valides. Quatre scénarios navigateur réussis en environnement temporaire : nouveaux parcours catalogue/inventaire et régressions catalogue/modération. Cinq largeurs sans débordement; trois captures finales revues et conservées sous `lot11-`.
- Couverture portée à 32 surfaces auditées et 18 modifiées. Aucune page déclarée exhaustivement validée. Thème clair et clavier complet restent à contrôler pour ce lot.
- Travail laissé non commité : la branche et des commits ont changé par une opération extérieure pendant la session. Préserver cet état; ne pas ajouter globalement les fichiers.

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

### 5 novembre 2026 — Arbre complet des déblocages

L’arbre technologique inclut les bâtiments, recherches, vaisseaux du chantier spatial et défenses, pour les trois rangs. Les relations utilisent les prérequis de déblocage du premier niveau et les contraintes existantes du laboratoire et du chantier (exception du chasseur conservée). Les prérequis sont placés au-dessus des éléments débloqués ; leurs niveaux restent consultables dans les fiches. Un filtre par catégorie complète la recherche et la disponibilité. La préférence de masquage des éléments verrouillés reste appliquée. Aucune règle ni valeur d’équilibrage modifiée.

Validation : Compose et compilation réussis ; test PHPUnit dédié réussi (782 assertions), test navigateur ciblé de l’arbre réussi (responsive, ordre vertical, liens, filtres, clavier et sans JavaScript). Lint PHP/Twig/YAML/conteneur réussi ; validation du schéma local en échec (écart base/mapping). Suite PHPUnit générale : 161 tests, 5 erreurs et 14 échecs dans les vérifications existantes (boosts, files, équilibrage et fiches) ; elle ne constitue pas une validation globale du dépôt.

### 5 novembre 2026 — Recherche des chemins de déblocage

Précision utilisateur : l’arbre est un outil de préparation des déblocages. Cette décision remplace la conservation du masquage des éléments verrouillés indiquée ci-dessus pour l’arbre uniquement. La recherche conserve récursivement les ancêtres des résultats ; les filtres s’appliquent aux résultats recherchés et leurs prérequis restent visibles dans les autres catégories. Un encadrement distingue les correspondances, et le compteur sépare résultats et prérequis. Le comportement est activé seulement sur l’arbre ; les autres listes utilisant le filtre partagé conservent leur fonctionnement.

### 5 novembre 2026 — Repères de catégorie et de rang dans l’arbre

À la demande utilisateur, les fonds des cartes identifient la catégorie : ocre pour les bâtiments, bleu pour les recherches, vert pour les vaisseaux et rose pour les défenses. Le cadre identifie le rang : doré basique, bleu intermédiaire, violet supérieur. Les palettes sont adaptées aux thèmes clair et sombre ; une légende et les libellés textuels accompagnent les couleurs. La disponibilité reste indiquée par son texte, indépendamment du fond et du cadre. La surbrillance des résultats de recherche reste distincte du cadre. Compilation des assets et validation Twig réussies.

### 5 novembre 2026 — Orientation corrigée à la demande utilisateur

La lecture des dépendances passe de gauche à droite. Les trois rangs occupent des bandes communes, dans l’ordre basique, intermédiaire, supérieur de haut en bas ; les colonnes représentent les étapes des prérequis. Les tracés relient le bord droit des prérequis au bord gauche des éléments débloqués. Les fonds par catégorie, cadres par rang et chemins de recherche sont conservés. Cette disposition remplace l’orientation verticale précédemment demandée. Compilation et validation Twig réussies.

### 5 novembre 2026 — Sélection d’un chemin par clic

Cliquer sur l’en-tête d’une carte (ou l’activer au clavier) conserve le dépliage natif et isole cette carte avec tous ses prérequis directs et indirects. Les autres branches sont masquées, la carte sélectionnée est mise en évidence et le bouton « Afficher tout l’arbre » rétablit la vue complète. Modifier une recherche ou un filtre remplace la sélection par les résultats du filtre. Les liens des fiches restent navigables sans modifier la sélection. Compilation et validation Twig réussies.
