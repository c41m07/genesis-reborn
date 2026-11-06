# Consignes du dépôt

- Répondre en français. Faire des changements ciblés et préserver tout travail déjà présent dans le dépôt.
- Ne pas changer les règles de jeu ou les valeurs d’équilibrage sans décision explicite ; le code décrit le comportement, les décisions produit sont consignées dans [docs/DECISIONS.md](docs/DECISIONS.md) et les règles actuelles dans [docs/REGLES.md](docs/REGLES.md).
- Lancer d’abord les commandes disponibles dans le [Makefile](Makefile). Vérifications validées : `make validate`, `make lint`, `npm run build`, `make test`, `make test-browser`.
- Le périmètre livré et les objectifs sont dans [README.md](README.md), [docs/README.md](docs/README.md) et [docs/ROADMAP.md](docs/ROADMAP.md). Les audits dans `docs/agent/` sont historiques, sauf mention contraire.
- Conserver les logos et les SVG d’identité. Les illustrations matricielles du projet sont absentes volontairement ; les prompts de recréation sont dans [docs/ARTWORK_PROMPTS.md](docs/ARTWORK_PROMPTS.md) et [docs/agent/ASSET_GENERATION_PROMPTS.md](docs/agent/ASSET_GENERATION_PROMPTS.md).

## Vérifications avant commit ou push

- Avant tout commit ou push, lancer `make validate`, `make lint`, `make test`, `npm run build`, `make test-browser` et `git diff --check`.
- Attendre la fin de chaque vérification et examiner son résultat. Si ces contrôles ont déjà réussi sur le contenu à publier pendant la tâche, ne pas les relancer sans changement ou problème justifiant une nouvelle exécution.
- Corriger les échecs sans supprimer ni affaiblir les tests, puis relancer les contrôles concernés.
- Ne pas effectuer de commit ni de push tant qu’un échec reste inexpliqué. Si une vérification est impossible, indiquer clairement laquelle et pourquoi.
- Présenter les résultats avant de déclarer le travail terminé. Distinguer une exécution complète réussie des réussites consolidées par reprises ciblées, et ne pas présenter les contrôles locaux comme une validation de la CI GitHub.

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
- Le protocole adaptatif ne dispense **jamais** des six contrôles avant commit/push, ni des validations de phase du prompt maître V4.

## Continuité et protection du projet

- Vérifier `git status`, la branche et les modifications locales avant toute intervention ; ne pas écraser le travail existant. Utiliser une branche dédiée pour la mission, sauf consigne explicite contraire.
- Préserver les fonctionnalités et décisions existantes. Toute suppression ou modification incompatible doit être justifiée, documentée et, si elle touche le gameplay, soumise à décision explicite.
- Actualiser la documentation pertinente, notamment README, ROADMAP, décisions et suivi de reprise lorsque les changements le justifient ; ne pas réécrire des audits historiques comme s'ils décrivaient automatiquement l'état courant.
- Ne pas promettre d'exécution hors session, de contournement de quotas ou d'accès à des outils non vérifiés.
