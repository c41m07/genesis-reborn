# Phase 15 — audit final et limites de production

Mise à jour : 2 novembre 2026.

## Verdict

Genesis Reborn est **jouable en Alpha contrôlée** et **n’est pas déclaré prêt pour la production**. Les quinze phases ont été auditées; quatorze sont strictement validées. La phase 12 reste partielle pour la revue manuelle d’accessibilité exhaustive avec lecteur d’écran.

L’orientation de rythme et les valeurs initiales ont été choisies par Kévin pendant le QCM et sont appliquées dans le projet. L’Alpha doit mesurer la progression réellement obtenue et guider les ajustements; cette phase de mesure n’empêche pas les joueurs de tester le jeu.

## Vérifications locales du 2 novembre

- PHPUnit : 110 tests, 627 assertions — réussi.
- Lint PHP, YAML, Twig, conteneur Symfony et schéma Doctrine — réussi.
- Configuration Docker Compose — valide.
- Build frontend — réussi.
- Playwright isolé : résultats de la campagne de 34 scénarios et limites consignés dans TEST_STATUS.md; aucune réussite complète n’est revendiquée avant le bilan.

Les précédents audits phase 03, 13 et 14 décrivent aussi l’installation propre, les contrôles de sécurité/dépendances et le test jetable de sauvegarde/restauration. Leurs limites de portée restent applicables.

## Points de suivi Alpha

- Mesurer les jalons de progression de début, milieu et fin de partie.
- Observer la fréquence de connexion nécessaire, les temps d’attente, les stocks limitants, les pertes et le coût/temps de reconstitution.
- Comparer les retours des joueurs aux valeurs de départ, puis documenter les modifications retenues.

La définition des paramètres choisis, des calculs dérivés et des risques à suivre est dans BALANCING_QCM_2026-11-02.md.

## Conditions avant production

1. Analyser les mesures et retours Alpha et valider les ajustements de balance.
2. Exécuter et conserver un résultat de CI distant.
3. Exécuter des tests de charge et établir les paramètres de supervision/SLA.
4. Définir et éprouver la sauvegarde distante, son chiffrement, sa rétention et le PRA.
5. Compléter la revue manuelle d’accessibilité avec lecteur d’écran et cadrer la conformité RGAA/WCAG.

Aucun push, déploiement ou publication n’a été effectué. Les critères ligne par ligne restent dans REQUIREMENTS_MATRIX.md; les validations courantes sont dans TEST_STATUS.md.
