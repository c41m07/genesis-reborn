# Genesis Reborn — audit UI/UX

Date : 31/10/2026. Statut : phase 12 EN COURS. Source applicative : routes/controllers et templates Twig. Référence de conception : site public Figma Make transmis par Kévin.

## État du dépôt réel

L’application possède les routes et templates recensés dans UI_SCREEN_MATRIX.md. Elle utilise la stack Symfony/Twig/Stimulus/Encore existante. Les pages authentification, univers, colonies, carte, flottes, rapports, social, profil et administration existent. Récupération sécurisée de mot de passe et préférences persistées restent à faire (phase 04). L’outil d’équilibrage des catalogues reste incomplet (phase 11).

## État du prototype publié

Le prototype contient un shell cohérent, une navigation qui couvre les principaux domaines et des variantes desktop/mobile. Les données et interactions sont des simulations. Il est utile comme cible UX mais ne prouve ni que les actions fonctionnent côté application, ni que tous les états et rôles existent. L’écran admin n’est pas accessible comme parcours dans l’aperçu testé ; le rôle y est seulement mentionné dans Paramètres.

## Contrôles réalisés

- Ouverture Figma Make directe par Playwright : HTTP 403 et formulaire de connexion dans le navigateur isolé.
- Site publié : HTTP 200, title chargé, aucun événement pageerror observé au chargement.
- Écrans principaux parcourus au desktop 1440 px ; navigation « architecture » annonce 15 écrans et 7 parcours.
- À 390 px, mesure scrollWidth = innerWidth = 390 ; menu mobile Plus présente les fonctions de navigation complète. À 768 px, scrollWidth = innerWidth = 768 et le bouton Ouvrir la navigation présente 15 entrées. Il manque aria-expanded sur ce bouton dans le prototype.
- App locale : test Playwright responsive/navigation ciblé réussi, 1/1.
- App locale : test réel de construction/notification ciblé réussi après correction du runner E2E ; détails dans TEST_STATUS.md.
- Les six tests navigateur de la suite ont ensuite réussi en exécution individuelle : responsive/navigation, toast Mercure, missions complètes (3,1 min), alliances, files, bénéfices/prérequis. Le lancement agrégé n’a pas rendu son rapport avant 300 s; aucun échec de test n’est inféré de son expiration.

## Limites

- Tablette 768 px pas vérifiée.
- Parcours UI PNJ, rapports privés, erreurs, états vides, permissions administrateur et responsive complet pas encore traversés.
- Pas de rapport RGAA/WCAG exhaustif ni de vérification lecteur d’écran.
- Pas de comparaison frame-par-frame entre Figma et Twig ; le fichier Make natif reste non inspectable par connecteur.
- Pas de validation de direction artistique/logo/assets/licences.

La phase demeure PARTIELLE jusqu’à couverture écran-par-écran, parcours, états, trois breakpoints, accessibilité et non-régression.
