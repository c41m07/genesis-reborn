# Suivi des chantiers Alpha — 6 novembre 2026

## État en cours

- Branche : `feature/account-email`, issue de `origin/dev` au commit `d2e90aa`.
- Worktree : `/home/caim/Projets/Genesis_Reborn-account-email`.
- Les changements du propriétaire dans le checkout original sont préservés.
- Implémentation publiée : commit `2d09960` sur `feature/account-email`.
- [PR #2](https://github.com/c41m07/genesis-reborn/pull/2), base `dev`, créée et vérifiée. CI distante en cours ; aucune fusion ni mise en ligne à ce stade.
- Décision du propriétaire : envoyer un mail de bienvenue et vérifier l’adresse avant accès pour les nouveaux comptes. Les comptes existants gardent leur accès.
- Migration autorisée uniquement dans les bases locales de test ; aucune intervention serveur autorisée par cette validation.

## Chantier A — validé localement, PR ouverte

Statut d’adresse vérifiée, jetons hachés valables 24 heures, confirmation par POST protégé contre les CSRF, deux mails et formulaire de renvoi préparés. L’expéditeur utilise `MAILER_FROM` (valeur locale par défaut) et les liens utilisent `DEFAULT_URI`.

Contrôles : premiers essais bloqués par configuration et dépendances absentes du nouveau worktree ; environnement désormais configuré avec secrets locaux générés et copies des dépendances existantes, sans installation réseau. Les scripts shell ont été normalisés localement pour leur exécution. Projet Compose isolé `genesis-account-email` (base éphémère via `compose.browser-test.yaml`).

Les 33 migrations ont réussi sur cette base isolée. Le nom d’index divergent découvert pendant le lint a été aligné dans le mapping. La CI et la production ne sont pas validées par ces contrôles locaux.

Contrôles finaux : `make validate`, `make lint`, `npm run build` et `git diff --check` réussis. `make test` a terminé complètement avec **205 tests et 205 477 assertions**, sans échec. Les quatre nouveaux tests couvrent confirmation, refus avant vérification, hachage, expiration, renouvellement, usage unique, CSRF, réponse neutre et échec du transport. Le compte de démonstration local reste explicitement vérifié.

Les premiers essais PHP ont révélé une variable Mercure incompatible avec le domaine HTTP des tests ; les exécutions finales passent `MERCURE_PUBLIC_URL=http://genesis-reborn.localhost/.well-known/mercure` au conteneur de test. Une collision de schémas causée par deux suites PHP lancées sur la même base a été interrompue puis suivie d’une exécution unique complète réussie. Ne pas lancer deux suites PHP simultanément sur la même base de test.

L’échec intermittent PNJ était une attente incorrecte du test : une mission non détectée peut rapporter zéro catégorie avec les niveaux de recherche de cette fixture. L’assertion vérifie désormais précisément ce résultat et le rapport vide ; aucune règle de jeu n’a changé.

`make test-browser` : **88 scénarios uniques réussis par résultats consolidés**. Les quatre lots complets ont donné 84 réussites et 4 échecs. Après correction des droits du dossier local `var`, les 3 tests catalogue/inventaire passent. La reprise du scénario des routes passe sans augmenter son délai. Le défilement natif animé du clavier pouvait reprendre après un filtre technologique : un traitement limité aux flèches horizontales sur le graphe corrige ce défaut, puis les 4 tests de cette page passent après recompilation. Aucun test supprimé ni assertion assouplie. Les autres fonctionnalités utilisent des comptes vérifiés via la fixture navigateur ; le scénario de confirmation désactive cette préparation. Le transport navigateur est forcé à `null://null`.

Le diff publié a été contrôlé : 28 fichiers, sans les changements du propriétaire ni les dépendances locales. Aucune discussion ouverte au premier contrôle de la PR. Prochaine action : examiner la fin de la CI sur le dernier commit, corriger ses éventuels échecs, puis fusionner uniquement si les contrôles et discussions le permettent. Le SMTP, l’expéditeur et l’URL de l’Alpha doivent être vérifiés séparément sur le serveur ; aucun déploiement effectué.

## Ordre prévu

A (comptes/mails), B (réinitialisation), C/D (signaux/relais), E (alertes : canal et seuils à confirmer), F (sauvegardes), G (parcours serveur : accès requis), H (mesures), I (documentation hébergement), J (wiki), K (fermeture). Le README accompagne les changements puis reçoit une vérification finale.
