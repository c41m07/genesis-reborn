# Consignes du dépôt

## Règles critiques

- **Univers et droits.** Vérifier côté serveur la participation du joueur, le propriétaire, l’univers et les permissions de chaque ressource, y compris dans les listes, compteurs et fragments privés.
- **Base existante.** La création d’une migration et son application sont deux opérations distinctes. Avant toute migration, restauration ou import qui modifie une base existante, obtenir l’autorisation explicite et vérifier une sauvegarde. Voir le [guide des sauvegardes](docs/SAUVEGARDES.md).
- **Gameplay.** Toute modification des règles ou de l’équilibrage exige une décision explicite. Consigner la décision dans [les décisions produit](docs/DECISIONS.md) et actualiser [les règles du jeu](docs/REGLES.md). Justifier et documenter toute suppression ou incompatibilité.
- **Données privées.** Garder les secrets et les données personnelles hors des fichiers versionnés, des journaux, des commentaires et des comptes rendus.
- **Actions différées.** Préserver les transactions, verrous, jetons et protections contre les doubles effets d’un message obsolète ou rejoué.

## Commencer et terminer un lot

- Répondre en français. Avant toute intervention, vérifier la branche, `git status` et les modifications locales. Préserver le travail présent, les logos et les SVG d’identité. Annoncer uniquement les actions réalisables avec les accès et les outils vérifiés dans la session.
- Partir du [périmètre livré](README.md), de [l’index documentaire](docs/README.md) et de [la feuille de route](docs/FEUILLE_DE_ROUTE.md). L’index de `docs/archives/` précise quelles références historiques restent utiles.
- Avant de coder, définir un résultat observable par le joueur, l’administrateur ou l’exploitant. Rechercher le parcours existant et les composants déjà préparés, même sans appelant, option d’activation ou TODO.
- Prolonger le parcours existant lorsqu’il convient. Si deux voies coexistent, identifier celle qui fait référence, les effets susceptibles de se produire deux fois, les données, liens et messages à préserver et la condition de retrait de l’ancienne voie. Faire valider toute perte de comportement.
- Exercer le parcours depuis son entrée réelle. Consigner le critère, le résultat observé et la vérification dans la PR ou le compte rendu du lot. Relire le diff pour repérer code provisoire, commentaires périmés et modifications étrangères.
- Terminer les branchements relevant du lot. Si une décision ou une dépendance externe manque, garder le lot ouvert et inscrire le blocage, le travail restant et le critère de clôture dans le suivi de reprise. Pour une fonctionnalité désactivée, citer la décision correspondante. Si le déclenchement dépend d’un VPS inaccessible, indiquer « planification non vérifiable depuis le dépôt » et préciser le contrôle d’exploitation restant.

## Choisir les vérifications du lot

Partir des cibles du [Makefile](Makefile). Choisir les lignes qui correspondent aux risques de la modification. Exercer les opérations qui changent un état sur des données synthétiques en environnement isolé.

| Changement | Vérification attendue |
| --- | --- |
| Carte ou formulaire Twig, CSS ou Stimulus | Compiler avec `make assets` si les assets changent. Cibler le parcours avec `make test-browser` et son argument `BROWSER_ARGS` (par exemple `quantity-max.spec.mjs`). Exercer l’action sur ordinateur et mobile, au clavier, dans les thèmes concernés. Pour un champ Quantité, saisir 291 puis utiliser Max. Vérifier que tous les chiffres et chaque coût restent visibles dans leur case. |
| Règle métier, accès ou correctif | Avec `make test-php` et son argument `PHP_TEST_PATH`, reproduire le cas depuis l’entrée concernée. Vérifier le résultat, les limites et les refus applicables, notamment pour un autre joueur ou univers. Ajouter un test de régression lorsqu’il est pertinent. |
| Mission, livraison, récompense ou notification différée | Suivre le déclencheur, l’outbox, le routage et le worker jusqu’à l’effet visible. Pour un convoi, vérifier la boîte et le rapport attendus. Rejouer le message et vérifier l’absence de double effet. Si le risque porte sur deux actions simultanées, les exécuter réellement en concurrence. |
| Service Symfony, fournisseur ou option | Vérifier le service sélectionné par la configuration de production livrée et la disponibilité de ses dépendances. Tester l’état par défaut puis l’activation. Compléter les essais avec un faux fournisseur par le contrôle du branchement livré, en environnement isolé et sans envoi externe. |
| Schéma ou sauvegarde | Tester la migration sur une base isolée et contrôler le schéma avec `make lint-schema`. Pour une modification de la chaîne de sauvegarde, exercer la restauration isolée avec `make test-backup`. |
| Refactorisation ou optimisation | Couvrir les résultats et les droits des contextes touchés. Pour annoncer un gain, appliquer le protocole de mesure de la section Performances. |
| Documentation ou consignes | Contrôler les liens, les commandes citées et leur accord avec le code, le Makefile ou le workflow concerné. |

## Lisibilité, commentaires et refactorisation

- Suivre [l’architecture](docs/ARCHITECTURE.md), les conventions et le suivi du domaine. Limiter le changement au besoin établi, conserver les lectures ciblées et les protections déjà validées. Séparer reformatage, refactorisation et changement de comportement pour faciliter la revue.
- Écrire les identifiants en anglais, les messages utilisateur en français. Choisir des noms métier explicites, typer propriétés, paramètres et retours, écrire une instruction par ligne et développer les conditions lorsque cela facilite la lecture. Conserver les conventions Symfony/PHP.
- Donner une responsabilité claire à chaque composant. Centraliser les règles et constantes communes à PHP, Twig et JavaScript. Introduire un DTO, une abstraction ou une dépendance pour un besoin identifié. Documenter tout nouvel outil et vérifier les usages avant de retirer du code.
- Préserver les contrats publics et persistés, notamment routes, colonnes SQL, clés et messages. Traiter les erreurs attendues, préserver la journalisation et les réponses neutres des parcours sensibles.
- Commenter en français les choix que le code seul n’explique pas. Pour un traitement complexe, donner son intention d’ensemble puis expliquer les étapes sensibles : ordre des opérations, périmètre d’un verrou, visibilité, état périmé, idempotence ou compatibilité historique.
- Actualiser les commentaires avec le code après avoir vérifié le comportement. Retirer ceux devenus faux ou redondants. Réserver les PHPDoc aux contrats, structures de tableaux et contraintes que les types natifs expriment mal. Préserver les annotations requises par les outils.

## Architecture Symfony, Doctrine, Twig et Stimulus

- Préserver la structure contrôleur → service métier → manager/repository du projet. Les contrôleurs valident la demande, vérifient les accès, délèguent et construisent la réponse ; les services portent les règles et orchestrent les actions ; les repositories regroupent les lectures ; le manager conserve les garanties transactionnelles existantes.
- Injecter les dépendances explicitement. Éviter les accès globaux au conteneur, l'état partagé mutable et les classes regroupant des responsabilités sans lien ; réutiliser les mécanismes Symfony et les abstractions déjà présentes avant d'en ajouter.
- Distinguer préparation d'affichage, vérification d'une action et modification des données. Nommer et documenter les lectures qui synchronisent ou écrivent effectivement ; ne pas retirer leurs effets métier pour accélérer une page sans analyser les garanties concernées.
- Préparer dans les services les données nécessaires à la page ou à l'onglet consulté. Conserver les dépendances utiles aux prérequis et les panneaux communs affichés ; ne pas calculer les cartes et détails des rubriques inutilisées. Les données d'affichage ne remplacent jamais la validation serveur d'une action.
- Garder Twig centré sur la présentation : conditions et boucles simples, fragments réutilisables, sans calcul métier complexe ni appel à un service de chargement coûteux par ligne. Anticiper les lectures implicites des associations Doctrine utilisées par les templates. Conserver l'échappement automatique ; réserver `raw` aux contenus explicitement maîtrisés ou assainis.
- Donner aux contrôleurs Stimulus des responsabilités ciblées et utiliser les actions, targets et values adaptées. Nettoyer dans `disconnect()` les écouteurs ajoutés manuellement, minuteries, observateurs, abonnements et requêtes en cours ; vérifier les reconnexions après remplacement de fragments.
- Pour les requêtes JavaScript, gérer les erreurs et réponses obsolètes, les changements de joueur/univers/colonie et les soumissions multiples. Préserver focus, sélection, saisies et formulaires modifiés lors des actualisations ; une annulation côté navigateur ne garantit pas l'arrêt du traitement serveur.
- Préserver les contrats des messages asynchrones et événements, l'outbox, les jetons, les tentatives et la gestion des échecs. Une notification temps réel déclenche une récupération autorisée de l'état ; elle ne constitue pas une preuve qu'une action reste permise.
- Pour HTML/CSS et les assets, réutiliser les composants et styles cohérents du projet, préserver la structure sémantique et les parcours sans JavaScript lorsqu'ils existent. Vérifier les chargements et contrôleurs requis avant de déplacer ou différer un fichier ; ne pas changer d'outillage frontend sans besoin démontré.

## Dates et fuseaux horaires

- Stocker chaque instant en UTC. Pour une colonne `DATETIME` sans fuseau, convertir l’instant en UTC à l’écriture et interpréter la valeur lue en UTC. Une colonne `DATE` représente un jour civil et se conserve sans conversion.
- Afficher les heures dans le fuseau choisi dans les préférences du joueur. Pour un courriel ou une notification différée, utiliser le fuseau du destinataire et le repli prévu par l’application si sa préférence manque. Valider tout identifiant de fuseau reçu du client.
- Avant de formater une date déjà chargée, appliquer explicitement le fuseau d’affichage. Préférer un objet immuable ou convertir une copie pour préserver la valeur de l’entité. Si un traitement change temporairement le fuseau de PHP ou de Twig, le rétablir dans un bloc `finally`, y compris dans les workers.
- Pour filtrer les événements d’un jour, calculer le début de ce jour et le début du jour suivant dans le fuseau concerné, puis convertir les deux bornes en UTC pour la requête SQL ou DQL. Exclure la borne de fin. Lors d’un changement d’heure, deux minuits locaux peuvent être séparés de 23 ou 25 heures.
- Pour une échéance de jeu, appliquer le fuseau fixé par la règle métier. Par exemple, le classement quotidien suit minuit en `Europe/Paris`, quel que soit le fuseau d’affichage du joueur. Préserver le format des API existantes et, pour tout nouvel horodatage textuel échangé, utiliser ISO 8601 avec `Z` ou un décalage explicite.
- Dans les tests concernés, fixer l’instant et le fuseau. Vérifier que deux joueurs dans des fuseaux différents voient le même événement à leur heure locale et que l’instant stocké reste identique. Couvrir les changements d’heure pour les règles fondées sur le jour civil.

## Performances et protections contre les régressions

- Mesurer avant et après une optimisation avec le même scénario, les mêmes données, le même mode de débogage et des états de cache comparables. Distinguer nombre de requêtes, temps SQL, temps serveur, mémoire, volume transféré et rendu navigateur ; séparer premier chargement et passages à chaud. Indiquer « non mesuré » si les données manquent.
- Éviter les requêtes répétées par carte, rang ou ligne : charger ensemble les niveaux, stocks, files et associations nécessaires. Filtrer et paginer en base lorsque pertinent, préserver le tri global et vérifier les index avec les requêtes réelles ; ne pas remplacer les lectures répétées par le chargement illimité de données.
- Respecter dans les listes, recherches, compteurs et réponses HTML/JSON les mêmes règles de visibilité et d'univers. Une optimisation de requête, jointure ou pagination ne doit ni exposer une ressource interdite ni exclure silencieusement un résultat autorisé.
- Réutiliser les lectures communes pendant une requête uniquement lorsque leur état reste valable. Après écriture, synchronisation, rollback ou rafraîchissement sous verrou, invalider ou recharger les données concernées ; ne pas valider une action avec un stock ou une permission périmés. Tout cache partagé doit définir son périmètre, sa durée et son invalidation, avec isolation des données privées.
- Optimiser les actualisations et fichiers du navigateur selon les mesures, sans réduire arbitrairement la fraîcheur des informations. Respecter les exclusions explicites du chantier, notamment une barre de ressources à conserver : vérifier aussi les effets indirects des services partagés.
- Utiliser des données synthétiques et des environnements isolés pour les essais modifiant l'état. Ne pas déclencher d'action de jeu sur la base habituelle, perturber une instance de tests partagée ou envoyer des notifications externes pour produire un benchmark. Ne pas changer les verrous ni l'ordre des transactions sans étude dédiée des accès concurrents.
- Lorsque pertinent, ajouter des garde-fous de performance sur un scénario stable : plafonds de requêtes avec marge justifiée et comparaison de plusieurs volumes pour détecter les lectures par élément. Éviter les nombres SQL exacts fragiles et les seuils de temps serrés en CI ; ne pas affaiblir les tests pour faire passer une optimisation.
- Documenter les limites des mesures et conserver une possibilité de retour arrière compatible avec les données.

## Maintenance continue de la documentation

- Mettre à jour le README dans le même lot lorsque changent les fonctionnalités, commandes, dépendances ou choix d’architecture qui y sont documentés.
- Tenir la documentation à jour au fur et à mesure du développement, dans le même lot que les changements concernés : README, documentation de `docs/`, wiki lorsqu’il est accessible, guides d’installation et d’utilisation, références techniques, feuille de route et suivi de reprise.
- À chaque ajout, modification ou suppression de fonctionnalité, commande, dépendance ou élément d’architecture, examiner les documents concernés et les synchroniser avec le comportement réellement vérifié. Ajouter les explications des nouveautés et retirer ou corriger celles qui ne s’appliquent plus.
- Supprimer les contenus et fichiers documentaires devenus inutilisés, redondants ou orphelins après avoir vérifié leurs références et leur utilité. L’absence de lien entrant ne suffit pas à justifier une suppression ; préserver les décisions validées et les références historiques utiles, en les archivant et en les identifiant clairement lorsque nécessaire.
- Après un ajout, déplacement ou retrait de document, actualiser les index, sommaires et liens concernés pour éviter les références cassées et garder un point d’entrée clair vers l’information à jour.
- Si une source documentaire externe, notamment le wiki, est inaccessible, signaler précisément ce qui reste à synchroniser sans prétendre l’avoir mis à jour.

## Format des patchnotes

- Lorsqu’un patchnote est demandé, fournir quatre champs distincts, prêts à copier dans le formulaire d’administration : **Titre**, **Version**, **Résumé** et **Contenu**.
- Titre : `Mise à jour Alpha-X.Y.Z — Thèmes principaux`. Reprendre la version donnée ; si elle est inconnue, annoncer clairement une version proposée, sans la présenter comme publiée.
- Résumé : un seul paragraphe court présentant les principaux changements, dans un français clair destiné aux joueurs.
- Contenu : texte brut, sans syntaxe Markdown nécessaire au rendu. Utiliser les sections en majuscules utiles au lot : `NOUVEAUTÉS`, `ÉQUILIBRAGE`, `AMÉLIORATIONS ET CORRECTIONS`, `ADMINISTRATION`, `INFORMATIONS`. Ne pas ajouter de section vide.
- Chaque point commence par `• Fonction : explication concrète pour le joueur`. Séparer les points par une ligne blanche et les sections par deux lignes blanches. Réserver les informations générales à de courts paragraphes dans `INFORMATIONS`, avec un rappel du bouton Feedback si pertinent.
- Décrire uniquement les changements vérifiés du lot à livrer, sans reprendre les anciennes annonces comme des nouveautés. Préciser les conditions utiles (univers, position de référence, nouveaux empires ou stocks existants) et éviter les détails internes tels que commits, commandes ou variables de configuration.
- Distinguer clairement une note proposée d’une version déjà publiée ; ne pas annoncer un déploiement sans en avoir vérifié le résultat.

## Organisation des pages et de la navigation

- Pour tout ajout ou changement d’interface, choisir explicitement entre page dédiée, onglet, filtre, ancre et contenu dépliable selon l’objectif du joueur, le volume de contenu, la fréquence d’utilisation et les besoins sur mobile. Expliquer brièvement les choix structurants dans le suivi du lot.
- Privilégier une page dédiée pour une tâche autonome, une gestion comportant ses propres formulaires ou un contenu conséquent consulté indépendamment. Lui donner une URL stable, un titre précis et une navigation cohérente ; vérifier l’accès direct, le rechargement et le retour arrière. Ne pas simuler cette séparation en masquant simplement les sections d’une grande page avec JavaScript.
- Réserver les onglets aux vues proches d’un même sujet, et les filtres à la sélection ou à la présentation d’un même ensemble de données. Un lien présenté comme un onglet peut mener à une vraie page ; son apparence ne détermine pas l’organisation du contenu.
- Utiliser les ancres pour parcourir les sections d’un même document ou résultat, par exemple un rapport de combat ou une aide. Ne pas les employer comme substitut à des pages correspondant à des tâches indépendantes. Le nombre de titres seul ne justifie pas la création automatique d’un sommaire.
- Employer un contenu dépliable pour une explication complémentaire ou une option secondaire, sans dissimuler une information indispensable à la décision. Garder ensemble les étapes et informations nécessaires à une même action afin d’éviter les allers-retours et la perte de saisie.
- Organiser les espaces autour d’une entrée principale ouvrant leur page d’accueil, puis d’une navigation secondaire commune vers leurs sous-pages. Garder le menu principal compact et rendre identifiables l’espace et la page actifs, sur ordinateur comme sur mobile.
- Préserver les permissions, les contextes de colonie et d’univers, les confirmations, les liens existants et les redirections après action. Une réorganisation visuelle ne doit retirer aucun accès autorisé ; notamment, les relations entre commandants restent accessibles avec ou sans guilde.
- Adapter les lectures et actualisations aux données réellement nécessaires à chaque page, sans dupliquer les traitements métier ni charger systématiquement toutes les rubriques d’un espace.
- Appliquer ces principes au périmètre traité, sans imposer une refonte générale des écrans existants. Toute séparation doit apporter un bénéfice concret de compréhension, de navigation ou de maintenance, avec les vérifications d’accessibilité et la documentation correspondantes.

## Accessibilité (RGAA) et SEO

- Textes d’aide dans l’interface : ajouter uniquement des précisions utiles, courtes et placées au bon endroit. Éviter les explications évidentes ou répétitives et les mentions qui surchargent les libellés, débordent des menus ou gênent la lecture. Conserver les informations nécessaires pour comprendre une action, ses conditions ou ses conséquences.
- Pour toute modification d’interface, vérifier les points RGAA concernés : structure des titres, libellés des champs, messages d’erreur compréhensibles, navigation au clavier, visibilité du focus et contrastes. Ces vérifications ciblées ne constituent pas à elles seules un audit de conformité RGAA.
- Donner aux images informatives un texte alternatif pertinent. Pour le logo, utiliser `alt="Genesis Reborn"` ; pour un emblème purement décoratif, utiliser `alt=""` afin qu’il soit ignoré par les lecteurs d’écran. Adapter l’alternative si l’image porte une information ou constitue seule un lien ou un bouton.
- Prévoir dans le `<head>` une meta description adaptée au contenu de chaque page publique indexable. Pour l’accueil, utiliser : `<meta name="description" content="Genesis Reborn, jeu de stratégie spatiale par navigateur : bâtis ta colonie, explore des systèmes inconnus et forge ton empire.">`.
- Mettre la page de connexion en `noindex` avec `<meta name="robots" content="noindex">`. Conserver l’indexation de l’accueil et de la page d’inscription. Ne pas bloquer la connexion dans `robots.txt` si cela empêche les moteurs de lire sa directive `noindex`.
- Renseigner `og:title`, `og:description` et `og:image` pour les pages publiques partageables, avec des valeurs cohérentes avec leur contenu et une URL absolue publiquement accessible pour l’image, afin de permettre les aperçus de liens notamment sur Discord.
- Maintenir un `robots.txt` et un `sitemap.xml` cohérents avec le domaine de production et les règles d’indexation. Le sitemap doit référencer uniquement les URL canoniques, publiques et indexables ; exclure la connexion et les pages privées.
- Lors de la mise en production, prévoir la vérification du domaine et la soumission du sitemap dans Google Search Console avec un accès autorisé au compte propriétaire. Ne pas annoncer cette étape comme réalisée sans en avoir vérifié le résultat.
- Lors d’un changement de template ou de routage concerné, contrôler le HTML rendu (alternatives, métadonnées et directives d’indexation), ainsi que les réponses de `robots.txt` et `sitemap.xml` si elles sont modifiées.

## Branches, livraison et CI

- **Par défaut**, créer une branche par lot depuis `dev` actualisée et ouvrir sa PR en brouillon vers `dev`. Nommer la branche `feature/<sujet>`, `fix/<sujet>`, `refactor/<sujet>`, `docs/<sujet>` ou `chore/<sujet>`, selon le lot. Écrire le sujet en minuscules, sans accents, avec des tirets et sans préfixe `codex/`.
- **Sur demande explicite du propriétaire**, un push direct sur `dev` est autorisé. Sa protection a été retirée le 27 novembre 2026. Ce push ne déclenche pas la CI. Indiquer dans le compte rendu les contrôles effectués et si une CI a été lancée manuellement.
- Pendant le développement, lancer les contrôles ciblés pertinents et `git diff --check` avant commit ou push. Une PR en brouillon ajoute une CI légère qui vérifie le diff, Compose, PHP/Twig/YAML et le conteneur Symfony, compile les assets et exécute les tests Python, sans base ni migration.
- **Avant fusion vers `dev` ou `alpha`**, actualiser la branche si nécessaire et passer la PR en « prête à relire ». Attendre la CI complète et ses cinq jobs `quality` réussis sur la dernière révision. Un nouveau commit sur une PR prête relance la suite complète. Repasser en brouillon si le développement reprend.
- Une branche de travail peut être poussée pour diagnostic en signalant les échecs. Avant fusion, corriger tout échec sans affaiblir les tests et relancer les contrôles concernés. Éviter de répéter un contrôle réussi sur un contenu identique. Pendant la CI, un lot indépendant peut commencer sur une autre branche avec une base de test distincte. Un lot dépendant attend l’intégration du précédent.
- Le [workflow CI](.github/workflows/ci.yml) exécute aussi la suite complète sur les push vers `alpha`. Le lancement manuel propose une catégorie ou la suite complète. Seule la suite complète peut publier sur une branche de livraison.
- Pour reproduire les contrôles locaux : `make validate`, `make lint`, `make test`, `make assets`, `BROWSER_PERSISTENCE=1 make test-browser` et `git diff --check`. La CI complète vérifie aussi la sauvegarde et la restauration isolées. Distinguer recette du parcours, contrôles locaux, CI GitHub, intégration et déploiement dans le bilan.

## Documents de pilotage et périmètre d'application

- Le présent fichier régit le travail sur le dépôt. Les règles métier validées dans `docs/DECISIONS.md` et `docs/REGLES.md` ne peuvent pas être supplantées par une simple consigne de méthode.
- Pour une mission Work Genesis Reborn, consulter intégralement les sources du projet Work lorsqu'elles sont accessibles : `GENESIS_REBORN_PROMPT_MAITRE_WORK_V4_COMPLET.md`, `GENESIS_REBORN_BRIEF_FIGMA_WORK_V2_COMPLET.md` et `GENESIS_REBORN_PROTOCOLE_DEVELOPPEMENT_ADAPTATIF_WORK_V1.md`.
- Ces documents peuvent être joints à Work sans exister physiquement dans le dépôt. Vérifier leur présence avant d'utiliser un chemin local ; ne jamais prétendre avoir lu un fichier inaccessible.
- Le prompt maître V4 pilote les 15 phases et leurs critères ; le brief Figma V2 définit les exigences UX/UI ; le protocole adaptatif V1 ajuste la méthode de travail, sans modifier les exigences ni alléger les vérifications.
- En cas de contradiction, respecter les décisions explicites validées, la sécurité et l'intégrité des données, puis signaler le conflit documentaire sans l'arbitrer silencieusement.

## Développement adaptatif

- Avant chaque sous-tâche, examiner les fichiers concernés, dépendances, permissions, impacts sur les données, risques de régression et sécurité ; attribuer une complexité de 1 à 5.
- **Niveaux 1–2** : analyse rapide, modification ciblée et tests pertinents. **Niveau 3** : analyse des dépendances et tests adaptés. **Niveaux 4–5** : planification détaillée, analyse d'architecture, vérifications renforcées et revue des risques.
- Réévaluer la complexité après deux corrections ciblées infructueuses, une dépendance inattendue, un changement de périmètre ou un risque de perte de données/sécurité. Ne pas répéter indéfiniment la même tentative.
- Si l'environnement autorise explicitement une sélection de modèle **entre sous-tâches**, envisager Sol Mini (simple), Sol Moyen (intermédiaire) et Astra (critique, si disponible). Ne jamais affirmer qu'un changement de modèle a eu lieu sans preuve de l'outil. À défaut, adapter uniquement la profondeur d'analyse.
- Le niveau de complexité conserve les validations de phase du prompt maître V4. Les modalités de livraison figurent dans la section « Branches, livraison et CI ».
