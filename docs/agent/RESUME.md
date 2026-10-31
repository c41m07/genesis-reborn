# Genesis Reborn — reprise

## Au démarrage
1. Lire PROMPT_PHASE_CROSSCHECK.md, PROGRESS.md, MASTER_PLAN.md, BLOCKERS.md et TEST_STATUS.md.
2. Vérifier la branche feat/genesis-reborn-production-completion et le statut Git. Ne jamais écraser les modifications préexistantes.
3. Reprendre les contrôles de la phase 12 sur l’interface existante (livraison UI du 21/10), ses parcours utilisateur, états, tailles d’écran et accessibilité; corriger les problèmes observés.
4. Ne pas conclure la phase 12 sur la base des seules corrections CSS/clavier. Garder la suite navigateur isolée des données de développement.
5. Après phase 12, terminer les critères rouverts des phases 04 (récupération de mot de passe, préférences) et 11 (outils d’équilibrage), puis poursuivre les phases 13–15.

## Position vérifiée au 31/10/2026
- 9/15 phases validées : 01–03 et 05–10. Phase 04 partielle, phase 11 partielle, phase 12 active; 13–15 à faire. Voir PROMPT_PHASE_CROSSCHECK.md pour la comparaison détaillée avec le prompt initial.
- Dernière validation PHP rapportée : 78 tests / 406 assertions. lint, validate, assets et installation vierge (14 migrations) réussis selon TEST_STATUS.md. Suite navigateur isolée : 4/6 passent; restent un toast de notification temps réel absent et un délai d’attente de colonisation à rejouer.
- Les corrections UI du 31/10 sont commit 17be81d; suivi corrigé commit c3ded73, à compter du dernier HEAD local.
- Les données et fichiers de validation contiennent des modifications locales préexistantes; ne pas réinitialiser, clean ou restaurer ni toucher aux PDF, scripts, binaires ou captures sans nécessité et inspection explicite.
- Aucun déploiement/publication. Aucun réveil automatique après quota n’a été programmé; une reprise manuelle est requise.
