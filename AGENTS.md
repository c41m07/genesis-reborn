# Consignes du dépôt

- Mettre à jour le README dans le même lot lorsque changent l’architecture, les dépendances, les commandes ou les fonctionnalités qui y sont documentées.

- Répondre en français. Faire des changements ciblés et préserver tout travail déjà présent dans le dépôt.
- Ne pas changer les règles de jeu ou les valeurs d’équilibrage sans décision explicite ; le code décrit le comportement, les décisions produit sont consignées dans [docs/DECISIONS.md](docs/DECISIONS.md) et les règles actuelles dans [docs/REGLES.md](docs/REGLES.md).
- Lancer d’abord les commandes disponibles dans le [Makefile](Makefile). Vérifications validées : `make validate`, `make lint`, `make assets` (équivalent à `npm run build` depuis `app/`), `make test`, `make test-browser`.
- Le périmètre livré et les objectifs sont dans [README.md](README.md), [docs/README.md](docs/README.md) et [docs/FEUILLE_DE_ROUTE.md](docs/FEUILLE_DE_ROUTE.md). Les documents de `docs/archives/` sont historiques, sauf mention contraire ; leur index distingue les références conservées des bilans remplacés.
- Conserver les logos et les SVG d’identité.

## Protection des données, sécurité et fiabilité

- Protection des données : distinguer la création d’une migration de son application. Sur une base existante, exiger une autorisation explicite et une sauvegarde vérifiée avant migration, restauration ou import modifiant les données.
- Secrets et données personnelles : ne jamais versionner ni afficher de mots de passe, jetons, fichiers d’environnement privés ou données personnelles dans les journaux et comptes rendus.
- Isolation des univers et permissions : vérifier côté serveur le propriétaire, la participation et l’univers de chaque ressource manipulée, même si l’interface masque déjà l’action.
- Concurrence et traitements différés : préserver les transactions, verrous et jetons existants ; vérifier qu’un message reçu plusieurs fois ou devenu obsolète ne produit aucun double effet.
- Tests de régression : pour un correctif métier, ajouter un test reproduisant le problème lorsque pertinent ; couvrir les refus d’accès et les cas limites concernés.

## Maintenance continue de la documentation

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

## Accessibilité (RGAA) et SEO

- Pour toute modification d’interface, vérifier les points RGAA concernés : structure des titres, libellés des champs, messages d’erreur compréhensibles, navigation au clavier, visibilité du focus et contrastes. Ces vérifications ciblées ne constituent pas à elles seules un audit de conformité RGAA.
- Donner aux images informatives un texte alternatif pertinent. Pour le logo, utiliser `alt="Genesis Reborn"` ; pour un emblème purement décoratif, utiliser `alt=""` afin qu’il soit ignoré par les lecteurs d’écran. Adapter l’alternative si l’image porte une information ou constitue seule un lien ou un bouton.
- Prévoir dans le `<head>` une meta description adaptée au contenu de chaque page publique indexable. Pour l’accueil, utiliser : `<meta name="description" content="Genesis Reborn, jeu de stratégie spatiale par navigateur : bâtis ta colonie, explore des systèmes inconnus et forge ton empire.">`.
- Mettre la page de connexion en `noindex` avec `<meta name="robots" content="noindex">`. Conserver l’indexation de l’accueil et de la page d’inscription. Ne pas bloquer la connexion dans `robots.txt` si cela empêche les moteurs de lire sa directive `noindex`.
- Renseigner `og:title`, `og:description` et `og:image` pour les pages publiques partageables, avec des valeurs cohérentes avec leur contenu et une URL absolue publiquement accessible pour l’image, afin de permettre les aperçus de liens notamment sur Discord.
- Maintenir un `robots.txt` et un `sitemap.xml` cohérents avec le domaine de production et les règles d’indexation. Le sitemap doit référencer uniquement les URL canoniques, publiques et indexables ; exclure la connexion et les pages privées.
- Lors de la mise en production, prévoir la vérification du domaine et la soumission du sitemap dans Google Search Console avec un accès autorisé au compte propriétaire. Ne pas annoncer cette étape comme réalisée sans en avoir vérifié le résultat.
- Lors d’un changement de template ou de routage concerné, contrôler le HTML rendu (alternatives, métadonnées et directives d’indexation), ainsi que les réponses de `robots.txt` et `sitemap.xml` si elles sont modifiées.

## Vérifications pendant le développement et avant fusion

- Pendant le développement, lancer les contrôles locaux adaptés aux fichiers modifiés et aux risques identifiés, ainsi que `git diff --check` avant commit ou push. Les six contrôles complets ne sont pas exigés à chaque commit ou push sur une branche de travail.
- Avant fusion vers `dev` ou `alpha`, attendre la réussite de la CI complète sur la dernière version de la PR : validation Compose, lint, tests PHP et Python, compilation des assets, tests navigateur et vérification du diff ; la CI vérifie aussi la sauvegarde et la restauration isolées.
- Pour reproduire les six contrôles localement : `make validate`, `make lint`, `make test`, `make assets`, `BROWSER_PERSISTENCE=1 make test-browser` et `git diff --check`. Une CI complète réussie ne nécessite pas de relancer systématiquement toute la suite locale.
- La CI automatique se lance sur les PR vers `dev` et `alpha`, puis à chaque mise à jour de ces PR. Les push seuls sur les branches de travail et sur `dev` ne la déclenchent pas. Conserver la validation avant publication sur `alpha` (actuellement aussi branche par défaut) et le lancement manuel ; adapter les filtres si la branche par défaut change.
- Les règles GitHub de `dev` doivent imposer une PR et les cinq jobs `quality` réussis avant fusion. Vérifier leur activation ; le workflow seul ne protège pas la branche et le job `publish`, absent des PR, ne doit pas être requis.
- Attendre la fin de chaque vérification lancée et examiner son résultat. Ne pas répéter un contrôle déjà réussi sur un contenu inchangé sans problème justifiant une nouvelle exécution.
- Corriger les échecs sans supprimer ni affaiblir les tests, puis relancer les contrôles concernés. Une branche de travail peut être poussée pour diagnostic en signalant les échecs ou contrôles impossibles ; ne pas fusionner tant qu’un contrôle requis échoue, manque ou reste inexpliqué.
- Présenter les résultats avant de déclarer le travail terminé. Distinguer contrôles ciblés, suite complète locale, CI GitHub, intégration et déploiement ; ne pas présenter les contrôles locaux comme une validation de la CI GitHub.

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
- Le protocole adaptatif ne dispense **jamais** de la CI complète avant fusion, ni des validations de phase du prompt maître V4. Les contrôles locaux pendant le développement suivent la section de vérification ci-dessus.

## Continuité et protection du projet

- Vérifier `git status`, la branche et les modifications locales avant toute intervention ; ne pas écraser le travail existant. Utiliser une branche dédiée pour la mission, sauf consigne explicite contraire.
- Nomenclature des branches : utiliser `feature/<sujet>` pour une fonctionnalité, `fix/<sujet>` pour un correctif, `refactor/<sujet>` pour une refactorisation, `docs/<sujet>` pour la documentation et `chore/<sujet>` pour la maintenance. Le sujet doit être explicite, en minuscules, sans accents et séparé par des tirets. Ne pas utiliser de préfixe `codex/`. Chaque nouveau lot part de `dev` actualisée ; après validation, créer une PR vers `dev` et la fusionner avant de démarrer le lot suivant.
- Préserver les fonctionnalités et décisions existantes. Toute suppression ou modification incompatible doit être justifiée, documentée et, si elle touche le gameplay, soumise à décision explicite.
- Actualiser la documentation pertinente, notamment README, ROADMAP, décisions et suivi de reprise lorsque les changements le justifient ; ne pas réécrire des audits historiques comme s'ils décrivaient automatiquement l'état courant.
- Ne pas promettre d'exécution hors session, de contournement de quotas ou d'accès à des outils non vérifiés.
