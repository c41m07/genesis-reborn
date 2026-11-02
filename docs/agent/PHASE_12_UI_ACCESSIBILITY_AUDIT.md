# Phase 12 — UI, responsive et accessibilité

Mise à jour : 2 novembre 2026. L’application et les principaux parcours sont livrés; l’état strict reste PARTIEL pour la revue manuelle RGAA/WCAG exhaustive avec lecteur d’écran. Les relevés ci-dessous datés du 1er novembre sont conservés comme preuves historiques; ils ne décrivent pas les suites les plus récentes. Pour l’état courant, voir ALPHA_READINESS.md et TEST_STATUS.md. La maquette guide la structure UX; les fonctions métier viennent des services réels. Aucune conformité d’accessibilité exhaustive n’est revendiquée.

## Revue et corrections
- Navigation : lien d’évitement vers main, titres structurés, langue de document définie en français, navigation principale nommée, menu mobile avec état ARIA, fermeture Échap et retour du focus. Le piège clavier inclut maintenant les liens, boutons, champs et sélecteurs.
- Formulaires : labels vérifiés; indicateur de focus cyan étendu aux zones de texte; erreurs annoncées comme alertes et confirmations comme statuts. Les états d’espionnage détecté/renseignements obtenus et d’attente carburant sont sémantisés comme statuts.
- Données : titres de tableaux, scopes de colonnes et aria-sort au classement; tableaux défilants horizontalement atteignables au clavier; graphiques de progression nommés.
- Mouvement : préférence prefers-reduced-motion respectée; animations désactivées quand l’utilisateur le demande.
- Messages : possibilité de signaler les messages reçus et de guilde, sans proposer de signaler ses propres messages. La console admin affiche correctement les signalements de guilde.
- Rapports : titre explicite « Rapports de combat et d’espionnage »; assertions navigateur alignées sur le libellé.

## Vérifications
- Capture mobile à 390 px inspectée : ressources et contenu restent dans la largeur de l’écran; menu latéral fermé; parcours de navigation testé dans Chromium avant l’étape finale du scénario. Capture : docs/validation/mobile-apercu.png.
- Historique : 78 tests / 406 assertions au 31/10; la suite actuelle complète passe 93 tests / 477 assertions (01/11), avec les tests du reset catalogue et des accès admin.
- Lint actuel : 25 Twig, 63 YAML, conteneur Symfony et schéma Doctrine valides (01/11).
- make validate : configuration Compose valide.
- make assets : compilation production réussie.
- Le lancement Playwright agrégé a expiré à 300 s sans restituer de rapport. Les six scénarios historiques ont réussi séparément avec le runner isolé. Trois tests UI supplémentaires ont aussi passé individuellement; leur regroupement a deux fois rencontré une erreur MariaDB 1020 pendant la deuxième adhésion. Une reproduction isolée de deux adhésions successives passe; investigation de la collision à poursuivre.

## Résultats et limites au 01/11/2026
- Le site public Figma fourni répond 200; les 15 vues ont été ouvertes à 1440/768/390 avec titres attendus et sans débordement. Les actions du prototype restent fictives; UX-01 et UX-15 ne sont pas des vues interactives du prototype, tandis que l’application contient les écrans d’accès et admin. Voir FIGMA_HANDOFF.md.
- Le shell et plus de dix routes authentifiées sont validés aux trois largeurs. Les tests ciblés couvrent noms accessibles, refus 403 avant promotion, succès de construction, rapport vide, espionnage détecté et rapport privé, et administration catalogue. Le regroupement des anciens tests sociaux a déjà rencontré une collision MariaDB 1020 intermittente; une adhésion successive isolée passe.
- Un contrôle Playwright ciblé ne trouve pas de lien, bouton ou champ visible sans nom accessible sur les routes principales. Aucun outil axe/RGAA automatisé n’est installé; ce smoke test et la revue manuelle ne suffisent pas à revendiquer une conformité exhaustive.
- Le fichier Make natif reste inaccessible au connecteur et renvoie un écran de connexion via Playwright; il n’y a pas de comparaison frame par frame. Le nouveau logo et l’illustration de colonie sont générés et intégrés; les SVG historiques ont depuis été remplacés par des tracés originaux documentés dans ASSET_MANIFEST.md. Les images générées ont été converties en WebP et ramenées à 186 KB au total.
- Les matrices REQUIREMENTS_MATRIX.md, UI_SCREEN_MATRIX.md, UX_FLOWS.md, ASSET_MANIFEST.md et FIGMA_HANDOFF.md tracent les exigences et preuves.
- Un runner isolé existe; la correction dynamique du public URL Mercure et le parcours de colonisation long ont été vérifiés. Le shell principal et plus de dix routes sont vérifiés à 1440/768/390 px; compléter les parcours, états négatifs et permissions écran par écran.
- Les captures produites durant les parcours restent des artefacts locaux non inclus dans le commit.

## Réaudit Playwright — 01/11/2026

- Runner isolé Linux : responsive-breakpoints 1/1, ui-routes-responsive 1/1, ui-accessibility-smoke 1/1. Contrôles à 1440/768/390 px, routes de navigation et noms accessibles des contrôles visibles.
- npm run build réussi; 5 fichiers produits.
- La campagne agrégée de neuf scénarios a atteint la limite de 300 s avant retour du rapport. Les conteneurs isolés ont été nettoyés; aucun succès ou échec agrégé n’est attribué.
- Depuis ce relevé, le parcours conquest-report.spec.mjs passe 1/1 : détail du rapport, pertes, issue du combat et résultat de conquête PNJ sont affichés; tableau des pertes atteignable au clavier, responsive 1440/768/390 et accès direct interdit à un non-participant (404) vérifiés. Le parcours preferences-accessibility.spec.mjs passe 1/1 : réglages reduced-motion, alertes temps réel et fuseau persistés. Le parcours moderation-flow.spec.mjs passe 1/1 après correction d’un appel de service erroné : message signalé, CSRF admin invalide rejeté en 403, décision métier invalide annoncée par une alerte accessible, dossier classé sans suite puis second signalement masqué et vérifié côté conversation. keyboard-navigation.spec.mjs passe 1/1 : lien d’évitement visible, focus-visible et fonctionnel sur toutes les routes principales à 1440/390. password-reset-flow.spec.mjs passe 1/1 : demande générique, réinitialisation, en-têtes anti-fuite, jeton unique et reconnexion. Les vues UX-01/UX-15 restent propres à l’application, absentes de l’aperçu Figma observé. Le test research-flow.spec.mjs passe 1/1 : construction du laboratoire, déblocage, lancement, progression de file et application effective du niveau 1 d’Ingénierie fondamentale dans Turbo. La refonte visuelle complète alignée au wireframe, les autres états UI, la revue des permissions, le contraste des overlays et l’accessibilité intégrale restent à traiter avant validation. La phase demeure en cours.

- Nouvelle preuve UX-15 : admin-catalogue.spec.mjs 1/1; connexion admin, catalogue, sauvegarde/réinitialisation et responsive aux trois tailles. Le tableau de bord Figma public a été inspecté à 1440/768/390 et 15 vues navigables ont été ouvertes.
- Restent ouverts : couverture écran par écran des états négatifs et permissions, autres branches de résultat métier, revue clavier/contraste exhaustive et vérification assistive. La phase demeure en cours.
- espionage-report.spec.mjs : 1/1; vue de rapport privé après détection d’une sonde PNJ, retour vers le centre des rapports et titres accessibles vérifiés.

- Contraste : huit paires de jetons principales avaient 4,83:1 à 16,39:1. Un échantillon Playwright sur toutes les routes principales et 1440/768/390 a trouvé le suffixe de niveau à 4,36:1; la teinte corrigée (#7893af) atteint 5,23:1 sur le fond observé, et le test automatisé ne relève plus de texte visible sous 4,5:1 sur fonds opaques échantillonnés. Les gradients, pseudo-éléments, overlays et états désactivés ne sont pas couverts; aucune conformité RGAA/WCAG exhaustive n’est revendiquée.

- Contrôle post-correction du parcours de modération : make test 93/477; make lint valide PHP, 63 YAML, 25 Twig, le conteneur Symfony et le schéma Doctrine; make validate valide Compose. Le contrôle diff --check est passé.


## Refonte high-fidelity démarrée — 01/11/2026

- Écart de direction identifié par comparaison réelle : la référence publique est claire, avec surfaces blanches, navigation latérale claire et cartes sobres; l’application avait un thème bleu nuit. Un thème clair partagé est maintenant appliqué et compilé.
- La page d’accueil a été restructurée autour d’un bandeau planétaire, de quatre indicateurs alimentés par les vraies données, de files d’opérations, de colonies cliquables, des flottes en mouvement et du journal des notifications. Les données affichées proviennent des services et entités existants.
- Captures UI vérifiées : docs/validation/colonie-desktop.png et docs/validation/colonie-mobile.png. Le parcours d’accueil passe 1/1, les routes responsive 1/1 à 1440/768/390 et le contraste échantillonné repasse 1/1 après ajustement des couleurs.
- Cette livraison démarre l’alignement visuel; elle ne valide pas la refonte complète. Les vues restantes doivent être comparées et travaillées individuellement, sans retirer les fonctions du brief.

- wireframe-theme-screens.spec.mjs passe 1/1 pour carte, flottes, diplomatie, messages et rapports sous le thème clair à 1440 px; les captures servent de base au contrôle écran par écran.


## Itération de refonte globale — 01/11/2026

- Le shell partagé suit davantage la hiérarchie du wireframe : Commandement, Développement, Opérations, Communauté et Compte; liens conservés vers les fonctionnalités et routes existantes.
- Barre de ressources compacte sur grand écran et transformée en grille à deux colonnes sur mobile. La sélection de colonie, les cinq ressources du jeu, l’horloge et le profil restent présents.
- Les titres, espacements, cartes et navigation utilisent désormais les surfaces claires et les bordures neutres du prototype. Espionnage mène à /flottes?mission=spy.
- Captures Playwright étendues : Carte, Flottes, Diplomatie, Messages, Rapports, Classement, Univers, Profil, Préférences, quatre sections de colonie, Connexion, Inscription, mot de passe oublié, Administration et catalogue admin. Voir docs/validation/*-theme.png.
- Preuves : make assets; make lint; wireframe-theme-screens.spec.mjs 2/2; admin-catalogue.spec.mjs 1/1; ui-routes-responsive.spec.mjs 1/1 sur 1440/768/390; ui-contrast-sampled.spec.mjs 1/1. Un débordement à 390 px a été localisé sur les ressources et corrigé.
- Phase 12 reste EN COURS : la cohérence globale est renforcée, mais l’audit et l’ajustement visuel de chaque état métier, rapport détaillé et branche d’erreur ne sont pas achevés. Pas de commit créé.


## Revue visuelle des vues secondaires — 01/11/2026

- Préférences : cases à cocher remises en lignes de réglage avec libellés associés; le flux d’accessibilité et de sauvegarde passe toujours.
- Messages : grille dédiée à deux colonnes qui répartit les vues de sélection et de canal sur la largeur disponible.
- Classement : tableau lisible, colonne Action explicite et défilement horizontal contenu dans le tableau sur petit écran.
- Captures mobile ajoutées pour le tableau de bord et la carte à 390 px.
- Vérifications après ces changements : wireframe-theme-screens.spec.mjs 2/2; ui-routes-responsive.spec.mjs 1/1; preferences-accessibility.spec.mjs 1/1; make assets et make lint réussis; git diff --check propre.
- Les vues ont été ouvertes, mais les parcours de rapports détaillés, conversations avec activité, diplomatie avec plusieurs membres, erreurs et états d’administration restent à confronter à la maquette. Phase 12 toujours EN COURS.


## Revalidation complète — 01/11/2026

Après correction des droits du cache de test généré (répertoire Twig test root:root non modifiable par l’utilisateur PHPUnit 1000), la suite complète passe : make test, 93 tests / 477 assertions. L’échec précédent était une permission du cache, résolue avant le succès. Les vues Préférences, Communications et Classement ont aussi leurs corrections page spécifiques; les captures ont été régénérées. La phase 12 demeure en cours et les états détaillés encore listés restent à inspecter.


## États métier et captures détaillés — 01/11/2026

- Rapports de conquête : capture bureau et mobile du résultat + pertes stylisées; conquest-report.spec.mjs passe 1/1.
- Espionnage : capture bureau et mobile du rapport privé détecté; espionage-report.spec.mjs passe 1/1 après alignement de son sélecteur de navigation sur le nouveau libellé.
- Diplomatie : capture de deux guildes ayant une alliance réciproque; orbit-request.spec.mjs passe 1/1.
- Flotte : capture de la mission d’orbite préparée et de son vol actif; test d’orbite rendue déterministe en exécutant ses requêtes d’accélération existantes.
- Les scénarios conquest et espionnage doivent chacun conserver une base isolée : le premier consomme le PNJ unique utilisé par le second lorsqu’ils partagent une base.
- make lint et make test réussis après les changements; PHPUnit 93 tests / 477 assertions; git diff --check propre. Phase 12 en cours, aucune nouvelle phase validée.


## Accessibilité et rapports détaillés — contrôle du 01/11/2026

- keyboard-navigation.spec.mjs : 1/1 sur les routes principales à 1440 et 390 px; lien d’évitement et focus visibles.
- ui-accessibility-smoke.spec.mjs : 1/1; contrôles visibles nommés.
- conquest-report.spec.mjs : 1/1; rapport combat bureau/mobile et tableau de pertes harmonisé.
- espionage-report.spec.mjs : 1/1 isolé; rapport privé bureau/mobile.
- orbit-request.spec.mjs : 1/1 isolé; relation de guilde et flotte orbitale.
- make lint, make test (93/477) et git diff --check réussis après les changements de template.


## Comparaison Figma et navigation mobile — 01/11/2026

- Visite Playwright de la maquette publiée : les écrans Rapports, Galaxie, Classements, Messages, Architecture du prototype et Paramètres ont été observés.
- La page Rapports est réorganisée en centre unique avec filtres Tous, Combats, Économie et Espionnage, lignes ouvrables et états vides; les rapports de combat et d’espionnage existants restent accessibles. Le filtre Économie signale sans inventer de données tant que le jeu n’a pas de rapport économique persistant.
- La barre mobile partagée propose Accueil, Planètes, Galaxie, Flottes et Plus, avec ouverture clavier du menu complet. Ressources réparties sur deux colonnes.
- Le test wireframe passe 2/2 après vérification de chaque écran applicatif à 390 px, sans débordement, et de l’ouverture du menu Plus. Les filtres rapports sont parcourus par le test. Captures desktop et mobile régénérées dans docs/validation/*-theme.png.
- npm run build, make lint réussis. Phase 12 EN COURS : architecture spécifique des pages Galaxie, Flottes, Alliances, Messages et Paramètres encore à rapprocher; états métier restants non exhaustivement vérifiés.

- Les routes Flottes et Espionnage ont désormais des intitulés et un lien actif distincts; si aucune sonde n’est disponible, l’écran explique le prérequis. Les libellés Alliances et Messages suivent aussi ceux du prototype. Les captures sont incluses dans le nouveau contrôle complet.


## Livraison visuelle — état strict de la phase — 01/11/2026

**État strict au 2 novembre : PARTIELLE (phase 12/15).** La refonte visuelle des vues principales est livrée selon les preuves listées ci-dessous. À la mise à jour du 01/11/2026, P12-STATE-01 et P12-ART-01 sont techniquement validées; P12-A11Y-01 reste partielle jusqu’à un audit manuel RGAA/WCAG avec lecteur d’écran. La phase 12 reste donc ouverte. Les fonctions existantes restent accessibles; les écrans sans données métier affichent un état vide explicite. Le prototype public a été parcouru pour ses vues disponibles et ses structures visuelles ont guidé les routes correspondantes.

Preuves de validation :
- Captures Playwright desktop et mobile dans docs/validation/, incluant états de rapport, navigation et catalogue admin.
- wireframe-theme-screens.spec.mjs 2/2; ui-routes-responsive.spec.mjs 1/1 à 1440/768/390; responsive-breakpoints.spec.mjs 1/1; fleet-wizard.spec.mjs 1/1.
- conquest-report.spec.mjs 1/1; espionage-report.spec.mjs 1/1; orbit-request.spec.mjs 1/1; moderation-flow.spec.mjs 1/1; leaderboard-contact.spec.mjs 1/1; colony.spec.mjs (chantier, estimation, colonisation) réussi.
- keyboard-navigation.spec.mjs 1/1; ui-accessibility-smoke.spec.mjs 1/1; ui-contrast-sampled.spec.mjs 1/1 après corrections; preferences-accessibility.spec.mjs 1/1; navigation responsive, focus retour et aria-expanded couverts.
- make test: 93 tests / 477 assertions; make lint, make validate, npm run build et git diff --check réussis.

La validation ne constitue pas une déclaration de conformité exhaustive WCAG/RGAA : l’inspection contraste est échantillonnée, aucun lecteur d’écran tiers ni audit externe n’a été exécuté. Les constats des passes antérieures ci-dessous sont historiques; le registre final et ses preuves sont dans REQUIREMENTS_MATRIX.md et UI_STATE_COVERAGE.md. Le Figma est un wireframe multi-écrans et non une spécification pixel/frame complète. Aucune décision de gameplay créative n’est en attente pour les écrans UI.

**Suite : réaliser la revue manuelle d’accessibilité avec lecteur d’écran et mettre à jour REQUIREMENTS_MATRIX.md à partir de ses résultats.**


## Contrôle complémentaire d’accessibilité — 01/11/2026

- ui-accessibility-smoke.spec.mjs : 1/1; noms accessibles présents sur les liens, boutons et champs visibles des routes couvertes.
- keyboard-navigation.spec.mjs : 1/1; lien d’évitement et focus visibles/cliquables aux largeurs desktop et mobile.
- ui-contrast-sampled.spec.mjs : 1/1 aux largeurs 1440, 768 et 390; les textes visibles sur fonds unis échantillonnés respectent les seuils du test.
- ui-axe-audit.spec.mjs : 1/1; aucune violation axe sur 21 écrans à 1440 et 390 px (WCAG A/AA 2.0/2.1, WCAG 2.2 AA et bonnes pratiques). Les défauts trouvés ont été corrigés : régions principales imbriquées, navigation non nommée, ordre de titres, cibles tactiles et nom accessible du lien profil.
