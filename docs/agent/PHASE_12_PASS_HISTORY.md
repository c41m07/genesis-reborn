# Phase 12 — historique consolidé des passes UI du 1er novembre 2026

Les comptes rendus datés des passes visuelles, des états interactifs et de l’accessibilité ciblée sont réunis ici; ils décrivent l’état au moment de leur exécution et ne remplacent pas les audits mis à jour ensuite. Le bilan et la portée globale restent décrits dans PHASE_12_UI_ACCESSIBILITY_AUDIT.md. Ces passes ne clôturent pas l’audit manuel RGAA/WCAG exhaustif.

# Phase 12 — contrôles ciblés d’accessibilité 01

Date : 2026-11-01. Tests exécutés sur la stack browser isolée après l’intégration de l’état vide Espionnage.

## Résultats

- ui-accessibility-smoke.spec.mjs : 1/1; les liens, boutons et champs visibles contrôlés ont un nom accessible.
- ui-routes-responsive.spec.mjs : 1/1; navigation sans débordement à 1440, 768 et 390 px.
- ui-contrast-sampled.spec.mjs : 1/1; contraste des textes sur les fonds unis échantillonnés.
- fleet-empty-state.spec.mjs : 1/1; état Espionnage sans flotte, CTA vers le chantier, menu mobile fermé et largeur de document <= 390 px.

## Portée et limites

Les résultats attestent uniquement ces contrôles ciblés. Le test de contraste ne couvre pas les dégradés, pseudo-éléments, overlays ni tous les états désactivés. Aucun audit axe/WCAG/RGAA complet et aucun essai avec lecteur d’écran tiers ne sont réalisés dans cette passe. P12-A11Y-01 reste partiel.


---

# Phase 12 — parcours clavier de l’état Espionnage vide

Date : 2026-11-01.

## Contrôle effectué

Le test fleet-empty-state.spec.mjs ouvre l’état Espionnage sans flotte sur mobile et vérifie que :

- le panneau latéral fermé est aria-hidden et hors écran;
- le lien du chantier est atteignable par Tab, reçoit le focus-visible et affiche son contour;
- Entrée suit le lien vers le chantier spatial réel de la colonie.

Résultat du runner isolé : 1/1. Capture du lien avec focus visible : docs/capture de validation supprimee. Les captures de l’état normal restent dans le journal STATE_PASS_03.

## Limite

Ce contrôle couvre ce CTA et ce breakpoint. Il ne remplace pas l’audit clavier complet de tous les composants, un essai lecteur d’écran ni une vérification RGAA/WCAG exhaustive. P12-A11Y-01 reste partiel.


---

# Phase 12 — navigation mobile au clavier

Date : 2026-11-01. Complément au contrôle A11Y ciblé, sans clôturer la phase 12.

## Parcours vérifié

Le test Playwright isolé fleet-empty-state.spec.mjs vérifie désormais à 390 × 844 px :

- « Plus » ouvre le menu, avec aria-expanded=true et navigation latérale exposée;
- le panneau finit son animation et atteint le bord gauche avant la capture;
- Échap ferme le menu, rétablit aria-expanded=false et masque le panneau;
- le focus revient sur « Plus »;
- le CTA d’état vide reste atteignable au clavier et ouvre le vrai chantier de la colonie.

Résultat : runner Docker isolé, 1 test / 1 réussi. La capture du menu ouvert est docs/capture de validation supprimee. Le test s’exécute dans un projet temporaire dédié, nettoyé par son runner.

## Portée

Cette preuve couvre le menu et le CTA de ce parcours à 390 px. Elle ne remplace ni la revue clavier de tous les écrans et états, ni l’essai avec lecteur d’écran tiers, ni un audit RGAA/WCAG complet. P12-A11Y-01 reste PARTIEL.


---

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

- docs/capture de validation supprimee
- docs/capture de validation supprimee
- docs/capture de validation supprimee

## Limites

Cette passe valide les routes listées et la sauvegarde des préférences; elle ne clôt pas l’audit exhaustif des 15 écrans, des états d’erreur/chargement ou de la conformité WCAG/RGAA. Les blocages de règles de jeu et le moteur de combat temps réel restent hors périmètre de cette correction UI.


---

# Phase 12 — revue des états et limites de preuve 01

Date : 2026-11-01.

## Parcours flotte vérifié

Le test navigateur isolé tests/browser/fleet-preview-submit-state.spec.mjs vérifie maintenant, dans un seul parcours, qu’une estimation valide active le bouton d’envoi, puis qu’après retour à la composition, l’absence de sonde exploratrice est refusée par le backend et désactive ce bouton. Résultat : 1/1. La flotte n’a pas été envoyée.

Le test couvre un état de réussite de prévisualisation et un état d’erreur métier; il ne valide pas à lui seul les autres branches d’erreur ni l’ensemble du critère P12-STATE-01. Le runner détruit sa stack temporaire après le scénario; aucune capture image de cet état n’est donc conservée comme preuve.

## Provenance des ressources héritées

L’historique Git des quatre SVG du manifeste — public/art/icons.svg, public/art/structures.svg, public/art/colony.svg et public/art/favicon.svg — ne remonte qu’au snapshot initial 80668fa du 31/10/2026, attribué à Caim. Cet historique n’identifie ni leur auteur d’origine ni leur licence. Les SVG ne contiennent pas de référence externe détectée d’après l’inspection déjà inscrite au manifeste, mais cela ne prouve pas le droit de réutilisation.

Le critère P12-ART-01 reste partiel et le blocage de provenance avant production demeure. Il faudra obtenir la source/licence ou remplacer les SVG par des ressources documentées avant de clore ce critère.

## Limite d’audit accessibilité

Axe-core n’est pas présent dans les dépendances du projet lors de cette revue. Les tests existants de navigation clavier, noms accessibles et contraste échantillonné restent des vérifications ciblées; aucun audit automatisé axe ni essai avec lecteur d’écran tiers n’est attesté ici. P12-A11Y-01 reste partiel.

## Statut

Cette passe ajoute une preuve ciblée et confirme deux limites déjà listées dans REQUIREMENTS_MATRIX.md. Elle ne clôt pas P12-STATE-01, P12-ART-01 ou P12-A11Y-01.


---

# Phase 12 — revue des états et accessibilité 02

Date : 2026-11-01.

## Prévisualisation de flotte

Le scénario navigateur isolé tests/browser/fleet-preview-submit-state.spec.mjs couvre maintenant trois états de la prévisualisation du service réel :

- estimation valide : le bouton d’envoi est activé;
- erreur métier backend (sonde requise absente) : l’erreur apparaît et l’envoi reste désactivé;
- panne réseau simulée : l’estimation prend l’état d’erreur et l’envoi reste désactivé.

Le résultat de prévisualisation possède aria-live="polite"; le test vérifie cette annonce accessible. Résultat du run isolé : 1/1. Aucun départ de flotte n’a été soumis.

## Limite de portée

Ces états ajoutent une preuve de transition réussite/erreur et de panne asynchrone, mais ne couvrent pas toutes les routes, tous les retours serveur, ni les états de chargement du site. P12-STATE-01 demeure partiel jusqu’à une revue systématique de ces branches.


---

# Phase 12 — état vide Espionnage

Date : 2026-11-01.

## Correction

Quand aucune flotte disponible n’existe, l’écran Espionnage affiche désormais un état vide dédié : prérequis de sonde explicite et lien « Accéder au chantier spatial » vers la route réelle de la colonie active. La vue Flottes générale conserve son propre texte d’absence de vaisseaux et utilise le même accès au chantier. Aucune mission ni donnée métier fictive n’est créée.

## Vérifications

- fleet-empty-state.spec.mjs : 1/1 sur la stack isolée.
- Le compte de test sans flotte ouvre /flottes?mission=spy, voit l’état attendu, suit le lien vers /planetes/{id}?section=ships et y trouve le titre du chantier spatial.
- À 390×844, la barre latérale est fermée après sa transition et le document ne dépasse pas la largeur du viewport.
- Captures prises après stabilisation :
  - docs/capture de validation supprimee
  - docs/capture de validation supprimee

## Limite

Cette preuve couvre l’état sans flotte et son CTA sur desktop/mobile. Les états d’erreur, indisponibilité et chargement des autres parcours restent à inventorier; P12-STATE-01 n’est pas clôturé.


---

# Phase 12 — retours d’erreur d’accès

Date : 2026-11-01. Complément à l’audit des états, sans clôturer P12-STATE-01 ni P12-A11Y-01.

## Défaut corrigé

Le contrôle navigateur a confirmé que le refus d’identifiants était déjà annoncé dans une alerte, mais que l’erreur de mot de passe répété lors d’une inscription rejetée en HTTP 422 était seulement rendue dans une liste sans rôle accessible. Un thème de formulaire appliqué uniquement à l’inscription rend maintenant ces erreurs dans un élément role=alert. Le texte métier et le statut 422 restent inchangés.

## Preuves

- auth-validation-states.spec.mjs : runner Docker isolé, 1 test / 1 réussi.
- Identifiants invalides : le message générique de connexion est visible dans une alerte.
- Confirmation différente : réponse 422, erreur lisible et rendue dans role=alert.
- Capture : docs/capture de validation supprimee.

Cette passe couvre deux erreurs d’accès. Les autres formulaires, erreurs réseau/métier, états de chargement, retours des écrans protégés et la vérification par lecteur d’écran restent à auditer; P12-STATE-01 et P12-A11Y-01 demeurent partiels.


---

# Phase 12 — chargement de la prévisualisation de flotte

Date : 2026-11-01. Nouvelle preuve P12-STATE-01 ciblée; exigence toujours PARTIELLE.

## Correction et comportement

Quand une estimation est demandée ou que la composition change, la région de résultat aria-live annonce désormais « Estimation en cours… ». L’envoi reste désactivé jusqu’au retour du service. Le message est remplacé par l’estimation réelle ou par l’erreur existante; aucun calcul métier client n’a été ajouté.

## Vérification Playwright

- fleet-preview-submit-state.spec.mjs : runner Docker isolé, 1 test / 1 réussi.
- La réponse d’estimation est suspendue pour contrôler le chargement et le bouton verrouillé, puis relâchée pour vérifier le succès. Le même scénario couvre l’erreur métier sonde requise et l’échec réseau.
- À 390 × 844 px : navigation fermée après transition, document sans débordement horizontal et état de chargement visible.
- Capture : docs/capture de validation supprimee.

La couverture reste limitée au wizard de flotte. Les autres états des routes et services, ainsi que l’audit complet des erreurs et chargements, restent ouverts; P12-STATE-01 demeure partiel.


---

# Phase 12 — intégration visuelle, écran Planètes

Date : 01/11/2026. Passe indépendante de l’audit exhaustif phase 12. Référence comparée au site Figma publié et à la page réelle du jeu.

## Ajustement

La page Planètes possédait déjà un catalogue réel avec les colonies du joueur, les positions orbitale et les raccourcis métier. Sur l’univers de démonstration, aucune position colonisable n’est disponible, mais l’action principale affichait encore « Coloniser un monde ». Le libellé est maintenant « Explorer la galaxie » quand le serveur ne retourne aucune position libre; il redevient « Coloniser un monde » dès qu’une position est disponible. Le lien utilise dans les deux cas la vraie carte du jeu. L’action principale a reçu le style doré partagé avec le thème spatial. Aucune donnée ou règle de colonisation fictive n’a été ajoutée.

## Vérification

- Twig : lint réussi sur templates/colony/planets.html.twig.
- Edge + Playwright : /planetes renvoie HTTP 200 aux largeurs 1440, 768 et 390; aucun débordement horizontal.
- Dans l’univers de démonstration (0 position libre), le CTA « Explorer la galaxie » est visible aux trois formats; le clic ouvre /carte.
- Captures post-correction : docs/capture de validation supprimee, -768.png et -390.png.
- Le site de référence publié répond HTTP 200; le tableau de bord de référence a été inspecté à 1440 px, sans erreur JavaScript. Ses noms et chiffres de démonstration ne sont pas réutilisés.

## Suite

L’alignement reste à vérifier pour tous les états (catalogues avec position libre, colonies multiples et retours d’action). La phase 12 demeure ouverte pour la revue exhaustive des parcours et de l’accessibilité.


---

# Phase 12 — tableau de bord, cartes d’empire

Date : 01/11/2026. Passe visuelle complémentaire; la phase 12 reste ouverte.

## Ajustement

Les quatre cartes du wireframe sont maintenant reprises dans la vue générale : Planètes, Classement, Flottes en vol et Alliance. Les valeurs viennent des services du jeu : nombre de colonies, rang calculé par le classement, flottes actives, guilde réelle et drapeau de fonctionnalité de l’univers. Les constructions et recherches restent visibles dans le panneau Activité et leurs files backend. Le nouveau calcul de rang est appelé uniquement sur la vue générale.

## Vérifications

- Contrôle de syntaxe PHP du contrôleur et lint Twig de la page réussis.
- Build production réussi : Webpack a compilé 5 fichiers.
- Playwright isolé : dark-dashboard.spec.mjs et ui-routes-responsive.spec.mjs réussis (2/2). Assertions ajoutées sur les quatre cartes, le rang calculé et le lien Diplomatie.
- Edge local : /planetes/82 répond HTTP 200 à 1440, 768 et 390 px; aucune largeur supplémentaire. Valeurs observées dans l’univers de démonstration : 1 planète, rang #1, 0 flotte et guildes désactivées.
- Captures réelles : dashboard-live-metrics-20261101-1440.png, -768.png et -390.png.

## Limites

La carte Alliance montre l’état actuel de l’univers; aucune alliance n’est inventée pour Turbo. Les tests couvrent l’univers Turbo sans guilde et le classement à une entrée. Il reste à vérifier un univers avec plusieurs joueurs et une guilde active, puis les autres états écran par écran.


---

# Phase 12 — bannière et navigation Diplomatie

Date : 01/11/2026. Passe visuelle complémentaire; la phase 12 reste en cours.

## Ajustement

La navigation Diplomatie présentait un lien « Vue d’ensemble » vers une cible qui n’existait que si le commandant appartenait déjà à une guilde. La page rend maintenant toujours une bannière dans cet emplacement : guilde réelle, absence de guilde dans un univers qui les autorise, ou état « Diplomatie directe » quand les guildes sont désactivées. Les quatre liens de section ont ainsi une cible stable. Les formulaires de relations et de guildes restent les actions backend réelles.

## Vérifications

- Twig : lint réussi sur templates/player/diplomacy.html.twig.
- Test Playwright isolé du parcours diplomatique : 1/1; quatre ancres vérifiées sur un commandant sans guilde avant de poursuivre les contrats réciproques, création/adhésion, transfert et dissolution.
- Edge local, état guildes désactivées : /diplomatie répond 200 à 1440/768/390 px, aucune largeur de document supérieure au viewport; chaque lien modifie le hash et cible un élément présent.
- Captures : docs/capture de validation supprimee, -768.png et -390.png.

## Limites

Le test couvre l’univers Omega avec guilde activée et l’univers Turbo sans guilde. Une revue visuelle complète des états négatifs, du rôle de membre non-dirigeant et de la liste de guildes avec plusieurs alliances reste à faire.


---

# Phase 12 — passe visuelle 05 : classement

Date : 01/11/2026. Route évaluée : /classement avec session de jeu réelle en environnement local.

## Ajustement

Les actions de contact du tableau prenaient la largeur complète de la colonne. Le prototype les affiche comme des commandes compactes. Une règle locale au tableau limite maintenant le bouton à son contenu; les noms, rangs, points, colonnes, liens de contact et tri backend restent inchangés.

## Vérification

- Compilation production npm run build : réussie (5 fichiers écrits).
- Playwright Chromium connecté à l’application locale : /classement répond 200 à 1440×1000, 768×1024 et 390×844.
- Largeur document / viewport : 1440/1440, 768/768, 390/390; aucun débordement de page.
- Le tableau conserve les 53 commandants présents dans l’environnement de test.
- Le tri ?by=fleet sélectionne bien la colonne « Flotte et défenses »; le lien de contact conserve sa destination /messages?with=….
- Largeur mesurée du premier bouton : 139 px à 1440 px contre 249 px avant le changement; 126 px à 390 px dans la table défilante.

## Captures

- docs/capture de validation supprimee
- docs/capture de validation supprimee
- docs/capture de validation supprimee

Cette passe valide uniquement la présentation des actions du classement; elle ne clôt pas l’audit de toute la phase 12 ni les phases suivantes.


---

# Phase 12 — passe visuelle 06 : messagerie

Date : 01/11/2026. Route évaluée : /messages avec la boîte de réception locale de 52 conversations (données de test déjà présentes).

## Écart corrigé

Avec une boîte de réception longue, la grille étirait simultanément la liste et le panneau de conversation à plus de 3 600 px de haut. Le wireframe garde une colonne de conversations et un panneau de fil stables. La liste est maintenant défilable indépendamment, tandis que le fil reste dans une hauteur de travail bornée. Sur mobile, la liste conserve sa zone défilable de 220 px au-dessus du fil empilé.

Aucun message n’a été envoyé; la conversation ouverte pour contrôle était une lecture seule. Les contrôleurs et formulaires backend restent inchangés.

## Vérification

- Compilation production npm run build : réussie (5 fichiers écrits).
- Playwright Chromium connecté : /messages répond 200 à 1440×1000, 768×1024 et 390×844.
- Largeur document / viewport : 1440/1440, 768/768, 390/390.
- Avant : liste et panneau à 3 664 px de haut sur desktop. Après : 500 px chacun; à 390 px, liste 220 px et fil 340 px.
- La liste se défile indépendamment (scrollTop mesuré à 1200 px sur mobile).
- L’ouverture d’une conversation conserve l’URL /messages?with=… et le compositeur réel est présent.

## Captures

- docs/capture de validation supprimee
- docs/capture de validation supprimee
- docs/capture de validation supprimee
- docs/capture de validation supprimee
- docs/capture de validation supprimee

Cette passe valide la disposition et le défilement de la boîte de réception. Elle ne clôt pas la phase 12 ni l’audit complet des fonctionnalités.


---

# Phase 12 — passe visuelle 07 : Galaxie

Date : 01/11/2026. Comparaison du wireframe publié avec la route locale /carte.

## Résultat de la revue

La page reprend la hiérarchie du wireframe : contrôles de galaxie et de système, légende, table des positions, puis note sur les actions possibles. À 1440 et 768 px, le tableau est entièrement visible. À 390 px, la table défile horizontalement dans son panneau sans élargir la page.

Le contenu local reste fourni par les données de l’univers. Le système 0:0 contient 3 positions et le système 3:0 en contient 5; les valeurs et actions fictives de la maquette ne sont pas reprises.

## Vérification

- Playwright Chromium connecté : /carte répond 200 à 1440×1000, 768×1024 et 390×844.
- Largeur document / viewport : 1440/1440, 768/768 et 390/390.
- La sélection du système 3:0 via le contrôle existant ouvre /carte?galaxy=2&system=14, affiche le titre « Système 3:0 · Système 02 » et les 5 positions du système.
- Aucune modification de code n’était requise sur cette page pendant cette passe.

## Captures

- docs/capture de validation supprimee
- docs/capture de validation supprimee
- docs/capture de validation supprimee
- docs/capture de validation supprimee

Cette revue ne clôt pas l’audit visuel global ni la phase 12.


---

# Phase 12 — passe visuelle 08 : Bâtiments

Date : 01/11/2026. Route évaluée : /planetes/82?section=buildings.

## Résultat de la revue

Le catalogue reprend la disposition de travail du wireframe : cartes de construction à gauche et activité de colonie à droite. Sur desktop comme sur tablette, les cartes restent en deux colonnes avec le rail visible. Sur mobile, elles passent en une colonne; les 13 entrées réelles restent disponibles.

Les cartes supplémentaires par rapport aux six exemples Figma viennent du catalogue de jeu réel et sont conservées.

## Vérification

- Playwright Chromium connecté : réponse 200 à 1440×1000, 768×1024 et 390×844.
- Largeur document / viewport : 1440/1440, 768/768 et 390/390.
- Le filtre « Stockage » réduit la liste de 13 à 5 cartes visibles, sans débordement de page.
- Aucun ordre de construction n’a été lancé pendant cette revue.

## Captures

- docs/capture de validation supprimee
- docs/capture de validation supprimee
- docs/capture de validation supprimee
- docs/capture de validation supprimee

Cette revue confirme la présentation et le filtre de catégorie; elle ne valide pas les règles économiques ni la file backend.


---

# Phase 12 — passe visuelle 09 : Chantier spatial et Défenses

Date : 01/11/2026. Routes évaluées : /planetes/82?section=ships et /planetes/82?section=defenses.

## Résultat de la revue

Les deux vues utilisent le gabarit commun catalogue à gauche / activités de colonie à droite, avec les filtres en tête. Le chantier conserve 14 entrées du catalogue de jeu réel; le wireframe n’en montre que quatre exemples. L’écran Défenses affiche l’unique entrée actuellement disponible dans le catalogue local. Les données de maquette ne sont pas utilisées pour combler cette différence.

## Vérification

- Playwright Chromium connecté : les deux routes répondent 200 à 1440×1000, 768×1024 et 390×844.
- Largeur document / viewport : identique aux trois tailles pour les deux routes.
- Le filtre « Utilitaires » du chantier réduit les 14 cartes à 3 : Vaisseau colonie, Sonde exploratrice et Transporteur.
- Aucun lancement de production n’a été soumis.
- Les défenses restent distinctes des bâtiments et des vaisseaux dans les textes et parcours existants.

## Captures

- docs/capture de validation supprimee
- docs/capture de validation supprimee
- docs/capture de validation supprimee
- docs/capture de validation supprimee
- docs/capture de validation supprimee
- docs/capture de validation supprimee
- docs/capture de validation supprimee

La revue couvre la présentation et le filtrage, pas l’équilibrage ni la file de production backend.


---

# Phase 12 — passe visuelle 10 : technologies

Date : 2026-11-01. Parcours observé dans le navigateur sur l’application locale, route **/planetes/82?section=technologies**, aux largeurs 1440, 768 et 390 px.

## Contrôles effectués

- La route répond et conserve la navigation réelle du jeu.
- Le catalogue contient 10 technologies réelles; le filtre « Propulsion » réduit la liste à l’entrée correspondante.
- L’arbre technologique se déplie et affiche les liens de prérequis. La capture dédiée conserve l’arbre ouvert.
- À ces trois largeurs, aucun débordement horizontal du document n’a été observé. La capture mobile a été reprise après la transition du panneau latéral.
- Cette passe est visuelle et non une validation des règles de recherche ni une soumission de recherche.

## Preuves

- docs/capture de validation supprimee
- docs/capture de validation supprimee
- docs/capture de validation supprimee
- docs/capture de validation supprimee

## État

Écran audité sur desktop, tablette et mobile. Contrôle d’interaction du filtre et du dépliage confirmé. Aucune règle de gameplay n’a été modifiée.


---

# Phase 12 — passe visuelle 11 : flottes

Date : 2026-11-01. Parcours observé dans le navigateur sur la route réelle **/flottes**, à 1440×1000, 768×1024 et 390×844.

## Contrôles effectués

- Le formulaire réel accompagne la composition, le choix de destination et la confirmation; revenir à la destination conserve la composition choisie.
- À ces trois largeurs, aucun débordement horizontal du document n’a été observé. La confirmation et son action restent accessibles sur mobile au-dessus de la navigation basse.
- La prévisualisation appelle le backend réel. Pour le compte d’essai observé, le backend refuse la mission d’exploration faute de sonde exploratrice. Le bouton d’envoi restait actif avant le correctif, malgré cette erreur.
- Le contrôleur désactive maintenant l’envoi avant une estimation et le maintient désactivé sur toute erreur. Après une estimation valide, il restaure l’état initial du bouton afin de conserver un verrou métier rendu par le serveur (dont la règle de carburant en attente). Aucun départ de flotte n’a été soumis pendant cette vérification.
- Le test navigateur isolé tests/browser/fleet-preview-submit-state.spec.mjs vérifie le refus serveur faute de sonde et l’état désactivé du bouton. Résultat : 1/1.
- Le test de parcours existant tests/browser/fleet-wizard.spec.mjs a également passé précédemment : 1/1. Il vérifie les trois étapes et la conservation de la composition.

## Preuves

- docs/capture de validation supprimee
- docs/capture de validation supprimee
- docs/capture de validation supprimee
- docs/capture de validation supprimee
- docs/capture de validation supprimee

## État

Correction ciblée de sûreté d’interface reliée à la prévisualisation du service existant. Le moteur de flotte et ses règles ne sont pas remplacés. Aucune mission n’a été envoyée.


---

# Phase 12 — passe visuelle 12 : thèmes clair et spatial sombre

Date : 2026-11-01. Vérification du thème sur le shell d’accueil et dans les paramètres du compte.

## Contrôles effectués

- Pour un compte sans préférence explicite, le shell expose data-theme=dark; le CSS clair n’est pas chargé.
- Le mode spatial sombre est le défaut visuel. La feuille du wireframe clair n’est chargée qu’après sélection explicite du mode Clair.
- Le test Playwright isolé tests/browser/preferences-accessibility.spec.mjs passe 1/1 : thème par défaut sombre, bascule et persistance du clair, retour au sombre, puis persistance du fuseau horaire, de la réduction du mouvement et des alertes temps réel.
- Les deux captures desktop ont été inspectées visuellement. Le mode clair conserve son apparence claire, et le mode sombre conserve le shell bleu nuit spatial.

## Preuves visuelles

- docs/capture de validation supprimee — mode sombre spatial
- docs/capture de validation supprimee — mode clair préservé
- docs/capture de validation supprimee — mode sombre à 390 px

## État

Thème par défaut, bascule et persistance vérifiés. La passe porte sur le shell et le parcours de préférences; elle ne revendique pas la vérification pixel par pixel de chaque composant des autres pages dans les deux thèmes.
