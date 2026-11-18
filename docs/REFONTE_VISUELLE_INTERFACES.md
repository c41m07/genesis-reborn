# Refonte visuelle des interfaces — suivi de mission

## État du 17 novembre 2026

Départ depuis `dev` à `e7acc17`, après récupération GitHub autorisée ; arbre
initial propre, `dev` identique à `origin/dev`. Branche de mission :
`feature/refonte-visuelle`. Le lot B est enregistré dans `3e54b81` ; la branche
a été renommée à la demande de l’utilisateur pour accueillir les commits des
prochains lots. Cette consigne remplace le découpage en branches séparées pour
cette mission ; la CI complète reste obligatoire avant toute fusion.

**Reprise de conception demandée par l’utilisateur : seule la page Figcraft
« Vue générale » est conservée et approuvée comme référence. Les anciennes
propositions ont été retirées par l’utilisateur.** Les deux nouvelles propositions
L1A ont été présentées ; l’utilisateur a choisi **B** (« j’aime bien la B »).
Les trois pages sont implémentées localement dans cette direction. Cette
validation ne concerne pas les lots suivants. Aucune migration sur une base
existante, dépendance, PR, fusion ou livraison n’est réalisée pour ce lot.

L’[inventaire de couverture](INVENTAIRE_INTERFACES.md) recense 170 routes du projet,
200 gabarits Twig et 53 contrôleurs Stimulus après L1A, avec les paramètres et états qui ne
se comptent pas comme des routes distinctes. Le relevé global reste utile à la
reprise ; il ne constitue pas une validation visuelle ou fonctionnelle de tous
les écrans. L’analyse détaillée porte sur le socle et le premier lot.

## Sources et périmètre

Sources examinées : `AGENTS.md`, architecture, décisions actuelles, règles de
diplomatie/présence/profils, guide d’interface et suivi des pages dédiées ;
`DiplomacyController`, `DiplomacyPageView`, navigation commune, accueil de guilde,
membres et cartes de relations. Le relevé rapproche les noms et propriétaires
des routes chargées par Symfony avec les attributs PHP, y compris le préfixe de
classe administratif et l’API de classement.

Les trois sources Work ont été retrouvées et lues intégralement dans les copies
locales non versionnées de `var/corrections-interface/docs/` :

- `GENESIS_REBORN_PROMPT_MAITRE_WORK_V4_COMPLET.md` ;
- `GENESIS_REBORN_BRIEF_FIGMA_WORK_V2_COMPLET.md` ;
- `GENESIS_REBORN_PROTOCOLE_DEVELOPPEMENT_ADAPTATIF_WORK_V1.md`.

La demande actuelle pilote la refonte par lots et la conservation des logos.
Elle ne rouvre pas l’audit général des quinze phases ; aucune phase globale n’est
annoncée validée. Le thème sombre est prioritaire, avec conservation du thème
clair existant. La référence publiée `https://chili-sunset-36220944.figma.site/`
renvoie « Not Found » dans le navigateur réel ; ses écrans n’ont pas été observés.
Le wiki documentaire externe n’a pas été synchronisé dans cette session.

Les [huit lots de navigation](NAVIGATION_PAGES_DEDIEES.md) sont déjà présents dans
le `dev` inspecté. Leurs URL, préparations ciblées, contrats et garanties sont
conservés. Les nouvelles compositions ne recommencent pas ces séparations.

## Figcraft de bureau et document courant

Le MCP Figcraft 0.18.1 est connecté à l’application de bureau sur la connexion
locale 7979. La fenêtre initialement lancée sans cette connexion a été conservée ;
l’instance connectée a ouvert le fichier de mission. Après le nettoyage réalisé
par l’utilisateur, cette instance a été inspectée et enregistrée avant la reprise.
Les nouvelles pages ont été construites via le MCP **attaché au bureau**.

Le catalogue de cette session n’expose pas de fonction Figcraft directement ;
le client MCP local préexistant `figcraft-cli.exe mcp --attach 7979` donne accès
aux commandes, nœuds, composants, variables, prototypes, inspections et exports.
Les outils ont effectivement été appelés. Aucune configuration globale n’est
changée. La préparation initiale sans fenêtre ne décrit plus le mode de travail
courant et ses anciennes propositions ne sont pas les options présentées ici.

Fichier natif local, hors du dépôt :

`C:/Users/xdark/.codex/visualizations/2026/11/17/01a1ea81-5284-7b10-9009-41e7f3c29e5e/genesis-reborn-lot-1-guildes-A-B.figcraft`

Après la préparation de L1C, le document comporte sept pages :

1. **01 · Vue générale — Validée** : conservée sans modification ;
2. **02 · Guildes · A — Horizon partagé** : première proposition nouvelle ;
3. **03 · Guildes · B — Échanges et gestion** : B validée et implémentée pour L1A ;
4. **04 · L1B · A — Recherche directe** : liste compacte et création accompagnée ;
5. **05 · L1B · B — Identités en cartes** : seconde proposition L1B ;
6. **06 · L1C · A — Gestion directe** : grades latéraux et formulaires visibles ;
7. **07 · L1C · B — Gestion progressive** : grades horizontaux, réglages secondaires dépliables.

Dans chaque proposition, les formats ordinateur, mobile et tablette sont rangés
côte à côte par tâche : Ma guilde, Membres, Relations entre commandants. Une
planche couvre les états et contraintes. Les composants restent dans ces pages ;
aucun index ou ensemble de références supplémentaires n’est réintroduit.

Une version de reprise conserve l’état utilisateur avec sa seule Vue générale.
Les structures grises ont précédé la mise en couleur ; une version Figcraft
conserve cette étape, sans multiplier les pages visibles. Une comparaison du
contenu complet de la page de référence avant/après confirme sa conservation.
Les liens du prototype simulent la navigation et n’exécutent aucune action du jeu.

## Découpage et couverture restante

| Lot | Périmètre | Complexité conception / réalisation | Dépendances et risques |
| --- | --- | --- | --- |
| L1A | Ma guilde, Membres, Relations entre commandants | 3/5 · 4/5 | Socle approuvé ; appartenance, présence privée, grades, consentements et priorité des relations. |
| L1B | Trouver/créer une guilde et présentation consultée | 3/5 · 4/5 | Conventions validées en L1A ; recherche, adhésion et absence de guilde. |
| L1C | Grades, gestion, personnalisation, relations entre guildes | 4/5 · 4/5 | Commandement, délégations, versions, images privées et traités. |
| L2 | Missions, préparation, orbites, quai et compositions | 4/5 · 5/5 | Plusieurs sous-lots ; continuité du parcours, périmètres colonie/empire et concurrence. |
| L3 | Boosts, boîtes et récompenses en attente | 3/5 · 4/5 | Capacité, stockage, vacances et consommation unique. |
| L4 | Identifiants, préférences, vacances, suppression | 3/5 · 5/5 | Compte commun, empire actif, authentification et confirmations. |
| L5 | Projets en cours/disponibles/historique et détail | 3/5 · 4/5 | Dons définitifs, paliers, minimums et récompenses acquises. |
| L6 | Événements/bonus, défis/rotations et détail | 3/5 · 4/5 | Visibilité, filtres, périodes et panneau commun. |
| L7A | Vue générale, colonies/comparaison et abandon | 4/5 · 4/5 | Conserver la référence approuvée ; examiner les autres états et confirmations. |
| L7B | Bâtiments, Recherche, Chantier, Défenses, Guide | 4/5 · 4/5 | Filtres, prérequis, files et préparation ciblée. |
| L7C | Cartes classique/V2 et simulateur | 4/5 · 4/5 | Découvertes, confidentialité, clavier et parcours préparation/résultats. |
| L7D | Rapports, archives et détails | 3/5 · 4/5 | Participation, confidentialité, pagination et ancres utiles. |
| L7E | Messages/recherche, classements et profils | 4/5 · 4/5 | Canaux, non-lus, saisie, destinataires et informations publiques. |
| L8 | Accueil, accès, récupération et univers joueur | 4/5 · 4/5 | Sous-lots ; validations, erreurs, redirections et SEO. |
| L9 | Feedback, patchnotes et documents complémentaires | 2–3/5 · 3/5 | Pièces jointes, informations publiées et formulaires. |
| L10 | Toutes les interfaces administratives recensées | 4/5 · 4/5 | Sous-lots par groupe ; conserver organisation, droits, recherches et formulaires dédiés. |
| L11 | Wiki : accueil, chapitre, recherche et références | 3/5 · 3/5 | Pages déjà séparées, anciens fragments et formules dépliables. |
| L12 | Cohérence, erreurs et composants communs restants | 4/5 · 4/5 | Revue transversale ; adaptations communes rattachées au lot consommateur. |

Le tableau comporte **18 lots prévus** : huit ensembles réalisés localement
(L1A/L1B/L1C, L2, L3, L4, L5 et L6), dix restants. Certains ensembles complexes seront
subdivisés pendant leur analyse ;
ce total ne fixe donc pas le nombre final de livraisons. Les complexités seront
réévaluées avant chaque sous-tâche. À la demande de l’utilisateur, les lots de
cette mission se poursuivent par commits sur la branche commune, sans fusion
intermédiaire imposée ; la CI complète reste requise avant fusion vers `dev`.
Les volumes de routes incluent actions et fragments et ne fixent pas la taille
des sous-lots.

## L1A — pages, données et garanties

| Page / URL | Source examinée | Organisation et comportement conservés |
| --- | --- | --- |
| Ma guilde — `/guildes` avec appartenance | `DiplomacyPageView`, `_guild_hub`, profil et aperçu de trois messages | Accueil de l’espace, bannière/logo/présentation, canal, projets, galaxie et classement. Aucun compteur de puissance, de projets ou de connectés ajouté. L’aperçu ne marque pas le canal lu. |
| Membres — `/guildes/membres` | `_guild_members`, présence et attributions autorisées | Liste et liens profils/messages, présence dans l’univers sans heure révélée. Attribution dépliable, choix autorisés et contrôle du grade attendu. |
| Relations — `/diplomatie/commandants` | `_relation_card`, contrats, recherche et curseurs | Relation actuelle séparée des propositions, accès avec/sans guilde et guildes désactivées. Priorité collective, paix, rivalité, recherche bornée et pagination conservées. |

La guilde garde Vue générale, Membres, Grades, Projets, Personnalisation
(commandant actuel) et Gestion selon les droits. Diplomatie garde ses deux
destinations et l’entrée adaptée à l’appartenance. Les destinations restent des
liens serveur, les actions des boutons de formulaire. Aucun nouveau découpage
métier n’est requis. Le sommaire automatique est déjà désactivé sur ces pages.

### A — Horizon partagé

Prolonge la Vue générale approuvée : même fond, menu, couleurs et typographie ;
bannière immersive, présentation/projets à gauche et aperçu des échanges à droite.
Les membres restent en tableau sur ordinateur et en lignes empilées sur mobile.
Les relations occupent des lignes larges avec statuts et actions distincts.

Sur mobile, Vue générale et Membres restent visibles ; les autres pages de guilde
sont dans un menu identifié. Les deux destinations diplomatiques restent visibles.
Bénéfice : lecture directe et largeur utile pour les listes. Limite : les échanges
suivent la présentation et les projets sur mobile ; le menu secondaire demande
un contrôle d’ouverture, de fermeture et de focus à l’implémentation.

### B — Échanges et gestion

Même identité et même navigation principale ; bannière plus compacte, canal et
trois échanges prioritaires, puis présentation/projets. La liste des membres
ajoute des repères d’identité et les relations occupent deux colonnes de cartes
sur ordinateur. Aucune navigation latérale supplémentaire n’est ajoutée.

Sur mobile, le menu « Pages de l’espace » indique la page courante. Bénéfice :
accès plus immédiat à la vie de la guilde et aux actions diplomatiques. Limite :
présentation/projets sont plus bas et les cartes demandent davantage de retours
à la ligne. Les mêmes fonctions et données sont couvertes que dans A.

La recommandation initiale était A pour la continuité et la lisibilité des listes.
**Choix utilisateur : B**, validé après présentation, sans combinaison nouvelle.
La version « Lot 1A - B validee par utilisateur » est enregistrée dans le fichier
Figcraft de bureau, ouvert sur B ; la page Vue générale reste inchangée.

## Livrables de la reprise

Dans le dossier local du fichier Figcraft indiqué plus haut :

- `reprise-A-apercus.png`, `reprise-B-apercus.png` : ordinateur et premier écran mobile ;
- `reprise-A-home-1440.png`, `reprise-A-members-1440.png`, `reprise-A-relations-1440.png`
  et variantes `390`/`768` ; mêmes noms avec `B` pour l’autre proposition ;
- `reprise-A-states-1000.png`, `reprise-B-states-1000.png` : états et contraintes ;
- `reprise-structure-A-1440.png` et variantes : étape en niveaux de gris ;
- `reprise-lot-1-manifest.json`, `reprise-verification.json`, `reprise-contrastes.json` :
  cadres, exports et vérifications de conception.

Les données sont fictives. Le visuel d’exemple vient de
`app/public/art/genesis-reborn-planet.png` ; les images personnalisées de guilde
restent la source réelle à l’implémentation. L’emblème Genesis Reborn est réutilisé
sans retouche. Segoe UI correspond à la référence Figcraft ; le raccord à Manrope
et au repli du jeu sera contrôlé sans installer une police. Les livrables graphiques
restent hors Git et devront être transférés pour une reprise sur un autre poste.

Les planches représentent ou annotent : recherche sans résultat, attributions et
versions, confirmation de rivalité, résultat d’action, menus, absence de guilde,
contenus longs, pagination et échec d’actualisation. Les cibles prévues font au
moins 44 px. Les états actifs et statuts ont des repères textuels. Les composants
emploient variables et mises en page automatiques. Les hauteurs des cadres ne
doivent pas devenir des hauteurs fixes imposées aux pages du jeu.

## Vérifications de conception

Contrôles de cette préparation :

- `make status` et lecture du routeur via le Makefile ; correspondance des 170
  routes du projet et recensement des gabarits/contrôleurs d’interaction.
- Enregistrement de l’état laissé par l’utilisateur puis création via le MCP
  de bureau ; structures avant mise en couleur, composants et liens simulés.
- Comparaison du contenu complet de la page Vue générale avant/après : inchangée.
  À l’issue de L1A, le document compte trois pages ; L1B en ajoute deux sans
  modifier Vue générale ni la page B validée.
- Contrôles des limites et chevauchements de texte des 18 vues détaillées et
  deux planches d’états : réussis après corrections ciblées. 185 liens de
  prototype définis ; aucune action réelle du jeu exécutée.
- Contrastes calculés sur les couleurs prévues : texte/panneau 14,80 ; secondaire
  8,84 ; cyan/fond 11,39 ; vert/panneau 10,31 ; rouge/panneau 9,08 ; contour des
  contrôles/panneau 3,21. Ces mesures ne constituent pas un audit RGAA du rendu.
- Examen d’exports représentatifs ordinateur/mobile/tablette. La capture de
  fenêtre native a expiré ; les aperçus fournis sont des exports de maquettes,
  pas des captures de l’application web implémentée.
- `make validate`, vérification des liens documentaires et structure des tableaux,
  puis `git diff --check`. Aucun test PHP/navigateur du jeu n’est présenté comme
  réexécuté pour ces seuls changements documentaires.

## Réalisation locale B

Complexité réévaluée avant code : **4/5**. Le risque porte sur les fragments
partagés, les nombreuses conditions d’action et la navigation mobile, plutôt que
sur un nouveau traitement serveur. Routes, services, lectures, droits, CSRF,
versions attendues, transactions et isolation des univers restent inchangés.

- `_guild_hub` : bannière et logo existants, canal en action principale, trois
  échanges avant présentation/projets. Les trois raccourcis sont conservés.
- `_guild_members` : même tableau, présence et formulaires d’attribution ; ajout
  d’une initiale décorative et adaptation des lignes sur petit écran.
- `_relation_card` : relation actuelle et proposition séparées, deux colonnes
  sur ordinateur, une sous 700 px. Confirmation de rivalité, paix, priorité de
  guilde et formulaire d’origine conservés ; lien de message en bas de carte.
- Navigation : destinations serveur et `aria-current` dans un fragment commun ;
  `details` ouvert sans JavaScript, adapté sous 600 px par un contrôleur Stimulus.
  Échap ferme le menu ; les changements de format gardent le focus visible.
  Sans guilde, l’unique destination diplomatique n’est pas repliée.
- Styles limités à `.guild-workspace`, importés après les styles existants.
  Barre de ressources, menu principal, logos, SVG et polices conservés.

Adaptations mineures à la maquette : données et visuels réels du profil de
guilde, Manrope existante et hauteurs fluides ; aucune image de démonstration
imposée aux guildes. Les menus des pages hors L1A gardent leur présentation.
Le thème clair utilise ses variables actuelles, sans seconde direction visuelle.
Performance : non mesurée ; aucun gain chiffré annoncé.

### Vérifications locales

- `make validate`, `make lint-static` (200 Twig, 80 YAML, PHP et conteneur) et
  `make assets` : réussis.
- PHP ciblé, via le PHPUnit existant dans une pile jetable propre au lot :
  **68 tests, 456 assertions réussis** (`GuildTagPlayerReportTest`, `GuildRankTest`,
  `PersonalDiplomacyTest`, `DiplomacyAccessPolicyTest`).
- Première exécution navigateur : cinq scénarios réussis (ancres de diplomatie,
  grades, affinage des pages et deux profils) ; débordement des cartes et bannière
  de fixture hors dimensions détectés. Contournement de style historique corrigé,
  fixture remplacée par une bannière synthétique autorisée ; aucune règle d’image
  ni assertion de débordement affaiblie.
- Nouveau scénario `guild-workspace.spec.mjs` : comptes et guilde synthétiques,
  vraie personnalisation avec bannière, membre au nom long, quatre messages et
  proposition reçue. Les trois pages passent en sombre/clair à 1440/768/390/320 px,
  sans débordement ; aperçu limité à trois sans lecture implicite, disposition des
  cartes et présence vérifiés. Axe ne relève aucune violation dans le périmètre
  `.guild-workspace` à 390 px. Clavier, Échap, focus, accès direct, rechargement,
  retour arrière, recherche vide et navigation sans JavaScript sont couverts.
- Reprise navigateur : **4 scénarios réussis** (nouveau parcours B, deux recettes
  finales sans/avec guilde et rivalités personnelles). Après le dernier ajustement
  du focus dans les deux sens de redimensionnement, le seul parcours B est relancé :
  **1 scénario réussi**, captures finales produites. Les autres cas inchangés ne
  sont pas relancés une troisième fois.
- `git diff --check` : réussi ; relecture du périmètre et des commentaires effectuée.

Les migrations/imports des essais ne concernent que leurs bases synthétiques en
mémoire. La base de test partagée non saine n’a pas été utilisée. Une première
tentative PHP et une reprise navigateur ont rencontré l’épuisement des réseaux
Docker ; le réseau du seul projet PHP jetable créé ici a été libéré avec son
profil de test. Aucun réseau d’un autre projet ni configuration globale changé.

Les captures réelles `jeu-B-{guilde,membres,relations}-{dark,light}-{1440,768,390}.png`
sont produites par le scénario dans `app/test-results/` et conservées hors Git
dans `output/playwright/guild-workspace/` et le dossier Figcraft local. Elles
représentent le jeu avec des données synthétiques, distinctement des maquettes.
Les variantes `-390-ecran.png` montrent la fenêtre mobile sans capture déroulée.
18 captures de page et six captures de fenêtre sont conservées. Aucun lecteur
d’écran réel n’a été essayé.
Ces contrôles ciblés ne constituent ni un audit RGAA ni la CI complète.

Fichiers de réalisation : `app/assets/app.js`,
`app/assets/styles/guild-workspace.css`,
`app/assets/controllers/space_navigation_controller.js`,
`app/templates/ui/{space_navigation,guild_navigation}.html.twig`,
`app/templates/player/{diplomacy,_guild_hub,_guild_members,_relation_card}.html.twig`.
Scénario ajouté : `guild-workspace.spec.mjs` ; adaptations de navigation et de
disposition dans `guild-ranks.spec.mjs`, `navigation-final.spec.mjs` et
`player-pages-refinement.spec.mjs`. README, architecture, décisions, guide UI,
feuille de route, inventaire et présent suivi sont synchronisés dans le même lot.

## L1B — trouver, créer et consulter une guilde

Conception **3/5**, réalisation **4/5**. Analyse ciblée des routes
`/guildes` sans appartenance, `/guildes/creer`, `/guildes/{id}`, du média de
guilde, des fragments join/search/create/card, de `DiplomacyPageView`,
`DiplomacyController`, `SocialProfileView`, `DiplomacyService` et des scénarios
existants d’état vide et de profils. Les traitements de grades et de traités
restent hors de ce sous-lot ; leur fragment partagé devra être préservé.

Les trois tâches gardent des pages dédiées : recherche et adhésion à l’entrée,
formulaire autonome pour fonder, présentation consultable par URL. La liste des
membres reste dépliable dans son résultat de recherche, sans présence privée.
La recherche littérale porte sur le nom, exige deux caractères et renvoie au plus
20 guildes de l’univers, sans pagination actuelle. Aucune liste populaire,
statistique de puissance, filtre ou candidature n’est inventé. Le recrutement
reste ouvert, l’adhésion immédiate et les contrôles serveur restent applicables.

La présentation conserve nom/tag, description, images, nombre de membres et
lien du commandant. Elle n’ajoute pas d’accès à son équipage privé ni de bouton
d’adhésion supplémentaire ; rejoindre reste une action du résultat de recherche.
Personnaliser est réservé au commandant actuel de sa propre guilde. Les liens
Classement/Guildes, le contexte d’univers, les médias authentifiés, les redirections
et l’accès permanent aux relations personnelles restent conservés.

### Deux propositions dans la continuité de B

| Option | Composition et navigation | Bénéfice | Limite / réalisation |
| --- | --- | --- | --- |
| A · Recherche directe | Résultats en lignes larges ; aide courte à côté du formulaire sur ordinateur ; présentation immersive puis description et commandant en sections successives. Deux liens d’espace visibles sur mobile, troisième lien pour la présentation. | Comparaison et actions directes, densité plus compacte. | Les actions occupent la largeur de la ligne ; présentation plus longue verticalement. |
| B · Identités en cartes | Résultats sur deux colonnes ; formulaire centré ; présentation compacte avec description et commandant côte à côte. Deux liens visibles sur mobile ; menu Pages de l’espace sur la présentation. | Continuité des cartes diplomatiques de L1A B, groupes d’actions mieux isolés. | Les cartes demandent plus de hauteur et doivent gérer les noms longs ; la troisième destination mobile est dans le menu. |

La recommandation initiale était B pour la continuité des cartes.
**Choix utilisateur : A** (« j’aime bien le A »), pour les trois pages du lot.
La version de validation est enregistrée dans le document Figcraft de bureau.
Les deux options restent consultables ; les compositions convergent en une
colonne sur mobile.

La conservation du nom/tag après erreur est réalisée par un brouillon de session
propre à la participation et à l’univers, échappé au rendu. Les valeurs stockées
sont bornées à 120/10 caractères, au-delà des maxima valides 60/5 : une saisie
trop longue ne devient pas valide après troncature. Le brouillon survit aux GET
et rechargements, puis disparaît après création réussie. CSRF, messages de refus,
services, permissions, transactions et validation métier restent inchangés.

### Maquettes et contrôles de conception

Les deux pages L1B sont ajoutées dans le même fichier natif de bureau. Par option :
trois pages à 1440/768/390 px et une planche d’états, soit **20 cadres** au total.
Structures grises enregistrées avant couleurs ; composants avec propriétés,
variables propres à L1B et 209 liens de prototype. Les liens simulent les actions.

Les planches couvrent recherche initiale/courte/sans résultat, membres dépliés,
erreur de création, adhésion réussie ou refusée, guilde désactivée, images et
description absentes, rôles et navigation avec/sans guilde. Les noms longs sont
représentés dans les résultats. Les textes, actions et contrastes ont été relus
sur des exports ; limites et chevauchements de texte contrôlés sur les 20 cadres.
La comparaison complète des nœuds confirme Vue générale et L1A B inchangées.
Les 18 champs dessinés et le contenu de leurs panneaux restent dans leurs
limites. Contrastes calculés : texte 14,80 ; secondaire 8,84 ; liens 10,28 ;
recrutement 10,31 ; texte de l’action principale 10,39 ; contours 3,21.
Ces mesures de couleurs et contrôles de maquette ne constituent pas un audit RGAA.

Exports hors dépôt dans le dossier du fichier natif :
`lot-1B-{A,B}-{find,create,profile}-{1440,768,390}.png`,
`lot-1B-{A,B}-states-1050.png`, `lot-1B-{A,B}-apercus.png` (ordinateur/mobile),
structures grises, manifeste et rapports `lot-1B-verification.json`,
`lot-1B-containment.json` et `lot-1B-contrastes.json`. Visuel d’exemple issu des
assets existants ; aucun logo/SVG retouché. Les contrôles d’outil portent sur la
conception ; les vérifications du jeu sont distinguées ci-dessous.

### Réalisation A et contrôles locaux

`guild-discovery.css` est limité aux recherches/créations sans guilde et à la
présentation consultée. Résultats et actions occupent des lignes ; l’aide et le
formulaire sont côte à côte sur ordinateur, puis les champs précèdent l’aide sur
petit écran. La présentation garde sa bannière, puis description et commandant.
Les liens secondaires restent visibles. Aucun nouveau contrôleur JavaScript,
route, migration, dépendance ou changement des traitements métier.

Adaptations mineures : conservation du nom de guilde dans le titre principal
du profil, du titre « Rejoignez une guilde » et des libellés d’adhésion existants ;
les images sont celles de la guilde, avec le repli existant. Les autres pages de
traités gardent leur composition et leurs actions.

Contrôles locaux :

- `make validate`, `make lint-static` (200 Twig, 80 YAML, PHP/conteneur) et
  `make assets` réussis.
- PHP ciblé isolé : 69 des 70 cas réussis au premier passage ; la nouvelle fixture
  de changement d’univers réutilisait une entité détachée après refus. Les trois
  nouveaux tests de brouillon sont relancés après relecture des fixtures :
  **3 tests / 31 assertions réussis**. Le jeu n’est pas modifié pour contourner
  cet échec de préparation. Couverture : erreur/rechargement, joueur et univers
  distincts, échappement, succès, CSRF et stockage borné restant invalide.
- Nouveau parcours navigateur A réussi en sombre/clair à 1440/768/390/320 px :
  deux guildes synthétiques, bannière stockée réellement, saisie conservée,
  résultats longs, actions en lignes/empilées, profils avec/sans droits, recherche
  initiale/courte/vide, accès direct/rechargement/retour arrière, clavier et
  adhésion sans JavaScript. Axe ciblé sur `.guild-discovery` à 390 px : aucune
  violation relevée.
- **9 scénarios navigateur réussis** : nouveau parcours, état vide, trois parcours
  de tag/signalement, traités, B de L1A et deux parcours de profils. La revue des
  captures a entraîné des ajustements limités d’alignement, de couleurs des actions
  et de parcours clavier. Le formulaire précède l’aide dans le DOM ; la grille
  le place à droite sur ordinateur et en premier sur mobile.
- Après ces ajustements, reprise des trois parcours concernés (A et deux profils) :
  **3 scénarios réussis**. Contrôles supplémentaires d’alignement du champ/bouton
  de recherche et de premier champ au clavier sans JavaScript. Nouveau
  `make lint-static`, compilation finale et `git diff --check` réussis.

24 captures réelles finales sont conservées dans
`output/playwright/guild-discovery/captures-jeu-L1B-A/` et le dossier Figcraft local
`captures-jeu-L1B-A/` : trois pages en sombre/clair à 1440/768/390 px, plus six
fenêtres mobiles. Préfixe : `jeu-L1B-A-{recherche,creation,presentation}`.
Elles montrent le jeu et des données synthétiques, distinctement des maquettes.
Le cache de l’application locale est actualisé après réalisation ; aucune
écriture sur sa partie habituelle n’est nécessaire à la recette.

Les essais écrivent uniquement dans leurs bases synthétiques et stockages
jetables ; la base de test partagée non saine reste inutilisée. Aucun email
externe ni action sur la partie habituelle. Performance non mesurée ; aucun
lecteur d’écran réel essayé. Ces contrôles ne constituent ni un audit RGAA ni la
CI GitHub complète, qui reste requise avant fusion de la branche commune.

Fichiers de réalisation : `app/src/Controller/DiplomacyController.php`,
`app/templates/player/{diplomacy,_guild_join,_guild_search,_guild_create,_guild_diplomacy_card,public_guild}.html.twig`,
`app/assets/app.js`, `app/assets/styles/guild-discovery.css`. Régressions dans
`GuildTagPlayerReportTest.php` et nouveau scénario `guild-discovery.spec.mjs`.
README, architecture, décisions, guide UI, feuille de route, inventaire et suivi
sont synchronisés dans le même lot.

## L1C — grades, gestion, personnalisation et traités

Conception **4/5**, réalisation **4/5**. Quatre pages existantes :
`/guildes/grades`, `/guildes/gestion`, `/guildes/{id}/personnaliser` et
`/diplomatie`. Sources ciblées lues : fragments ranks/rank_form/settings/management,
personnalisation, cartes/formulaires de traité, `DiplomacyPageView`,
`DiplomacyController`, `PublicProfileController`, `GuildRankService`,
`DiplomacyAccessPolicy`, `DiplomacyService` et suivi de navigation livré.

Les quatre tâches gardent leurs pages dédiées. La sélection d’un grade reste
une URL avec `rank`, pas un panneau masqué par JavaScript. Les réglages secondaires
et confirmations peuvent être dépliables ; nom/priorité/permissions d’un même
grade restent dans un formulaire. Les deux compositions réutilisent le menu
mobile de l’espace déjà retenu en L1A pour ces destinations nombreuses.

### Deux compositions, mêmes fonctions

| Option | Grades et gestion | Personnalisation et traités | Limite |
| --- | --- | --- | --- |
| A · Gestion directe | Grades à gauche, édition à droite ; tag/transfert visibles et dissolution distinguée. | Description puis fichiers ; relations en lignes complètes. | Plus de hauteur pour sept permissions et plusieurs relations ; réglages visibles plus denses. |
| B · Gestion progressive | Grades au-dessus, permissions sur deux colonnes ; commande secondaire dépliable. | Formulaire avec visuel actuel à côté ; proposition reçue prioritaire, autres relations en cartes. | Les réglages secondaires demandent un clic ; cartes à adapter aux descriptions et noms longs. |

La recommandation était A pour la lecture linéaire des permissions et
des conséquences. **Choix utilisateur : A** (« j’aime bien le A »), pour les
quatre pages L1C. La version de validation est enregistrée dans Figcraft.
Les deux compositions restent consultables ; les lots suivants attendent leur
propre validation.

### Rôles et garanties à préserver

- Seul le commandant actuel crée/modifie les grades et personnalise la guilde.
  Sept permissions déléguées existantes, priorité 1–1 000 et nom 1–60 caractères.
  Aucun classement par glisser-déposer ni suppression de grade inventés.
  Attribution dans Membres, avec les limites de rang et de délégation existantes.
- Tag unique de 2–5 lettres/chiffres. Transfert à un autre membre, désactivé s’il
  n’y en a aucun. Le membre quitte ; le commandant transfère ou dissout. La
  dissolution abandonne les projets sans remboursement ; les contrats personnels
  restent conservés et la récompense exige la présence à la clôture du projet.
- **Adaptation UX validée et réalisée** : confirmation dépliable avant
  transfert, départ et dissolution. Une étape de revue précède désormais les
  POST existants, sans modifier les conséquences ni les contrôles serveur.
- Description simple de 2 000 caractères ; fichiers JPEG/PNG/WebP, logo 1 Mo et
  512×512, bannière 2 Mo et 1920×640. Images conservées sans nouveau fichier,
  texte conservé après refus et fichiers à resélectionner. B montre le visuel
  actuel, sans inventer un aperçu interactif du futur fichier.
- Traités selon permissions : proposition et deux signatures, état effectif
  maintenu avant acceptation. Rupture/hostilité immédiates après confirmation,
  paix bilatérale ; refus de version périmée et historique de dix actions maximum.
  Les particularités non-agression/défense/espionnage et combats à plusieurs camps
  sont rappelées dans la planche. Aucun droit de transport ou d’offensive ajouté.
- Univers, appartenance, CSRF, versions attendues, verrous et transactions
  restent vérifiés côté serveur. Les lectures par destination et les accès aux
  relations personnelles sans guilde restent conservés.

### Livrables et contrôles de conception

Deux nouvelles pages dans le fichier de bureau courant ; quatre vues par option
à 1440/768/390 px et deux planches rôles/erreurs, soit **26 cadres**. Structures
grises enregistrées avant couleurs ; composants, propriétés et variables propres
au lot. Les liens simulent les pages, états et confirmations ; aucun POST du jeu.
La comparaison complète confirme Vue générale, L1A B et L1B A inchangées.
Contrôles des 26 cadres : aucun texte hors cadre, coupé par son parent ou
superposé ; 298 liens de prototype avec destinations existantes. L’entrée
Diplomatie avec une guilde mène aux traités, les relations personnelles restent
accessibles. Contrastes fondés sur les mêmes couleurs vérifiées : texte 14,80,
secondaire 8,84, action principale 10,39 et contours 3,21. Ces vérifications de
conception ne constituent pas une recette du jeu ni un audit RGAA.

Exports : `lot-1C-{A,B}-{ranks,management,customize,treaties}-{1440,768,390}.png`,
`lot-1C-{A,B}-states-1100.png`, `lot-1C-{A,B}-apercus.png` (grades ordinateur/mobile),
structures, manifeste et rapports `lot-1C-verification.json` / `lot-1C-containment.json`.
Les maquettes restent hors dépôt dans le dossier natif indiqué plus haut. Les
logos et SVG sont conservés ; seul l’asset d’exemple existant illustre B.

### Réalisation A et vérifications locales

Templates et styles réorganisés dans les quatre pages approuvées. Les services,
contrôleurs, lectures, validations, transactions et droits restent inchangés.
Le menu mobile existant est réutilisé ; ses styles sont extraits vers
`space-navigation-menu.css` pour L1A/L1C, sans ajouter de contrôleur. Les formulaires
conservent leurs champs, CSRF et versions. La confirmation de transfert garde
le choix du successeur visible ; départ et dissolution placent le formulaire
complet dans leur dépliable. Les membres n’ont que leur départ et la lecture des
grades, selon les permissions actuelles.

Contrôles locaux :

- `make validate`, `make lint-static` (200 Twig, 80 YAML, PHP et conteneur) et
  `make assets` réussis.
- PHPUnit existant dans une pile jetable propre au lot : **83 tests / 471 assertions
  réussis** (`GuildTagPlayerReportTest`, `GuildRankTest`, `PublicProfilesTest`,
  `DiplomacyAccessPolicyTest`, `GuildTreatyTest`). Les refus d’accès et versions
  périmées restent couverts par leurs scénarios existants.
- Nouveau parcours navigateur A réussi : grade long et sept permissions,
  consultation d’un délégué, fichier invalide avec texte conservé, proposition
  de défense reçue pendant une alliance, champs et actions autorisés selon rôle.
  Les quatre pages passent en sombre/clair à 1440/768/390/320 px ; Axe ciblé à
  390 px ne relève aucune violation dans `.guild-control`.
- Transfert, départ et dissolution sont réalisés dans la pile de test sans
  JavaScript, après leur revue. Entrée dans la liste du successeur ne transfère
  pas avant confirmation ; ancien commandant privé de personnalisation après
  transfert ; dissolution limitée à sa propre guilde. État sans successeur,
  accès direct, rechargement, retour arrière et clavier vérifiés.
- Recette des consommateurs partagés : 14 des 15 scénarios réussis au premier
  passage. Le parcours B détecte une perte de focus lors du passage mobile →
  ordinateur. Le CSS peut masquer le résumé avant l’événement de changement de
  format ; le contrôleur enregistre cette perte de focus pour le rendre à la page
  active. Les assertions sont conservées ; un cas vérifie aussi que le focus d’un
  champ extérieur au menu reste inchangé au redimensionnement.
- La revue des captures conduit à harmoniser description et boutons natifs avec
  les couleurs de A, à distinguer les actions principales de traité et à éviter
  les titres visuels répétés. Leurs identifiants et titres de section restent
  disponibles pour la structure accessible et les anciens liens.
- Reprise finale des six parcours concernés : **6 scénarios réussis** (L1C,
  traités, B de L1A, discussion/gestion et deux profils). Aucun test n’est affaibli.
  Nouveau lint statique et compilation finale réussis ; diff relu et contrôlé.

32 captures réelles finales sont conservées dans
`output/playwright/guild-control/captures-jeu-L1C-A/` et le dossier Figcraft local
`captures-jeu-L1C-A/` : quatre pages en sombre/clair à 1440/768/390 px, plus huit
fenêtres mobiles. Préfixe : `jeu-L1C-A-{grades,gestion,personnalisation,traites}`.
Les images montrent des données synthétiques du jeu, distinctement des maquettes.

Fichiers principaux : `app/templates/player/{diplomacy,_guild_ranks,_guild_rank_form,
_guild_settings,_guild_management,guild_profile_edit,_guild_diplomacy_card}.html.twig`,
`app/assets/app.js`, styles `guild-control.css`, `space-navigation-menu.css` et
extraction ciblée dans `guild-workspace.css`. Scénario ajouté :
`guild-control.spec.mjs` ; correction du focus dans
`space_navigation_controller.js` et action correspondante dans
`ui/space_navigation.html.twig`. Les scénarios historiques de tag, transfert/départ/
dissolution, accès direct et classement/contact suivent désormais la confirmation
validée. Documentation synchronisée dans le même lot.

Les essais écrivent uniquement dans leurs bases et stockages synthétiques jetables,
sans utiliser la base de test partagée non saine. Aucun email externe ni action
sur la partie habituelle ; aucun déploiement. Performance non mesurée, lecteur
d’écran réel non essayé. Les vérifications ciblées ne remplacent pas la CI
complète requise avant fusion.

## L2A — missions, préparation complète et rappel

Les deux propositions sont prêtes dans le même document Figcraft de bureau :
**08 · L2A · A — Missions en lignes** et **09 · L2A · B — Poste d’opérations**.
L’utilisateur a choisi **A** (« j’aime bien le A »). La validation est enregistrée
dans la version Figcraft `L2A - A validee par utilisateur` ; elle porte uniquement
sur Missions, les trois étapes de préparation et le rappel.
L1C A est enregistré dans `1a33d14` sur la branche commune.

### Analyse ciblée et découpage

Conception : **4/5** ; réalisation : **5/5**. Sources examinées :
`FleetController`, `FleetPageView`, `FleetRecallController`, templates de Flottes,
navigation, destinations, vols personnels et arrivées, contrôleurs `fleet-wizard`
et `fleet-preview`, scénarios existants de destinations, devis, états vides et
rappel, architecture et suivis de navigation/arrivées.

| Vue | Adresse / organisation conservée | Contraintes |
| --- | --- | --- |
| Missions actives | `/flottes`, entrée de l’espace | Missions de la colonie active, pages de 30, liens de vol ciblé, états de combat et rappel autorisé |
| Composition | Première étape de `/flottes/preparer` | Unités disponibles de la colonie, quantités/Max, composition chargée exacte et avertissement civil |
| Destination | Deuxième étape du même formulaire | Mission, vitesse, catégories compatibles ou coordonnées, recherche, orbite conditionnelle et fret |
| Confirmation | Troisième étape du même formulaire | Composition/fret, devis serveur, réserve de carburant, erreurs et blocage pendant une estimation |
| Rappel | `/flottes/{id}/rappel`, page dédiée existante | Revue avant confirmation, version/idempotence, origine perdue et colonie de repli |

La préparation garde ses étapes dans un seul formulaire : elles servent un même
envoi et les retours ne doivent pas perdre la saisie. Missions et rappel restent
des pages autonomes. La navigation secondaire conserve les cinq destinations
existantes ; orbites, quai et compositions seront traités dans la suite de L2.
Le menu principal garde une entrée Flottes ; aucun nouveau sommaire ni filtre
de missions n’est proposé. Les ancres de flotte restent utiles.

### A — Missions en lignes

Les vols personnels occupent des lignes larges et compactes sur ordinateur et
tablette, puis une colonne sur mobile. Type/statut, trajet/échéance et rappel
restent séparés. Les arrivées étrangères suivent les vols personnels. La
préparation affiche les étapes horizontalement ; les trois missions résumées
et les arrivées restent à droite sur grand écran, après le formulaire aux
formats plus étroits. La confirmation présente résumé et estimation côte à
côte lorsqu’ils disposent d’assez de largeur. Le rappel utilise deux colonnes de
faits sur ordinateur. Un menu compact rassemble les cinq pages sur mobile.

Bénéfice : comparaison des trajets et largeur utile pour les libellés longs ;
continuité avec la préparation déjà présente. Limite : la liste doit être parcourue
avant d’atteindre les arrivées sur mobile ; le menu compact demande une ouverture.
L’adaptation devra rester limitée à l’espace Flottes pour préserver la Vue générale
et les autres consommateurs des composants de vols. **Recommandation : A** pour
la lecture de plusieurs missions et la proximité avec l’organisation existante.

### B — Poste d’opérations

Les missions deviennent des cartes sur deux colonnes et les arrivées occupent
une colonne distincte sur grand écran. La préparation met les étapes dans une
colonne latérale ; résumé des missions et arrivées suivent le formulaire. La
confirmation et le rappel privilégient une lecture verticale. Sur mobile,
Missions et Préparer restent visibles ; les autres pages sont dans un menu.

Bénéfice : séparation visuelle du suivi personnel et des arrivées sur ordinateur,
parcours de préparation concentré. Limite : davantage de défilement dans la
confirmation ; deux liens visibles et un menu prennent plus de place sur mobile.
L’implémentation devra préserver un seul panneau d’arrivées et son actualisation
autorisée lorsqu’il passe dans une colonne latérale.

Les deux options couvrent les mêmes données, actions, conditions et destinations.
Les chiffres du devis sont des exemples fictifs de présentation, pas des mesures
ni des valeurs d’équilibrage proposées. Les champs Figcraft sont illustratifs ;
les interactions naviguent dans les maquettes et n’exécutent aucune mission.

### États et vérification de conception

**32 cadres** : les quatre vues missions/composition/destination/confirmation
en 1440/768/390 px pour chaque option ; rappel en 1440/390 px ; deux planches
par option consacrées aux contraintes et aux états visibles. Les exemples incluent
un nom de colonie long, un retour, un combat, une attaque détectée à origine
inconnue, aucun vaisseau/aucune sonde, listes vides, erreur de composition,
estimation en cours/refusée, origine perdue et menu ouvert.

Les structures grises sont enregistrées avant couleur. Composants et variables
propres à L2A réutilisent les conventions approuvées sans modifier les sept pages
précédentes : leurs empreintes sont identiques, dont Vue générale, L1A B, L1B A
et L1C A. Logos/SVG conservés ; aucune illustration ajoutée. Les couleurs sont
celles déjà vérifiées : texte 14,80, secondaire 8,84, action principale 10,39,
contours de champs 3,21. Les actions portent du texte ; états et erreurs ne reposent
pas seulement sur la couleur.

Le contrôle des 32 cadres ne relève aucun texte hors cadre, superposé ou coupé
par son parent ; **400 liens** ont une destination existante. La revue visuelle
a conduit à compacter les lignes de A, identifier Flottes dans le menu principal,
reprendre les libellés du catalogue et ajuster les cadres à leur contenu.
Ces contrôles portent sur la conception ; ils ne valident pas encore le jeu,
le clavier réel ni la conformité RGAA.

Exports dans le dossier local du document natif :
`lot-2A-{A,B}-{missions,composition,destination,confirmation}-{1440,768,390}.png`,
`lot-2A-{A,B}-recall-{1440,390}.png`, planches `states` / `visible-states`, aperçus
ordinateur/mobile `lot-2A-{A,B}-apercus.png` et `-preparation-apercus.png`.
Manifeste : `lot-2A-manifest.json` ; contrôle : `lot-2A-verification.json`.

Après choix explicite : styles et templates ciblés, puis contrôles Make adaptés,
parcours de préparation/rappel dans une base synthétique isolée, devis en erreur
ou modifié, préremplissage carte/composition, actualisation sans perte de saisie,
clavier, accès directs/rechargement/retour, mobile 320/390/768 et ordinateur,
thèmes sombre et clair, vérification des consommateurs partagés. La préparation
charge toujours trois missions résumées, la liste au plus 30 détails. Les services,
permissions, univers, colonie, CSRF, versions et clés d’envoi restent conservés.
Aucun gain de performance n’est annoncé ; aucune nouvelle règle n’est proposée.

### Réalisation de A

`fleet-workspace.css` est limité à `.fleet-redesign`, présent sur Missions,
Préparer et Rappel. Les vols personnels utilisent des lignes larges, avec numéro
de flotte et bouton Composition et fret séparé du titre. Le fragment de mise à
jour reçoit aussi `fleet_page` : il conserve cette présentation après actualisation.
La Vue générale et les catalogues gardent leurs déclencheurs et leur disposition.
Les demandes d’orbite, le quai et les compositions conservent leur présentation
en attendant leurs validations.

Le menu réutilise `ui/space_navigation` et le contrôleur existant ; les cinq
destinations, leurs URL et leur page active sont conservées. Le rappel place le
titre avant la navigation, puis présente ses six informations sur deux colonnes
lorsque la largeur le permet. Aucun champ, action, CSRF ou version n’est changé.

La préparation conserve ses contrôleurs, champs, préremplissages et clé d’envoi.
Résumé et estimation sont regroupés avant les explications et les actions, sur
deux colonnes si la largeur suffit. Le fret est déplié pour les missions qui le
permettent ; le sélecteur natif conserve le libellé complet de destination dans
ses options. Le titre reçoit le focus après Suivant/Retour, sans prise de focus
à la connexion du contrôleur. L’envoi reste bloqué par le contrôleur de devis
pendant le calcul et après erreur ; sans JavaScript, les trois étapes et les
formulaires restent accessibles.

Les contrôles ont identifié des contrastes insuffisants en thème clair,
dans le menu compact, les arrivées et le badge Retour. Les corrections restent limitées au nouvel
espace. Les libellés des unités et les valeurs du devis ont été agrandis après
revue des captures. Le contrôle Axe du nouveau parcours a aussi relevé le nom
accessible de Rappeler, qui ne contenait pas ce texte visible : la nouvelle vue
affiche désormais Préparer le rappel, cohérent avec son libellé accessible et
sa destination de confirmation. Les autres consommateurs ne sont pas modifiés.

Fichiers principaux : `app/templates/fleet/{index,_available,recall}.html.twig`,
`app/templates/ui/{fleet_navigation,flights}.html.twig`,
`app/assets/styles/fleet-workspace.css`, import dans `app/assets/app.js`,
`app/assets/controllers/fleet_wizard_controller.js`. Nouveau scénario :
`app/tests/browser/fleet-workspace.spec.mjs` ; scénario de navigation existant
adapté à l’ouverture du menu mobile, avec ses assertions conservées.

Contrôles serveur : `make validate`, `make lint-static` (200 Twig, 80 YAML, PHP
et conteneur), compilation des assets, syntaxe JavaScript et `git diff --check`
réussis. Les champs, méthodes, destinations de formulaire et CSRF de préparation
et de rappel ont été comparés avant/après : identiques. PHPUnit isolé :
**59 tests / 713 assertions réussis**, suites `FleetDestinationTest`,
`FleetNavigationTest`, `FleetRecallTest`, `GalaxyFleetVisibilityTest`.

Premier passage navigateur : 12 succès sur 15 ; deux échecs de contraste et une
fixture de rappel insuffisamment fiable. Cette fixture utilise désormais un
véritable envoi pour préparer la navigation. Reprise des sept parcours concernés :
six succès, dont navigation complète, devis, trois rappels et surveillance ; le
nouveau parcours relève ensuite le libellé accessible corrigé ci-dessus.
La recette finale de A passe après correction du libellé et du badge Retour :
les cinq vues, en sombre et clair, à 320/390/768/1440 px ; Axe sans violation à
390 px dans `.fleet-redesign`, absence de débordement, focus des étapes, erreur
sans vaisseau, retours avec composition/fret conservés, devis autorisé, détails
privés au clavier, menu/Échap, rechargement et retour arrière. Le scénario passe
d’abord en clair pour contrôler les états concernés avant de parcourir le sombre.
**15 scénarios distincts réussis par consolidation des reprises**, sans retirer
ni affaiblir les assertions. Lint statique et compilation finaux réussis.

**40 captures réelles finales** : missions, composition, destination, confirmation
et rappel, en sombre/clair à 1440/768/390 px, plus dix fenêtres mobiles. Conservées
dans `output/playwright/fleet-workspace/captures-jeu-L2A-A/` et le dossier Figcraft
local `captures-jeu-L2A-A/`, avec index JSON ; préfixe `jeu-L2A-A-`. Les noms,
stocks et flottes sont synthétiques. Le nouveau parcours repousse seulement
l’échéance de sa flotte de capture pour garder les vues stables ; les scénarios
existants testent le rappel réel, l’origine perdue et le refus d’un trajet ancien
non fiable sans changer les règles.

Les essais utilisent uniquement leurs bases et stockages synthétiques jetables.
Pas de migration sur la partie habituelle, aucune dépendance installée, aucune
action de jeu sur les données usuelles et aucun email externe. Aucun gain de
performance mesuré ou annoncé ; aucun audit RGAA complet. La CI GitHub, la fusion
et le déploiement restent distincts.

## L2B — orbites, quai et reprise de stationnement

Deux propositions conservées dans le même document Figcraft :
**10 · L2B · A — Suivi en lignes** et **11 · L2B · B — Opérations en cartes**.
L’utilisateur a choisi **A** (« j’aime bien le A ») pour les trois pages L2B.
La validation est enregistrée dans Figcraft. Conception **4/5**, réalisation **5/5**.

Sources examinées : `FleetPageView`, `OrbitRequestView`, `FleetStationingController`,
`FleetStationing` et `FleetHold` pour leurs actions et erreurs ; templates
`ui/orbit_requests{,_list}`, `fleet/{_dock,holds,resume}`, contrôleur
`orbit_requests`, architecture, règles et suivis actuels des flottes.

| Page dédiée conservée | But et périmètre |
| --- | --- |
| `/flottes/orbites` | Demandes envoyées/reçues de la colonie active ; origine inconnue masquée ; réponses seulement si autorisées et retrait par le rappel existant |
| `/flottes/quai` | Unités/réserves de la colonie active, puis stationnements de tout l’empire, y compris après perte de l’origine |
| `/flottes/{id}/reprendre` | Choix d’une base possédée, calcul GET puis confirmation POST du devis ; erreur visible et absence de colonie sans perte automatique |

Les tâches gardent leurs pages et URL ; carburant et fret conservent leurs deux
actions de déchargement. La reprise garde un seul formulaire de choix de base,
suivi du devis et de sa confirmation. Le menu principal conserve Flottes ; les
trois vues proposent le même menu secondaire mobile que L2A. Aucun filtre, tri
métier, regroupement reçu/envoyé ni indicateur privé n’est ajouté. Les demandes
sont directement lisibles ; Composition et fret des stationnements reste dépliable.

Accord automatique/confirmé : aucune réponse supplémentaire. Échéance atteinte :
arrivée à résoudre. Ancienne demande : aucun faux délai. Le remplacement d’un
fragment attend que le focus quitte ses actions, comme actuellement ; compteur,
contexte et contrôles serveur doivent être conservés. Le fret d’hydrogène ne
devient pas du carburant de reprise ; le déchargement reste limité aux capacités.

### A — Suivi en lignes

Orbites en lignes larges : informations à gauche, échéance/actions à droite sur
grand écran, puis empilées. Au quai, vaisseaux et réserve sont côte à côte, suivis
des stationnements de tout l’empire sur la largeur complète. La reprise rapproche
choix de base et devis, puis les empile aux formats étroits.
Bénéfice : comparaison des statuts et revue du devis sans aller-retour vertical
sur ordinateur. Limite : les stationnements suivent la réserve sur mobile ; les
lignes doivent se développer pour les noms longs. **Recommandation : A**, pour
la lecture des demandes et la continuité avec A de L2A.

### B — Opérations en cartes

Les demandes occupent deux colonnes sur grand écran, une ailleurs. Au quai, la
liste des vaisseaux précède réserve et stationnements, placés côte à côte sur
ordinateur. La reprise conserve une lecture verticale : choix, devis, confirmation.
Même menu, champs, états et droits que A. Bénéfice : demandes séparées visuellement
et réserve/stationnements visibles ensemble sur ordinateur. Limite : davantage de
défilement dans la reprise et lecture des demandes sur deux colonnes.
Les deux options réutilisent les préparations ciblées, sans agrégation métier imposée.

### États et contrôle de conception

**20 cadres** : trois pages en 1440/768/390 px pour chaque option et une planche
d’états par option. Demande reçue, envoi avec accord automatique, échéance atteinte,
mission ancienne, nom long, réserve partiellement déchargeable, stationnements,
devis, listes vides, réserve insuffisante, version périmée et absence de colonie
sont représentés ou explicités. Les chiffres sont fictifs et les actions simulées.

Structures grises enregistrées avant couleur, composants/variables propres au lot,
mêmes couleurs et typographie que les références approuvées. Les neuf pages
précédentes sont identiques par empreinte, dont Vue générale et L2A A. Aucun texte
hors cadre, superposé ou coupé par son parent sur les vingt cadres ; **178 liens**
ont une destination existante. La revue visuelle a compacté A et ajusté hauteurs
et échéances d’exemple. Ce contrôle ne constitue pas une recette du jeu ou un audit RGAA.

Exports locaux : `lot-2B-{A,B}-{orbits,dock,resume}-{1440,768,390}.png`, planches
`states-1100`, aperçus `lot-2B-{A,B}-{orbits,dock}-apercus.png`, manifeste et
`lot-2B-verification.json`. Le fichier natif reste celui indiqué au début du suivi.
Après choix : réponses orbitales, contextes/délais, déchargement partiel, devis
périmé, origine perdue, accès refusés, formulaires sans JavaScript, focus et
actualisation ; recette responsive/thèmes et contrôles Make adaptés.

### Réalisation A et contrôles locaux

Les trois pages réutilisent le socle `.fleet-redesign` de L2A et le menu natif,
avec `fleet-harbour.css` limité à `.fleet-harbour`. Les demandes sont directement
lisibles ; `orbit-requests` actualise le compteur indépendamment du target
optionnel `details`, conservé pour les anciennes vues. La protection de focus et
le nettoyage des écouteurs/minuteries restent inchangés.

Au quai, unités et réserve sont côte à côte sur grand écran ; sans réserve, les
unités reprennent la largeur disponible. Les stationnements de tout l’empire
gardent une section distincte et leurs détails dépliables, même après perte de
l’origine. Le titre et le message vide restent affichés lorsque cette liste est
vide. La reprise rapproche choix de base et devis conditionnel, avec ses deux
formulaires existants. Aucun service, lecture, règle, permission ou valeur de jeu
n’est modifié. Champs, méthodes, actions et CSRF ont été comparés avant/après :
identiques pour demandes, déchargement et reprise.

Contrôles réussis :

- `make validate`, `make lint-static` (200 Twig, 80 YAML, PHP et conteneur),
  `make assets`, syntaxe JavaScript et `git diff --check`.
- PHPUnit dans une pile synthétique propre au lot : **60 tests / 471 assertions**,
  suites `FleetStationingTest`, `OrbitDepartureRequestTest`, `OrbitRequestStateTest`
  et `FleetDestinationTest` ; accès, stockages, versions et protections métier.
- Premier passage navigateur : 12 succès sur 14. Le nouveau scénario utilisait
  une réserve synthétique insuffisante pour son trajet ; elle est corrigée sans
  changer le moteur. Le test couvre aussi explicitement le refus d’un devis avec
  réserve nulle. L’ancien test de thème modifiait seulement `data-theme`, alors
  que le clair charge une feuille dédiée : il applique désormais la préférence
  et recharge réellement la page, avec les mêmes assertions Axe.
- Reprise des deux scénarios concernés : **deux succès**. Consolidation :
  **14 scénarios distincts réussis**, dont envois réels, ancien/neutre orbital,
  accord clavier et retrait tactile, stationnement/déchargement, origine perdue,
  maxima de fret, états vides, navigation et régression de L2A. Les tests qui
  ouvraient le fret tiennent compte de son ouverture déjà validée en L2A ; leurs
  vérifications de quantités et d’envoi sont conservées.
- Nouvelle recette A : trois pages, deux thèmes, largeurs 320/390/768/1440 px,
  aucun débordement ; Axe sans violation à 390 px dans `.fleet-harbour`. Origine
  étrangère et fret privés masqués ; ancienne demande sans faux délai ; compteur
  différé tant qu’une action garde le focus, puis passage de quatre à trois.
- Sans JavaScript : déchargements partiels de carburant/fret avec surplus
  conservé, accord orbital, calcul et confirmation de reprise. Autre commandant
  refusé ; changement de colonie sans disparition des stationnements de l’empire.

**24 captures réelles finales** : orbites, quai et reprise en sombre/clair à
1440/768/390 px, plus six fenêtres mobiles. Dossiers
`output/playwright/fleet-harbour/captures-jeu-L2B-A/` et `captures-jeu-L2B-A/`
près du fichier Figcraft, index JSON et préfixe `jeu-L2B-A-`. Données synthétiques ;
aucune partie habituelle modifiée, dépendance installée ou migration existante.
CI GitHub, intégration et déploiement restent distincts. Performance non mesurée,
lecteur d’écran réel non essayé ; ces contrôles ne constituent pas un audit RGAA.

Fichiers principaux : `app/templates/fleet/{index,_dock,holds,resume}.html.twig`,
`app/templates/ui/{fleet_navigation,orbit_requests,orbit_requests_list}.html.twig`,
`app/assets/controllers/orbit_requests_controller.js`, style `fleet-harbour.css`
et import dans `app/assets/app.js`. Nouveau scénario `fleet-harbour.spec.mjs` et
adaptations ciblées des scénarios historiques. Documentation synchronisée.

**Priorité suivante demandée par l’utilisateur** : finaliser la Vue générale à
partir de sa maquette déjà validée, avant de poursuivre les compositions. Les
propositions L2C préparées restent en attente ; leur code n’est pas commencé.

## Vue générale — finalisation de la référence, 18 novembre 2026

Priorité explicite avant L2C, sur la même branche. Complexité **4/5** : composants
partagés, actualisation, journal privé et états occupés. La page Figcraft 01 et
ses deux cadres 1440/390 restent strictement conservés ; aucun nouveau choix
A/B n’est nécessaire pour cette référence déjà validée.

Réalisation : alertes avant la bannière, colonie et exploration de la galaxie,
quatre catégories de points et évolution quotidienne, quatre liens d’empire
intégrés. Activité locale à gauche et arrivées avant les vols personnels à droite ;
sur petit écran, activité puis suivi, puis journal en pleine largeur. Les liens
vers les bâtiments, la recherche, les vaisseaux et les défenses restent disponibles
quand les files sont vides et après actualisation.

La destination garde son URL et ses pages dédiées existantes ; aucun nouvel onglet
ou filtre n’est introduit. Les détails de file restent dépliables et le journal
conserve son formulaire de consultation. Les en-têtes des composants de vols
et le bouton global des événements gardent leur place commune, adaptation limitée
à la maquette pour préserver les actualisations et les autres pages. La barre de
ressources, les lectures métier et la visibilité des flottes ne changent pas.

L’alerte utilise les ressources déjà préparées. Une région vide permet son ajout
ou retrait sans rechargement ; le focus existant en diffère le remplacement.
Les catalogues ne rendent pas cette nouvelle région. `empire-overview.css` est
limité à `.overview-page`. Le lien de confirmation de file affiche maintenant
« Préparer l’annulation », cohérent avec son nom accessible et son comportement.

Vérifications : 28 tests PHP / 562 assertions, dont une mesure optionnelle ignorée
sans `REWORK_MEASURE=1` ; validation Compose, lint PHP/Twig/YAML/conteneur et
compilation réussis. **Huit scénarios navigateur distincts réussis**, consolidés
après corrections ciblées : contraste de l’alerte claire, alignement des points,
encodage d’une édition intermédiaire et nom accessible de la confirmation.
L’ancienne assertion d’ordre des cadres distingue maintenant Vue générale des
cinq autres destinations ; contrôles de confidentialité et de styles conservés.

Recette à 1440/768/390/320 px dans les deux thèmes : aucun débordement,
Axe sans violation à 390 px, files vides et occupées, alertes ajoutées/retirées
avec focus préservé, liens natifs et retour/rechargement sans JavaScript. Journal,
protection, détails de flottes et surveillance restent couverts par les scénarios
existants. **Dix captures finales réelles**, dont deux avec file active, conservées
dans `output/playwright/overview/captures-jeu-vue-generale/` et près du document
Figcraft dans `captures-jeu-vue-generale/`, avec index JSON. Journaux de contrôles
dans `output/playwright/overview/` ; données exclusivement synthétiques.

Fichiers principaux : `colony/show.html.twig`, nouveau fragment
`colony/_overview_attention.html.twig`, composants `ui/colony_queues` et `ui/flights`,
`ColonyController::liveRegions` et `empire-overview.css`. Les scénarios existants
`empire-overview`, `fleet-surveillance` et `AbsenceJournalTest` suivent ce périmètre.
README, architecture, guide, inventaire et documentation des arrivées synchronisés.
Aucune migration existante, nouvelle dépendance, modification d’équilibrage,
publication distante ou CI GitHub. Performance non mesurée ; pas d’audit RGAA global.

## L2C — compositions de flotte, proposition A retenue

Après finalisation de Vue générale (`6b3bb70`), le prochain sous-lot porte sur
Mes compositions, Créer, Modifier et Vérifier/charger. Analyse ciblée de
`FleetCompositionController`, `FleetCompositionService`, des trois templates et
du catalogue des unités mobiles. Complexité : conception 4/5, réalisation 5/5
(propriétaire/univers, versions, disponibilité et continuité de préparation).

Les pages dédiées et leur menu commun restent le support du parcours. Le formulaire
garde ensemble le nom et les quantités des trois rangs ; pas de filtre qui masque
une partie de la saisie. Le chargement reste une vérification de disponibilité
avant le retour à Préparer une mission, sans envoi direct.

- **A — Modèles en lignes** : liste compacte, informations à gauche et actions à
  droite ; éditeur en tableau sur grand écran, une unité et ses trois rangs sur
  mobile ; choix de colonie et disponibilité côte à côte lorsque la largeur suffit.
- **B — Modèles en cartes** : modèles en grille, éditeur par cartes d’unités et
  chargement en étapes verticales. Une seule colonne sur petit écran.

Même contenu et mêmes actions existantes : maximum de 20 modèles par empire/univers,
nom jusqu’à 80 caractères, types mobiles disponibles et trois rangs. Les versions,
jetons, contrôles d’accès et la nouvelle vérification serveur au retour vers l’envoi
restent nécessaires. Une disponibilité réduite exige l’accord explicite existant.
Les données des maquettes sont fictives et leurs actions sont simulées.

Dans le document Figcraft courant, **pages 12 et 13**, après les lots conservés :
quatre vues à 1440/768/390 px par option et une planche d’états par option, soit
**26 cadres**. Structures grises enregistrées avant mise en couleur ; fondations
et composants du document réutilisés. Le contrôle du document vérifie 230 liens,
aucun texte hors cadre, chevauchement ni découpage par son parent ; les onze pages
précédentes, dont Vue générale, sont inchangées. Les exports liste/éditeur et les
écrans de chargement ont fait l’objet de contrôles visuels ciblés.

Manifestes et preuves près du fichier `.figcraft` : `lot-2C-manifest.json`,
`lot-2C-verification.json`, exports `lot-2C-{A,B}-{list,new,edit,load}-{largeur}.png`
et aperçus `lot-2C-{A,B}-list-apercus.png`. Il s’agit de conception uniquement :
les contrôles de conception seuls ne valident pas les parcours du jeu.
L’utilisateur choisit ensuite **A** (« le A est bien »). Ce choix est enregistré
dans la version Figcraft `L2C - A validee par utilisateur` ; les autres propositions
et la référence Vue générale restent conservées.

### Réalisation A et vérifications — 18 novembre 2026

Liste en lignes avec informations et actions séparées sur grand écran ; éditeur
en tableau, puis trois quantités réunies par vaisseau sur mobile. Une seule série
de champs est rendue à chaque largeur, avec libellés et rôles de tableau conservés.
La revue de chargement place le choix de colonie à gauche lorsque la largeur
suffit. Les trois pages dédiées de formulaire/liste gardent leurs URL ; la création
et la modification partagent l’éditeur existant. Le menu mobile commun est étendu
aux quatre destinations ; aucune nouvelle page, ancre de substitution ni filtre.

`fleet-compositions.css` est limité à `.fleet-compositions`, avec le socle L2A
`.fleet-redesign`. Les confirmations restent natives ; `Modifier` garde le nom du
modèle dans son libellé accessible. Lien de chantier souligné, manques exprimés
par colonne, libellé et couleur. Contrôleurs et services métier inchangés.
Champs, méthodes, noms de sélection et CSRF comparés avant/après : identiques.

Contrôles locaux réussis :

- `make validate`, `make lint-static` (201 Twig, 80 YAML, PHP et conteneur),
  `make assets`, syntaxe JavaScript et `git diff --check`.
- **27 tests PHP / 372 assertions**, dans une pile jetable propre au lot :
  `StrategicToolsTest`, `FleetDestinationTest` et le refus de composition exacte
  devenue indisponible de `GamePersistenceTest`. Versions, requêtes rejouées,
  isolation, CSRF, brouillon et absence d’envoi automatique couverts.
- **Cinq parcours navigateur distincts consolidés** : recette A des quatre vues,
  navigation/contexte/actualisation, parcours sans JavaScript, chargement explicite
  avec manques et plafond de vingt soumis à deux créations réellement simultanées.
- Recette A à 1440/768/390/320 px dans les deux thèmes : aucun débordement ;
  Axe sans violation à 390 px dans `.fleet-compositions`, y compris après erreur
  de saisie et dans la colonie sans vaisseau. Nom de 80 caractères, trois rangs,
  focus du menu, brouillon et clé conservés après 422 ; stock changé avant
  chargement refusé puis revu, suppression native sans JavaScript, unités intactes.

Le premier passage a révélé un libellé identique porté par le panneau et le champ
de colonie ; le panneau reste structuré par son titre, le libellé est réservé au
champ. Une reprise a révélé le lien de chantier insuffisamment distingué du texte
en thème clair ; il est souligné. Bouton de création compact sur ordinateur et
largeur complète sur mobile ; titres de lignes du tableau rendus plus lisibles.
Les scénarios existants et leurs assertions sont conservés.

**36 captures réelles finales**, quatre vues et états erreur/sans vaisseau, dans
`output/playwright/fleet-compositions/captures-jeu-L2C-A/` et `captures-jeu-L2C-A/`
près du document Figcraft ; index JSON et journaux de contrôles conservés.
Fichiers principaux : `fleet/{compositions,_composition_editor,composition_load}`,
`ui/fleet_navigation`, style et import, nouveau `fleet-compositions.spec.mjs`.
Aucune base habituelle modifiée, dépendance installée, règle changée ou migration
existante appliquée. CI GitHub, fusion et déploiement distincts ; pas d’audit RGAA
global ni de mesure de performance annoncée.

## L3 — Inventaire, proposition A retenue — 18 novembre 2026

L2C A est enregistré dans `da86685`. Le prochain lot conserve les trois pages
existantes : `/boosts`, `/inventaire/boites`, `/inventaire/recompenses`.
Conception **4/5**, réalisation **4/5** : capacité commune, consommation unique,
colonies autorisées, vacances, stocks et reprise des récompenses en attente.
Sources examinées : `BoostController`, `InventoryPageView`, les cinq templates,
la navigation, `inventory-review`, services d’utilisation de boosts/boîtes,
configuration des bonus et suivi des pages dédiées. Aucun audit général rouvert.

Deux pages ajoutées au document Figcraft courant, sans nouvel index :

- **14 · L3 · A — Inventaire guidé** : stock et choix d’opération rapprochés,
  sélecteur existant pour afficher une opération ; boîtes en lignes avec contenu
  à gauche et destination/ouverture à droite sur ordinateur ; attente en liste
  ordonnée. Une seule colonne sur mobile.
- **15 · L3 · B — Inventaire en cartes** : stock puis opérations actives visibles
  en cartes, sans sélecteur masquant les autres ; boîtes en grille avec leur
  destination sous le contenu ; attente en cartes numérotées dans le même ordre.
  Une seule colonne sur mobile. Ce mode réutilise la présentation de toutes les
  opérations déjà disponible sans le sélecteur, sans nouvelle lecture métier.

Les tâches restent sur leurs pages dédiées. Le choix d’opération de A est une
sélection dans la même tâche, pas une nouvelle page. La capacité partagée reste
visible sur les trois pages ; Comprendre vos bonus reste un dépliable. Les deux
fenêtres de revue représentent les confirmations JavaScript existantes, sans
nouvelles URL ; les formulaires serveur restent accessibles sans JavaScript.
Les liens de retour et les notifications de refus restent ceux du jeu.

Même contenu et mêmes conditions dans les deux options : retrait de 25 % du temps
restant et capacité de 100 selon la configuration locale, contenu de boîte fixé
à l’attribution, regroupement par contenu identique, dépôt intégral ou refus,
colonies du même univers, restrictions de vacances, opérations actives autorisées,
attente chronologique automatiquement promue et pagination de 25 objets.
Les nombres illustrés sont fictifs : 12 boosts, 88 boîtes regroupées, trois objets
en attente et deux colonies. Aucun bouton de récupération manuelle ou nouveau
tri n’est proposé ; aucune règle ou valeur d’équilibrage n’est modifiée.

**32 cadres** : trois pages et deux revues à 1440/768/390 px pour chaque option,
plus une planche d’états par option. Stocks nuls, aucune opération, vacances,
absence de colonie, capacité pleine, stockage insuffisant, opération terminée,
objet consommé, rejeu, contexte refusé, focus et parcours sans JavaScript sont
représentés ou explicités. Les actions du prototype sont simulées.
Structures grises enregistrées avant la couleur ; styles de référence et boutons
réutilisés avec des variables propres au lot. Les treize pages antérieures,
dont Vue générale et L2C A, restent strictement conservées.

Contrôle du document : 32 cadres, 226 liens vers une destination existante,
aucun texte hors cadre, chevauchement ou découpage par son parent. Exports
Boosts, Boîtes et confirmations relus visuellement, dont les formats mobiles ;
compteurs d’exemple cohérents avec les deux colonies. Manifestes, exports et
preuves près du `.figcraft` : `lot-3-manifest.json`, `lot-3-verification.json`,
`lot-3-{A,B}-{boosts,boxes,pending,review-boost,review-box}-{largeur}.png`.
Ce contrôle de conception ne constitue pas une recette du jeu ni un audit RGAA.

L’utilisateur choisit **A** (« j’aime bien le A »). Cette validation est enregistrée
dans la version Figcraft `L3 - A validee par utilisateur`, sans modification des
maquettes précédentes ni validation implicite du prochain lot.

### Réalisation A et vérifications locales

Stock et opération choisie sont côte à côte sur grand écran, puis empilés. Chaque
boîte garde contenu et formulaire sur une ligne large, puis une colonne sur mobile.
La capacité occupe toute la largeur sur les trois pages. L’attente conserve son
ordre natif, sa numérotation et sa pagination, avec une ligne par récompense.
Les tâches gardent leurs URL ; le choix d’opération filtre une même tâche et
l’aide complémentaire reste dépliable. Les anciens fragments restent accessibles.

`inventory-workspace.css` est limité à `.inventory-redesign`. Le menu natif partagé
est étendu à l’Inventaire : ses compteurs sont optionnels dans `ui/space_navigation`,
les autres consommateurs gardent leurs libellés. Le choix d’opération et la revue
réutilisent `inventory-review` sans changement du contrôleur. Sans JavaScript,
le menu est ouvert et tous les formulaires d’opérations restent disponibles.
Méthodes, actions, noms de champs et CSRF comparés avant/après : identiques.
Aucun contrôleur PHP, service, calcul, chargement ou règle de jeu n’est modifié.

Contrôles réussis :

- `make validate`, `make lint-static` (201 Twig, 80 YAML, PHP et conteneur),
  `make assets`, syntaxe JavaScript et `git diff --check`.
- **22 tests PHP / 318 assertions**, pile MariaDB jetable propre au lot :
  `InventoryPagesTest`, `BoostSystemTest`, `ResourceBoxTest`. Isolation, CSRF,
  capacité commune, lectures ciblées, historique utilisé exclu, ordre/pagination,
  dépôt intégral ou refus, rejeu et promotion de la plus ancienne attente couverts.
- **Sept parcours navigateur distincts réussis** dès le premier passage : état
  vide/plein et effet configuré ; boosts sur file puis flotte ; pages et fragments ;
  nouvelle recette A ; ouverture unique sur colonie choisie ; stockage/vacances ;
  consommation sans JavaScript. Les assertions existantes sont conservées.
- Après finitions du repère de page active et de la jauge, seule la nouvelle recette
  visuelle est relancée : réussite. Données synthétiques d’attente passées et ordonnées.
- Trois pages et deux revues à 1440/768/390/320 px, thèmes sombre et clair, aucun
  débordement ; Axe sans violation à 390 px, y compris vide et vacances. Opérations
  de deux colonies visibles au choix, noms longs, contenu regroupé, compteurs,
  clavier/historique/rechargement, focus initial et retour après Échap vérifiés.
  Aucune consommation pendant une annulation ; formulaires natifs confirmés par
  les parcours sans JavaScript. Ces contrôles ne constituent pas un audit RGAA.

**50 captures finales réelles** : trois pages, deux confirmations et états vide/
vacances. Dossiers `output/playwright/inventory-workspace/captures-jeu-L3-A/` et
`captures-jeu-L3-A/` près du fichier Figcraft, avec index JSON ; journaux conservés.
Fichiers principaux : `boosts/{index,_boosts,_resource_boxes,_pending}`, navigation
Inventaire et menu commun, style/import, nouveau `inventory-workspace.spec.mjs`.
Documentation synchronisée dans le même lot. Aucune dépendance installée, base
habituelle modifiée ou migration existante appliquée. CI GitHub, fusion et
déploiement restent distincts ; performance non mesurée.

## L4 — Mon compte, proposition A retenue — 18 novembre 2026

L3 A est enregistré dans `16a9173`. Le lot suivant conserve quatre pages dédiées :
`/profil`, `/profil/preferences`, `/profil/vacances`, `/profil/supprimer`.
Complexité : conception **3/5**, réalisation **5/5** en raison des confirmations,
de la déconnexion et des portées compte/univers. Sources ciblées examinées :
les quatre templates et leur navigation, `ProfileController`, `PreferencesController`,
`VacationController`, `ProfileType`, `PreferencesType`, leurs données de formulaire,
`ProfileService`, `AccountDeletionService` et les conditions de `VacationPolicy`.
Aucun audit global ni chantier métier rouvert.

Deux pages ajoutées au même document Figcraft de bureau :

- **16 · L4 · A — Compte structuré** : champs et confirmation côte à côte sur
  ordinateur pour les identifiants, les vacances et la suppression. Préférences
  regroupées, avec raccourcis et réception des transports en deux colonnes larges.
  Sections empilées sur tablette/mobile ; les champs proches restent en deux
  colonnes lorsque leur largeur le permet.
- **17 · L4 · B — Compte en sections** : champs puis confirmation en sections
  successives ; préférences et champs toujours empilés, même sur ordinateur.
  Même contenu et mêmes conditions dans les deux propositions.

Les quatre tâches conservent leurs URL, formulaires et liens de retour existants.
La navigation secondaire commune garde la page active identifiable et se replie
sur mobile. Le mot de passe facultatif reste un dépliable dans le formulaire
unique des identifiants : les cadres fermé/ouvert représentent le même état de
page, pas deux tâches ni deux formulaires. Les vacances inactives/actives restent
également deux états de la même page. Aucun onglet JavaScript ne remplace les
pages dédiées ; les formulaires serveur restent prévus sans JavaScript.

La portée reste explicite : identifiants et préférences communs à tous les
univers ; vacances limitées à l’empire de l’univers sélectionné ; suppression
définitive du compte dans tous les univers. Un profil enregistré ferme la session
courante et demande une reconnexion. Le mot de passe actuel est obligatoire ;
les deux champs de remplacement restent facultatifs, jamais préremplis.
Préférences conservées : thème, fuseau horaire, deux vitesses, réception des
transports et trois options de lecture, avec un seul enregistrement.

Les conditions actuelles de vacances sont reprises : minimum 48 heures, maximum
90 jours, délai de 15 jours, flottes rentrées et règles d’orbite/inactivité.
Aucune date de fin saisissable ajoutée. L’indisponibilité, le refus d’activation,
l’absence d’univers et la sortie avant 48 heures sont explicités dans les états.
La suppression garde mot de passe, case de confirmation, portée sur tous les
univers, conversion des empires, conservation des historiques et refus du dernier
administrateur actif ou d’une conversion impossible. Ces actions sont simulées
et ces protections devront être vérifiées dans le jeu après le choix utilisateur.

**38 cadres** : quatre pages, mot de passe déplié et vacances actives, chacun à
1440/768/390 px et dans les deux options, plus deux planches d’états. Données
fictives et mots de passe vides. Structures grises enregistrées avant mise en
couleur ; variables propres au lot et boutons réutilisables, dont une action de
suppression distincte. Les quinze pages précédentes, dont Vue générale, sont
conservées intégralement.

Contrôle du document : **38 cadres et 268 liens** vers une destination existante ;
aucun texte hors cadre, superposé ou coupé par son parent. Empreintes des quinze
pages antérieures inchangées, dont la référence Vue générale. Exports relus :
identifiants A/B sur ordinateur, mot de passe déplié sur mobile, préférences sur
tablette/mobile, vacances actives sur mobile et suppression sur ordinateur/mobile.
Manifestes et preuves conservés près du `.figcraft` : `lot-4-manifest.json`,
`lot-4-preservation-avant.json`, `lot-4-verification.json` et
`lot-4-{A,B}-{profile,preferences,vacation,delete,profile-password,vacation-active}-{largeur}.png`.
Après précision de deux textes (vitesse par défaut et caractères du nom), les
huit cadres concernés sont revérifiés : aucun défaut de limites ou superposition ;
rapport complémentaire `lot-4-verification-textes.json`. Version sauvegardée :
`L4 - propositions A B pour validation`.
Ces contrôles de conception ne constituent ni une recette du jeu ni un audit RGAA.

L’utilisateur choisit **A** (« j’aime bien le A »). Validation conservée dans la
version Figcraft `L4 - A validee par utilisateur`. Il demande de conserver la
méthode et l’organisation A/B actuelles pour les lots suivants.

### Réalisation A et vérifications locales

Profil, vacances et suppression présentent les informations à gauche et leur
confirmation à droite à partir de 1201 px, puis une colonne. Les préférences
regroupent affichage/horloge, raccourcis, réception et confort, avec un seul
bouton d’enregistrement. Le formulaire de profil reste unique : le découpage
en panneaux ne crée pas deux validations. Le mot de passe facultatif reste un
`details` natif, fermé au départ et ouvert après un formulaire invalide.

Les cinq templates réutilisent `ui/space_navigation` pour le menu mobile.
`account-workspace.css` est limité à `.account-redesign`. Aucun contrôleur PHP,
service, DTO, formulaire Symfony ou contrôleur JavaScript n’est modifié. Noms des
champs, méthodes/actions, CSRF et contexte de participation sont conservés.
Les quatre pages et les ancres historiques restent utilisables. Le compte et
les préférences restent globaux, les vacances limitées à l’univers sélectionné.

Contrôles locaux :

- `make validate`, `make lint-static` (201 Twig, 80 YAML, PHP et conteneur),
  `make assets`, syntaxe JavaScript et `git diff --check` réussis.
- **34 tests PHP / 209 assertions** sur une pile MariaDB jetable : préférences,
  service et politique de vacances, contrôleur des vacances, suppression et
  mise à jour du profil. Isolation, minimum de 48 h, délai de 15 jours, refus
  serveur, absence d’univers, mots de passe, dernier administrateur, combat et
  rollback d’une conversion sur plusieurs univers sont couverts.
- Six parcours navigateur concernés : profil/refus/reconnexion ; suppression
  confirmée et autre session fermée ; quatre pages/thèmes/historique/anciens
  liens ; formulaires sans JavaScript ; persistance des préférences ; recette A.
  Les tests existants gardent leurs assertions ; le menu mobile est ouvert avant
  d’examiner ses liens. Quatre réussites au premier passage, puis reprise ciblée.
- La recette A vérifie 1440/768/390/320 px, thèmes sombre/clair, confirmation à
  droite ou sous le contenu, mot de passe déplié, champs conservés après erreur,
  mots de passe vides, clavier/rechargement/retour et absence de débordement.
  Axe ne signale aucune violation dans l’espace à 390 px. Le contraste des
  erreurs Symfony en thème clair a été corrigé sans retirer de vérification.
- La pile navigateur habituelle désactive les vacances : ce refus est vérifié.
  Une pile dédiée les active uniquement pour ce test synthétique : activation
  disponible et vacances récentes avec sortie bloquée avant 48 h sont vérifiées.
  La sortie autorisée est exercée par le parcours sans JavaScript existant.

**52 captures réelles finales**, avec index : quatre pages, mot de passe déplié,
vacances actives, compte sans univers et erreur de profil. Dossiers
`output/playwright/account-workspace/captures-jeu-L4-A/` et `captures-jeu-L4-A/`
près du fichier Figcraft. Recette `account-workspace.spec.mjs`, parcours clavier
adapté dans `profile-forms.spec.mjs` ; README, architecture, guide et suivi synchronisés.
Aucun changement de configuration globale, dépendance installée, donnée habituelle
modifiée ou migration sur une base existante. Ces contrôles ciblés ne constituent
pas un audit RGAA ; performance non mesurée, CI GitHub/fusion/déploiement distincts.

## L5 — Projets collectifs, proposition A retenue — 18 novembre 2026

L4 A est enregistré dans `e13e588`. Le lot conserve les trois listes dédiées
`/guildes/projets`, `/guildes/projets/disponibles`, `/guildes/projets/historique`
et la fiche `/guildes/projets/{id}`. Complexité : conception **3/5**, réalisation
**4/5**, avec droits de lancement, dons définitifs, versions, univers et minimums.
Sources ciblées examinées : contrôleur, préparation des listes/fiches, service,
règles de projets, six templates et deux navigations ; guide courant des projets.
Les lectures ciblées, pagination et contrôles métier ne sont pas repensés.

Deux pages ajoutées au même document Figcraft :

- **18 · L5 · A — Projets en lignes** : identité, financement du palier et
  contribution personnelle alignés sur grand écran. Modèles disponibles en lignes,
  avec conditions utiles et lancement rapprochés. Sur la fiche active, progression
  à gauche et choix de colonie/don à droite ; une colonne sur tablette/mobile.
- **19 · L5 · B — Projets en cartes** : deux cartes par ligne sur ordinateur,
  informations successives dans chaque carte. La fiche place la progression
  avant le formulaire de contribution, y compris sur ordinateur. Une colonne
  sur tablette/mobile. Même contenu, minimums et restrictions dans les deux options.

Les listes et la fiche restent des pages autonomes, avec navigation de guilde
commune puis navigation des projets, toutes deux compactes sur mobile. Les
contributions et les conditions/paliers restent des dépliables natifs de la fiche.
Les ancres et paginations existantes sont conservées ; aucune modale ni étape
supplémentaire n’est ajoutée au don. Le bouton annonce le prélèvement définitif
sur la colonie choisie. Les minimums personnels utiles à la décision restent visibles.

**38 cadres** : trois listes et une fiche illustrée dans ses trois statuts actif,
terminé et abandonné à 1440/768/390 px pour A/B, plus deux planches d’états.
Les nombres et modèles sont fictifs, sans modèle actif créé ni valeur de jeu modifiée.
Une fiche illustre chaque statut ; les autres exemples et les actions de
lancement/don renvoient aux états simulés du prototype.

Les états explicitent : absence de guilde, fonctionnalité désactivée, listes vides,
vacances, permissions de lancement, modèle déjà utilisé, minimums par ressource,
présence à la clôture, boost déjà gagné, récompense en cours de distribution,
inventaire plein, abandon sans remboursement, version périmée, stock insuffisant,
colonie étrangère et ordre/pagination des contributions. Les validations serveur
et la distribution différée restent à vérifier dans le jeu après choix utilisateur.

Structures grises sauvegardées avant la couleur. Variables propres au lot et
boutons réutilisables dans la continuité des références. Les dix-sept pages
antérieures sont conservées, dont Vue générale et Mon compte A.
Contrôles de conception : **38 cadres, 478 liens** vers des destinations existantes,
aucun texte hors cadre, chevauchement ou découpage par un parent. Les empreintes
des dix-sept pages précédentes sont inchangées. Après élargissement du bouton
Membres, les 24 cadres ordinateur/tablette sont revérifiés : aucun défaut.
Listes A/B sur ordinateur, fiche active sur ordinateur/mobile, modèles sur tablette,
historique mobile et fiche terminée relus visuellement. Les contributions fictives
des listes et des fiches de bilan concordent.

Preuves près du `.figcraft` : `lot-5-manifest.json`, `lot-5-preservation-avant.json`,
`lot-5-verification.json`, `lot-5-verification-navigation.json` et
`lot-5-{A,B}-{ongoing,available,history,detail,completed,abandoned}-{largeur}.png`.
Versions de structure et de navigation conservées dans le document.
Ces contrôles de conception ne constituent pas une recette du jeu ni un audit RGAA.
L’utilisateur choisit **A** (« j’aime bien le A »). Validation sauvegardée dans
Figcraft `L5 - A validee par utilisateur`, sans toucher aux pages précédentes.

### Réalisation A et vérifications locales

Listes en lignes sur grand écran, identité/financement/contribution côte à côte ;
modèles avec description/paliers et minimums/lancement rapprochés. Les minimums
restent visibles, hors du dépliable des paliers. Fiche active : progression à
gauche et don à droite à partir de 1201 px, puis une colonne. Maximums des dons
indiqués en texte, sans changer les bornes ni les champs. Fiches terminées,
abandonnées et consultation pendant les vacances sans formulaire de don.

`guild-project-workspace.css` reste limité à `.project-redesign`. Les deux menus
utilisent `ui/space_navigation`, ouvert sans JavaScript ; l’option facultative
`currentAriaValue` conserve `location` sur la fiche et `page` par défaut ailleurs.
Le titre de fiche ne contient plus une balise HTML parasite. Aucun contrôleur,
service, règle de jeu, requête ou contrôleur JavaScript n’est modifié.
Méthodes/actions, noms des champs et CSRF des deux formulaires comparés avant/après :
identiques. Les versions, accès et dépliables/paginations restent existants.

Contrôles réussis :

- `make validate`, `make lint-static` (201 Twig, 80 YAML, PHP et conteneur),
  `make assets`, syntaxe JavaScript et `git diff --check`.
- **44 tests PHP / 239 assertions** dans une pile MariaDB jetable :
  `GuildProjectPagesTest`, `GuildProjectTest`, `GuildProjectRulesTest`.
  Accès par guilde/univers, lectures et paginations bornées, minimums, stocks,
  colonie étrangère, versions périmées, CSRF, départ/dissolution, vacances,
  attribution unique et récompense conservée en attente couverts.
- **Trois parcours navigateur réussis** sur le passage final : financement de
  deux paliers à plusieurs membres et boost unique ; lancement/don/historique
  sans JavaScript ; recette A. Les assertions métier existantes sont conservées.
  Les menus mobiles sont ouverts avant d’examiner leurs liens. La nouvelle recette
  a été reprise après correction de sa mise en situation clavier (Tab avant focus).
- Six vues/états à 1440/768/390/320 px, thèmes sombre/clair ; aucune barre horizontale,
  Axe sans violation dans l’espace à 390 px. Minimums visibles, ressource déjà
  financée sans champ de don, maximums, colonie au nom long, clavier/retour/
  rechargement, absence de guilde, listes vides, contributions et vacances vérifiés.

**66 captures réelles finales**, indexées dans
`output/playwright/project-workspace/captures-jeu-L5-A/` et `captures-jeu-L5-A/`
près du fichier Figcraft. Nouveau `project-workspace.spec.mjs` et adaptation
ciblée de `guild-projects.spec.mjs`. README, architecture, guide des guildes,
guide d’interface et suivi synchronisés. Aucune installation, migration sur base
existante ou modification de données habituelles. Performance non mesurée ;
ces contrôles ne constituent pas un audit RGAA. CI GitHub, fusion et déploiement
restent distincts.

## L6 — Événements et Défis, proposition A retenue — 18 novembre 2026

L5 A est enregistré dans `9eb316e`. Le lot conserve les trois routes existantes :
`/evenements`, `/evenements/defis` et `/evenements/defis/{id}`. Complexité :
conception **3/5**, réalisation **4/5** ; contexte d’univers, périodes, seuils et
états des récompenses. Sources ciblées examinées : contrôleurs des pages et fiches,
`ChallengeQueryService`, les trois templates, résumé de défi, navigation,
pagination, objectifs du planificateur et documentation d’architecture courante.

Deux pages ajoutées au même document Figcraft :

- **20 · L6 · A — Événements en lignes** : annonces et bonus en lignes, dates
  regroupées à droite ; défis avec objectif, progression/récompense et accès
  rapprochés. Fiche avec objectif/récompense à gauche et participation/action à
  droite sur grand écran. Une colonne sur tablette/mobile.
- **21 · L6 · B — Événements en cartes** : deux cartes par ligne sur ordinateur,
  informations successives dans chaque carte. Fiche avec objectif/récompense,
  puis participation, y compris sur ordinateur. Une colonne sur tablette/mobile.

Événements et Défis restent des pages dédiées avec navigation secondaire commune
repliable sur mobile. En cours et À venir restent deux sections du même document
d’annonces, accessibles par leurs ancres ; aucun nouvel onglet ou filtre ne
remplace une tâche autonome. La fiche garde son URL et le retour vers les défis.
Aucun formulaire d’inscription, classement collectif supplémentaire, bouton de
récupération manuelle ou nouveau compte à rebours n’est proposé.

**38 cadres** : deux listes et quatre illustrations de fiche (défi guidé avec
boîte, rotation Top 3, clôture avec objet en attente, annulation sans objet), à
1440/768/390 px en A/B, plus deux planches d’états. Noms, pourcentages, objectifs,
contributions, contenus et dates sont fictifs ; aucun événement réel n’est créé
ni aucune valeur d’équilibrage modifiée.

Les listes utilisent le fuseau personnel ; les dates des fiches restent
explicitement en UTC, avec début prévu/effectif et fin distincts. Objectif
collectif, minimum individuel et contenu complet de boîte restent visibles,
y compris les ressources à zéro. Top 3/ex æquo, seuil, contribution positive,
objectif non atteint, attente de démarrage, clôture en cours, objet disponible/
en attente/utilisé et annulation avec récompense déjà acquise sont explicités.
Les liens de participation gardent leur objectif : exploration/combat sur la
carte, transport entre ses propres colonies, inventaire pour un objet déjà attribué.
Les sorties hors du lot sont simulées par la planche d’états, sauf l’inventaire
relié à sa conception approuvée.

Les deux paginations d’annonces restent indépendantes par 25, comme celle des
défis ; états vides et anciens liens conservés. Le panneau global Événements,
ses compteurs et actualisations restent hors du lot. Structures grises sauvegardées
avant la couleur ; styles propres au lot dans la continuité des références.
Les dix-neuf pages précédentes sont conservées à l’issue de la conception.

Contrôle du document : **38 cadres et 250 liens** vers des destinations existantes ;
aucun texte hors cadre, superposé ou coupé par son parent. Empreintes des dix-neuf
pages antérieures inchangées. Après précision du texte des découvertes comptées,
les douze cadres concernés sont revérifiés : aucun défaut. Exports relus :
annonces sur ordinateur, défis en cartes, fiche guidée mobile, rotation sur
ordinateur, annulation mobile et clôture sur tablette. Horaires cohérents entre
listes en Europe/Paris et fiches UTC ; dates des états clos/annulés antérieures
aux illustrations actives.

Preuves près du `.figcraft` : `lot-6-manifest.json`, `lot-6-preservation-avant.json`,
`lot-6-verification.json`, `lot-6-verification-textes.json` et
`lot-6-{A,B}-{events,challenges,guided,weekly,closed,cancelled}-{largeur}.png`.
Version finale `L6 - propositions A B pour validation`, page A sélectionnée.
Ces vérifications de conception ne constituent ni une recette du jeu ni un audit RGAA.

L’utilisateur choisit **A** (« j’aime bien le A »). Validation conservée dans
Figcraft `L6 - A validee par utilisateur`, sans modification des pages antérieures.

### Réalisation A et vérifications locales

Les trois pages gardent leurs URL. Annonces en lignes avec dates à droite ; défis
avec identité/objectif, progression/récompense et accès rapprochés. Sur la fiche,
l’objectif est à gauche et la participation à droite à partir de 1201 px ; les
panneaux s’empilent ensuite. Le menu secondaire réutilise le composant natif,
ouvert sans JavaScript. En cours/À venir restent deux sections d’un même document,
avec leurs ancres et paginations indépendantes.

`event-workspace.css` est limité à `.event-redesign`. Le résumé partagé avec
l’administration reste inchangé. Les liens d’exploration/combat/transport sont
déplacés avec leurs conditions existantes ; aucun accès, calcul, chargement,
contrôleur JavaScript ou service PHP n’est modifié. Les dates, seuils, boîtes,
états personnels et l’ordre de priorité objet attribué/annulation restent existants.
Le panneau global, ses compteurs et ses actualisations restent hors du lot.

Contrôles réussis :

- `make validate`, `make lint-static` (201 Twig, 80 YAML, PHP et conteneur),
  `make assets`, syntaxe JavaScript et `git diff --check`.
- **32 tests PHP / 175 assertions** sur une pile MariaDB jetable : `EventPagesTest`,
  `WeeklyRotationTest`, `AdminEventPlannerTest`. Isolation, filtres, pagination,
  absence de chargement d’historique sur l’entrée, objectifs/seuils, classement
  ex æquo, annulation et conservation des récompenses acquises couverts.
- **Trois parcours navigateur réussis dès le premier passage** : pages/thèmes/
  anciens liens et sans JavaScript ; publication guidée puis attribution réelle
  d’une boîte dans la pile de test et ouverture ; recette A des états. Les
  assertions existantes sont conservées ; seul le menu mobile est ouvert avant
  d’en parcourir les liens. Après finition des lignes de boîte sur mobile et du
  lien de retour, seule la recette A est relancée : réussite.
- Six vues/états à 1440/768/390/320 px et deux thèmes, sans débordement ; Axe
  sans violation dans l’espace à 390 px. Boîte complète avec ressource à zéro,
  Top 3 et classement personnel, transport sans objectif global, planification,
  objectif collectif manqué, minimum individuel manqué, objet disponible/en attente/
  utilisé et objet acquis avant annulation vérifiés. Clavier, focus, historique et
  rechargement conservés. Les dates UTC des fiches et le fuseau des listes restent distincts.

**66 captures réelles finales**, indexées dans
`output/playwright/event-workspace/captures-jeu-L6-A/` et `captures-jeu-L6-A/` près
du fichier Figcraft. Nouveau `event-workspace.spec.mjs` et adaptation ciblée de
`event-pages.spec.mjs`. README, architecture, guide et suivi synchronisés.
Aucune dépendance installée, migration sur base existante ou donnée habituelle
modifiée. Performance non mesurée ; ces contrôles ne constituent pas un audit
RGAA. CI GitHub, fusion et déploiement restent distincts.

## L7A — Colonies, comparaison et abandon, propositions préparées — 18 novembre 2026

L6 A est enregistré dans `811ef2d`. Vue générale, déjà finalisée dans `6b3bb70`,
reste conservée comme référence ; aucune nouvelle version A/B n’est produite pour
elle. Le reste de L7A concerne `/planetes`, sa présentation `?view=table` et
`/planetes/{id}/abandonner`. Conception réévaluée à **4/5**, réalisation **4/5** :
bilan d’une action irréversible, confidentialité des refus et confirmation périmée.
Sources ciblées examinées : actions de liste/renommage, contrôleur et service
d’abandon, templates, fiche de ressources/vaisseaux, contrôleur de comparaison,
styles et documentation actuelle de l’abandon.

Deux pages ajoutées au même document Figcraft :

- **22 · L7A · A — Colonies en lignes** : cartes horizontales sur grand écran,
  illustration/identité, informations et accès rapprochés. Comparaison en tableau ;
  bilan et confirmation d’abandon côte à côte sur grand écran, puis empilés.
- **23 · L7A · B — Colonies en cartes** : deux cartes par ligne sur ordinateur,
  illustration puis informations et actions. Comparaison en fiches par colonie ;
  bilan complet puis confirmation de l’abandon. Une colonne sur tablette/mobile.

Cartes et comparaison restent deux présentations d’un même ensemble, avec les
paramètres existants `view=table` et `resource`. Le choix d’une ressource conserve
les cinq possibilités : Métal, Nano, Hydrogène, Antimatière et Électricité.
Sur grand écran, les cinq ressources restent visibles ; sur format étroit, dont
la tablette de 768 px, la sélection conserve une lecture confortable. Stocks /
capacités, soldes nets horaires et commandes en file restent présents. Il ne
s’agit ni d’une nouvelle page métier ni d’un nouveau tri.

Renommer reste un dépliable dans la carte, avec nom prérempli et limite actuelle.
Bonus/malus et fiche des stocks/vaisseaux restent des informations complémentaires
accessibles au clavier et au toucher. Liens de gestion, sélection, bâtiments,
chantier, flottes et Vue générale conservés. L’abandon reste une page autonome :
conséquences, ressources, bâtiments, défenses, vaisseaux, fret et commandes annulées
visibles avant confirmation. Aucun nouveau formulaire de suppression ni modale
intermédiaire n’est ajouté.

**32 cadres** : colonies, renommage ouvert, comparaison, bilan d’abandon et bilan
bloqué à 1440/768/390 px pour A/B, plus deux planches d’états. Deux colonies et
nombres fictifs ; bandeau des nouvelles maquettes cohérent avec les stocks du
comparatif et du bilan. Illustration existante réutilisée sans modification.
Le bilan reprend l’absence de remboursement/transfert, la conservation des
recherches/historiques et l’absence de nouveau kit lors d’une recolonisation par
le même compte dans cet univers. Le nom retapé, la participation, la version,
le CSRF et la clé de requête restent existants.

Les états précisent : dernière colonie, opérations bloquantes sans détail privé,
commandes dépendant d’une autre colonie avec liens de gestion, nom incorrect,
bilan périmé, rejeu, stocks variables, capacité dépassée, ancienneté inconnue et
absence de colonie. Le service de bilan synchronise les files sous verrou avant
l’affichage : cet effet métier existant devra être préservé à la réalisation.
Aucun bilan réel ni abandon n’est exécuté pour dessiner les maquettes.

Structures grises sauvegardées avant la couleur ; variables propres au lot.
Vérification native des **32 cadres et 428 interactions** : destinations présentes,
aucun débordement de texte, chevauchement de texte ou texte coupé par son parent
détecté. Empreintes des vingt et une pages précédentes identiques, dont Vue générale.
Exports examinés : colonies A/B et comparaison A/B sur ordinateur, renommage et
abandon A sur mobile, comparaison A sur mobile et abandon bloqué A sur tablette.
Ces vérifications portent sur la conception, pas sur le fonctionnement du jeu.

Preuves dans le dossier de visualisation de cette mission :
`lot-7A-manifest.json`, `lot-7A-preservation-avant.json`,
`lot-7A-verification.json` et exports `lot-7A-*.png`. Document natif sauvegardé,
page A sélectionnée lors de cette étape de conception. Le choix et la réalisation
sont consignés ci-dessous.

### Réalisation L7A A — 18 novembre 2026

L’utilisateur retient explicitement A. La version native « L7A - A validee par
utilisateur » conserve ce choix. Réalisation limitée aux deux vues et à
`colony-workspace.css`, importé par l’entrée existante. Aucun contrôleur, service,
calcul de production, permission ou règle d’abandon ne change. La Vue générale
et le bandeau commun des ressources ne sont pas modifiés.

- `/planetes` : une colonie par ligne sur grand écran, avec illustration existante,
  identité/informations au centre et accès à droite ; une colonne sinon.
  Nom ouvrant le panneau de stocks/vaisseaux, renommage, bonus/malus,
  acquisition et raccourcis conservent leurs informations et destinations.
- `?view=table` : tableau sémantique, cinq ressources sur grand écran ; ressource
  sélectionnée sur format étroit, y compris 768 px. Paramètres GET, historique,
  rechargement et formulaire sans JavaScript conservés. L’ordre historique
  et l’avertissement de capacité dépassée restent ceux de l’existant.
- L’abandon conserve ses conséquences et pertes complètes : ressources, bâtiments,
  défenses, vaisseaux, fret et commandes. Confirmation à droite sur grand écran,
  sous le bilan sinon ; aucun formulaire quand un motif bloque l’abandon.
  Les quatre champs de protection et le nom attendu restent inchangés.

Les dimensions suivent le contenu réel plutôt que des hauteurs fixes de maquette.
Les libellés viennent du catalogue actuel. Les textes du comparatif se replient
sur petit écran ; couleurs et actions sont limitées au nouvel espace.

Vérifications locales réussies : `make validate`, `make lint-static` (201 Twig,
80 YAML, PHP et conteneur), `make assets`, syntaxe des deux tests navigateur et
`git diff --check`. **51 tests PHP / 417 assertions** : `ColonyAbandonmentTest`,
`AbsenceJournalTest` et `GameplayAccessTest`, sur une MariaDB privée jetable.
Dernier passage navigateur complet : **sept parcours réussis** avec
`colony-abandonment.spec.mjs` et `colony-workspace.spec.mjs`.

Recette : deux thèmes réels, quatre formats (1440/768/390/320), cartes, comparaison,
renommage, cinq ressources, capacité dépassée, noms longs, acquisition inconnue,
contenu des pertes, champs protégés, dernière colonie et absence de confirmation
bloquée. Clavier, toucher, focus, panneau des stocks/vaisseaux, bonus/malus,
historique/rechargement et formulaires sans JavaScript vérifiés. Abandon réussi,
nom incorrect, rejeu, refus sans révéler une sonde, dépendance d’une autre colonie
et deux abandons réellement simultanés conservent une colonie et une seule trace.
Axe porte sur les nouveaux espaces dans les deux thèmes ; le repli sans JavaScript
est vérifié nativement, sans y injecter Axe. Le test existant charge désormais la
vraie feuille du thème clair et contrôle aussi le panneau de confirmation.

**43 captures réelles** du dernier passage complet, dans
`captures-jeu-L7A-A/` du dossier de visualisation et, en copie ignorée,
`output/playwright/colony-workspace/captures-jeu-L7A-A/`. Examen visuel ciblé du
bureau, de la comparaison mobile et du bilan ; index JSON et journaux conservés.
L’option existante `data-page-sections="disabled"` évite un sommaire automatique
sur la liste ou le bilan, sans toucher au contrôleur partagé.

Le pool automatique Docker était saturé. Les mêmes contrôles utilisent un
complément Compose local ignoré, limité au réseau privé jetable
`10.240.18.0/24`, vérifié libre : aucune configuration globale ni aucun réseau
existant retiré. Le lanceur navigateur reprend le script du dépôt avec ce seul
complément. Aucun test ne vise la base habituelle. Aucune dépendance installée,
migration sur base existante ni action de jeu habituelle. Performance non mesurée ;
ces contrôles ne constituent pas un audit RGAA. CI, fusion et déploiement distincts.

## L7B — Développement, propositions préparées — 18 novembre 2026

L7A A est enregistrée dans `730b447`. Le lot suivant concerne Bâtiments,
Recherche, Chantier spatial, Défenses et Guide technologique, avec les fiches
d’information, les files et leur confirmation d’annulation. Conception et
réalisation **4/5** : familles/rangs, accès définitivement acquis, recherche commune,
quantités/capacités, actualisations et fenêtres de détail sur petit écran.
Sources examinées : vues et fragments des catalogues, files et guide, contrôleur,
préparation ciblée `ColonyView`, catégories `CatalogFilters`, contrôleurs de rangs,
actualisation et guide, définitions/illustrations des exemples et documentation
actuelle d’architecture. Aucun nouveau parcours métier n’est inventé.

Deux pages ajoutées au même document Figcraft bureau :

- **24 · L7B · A — Développement en lignes** : familles horizontales sur grand écran,
  illustration et informations rapprochées, activité de la colonie à droite.
  Guide avec détail latéral ; bilan d’annulation avec confirmation rapprochée.
- **25 · L7B · B — Développement en cartes** : quatre familles par ligne sur ordinateur,
  activité en bandeau à trois colonnes ; détail du guide et confirmation après
  leur contenu. Les deux options passent à une colonne sur tablette/mobile.

Les quatre catalogues gardent leurs URL avec `section` ; le guide garde
`/technologies`. La navigation secondaire mène à ces destinations existantes.
Les catégories et rangs filtrent un même catalogue ; les prérequis et gains restent
complémentaires et dépliables. Les fiches d’information gardent leur fenêtre,
et les détails du guide gardent leur présentation modale sur format étroit,
avec fermeture en tête. Aucun détail n’obtient une nouvelle URL dans ce lot.
L’annulation reste une page dédiée, avec dépendances, estimation à 25 % dans
la capacité disponible et nouvelle vérification serveur à la confirmation.

**50 cadres** : les quatre catalogues, guide fermé/ouvert, exemple de fiche
d’information et confirmation d’annulation, à 1440/768/390 px pour A/B ; plus deux
planches d’états. Illustrations et libellés issus du catalogue présent. Niveaux,
files, coûts et stocks illustratifs ; aucun calcul d’équilibrage validé par ces
maquettes. L’exemple détaillé de fiche porte sur Mine à ciel ouvert et celui du
guide sur Propulseurs vectoriels ; les cas complémentaires sont dirigés vers
la planche d’états, au lieu d’afficher les données d’un autre élément.

Les états couvrent éléments verrouillés, préférences de rangs, vue condensée,
ressources insuffisantes, file pleine, vacances, unités civiles et capacité,
quantité/Max, accès permanent, recherche commune, annulation dépendante,
filtres sans résultat, guide vide, objectif propre au navigateur et au contexte,
actualisation sans perte de saisie, clavier et repli sans JavaScript.
Les arrivées entrantes restent consultables même à zéro. Vue générale et bandeau
de ressources sont conservés. Les formulaires du prototype sont simulés.

Structures grises sauvegardées avant la couleur ; collection de variables et
composants propres au lot. Vérification initiale des **50 cadres et 1 118
interactions définies** : aucune destination manquante, aucun débordement,
chevauchement ou texte coupé détecté ; empreintes des **23 pages précédentes
identiques**. Revue visuelle A/B sur ordinateur, domaines du guide sur deux
colonnes au téléphone et fenêtres de détail avec fermeture en tête. Ajustements
limités à **14 nouveaux cadres**, revérifiés sans erreur ; liens des exemples
précisés sans changement de géométrie.

Preuves dans le dossier de visualisation : `lot-7B-manifest.json`,
`lot-7B-preservation-avant.json`, `lot-7B-verification.json`,
`lot-7B-verification-fenetres.json`, `lot-7B-verification-liens.json` et exports
`lot-7B-*.png`. Document natif sauvegardé. Conception uniquement ; le code du
jeu L7B attend le choix explicite entre A et B.

### Ajustement demandé : quatre cartes sur ordinateur — 18 novembre 2026

L’utilisateur demande quatre cartes par ligne sur ordinateur. La proposition B
est adaptée dans les quatre catalogues de la page 25 ; A, le guide, les fenêtres,
la confirmation et les formats tablette/mobile sont conservés. Complexité **3/5** :
densité des cartes, libellés longs, trois rangs et quantités à maintenir lisibles.
Quatre familles réelles illustrent chaque catalogue. Illustrations de 120 px,
titres de 18 px, textes principaux de 14 px, rangs abrégés comme dans le catalogue
et actions alignées en bas. Les prérequis des éléments verrouillés restent visibles ;
leur affichage est activé dans ces exemples. Le changement porte sur la composition,
sans nouvelle règle ni nouvelle action de jeu.

Vérification native des quatre vues à 1440 px : quatre cartes visibles, même ligne,
espacements vérifiés, aucun débordement, chevauchement ou texte coupé détecté.
Les **46 autres cadres L7B ont des empreintes identiques** avant/après. Les
anciennes cartes de ces quatre cadres sont conservées masquées pour le retour
arrière ; les nouveaux liens visent les états existants. Examen visuel de Bâtiments,
exports actualisés et document bureau sauvegardé sur B. Preuves :
`lot-7B-B-4cartes-preservation.json`, `lot-7B-B-4cartes-verification.json` et
`lot-7B-manifest.json`. Aucun code du jeu modifié ; le choix de la proposition
révisée reste attendu avant réalisation.

### Nouvelles propositions depuis B : usage fréquent — 18 novembre 2026

L’utilisateur demande deux nouvelles propositions depuis L7B-B à quatre cartes,
pour les améliorations répétées : information décisive directement lisible,
composition agréable et actions faciles à retrouver. La base B à quatre cartes
reste conservée. Les nouvelles propositions sont dans deux pages distinctes :

- **26 · L7B · A — Actions directes** : petite illustration en tête, gains et
  consommations mis en évidence, coûts avec stock disponible, actions alignées.
  Trois résumés de files au-dessus du catalogue, à garder accessibles au défilement.
  Filtres de catégorie visibles sur ordinateur.
- **27 · L7B · B — Lecture comparée** : petite illustration près du nom, gains
  directement lisibles, tableau Ressource / Coût / Stock identique entre cartes.
  Suivi des files en bas de l’espace pendant le défilement et filtre de catégorie
  compact. Il devra rester au-dessus de la navigation mobile sans couvrir de champ.

Les deux conservent **4 cartes à 1440 px, 2 à 768 px et une colonne au téléphone**.
Les détails des files s’ouvrent sur demande sans déplacer les cartes. Guide,
fiche d’information et confirmation d’annulation gardent la direction de la base B ;
les scénarios secondaires du nouveau prototype renvoient à leur planche d’états.
Les adresses actuelles, groupes, rangs, filtres et formulaires sont conservés.
Aucun moteur d’amélioration automatique ni achat groupé de niveaux n’est proposé.

Complexité de conception et de réalisation **4/5** : affichage des niveaux déjà
programmés, coût électrique ponctuel, gains/consommations, quantités, actualisation,
réponses de soumission et maintien du focus/contexte. Analyse ciblée complémentaire :
`ColonyView`, action d’ajout, `OperationCalculator`, `CatalogueInsights`, contexte de
production, files, contrôleur de quantité et aperçu serveur de construction.

Les intentions de confort à implémenter après choix sont :

- Afficher séparément niveau actuel, prochain niveau proposé et niveaux en file.
  Après ajout, le niveau réel ne monte pas immédiatement ; cible, coût et gain
  portent sur le prochain niveau après ceux déjà programmés.
- Montrer les coûts complets, dont l’électricité ponctuelle, les stocks et un manque
  chiffré. Séparer cette dépense de la consommation horaire supplémentaire du bâtiment.
  Garder gains du prochain niveau, durée de traitement et blocages directement visibles.
- Conserver la carte et la place du bouton après la réponse serveur, ainsi que filtre,
  rang, quantité et position de lecture. Retour du focus cohérent et confirmation
  accessible ; aucune réussite anticipée avant validation serveur.
- Préserver la clé de requête, empêcher une double soumission accidentelle, puis
  permettre un nouvel ajout volontaire. Refus de capacité, vacances, file pleine et
  prérequis restent ceux du serveur ; le manque n’est pas indiqué seulement par couleur.
- Pour les unités, distinguer possédé et quantité saisie ; montrer coût et durée du
  lot renvoyés par l’aperçu existant. Max reste calculé par le serveur. Le cadre de
  quantité montre une saisie de cinq chasseurs, sans prétendre que cinq est le maximum.
- Garder recherche commune, compteur de commandes sur 30, progression et fin de file,
  annulation dédiée, vols et arrivées même à zéro. Une alerte indispensable reste
  visible dans le contexte. Sans JavaScript, formulaires, redirection vers la carte,
  détails et confirmations conservent leur parcours.

**46 cadres** : quatre catalogues à trois formats pour chaque option, puis premier
et deuxième ajout, quantité saisie, manque de ressources et détail des files à
1440/390 px, plus deux planches d’états. Deux ajouts successifs montrent niveau
actuel 0, puis un et deux niveaux programmés, avec cible 2 puis 3. Les boutons
restent exactement aux mêmes coordonnées dans ces trois états, vérifiés pour A/B
sur ordinateur et téléphone. Il s’agit du comportement proposé, pas d’une fonction
déjà implémentée dans le jeu.

Les valeurs illustratives sont calculées par les services actuels : `GameCatalog`,
`OperationCalculator`, `CatalogueInsights` et `ResourceService`, sur des objets
synthétiques en position 3, sans événements/vacances et avec infrastructure de
base définie. Le contexte de production fourni évite toute lecture des effectifs.
**Aucune connexion de base ouverte**, vérifiée avant/après ; aucune commande de jeu,
migration, import ou notification. Le fichier `lot-7B-usage-donnees.json` conserve
ce contexte et les résultats, dont prix/durée des niveaux 1 à 3 et lot de cinq unités.
Ce ne sont pas les données d’un joueur ni une mesure de performance.

Vérification native des **46 cadres et 1 580 interactions définies** : destinations
présentes, aucun débordement ou texte coupé ; cinq libellés de recherche raccourcis
sans perte de sens, puis les trois vues concernées revérifiées sans chevauchement.
La grille est vérifiée dans l’état fermé et le panneau au premier plan dans les
états superposés. **25 pages précédentes identiques** avant/après création ; les
ajustements suivants ne concernent que les nouveaux cadres. Examen visuel des deux
compositions ordinateur et des cartes au téléphone. Les schémas gris sont
sauvegardés avant la couleur ; variables et composants sont propres à cette étape.

Preuves dans le dossier de visualisation : `lot-7B-usage-manifest.json`,
`lot-7B-usage-preservation.json`, `lot-7B-usage-verification.json`,
`lot-7B-usage-verification-textes.json`, exports `lot-7B-usage-*.png` et cartes
mobiles exportées séparément. Le code du jeu est inchangé. Le choix A/B reste
attendu avant réalisation ; tests du jeu, CI, fusion et déploiement restent distincts.

### Consolidation demandée : design actuel et Actions directes — 18 novembre 2026

L’utilisateur préfère les cartes actuelles des Bâtiments, de la Recherche, du
Chantier spatial et des Défenses. Il demande de les reprendre dans l’esprit de
L7B A « Actions directes ». Cette correction remplace le choix attendu entre les
pages 26/27 par une **version consolidée en page 28**, à valider avant intégration.
Les anciennes propositions sont conservées, sans approbation implicite.

Sources ciblées revérifiées : `building_cards.html.twig`, `building-catalog.css`,
`_catalog.html.twig`, bénéfices et sprite de pictogrammes. Les captures locales
existantes servent de référence visuelle ; la structure actuelle est confirmée
par les templates et styles. Complexité réévaluée à **4/5** pour la conception
et l’intégration : composants, illustrations, parcours du prototype, rangs,
files, quantités, actualisations et maintien du contexte.

Repères conservés : rangs en tête avec compte possédé et verrous, grandes images
16/9 existantes, titre centré, information à proximité, coûts avec pictogrammes,
surface sombre et action au texte doré. Quatre cartes sur ordinateur, deux sur
tablette, une sur téléphone. Le mode condensé existant masque les images tout en
gardant les informations décisives. Les logos et SVG d’identité sont inchangés.

Apports d’Actions directes : niveau réel distinct de la prochaine cible et des
niveaux programmés, gains/consommations ouverts, coût complet et stock disponible,
manque chiffré et prérequis près de l’action, boutons alignés et stables après deux
ajouts. Les trois files sont résumées au-dessus ; le détail s’ouvre sur demande.
Les catégories filtrent le même catalogue, les rangs restent des onglets de carte,
et les quatre tâches gardent leurs pages et URL. Fiche d’information et suivi des
files restent des fenêtres ; guide et annulation conservent leurs propositions
précédentes. Aucun changement métier ou automatisme supplémentaire.

**27 cadres** : quatre catalogues à 1440/768/390, premier/deuxième ajout, quantité,
manque, mode condensé, files et fiche d’information à 1440/390, plus une planche de
principes et états complémentaires. Données synthétiques reprises des résultats
déjà calculés par les services du jeu ; aucun nouvel accès à la base. Les réponses
du prototype sont simulées ; maintien du focus, validation serveur, accès,
annulations et repli sans JavaScript restent à vérifier lors de l’intégration.

Vérifications natives : 27 cadres, 4/2/1 colonnes, illustrations 16/9 sous les rangs,
absence de texte coupé ou de chevauchement et boutons stables sur ordinateur/mobile
dans les trois états d’ajout. Les 27 pages antérieures sont préservées ; comparaison
de leur structure, géométrie, textes, couleurs, typographie, composants et liens.
Les panneaux superposés sont contrôlés séparément du contenu qu’ils recouvrent.
Examen visuel des bâtiments, de la recherche et du chantier, dont une carte mobile.

Preuves : `lot-7B-base-manifest.json`, `lot-7B-base-preservation.json`,
`lot-7B-base-verification.json` et exports `lot-7B-base-*.png` dans le dossier de
visualisation. Maquette native sauvegardée. Le code du jeu est inchangé ; validation
de cette reprise attendue. Aucune CI, fusion ou mise en production revendiquée.

### Ajustements demandés : lignes, interrupteurs et suivi dépliable — 18 novembre 2026

La page **29 · L7B · Cartes et lignes · Suivi dépliable** consolide les dernières
corrections. Elle remplace la version à valider de la page 28 ; les versions
précédentes restent conservées. Le choix de cette reprise reste attendu avant code.
Complexité **4/5** : vues interchangeables, suivi fixe, fenêtres dépliables,
annulation et contexte de recherche/flottes, avec actualisation et focus à préserver.

- Retrait des « Dispo » des cartes : les stocks restent dans la barre de ressources.
  Coûts, gains, durée et raisons de refus restent lisibles ; le manque chiffré reste
  une exigence de l’intégration, sans ajouter un second affichage des stocks.
- Interrupteurs à piste/curseur pour les verrouillés et le mode condensé. Le
  condensé devient un vrai affichage en lignes, avec rang, niveau/quantité, gains,
  coût et action. Les cartes illustrées gardent quatre colonnes sur ordinateur.
  Le prototype représente le basculement avec une quantité de cinq conservée.
- Suivi des trois files sous les ressources pendant le défilement. Les vaisseaux
  et les défenses continuent de partager leur file actuelle. Le nombre de commandes
  sur 30 reste distinct du nombre d’unités. Recherche commune et colonie de lancement
  sont identifiées ; fin de file visible en tête de chaque panneau.
- Un clic ouvre le panneau correspondant, avec toutes ses entrées et « Annuler… ».
  Hauteur bornée et défilement interne pour conserver titre et fermeture accessibles.
  Le bilan d’annulation reste une page dédiée : dépendances, estimation de 25 % dans
  le stockage disponible et nouvelle validation serveur, sans nouvelle règle.
  Les exemples du premier niveau et du second ont des bilans distincts.
- Les flottes personnelles montrent état, route, échéance, composition et fret ;
  accès à leur gestion conservé. Les entrantes gardent les informations autorisées.
  L’exemple hostile n’affiche ni heure de départ ni progression inventée, et le
  signal d’impact reste visible quand le panneau est fermé.

Confort prévu à l’intégration : un seul panneau ouvert, fermeture par le déclencheur,
la commande de fermeture ou Échap, retour du focus, maintien du panneau et de sa
lecture pendant les actualisations. Le passage cartes/lignes doit conserver
filtre, rang, quantité et élément lu. Les formulaires, parcours sans JavaScript,
permissions, confirmations et protections de soumission restent ceux du jeu.

Sources ciblées revérifiées : `colony_queues.html.twig`, `flights.html.twig`,
`incoming_attacks_list.html.twig`, détails des flottes, confirmation d’annulation,
`CatalogFilters`, contrôleur du suivi et estimation du remboursement dans
`QueueService`. Les données synthétiques déjà calculées par les services sont
réutilisées ; aucun accès à une base ni aucune commande de jeu.

**53 cadres vérifiés** : quatre catalogues en cartes/lignes sur ordinateur/mobile,
exemples tablette, verrouillés masqués, quantité, panneaux de files/flottes,
bilans d’annulation distincts et états complémentaires. Deux aperçus natifs bornés
à 1440×900 et 390×844 placent le catalogue dans une zone défilante, tandis que les
ressources et le suivi restent à l’extérieur. Ils permettent d’essayer le suivi
fixe dans le prototype ; le CSS sticky du jeu reste à implémenter après validation.

Contrôle de la géométrie et des destinations : aucun débordement, texte coupé ou
chevauchement visible détecté. Les zones volontairement défilantes sont vérifiées
avec leurs limites de visibilité. Revue visuelle des cartes, lignes, interrupteurs
et panneaux mobiles. Aucun ancien composant ou variable partagé n’est modifié ;
les empreintes des pages de référence 1 et 28 sont identiques avant/après création.

Preuves : `lot-7B-pilotage-manifest.json`, `lot-7B-pilotage-preservation.json`,
`lot-7B-pilotage-verification.json` et exports `lot-7B-pilotage-*.png` dans le dossier
de visualisation. Maquette native sauvegardée ; code du jeu inchangé. Contrôles des
maquettes et du diff uniquement, sans CI, fusion ou déploiement revendiqué.

## Reprise et états de couverture

| Périmètre | État | Validation | Réalisation / vérifications | Limite restante |
| --- | --- | --- | --- | --- |
| Vue générale Figcraft | Conception de référence conservée | Choix explicite de l’utilisateur | Page inchangée, comparaison effectuée | Les autres écrans restent soumis à leur propre validation |
| Vue générale web | Finalisée et vérifiée localement | Demande explicite avant L2C | 28 PHP, huit parcours navigateur consolidés et dix captures ; bilan ci-dessus | CI, intégration et déploiement distincts |
| L1A | B implémentée et vérifiée localement | B choisie explicitement | Trois pages, PHP ciblé, recette responsive et captures ; bilan ci-dessus | CI complète, intégration et déploiement distincts |
| L1B | A implémentée et vérifiée localement | A choisie explicitement | Trois pages et brouillon, PHP ciblé, recette responsive et captures | CI complète et intégration distinctes |
| L1C | A implémentée et vérifiée localement | A choisie explicitement | Quatre pages, confirmations natives, 83 PHP et recette responsive ; captures | CI complète et intégration distinctes |
| L2A | A implémentée et vérifiée localement | A choisie explicitement | 59 tests PHP, 15 parcours navigateur consolidés et 40 captures ; bilan ci-dessus | CI complète et intégration distinctes |
| L2B | A implémentée et vérifiée localement | A choisie explicitement | 60 tests PHP, 14 parcours navigateur consolidés et 24 captures | CI complète et intégration distinctes |
| L2C | A implémentée et vérifiée localement | A choisie explicitement | 27 PHP, cinq parcours navigateur consolidés et 36 captures | CI, intégration et déploiement distincts |
| L3 | A implémentée et vérifiée localement | A choisie explicitement | 22 PHP, sept parcours navigateur et 50 captures | CI, intégration et déploiement distincts |
| L4 | A implémentée et vérifiée localement | A choisie explicitement | 34 PHP, six parcours navigateur consolidés et 52 captures | CI, intégration et déploiement distincts |
| L5 | A implémentée et vérifiée localement | A choisie explicitement | 44 PHP, trois parcours navigateur et 66 captures | CI, intégration et déploiement distincts |
| L6 | A implémentée et vérifiée localement | A choisie explicitement | 32 PHP, trois parcours navigateur et 66 captures | CI, intégration et déploiement distincts |
| L7A | A implémentée et vérifiée localement | A choisie explicitement ; Vue générale conservée | 51 PHP, sept parcours navigateur et 43 captures | CI, intégration et déploiement distincts |
| L7B | Cartes/lignes et suivi dépliable consolidés | Validation attendue, page 29 | 53 cadres vérifiés ; interrupteurs, files/flottes, bilans et deux aperçus défilables | Réalisation après validation |
| L7C à L11 | Inventorié | Aucune nouvelle | Non engagés pour cette refonte | Examiner chaque lot sans recommencer les navigations déjà livrées |
| L12 | Inventorié ; socle partiellement analysé | Aucune validation globale | Revue transversale non exécutée | Inventorié ne signifie pas vérifié |

Les commits restent sur `feature/refonte-visuelle` ; L1B A est enregistré dans
`1e390a9`, L1C A dans `1a33d14`, L2A A dans `ff6c324`, L2B A dans `88d68e3`.
La Vue générale est finalisée dans `6b3bb70`, avant L2C (compositions),
à la demande de l’utilisateur. L2C A est enregistré dans `da86685` ; L2 est donc complet localement.
L3 A est enregistré dans `16a9173`, réalisé et vérifié localement.
L4 A est enregistré dans `e13e588`, réalisé et vérifié localement.
L5 A est enregistré dans `9eb316e`, réalisé et vérifié localement.
L6 A est enregistré dans `811ef2d`, réalisé et vérifié localement.
L7A A est enregistrée dans `730b447` ; recette ci-dessus. Vue générale conservée.
Le plan reste à 18 ensembles : neuf sont réalisés, neuf restent à traiter.
Vue générale avait été finalisée en avance ; L7A est désormais complet localement. Les sous-lots ne sont pas comptés comme des ensembles supplémentaires.
Les validations précédentes restent limitées à leurs lots.
Aucun push, PR, fusion ou déploiement n’est autorisé par le seul changement de
branche. La mission globale reste non terminée.
