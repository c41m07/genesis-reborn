# Phase 12 — UI, responsive et accessibilité

État au 31/10/2026 : phase en cours. La refonte visuelle professionnelle et responsive existe déjà dans la livraison du 21/10; les corrections clavier/mobile du 31/10 l’améliorent. La revue de l’ensemble des parcours et critères d’accessibilité n’est pas complète. Il faut valider les écrans existants et corriger uniquement les écarts observés; aucune conformité RGAA ou WCAG complète n’est revendiquée.

## Revue et corrections
- Navigation : lien d’évitement vers main, titres structurés, langue de document définie en français, navigation principale nommée, menu mobile avec état ARIA, fermeture Échap et retour du focus. Le piège clavier inclut maintenant les liens, boutons, champs et sélecteurs.
- Formulaires : labels vérifiés; indicateur de focus cyan étendu aux zones de texte; erreurs annoncées comme alertes et confirmations comme statuts.
- Données : titres de tableaux, scopes de colonnes et aria-sort au classement; tableaux défilants horizontalement atteignables au clavier; graphiques de progression nommés.
- Mouvement : préférence prefers-reduced-motion respectée; animations désactivées quand l’utilisateur le demande.
- Messages : possibilité de signaler les messages reçus et de guilde, sans proposer de signaler ses propres messages. La console admin affiche correctement les signalements de guilde.
- Rapports : titre explicite « Rapports de combat et d’espionnage »; assertions navigateur alignées sur le libellé.

## Vérifications
- Capture mobile à 390 px inspectée : ressources et contenu restent dans la largeur de l’écran; menu latéral fermé; parcours de navigation testé dans Chromium avant l’étape finale du scénario. Capture : docs/validation/mobile-apercu.png.
- make test : 78 tests / 406 assertions.
- make lint : PHP, YAML, Twig, conteneur Symfony et schéma Doctrine valides.
- make validate : configuration Compose valide.
- make assets : compilation production réussie.
- La suite Playwright a été relancée dans une base MariaDB éphémère, sans écrire les captures dans docs/validation : 4/6 scénarios réussis (responsive/navigation, diplomatie/guilde, files et prérequis technologiques). Deux restent ouverts : la notification de fin de construction n’apparaît pas dans le toast temps réel; le scénario colonisation était encore « En route » après 60 s, ce qui a conduit à relever son attente à 160 s mais ce correctif n’a pas encore été relancé.

## Limites à traiter
- Un runner isolé a été ajouté; configuration Compose et syntaxe Bash validées. La base et les services de test sont détruits par le script à la fin. La notification temps réel doit être diagnostiquée; le test de colonisation doit être rejoué avec son délai mis à jour.
- Aucun outil axe/RGAA automatisé n’est installé; la revue manuelle et les contrôles existants ne suffisent pas à revendiquer une conformité exhaustive.
- Les captures produites durant les parcours restent des artefacts locaux non inclus dans le commit.
