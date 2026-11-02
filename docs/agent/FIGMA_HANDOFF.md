# Genesis Reborn — handoff Figma

Mise à jour : 01/11/2026.

## Liens

- Aperçu Figma Make transmis par Kévin : [Genesis Reborn Wireframe](https://www.figma.com/make/bPgzJg07LClrAqSRuMubKG/Genesis-Reborn-Wireframe?t=Lkn2cEfN92PRSzy6-0&fullscreen=1)
- Site publié correspondant : [chili-sunset-36220944.figma.site](https://chili-sunset-36220944.figma.site/)

L’API officielle Figma disponible dans cet environnement refuse les sessions pour les fichiers de type Make. Le lien de preview Make demande une connexion depuis un navigateur isolé (HTTP 403, page « Sign up or Log in »). Le site publié répond HTTP 200 et a été inspecté en lecture seule avec Playwright ; aucun changement n’a été envoyé à Figma.

## Constat contrôlable

La phase 12 utilise le wireframe publié comme référence de navigation, de hiérarchie des écrans, de composants et de comportement responsive pour la refonte complète de l’application. Les fonctions requises qui ne figurent pas dans le prototype doivent rester accessibles et cohérentes dans l’interface finale.


- Le projet contient désormais un emblème Genesis Reborn et une illustration de monde originaux générés pour le jeu et intégrés dans l’application; voir ASSET_MANIFEST.md. Les visuels sont livrés en WebP optimisé.

- Titre de document rendu : genesisrebornwirefrime (orthographe affichée par le site).
- La page se présente comme « WIREFRAME INTERACTIF V.01 » et « Atelier UX · Prototype fonctionnel basse fidélité, données fictives et actions simulées en mémoire ».
- L’architecture annonce 15 écrans et 7 parcours.
- Aperçu desktop 1440 × 1000 chargé sans erreurs JavaScript ; contenu observé : shell, tableau de bord, planète active, ressources, activités, files, colonies, flottes et journal.
- À 390 px, le document avait une largeur de 390 px (aucun débordement horizontal mesuré). Barre mobile Accueil/Planètes/Galaxie/Flottes/Plus ; Plus ouvre le menu latéral complet.
- Les écrans ouverts dans le prototype couvrent tableau de bord, planètes, bâtiments, technologies, chantier, défenses, flottes, galaxie, rapports, espionnage, alliances, classements, messages, architecture/parcours et paramètres.
- Les défenses sont correctement décrites comme unités de combat stationnaires destructibles qui ne peuvent pas partir en mission.
- La carte décrit séparément colonisation d’un monde vierge et conquête JcE d’un PNJ, et indique la conservation de son économie.
- Les actions restent des simulations en mémoire : elles ne sont pas raccordées au backend de Genesis Reborn.

## Écarts et limites

- L’écran UX-01 (connexion, inscription et récupération sécurisée) n’est pas accessible comme vue depuis le prototype observé ; l’architecture en mentionne l’accès sans fournir de page testée.
- L’écran UX-15 Administration n’a pas de route/entrée dédiée dans le menu. Paramètres indique un rôle admin simulable, mais « Simuler un utilisateur administrateur » n’est pas rendu comme bouton ou contrôle interactif.
- Rapports n’a été vérifié qu’en liste ; le détail d’un rapport de combat et ses permissions ne sont pas confirmés.
- Mise à jour 01/11 : application réelle parcourue pour espionnage et rapport privé, accès admin refusé puis autorisé, ainsi que combat/conquête PNJ et rapport détaillé. Les erreurs métier et autres issues restent à vérifier; l’aperçu Figma demeure un prototype simulé.
- À 768 px, Playwright charge la vue générale sans débordement horizontal (scrollWidth = 768). Le bouton « Ouvrir la navigation » ouvre les 15 entrées du menu; son état ne fournit pas aria-expanded. Le menu complet mobile fonctionne également via Plus à 390 px. Accessibilité clavier/lecteur d’écran au-delà de ces contrôles et structure Figma native/Auto Layout non vérifiées.
- Ressources, coûts, durées, noms et stats du prototype sont fictifs. Les références de jeu ne peuvent pas valider l’équilibrage métier.

## Organisation Figma requise par le brief

Le brief demande les sections/pages : 00 Architecture, 01 Composants wireframe, 02 Desktop, 03 Tablette, 04 Mobile, 05 Flux et prototypes, 06 États et exceptions, 07 Handoff, 08 Maquettes high-fidelity. La structure interne du fichier Make n’étant pas lisible par l’API, leur présence n’est pas déclarée.

À faire pour valider le handoff : obtenir un accès authentifié/public exploitable au fichier Make, vérifier chaque flow, inventorier toutes les frames et variantes, comparer 1440/768/390, documenter états, liens entre frame et écran/code, puis produire les écarts et corrections au fichier. L’application doit néanmoins continuer sur sa stack réelle sans attendre ces contrôles.

## Inspection Playwright complémentaire — 01/11/2026

- Le site publié a répondu HTTP 200 dans Chromium Linux. Titre affiché : genesisrebornwirefrime; vue principale : « Vue générale. »; aucune erreur JavaScript de page observée.
- Les 15 entrées de navigation visibles (vue générale, planètes, bâtiments, technologies, chantier, défenses, flottes, galaxie, rapports, espionnage, alliances, classements, messages, architecture, paramètres) ont été cliquées une à une; chaque vue a présenté son titre attendu et est restée à 1440 px sans débordement.
- Le tableau de bord a été mesuré à 1440, 768 et 390 px : largeur du document identique au viewport. Ces mesures concernent le prototype publié; l’application est vérifiée séparément dans PHASE_12_UI_ACCESSIBILITY_AUDIT.md.

- Vérification responsive élargie le 01/11 : les 15 vues ont été sélectionnées une à une à 768 px puis 390 px; chaque page affichait le titre attendu, avec scrollWidth respectivement 768 et 390. La navigation tablette/mobile a été ouverte pendant le parcours.
