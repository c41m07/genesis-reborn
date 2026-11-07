# Suivi des chantiers Alpha — 6 novembre 2026

## État en cours

- Branche courante : `feature/universe-metrics`, créée depuis `origin/dev` au commit `2870e13`, après fusion vérifiée de G. Chantier H validé localement : publication et CI GitHub à effectuer, aucune validation de l’Alpha.
- Avancement demandé par le propriétaire : **58 %**, sept lots intégrés sur douze (A–K et refonte finale du README). Le pourcentage compte les lots fusionnés, pas les déploiements.
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

Suivi de la CI sur `6601b8eebd56eedf8c4a439c40750f0da9d436f2` : dix contrôles réussis, dernier lot navigateur 2 encore en cours. Le diff final contient quatorze fichiers attendus et aucune discussion ouverte au dernier examen.

Point serveur à conserver : la route de combat corrige les nouvelles publications mais ne démontre pas l’état des combats déjà concernés sur l’Alpha. Aucune commande de réconciliation globale n’a été trouvée dans les commandes actuelles ; le worker est `messenger:consume async`, supervisé par Compose. Lors d’une intervention autorisée, examiner les combats persistants, l’outbox et le transport avant toute décision de reprise. Aucun rejeu, effacement ou redémarrage de l’Alpha exécuté. Cette limite est une vérification serveur restante, pas un résultat de test local.

## Chantier D — intégré à dev, non déployé

[PR #5](https://github.com/c41m07/genesis-reborn/pull/5) fusionnée et vérifiée le 6 novembre 2026 à 19 h 14 (Europe/Paris), commit d’intégration `68c8a2946e3259c9fdfe56ab81446ec5409602d0`. Les onze contrôles GitHub réussissent sur `6601b8eebd56eedf8c4a439c40750f0da9d436f2` : deux suites PHP, huit lots navigateur et GitGuardian. PR `clean`, sans commentaire, revue ni discussion avant fusion. `origin/dev` récupérée après fusion. Les étapes et résultats intermédiaires ci-dessus sont historiques.

Résultat : routage différé des combats rétabli, diagnostic de vingt échéances en attente et de leur retard, natures inconnues visibles, collecte indisponible distinguée d’une file vide et affichage clavier/mobile vérifié. Contrôles locaux : 215 tests PHP et 205 539 assertions sur une suite complète ; 89 scénarios navigateur validés après les reprises décrites ; validation, lint, construction et diff réussis. Aucun déploiement, migration, installation ou effacement de messages. L’état des messages et des combats déjà concernés sur l’Alpha reste à examiner lors d’une intervention serveur autorisée.

## Chantier E — préparation et choix indispensables

Branche `feature/operation-alerts` créée depuis le nouveau `dev` à `68c8a29`, sans développement sur une ancienne branche. Complexité : 4/5 (supervision réelle, distinction des états inconnus, canal externe, déduplication persistante et sécurité). État Git vérifié avant reprise ; changements du propriétaire et fichiers générés exclus préservés. Aucun code E modifié.

Premières recherches dans la configuration, les scripts, les workflows et les documents actifs : aucun canal ni seuil d’alerte d’exploitation trouvé. Le mail utilise déjà Symfony Mailer, `MAILER_DSN` et `MAILER_FROM`. Les notifications du jeu passent par leur propre outbox/Mercure ; elles ne doivent pas devenir des alertes envoyées aux joueurs. Les signaux et l’outbox sont observables par `AdminOperationsService` ; le compteur `failed` peut être obtenu avec le receiver AMQP, mais pas sa liste détaillée via la commande Messenger actuelle.

Questions ciblées envoyées au propriétaire : canal et destination (courriel recommandé via le transport du projet, ou webhook hors dépôt) ; profils de seuils proposés. Profil modéré proposé : signal absent 2 minutes, retard de relais 5 minutes, échec définitif dès détection, rappel au plus une fois par heure et notification de rétablissement. Aucun choix considéré accepté en l’absence de réponse, aucun envoi externe activé.

Plan de reprise E : confirmer les choix ; concevoir un relevé qui distingue arrêt confirmé par supervision, signal manquant/ancien, collecte indisponible et fonctionnement récent ; prévoir un état persistant de déduplication et tester les frontières de seuil, rappels, rétablissement et panne de transport. Une vérification locale ne prouvera pas la supervision de l’Alpha. Préserver l’absence d’accès Docker depuis l’application : la confirmation d’arrêt doit venir d’un contrôleur côté hôte ou d’une source de supervision autorisée. Documenter exécution automatisable, configuration sans secret et diagnostic. Réutiliser le mail et les mesures existantes si le canal retenu le permet ; ne pas ajouter de migration sans nécessité démontrée et validation.

Cette conclusion D et cette préparation E restent locales pour accompagner le prochain lot documentaire. E–K et audit final du README restent incomplets.

### Choix E reçus et implémentation préparée

Le propriétaire a choisi le courriel et les seuils modérés, puis fourni l’adresse destinataire. Elle est conservée uniquement dans `.env.local` ignoré, avec `OPS_ALERT_ENABLED=0`, jamais dans les valeurs publiées. Aucune activation sur un serveur.

Implémentation : évaluateur de conditions, mails réutilisant Symfony Mailer, état JSON atomique sous verrou, volume persistant dédié, commande CLI alimentée par le relevé Docker et script hôte automatisable. Les signaux manquants ont un délai d’observation ; l’arrêt confirmé prime sur un signal récent. Les états inconnus ne déclenchent pas de faux rétablissement. Les compteurs d’échec AMQP restent agrégés et les indisponibilités globales nécessitent une surveillance extérieure.

Neuf tests unitaires passent (43 assertions). La première suite PHP complète passe avec 224 tests et 205 582 assertions. Après reconstruction du cache local dev ancien, la commande et le script fonctionnent en simulation, même avec --send lorsque les envois sont désactivés. Le test d’arrêt réel du worker éphémère passe après préparation explicite d’un signal côté collecteur (tmpfs séparés par conteneur) ; aucun mail réel.

Construction complète de l’image de production tentée hors réseau : impossible faute de paquets APT en cache, pas un résultat applicatif réussi. Aucune installation réseau effectuée pour contourner ce prérequis. Vérification plus limitée sur une image dérivée du runtime existant, code/configuration actuels : volume initialisé sous www-data, alerte avec transport null, nouveau conteneur sans doublon, rétablissement (1,0,1). Le premier essai de ce runtime manquait de DEFAULT_URI ; la reprise fournit les variables de l’application isolée sans afficher les secrets. Cette preuve ne remplace ni une construction complète ni une réception de mail en production.

Prochaine action : contrôles obligatoires sur le lot figé, puis revue du diff, commit et PR vers dev. Aucun code de jeu, migration ou traitement de message modifié.

Contrôles E finaux sur le lot figé : make validate, make lint, make test (**224 tests, 205 582 assertions**), npm run build, make test-browser (**90 scénarios, deux lots complets de 45 réussis, 15,7 et 16,8 minutes**) et git diff --check réussis. Aucun échec dans cette validation finale complète ; les essais de mise au point précédents restent décrits séparément. Script validé par bash -n ; configuration Compose de production rendue avec volume d’état en écriture. Liens locaux vérifiés : README 16, index 34, ROADMAP 8 et guide 2, aucun absent. La construction complète de l’image de production hors réseau reste non validée pour la raison indiquée ; le runtime dérivé et les droits ont une preuve séparée.

Publication E : PR #6 vérifiée avec base dev et head `d56cc4f949f51182f0c02a2a21d52ef1e61d209b`. Dix-neuf fichiers attendus dans le diff, aucune revue ni discussion au premier examen. Contrôle des secrets réussi ; suites PHP et navigateur en cours. L’adresse privée est absente des fichiers publiés et .env.local est bien ignoré. Aucune action workflow_dispatch de déploiement lancée. Cette note reste locale pour le prochain lot documentaire.

CI E sur d56cc4f : dix contrôles réussis, dernier lot navigateur 2 toujours en cours ; aucun échec. La destination et les seuils sont définis, mais les envois restent désactivés et aucun job n’est installé sur l’Alpha. Attendre le dernier contrôle, revérifier SHA/discussions, puis fusionner uniquement vers dev.

## Chantier E — intégré à dev, non déployé

[PR #6](https://github.com/c41m07/genesis-reborn/pull/6) fusionnée et vérifiée le 6 novembre 2026 à 20 h 38 (Europe/Paris), commit d’intégration `766f2cbe11e7c6a74904f304491719d2fa8d4b73`. Les onze contrôles GitHub réussissent sur `d56cc4f949f51182f0c02a2a21d52ef1e61d209b` : deux suites PHP, huit lots navigateur et GitGuardian. PR `clean`, sans revue, commentaire ni discussion avant fusion. Aucun correctif de CI nécessaire pour E. `origin/dev` récupérée après fusion ; les résultats intermédiaires ci-dessus restent historiques.

Alertes par courriel avec seuils choisis, rappels limités, rétablissement et état persistant préparées. La destination est conservée hors dépôt et les envois restent désactivés. Aucun cron installé, courriel réel envoyé ou déploiement effectué. Le guide décrit activation, droits, observation périodique et limites (surveillance extérieure nécessaire en cas d’indisponibilité globale). La construction complète de l’image de production reste à vérifier dans un environnement autorisé disposant des paquets ; les preuves limitées sur le runtime dérivé ne la remplacent pas.

## Chantier F — audit commencé

Complexité : 5/5, compte tenu des données personnelles, des suppressions de conservation, de la restauration destructive et de la reprise des opérations asynchrones. Branche `feature/automated-backups` créée depuis le nouveau dev `766f2cb`. État Git vérifié, fichiers du propriétaire préservés. Les scripts du worktree sont lus sans modification de contenu.

Existant vérifié : db-backup.sh produit un dump MariaDB transactionnel avec routines/événements/déclencheurs, compression gzip, contrôle gzip et fichier SHA-256 ; droits restrictifs. db-restore.sh exige une confirmation du projet, vérifie gzip et un manifeste SHA-256 puis importe. Aucun mécanisme de planification ou de conservation dans ces scripts. Les erreurs remontent par les codes de sortie et pipefail ; pas encore de preuve de restauration F.

Le runbook trouvé sous docs/agent/RUNBOOK_OPERATIONS.md est une référence historique : il laisse stockage distant, chiffrement et rétention à l’opérateur et affirme que les messages de transport peuvent être reconstruits. L’analyse D n’a trouvé aucune commande générale de réconciliation ; ne pas reprendre cette affirmation sans vérification du code et test. Préserver son contexte historique, créer une procédure active vérifiée dans le lot F.

Prochaine action : comparer en lecture seule les changements locaux des scripts du propriétaire avec la base pour les préserver, définir source et cible de test strictement séparées, reproduire les problèmes de concurrence/nommage et de liaison du checksum à l’archive avant correction, préciser la conservation et les traces d’échec. Tester sauvegarde puis restauration sur une nouvelle instance isolée, sans worker, notifications ou mails réels, et comparer effectivement comptes et progression. Examiner les événements en cours : restaurer la base seule ne prouve pas une reprise des messages déjà publiés dans RabbitMQ. Aucun accès serveur, emplacement distant ou résultat de restauration ne doit être inventé.

Cette conclusion E et cet audit F restent locaux pour accompagner le prochain lot documentaire. F–K et audit final du README restent incomplets.

### F — défauts reproduits et correction préparée

Avant modification, deux défauts démontrés avec faux appels Docker et fichiers temporaires : deux sauvegardes à la même seconde n’en conservaient qu’une ; un manifeste pointant vers un autre gzip permettait l’appel d’import de l’archive non vérifiée. Aucun import réel durant ces reproductions.

La branche F a été avancée sans réécriture de 766f2cb à cb9646e, intégrant la PR propriétaire #7 (consignes RGAA/SEO et prérequis Fighter déjà décidé). AGENTS et changements métier consultés, aucune règle annulée ou modifiée par F. Les modifications originales des deux scripts sont uniquement de mode, sans changement de contenu ; elles restent dans le checkout propriétaire.

Corrections : suffixe unique, verrou de job, manifeste strictement lié au fichier, rétention de 14 jours configurable après nouveau succès seulement, statut atomique conservant dernier succès, contrôle d’ancienneté/intégrité et extension facultative du canal d’alertes existant. Un job normalement en cours ne doit ni échouer artificiellement ni effacer l’incident précédent : le défaut de classification a été reproduit par test puis corrigé avec un état pending, une limite de durée et protection contre les faux rétablissements.

Treize tests Python des scripts et dix tests PHP d’alertes réussis en ciblé. Deux restaurations réelles passent sur des projets neufs générés, avec 38 tables identiques, compte/mot de passe, stocks, bâtiments, recherche, vaisseaux et file en attente vérifiés. Sources inchangées, event_scheduler OFF, aucun consommateur, aucun mail réel. Test permanent ajouté à la CI PHP ; reprise finale sur le code figé et contrôles obligatoires encore à exécuter.

La reprise des messages déjà publiés dans RabbitMQ reste un point distinct, explicitement documenté pour G ; ne pas présenter le test de données restaurées comme un PRA complet. Aucun cron installé, archive de production créée ou restauration de production exécutée.

Contrôles F sur le lot figé : make validate, make lint, npm run build, make test (**225 tests PHP, 205 599 assertions et 13 tests Python**), make test-browser (**91 scénarios, deux lots complets réussis : 48 et 43**, sans reprise sur cette exécution), git diff --check réussis. make test-backup repasse avec 38 tables comparées, compte/mot de passe et progression contrôlés ; nettoyage réussi des seuls projets générés. Bash et YAML du workflow validés, liens actifs vérifiés. Les nouveaux caches Python et archives générées sont ignorés par Git ; archives, manifestes et caches sont également exclus du contexte Docker.

Le propriétaire confirme qu’il n’existe pas de préproduction séparée : seulement l’Alpha et son local. Pour G, préparer puis exécuter les scénarios perturbateurs dans des projets locaux jetables ; ne pas les présenter comme validation du serveur hébergé et ne pas redémarrer la production.

Publication F : [PR #8](https://github.com/c41m07/genesis-reborn/pull/8), base dev, head b39871f8be04752929f4544544e8c1e305091db5, 23 fichiers attendus. Diff examiné, aucune revue, commentaire ou discussion au premier contrôle. CI distante en cours, incluant désormais la restauration isolée dans les deux lots PHP. Attendre les contrôles et revérifier les discussions/SHA avant fusion. Cette note reste locale pour le lot suivant.

## Chantier F — intégré à dev, non déployé

[PR #8](https://github.com/c41m07/genesis-reborn/pull/8) fusionnée et vérifiée le 6 novembre 2026 à 21 h 50 (Europe/Paris), commit d’intégration `c51484fe2ed4250ac420d5c68951a826a5bc0f20`. Les onze contrôles GitHub réussissent sur `b39871f8be04752929f4544544e8c1e305091db5` : deux suites PHP/scripts/restauration, huit lots navigateur et GitGuardian. Les journaux des deux jobs PHP confirment 225 tests PHP, 13 tests Python et la restauration des 38 tables avec compte/mot de passe et progression vérifiés, sans consommateur ni mail réel. PR clean, sans revue, commentaire ou discussion avant fusion. Aucun correctif de CI nécessaire. Origin/dev récupérée après fusion ; les statuts antérieurs ci-dessus restent historiques.

Résultat : archives uniques, verrou, checksum lié au bon fichier, conservation après succès, contrôle d’âge/intégrité et alertes facultatives sans faux rétablissement pendant un job. Documentation active et exclusion des fichiers générés livrées. Contrôles locaux complets : 225 tests PHP, 205 599 assertions, 13 tests Python, 91 navigateurs et restauration réelle isolée, sans reprise ciblée dans la validation finale. Aucun cron ou stockage distant configuré, sauvegarde de production créée ou restauration de production exécutée. La reprise des événements déjà dans RabbitMQ reste distincte de la restauration des données.

## Chantier G — préparation sur le local uniquement

Le propriétaire confirme : pas de préproduction séparée, seulement production et local. Ne pas redemander cette information et ne pas utiliser la production pour les essais perturbateurs. Branche feature/server-scenarios créée depuis le nouveau dev c51484f. Complexité initiale 4/5, à réévaluer en cas de défaut de reprise ou risque de données. Aucun nouveau code G.

Lectures ciblées commencées : scénarios navigateur existants de construction/notification, recherche, flottes et conquête. Le scénario de conquête exécute déjà un combat réel accéléré via les dates de la fixture, pas seulement un affichage de rapport. Les suites précédentes sont des preuves de ces parcours locaux, pas de la déconnexion et du redémarrage demandés en G.

Prochaine action : lire les helpers d’isolation et les scénarios concernés, compléter une matrice des six cas (construction hors connexion, recherche hors connexion, départ/retour, combat, notification, redémarrage). Vérifier effectivement l’absence de client connecté et la progression côté serveur, puis reconnexion et consultation. Pour le redémarrage, utiliser un projet local généré et protégé ; choisir une persistance représentative pour MariaDB/RabbitMQ plutôt que confondre perte d’un tmpfs de test avec panne de production. Aucune règle de jeu modifiée. Consigner conditions, résultats exécutés et procédures seulement préparées ; ne jamais présenter un résultat local comme validation de l’Alpha.

Point de livraison à conserver pour I : le workflow de déploiement manuel ne transfère actuellement que compose.prod.yaml et deploy.sh. Les scripts d’exploitation hôte et leur utilitaire Python doivent donc être installés séparément avec la bonne version, comme les jobs et variables privées ; ne pas annoncer leur installation automatique sur l’Alpha. Aucun transfert SSH ni déploiement lancé.

Cette conclusion F et la préparation G restent locales pour accompagner le prochain lot documentaire. G–K et refonte finale du README restent incomplets.

### G — premiers essais locaux

Nouveau scénario tests/browser/offline-progression.spec.mjs : fermeture de tous les contextes navigateur, contrôle SQL des niveaux et files avant toute reconnexion, puis consultation dans une nouvelle session. Deux essais ciblés réussissent : reprise depuis outbox (46,1 s), puis extension avec recherche déjà publiée avant redémarrage du relais/notificateur et relance du worker (55,2 s). La base et RabbitMQ restent actifs, leur tmpfs de test ne permettant pas de conclure à une persistance après redémarrage complet. Aucun serveur hébergé sollicité.

Documentation active SERVER_SCENARIOS.md ajoutée avec matrice des six cas et limites ; README, index, ROADMAP et rapport de nettoyage actualisés. Renforcement final de l’assertion de notification (exactement une par commande) à vérifier. Les six contrôles et la publication G restent à effectuer.

Extension G vérifiée : mode BROWSER_PERSISTENCE=1, volumes MariaDB/RabbitMQ propres au projet généré, arrêt ordonné complet application/base/transport/Mercure et traitements. Le scénario passe en environ 2 minutes, y compris recherche déjà publiée avant arrêt et exactement une notification par commande. Aucun arrêt brutal de l’hôte simulé. La CI navigateur sélectionne ce mode ; aucune configuration de production modifiée. Contrôles configuration/lint relancés après ajout YAML et script : réussis. PHP 225/205599 assertions, Python 13 et compilation réussissent ; suite navigateur complète à exécuter sur le code figé.

### G — validation locale finale

Six contrôles réussis : make validate, make lint, make test (225 PHP, 205599 assertions et 13 Python), npm run build, make test-browser (92 scénarios : lots complets de 48 en 22,5 min et 44 en 23,8 min, sans reprise ciblée) et git diff --check. Configuration/lint repris après ajout du mode persistant ; aucune correction d’échec. Les six cas de la matrice sont exécutés localement. Constructions et recherches terminées avec zéro contexte navigateur ; notifications persistées exactement une fois ; reprise depuis outbox puis message déjà publié après arrêt ordonné complet des services à volumes persistants. Flotte aller/retour, combat et notification en direct également réussis. Aucun résultat sur l’Alpha. Publication et CI G à effectuer ; H–K et refonte finale du README restent ouverts.

Publication G : PR #9 https://github.com/c41m07/genesis-reborn/pull/9, feature/server-scenarios vers dev, head fd292b12245d26b83b5bc601bc468917b3cee269, 11 fichiers/221 ajouts/4 retraits. Diff et SHA vérifiés ; aucune revue, commentaire ou discussion au premier contrôle. Dix contrôles GitHub réussissent, un navigateur encore en cours (job 128205945521, étape Browser suite in disposable project), publication d’image ignorée comme prévu. Le job navigateur 128205945102 confirme le nouveau scénario de reprise complète en 1,7 min et 21 tests réussis ; le job PHP 128205945299 confirme 225 PHP/205599 assertions, 13 Python et restauration de 38 tables avec compte/mot de passe et progression vérifiés, sans consommateur ni mail réel. Attendre le dernier contrôle, relire discussions et SHA, puis fusionner si protections satisfaites. Note locale pour le prochain lot documentaire.

## Chantier G — lot local intégré à dev, non déployé

PR #9 https://github.com/c41m07/genesis-reborn/pull/9 fusionnée et vérifiée le 7 novembre 2026 à 00 h 20 (Europe/Paris), commit 2870e138acaff8709121b756802897f98c8db037. Les onze contrôles GitHub réussissent sur fd292b12245d26b83b5bc601bc468917b3cee269 : deux lots PHP/scripts/restauration, huit lots navigateur et GitGuardian. Les deux publications d’image sont ignorées comme prévu. Aucune correction de CI ni relance nécessaire. Le dernier job confirme 23 scénarios réussis, sans échec ; sa durée globale supérieure ne constitue pas un défaut démontré. PR clean, SHA et base dev revérifiés, aucune revue, commentaire ou discussion avant fusion. Origin/dev récupérée après fusion.

Résultat G : 11 fichiers ciblés (scénario hors connexion, helper d’isolation, lanceur et override de persistance, workflow, README et cinq documents). Les six cas demandés ont une preuve locale et une matrice active ; aucune règle de jeu ni configuration de production modifiée. Contrôles locaux réussis : validate, lint, 225 PHP/205599 assertions et 13 Python, compilation, 92 navigateurs en deux lots complets 48+44 sans reprise ciblée et diff-check. Nettoyage vérifié : zéro conteneur/volume restant pour les trois essais ciblés et les deux lots complets. Les fichiers du propriétaire dans le checkout original restent préservés ; seuls les fichiers du lot ont été commités.

Limites ouvertes : Alpha hébergée non validée, aucune production redémarrée, arrêt brutal de l’hôte et reprise après restauration du transport non testés. La restauration SQL n’est pas présentée comme un PRA complet. Scénarios perturbateurs autorisés uniquement dans des instances de test isolées. Pas de préproduction disponible, information déjà fournie par le propriétaire.

## Reprise H — mesures par univers

Branche feature/universe-metrics créée depuis le nouveau origin/dev 2870e13 ; ne pas repartir de la branche G. Complexité initiale 4/5 : définitions de métriques, qualité des données historiques et droits admin à analyser avant modification. Aucun code H. Prochaine action : vérifier Git et les consignes, lire uniquement contrôleur/service admin, entités et repositories de progression, ressources, files et combats ; déterminer les mesures réellement calculables et leurs limites. Ne pas transformer des niveaux courants en durées historiques inventées. Mesurer sans changer l’équilibrage ; documenter données absentes et périmètre des indicateurs. Puis six contrôles, commit/push/PR dev et CI avant fusion.

H–K et refonte finale du README restent ouverts. La conclusion G et cette préparation H sont locales pour accompagner le prochain lot documentaire. Éléments à préserver hors staging : config/reference.php généré, scripts/deploy.sh et scripts/verify-install.sh sans diff de contenu, node_modules lié aux dépendances existantes. Le suivi local est notre travail de continuité.

### H — audit ciblé et plan de calcul

Lectures : AdminController et index/catalogue admin, GameRepository, entités PlayerUniverse/Planet/PlanetResource/QueueItem/CombatEvent/CombatLog/CombatParticipant/Fleet, ResourceService::snapshot et fonctions de bonus de production. Les snapshots de ressources sont en lecture seule (contrairement à materialize), sans flush ni modification de stocks. Les participations conservent joinedAt, dernière protection après pertes et échéance ; les déblocages sont des booléens sans date. Les files terminées disparaissent. Fleet.departureAt est réécrit au retour et en combat : ne pas le présenter comme une nouvelle activité après défaite.

Plan H sans migration ni équilibrage : page dédiée protégée par ROLE_ADMIN, liée à chaque carte d’univers ; service de lecture séparé. Ancienneté des participations et niveaux actuels comme contexte de progression, pas comme temps d’atteinte de jalon. Attentes mesurées seulement sur les files actuelles (âge des commandes non démarrées, délai avant démarrage des actives, restant/retard). Ressources projetées avec le moteur existant sur une page bornée de colonies, couverture affichée, pénuries/productivité sans prétendre connaître les commandes refusées. Protections après fortes pertes et verdicts de combats ; durée réelle de reconstitution indisponible. Définir les dénominateurs, UTC, zéros/absences et données manquantes dans un guide actif. Tests ciblés d’isolation par univers, calcul temporel, absence d’écriture et accès admin ; UI mobile/clavier selon AGENTS, puis six contrôles et PR dev.

Dépendance de tests à examiner : operations-alerts.spec.mjs reconstruit ses arguments Compose avec les deux fichiers habituels, alors que G a introduit un troisième override facultatif pour la persistance. Son appel up worker peut réappliquer les dépendances sans cet override. Reproduire sur projet jetable avant correction ciblée ; ne jamais utiliser la production. Complexité réévaluée et maintenue à 4/5, vérification renforcée de l’isolation. Aucun code H modifié à ce stade.

### H — implémentation et validation ciblée

UniverseMetricsService en lecture seule, route protégée app_admin_universe_metrics et page dédiée liées aux cartes d’univers. Mesures documentées dans UNIVERSE_METRICS.md : ancienneté et niveaux actuels, attente sur les files conservées, 25 colonies par page avec couverture explicite, ressources projetées sans matérialisation, protections après pertes et verdicts par univers. Les durées de jalons et de reconstitution restent indisponibles. Les univers fermés affichent leurs stocks conservés, débits et productivités non applicables ; aucune production post-fermeture simulée. README, index et ROADMAP actualisés. Aucun changement de règle, valeur ou schéma.

Trois tests MariaDB ciblés réussissent (39 assertions) : séparation des univers y compris niveaux/files/rapports, temps et date future, absence d’écriture même après flush, données absentes/futures et pagination 25/26, univers vide et stocks fermés non projetés. Scénario navigateur ciblé réussi (26,2 s) : refus joueur/anonyme, accès admin, lien depuis l’admin, horodatage/données manquantes, lecture seule, clavier, 1440/768/390, thèmes sombre/clair, axe sans violation et univers fermé/404.

Deux défauts de contraste successifs détectés dans la nouvelle page claire : liens/en-têtes (4,04/3,96), puis légende du tableau (4,27). Corrections CSS strictement limitées à .universe-observations en thème clair, sans exclusion de règle ni affaiblissement de test. La reprise ciblée finale passe. La compilation est terminée avant chaque lancement navigateur ; ne jamais recompiler pendant la suite complète.

L’hypothèse de changement des volumes par le test d’alertes n’est pas reproduite : contrôle réel avant/après relance du worker dans un projet persistant, mêmes volumes MariaDB/RabbitMQ (42,2 s, réussi). Aucune correction spéculative de configuration ; assertion de conservation ajoutée au scénario existant pour garder cette preuve. Tous les essais restent isolés avec mails neutralisés. Les six contrôles et la PR H restent à effectuer ; le total intégré reste 58 %.

H — contrôles sur le lot figé : make validate et make lint réussis ; make test réussit en exécution complète (228 PHP, 205638 assertions, 13 Python) ; npm run build et git diff --check réussis. Les deux lots navigateur complets sont lancés en mode persistant, logs /tmp/genesis-h-full-1.log et /tmp/genesis-h-full-2.log, sorties test-results/h-full-1 et h-full-2 ; sessions exec 38010 et 71817. Attendre leur fin et examiner les résultats avant commit/push. Ne plus recompiler pendant les navigateurs. Le source est figé ; seuls des résultats de suivi peuvent encore être consignés. H non publié, G intégré par PR #9 et non déployé.

### H — résultats locaux finaux

Les six contrôles réussissent : make validate, make lint, make test (228 PHP, 205638 assertions, 13 Python), npm run build, make test-browser (93 scénarios, deux lots complets 49 en 23,5 min et 44 en 24,4 min, aucune reprise sur cette exécution finale) et git diff --check. Les deux corrections ciblées de contraste précédentes restent consignées ci-dessus ; ne pas les masquer en parlant d’une première exécution globale sans correction. Les tests ciblés de calcul comptent 3 tests/39 assertions. Vérification visuelle de la capture mobile réalisée, tableau défilant et sections lisibles ; présence du lien d’évitement sous focus dans la capture, sans défaut de débordement. Les contrôles ciblés RGAA ne constituent pas un audit complet.

Nettoyage réel vérifié pour les quatre essais ciblés et les deux lots finaux : aucun de leurs conteneurs ni volumes ne subsiste. Documentation et 19/37/11/3 liens vérifiés. Le dernier origin/dev récupéré reste 2870e13. Publication H à réaliser sur cette branche ; ne pas commiter les fichiers générés ou les modes étrangers au lot. Aucun serveur Alpha sollicité, aucun mail réel ni changement d’équilibrage.
