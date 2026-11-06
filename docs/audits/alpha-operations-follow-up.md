# Suivi des chantiers Alpha — 6 novembre 2026

## État en cours

- Branche courante : `feature/admin-operation-signals`, créée depuis le nouveau `origin/dev` au commit `abae47d`, après fusion du chantier B. Aucun code du chantier C modifié à ce stade.
  Elle a ensuite été avancée sans réécriture vers `366acab`, qui intègre les nouvelles consignes AGENTS.
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

## Chantier B — intégré à dev, non déployé

Conclusion : [PR #3](https://github.com/c41m07/genesis-reborn/pull/3) fusionnée et vérifiée le 6 novembre 2026 à 13 h 52 (Europe/Paris), commit d’intégration `abae47d`. Tous les contrôles GitHub sont réussis sur son dernier commit `f9d11c4` (les deux exécutions PHP et les huit lots navigateur, plus GitGuardian). La PR était `clean`, sans commentaire ni discussion ouverte avant fusion. `origin/dev` a été récupérée au commit d’intégration ; le checkout d’origine et ses 14 changements du propriétaire restent intacts.

Les résultats intermédiaires ci-dessous sont historiques et ne remplacent pas cette conclusion. Le prochain chantier est C : vérifier production, stockage et affichage des signaux d’exploitation. Cette note finale reste locale sur la branche C pour être intégrée à son lot documentaire. Aucun déploiement ni contrôle de réception sur le serveur Alpha réalisé.

Le parcours existait déjà : réponse publique neutre, jeton haché valable une heure, expiration, usage unique et remplacement du mot de passe. Deux défauts de code sont corrigés : expéditeur local codé en dur et URL fondée sur l’hôte de la requête. Le parcours utilise désormais `MAILER_FROM` et `DEFAULT_URI`, comme la confirmation de compte.

L’ordre des verrous de la demande et de la consommation a été aligné (joueur puis jetons). Une requête verrouillée avec rafraîchissement lit le jeton courant après attente, y compris lorsqu’une autre connexion l’a invalidé. Un test MariaDB à deux connexions vérifie cette lecture malgré le premier instantané de transaction. Aucune migration ni nouvelle dépendance.

Le cache Symfony local de test conservait l’ancien constructeur ; sa reconstruction a permis de reprendre les tests ciblés. `make validate`, `make lint`, `npm run build` et `git diff --check` réussissent. La suite PHP finale termine entièrement avec **208 tests et 205 495 assertions**, sans échec. Le test de configuration utilise aussi un expéditeur et une URL distincts des valeurs locales.

Les quatre lots navigateur complets totalisent 86 réussites et deux dépassements de délai (inventaire et routes) sous exécution locale concurrente. Les trois scénarios des fichiers concernés repassent isolément, sans changement de délai ni d’assertion : **88 scénarios uniques validés par résultats consolidés**. Ce résultat local ne constitue pas une validation de la CI ou de la production.

Les 13 liens locaux du README existent. Le commit `6316b87` est poussé et la [PR #3](https://github.com/c41m07/genesis-reborn/pull/3) est vérifiée avec base `dev`. Le diff publié contient les 7 fichiers attendus. Aucune discussion ouverte au premier contrôle. Prochaine action : examiner la fin de la CI sur ce commit et les discussions avant toute fusion. Cette actualisation du suivi reste locale pendant la CI et sera intégrée au lot documentaire suivant.

Les valeurs SMTP et la réception sur l’Alpha restent des points de configuration et de vérification serveur, non démontrés par les tests locaux. Aucun envoi externe ni déploiement effectué.

Première CI du chantier B sur `6316b87` : les deux lots PHP et toutes les autres exécutions navigateur réussissent ; une exécution du lot 2 échoue lors du second changement de colonie, effectué juste après l’ouverture de Recherche. Le scénario attend désormais le chargement de cette page avant l’interaction, comme pour le premier changement déjà stabilisé au chantier A. Reprise locale réussie du scénario complet (47,8 secondes), sans modification d’assertion ou de délai. La nouvelle CI sur ce correctif doit réussir avant fusion.

CI suivante sur `520bbb3` : une mesure responsive du formulaire d’univers est exécutée trop tôt après redimensionnement (466 px mesurés pour une fenêtre de 390 px). Les trois mesures identiques du fichier attendent désormais la stabilisation via `expect.poll`, avec les mêmes seuils et le délai global des scénarios inchangé. Les trois scénarios du fichier passent localement (59,6 secondes au total). Nouvelle validation GitHub requise avant fusion.

## Ordre prévu

### Reprise du chantier C

Complexité provisoire : 3/5. État Git vérifié avant examen ; seuls le suivi local et les fichiers générés précédemment sont modifiés dans le worktree. Les trois documents Work nommés dans les nouvelles consignes n’ont pas été trouvés dans les emplacements locaux examinés ; ils n’ont pas été lus et leur contenu ne doit pas être supposé.

Défaut de configuration démontré dans le dépôt : `ProcessHeartbeat` écrit dans `/app/var/run`, et `AdminOperationsService` lit ce même chemin dans le conteneur app. `compose.prod.yaml` ne partage pas ce répertoire entre app, worker, relay et notifier ; seul le volume des sessions est monté sur app. Les healthchecks peuvent donc lire les signaux locaux des processus tandis que l’admin ne les voit pas. Ce constat n’est pas une inspection du serveur Alpha.

Prochaine action : vérifier les droits et l’initialisation du répertoire dans l’image de production, préparer un partage de runtime compatible entre les quatre services, tester cette visibilité et distinguer absence/inconnu/signal périmé sans présenter une absence de fichier comme preuve d’un processus arrêté. Aucune correction applicative ni publication du chantier C à ce stade.

A (comptes/mails), B (réinitialisation), C/D (signaux/relais), E (alertes : canal et seuils à confirmer), F (sauvegardes), G (parcours serveur : accès requis), H (mesures), I (documentation hébergement), J (wiki), K (fermeture). Le README accompagne les changements puis reçoit une vérification finale.

### Sources Work désormais accessibles et avancement C

Les trois sources fournies par le propriétaire ont été lues intégralement le 6 novembre 2026 : prompt maître Work V4, brief Figma Work V2 et protocole de développement adaptatif. Le constat antérieur d’indisponibilité reste historique. Ces références cadrent le programme en quinze phases ; la mission explicite A–K, les branches `feature/*` vers `dev`, la préservation des logos et les décisions métier validées restent applicables. Ce lot contribue aux diagnostics administrateur et à l’exploitation sans prétendre valider une phase complète.

Correction C préparée sur `feature/admin-operation-signals` depuis `dev` actualisé à `366acab` : volume runtime partagé, application en lecture seule et états distincts sans déduire un arrêt de la seule absence de signal. La documentation de diagnostic et deux tests unitaires sont ajoutés.

Contrôles locaux : validation, lint, construction des assets et suite PHP complète réussis (**210 tests, 205 505 assertions**). Configuration Compose rendue vérifiée. Un volume jetable a aussi été initialisé par le montage applicatif en lecture seule ; les trois processus ont écrit sous `www-data`, puis le service réel de collecte a affiché trois signaux récents avec la base locale isolée. Aucune donnée ni configuration de l’Alpha n’a été utilisée.

Navigateur : les deux lots complets donnent 87 réussites sur 88 ; le retour galaxie du scénario GalaxieV2 échoue lors de transitions animées puis le scénario complet repasse isolément (21,1 secondes), sans modification du code, des assertions ou des délais. Résultat consolidé : 88 scénarios uniques réussis ; la première exécution complète reste en échec et la CI doit être examinée séparément. Aucun commit, push ou PR du chantier C à ce stade. Prochaine action : expliquer cet échec, terminer les contrôles, examiner le diff puis publier le lot et vérifier sa CI avant fusion.
