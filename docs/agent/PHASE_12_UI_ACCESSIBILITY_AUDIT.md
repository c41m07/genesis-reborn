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
- Le lancement Playwright agrégé a expiré à 300 s sans restituer de rapport. Les six scénarios ont ensuite été exécutés séparément avec le runner isolé et ont réussi : responsive/navigation, construction/toast, chantier/colonisation/transport/exploration (3,1 min), alliances, files et prérequis technologiques. Ce résultat ne vaut pas rapport agrégé.

## Résultats et limites au 31/10/2026
- Le site public Figma fourni répond 200; pages clés parcourues au desktop et aux vues 768/390 px. Le prototype annonce 15 écrans et 7 parcours, avec données fictives et actions en mémoire. UX-01 et UX-15 restent incomplets ou indisponibles dans l’aperçu observé; voir FIGMA_HANDOFF.md.
- Le shell principal de l’application est validé à 1440, 768 et 390 px par un test Playwright dédié. Les vues secondaires, états négatifs et permissions restent à vérifier.
- Aucun outil axe/RGAA automatisé n’est installé; la revue manuelle actuelle ne suffit pas à revendiquer une conformité exhaustive.
- Le fichier Make natif reste inaccessible au connecteur et renvoie un écran de connexion via Playwright; il n’y a pas de comparaison frame par frame. La provenance/licence des assets et la validation complète de la direction artistique restent ouvertes.
- Les matrices REQUIREMENTS_MATRIX.md, UI_SCREEN_MATRIX.md, UX_FLOWS.md, ASSET_MANIFEST.md, UI_AUDIT.md et FIGMA_HANDOFF.md tracent les exigences et preuves.
- Un runner isolé existe; la correction dynamique du public URL Mercure et le parcours de colonisation long ont été vérifiés. Le shell principal de l’application est validé à 1440/768/390 px; vérifier les vues secondaires ainsi que chaque état négatif/permission.
- Aucun outil axe/RGAA automatisé n’est installé; la revue manuelle et les contrôles existants ne suffisent pas à revendiquer une conformité exhaustive.
- Les captures produites durant les parcours restent des artefacts locaux non inclus dans le commit.
