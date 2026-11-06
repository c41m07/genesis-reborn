# Suivi des chantiers Alpha — 6 novembre 2026

## État en cours

- Branche courante : `feature/password-reset`, créée depuis le nouveau `origin/dev` au commit `15e8d43`, après fusion du chantier A. Chantier B validé localement, publication en préparation.
- Worktree : `/home/caim/Projets/Genesis_Reborn-account-email`.
- Les changements du propriétaire dans le checkout original sont préservés.
- Implémentation publiée : commit `2d09960` sur `feature/account-email`.
- [PR #2](https://github.com/c41m07/genesis-reborn/pull/2), base `dev`, **fusionnée et vérifiée le 6 novembre 2026 à 12 h 50 (Europe/Paris)**, commit d’intégration `15e8d43`. Aucun déploiement effectué.
- Décision du propriétaire : envoyer un mail de bienvenue et vérifier l’adresse avant accès pour les nouveaux comptes. Les comptes existants gardent leur accès.
- Migration autorisée uniquement dans les bases locales de test ; aucune intervention serveur autorisée par cette validation.

## Chantier A — intégré à dev, non déployé

Conclusion : les contrôles GitHub sont tous réussis sur le dernier commit de la PR `d335550` (PHP, quatre lots navigateur sur les deux déclenchements et GitGuardian). La PR était `clean`, sans commentaire ni discussion de revue avant fusion. La fusion vers `dev` est confirmée par GitHub et `origin/dev` a été récupérée à `15e8d43`. Les exécutions antérieures et leurs reprises restent décrites ci-dessous comme historique ; elles ne constituent pas le résultat final de CI.

Le checkout d’origine est resté sur son ancien `dev`, avec les 14 fichiers déjà modifiés par le propriétaire, pour préserver ces changements et son environnement actif. La nouvelle copie de travail du prochain chantier repose bien sur le `dev` intégré à jour. Les notes finales de cette section restent localement dans la branche B et devront être incluses dans sa prochaine documentation, sans recommencer le chantier A.

Statut d’adresse vérifiée, jetons hachés valables 24 heures, confirmation par POST protégé contre les CSRF, deux mails et formulaire de renvoi préparés. L’expéditeur utilise `MAILER_FROM` (valeur locale par défaut) et les liens utilisent `DEFAULT_URI`.

Contrôles : premiers essais bloqués par configuration et dépendances absentes du nouveau worktree ; environnement désormais configuré avec secrets locaux générés et copies des dépendances existantes, sans installation réseau. Les scripts shell ont été normalisés localement pour leur exécution. Projet Compose isolé `genesis-account-email` (base éphémère via `compose.browser-test.yaml`).

Les 33 migrations ont réussi sur cette base isolée. Le nom d’index divergent découvert pendant le lint a été aligné dans le mapping. La CI et la production ne sont pas validées par ces contrôles locaux.

Contrôles finaux : `make validate`, `make lint`, `npm run build` et `git diff --check` réussis. `make test` a terminé complètement avec **205 tests et 205 477 assertions**, sans échec. Les quatre nouveaux tests couvrent confirmation, refus avant vérification, hachage, expiration, renouvellement, usage unique, CSRF, réponse neutre et échec du transport. Le compte de démonstration local reste explicitement vérifié.

Les premiers essais PHP ont révélé une variable Mercure incompatible avec le domaine HTTP des tests ; les exécutions finales passent `MERCURE_PUBLIC_URL=http://genesis-reborn.localhost/.well-known/mercure` au conteneur de test. Une collision de schémas causée par deux suites PHP lancées sur la même base a été interrompue puis suivie d’une exécution unique complète réussie. Ne pas lancer deux suites PHP simultanément sur la même base de test.

L’échec intermittent PNJ était une attente incorrecte du test : une mission non détectée peut rapporter zéro catégorie avec les niveaux de recherche de cette fixture. L’assertion vérifie désormais précisément ce résultat et le rapport vide ; aucune règle de jeu n’a changé.

`make test-browser` : **88 scénarios uniques réussis par résultats consolidés**. Les quatre lots complets ont donné 84 réussites et 4 échecs. Après correction des droits du dossier local `var`, les 3 tests catalogue/inventaire passent. La reprise du scénario des routes passe sans augmenter son délai. Le défilement natif animé du clavier pouvait reprendre après un filtre technologique : un traitement limité aux flèches horizontales sur le graphe corrige ce défaut, puis les 4 tests de cette page passent après recompilation. Aucun test supprimé ni assertion assouplie. Les autres fonctionnalités utilisent des comptes vérifiés via la fixture navigateur ; le scénario de confirmation désactive cette préparation. Le transport navigateur est forcé à `null://null`.

Le diff publié a été contrôlé : 28 fichiers, sans les changements du propriétaire ni les dépendances locales. Aucune discussion ouverte au premier contrôle de la PR. Prochaine action : examiner la fin de la CI sur le dernier commit, corriger ses éventuels échecs, puis fusionner uniquement si les contrôles et discussions le permettent. Le SMTP, l’expéditeur et l’URL de l’Alpha doivent être vérifiés séparément sur le serveur ; aucun déploiement effectué.

Première CI sur `557fc26` : les deux exécutions PHP réussissent (205 tests, 205 477 assertions), avec lint et schéma valides. Les lots navigateur 1 et 3 réussissent ; l’exécution PR des lots 2 et 4 révèle quatre attentes faites avant stabilisation de la page. Les tests attendent désormais la fin du chargement avant mesure de la barre, changement de colonie et recherche GalaxieV2 ; la mesure responsive de la carte classique est interrogée jusqu’à stabilisation avec le même seuil et le même délai. Les 8 scénarios colonie/GalaxieV2 et les 4 scénarios technologiques repassent localement. Aucune assertion retirée ou délai augmenté. La nouvelle CI doit réussir avant fusion.

Vérification supplémentaire de migration : sur le projet isolé `genesis-account-email`, retour arrière de cette seule migration, insertion d’un compte fictif, puis réapplication réussie. L’adresse et le pseudo sont conservés et `email_verified_at` est bien renseigné. La base de développement du propriétaire et le serveur ne sont pas concernés.

## Chantier B — validé localement, publication en préparation

Le parcours existait déjà : réponse publique neutre, jeton haché valable une heure, expiration, usage unique et remplacement du mot de passe. Deux défauts de code sont corrigés : expéditeur local codé en dur et URL fondée sur l’hôte de la requête. Le parcours utilise désormais `MAILER_FROM` et `DEFAULT_URI`, comme la confirmation de compte.

L’ordre des verrous de la demande et de la consommation a été aligné (joueur puis jetons). Une requête verrouillée avec rafraîchissement lit le jeton courant après attente, y compris lorsqu’une autre connexion l’a invalidé. Un test MariaDB à deux connexions vérifie cette lecture malgré le premier instantané de transaction. Aucune migration ni nouvelle dépendance.

Le cache Symfony local de test conservait l’ancien constructeur ; sa reconstruction a permis de reprendre les tests ciblés. `make validate`, `make lint`, `npm run build` et `git diff --check` réussissent. La suite PHP finale termine entièrement avec **208 tests et 205 495 assertions**, sans échec. Le test de configuration utilise aussi un expéditeur et une URL distincts des valeurs locales.

Les quatre lots navigateur complets totalisent 86 réussites et deux dépassements de délai (inventaire et routes) sous exécution locale concurrente. Les trois scénarios des fichiers concernés repassent isolément, sans changement de délai ni d’assertion : **88 scénarios uniques validés par résultats consolidés**. Ce résultat local ne constitue pas une validation de la CI ou de la production.

Les 13 liens locaux du README existent. Prochaine action : commit ciblé, push et PR vers `dev`, puis examen du diff publié, de la CI et des discussions avant toute fusion.

Les valeurs SMTP et la réception sur l’Alpha restent des points de configuration et de vérification serveur, non démontrés par les tests locaux. Aucun envoi externe ni déploiement effectué.

## Ordre prévu

A (comptes/mails), B (réinitialisation), C/D (signaux/relais), E (alertes : canal et seuils à confirmer), F (sauvegardes), G (parcours serveur : accès requis), H (mesures), I (documentation hébergement), J (wiki), K (fermeture). Le README accompagne les changements puis reçoit une vérification finale.
