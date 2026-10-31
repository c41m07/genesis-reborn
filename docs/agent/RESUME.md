# Genesis Reborn — reprise

## Au démarrage
1. Lire PROMPT_PHASE_CROSSCHECK.md, PROGRESS.md, MASTER_PLAN.md, BLOCKERS.md et TEST_STATUS.md.
2. Vérifier la branche feat/genesis-reborn-production-completion et le statut Git. Ne jamais écraser les modifications préexistantes.
3. Lire UI_AUDIT.md, UI_SCREEN_MATRIX.md, FIGMA_HANDOFF.md, UX_FLOWS.md et REQUIREMENTS_MATRIX.md; reprendre UX-01/UX-15 et les parcours incomplets, breakpoint 768, accessibilité et colonisation E2E.
4. Garder la suite navigateur isolée ; compose.browser-test.yaml a été ajusté pour servir Mercure sur BROWSER_HOST, et les tests responsive/notification ciblés passent.
5. Après phase 12, terminer les critères rouverts des phases 04 (récupération de mot de passe, préférences) et 11 (outils d’équilibrage), puis poursuivre les phases 13–15.

## Position vérifiée au 31/10/2026
- 9/15 phases validées : 01–03 et 05–10. Phase 04 partielle, phase 11 partielle, phase 12 active; 13–15 à faire. Voir PROMPT_PHASE_CROSSCHECK.md pour la comparaison détaillée avec le prompt initial.
- Validation PHP rapportée : 78 tests / 406 assertions; lint, validate, assets et installation vierge (14 migrations) dans TEST_STATUS.md. Cette session : Playwright responsive/navigation 1/1 pass; construction + notification Mercure 1/1 pass après ajustement de l’URL dans l’overlay. Run agrégé expiré à 300 s sans rapport; les six tests E2E réussissent quand ils sont lancés séparément, dont le parcours colonisation (3,1 min).
- Derniers commits existants : e8273b1 puis historique 17be81d/c3ded73. Les nouveaux changements UI, test runner et matrices sont en attente de commit; vérifier git status avant reprise.
- Les données et fichiers de validation contiennent des modifications locales préexistantes; ne pas réinitialiser, clean ou restaurer ni toucher aux PDF, scripts, binaires ou captures sans nécessité et inspection explicite.
- Aucun déploiement/publication. Aucun réveil automatique après quota n’a été programmé; une reprise manuelle est requise.
