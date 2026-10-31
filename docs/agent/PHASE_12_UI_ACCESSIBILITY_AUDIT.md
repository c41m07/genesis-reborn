# Phase 12 — UI, responsive et accessibilité

État au 31/10/2026 : phase en cours. Une revue ciblée a permis de corriger des défauts clavier/mobile, mais cela ne réalise pas la refonte UI/UX attendue : parcours complets et cohérents, responsive abouti, identité visuelle homogène et validation d’accessibilité. Aucune conformité RGAA ou WCAG complète n’est revendiquée.

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
- make test-browser : 3 des 6 scénarios ont réussi lors de l’exécution. Un échec était dû à l’ancien intitulé attendu des rapports, corrigé ensuite. Deux scénarios de création de compte ont été bloqués car l’univers Turbo n’avait plus de planète libre dans la base de développement partagée. Le scénario responsive a atteint son assertion finale avant la correction du libellé.

## Limites à traiter
- La suite Playwright utilise la base locale partagée; elle consomme des planètes de départ et n’isole pas ses données. Pour cette raison, seuls 3/6 scénarios ont abouti. Préparer une base isolée comme tâche de test, puis terminer les validations de la refonte dans cette phase 12.
- Aucun outil axe/RGAA automatisé n’est installé; la revue manuelle et les contrôles existants ne suffisent pas à revendiquer une conformité exhaustive.
- Les captures produites durant les parcours restent des artefacts locaux non inclus dans le commit.
