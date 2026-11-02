# Genesis Reborn — préparation Alpha

Mise à jour : 2 novembre 2026.

## Verdict

**Prêt pour une Alpha contrôlée; pas déclaré prêt pour la production.** Le seuil Alpha retenu est une boucle jouable avec les fonctions principales opérationnelles. L’Alpha sert à mesurer la progression réelle et à recueillir les retours; aucun déploiement public n’a eu lieu.

## Parcours jouables

- Créer un compte, choisir un univers et commencer sur une planète.
- Produire des ressources, construire, rechercher et gérer les files de production.
- Construire et composer des flottes, estimer les trajets et exécuter les missions.
- Explorer, coloniser, transporter, attaquer, défendre, orbiter, espionner et conquérir les planètes PNJ.
- Résoudre des combats persistés un round par minute, jusqu’à dix rounds ou fin anticipée; consulter les rapports privés.
- Utiliser diplomatie, guildes, messages, événements/boosts et les outils de gestion disponibles.

## Équilibrage pendant l’Alpha

L’orientation choisie est une stratégie modérée. Les réglages de départ, le bouclier indépendant de la structure et les règles de combat sont recensés dans BALANCING_QCM_2026-11-02.md et appliqués à la configuration. Les temps de première colonie, de flotte spécialisée et de premier vaisseau capital seront mesurés par les joueurs, puis ajustés selon les résultats. Les mesures à recueillir incluent les jalons atteints, les périodes d’attente, la fréquence de connexion, les ressources limitantes, les pertes et le temps de récupération après défaite.

## Validation locale au 2 novembre

- PHPUnit : 110 tests et 627 assertions réussis.
- Lint : syntaxe PHP, YAML, Twig, conteneur Symfony et schéma Doctrine réussis.
- Compose : configuration valide.
- Frontend : compilation Encore réussie.
- Playwright : campagne complète isolée 21/34; cinq scénarios ciblés (administration catalogue, thème, modération, responsive, accessibilité) passent après adaptation des sélecteurs. Le bilan agrégé n’a pas été relancé après ces corrections; huit parcours restent à reprendre, listés dans TEST_STATUS.md.

Les commandes, leurs périmètres et les résultats sont détaillés dans TEST_STATUS.md.

## Limites avant production

- Pas de run CI distant ni de push.
- Pas de test de charge soutenu ni de SLA de capacité.
- Sauvegarde et restauration validées sur une base jetable; destination distante, rétention, supervision et PRA restent à définir.
- La revue manuelle complète avec lecteur d’écran et la validation exhaustive RGAA/WCAG restent à réaliser.

Voir BLOCKERS.md, REQUIREMENTS_MATRIX.md et PHASE_15_FINAL_AUDIT.md pour les critères ouverts.
