# Suivi des chantiers Alpha — 6 novembre 2026

## État en cours

- Branche courante : `feature/pending-relays`, créée depuis `origin/dev` au commit `a372a9d`, après fusion vérifiée du chantier C. Chantier D publié au commit `9771855`, [PR #5](https://github.com/c41m07/genesis-reborn/pull/5) ouverte vers `dev`, CI en cours ; aucun déploiement.
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

Publication C : commit `c4deb3f`, PR #4 vérifiée avec base `dev`, neuf fichiers attendus dans le diff, aucune revue ni discussion au premier examen. Les onze contrôles GitHub ont démarré ; aucun résultat final de CI annoncé. Cette note de suivi reste locale jusqu’au prochain lot documentaire.

Première CI C sur `c4deb3f` : une exécution PHP réussit, l’autre révèle le caractère aléatoire du test `testSpyMissionConsumesAllProbesAndKeepsReportPrivate` (résultat détecté avec un risque attendu de 5 %). Correction ciblée de testabilité : `EspionageService` reçoit le générateur PHP natif `Randomizer`, sécurisé par défaut ; seul ce test injecte un moteur reproductible dont le premier tirage vaut 45. Le risque de détection reste de 5 %, les assertions de succès, de contenu, de consommation et de confidentialité sont conservées. Aucun paramètre d’équilibrage modifié ni dépendance installée. Les 210 tests PHP et 205 505 assertions, validation, lint et construction passent après ce changement ; une nouvelle exécution navigateur est en cours avant publication.

Une exécution navigateur GitHub du lot 2 échoue après colonisation : la fixture accélère l’arrivée immédiatement après le clic d’envoi, avant que l’écran de suivi ne soit prêt. Comme pour les missions d’exploration et de transport du même scénario, le test attend désormais l’affichage d’une flotte en trajet avant accélération. Le délai de 45 secondes, l’achèvement, les mouvements et les assertions de progression restent inchangés. Reprise du fichier requise avant publication.

Contrôles après corrections de CI : **88 tests navigateur réussis sur deux lots complets de 44** (15,0 et 16,2 minutes), sans reprise ciblée ni échec dans cette exécution. Le scénario de colonisation inclut l’attente supplémentaire. Les 210 tests PHP et 205 505 assertions passent intégralement, ainsi que `make validate`, `make lint`, `npm run build` et `git diff --check`. Le diff supplémentaire se limite à l’injection du générateur natif, sa préparation de test, l’attente de trajet et ce suivi. Nouvelle CI requise avant fusion ; aucun déploiement.

Correctifs publiés au commit `58d40ee` sur la PR #4. La revue du diff confirme treize fichiers prévus, aucune modification étrangère ; aucune revue ni discussion ouverte. Nouvelle CI : deux suites PHP, sept lots navigateur et GitGuardian réussis ; le dernier lot navigateur est encore en cours. Attendre sa conclusion et revérifier les discussions et le SHA avant toute fusion.

## Chantier C — intégré à dev, non déployé

[PR #4](https://github.com/c41m07/genesis-reborn/pull/4) fusionnée et vérifiée le 6 novembre 2026 à 16 h 19 (Europe/Paris), commit d’intégration `a372a9d81ed47b7037fdfba2644145af2515d152`. Les onze contrôles GitHub réussissent sur `58d40ee` : deux suites PHP, huit lots navigateur et GitGuardian ; publication d’image ignorée. PR `clean`, sans revue, commentaire ni discussion avant fusion. `origin/dev` a été récupérée après fusion. Les changements du propriétaire sont préservés. Les statuts de CI intermédiaires ci-dessus restent historiques.

Résultat : volume runtime partagé, application en lecture seule, fraîcheur des signaux relue et quatre états explicites. Configuration et collecte réellement vérifiées localement ; aucun contrôle de l’infrastructure Alpha ni déploiement. Au prochain déploiement autorisé, recréer les quatre services pour appliquer les nouveaux montages, puis contrôler le serveur.

## Chantier D — analyse commencée

Complexité : 4/5, compte tenu des transactions, de la publication externe, de la reprise et des doublons. Branche `feature/pending-relays` créée depuis le `dev` intégré à jour (`a372a9d`). État Git examiné : ce suivi local et les fichiers générés/modes précédemment exclus sont préservés ; aucun changement de code D.

Premières lectures ciblées : `OutboxMessage`, `OutboxRelay` et `AdminOperationsService`. L’état « en attente » correspond à la présence d’une ligne non retirée après publication. Le stockage expose seulement nature, cible, jeton et échéance : aucune date de création, tentative ou dernière erreur, donc aucun historique de ce type ne peut être déduit. Le relais verrouille puis publie et retire la ligne dans une transaction ; le code annonce une livraison pouvant dupliquer après interruption. L’admin compte les lignes et leurs échéances dépassées, mais masque actuellement les erreurs de lecture des compteurs. Ces constats portent sur le dépôt, pas sur les messages effectivement présents sur l’Alpha.

Prochaine action : lire la requête `GameRepository::outbox`, les commandes des relais, `GameManager` et les tests de reprise/idempotence ; reproduire tout défaut avant correction. Définir les diagnostics calculables sans inventer l’historique manquant. Aucune migration supplémentaire autorisée ou appliquée pour D. Le suivi final C et ce début d’analyse D restent locaux et devront accompagner le prochain lot documentaire. Chantiers D–K et audit final du README non terminés.

Lectures D complémentaires : `GameRepository::outbox` sélectionne au plus 100 lignes par identifiant, avec circuits événements et notifications séparés. `OutboxRelayCommand` sort en erreur après exception ; la transaction Doctrine referme le gestionnaire en échec, donc la reprise relève d’un nouveau processus supervisé. Le test Mercure existant vérifie la conservation de la notification après panne et l’indépendance du circuit gameplay. Messenger prévoit cinq nouvelles tentatives et un transport `failed`, distincts des tentatives du relais qui ne sont pas historisées. Prochaine reproduction : lectures/collecte indisponibles, concurrence sur une ligne retirée et reprise d’une publication échouée. Aucun défaut de reprise annoncé comme reproduit, aucune modification D implémentée.

### Implémentation D en cours

Deux défauts reproduits avant correction : les tests unitaires échouent sur l’absence de distinction entre collecte indisponible et zéro ; un événement de combat n’apparaît pas dans le transport asynchrone faute de route Messenger. Correctifs ciblés : état de collecte explicite, comptage des natures inconnues, liste de 20 échéances avec retard et cible sans jeton, et route `CombatEventDue` vers `async`. Aucun changement des règles, aucune migration ni suppression de messages.

La reprise après publication puis exception est vérifiée sur MariaDB : ligne conservée, nouveau noyau de test, nouvelle livraison et une seule construction appliquée. La limite de 20 lignes, l’ordre et les dates UTC sont vérifiés avec de vraies requêtes. Quatre tests unitaires passent après correction. Une première suite complète passe avec 214 tests et 205 532 assertions avant ajout du test de routage ; nouvelle suite complète et lots navigateur en cours, pas encore de publication D.

La suite PHP finale passe intégralement : **215 tests et 205 539 assertions**. Le test de routage ajouté échouait auparavant (aucun message dans `async`) et passe après ajout de la route. Les liens locaux des documents actifs modifiés sont vérifiés : README 15, index 33, ROADMAP 7, guide relais 1, aucun fichier manquant.

Le nouveau test navigateur révèle un débordement à 768 pixels (844 pixels de document). Le diagnostic DOM montre que le tableau est bien contenu dans sa zone défilante ; les lignes à trois colonnes du journal voisin débordent dans la zone réduite par la barre latérale. Correction CSS limitée à ces lignes sous 900 pixels et nouvelle construction des assets. Reprise ciblée du nouveau scénario en cours ; les deux lots complets continuent. Aucun résultat navigateur global réussi n’est encore annoncé.

La reprise ciblée du nouveau tableau passe aux trois largeurs après correction CSS (17,9 secondes). La capture mobile montrait encore le haut du panneau : le scénario est complété pour cadrer la ligne et vérifier aussi le défilement horizontal au clavier avant une dernière reprise ciblée. La commande de diagnostic `messenger:failed:show --stats` a été essayée sur le projet local isolé et refusée par le receiver AMQP ; elle est remplacée dans le guide par `messenger:stats failed`, effectivement réussie (compteur local zéro). La limite de consultation détaillée du transport AMQP est documentée pour les chantiers suivants.

Dernière reprise du nouveau scénario réussie (20,3 secondes), incluant défilement horizontal au clavier et captures cadrées sur la ligne aux trois largeurs. Un autre scénario du lot complet (`research-flow`) a reçu une page 500 pendant une recompilation effectuée par l’agent : le journal du conteneur isolé établit l’absence transitoire de `public/build/entrypoints.json`. Il s’agit d’une interférence de la vérification locale ; aucune recompilation supplémentaire pendant les lots, puis reprise du scénario concerné après leur fin. Ce résultat ne sera pas présenté comme une exécution navigateur complète sans échec.

Contrôles D avant publication : `make validate`, `make lint`, `npm run build` et `git diff --check` réussis ; suite PHP complète finale **215 tests, 205 539 assertions**. Les deux lots navigateur complets totalisent 87 réussites et deux échecs expliqués. Le nouveau diagnostic admin passe après correction CSS, avec défilement clavier et captures aux trois tailles (20,3 secondes) ; la recherche repasse après la fin des lots et de la recompilation (29,0 secondes). Résultat : **89 scénarios uniques validés par résultats consolidés**, et non une première exécution complète sans échec. Les preuves sont locales, pas une validation de l’Alpha ni de la CI. Aucune migration, suppression de message, installation ou opération serveur.

Publication D vérifiée : PR #5, base `dev`, commit `977185589eb2961846be3383de7d13ddeb40ec81`, treize fichiers attendus dans le diff. Aucune revue, commentaire ou discussion au premier examen. Les contrôles GitHub ont démarré ; attendre leur résultat et revérifier le SHA et les discussions avant fusion. Cette note reste locale et accompagnera le lot suivant.

Première CI D : les deux suites PHP passent ; une exécution du lot navigateur 3 échoue sur une mesure immédiate de hauteur après le retour d’un formulaire de réinitialisation et redimensionnement (41 px mesurés pour un seuil de 44), tandis que l’autre exécution de ce lot passe. Le test attend désormais la fin du chargement et interroge la hauteur et la largeur jusqu’à stabilisation, avec les mêmes seuils, les vérifications de position conservées et le délai global inchangé. Le scénario complet repasse localement (24,1 secondes), avec les contrôles fonctionnels et axe ; aucune assertion supprimée ou assouplie. Les autres contrôles locaux réussis portent sur les mêmes fichiers applicatifs, inchangés par cette correction de test. `git diff --check` est relancé avant publication. Nouvelle CI requise avant fusion.
