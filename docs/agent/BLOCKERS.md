# Genesis Reborn — blocages et travaux restants

## État général
Aucun blocage n’empêche l’accès au dépôt ou l’exécution des tests PHP. Le projet n’est pas prêt pour la production. Les phases et écarts du prompt sont détaillés dans PROMPT_PHASE_CROSSCHECK.md.

## Écarts de phases à terminer
- Phase 12 active : poursuivre l’audit écran par écran, des états, des breakpoints 1440/768/390 et du clavier/contraste. Le toast temps réel et le parcours colonisation long ont été validés dans les six scénarios E2E exécutés individuellement. Le lancement agrégé a expiré à 300 s sans rapport. Le shell principal et plus de dix routes authentifiées sont testés à 1440/768/390; les états et permissions des vues restent à vérifier. Prototype public inspecté; UX-01 accès/récupération et UX-15 admin sont incomplets dans le prototype observé.
- Phase 04 partielle : récupération de mot de passe et préférences utilisateur citées par le prompt mais non implémentées. Concevoir et tester un flux sûr.
- Phase 11 partielle : l’administration n’offre pas encore le réglage fin des paramètres de catalogues bâtiments, technologies, vaisseaux et défenses.
- Phase 13 à faire : campagne navigateur complète avec le runner isolé disponible, sécurité, concurrence et performance.
- Phase 14 à valider : CI, observabilité, sauvegarde/restauration et procédures.
- Phase 15 à faire : audit final, équilibrage des valeurs provisoires et verdict production.

## Accès Figma en lecture native\nLe fichier Make n’est pas pris en charge par la session Figma API ici; le lien preview direct exige une connexion dans le navigateur isolé (HTTP 403). Le site figma.site public est inspectable et a été utilisé comme référence. Aucune tâche indépendante du dépôt n’est bloquée par cette limite.\n\n## Paramètres provisoires
Compositions PNJ, statistiques de combat, coûts, productions, durées, stocks initiaux, kit de colonie et plusieurs coefficients restent à valider. Ne pas présenter ces valeurs comme équilibrées. Les règles confirmées par Kévin sur la conquête JcE sont consignées dans REGLES.md et DECISIONS.md.

## Limites d’environnement et données
Compose nécessite --env-file .env.local; les cibles Makefile l’ajoutent. Des essais navigateur antérieurs ont consommé des planètes dans la base partagée; les conteneurs de ces essais ont été nettoyés. Le runner actuel utilise une base éphémère isolée pour les comptes E2E. Aucun déploiement n’a été réalisé ni ne doit l’être sans autorisation spécifique. Aucune relance automatique après quota n’est revendiquée.
