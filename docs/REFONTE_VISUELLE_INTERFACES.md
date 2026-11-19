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

Le tableau comporte **18 lots prévus** : treize ensembles réalisés localement
(L1A/L1B/L1C, L2, L3, L4, L5, L6, L7A, L7B, L7C, L7D et L7E), cinq restants. Certains ensembles complexes seront
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

### Complément : consommations négatives et aperçus des rangs — 18 novembre 2026

Complexité : **1/5** pour la présentation des consommations, **3/5** pour les
comparaisons de rangs (calculs réels, densité des ressources et contenus longs).

Les consommations horaires sont désormais en rouge et précédées de « − » dans
la version de la page 29, fiches comprises. Modification ciblée de 26 valeurs
sur 14 cadres : géométrie et 2 456 autres nœuds contrôlés identiques, dont les
productions et coûts. Les consommations changent de présentation, sans nouveau
calcul métier ; le coût électrique ponctuel reste distinct de la consommation horaire.

La demande suivante ajoute **8 planches comparatives en page 30**, pour juger
l’espace des ressources aux rangs intermédiaire et supérieur : bâtiments/recherche
puis chantier/défenses, en cartes et lignes sur ordinateur et téléphone. Il s’agit
de références de conception transversales, sans nouvelle page de jeu ni filtre
global de rang. Dans le jeu, le rang reste choisi sur chaque famille.

Les exemples montrent 3 à 4 ressources pour les bâtiments, 4 à 5 pour les recherches
et unités. Largeur de carte ordinateur : 274,5 px, soit les quatre colonnes actuelles.
Les cartes répartissent jusqu’à cinq ressources sur deux rangées, avec des montants
lisibles et des boutons alignés ; les lignes montrent la liste complète. Deux titres
longs sont ajustés sur téléphone. Les consommations restent rouges et négatives,
y compris la valeur fractionnaire de l’exemple supérieur (−445,5 électricité/h).

Libellés, illustrations et valeurs viennent des rangs réels du catalogue. Prix,
durées et bénéfices sont calculés par les services existants sur un contexte
synthétique documenté : position 3, prérequis représentés, recherche de niveau 5
vers 6 et bâtiment de niveau 0 vers 1. Connexion de base fermée avant/après,
aucune commande de jeu ni écriture métier. Ce ne sont pas les stocks d’un joueur.

Vérification ciblée : huit planches, 136 montants de ressources visibles et huit
consommations rouges négatives, aucune coupure ou collision détectée. Preuves :
`lot-7B-pilotage-consommations.json`, `lot-7B-rangs-donnees.json`,
`lot-7B-rangs-manifest.json`, `lot-7B-rangs-verification.json` et exports
`lot-7B-rangs-*.png`. Le modèle reste la page 29 ; la page 30 sert à son examen.
Le code du jeu est inchangé ; intégration après validation de la maquette.

### Correction du support : rangs dans les écrans de la page 29 — 18 novembre 2026

L’utilisateur précise que le support à modifier est **29 · L7B · Cartes et lignes ·
Suivi dépliable**, et non une planche séparée. La reprise part de l’état actuel du
document, après le retrait de pages par l’utilisateur. Aucune page n’est ajoutée
ou rétablie. Complexité **4/5** : adaptation des écrans, contrôle des ressources,
liaisons entre rangs et modes, avec préservation du travail présent.

**16 aperçus sont intégrés directement à la page 29** : Bâtiments et Recherche,
rangs intermédiaire/supérieur, cartes/lignes, ordinateur/téléphone. Ils reprennent
les cadres existants, avec leur menu, leurs ressources, les trois files, les flottes
et les interrupteurs. La première famille illustre le rang ; les autres familles
restent des repères de composition. Ce sont des états d’exemple, sans action de jeu.

Les coûts calculés précédemment sont présentés dans ces écrans, jusqu’à cinq
ressources. Les prix sont alignés comme dans le modèle, les consommations sont
rouges et négatives et le manque est indiqué par ressource, avec des groupes de
chiffres insécables. Le bouton est présenté indisponible lorsque les stocks fictifs
ne suffisent pas. Aucun calcul métier ou règle de validation du jeu n’est modifié.

Les contrôles Basique/Interm./Sup. de la première famille conduisent aux aperçus
correspondants. Deux menus de rang sont prévus pour les lignes sur téléphone ;
l’interrupteur Condensé conserve le rang montré. Les huit cadres sources gardent
leur apparence et leurs données : seules leurs destinations de rang évoluent.

Vérification ciblée des 16 aperçus : aucun débordement, chevauchement ou texte coupé
visible détecté ; coûts et appartenance à la page 29 contrôlés. Le document garde
les mêmes pages qu’avant l’intervention. Preuves : `lot-7B-rangs-page29-manifest.json`,
`lot-7B-rangs-page29-verification.json` et captures `lot-7B-page29-*.png`. La référence
courante est exclusivement la page 29 ; la comparaison précédente est écartée.
Le code du jeu est inchangé ; aucune CI, intégration ou publication revendiquée.

### Coûts sur cinq colonnes et manque dans le bouton — 18 novembre 2026

À la demande de l’utilisateur, les coûts occupent **au maximum cinq colonnes par
rangée** dans la page 29. Les rangs à quatre ou cinq ressources tiennent désormais
sur une seule rangée ; leurs cartes sont raccourcies en conséquence. Les quatre
cartes par ligne sur ordinateur sont conservées. Dans les lignes sur téléphone,
les caractéristiques passent sur une ou deux colonnes, puis les coûts prennent
toute la largeur. C’est une adaptation du mode d’affichage, sans nouvelle page.
Complexité **3/5** : adaptation responsive et préservation des états et liaisons.

Le bouton indisponible affiche **« Il manque : »**, suivi du nom complet et du
montant manquant de chaque ressource, une par ligne. Exemple supérieur de recherche :
976 Antimatière et 1 391 Hydrogène. Sa hauteur s’adapte au nombre de manques ;
l’ancien message extérieur est masqué. Les motifs de prérequis restent prioritaires
dans les états qui les présentent. Les libellés des coûts sont abrégés à quatre ou
cinq colonnes ; le détail du manque conserve les noms complets. Les consommations
horaires restent rouges et négatives, sans répétition des stocks dans les cartes.

Vérification ciblée des **44 écrans et 172 cartes/lignes** : 464 montants de coûts
inchangés, 16 boutons de manque dont quatre avec deux ressources, 40 consommations
rouges négatives et 1 540 interactions contrôlées. Aucun chevauchement ou débordement
de texte visible détecté ; lecture visuelle complémentaire sur ordinateur et
téléphone. Les pages, cadres, interrupteurs et zones de suivi fixe sont préservés.
31 captures sont actualisées. Preuves : `lot-7B-couts-page29-manifest.json` et
`lot-7B-couts-page29-verification.json`. Maquette enregistrée dans Figcraft ;
validation de l’utilisateur attendue avant réalisation dans le jeu.

### Réalisation L7B — page 29 validée, 18 novembre 2026

L’utilisateur valide la version consolidée et demande son intégration puis le lot
suivant. Réalisation **4/5** : présentation des quatre catalogues, formulaires de
quantité, trois files, vols/arrivées et actualisations partagées. La branche commune
est conservée conformément à sa décision, sans nouvelle récupération réseau.

`catalogue-workspace.css` raccorde les cartes et les vraies lignes au même HTML :
quatre colonnes sur ordinateur, deux sur tablette, une sur téléphone ; cinq coûts
maximum par rangée et nom complet/montant de chaque manque dans le bouton.
Les gains sont ouverts initialement ; niveaux, files, prérequis et informations
de rang restent disponibles. Les consommations sont rouges et négatives.

Les cinq cellules du bandeau restent sous les ressources à toutes les largeurs.
Elles ouvrent les listes complètes et les liens d’annulation déjà autorisés ; un
seul panneau est ouvert, Échap restitue le focus. Les fragments conservent panneau,
rang, mode, position de lecture et formulaires modifiés. Les détails de flottes et
les arrivées utilisent leurs préparations existantes, sans nouvelle information
privée. La Vue générale conserve son rendu. Le Guide et la confirmation d’annulation
sont examinés et conservés ; la navigation commune Développement rejoint le Guide.

Les quantités modifiées actualisent coûts et durée du lot depuis le serveur,
avec des réponses obsolètes ignorées et soumission suspendue pendant le devis.
Les champs historiques du devis restent inchangés ; trois champs de présentation
sont ajoutés pour afficher le prix d’une unité indisponible et ses manques.
Adaptation mineure de composition : les catégories existantes restent des boutons
de filtre, afin de conserver leurs accès et comportements.

Vérifications réalisées : validation Compose, lint PHP/YAML/Twig/conteneur, puis
lint ciblé des 13 gabarits après finalisation ; compilation des assets réussie.
**20 tests PHP / 2 298 assertions** ciblent maximums, gains par niveau et fiches.
**Six parcours navigateur distincts consolidés** vérifient les liens ciblés,
consommations, 156 fiches, gains avec files, responsive et actions du catalogue.
Les deux nouveaux parcours sont relancés avec succès après les corrections finales
de libellé accessible et de contraste clair. Formats 1440/768/390/320, thème clair,
contrôles Axe ciblés, devis ×5, cinq manques et maintien du suivi au défilement.
**30 captures réelles** sont conservées dans `captures-jeu-L7B-page29/` près du
document Figcraft et dans `output/playwright/catalogue-workspace/` (ignoré).

La commande habituelle de test navigateur a rencontré l’épuisement des sous-réseaux
Docker. La recette s’est exécutée avec le même script et un sous-réseau privé explicite,
dans un projet jetable ; aucun réseau existant n’a été supprimé. Aucune migration
sur la base habituelle, nouvelle dépendance, CI GitHub, fusion ou livraison.
Le contrôle ciblé ne constitue pas un audit RGAA complet. Le lot suivant est L7C :
cartes classique/V2 et simulateur, soumis à un nouveau choix A/B.

Commit de réalisation : `81807df`. Fichiers de ce lot :

- Présentation : `app/assets/app.js`, `app/assets/styles/catalogue-workspace.css`,
  `app/templates/colony/_catalog.html.twig`, `app/templates/colony/show.html.twig`,
  `app/templates/player/technology_tree.html.twig`, `app/templates/ui/building_cards.html.twig`,
  `app/templates/ui/catalogue_benefits.html.twig`, `app/templates/ui/colony_queues.html.twig`,
  `app/templates/ui/flights.html.twig`, `app/templates/ui/incoming_attacks.html.twig`
  et `app/templates/ui/development_navigation.html.twig`.
- Actualisation : contrôleurs `building_catalog`, `catalog_sticky`, `colony_live`,
  `construction_quantity` et `incoming_attacks` dans `app/assets/controllers/` ;
  `app/src/Service/Queue/QueueService.php` pour les données du devis.
- Régression : `app/tests/browser/catalogue-workspace.spec.mjs` et
  `app/tests/browser/catalogue-cleanup.spec.mjs` ; documentation dans `README.md`,
  `docs/ARCHITECTURE.md`, le présent suivi, la feuille de route et l’inventaire.

## L7C — Galaxie et simulateur, propositions préparées — 18 novembre 2026

Conception **4/5**, réalisation estimée **4/5**. Sources ciblées : routes et préparation
de carte dans `ColonyController`, cartes classique/V2, actions rapides et conquête,
contrôleurs de sélection/recherche/navigation, `CombatSimulationController`, formulaires,
résultats et détails de combat. Les lectures découvertes/propriétaire/univers, les
missions conditionnelles, la surveillance autorisée et les règles de simulation restent
à conserver. Aucun code de ces interfaces n’est modifié avant le nouveau choix.

Deux pages sont ajoutées au document Figcraft courant, sans rétablir les anciennes
pages retirées ni modifier la page 29 :

- **30 · L7C · A — Exploration cadrée** : commandes au-dessus, carte et fiche de
  sélection séparées sur ordinateur ; fiche sous la carte sur téléphone. Positions
  classiques en lignes et deux camps côte à côte dans le simulateur sur grand écran.
  Résultats en liste compacte. Bénéfice : lecture continue des informations et actions
  sans recouvrir la carte. Limite : surface visuelle de carte plus réduite.
- **31 · L7C · B — Carte immersive** : commandes et fiche ancrées dans une carte
  plus large, avec fiche basse sur téléphone. Positions classiques en cartes ; camps
  de simulation empilés et combats en cartes sur ordinateur. Bénéfice : place donnée
  à l’exploration et groupes plus larges. Limite : la fiche recouvre une partie de la
  scène et la préparation du simulateur demande davantage de défilement.

Chaque proposition comporte **25 cadres** : carte, système exploré, planète PNJ,
système inconnu, liste classique, préparation et résultats en 1440/768/390 ; détails
de combat et planche des états en ordinateur/téléphone. Les trois destinations restent
les vraies pages `/carte-v2`, `/carte` et `/simulateur`. La sélection et les fiches
restent des états d’une carte ; préparation et résultats restent le même formulaire
et sa réponse, sans nouvelle séparation par URL. Rechargement, retour arrière,
clavier, visibilité des découvertes et conservation des forces devront être vérifiés
dans le jeu après le choix.

Les contenus utilisent les champs existants : départ, coordonnées, type de cible,
PNJ/occupation, sondes/vitesse, hypothèses, recherches, défenses/forteresse, graine,
limite de 500 unités et série de dix combats. Les exemples géographiques sont fictifs.
Le résultat montré vient du moteur existant, exécuté hors base sur un scénario
synthétique : huit chasseurs basiques face à deux chasseurs intermédiaires arrivant
au round 2, un canon anti-léger et une forteresse de niveau 3 ; sept victoires et trois
défaites avec les graines 12345 à 12354. Pertes, survivants et rounds reprennent cette
sortie ; aucune moyenne de pertes ni probabilité garantie n’est ajoutée.

Recommandation : **A**, pour consulter les cibles et préparer une action avec des
informations stables. B reste une alternative donnant davantage de place à la scène.
Les deux réutilisent menu, ressources, identité et variables du document courant.
Une version native conserve les structures grises avant leur mise en couleur.

Vérification de conception : **50 cadres**, trois formats, **546 commandes reliées**
et leurs destinations réellement inspectées, huit fermetures par Échap et quatre
panneaux mobiles configurés pour défiler. Aucun débordement ou chevauchement de texte
visible détecté ; examen visuel des cartes A/B, de la fiche mobile, de la préparation
et des résultats. Les pages précédentes et les 71 cadres de la page 29 sont conservés.
Les 50 captures sont exportées et le fichier natif est enregistré.

Limites du prototype : carte schématique ; zoom, glisser, modes de carte et saisies
illustrés. Ils ne sont pas exécutés dans Figcraft : le moteur de carte actuel sera
conservé lors de l’intégration. Les actions simulées n’envoient aucune flotte. Ce
contrôle de conception ne valide ni les parcours du jeu L7C ni une conformité RGAA.
Preuves locales : `lot-7C-donnees.json`, `lot-7C-manifest.json`,
`lot-7C-verification.json` et `lot-7C-*.png`. **Choix utilisateur attendu.**

### Étude complémentaire — 80 systèmes et distances — 18 novembre 2026

Conception **3/5**. L’utilisateur souhaite garder sa galaxie et examiner une carte
immersive à 80 systèmes avant de choisir une direction. Cette demande ne valide
ni la proposition B ni sa réalisation dans le jeu.

La page Figcraft **32 · L7C · 80 systèmes — Galaxie et distances** contient onze
cadres : galaxie conservée, échelle actuelle et étude de spirale à l’échelle en
1440, 768 et 390 px, puis deux planches explicatives. Les pages précédentes sont
conservées. Les modes présentent les mêmes identifiants de systèmes et gardent
une sélection commune, plutôt que de séparer cette consultation en plusieurs pages.
La destination est désormais **vide par défaut** : pas de trajet de sélection ni
de fiche de distance avant un choix explicite. Deux mouvements fictifs, identifiés
comme tels, illustrent la consultation des flottes sans destination sélectionnée.

L’étude reprend les coordonnées des 80 systèmes de la galaxie locale existante,
par lecture seule, sans données de commandants. Le placement du contrôleur actuel
est conservé : huit bras de dix systèmes. Cette spirale est une disposition visuelle ;
ses écarts en pixels ne représentent pas les distances calculées depuis les coordonnées.
Les modes « Galaxie » et « Échelle actuelle » conservent donc les distances du jeu.
Le second retire le quadrillage visible et reprend un habillage spatial, sans déplacer
les marqueurs pour les faire artificiellement suivre les bras.

Exemple avec des positions planétaires illustratives : A:43:3 vers A:44:3 ou
A:53:3 donne **440 UA** dans les deux cas : sortie 70, hyperspace 300, entrée 70.
Les coordonnées des systèmes sont respectivement (6, 12), (9, 12) et (6, 15).
Le calcul de l’aperçu reprend `FleetNavigation::distances` ; aucune durée n’est
inventée sans flotte sélectionnée. Sur téléphone, les 80 marqueurs restent présents
et les libellés de la spirale sont allégés. Le zoom du moteur existant reste à préserver
lors d’une éventuelle intégration ; l’aperçu ne l’implémente pas.

À la demande de l’utilisateur, un troisième mode **« Spirale à l’échelle »** explore
une géographie différente : les distances hyperspatiales sont proportionnelles aux
écarts entre les points des bras, avec la même échelle sur les deux axes. L’exemple
utilise la courbe normalisée de la galaxie multipliée par 1 500 UA ; cette échelle
sert uniquement à la démonstration et ne constitue pas une décision d’équilibrage.
A:43:3 → A:44:3 vaut alors environ 292 UA et A:43:3 → A:53:3 environ 662 UA,
contre 440 UA chacun actuellement. Les 70 UA d’entrée et de sortie de l’exemple
s’ajoutent aux distances hyperspatiales. Adopter cette géographie modifierait les
distances, les durées et les consommations : cela nécessite une décision produit
distincte, sans réécriture ni migration implicite des systèmes existants.

La première proposition A (page 30) était une maquette de composition à cinq repères
placés manuellement, **pas une vue à l’échelle**. Le rendu de grille de la première
étude de page 32 utilisait bien les coordonnées actuelles à échelle uniforme.

Vérifications de conception : neuf cartes contenant chacune 80 marqueurs natifs,
38 liens de prototype et onze cadres contrôlés. Les 9 480 paires des trois cartes
métriques et 9 480 paires des trois spirales proposées confirment une échelle uniforme
pour leur géographie respective ; l’arrondi des marqueurs métriques produit un écart
maximal de 0,013 px. L’aperçu interactif est vérifié en 1024, 736, 390 et 320 px :
destination initiale vide, ancienne sélection d’exemple écartée, retour à « Aucune »,
mouvements visibles et distances cohérentes dans les trois modes. Aucune erreur de
script ni débordement horizontal constaté. Les libellés mobiles sont raccourcis après
contrôle visuel pour éviter leur coupure. Le document est enregistré sur la page 32.

Preuves locales : `lot-7C-80-manifest.json`, `galaxie-80-verification.json`,
`galaxie-80-v2-interactive-verification.json` et `genesis-galaxie-80.html`, dans le
dossier de conception courant. Aucun code du jeu, règle ou donnée existante n’est
modifié. **Direction de conception toujours à valider avant développement.**

### Exploration animée, repères et rapports de simulation — 18 novembre 2026

Conception interactive **4/5** : caméra, gestes tactiles, navigation à trois niveaux,
animations et états privés à préserver. L’utilisateur apprécie la spirale à l’échelle
et l’échelle actuelle, demande de conserver les vues système/planète du jeu et préfère
un bilan de simulateur comparable au rapport de combat. Cela ne vaut pas décision
d’appliquer la nouvelle géographie aux systèmes persistés.

La page Figcraft **33 · L7C · Exploration vivante et repères** ajoute 18 cadres :
spirale, échelle actuelle, système solaire, planète, signal inconnu et zoom sur la
colonie, chacun en 1440, 768 et 390 px. Les quatre détails de combat des pages 30/31
sont retravaillés, sans remplacer la préparation ni la synthèse du simulateur.
Les pages précédentes restent conservées ; 189 liens représentatifs et 21 survols
vers deux infobulles sont vérifiés.
Figcraft illustre des états de parcours : le zoom continu, le déplacement, les gestes
et les animations s’exécutent dans l’aperçu interactif, pas dans ses captures natives.

L’aperçu d’exploration propose les deux géographies appréciées. La caméra se déplace
par glissement et se zoome par boutons, molette centrée sur le pointeur et pincement.
« Vue entière » et « Ma colonie » permettent de retrouver ses repères. Les boutons
de caméra acceptent aussi les flèches du clavier ; le sélecteur donne accès aux
80 systèmes sans pointer de petites étoiles. Un clic ouvre un système connu ; un
clic sur une planète, ou son bouton dans la liste, ouvre le globe. Le fil de navigation
et Échap permettent de revenir, avec conservation du cadrage galactique et de la
rotation choisie pour la planète. La destination reste vide jusqu’à une action explicite.
Repérer un système inexploré vise le système sans inventer une position planétaire.

Les repères reprennent les quarts du moteur actuel : PNJ en haut à gauche, allié en
haut à droite, neutre/libre en bas à droite et ennemi en bas à gauche. Le cercle des
colonies personnelles et leur maison restent visibles. Les catégories se cumulent :
le système d’exemple A:43 contient une colonie personnelle, un allié et un ennemi.
Un signal inexploré n’a ni liste de planètes ni occupants préparés dans l’aperçu.
À la demande de l’utilisateur, les numéros de système quittent le fond de carte et
apparaissent au survol avec les renseignements connus. Sur téléphone, le toucher
ouvre la fiche ; le sélecteur conserve l’accès clavier à tous les identifiants.
Le « ? » est agrandi, jaune sur un disque noir opaque à contour fin. Une flèche
orientée remplace le point de chaque flotte et progresse dans le sens de son trajet.
Les identifiants et coordonnées proviennent de l’étude précédente ; les occupants
et deux mouvements sont fictifs et signalés comme tels, sans lecture de données privées.

Quatorze méthodes ciblées de `galaxy_v2_controller.js` sont réutilisées pour les
orbites, l’étoile, les disques de système, les palettes, textures procédurales, globes,
anneaux et lune décorative. Le contexte des fiches est amélioré autour de ce rendu.
Les étoiles, le noyau et les mouvements animent la galaxie sans déplacer les systèmes
ni modifier leurs distances. Les orbites et la rotation des globes restent animées.
Pause et reprise sont disponibles ; la préférence de mouvement réduit et l’onglet
masqué sont respectés. Les coordonnées orbitales, couleurs des mondes, anneaux et
lune restent des représentations décoratives, sans information de jeu supplémentaire.
Lors de l’intégration, conserver les actions et conditions existantes des fiches :
gestion, sondes, colonisation, conquête, renfort et contestation, selon les droits.

Le nouvel aperçu du simulateur conserve les dix résultats déjà calculés par le moteur.
Chaque combat affiche deux camps distincts, puis une ligne par type et rang d’unité :
**Engagés / Pertes / Survivants**, avec arrivée R1 ou R2 explicite. Les pertes sont
rouges et négatives ; les survivants sont comptés comme unités. Les points de structure
restants sont disponibles dans un détail secondaire, sans se substituer à ce nombre.
Les rounds restent consultables et leurs tirs sont présentés comme totaux des deux
camps : aucune statistique par camp absente des données n’est inventée. Les données
source vérifient les 30 lignes des dix combats : engagés = pertes + survivants, avec
concordance des pertes brutes du moteur. Aucune perte moyenne, reconstruction ou butin
simulé n’est ajouté.

Vérifications ciblées : aperçus en 1024, 736, 390 et 320 px ; clic réel sur un système
et un globe, navigation aller-retour, zoom, molette, glissement, pincement tactile,
conservation de rotation, choix et effacement de destination, signal inconnu sans
occupants, animation perceptible et image figée en pause. Dix combats contrôlés aux
quatre largeurs, détails des rounds et structures accessibles ; aucun débordement
horizontal ni erreur de script constaté. Les 24 cadres natifs concernés et leurs liens
sont contrôlés ; vérification visuelle des systèmes, globes et bilans sur téléphone.
Il s’agit de vérifications de conception, pas d’une recette du jeu ni d’un audit RGAA.

Preuves dans le dossier de conception courant : `genesis-galaxie-interactive.html`,
`genesis-simulateur-rapport.html`, `galaxie-interactive-reemploi.json`,
`exploration-interactive-verification.json`, `exploration-scenes.json`,
`simulateur-rapport-verification.json`, `lot-7C-interactif-manifest.json` et
`exploration-native-verification.json`. Aucun fichier de code du jeu, donnée de base,
dépendance ou règle d’équilibrage n’est modifié. Réalisation L7C, CI, fusion et
déploiement restent à effectuer après validation de la conception et des distances.

## L7C — réalisation locale validée — 18 novembre 2026

Complexité **4/5** : la décision explicite sur les distances touche les devis,
rappels et replis, en plus du rendu interactif. La spirale fixe une seule
géographie serveur ; la seconde disposition s’appelle **Grille de repérage**.
La préférence locale est séparée par participation. Les identifiants SQL et les
routes publiques restent conservés ; aucune migration de base existante.

`GalaxyGeometry` centralise la projection. `MovementCalculator` l’utilise pour
les nouveaux devis et l’information jusqu’à l’entrée d’un système. Le déplacement
local, les vitesses et coefficients de carburant restent conservés. Les anciens
vols gardent leurs secondes et carburants figés ; un nouveau repli utilise le point
physique atteint, interpolé entre les extrémités. Un ancien point intermédiaire
sans géométrie identifiable est refusé pour ne pas produire de devis trompeur.

La carte reprend les astres, textures, orbites et animations existants. Commandes
et fiches sortent du canvas pour rester lisibles dans le flux de la page. Recherche
et liste restent les accès clavier ; zoom, déplacement, pincement, retour système,
rotation du globe, pause, cadrage global et recentrage sont conservés ou ajoutés.
Les 80 systèmes réels sont affichés sans couper les suivants sur une carte plus
grande. La maison personnelle est permanente ; les quarts de relation restent
cumulables. Numéros au survol/focus, inconnus sur disque noir et flèches de flotte
orientées. Les renseignements inconnus restent filtrés avant envoi au navigateur.
La destination n’est pas sélectionnée automatiquement.

Le simulateur garde sa préparation et ses dix résultats reproductibles.
`CombatSimulationView` compte les unités restantes par camp, type et rang, sans
confondre structure et effectifs. Les bilans affichent **Engagés / Pertes /
Survivants**, pertes rouges négatives et arrivée explicite. Les rounds et points
de structure restent dans des détails secondaires. Les tables deviennent des
lignes lisibles sur téléphone, sans modifier les calculs ni la validation serveur.

Vérifications : **126 tests PHP et 4 375 753 assertions**, couvrant géographie, carburant, navigation,
simulation, rappels, stationnement et départ d’orbite ; cinq parcours navigateur
consolidés sur base temporaire. Carte à 80 systèmes en 1440, 768, 390 et 320 px,
persistance des deux vues, confidentialité, absence de départ involontaire,
navigation carte/système/globe, rotation, pincement, retour/focus et mouvement
réduit. Recette du simulateur avec conservation des effectifs, thèmes clair/sombre,
reproductibilité et parcours sans JavaScript ; rappel confirmé sans double effet.
Contrôle axe ciblé du nouvel espace carte sans anomalie sérieuse/critique, sans
revendiquer un audit RGAA. Compilation, validation Compose, lints PHP/Twig/YAML/
conteneur et `git diff --check` réussis. Aucun test sur la base habituelle.

Le premier passage a révélé une mini-carte dessinée alors que masquée et un nom
accessible différent du texte Pause/Animer ; corrigés et parcours concernés
relancés. Les essais de clic ont été adaptés au canvas désormais dans une page
défilante. L’instance habituelle de base de test étant indisponible et les plages
Docker automatiques occupées, les contrôles ont utilisé des projets jetables et
leurs propres réseaux ; aucun changement de configuration globale.

Preuves locales : `output/playwright/catalogue-workspace/l7c-map-verification.log`,
`l7c-final-browser.log`, `l7c-verification.log`, `l7c-lint.log`, `l7c-assets.log`,
captures de `recette-finale-l7c/` et `app/test-results/`. Les échecs intermédiaires
restent dans les journaux ; le résultat final fait foi pour les parcours corrigés.
CI GitHub, PR, fusion et déploiement ne sont pas exécutés.

Fichiers du lot : `app/assets/app.js`, `app/assets/controllers/galaxy_v2_controller.js`,
`app/assets/styles/galaxy-workspace.css`, `app/assets/styles/combat-simulation.css`,
`app/src/Controller/ColonyController.php`, `CombatSimulationController.php`,
`app/src/Service/Fleet/GalaxyGeometry.php`, `MovementCalculator.php`,
`FleetNavigation.php`, `app/src/Service/Combat/CombatSimulationView.php`,
`app/templates/universe/map_v2.html.twig`, `app/templates/combat/simulation.html.twig`,
`_simulation_trial.html.twig`, les tests PHP de géographie/mouvement/carburant/
navigation/bilan, les scénarios navigateur carte/simulateur et la documentation
README, architecture, décisions, règles, durées/carburant, inventaire et feuille de route.

## L7D — premières propositions refusées — 18 novembre 2026

**État historique : l’utilisateur refuse ces deux propositions. Elles sont remplacées
par la reprise ci-dessous dans les mêmes pages 34/35.**

Complexité **3/5 en conception, 4/5 à l’implémentation**. Lecture ciblée des trois
contrôleurs de rapports, de `ReportArchiveService` et des gabarits liste, combat,
espionnage, transport et retour. Les droits, archives personnelles, pagination
de 50 rapports, filtres et redirections restent les références fonctionnelles.

Le fichier Figcraft de bureau courant contient deux nouvelles pages :

- **34 · L7D · A — Lecture directe** : rapports en lignes sur ordinateur,
  type/date/cible/verdict repérables, archivage en bout de ligne ; détails de combat
  organisés autour des effectifs avant le butin.
- **35 · L7D · B — Journal de missions** : cartes sur deux colonnes à grande largeur,
  statut et commandes Ouvrir/Archiver distincts ; bilan de mission et butin avant
  les effectifs. Sur téléphone, les deux propositions passent sur une colonne.

Les propositions gardent le menu, le logo et la barre de ressources validés.
Actifs/Archives sont deux vues proches de la même liste, les types sont des filtres,
et chaque détail conserve sa vraie page et son lien de retour avec contexte.
Effectifs/Rounds/Reconstruction sont des ancres du même rapport de combat : les
cadres séparés illustrent ses sections, sans proposer de nouvelles routes.
Les compléments de recherche peuvent se déplier sur téléphone ; les informations
nécessaires au verdict restent visibles. Aucune nouvelle statistique non lue
par les services actuels n’est requise.

Couverture commune : sélection individuelle/collective, archivage/restauration
personnels, pagination, recherche, filtres, résultat vide, période invalide,
sélection absente et rapport inaccessible. Combat : camps, rangs, arrivée,
engagés/pertes/survivants, rounds, reconstruction et occupation conditionnelles.
Espionnage : relevé daté, catégories obtenues, catégories inconnues distinctes de
zéro, détection sans renseignement et reprise dans le simulateur. Transport :
expéditeur/destinataire, trajet, envoyé/livré/retour prévu, dépôt et pertes au
retour seulement quand le snapshot les fournit.

Les chiffres et noms des rapports sont fictifs. Les exemples de combat ne sont
pas des rapports privés extraits de la partie. Les commandes de prototype relient
les états illustrés ; filtres, saisies, changement de round et sélection ne
constituent pas un moteur fonctionnel de recherche dans Figcraft. Leur validation
réelle interviendra pendant l’implémentation de la proposition choisie.

Vérifications de conception : **44 cadres**, ordinateur 1440, tablette 768 et
téléphone 390 px ; détails des rounds et planches d’états en 1440/390.
**382 commandes reliées**, destinations vérifiées, aucune collision de texte ou
sortie de cadre détectée après ajustement des colonnes et titres. Examen visuel
d’exports de listes A/B et de détails sur téléphone. Les 28 pages précédentes,
dont Vue générale et page 29, restent conservées. Les cibles dessinées mesurent
au moins 44 px ; les textes, variables et contrastes réutilisent la référence.
Ces contrôles ne constituent ni une recette du jeu ni un audit RGAA.

Preuves locales dans le dossier de conception indiqué au début :
`lot-7D-manifest.json`, `lot-7D-arbres.json`, `lot-7D-verification.json` et exports
`lot-7D-A-*.png` / `lot-7D-B-*.png`. Le document natif est enregistré et ouvert
sur la proposition A. Aucun écran du lot L7D n’est encore implémenté ; choix
utilisateur attendu. Le lot L7C est enregistré dans `50f3965` ; la branche reste
`feature/refonte-visuelle`, sans push ni déploiement.

## L7D — reprise depuis les rapports actuels — 18 novembre 2026

L’utilisateur ne retient aucune des premières propositions et demande de repartir
des rapports actuels et de l’UI/UX déjà réalisée. La conception reprend donc les
gabarits et styles réels : `combat/reports`, `combat/report`, `espionage/report`,
`transport/_details`, `mission-reports.css` et `report-archives.css`. Les captures
de recette existantes sont des références visuelles historiques, confrontées aux
sources actuelles, et non de nouvelles captures du jeu réalisées pour ce lot.
Complexité de conception : **3/5**, sans modification des données ni du jeu.

Les deux pages sont **remplacées sur place**, après enregistrement d’une version
de l’état refusé ; aucune autre page n’est remplacée. Le menu, les ressources et
les variables des écrans validés sont repris. Les nouveaux choix sont :

- **34 · L7D · A — Rapport actuel affiné** : bilan global en tableaux, lignes par
  flotte/unité/rang, camps séparés par des titres explicites, comparaison VS des
  statistiques à chaque round. Le centre des rapports reste une liste compacte.
- **35 · L7D · B — Camps en vis-à-vis** : mêmes informations, comparaison des camps
  dès le bilan des pertes, dans l’esprit du simulateur validé ; sur téléphone les
  camps se suivent. Le centre conserve aussi les lignes, avec repère de focus.

Les deux directions conservent le détail utile du rapport actuel : engagés,
pertes, survivants, arrivée, puis début/pertes/fin, tirs, touchés, ratés et précision
pour chaque unité du round. L’absence de tir affiche une précision indéterminée,
distincte de 0 %. Les pertes sont rouges et négatives ; les zéros restent neutres.
Sur téléphone, identité/rang/flotte précèdent trois effectifs, puis les statistiques
de tirs : les valeurs ne sont plus cachées dans la largeur d’un tableau.
Le premier round est ouvert ; les autres sont illustrés comme dépliables, avec
accès à leur détail complet. Résumé, effectifs, rounds et reconstruction restent
des ancres d’un même rapport ; les cadres de détail ne créent pas de routes.

Le centre reprend les filtres de type et les vrais champs planète, participant,
du/au. Les états Combats/Économie/Espionnage sont distincts dans le prototype,
sans envoyer le filtre vers un rapport de détail. Sélection, archives et restauration
restent personnels. Les aides sont limitées aux informations utiles ; les commandes
secondaires ne prennent plus toutes la largeur de l’écran sur ordinateur.

Espionnage A reprend les cartes de ressources et catégories actuelles ; B les
présente en relevé compact. Stock, production, capacité, rangs et niveaux restent
distincts ; catégories absentes et catégories vides ne sont pas confondues.
Le cas détecté ne révèle aucun renseignement. Transport conserve envoyé, livré,
retour prévu, déposé et perdu au retour, avec un trajet plus visuel dans B.

L’exemple de combat est calculé **sans base** par `CombatSimulator`, puis présenté
par `CombatReportPresenter` : 8 rounds, 9 unités de votre camp, 1 perte et
8 survivants ; le renfort adverse commence au round 2 et le colonisateur sans tir
reste distingué. Les contrôles vérifient début − pertes = fin et tirs = touchés +
ratés pour chaque ligne. Butin, état d’occupation, renseignements et transport
sont des illustrations synthétiques supplémentaires, non des résultats du moteur
de simulation et non des rapports privés extraits de la partie.

Vérifications de conception : **62 cadres**, **578 commandes reliées**, trois
formats pour les pages principales (1440, 768, 390) et états complémentaires en
1440/390. Vérification des destinations, des ancres, des limites et des collisions
de texte ; contrôle visuel des rapports et listes A/B sur ordinateur/téléphone.
Les ajustements portent notamment sur les colonnes de tirs, les libellés courts
et le pied du menu dans les petits cadres. Les autres pages Figcraft sont conservées.
Ces vérifications concernent les maquettes, pas une recette du jeu ni un audit RGAA.
Les champs et actions de Figcraft relient des états illustrés ; ils n’exécutent
aucune recherche, écriture, mission ou simulation dans la partie.

Preuves dans le dossier de conception courant : `lot-7D-reprise-donnees.json`,
`lot-7D-reprise-manifest.json`, `lot-7D-reprise-arbres.json`,
`lot-7D-reprise-verification.json` et `lot-7D-reprise-A-*.png` / `B-*.png`.
Le fichier natif reste le même et s’ouvre sur le nouveau rapport A de la page 34.
Les anciennes propositions restent dans l’historique enregistré.
Fichiers versionnés de ce lot : ce suivi, `FEUILLE_DE_ROUTE.md` et
`INVENTAIRE_INTERFACES.md`. Aucun template, style ou traitement du jeu n’est modifié.
Choix A/B attendu avant implémentation ; sept lots restent à réaliser.

## L7D — réalisation locale A — 18 novembre 2026

**Choix utilisateur : A**, puis demande explicite d’intégrer le lot et de passer
au suivant. Complexité **4/5** : gabarits communs à la surveillance administrative,
variantes historiques, tableaux responsives et préservation des droits de lecture.

Le rapport de combat garde sa vraie page. Le bilan du camp regroupe les unités
enregistrées, y compris les renforts, avant reconstruction. Les tableaux restent
séparés par camp avec flotte, unité, rang, effectifs et arrivée. Le premier round
est ouvert ; chaque autre round est dépliable sans JavaScript. Les huit champs
du détail restent accessibles sur téléphone, sans défilement horizontal imposé.
Les ancres parcourent le même rapport. Butin, reconstruction et tous les états
de conquête existants restent présents ; les pertes sont rouges et négatives.

Le centre conserve type, critères, archives et page dans les liens. Les filtres
occupent une barre plus compacte. La sélection de la page gère le tout/partiel
et son compteur ; le serveur conserve ses contrôles et sa limite de 50 rapports.
Sans JavaScript, cases individuelles, archivage et restauration restent disponibles.
Le lien Réinitialiser est corrigé : les critères étaient réinjectés dans son URL ;
il les efface maintenant en gardant type et vue active/archives.

Les renseignements obtenus sont ouverts et repliables par catégorie ; catégories
inconnues et catégories connues vides restent distinguées. Stocks, taux, capacités,
niveaux et anciens formats gardent leurs données. Un rapport détecté ne révèle
aucun renseignement. Les transports conservent envoyé, livré, retour prévu,
déposé et perdu au retour, avec affichage des deux derniers uniquement après retour.
Les deux décimales des ressources et les valeurs exactes des quantités sont conservées.

Vérifications : **15 tests PHP / 98 assertions**, comprenant le présentateur et
les archives (droits, univers, contexte de navigation, lecture des résumés sans
hydrater les preuves complètes). **Cinq parcours navigateur consolidés** : archives
personnelles et sélection au clavier ; renseignements, rangs, format historique,
inconnus et détection ; conquête réelle en base temporaire et refus à un autre
commandant ; bilan historique et comparaison détaillée des camps ; transport,
retour filtré, effacement des critères, thèmes clair/sombre et lecture sans JavaScript.
Les rapports de combat et de transport sont capturés en 1440/768/390/320 px,
sans débordement. Contrôles axe ciblés du centre d’archives et du transport sans
anomalie sérieuse/critique ; aucun audit RGAA complet revendiqué.

`make validate`, `make lint` (PHP, Twig, YAML, conteneur et schéma), `make assets`
et `git diff --check` réussissent. Les essais modifiant l’état utilisent uniquement
des projets et bases jetables. Le test SQL des résumés exige le mode debug : il a
été relancé avec APP_DEBUG=1 dans son conteneur temporaire, sans configuration globale.
Les vérifications initiales ont révélé une largeur minimale de cellule héritée
et le style flex des anciennes listes de définitions sur les grands nombres ;
les corrections sont isolées au nouvel espace. Les trois parcours touchés ont été
relancés après correction, sans affaiblir les contrôles de largeur ou de contenu.

Fichiers : `CombatReportPresenter.php`, `combat/report.html.twig` et ses trois
fragments `_report_units`, `_report_round`, `_report_reconstruction`,
`combat/reports.html.twig`, `espionage/report.html.twig`, `transport/report.html.twig`
et `_details.html.twig`, `report_selection_controller.js`, `report-workspace.css`,
`assets/app.js`, les cinq fichiers de tests concernés, README, architecture,
feuille de route, inventaire et présent suivi. Aucun contrôleur d’action, règle
de jeu, table, permission ou dépendance n’est modifié.

Preuves locales : `output/playwright/rapports-refonte/`, notamment
`l7d-combat-verification.log` (15 PHP), `l7d-recette-validee.log` (trois parcours
de tableaux après corrections), `l7d-verification.log` (archives/espionnage),
`l7d-lint.log`, `l7d-assets.log` et captures `recette-validee/` / `recette/`.
Les échecs intermédiaires restent identifiés dans leurs journaux ; ils ne sont pas
présentés comme des contrôles réussis. CI GitHub, fusion et déploiement restent distincts.

## L7E — Messages, classements et profils, propositions préparées — 18 novembre 2026

Complexité **4/5** : canaux, non-lus, contenus privés, composition et signalements,
scores quotidiens et données publiques. Lecture ciblée de `SocialMessageController`,
`MessageSearchController`, `LeaderboardController`, `PublicProfileController`,
`PlayerReportController`, des gabarits associés et du contrôleur de rédaction.
Les profils de guilde déjà traités dans L1B sont réutilisés, sans nouvelle refonte.

Les deux nouvelles pages du fichier Figcraft courant sont :

- **36 · L7E · A — Échanges et tableaux** : conversations visibles à gauche sur
  ordinateur, échange à droite ; classement en tableau à grande largeur et lignes
  adaptées au téléphone ; profil public en relevé de scores. Continuité avec les
  rapports A et les listes déjà validées.
- **37 · L7E · B — Conversation et lecture** : échange au premier plan avec retour
  identifié vers les conversations ; classements et profil regroupés dans des
  encadrés donnant davantage de place aux informations détaillées.

Le menu, les ressources, les couleurs et la typographie reprennent le socle validé.
Privé/Guilde/Global restent les contextes GET de la même messagerie ; la liste et
l’échange sont deux états d’un même parcours. Recherche des messages, profil public,
classement et signalement d’un commandant gardent leurs pages dédiées et leur
contexte de retour. Joueurs/Guildes sont les vues du classement ; catégorie et tri
sélectionnent le même ensemble quotidien. Signalement d’un message et aide Markdown
sont des compléments secondaires. Les cadres du prototype ne créent pas de routes.

Couverture commune : boîte vide, sélection de conversation, recherche d’un
destinataire, privé, guilde, Global, composition, recherche dans les messages et
résultats, profil public, classements joueurs/guildes, choix des cinq catégories
et de l’ordre, actions par commandant et signalements. Les actions de contact et
signalement ne sont pas proposées sur sa propre ligne. Les non-lus restent liés
aux échanges privés et de guilde ; Global ne reçoit ni notification ni compteur.
La recherche ne marque pas lu ; ouvrir la conversation conserve son comportement.

Les champs reprennent les limites actuelles : message 2 000 caractères, motif d’un
message 500, commentaire d’un signalement de joueur 1 000. Ctrl + Entrée et Markdown
sans images restent identifiés. Les états couvrent absence de guilde, empire non
piloté, contenu modéré, recherche vide, envoi refusé et profil inaccessible.
La conservation de saisie/focus lors des actualisations devra être vérifiée à
l’implémentation ; les redirections d’erreur existantes de la messagerie demandent
une attention particulière. Aucune garantie de brouillon persistant n’est ajoutée.

Les profils ne montrent que les données publiques déjà préparées : pseudonyme,
guilde accessible, cinq scores et rangs, date du relevé. Les points de flotte
incluent les défenses fixes. Pas de stock, composition de flotte ou position privée
ajoutés. Les exemples de commandants, messages, dates et scores sont fictifs ;
les boutons d’envoi et de signalement relient des états dessinés, sans aucun envoi réel.

Vérifications de conception : **66 cadres**, **403 commandes reliées**, formats
1440/768/390 pour les cinq vues principales ; états complémentaires en 1440/390
ou panneaux compacts. Destinations contrôlées, profils de guilde L1B reliés,
couverture A/B équivalente, aucun dépassement ou chevauchement de texte détecté.
Examen d’exports des conversations, classements et profils sur ordinateur/mobile.
Les pages précédentes restent conservées, y compris la page 34 A validée.
Ces contrôles ne constituent ni une recette du jeu ni un audit RGAA complet.

Les listes du prototype utilisent quelques exemples, sans moteur réel de recherche,
de tri ou de pagination ; certaines options utilisent un panneau compact illustratif.
Le raccord aux identifiants de chaque commandant et la conservation du contexte
seront vérifiés pendant le développement. Les jetons, permissions, modération,
idempotence, actualisation des non-lus et calculs de classement restent à préserver.

Preuves locales : `lot-7E-manifest.json`, `lot-7E-arbres.json`,
`lot-7E-verification.json` et exports `lot-7E-A-*.png` / `B-*.png` dans le dossier
de conception courant. Fichier natif enregistré et ouvert sur la page 36 A.
Cette première version attendait un choix avant implémentation. La direction A
et les demandes de reprise ultérieures sont consignées ci-dessous. Le code du jeu
reste celui du lot L7D ; six lots restent à réaliser sur les dix-huit prévus.

## L7E — A retenue, messagerie et classements repris depuis l’existant — 18 novembre 2026

L’utilisateur retient A, mais demande de conserver les classements joueurs/guildes,
puis le design de sa messagerie. La page **36 A** est ajustée dans ce sens ;
il ne s’agit pas d’un nouveau choix A/B. L’utilisateur valide ensuite explicitement
« 36.L7E-A » et autorise son intégration locale. La page 37 B et les lots précédents
sont conservés.

Complexité **3/5** pour cette reprise de conception : examen ciblé des gabarits
`player/leaderboard.html.twig`, `player/messages.html.twig`,
`player/_message_contacts.html.twig`, des styles de classement/messagerie et du
contrôleur de classement. Aucun traitement de jeu ni accès aux données n’est modifié.

- **Classement joueurs** : tableau actuel conservé, avec les cinq catégories,
  les évolutions de place et de points, les repères de relation, la protection et
  les actions de contact/signalement. Sur téléphone, Rang/Commandant/Total/Actions
  restent visibles ; les autres catégories sont dépliables dans la ligne.
- **Classement guildes** : toutes les colonnes sont conservées, dont les effectifs,
  scores par catégorie, total, évolutions et médiane des points des joueurs.
  Le tableau défile horizontalement sur les petites largeurs ; un état supplémentaire
  permet aussi d’examiner les dernières colonnes dans le prototype.
- **Messagerie** : panneaux contacts/conversation bordés, recherche de joueur,
  avatar et titre de l’échange, bulles reçues bleues et envoyées brunes/dorées,
  repères dorés de non-lus et bouton d’envoi doré repris du design actuel.
  Les métadonnées « Conversation privée », le canal de guilde et Global sont
  conservés, sans inventer de présence ni d’accusé de lecture.
- **Lecture et rédaction** : historique défilant, zone de saisie et bouton côte
  à côte, raccourci Ctrl + Entrée, aide Markdown et signalement des messages reçus.
  Les espacements et libellés sont ajustés sur téléphone.

Les conversations restent les états d’une même messagerie ; recherche de messages,
classements, profils et signalements de joueur gardent leur rôle de pages dédiées.
Les détails de score sur téléphone et l’aide restent des contenus dépliables.
L’actualisation illustrée du classement est corrigée à **00 h, heure de Paris**,
conformément au comportement actuel, sans changement de cadence métier.

Vérifications : **25 cadres A repris**, soit **70 cadres** pour L7E au total
(37 A et 33 B), **499 commandes reliées** et destinations contrôlées. Les tableaux
joueurs/guildes disposent chacun des formats 1440/768/390. Aucun dépassement de texte
ou chevauchement détecté dans les cadres repris, en tenant compte des zones défilantes.
Examen visuel des exports messagerie et classements sur ordinateur/téléphone ;
libellé d’envoi et emplacement de la protection corrigés sur téléphone.
Ces contrôles portent sur les maquettes ; aucune recette du jeu ni conformité RGAA
globale n’est annoncée. Aucun message envoyé et aucun code applicatif modifié.

Preuves dans le dossier de conception courant : `lot-7E-manifest.json`,
`lot-7E-classements-manifest.json`, `lot-7E-messagerie-manifest.json`,
`lot-7E-reprise-verification.json`, `lot-7E-reprise-arbres.json` et exports
`lot-7E-reprise-A-*.png`. Les captures historiques de la messagerie ont servi de
repères visuels, complétés par la lecture des styles actuels ; elles ne sont pas
présentées comme une nouvelle recette du jeu. Fichier natif enregistré sur la page 36 A.

## L7E — Réalisation de la page 36 A validée — 18 novembre 2026

Complexité **4/5** : messages privés, non-lus, rédaction, contextes de recherche,
classements et profils. La reprise conserve les gabarits et services existants ;
aucune nouvelle requête, modification métier, migration ou dépendance.

- **Messagerie conservée et affinée** : titre puis liens de recherche, contacts à
  gauche sur ordinateur, panneaux empilés jusqu’à 1 050 px ; bulles bleues/dorées,
  avatar, sélection active, non-lus et bouton d’envoi existants conservés. Recherche
  de destinataire sur une ligne et rédaction dans le panneau de conversation.
- **Lecture et rédaction** : le contrôleur de fil utilise le même seuil que les
  panneaux pour garder la saisie accessible sur tablette. L’actualisation des
  contacts conserve le focus, le texte saisi et la position du fil. Ctrl + Entrée,
  Markdown, validation native, idempotence et envoi sans JavaScript sont conservés.
- **Recherche dédiée** : filtres sur deux colonnes puis une sur téléphone, résultats
  en lignes et lecture complète repliable ; retour vers le privé ou le canal recherché.
  Aucun marquage en lecture lors de la recherche.
- **Classements conservés** : cinq catégories, évolutions, relations, actions et
  pagination inchangées. La protection se consulte aussi au clavier/toucher avec
  `details`. Les catégories secondaires restent dépliables sur téléphone ; le tableau
  des guildes conserve ses huit colonnes et sa médiane dans une région défilante.
- **Profils et signalements** : scores/rangs publics en lignes ; accès à la guilde
  et à la conversation conservés, signalement existant harmonisé. Le menu principal
  identifie aussi la recherche, le profil public et le formulaire de signalement.

Navigation : les conversations restent les états d’une même messagerie ; recherche,
classement, profil et signalement conservent leurs URL dédiées. Les détails de score,
protection et Markdown utilisent des contenus dépliables natifs. Le sommaire
automatique est désactivé sur ces parcours. Les profils de guilde L1B sont réutilisés.

Adaptations techniques : les libellés réels, noms longs, dates et badges déterminent
la hauteur des lignes. Les anciens styles sans retour à la ligne ont été corrigés
sur le tableau joueurs mobile ; un contrôle du débordement de chaque cellule
complète désormais celui de la largeur de page. Les contrastes des dates, liens de
recherche et légendes du classement sont renforcés en clair. Les redirections après
refus serveur de la messagerie restent celles de l’existant ; aucun brouillon
persistant après navigation ou refus serveur n’est ajouté.

Vérifications locales : **90 tests PHP / 569 assertions** (`SocialMessageSubmission`,
`GlobalMessage`, `PublicProfiles`, `GuildTagPlayerReport`, `StrategicTools`) et
**neuf parcours navigateur distincts consolidés après corrections** : signalement,
fil long, trois canaux/Markdown/doubles envois, envoi sans JavaScript, profils,
recherche/non-lus/actualisation, puis classements/protection/colonnes des guildes.
Formats 1440/768/390/320 et thèmes sombre/clair ; contrôles axe ciblés, clavier,
accès sans JavaScript et captures réelles. Aucun dépassement de cellule conservé
sur le scénario joueurs contrôlé. Ces contrôles ne constituent pas un audit RGAA.

`make validate`, `make lint` complet (205 Twig, YAML, PHP, conteneur et schéma),
`make assets`, contrôles syntaxiques JavaScript et `git diff --check` réussissent.
Les tests PHP ont été lancés directement avec `APP_DEBUG=1` sur la base réservée
`database-test/genesis_v2_test` : l’attente Compose du service de test échouait sur
son contrôle de santé préexistant, sans empêcher ces connexions. Ce n’est pas un
`make test` complet. Les parcours navigateur utilisent leur projet éphémère, avec
messagerie externe neutralisée ; aucune donnée de la base habituelle n’est modifiée.

Preuves : `output/playwright/social-refonte/l7e-php.log`, `l7e-assets.log`,
`l7e-browser-final.log` (huit parcours réussis, un défaut de contraste ensuite corrigé),
`l7e-classements-mobile.log` (dernier parcours et contrôle de cellules réussis),
captures `recette-finale/` et `recette-classements-mobile/`. Les journaux antérieurs
conservent les échecs de préparation/contraste ; ils ne sont pas présentés comme
une réussite globale. La dernière modification du classement est vérifiée par son
parcours ciblé ; les huit autres parcours réussis ne sont pas relancés sans besoin.

L7E est réalisé et vérifié localement sur `feature/refonte-visuelle`.
**Treize lots sur dix-huit réalisés ; cinq restants**, à partir de L8.
CI GitHub, push, fusion et déploiement ne sont pas réalisés par ce lot.

## L8 — Accueil, accès et univers : propositions A/B préparées — 18 novembre 2026

Complexité **4/5** : parcours publics, erreurs de formulaire, confirmation d’adresse,
récupération, participations et calendriers d’univers. Lecture ciblée de
`SecurityController`, `UniverseController`, `ColonyController::home`,
`CrawlerController`, de la configuration d’authentification, des formulaires/DTO,
des gabarits publics/sécurité/univers, du socle commun et des deux configurations
d’univers. Les gabarits des emails de bienvenue, confirmation et réinitialisation
sont examinés et conservés : contenu, lien de repli et durées restent ceux de l’existant.

Le fichier Figcraft de la mission contient deux nouvelles pages natives :

- **38 · L8 · A — Continuité claire** : accueil présentant le jeu à côté d’un aperçu
  réel, formulaires en panneau avec contexte à gauche sur ordinateur, univers en
  cartes complètes. Sur téléphone, l’accès au compte reste direct et les cartes
  d’univers sont empilées. Bénéfice : continuité avec les écrans actuels et règles
  regroupées par univers ; limite : la comparaison demande davantage de lecture
  verticale lorsque plusieurs univers sont affichés sur téléphone.
- **39 · L8 · B — Immersion et comparaison** : illustration spatiale existante à
  l’accueil, formulaires centrés sur une surface opaque, inscription sur deux colonnes
  à grande largeur, univers comparés par critères. Sur téléphone, chaque critère
  rapproche les deux valeurs, sans défilement horizontal ni information retirée.
  Bénéfice : identité spatiale à l’entrée et comparaison plus immédiate ; limites :
  illustration plus présente et comparaison à adapter à un nombre supérieur d’univers.

Même couverture dans les deux directions : accueil visiteur, connexion, inscription,
confirmation d’adresse et renvoi, demande de récupération, nouveau mot de passe,
liens expirés/invalides, succès neutres, échec d’envoi, erreurs de champs, mot de
passe visible/masqué et mémorisation 30 jours. Les univers montrent participation
existante/premier empire, ouvert/programmé/inaccessible, fermeture, absence d’univers
et actualisation indisponible avec dernière échéance connue. L’accès refusé reprend
les trois rôles du gabarit actuel : visiteur, joueur et administrateur.

Les neuf paramètres des univers restent lisibles avant les boutons : cinq vitesses,
limites de planètes et de flotte, guildes et conquête JcE. Exemples basés sur les
configurations Omega/Turbo : vitesses temporelles ×1/×100, production ×1, 31 planètes,
1 000 000 vaisseaux ; guildes activées dans Omega et désactivées dans Turbo.
Les participations, comptes et calendriers sont fictifs, sans état de serveur prétendu.
Les illustrations et captures réutilisent les assets déjà présents dans le projet.

Navigation : pages dédiées conservées pour chaque tâche d’accès ; les erreurs et
succès sont des états de ces pages. Une comparaison d’univers reste sur `/univers`,
sans nouvelle étape obligatoire avant la création d’empire. La connexion reprend
la destination par défaut `/univers` ; l’accueil d’un compte déjà connecté conserve
sa redirection vers la colonie sélectionnée ou les univers. Les cadres du prototype
ne constituent pas de nouvelles routes. Un joueur déjà établi retrouve le socle
approuvé ; le premier empire ne montre aucun stock ou colonie inventés et conserve
les accès au compte, au wiki, au feedback et à la déconnexion.
Un **plan cliquable**, placé à gauche des cadres de chaque proposition, donne accès
aux parcours, aux erreurs et à l’autre direction. Huit départs de prototype au total
(plan, accueil, connexion et univers pour chaque option).

Garde-fous de réalisation : mêmes champs, réponses neutres, mots de passe de 12–128 caractères,
CSRF, quotas d’envoi, liens à usage unique et redirections. Aucun accès social,
case obligatoire ou compteur de joueurs ajouté. Confirmation et récupération restent
des actions explicites. Conserver les métadonnées, l’indexation accueil/inscription,
le `noindex` de connexion et les contrats de `robots.txt`, du sitemap et de `/health`.
Aucune vérification Search Console ou publication n’est annoncée.

Vérifications de conception : **92 cadres** (46 par direction), **512 commandes
reliées**, destinations contrôlées et couverture A/B équivalente. Accueil, connexion,
inscription et univers en 1440/768/390 ; récupération et confirmation en 1440/390 ;
autres états en 390. Contrôles des limites et des chevauchements de texte : aucun
signalement restant. Examen visuel des exports ordinateur/mobile de l’accueil,
connexion, inscription et univers. Les 32 pages antérieures, dont la page 36 A,
conservent leurs noms et nombres de cadres. Fichier natif enregistré sur la page 38 A.
Ces vérifications portent sur les maquettes, pas sur le fonctionnement ni une
conformité RGAA complète de l’application.

Limites du prototype : champs dessinés, exemples de navigation simulés et calendrier
statique ; aucune saisie réelle, création de compte, entrée dans un univers ou émission
d’email. Les documents légaux, le wiki, le feedback et le retour à la console sont
représentés sans nouvelle maquette de leurs destinations, rattachées aux lots suivants.
L’affichage du mot de passe est illustré à la connexion ; les contrôles des autres
formulaires restent représentés. Les changements de contexte et protections serveur
seront vérifiés sur l’application après choix. Le thème clair existant restera à
contrôler pendant la réalisation.

Preuves locales dans le dossier de conception courant : `lot-8-manifest.json`,
`lot-8-arbres.json`, `lot-8-verification.json`, `lot-8-document-avant.json`,
`lot-8-document-apres.json`, `lot-8-enregistrement.json`, exports `lot-8-A-*.png`
et `lot-8-B-*.png`. Au terme de cette préparation, aucun code du jeu n’est modifié :
L7E reste alors le dernier lot intégré dans `557061e`.
L’utilisateur valide ensuite explicitement **A** (« je valide le A »).

## L8 A — Accueil, accès et univers intégrés — 18 novembre 2026

La page **38 A — Continuité claire** est réalisée sur `feature/refonte-visuelle`.
Complexité maintenue à **4/5** après vérification des contrats d’authentification.
L’accueil rapproche le texte et l’aperçu réel de la colonie, puis présente la galaxie.
Les tâches d’accès conservent leurs pages dédiées et partagent un panneau avec
contexte illustré sur ordinateur ; à 1 050 px et moins, le formulaire devient direct.
Les cartes d’univers restent complètes, à deux colonnes sur grand écran et empilées
sur téléphone. L’absence d’empire est expliquée sans stock ou colonie inventés.
Les cinq vitesses, deux limites et deux fonctionnalités restent visibles ; la limite
de flotte est écrite en entier avec séparateurs de milliers. Les noms longs et les
libellés de boutons peuvent revenir à la ligne.

Les services, permissions, champs, CSRF, réponses neutres, quotas, mémorisation,
liens uniques et redirections restent inchangés. La confirmation d’adresse reste
explicite : le GET affiche le formulaire, le POST contrôle le lien. Les calendriers
UTC, l’horloge serveur et le seuil de fermeture à 24 heures sont conservés. Les
métadonnées publiques, le `noindex` de connexion et les réponses techniques ne sont
pas modifiés. Aucun email réel, migration existante ou changement d’équilibrage.

Adaptations : les messages de résultat restent au-dessus du contenu dans le socle
commun ; le premier empire conserve la navigation autorisée déjà disponible.
L’accès refusé garde sa présentation ciblée et son retour prioritaire selon le rôle.
Le bouton œil existant est conservé. Les actions dorées et le texte secondaire sont
ajustés pour le contraste dans les deux thèmes. Le sommaire automatique est désactivé
sur ces tâches courtes, sans modifier la navigation des autres lots.

Fichiers concernés :

- `app/templates/base.html.twig`, `public/home.html.twig`,
  `ui/public_entry_navigation.html.twig` et `universe/index.html.twig` ;
- dans `app/templates/security/` : `layout.html.twig`, `login.html.twig`,
  `register.html.twig`, `password_reset_request.html.twig`, `password_reset.html.twig`,
  `password_reset_invalid.html.twig`, `email_verification_request.html.twig`,
  `email_verification.html.twig`, `email_verification_invalid.html.twig` et
  `access_denied.html.twig` ;
- `app/assets/styles/entry-workspace.css`, importé depuis `app/assets/app.js` ;
- `app/tests/browser/entry-refonte.spec.mjs` ; README, architecture, feuille de route
  et présent suivi synchronisés dans le même lot.

Vérifications locales : **23 tests PHP / 244 assertions** (`PublicSeo`,
`SecurityHeaders`, `EmailVerification`, `Authentication`, `PasswordReset`,
`UniverseClosingClock`) et **16 parcours navigateur distincts consolidés** après
corrections. Les quatorze parcours existants couvrent les formulaires, clavier,
absence de JavaScript, refus selon le rôle, confirmation, récupération, jeton à
usage unique, fermeture et entrée dans un univers. Les deux nouveaux complètent
les accès directs, huit écrans ou états publics, les deux palettes, les neuf règles
des univers, noms longs, ouverture programmée, indisponibilité et reprise d’empire.
Formats 1440/768/390/320 pour ces compléments, cinq tailles jusqu’à 1920 pour les
contrôles existants. Axe ciblé, focus, erreurs liées et captures réelles ; ces
vérifications ne constituent pas un audit RGAA complet.

`make validate`, `make lint` complet (207 Twig, 80 YAML, PHP, conteneur et schéma),
`make assets`, syntaxe du nouveau scénario JavaScript et `git diff --check` réussissent.
PHP est lancé directement avec `APP_DEBUG=1` dans la base réservée aux tests ; ce
n’est pas un `make test` complet. Les scénarios navigateur utilisent des projets
éphémères séparés, avec messagerie externe neutralisée, sans modifier la base habituelle.

Preuves : `output/playwright/entry-refonte/php.log`, `lint-final.log`,
`assets-final.log`, `browser.log` (13 des 14 parcours existants réussis),
`browser-final.log` (refus joueur corrigé réussi), `entry-final.log` (univers réussi)
et `entry-public-final.log` (accès publics réussi). Les journaux intermédiaires
conservent les défauts de contraste et de préparation des nouveaux scénarios ; ils
ne sont pas présentés comme des réussites globales. Captures dans `recette-initiale/`,
`recette-finale/`, `recette-acces/` et `recette-publique-finale/`, avec examen visuel
de l’accueil, des formulaires et des univers sur ordinateur/mobile.

**Quatorze lots sur dix-huit réalisés localement ; quatre restants**, à partir de
L9 (feedback, patchnotes et documents complémentaires). CI GitHub, push, fusion
et déploiement restent distincts et ne sont pas réalisés par ce lot.

## L9 — Feedback, patchnotes et documents : propositions A/B — 19 novembre 2026

Départ : `feature/refonte-visuelle`, arbre propre après L8 A (`8933d0e`).
Complexité **3/5** : formulaire avec capture privée, lecture des publications et
documents publics. Lecture ciblée des contrôleurs, repositories, gabarits et
parcours existants de feedback, patchnotes et documents juridiques ; aucune
reprise des interfaces administratives, réservées à L10.

Deux nouvelles pages sont enregistrées dans le Figcraft de bureau de la mission :

- **40 · L9 · A — Lecture directe** : formulaire proche de l’existant, avec contexte
  et capture dans un panneau à droite sur ordinateur ; empilement sur mobile.
  Patchnotes en cartes dépliables et navigation horizontale entre documents.
  Bénéfice : accès direct aux champs et continuité ; limite : lecture verticale
  des séries de publications.
- **41 · L9 · B — Repères de lecture** : choix du type par quatre boutons, formulaire
  centré, index des versions et navigation latérale entre documents sur ordinateur.
  Sur mobile, les repères sont placés avant le contenu. Bénéfice : choix du type et
  repérage des versions plus visibles ; limite : formulaire plus haut et repères
  supplémentaires avant la lecture sur téléphone.

Couverture équivalente : **66 cadres, 33 par direction**, feedback et patchnotes
en 1440/768/390, quatre documents publics en 1440/390 ; états complémentaires en
390 et retour avec capture / patchnote dépliée également en 1440. Les quatre types,
capture choisie et aperçu, validation, capture refusée, limitation, succès et accès
sans empire sont représentés. Les trois notes possèdent leur détail et leur retour
à l’état fermé ; l’absence de publication est couverte. Les cadres légaux principaux
illustrent le visiteur ; un exemple de confidentialité avec contexte joueur est fourni.

Navigation : feedback reste une tâche sur sa page dédiée ; les patchnotes restent
une collection avec détails dépliables. L’index B utilise des ancres dans cette
collection, sans nouvelle route ni notes masquées par un filtrage JavaScript.
Chaque document conserve son URL. Seule la longue confidentialité reçoit des
ancres de sections ; les autres textes restent directement lisibles. Deux plans
cliquables, **782 liens** et six départs de prototype ; `M` revient au plan de la
direction consultée. Les accès compte, connexion et Vue générale renvoient aux
cadres déjà approuvés.

Garde-fous pour la réalisation : conserver champs, limites 120 / 5 000 / 500
caractères, quatre types, trois priorités, capture facultative JPEG/PNG/WebP de
5 Mo maximum, CSRF, refus après trois retours récents et valeurs saisies après
erreur. Une capture doit être resélectionnée après un refus serveur. Le fichier
enregistré reste privé, visible seulement par son auteur ou un administrateur.
L’aperçu dessiné est local et simulé ; il ne rend pas publique la route des captures.
Les patchnotes restent limitées aux publications déjà datées, dans l’ordre existant ;
l’ouverture conserve l’enregistrement de lecture du contrôleur. Le socle joueur
dessiné donne le contexte ; le composant de ressources existant sera conservé.

Les blocs des textes juridiques existants sont repris intégralement pour le cas
sans identité privée configurée : 10 mentions, 14 CGU, 26 confidentialité et
10 cookies. La date du 7 novembre 2026, les passages « À compléter » et le message
d’information provisoire sont conservés. Aucun nouveau consentement, contenu
juridique, sanction ou règle n’est proposé. Les contextes connecté/visiteur,
métadonnées, liens de contact et liens externes restent à préserver dans le jeu.

Vérifications de conception : destinations inspectées, parité A/B, limites des
textes, chevauchements de textes et chevauchements de cadres contrôlés ; **aucun
signalement restant**. Les 34 pages antérieures conservent leurs noms et nombres de
cadres ; le document compte 36 pages et garde les 348 variables existantes. Examen
visuel des exports ordinateur/mobile de feedback, patchnotes et documents ; fichier
natif enregistré et fenêtre de bureau observée sur le plan A.

Limites : champs dessinés, choix et navigation simulés, patchnotes et données de
jeu fictives. Les valeurs longues d’un champ peuvent être abrégées visuellement
dans la maquette ; leur valeur réelle ne devra pas être tronquée dans les formulaires.
Les liens de contact et liens externes des paragraphes ne déclenchent aucun envoi
dans le prototype. Les comportements serveur, thèmes réels, clavier et accessibilité
seront vérifiés lors de l’intégration après choix ; aucune conformité RGAA globale
n’est annoncée. Aucun feedback, email ou changement de données réelles effectué.

Preuves locales : `lot-9-manifest.json`, `lot-9-plan.json`, `lot-9-verification.json`,
`lot-9-document-avant.json`, `lot-9-document-apres.json`, `lot-9-textes-legaux.json`,
`lot-9-enregistrement.json` et 16 exports `lot-9-A-*.png` / `lot-9-B-*.png` dans le
dossier de conception. Aucun code du jeu modifié ; seuls le présent suivi, la feuille
de route et l’inventaire sont actualisés. `git diff --check` constitue le contrôle
du lot documentaire ; aucune suite applicative inchangée n’est relancée.

À la fin de cette conception, L8 A reste le dernier lot réalisé : quatorze lots sur
dix-huit, quatre restants. L’utilisateur valide ensuite A ; réalisation ci-dessous.
L10 à L12 ne sont pas engagés par cette préparation.

## L9 A — Lecture directe intégrée — 19 novembre 2026

La page **40 A** est validée explicitement puis réalisée sur `feature/refonte-visuelle`.
Complexité initiale **3/5**, réévaluée à **4/5** devant le conflit de largeur avec
les styles partagés antérieurs. Les corrections restent limitées aux conteneurs L9,
sans changement de service ni de données. Le formulaire unique conserve l’ordre des champs puis de l’envoi ;
sur ordinateur, contexte et capture passent à droite, avec le bouton sous le retour.
À 1 150 px et moins, les groupes sont empilés. Tous les champs, règles
de capture, erreurs et saisies conservées restent ceux de l’existant.

L’aperçu d’image est agrandissable dans un dialogue natif, fermable par Échap ; le
retrait ramène le focus au champ. Les URL temporaires sont révoquées au retrait,
remplacement et départ du contrôleur. Les décodages obsolètes sont ignorés. Cet
aperçu ne téléverse aucun fichier : le serveur garde sa validation et l’accès privé
auteur/administrateur. L’envoi sans JavaScript, le quota et les refus sont conservés.

Les patchnotes restent des cartes avec `details` natifs et contenu échappé. Le
contrôleur conserve les publications datées et l’enregistrement de lecture. Les
documents gardent leur contenu ; six identifiants d’ancre sont ajoutés à la
confidentialité. Les visiteurs retrouvent l’en-tête L8, les comptes connectés leur
contexte existant. Les liens inclus dans les textes sont soulignés, y compris en
clair, sans dépendre uniquement de leur couleur. Aucun changement juridique.

Fichiers : `app/templates/feedback/create.html.twig`, `patchnotes/index.html.twig`,
`legal/page.html.twig`, `legal/confidentialite.html.twig` et `base.html.twig` ;
`app/assets/controllers/feedback_attachment_controller.js`,
`app/assets/styles/information-workspace.css`, import dans `app/assets/app.js` ;
`app/tests/browser/information-refonte.spec.mjs` et cinq documents de suivi
(README, architecture, feuille de route, inventaire et présent bilan).

Vérifications locales : **7 tests PHP / 127 assertions** (`PublicSeoTest`,
`PatchNoteControllerTest`) ; **5 parcours navigateur distincts consolidés après
corrections**. Couverture : aperçu/remplacement/retrait/déconnexion-reconnexion,
image non décodable, dialogue et focus ; envoi sans JS, format refusé, saisies et
contexte conservés, trois retours acceptés puis quota, accès auteur/autre compte/admin ;
publication future/brouillon masqués, lecture et échappement du contenu ; quatre
documents publics, métadonnées, navigation et ancres, contexte connecté ; parcours
SEO public existant. Formats 1440/768/390/320 et deux thèmes pour L9, clavier,
captures réelles et axe ciblé, sans prétendre à un audit RGAA complet.

`make validate`, `make lint` complet (207 Twig, 80 YAML, PHP, conteneur et schéma),
`make assets`, syntaxe des deux nouveaux fichiers JavaScript et `git diff --check`
réussissent. PHP est lancé directement avec `APP_DEBUG=1` dans la base réservée
aux tests ; ce n’est pas un `make test` complet. Les navigateurs utilisent des
projets et bases éphémères, avec messagerie externe neutralisée.

Preuves : `output/playwright/information-refonte/php.log`, `lint.log`,
`assets-final.log`, `browser.log` (trois parcours réussis), `browser-final.log`
(aperçu et documents corrigés réussis), `feedback-final.log` (envoi et droits réussis)
et `layout-final.log` (aperçu et largeur complète du formulaire réussis).
Les échecs intermédiaires de contraste et de préparation du scénario restent dans
les journaux et ne sont pas présentés comme une réussite globale. Captures dans
`recette/`, `recette-finale/`, `recette-feedback/` et `recette-largeur/`. L’aperçu et les documents sont
examinés visuellement après les ajustements de largeur et de contraste.

**Quinze lots sur dix-huit réalisés localement ; trois restants** : L10, L11, L12.
Aucun push, CI GitHub, fusion, migration habituelle ou déploiement réalisé par ce lot.

## L10A — Console, espaces et joueurs : propositions A/B — 19 novembre 2026

L9 A est enregistré dans **`dafe1ca`**, avec arbre propre après commit. L10 couvre
toute l’administration et reste un seul ensemble du plan des dix-huit lots. Il est
traité par sous-lots cohérents pour permettre les validations avant réalisation.
Le premier porte sur **trois vues logiques** : accueil de la console, page d’un
espace (quatre variantes existantes) et liste des joueurs. Les fiches détaillées,
interventions, modération, univers, animation et exploitation restent aux suites
du lot ; leurs pages ne sont pas fusionnées dans cet accueil.

Complexité **4/5** : navigation commune, données privées de comptes et actions sur
les droits. Plan ciblé : examiner layout/navigation et leurs usages, conserver les
routes des espaces, les lectures ciblées et les contrats de recherche ; représenter
les états et confirmations ; vérifier les cadres puis attendre le choix avant code.
Sources : `admin/layout`, `_navigation`, `_page_navigation`, `index`, `group`,
`section`, `sections/players`, `_players_results`, pagination ; rubriques concernées
d’`AdminController`, `AdminReadRepository`, `AdminService`, `admin-list` et guide
d’interface. La fiche joueur est examinée pour repérer sa destination et ses effets
de consultation, sans ouvrir de fiche réelle ni proposer sa réécriture dans L10A.

Nouvelles pages du fichier Figcraft de bureau :

- **42 · L10A · A — Console directe** : panneaux proches de l’accueil actuel,
  rubriques en cartes et tableau des comptes à six colonnes sur ordinateur. Sur
  mobile, les informations des comptes sont empilées sans retirer de champ.
  Bénéfice : densité et comparaison entre comptes ; limite : lecture des colonnes
  à adapter soigneusement aux écrans étroits.
- **43 · L10A · B — Pilotage et fiches** : priorité en tête, rubriques en lignes et
  comptes présentés en fiches, avec état, rôle, courriel, présence et actions.
  Bénéfice : lecture individuelle plus explicite ; limite : hauteur supérieure
  pour parcourir plusieurs comptes.

Couverture équivalente : **68 cadres, 34 par direction**, **894 liens inspectés**
et six départs de prototype. Accueil, espace joueurs et liste en 1440/768/390 ;
autres espaces et recherche filtrée en 1440/390. États en 390 : absence de résultat,
recherche indisponible, pagination, noms longs, compte courant, confirmations de
promotion/retrait de rôle/suspension, retour simulé, refus, accueil sans priorité,
relais indisponible, dernières actions et menu. Les deux comptes joueurs de
l’exemple ouvrent une confirmation de promotion portant leur propre nom.
Le menu garde retour au jeu, déconnexion et identité visuelle, y compris sur mobile.
Chaque direction possède un plan de comparaison ; `M` y revient dans le prototype.

Navigation conservée : cinq entrées principales et quatre espaces avec vraies pages,
puis liens secondaires. Les quatre indicateurs, priorités, accès rapides, missions,
événements et derniers audits restent fondés sur les données actuellement préparées.
Un relais indisponible reste « à vérifier », sans zéro trompeur. Aucun nouvel
indicateur ou chargement global n’est proposé. Les filtres joueurs restent une
recherche GET par pseudo/courriel, état et rôle ; pagination serveur de 50 lignes,
tri et paramètres conservés. Sans JavaScript, Rechercher reste disponible ; une
erreur d’actualisation garde les derniers résultats et les champs saisis.

Garde-fous : `ROLE_ADMIN`, CSRF, contrôles serveur et journalisation conservés ;
aucune action sur le compte courant dans la liste, aucune suspension d’un autre
administrateur. Les protections de rôle courant, cible et dernier administrateur
actif restent dans le service. Les dialogues dessinés illustrent les confirmations
du navigateur existantes ; la réactivation ne reçoit pas de confirmation ajoutée.
Les actions ne deviennent pas des liens GET. Aucune promotion, suspension ou autre
intervention réelle n’est exécutée dans ce lot de conception.

Vérifications : limites des textes, chevauchements, parité et destinations inspectés ;
**aucun signalement restant**. Douze exports des trois vues principales en 1440/390
ont été examinés. Les 36 pages antérieures restent inchangées en noms et nombres de
cadres ; le fichier compte 38 pages et conserve ses 348 variables. Fichier natif
enregistré sur le plan A ; état de la fenêtre de bureau vérifié, fichier propre.

Limites : données, comptes et dates fictifs ; champs dessinés, choix de filtres et
opérations simulés, avec quatre comptes représentatifs. Le cadre « Destinations
conservées » identifie les pages des sous-lots suivants, sans constituer une nouvelle
page du jeu. Les scénarios de noms longs servent à comparer la lecture ; les valeurs
réelles et les noms de cible devront provenir des formulaires serveur conservés.
L’accessibilité, les thèmes réels, permissions, concurrence et comportements seront
vérifiés lors de l’intégration ; cette maquette ne constitue pas une recette du jeu.

Preuves dans le dossier de conception : `lot-10A-manifest.json`, `lot-10A-plan.json`,
`lot-10A-verification.json`, `lot-10A-document-avant.json`,
`lot-10A-document-apres.json`, `lot-10A-enregistrement.json` et exports
`lot-10A-A-*.png` / `lot-10A-B-*.png`. Seuls le suivi, l’inventaire et la feuille de
route sont actualisés après le commit L9 ; `git diff --check` est le contrôle adapté.
Le choix A a ensuite été reçu ; sa réalisation est décrite ci-dessous.
**Quinze ensembles réalisés sur dix-huit ; trois restants**, L10 reste en cours.

## L10A A — Réalisation locale du 19 novembre 2026

Validation explicite : « je valide A ». Complexité **4/5** : console réservée aux
administrateurs, données privées et formulaires de rôle/suspension à préserver.
Le lot conserve les pages dédiées, les cinq entrées principales et la navigation
secondaire. Les groupes restent des cartes ouvrant les pages existantes ; les
filtres sélectionnent les mêmes comptes, sans cacher des tâches indépendantes.
Seules les dernières actions de l’accueil restent dépliables, car secondaires.

L’accueil garde ses quatre indicateurs et l’ordre des priorités existant. Missions
et événements prennent toute la largeur après les panneaux priorités/accès rapides.
La liste conserve les six colonnes sur ordinateur ; sous 1050 px, chaque compte
présente son nom sur toute la largeur puis courriel, présence, état, rôle et actions
avec leurs libellés. Les filtres GET restent associés à leur champ. Les mêmes POST,
CSRF, confirmations natives, protections du compte courant et des administrateurs
restent utilisés. Le nom accessible des boutons de rôle reprend leur texte visible
et le joueur concerné. Aucune nouvelle requête, permission, règle ou migration.

Fichiers : `admin-workspace.css`, import dans `app.js`, templates administratifs
`layout`, `index`, `sections/players` et `_players_results`, recette navigateur
`admin-refonte.spec.mjs` et adaptation des libellés attendus dans deux recettes
existantes ; README, architecture, inventaire, feuille de route et ce suivi.

Vérifications locales : `make validate`, `make lint`, `make assets` et contrôle du
diff réussis ; **29 PHP / 312 assertions**, filtrés sur pagination, pages dédiées,
lectures administratives et interventions, dans la base de test isolée.
**Six parcours navigateur distincts consolidés** : recherche globale/pagination et
réponses obsolètes ; rôles/suspensions/confirmations/journal ; menu mobile/clavier ;
cinq entrées/cartes/accès refusés ; présentation et noms longs ; recherche en erreur
et filtres sans JavaScript. Thèmes clair/sombre en 1440/768/390, six routes visitées,
contrôles d’accessibilité ciblés sans signalement sur le contenu en 1440/390.
**24 captures finales** des six routes dans les deux thèmes, ordinateur/téléphone.

Les premières nouvelles recettes avaient deux erreurs de test (nom fictif supérieur
à la taille SQL et comparaison de deux représentations de texte différentes).
Elles ont été corrigées sans alléger les assertions. L’accessibilité a ensuite
détecté le nom de bouton incohérent ; la lecture des captures a révélé la largeur
de colonne restée appliquée au nom sur mobile. Corrections ciblées, libellés et
largeurs contrôlés explicitement, puis relance des seules recettes concernées.
Preuves : `output/playwright/admin-refonte/`, journaux `php`, `lint-final`,
`assets-final`, `browser`, `browser-final` et `browser-layout`, captures de
`recette-largeur`. Les actions utilisent uniquement les bases jetables des tests.
Pas de CI GitHub, fusion ou déploiement déclaré ; aucun gain de performance mesuré.

## L10B — Fiche joueur et interventions, propositions du 19 novembre 2026

Complexité **4/5**. Sources ciblées : fiche joueur, fragments des deux interventions,
`AdminPlayerController`, `AdminInterventionService`, règles du catalogue et cadrage
administratif. La fiche reste une page dédiée par joueur. Les participations aux
univers restent dans la même fiche ; les deux outils restent dépliables auprès
de leur colonie, avec leurs champs, motif et confirmation explicite réunis.
Les dossiers et missions restent des liens vers leurs pages existantes.

Dans le même fichier Figcraft de bureau :

- **44 · L10B · A — Fiche et actions locales** : six repères d’activité en cartes,
  colonies en lignes et outils ouverts sur place. Lecture comparative de l’activité ;
  hauteur supérieure des repères sur téléphone.
- **45 · L10B · B — Lecture par univers** : activité en liste et colonies en cartes
  côte à côte sur ordinateur. Lecture suivie et comparaison des colonies ; les
  formulaires disposent de moins de largeur dans ces cartes sur ordinateur.

Couverture équivalente : **48 cadres, 24 par direction**, **758 liens inspectés**,
six départs de prototype. Fiche, ressources ouvertes et vaisseaux ouverts en
1440/768/390 ; états en 390 : formulaires renseignés, refus, réussite, demande déjà
traitée, activité non mesurée, aucune colonie/participation, noms longs et plusieurs
univers, suspension, menu et destinations conservées. `M` revient au plan ; le plan
permet de comparer l’autre proposition. Les cinq entrées et les retours au jeu,
à la liste et à la connexion ouvrent les références déjà présentes.

Les cinq ressources gardent leur stock/capacité et leurs quantités ; les vaisseaux
gardent type, rang et entier de 1 à 2 147 483 647. Motif obligatoire de 1 000 caractères
maximum, case de confirmation, POST/CSRF, propriétaire, rôle courant, transactions,
idempotence et audit restent la référence. L’activité demeure estimée, avec journées
UTC et aucune reconstitution avant le suivi. Aucune règle d’équilibrage changée.
Les refus illustrent les retours serveur actuels ; aucune conservation de saisie
après redirection n’est promise. Aucun nouvel écran de confirmation n’est ajouté.

Vérification de tous les cadres : aucun texte hors parent ni chevauchement, aucune
destination manquante et parité conservée. Douze exports ordinateur/téléphone des
trois vues ; aperçus examinés. Les 38 pages antérieures sont conservées en noms et
nombres de cadres ; 40 pages et 348 variables après ajout. Fichier natif enregistré
sur le plan A et fenêtre de bureau contrôlée, document propre.

Limites : données et actions fictives, champs dessinés et parcours simulés. Terra
illustre les deux formulaires ; les outils de Nova et les dossiers/missions mènent
à un cadre de référence explicite. Les contrôles de sécurité et thèmes réels seront
recettés lors de la réalisation. Preuves dans le dossier de conception :
`lot-10B-plan.json`, `lot-10B-manifest.json`, `lot-10B-verification.json`, états du
document avant/après/enregistrement et `lot-10B-{A,B}-{profile,resources,ships}-*.png`.
Le choix A a ensuite été reçu ; réalisation et suite ci-dessous. Le plan global
reste à **15/18**, L10 en cours.

## L10B A — Réalisation locale du 19 novembre 2026

Validation explicite : « je valide le A ». Complexité **4/5** : données privées,
interventions de ressources et de vaisseaux, permissions et écritures journalisées.
La page dédiée par joueur est conservée. Les univers restent regroupés dans cette
fiche ; les outils secondaires restent dépliables sous leur colonie, avec toutes
les informations nécessaires à la même action. Les dossiers et missions conservent
leurs vrais liens et paramètres de participation/univers.

Six repères d’activité par univers en cartes : trois colonnes sur ordinateur,
deux sur tablette, une sur téléphone. Les colonies occupent des lignes complètes.
Les deux outils natifs gardent leurs champs, contrôles, CSRF, clés de demande,
motif et confirmation explicite. Les cinq ressources affichent stock/capacité
auprès du champ, avec un lien accessible ; les deux formulaires rappellent la
destination précise. Les liens et champs restent utilisables au clavier et sans
JavaScript. Le sommaire automatique est désactivé pour la fiche, sans retirer les
fragments de retour. Aucune modification des services, requêtes, règles ou valeurs.

Fichiers : `admin-player-workspace.css`, import `app.js`, templates `admin/player`,
`admin/layout`, `_resource_intervention`, `_ship_intervention`, recette
`admin-player-refonte.spec.mjs`, README, architecture, inventaire, feuille de route
et suivi. Les notes, formulaires et données d’activité restent issus de l’existant.

Contrôles locaux : `make validate`, `make lint`, `make assets`, contrôle du diff ;
**29 PHP / 138 assertions** (`AdminInterventionTest`, `PlayerActivityTest`) dans
la base de test isolée. **Cinq parcours navigateur distincts consolidés** :
ressources (confirmation, ajout, répétition, CSRF et accès refusés, retrait refusé,
audit) ; présence/temps par univers et refus d’accès ; attribution de vaisseaux
confirmée, auditée et sans double effet ; présentation à deux univers, noms longs,
saisie conservée entre ouvertures des outils ; états vides et correction native
sans JavaScript. La recette vérifie aussi les paramètres des liens de dossiers,
l’absence d’écriture d’intervention pendant les seules lectures, et les cinq
champs de ressources. Les tests existants ne sont pas affaiblis.

Thèmes clair/sombre en **1440/768/390**, fiche fermée puis outils de ressources et
de vaisseaux ouverts : **18 captures finales**. Contrôles d’accessibilité ciblés
sur ces états, sans signalement ; ce n’est pas un audit RGAA complet. Après lecture
des captures, titres, alignement/couleur des repères et hauteur des champs de motif
ont été ajustés pour suivre A ; la recette de présentation a été reprise, avec
assertions de l’alignement et de la hauteur minimale de 150 px des zones de motif.
Preuves : `output/playwright/admin-player-refonte/`, journaux `php`, `lint`,
`assets-final`, `browser` et `browser-final`, captures dans `recette-finale`.
Les données modifiées par les recettes restent dans leurs bases jetables.
Pas de migration sur la base habituelle, CI GitHub, fusion ou déploiement ; aucun
gain de performance annoncé. Les redirections d’erreur gardent leur comportement
actuel, sans nouvelle conservation de saisie promise.

## L10C — Signalements et feedbacks, propositions du 19 novembre 2026

Complexité **4/5**. Sources : sections de signalements, liste et détail des feedbacks,
contrôleurs et services de traitement, navigation et cadrage administratif actuels.
Trois vues existantes : modération des messages/joueurs, liste filtrée des feedbacks,
fiche de suivi. Les deux catégories de signalement restent dans la même page ; la
recherche porte uniquement sur les messages. Les cinq entrées principales et les
liens secondaires de Joueurs et modération sont conservés. Aucun faux changement
de page par masquage de sections n’est proposé.

Dans le même fichier Figcraft de bureau :

- **46 · L10C · A — Modération lisible** : signalements en panneaux complets,
  feedbacks en cartes ; sur ordinateur, suivi du feedback en colonne auprès du
  contenu. Bénéfice : contexte de chaque décision réuni ; limite : hauteur pour
  parcourir les signalements.
- **47 · L10C · B — Lecture et suivi** : signalements en colonnes sur ordinateur,
  feedbacks en lignes ; suivi du feedback après sa description, sa capture et son
  contexte. Bénéfice : contenu lu avant modification ; limite : accès au formulaire
  plus bas dans une longue fiche.

Couverture équivalente : **58 cadres, 29 par direction**, **892 liens inspectés**,
six départs de prototype. Trois vues en 1440/768/390 ; états en 390 : recherche
des messages, absence de résultat ou de signalement, décisions de message/joueur,
déjà traité, feedbacks filtrés/vides/paginés/archivés, note renseignée/refusée/
enregistrée, absence de capture, feedback archivé, textes longs, capture fictive,
menu et destinations conservées. `M` revient au plan ; celui-ci compare A et B.
Douze exports des trois vues en 1440/390 ; aperçus examinés. Aucun débordement de
texte, chevauchement ou lien manquant au contrôle de tous les cadres ; parité
conservée. Les 40 pages précédentes gardent noms et nombres de cadres ; 42 pages
et 348 variables après ajout. Fichier enregistré sur le plan A, document propre
dans la fenêtre de bureau contrôlée.

Garde-fous conservés dans la proposition : seuls les messages signalés sont
consultables ; classer sans suite et masquer le message gardent leurs POST/CSRF.
Le signalement de joueur conserve son contexte et sa décision sans sanction
automatique. Les feedbacks gardent cinq filtres GET, pagination serveur de 50,
archives exclues par défaut, statut et note interne de 5 000 caractères maximum.
Le raccourci « Voir les archives » utilise le filtre existant. Consultation et
suivi restent journalisés ; capture privée autorisée par le serveur, aucun accès
public supplémentaire. Pas de confirmation ou d’action métier ajoutée.

Limites : contenus et décisions fictifs ; champs dessinés et parcours simulés,
extraits de listes. Le suivi détaillé illustre le feedback d’Orion ; le second
feedback conserve son identité mais renvoie au cadre de référence pour son édition.
La capture est dessinée et identifiée comme fictive ; elle ne représente aucun
document réel. Les erreurs illustrent la redirection actuelle, sans conservation
de saisie nouvelle. Les permissions, thèmes et vrais retours serveur seront
recettés après choix. Preuves : `lot-10C-plan.json`, `lot-10C-manifest.json`,
`lot-10C-verification.json`, états du document avant/après/enregistrement et
`lot-10C-{A,B}-{reports,feedbacks,detail}-*.png` dans le dossier de conception.
Le choix A a ensuite été reçu ; réalisation et suite ci-dessous. L10 reste en cours,
plan global **15/18**.

## L10C A — Réalisation locale du 19 novembre 2026

Validation explicite : « je valide le A ». Complexité **4/5** : messages signalés,
contexte privé, captures et notes internes, décisions de modération et audit.
Les trois pages actuelles restent distinctes. Les deux catégories de signalement
restent dans la même page, avec recherche locale portant uniquement sur les messages.
La liste des feedbacks conserve ses filtres GET ; chaque feedback garde sa fiche
et son URL. La fiche place le suivi en colonne sur ordinateur, après le contenu
sur tablette/téléphone. Aucun contenu nécessaire à la décision n’est replié.

Signalements en panneaux complets, feedbacks en cartes, retours et champs lisibles,
limite de note reliée au champ. Le raccourci vers les archives garde recherche,
type, priorité et univers, puis revient à la page 1 ; son retour rétablit l’exclusion
des archives. Les mêmes actions, CSRF, autorisations, contextes, audits et redirections
sont conservés. Aucun service, calcul de données, règle de jeu ou schéma n’est modifié.

Fichiers : `admin-moderation-workspace.css`, import `app.js`, templates `admin/layout`,
`admin/sections/reports`, `admin/feedbacks`, `admin/feedback_detail`, recette
`admin-moderation-refonte.spec.mjs`, README, architecture, inventaire, feuille de route
et ce suivi. Les autres rubriques restent hors du périmètre des nouveaux styles.

Vérifications : `make validate`, `make lint`, `make assets`, contrôle du diff ;
**39 PHP / 331 assertions**, sur pagination/lectures administratives, signalements
de joueurs et modération des messages, dans la base de test isolée.
**Six scénarios navigateur consolidés** : présentation/contenus/capture privée ;
filtres, pagination et suivi sans JavaScript ; signalement joueur et preuve conservée ;
modération d’un message avec puis sans polices distantes ; sauvegarde d’une note longue.
Les quatre scénarios existants restent inchangés et réussissent. Les nouveaux
vérifient cinq filtres, pagination à 50, conservation des filtres dans le raccourci
d’archives, statut invalide, CSRF invalide et audit unique de la sauvegarde attendue.

La capture est accessible au propriétaire et à l’administrateur, refusée à un autre
joueur ; la fiche administrative lui est aussi refusée. Un message non signalé
n’apparaît pas dans la modération. Une description contenant une balise script reste
du texte échappé. La recherche sans résultat des messages laisse le signalement
de joueur visible. Les simples lectures ne créent pas de décision de modération.
Thèmes clair/sombre en **1440/768/390**, trois pages : **18 captures finales**,
contrôles d’accessibilité ciblés sans signalement. Les cinq états des feedbacks
et les trois priorités sont aussi vérifiés en thème clair ; pas d’audit RGAA global.

Les nouvelles recettes ont nécessité la correction d’un libellé de champ et le
passage des requêtes de contrôle par le navigateur pour résoudre le domaine local.
Une tentative parallèle n’a pas pu créer son réseau de test ; reprise après la
fin et le nettoyage de la recette précédente, sans changement de configuration.
Le contrôle visuel a détecté deux couleurs illisibles sur fond blanc : statut
Ouvert et priorité élevée. Les priorités historiques imposaient leur couleur avec
`!important` ; leur surcharge est limitée aux trois libellés en thème clair de ce lot.
Autres états/priorités couverts ; badge protégé contre sa compression par un titre
long et contexte agrandi après lecture des captures. Reprise du scénario concerné
avec assertion de largeur du badge, sans affaiblir les tests.
Preuves : `output/playwright/admin-moderation-refonte/`, journaux `php`,
`lint`, `assets-final`, `browser`, `browser-final`, `browser-contrast`,
`browser-final-contrast`, `browser-visual-final` et captures de `recette-verification-visuelle`.
Les données et captures utilisées sont synthétiques et
isolées ; aucun message ou feedback habituel n’est modifié. CI GitHub, fusion et
déploiement restent distincts ; aucun gain de performance mesuré.

## L10D — Dossiers, propositions du 19 novembre 2026

Complexité **4/5**. Sources ciblées : liste, création et fiche des dossiers,
`AdminCaseController`, `AdminCaseService`, règles de contexte, version d’état,
idempotence et suivi existants. La liste, la création et le détail gardent leurs
pages dédiées et leurs URL. Les filtres sélectionnent les mêmes dossiers ; les
observations initiales et les notes restent un historique du même dossier.

Dans le même fichier Figcraft de bureau :

- **48 · L10D · A — Dossiers en lignes** : entrées en lignes, observations et
  notes à gauche, formulaire de suivi en colonne sur ordinateur. Bénéfice : accès
  direct au suivi ; limite : largeur des longues notes auprès du formulaire.
- **49 · L10D · B — Lecture chronologique** : entrées plus espacées et fiche lue
  dans l’ordre observations, notes puis ajout de suivi. Bénéfice : continuité de
  lecture ; limite : formulaire situé plus bas dans une longue fiche.

Couverture équivalente : **58 cadres, 29 par direction**, **864 liens inspectés**,
six départs de prototype. Liste, création et détail en 1440/768/390 ; états en 390 :
filtres, absence de résultat, pagination, portée de participation, création renseignée/
invalide/avec contexte, aucune note, suivi renseigné/enregistré, dossier clos,
état périmé, demande déjà traitée, textes longs, dossier de plateforme, créations
réussies, menu et destinations conservées. `M` revient au plan ; comparaison A/B
depuis celui-ci. Douze exports ordinateur/téléphone des trois vues ; aperçus examinés.
Contrôle de tous les cadres : aucun texte hors parent, chevauchement ou lien manquant,
parité conservée. Les 42 pages antérieures gardent noms et nombres de cadres ;
44 pages et 348 variables après ajout. Fichier enregistré sur le plan A, document
propre dans la fenêtre de bureau contrôlée.

Filtres actuels conservés : recherche de 120 caractères, univers, dossiers actifs/
tous les états et trois états (Ouvert, En cours d’examen, Clos), avec pagination
serveur de 50 et portée participation/flotte transmise. Création : titre de 1 à
120 caractères, observations de 10 000 au maximum, plateforme ou univers, contexte
joueur/flotte cohérent. Une erreur de création reste sur le formulaire avec sa
saisie ; le suivi conserve sa redirection actuelle. Notes ajoutées à l’historique,
correction par nouvelle note ; changement d’état ou note nécessaire. Rôle actif,
CSRF, version attendue, clés de demande, transactions et audits restent la référence.
Aucune sanction automatique ni nouveau réglage métier proposé.

Limites : données fictives, champs dessinés et opérations simulées ; extraits de
listes. Le formulaire de suivi détaillé illustre le dossier d’Orion ; le dossier
de plateforme renvoie au cadre de référence pour son édition. Les missions/alertes
restent des destinations de référence. La confidentialité, les thèmes et vrais
retours serveur seront recettés après choix. Preuves : `lot-10D-plan.json`,
`lot-10D-manifest.json`, `lot-10D-verification.json`, états du document avant/après/
enregistrement et `lot-10D-{A,B}-{cases,new,detail}-*.png` dans le dossier de conception.
L10D A est ensuite validé explicitement ; réalisation ci-dessous. L10 reste
en cours, plan global **15/18**.

## L10D A — Réalisation locale du 19 novembre 2026

Complexité **4/5**. La page 48 A est retenue pour la liste, la création et la
fiche des dossiers. Trois pages dédiées conservées : tâches autonomes avec accès
direct, rechargement et formulaires propres. Les filtres GET sélectionnent le
même ensemble ; observations, notes et ajout de suivi appartiennent au même
dossier. Le suivi accompagne l’historique en colonne sur ordinateur, puis vient
après celui-ci sous 1100 px. Aucun nouvel onglet ni mécanisme d’actualisation.

Changements ciblés : `layout.html.twig`, `cases.html.twig`, `case_new.html.twig`,
`case.html.twig`, import dans `app.js` et `admin-case-workspace.css` limité aux
trois routes. Résultats en lignes avec une seule destination par dossier,
contexte de participation/flotte visible sans chargement supplémentaire,
champs et aides reliés, saisies longues lisibles et zone de notes de 200 px.
Les titres sans espaces passent à la ligne. Les états, univers, auteurs, dates,
observations, notes et liens joueur/flotte restent directement disponibles.
Les services et contrôleurs ne changent pas : mêmes droits, CSRF, cohérence
des contextes, demandes idempotentes, état attendu, transactions et audits.
Le brouillon de création est conservé après refus ; le suivi garde son retour
serveur actuel et ne promet pas une conservation supplémentaire de saisie.

Vérifications locales :

- `make validate`, `make lint`, `make assets` et `git diff --check` réussis ;
- huit tests PHP ciblés `AdminCaseTest`, **27 assertions** ;
- quatre scénarios navigateur consolidés : deux parcours existants dossiers/
  alertes et deux nouvelles recettes `admin-case-refonte.spec.mjs` ;
- création, contexte et liens, refus sur univers incohérent sans perte de titre/
  observations, notes conservées, doubles envois, état périmé, clôture et historique,
  CSRF et accès non administrateur ;
- filtres et pagination 50 + 2, portée participation/flotte et recherche conservées
  au retour/rechargement, absence de résultat, parcours sans JavaScript ;
- **18 captures finales**, trois vues × 1440/768/390 × clair/sombre : une seule
  colonne sur écrans étroits, aucun débordement horizontal, textes échappés,
  contrôles Axe ciblés sans exclusion de règle. Captures ordinateur/mobile examinées.

La première recette a relevé une hauteur de textarea écrasée par la spécificité
des champs communs et un sélecteur de test trop strict. Correction ciblée du style
et du sélecteur, recompilation et reprise réussie des deux scénarios concernés.
La présentation des trois petits templates modifiés a ensuite été développée
pour leur lisibilité, puis le lint relancé avec succès.
Preuves : `output/playwright/admin-case-refonte/`, journaux `php`, `lint-final`,
`assets-final`, `browser`, `browser-final` et captures de `recette-finale`.
Données synthétiques en environnement isolé ; aucun dossier habituel modifié.
CI GitHub, fusion et déploiement restent distincts ; aucun gain de performance mesuré.

## L10E — Alertes et missions, propositions du 19 novembre 2026

Complexité **4/5**. Sources ciblées : `AdminCaseController`, `AdminCaseService`,
`AdminMissionController`, vues de surveillance, liste et fiche des missions,
instantané de départ et rapports actuels. Alertes, liste des missions et fiche
gardent leurs pages et URL ; les filtres restent des filtres du même relevé.
L’instantané et les réglages/interventions secondaires peuvent se déplier
sur place, sans cacher l’état de la mission ni la cargaison nécessaire au contrôle.

Dans le même document Figcraft de bureau :

- **50 · L10E · A — Surveillance directe** : missions dans un tableau de huit
  colonnes sur ordinateur, fiches sur écrans étroits. Seuils et intervention
  dépliables, valeurs et repères essentiels visibles. Bénéfice : comparaison des
  missions ; limite : place plus étroite pour les noms et les trajets.
- **51 · L10E · B — Missions en fiches** : mêmes informations en fiches,
  réglages et formulaire d’intervention ouverts dans le fil. Bénéfice : lecture
  détaillée et contrôles accessibles ; limite : page plus longue.

Couverture équivalente : **74 cadres, 37 par direction**, **1 285 liens inspectés**,
six départs de prototype. Alertes, liste et fiche en 1440/768/390 ; états en 390 :
seuils ouverts/enregistrés/refusés, aucun signal, dossier relié avec nouvel élément
après clôture, missions filtrées/vides, historique, pagination, actualisation
réussie/indisponible, départ conservé/absent, intervention ouverte/renseignée/
enregistrée/refusée/périmée, échéance dépassée, retour, mission terminée, combat,
accord attendu, espionnage, bilan du transport, menu et destinations conservées.
`M` revient au plan et permet la comparaison A/B. Douze exports des trois vues
ordinateur/téléphone ; principaux aperçus examinés. Aucun texte hors parent,
chevauchement ou lien manquant dans les cadres inspectés ; parité conservée.
Les 44 pages antérieures gardent leurs noms et nombres de cadres ; 46 pages et
348 variables après ajout. Fichier enregistré, document propre et plan A affiché
dans la fenêtre de bureau contrôlée.

Contrats à conserver après choix : signaux de répétition sur 24 h, exclusions
actuelles, regroupement par paire pour les transports et par cible pour les
attaques, limites de 100 par catégorie. Seuils par univers : retard 1–10 080 min,
compteurs 1–100 000. Missions : recherche joueur/cible, univers, mission, état,
pagination 50 et relevé actualisé toutes les 20 s avec protections de consultation.
Départ conservé distinct de l’état courant ; réserves comprises dans l’hydrogène.
Motif et confirmation, CSRF, clé de demande, version, contrôles des combats,
du rappel et du retour forcé restent ceux du serveur ; aucune livraison forcée
immédiate ni modification de composition/cargaison. Rapports et audits gardent
leurs accès administratifs. Aucune règle de jeu ni valeur d’équilibrage modifiée.

Limites : données fictives, champs dessinés et actions simulées ; extraits de listes.
Le formulaire renseigné illustre la flotte 84. Les autres identités, sécurité,
univers et rapports non détaillés renvoient aux cadres de référence ou aux vues
déjà validées. Le bilan du transport illustre un état de la fiche, pas une nouvelle
page du jeu. Les thèmes, accès et retours serveur devront être recettés après choix.
Preuves dans le dossier de conception : `lot-10E-plan.json`, `lot-10E-manifest.json`,
`lot-10E-verification.json`, états avant/après/enregistrement et
`lot-10E-{A,B}-{alerts,missions,detail}-{1440,390}.png`.
L10E A est ensuite validé explicitement ; réalisation ci-dessous. L10 reste
en cours, plan global **15/18**.

## L10E A — Réalisation locale du 19 novembre 2026

Complexité **4/5**, conservée après examen des styles partagés et des écarts de
recette. Page 50 A retenue. Trois pages dédiées conservées : alertes, liste des
missions et fiche d’une mission ; les filtres sélectionnent le même relevé et
gardent leur URL. Les seuils et interventions secondaires se déplient avec
`<details>` natif ; valeurs actuelles, état, unités, cargaison et avertissements
restent disponibles sans ouvrir un formulaire. Aucun nouvel onglet ni contrôleur.

La liste garde ses huit colonnes et en-têtes HTML ; sous 1100 px, chaque ligne
devient une fiche avec ses sept valeurs libellées. La situation précède le départ
conservé, les unités et le fret, puis les rapports et l’intervention. La quantité
reste visible auprès du vaisseau et de son rang sur téléphone. Les textes longs
passent à la ligne. Motif, confirmation, clé de demande, CSRF, version attendue,
droits, verrous, audits et contrôles métier restent ceux du serveur. Aucune
requête, période d’actualisation, formule ou valeur d’équilibrage n’est modifiée.

Fichiers du lot :

- `app/templates/admin/layout.html.twig`, `surveillance.html.twig`,
  `missions.html.twig` et `mission.html.twig` ;
- `app/assets/app.js` et nouveau `app/assets/styles/admin-surveillance-workspace.css` ;
- nouveau `app/tests/browser/admin-surveillance-refonte.spec.mjs`, adaptations de
  `admin-cases-alerts.spec.mjs`, `admin-interventions.spec.mjs` et
  `admin-missions-notifications.spec.mjs` dans le même répertoire ;
- `README.md`, `docs/ARCHITECTURE.md`, `docs/FEUILLE_DE_ROUTE.md`,
  `docs/INVENTAIRE_INTERFACES.md` et le présent suivi.

Vérifications locales :

- `make validate`, `make lint`, `make assets` et `git diff --check` réussis ;
- **34 tests PHP / 149 assertions**, `AdminInterventionTest` et `AdminCaseTest` ;
- **huit scénarios navigateur consolidés** : trois nouveaux et cinq existants,
  couvrant alertes/seuils, univers, missions/états, pagination 50 + 2, clavier,
  rapports privés, consultation sans mutation, interventions et retour forcé ;
- actualisation automatique suspendue au focus ou lors d’une sélection, saisie
  conservée, échec de récupération laissant le dernier relevé, reprise manuelle ;
- filtres, pagination, état vide et enregistrement des seuils sans JavaScript ;
- droits/CSRF, demande répétée et formulaire périmé, audit, rapport de combat ou
  d’espionnage associé à la bonne mission ; aucune garantie nouvelle de concurrence ;
- **18 captures finales**, trois vues × 1440/768/390 × clair/sombre, contrôles Axe
  ciblés sans exclusion de règle, formulaires ouverts et fermés, absence de
  débordement horizontal. Captures ordinateur/mobile examinées, quantité visible
  dans la composition vérifiée par sa position à l’intérieur du tableau.

Corrections de recette : ancien nom accessible d’un rapport dans un test, nom
de planète synthétique limité aux 80 caractères du modèle et sélecteurs des
listes déroulantes. Le motif était encore contraint par le style des champs
courts : règles séparées puis contrôle réussi. La revue des captures a également
mené à afficher les trois colonnes d’unités ensemble ; recette visuelle relancée
avec assertion de position de la quantité. Aucun contrôle n’a été affaibli.
La sonde de santé du conteneur `database-test` a refusé son propre compte ; la
base dédiée restait accessible et les tests PHP ont été exécutés directement,
avec leurs vérifications d’hôte et de nom. Aucune configuration n’a été modifiée.

Preuves : `output/playwright/admin-surveillance-refonte/`, journaux `validate`,
`lint-final`, `assets-review`, `php-final`, `browser`, `browser-final`,
`browser-visual`, `browser-review` et captures de `recette-verifiee`.
Données synthétiques et bases isolées ; aucune intervention sur les flottes ou
univers habituels. CI GitHub, fusion et déploiement restent distincts ; aucun
gain de performance mesuré.

## L10F — Gestion des univers, propositions du 19 novembre 2026

Complexité **4/5**. Sources ciblées : liste, création, gestion, calendrier et
configuration des univers, `AdminController`, `AdminService`,
`UniverseCreationService` et leurs brouillons actuels. Les trois pages restent
dédiées, avec leurs URL. Recherche et état restent des filtres ; calendrier et
règles gardent leurs formulaires distincts sur la même fiche. La suppression
secondaire reste dépliable, avec ses conséquences et sa confirmation par nom.

Dans le même document Figcraft de bureau :

- **52 · L10F · A — Univers en lignes** : univers sur toute la largeur,
  calendrier puis configuration dans le fil, champs regroupés sur ordinateur.
  Bénéfice : lecture régulière ; limite : plus de défilement entre les univers.
- **53 · L10F · B — Fiches et configuration** : univers en fiches par deux,
  repères auprès de la création, calendrier à côté des règles sur ordinateur.
  Bénéfice : réglages rapprochés ; limite : colonne de calendrier plus étroite.

Couverture équivalente : **64 cadres, 32 par direction**, **1 330 liens inspectés**,
six départs de prototype. Liste, création et gestion en 1440/768/390 ; états en
390 : filtres, absence de résultat, pagination, nom long, création renseignée/
refusée/réussie, profils, calendrier renseigné/refusé/enregistré, règles renseignées/
refusées/enregistrées, valeurs hors plage conservées, univers fermé, confirmation
de suppression vide/renseignée, refus et suppression simulée, menu et références.
`M` revient au plan ; comparaison A/B depuis celui-ci. Douze exports des trois
vues ordinateur/téléphone ; principaux aperçus examinés. Aucun texte hors parent,
chevauchement ou lien manquant ; parité conservée. Les 46 pages antérieures gardent
noms et nombres de cadres ; 48 pages et 348 variables après ajout. Document propre,
enregistré et affiché sur le plan A dans la fenêtre Figcraft de bureau contrôlée.

Contrats actuels à conserver : liste paginée par 50 ; nom 3–100 caractères, code
unique de 64 au maximum, profils Normal/x2–x10/Alpha ×40/Beta ×20 ; une à cinq
galaxies, un à cent systèmes et neuf positions par système. La création ouvre
immédiatement l’univers, avec le calendrier dans sa fiche. Dates en UTC, retrait
de la fermeture par champ vide, univers fermé non rouvert. Cinq vitesses ×0,01–×40,
limites de colonies/vaisseaux, fonctionnalités et valeurs historiques hors plage
conservées sans ajustement automatique. Les brouillons, POST, CSRF et audits
restent ceux de l’existant. Aucun changement de règle ni valeur proposé.

Limites : données fictives, champs dessinés et opérations simulées. Les saisies
détaillées portent sur Omega ; le formulaire de création illustre le profil
Normal. Les autres choix de profil, univers et outils (mesures, catalogues,
modèles de boîtes) restent des références vers leurs pages actuelles. Delta-2
conserve son identité après création simulée. Aucun univers réel créé, modifié
ou supprimé ; thèmes et retours serveur à recetter après choix.
Preuves dans le dossier de conception : `lot-10F-plan.json`, `lot-10F-manifest.json`,
`lot-10F-verification.json`, états avant/après/enregistrement et
`lot-10F-{A,B}-{universes,new,manage}-{1440,390}.png`.
Choix A/B attendu pour L10F ; L10 en cours, plan global **15/18**.

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
| L7B | Page 29 implémentée et vérifiée localement | Version consolidée validée explicitement | 20 PHP, six parcours navigateur consolidés et 30 captures ; bilan ci-dessus | CI complète, intégration et déploiement distincts |
| L7C | Implémenté et vérifié localement | Conception validée ; spirale retenue explicitement pour les distances | Deux vues persistantes, gestes et moteur conservés, bilans par camp ; recette ci-dessous | CI complète, fusion et déploiement distincts |
| L7D | A implémentée et vérifiée localement | A choisie explicitement après reprise depuis les rapports actuels | 15 PHP / 98 assertions ; cinq parcours navigateur consolidés ; bilan ci-dessous | CI complète, fusion et déploiement distincts |
| L7E | A implémentée et vérifiée localement | Page 36 A validée explicitement après reprise de la messagerie et des classements | 90 PHP / 569 assertions ; neuf parcours navigateur consolidés ; bilan ci-dessus | CI complète, fusion et déploiement distincts |
| L8 | A implémentée et vérifiée localement | Page 38 A validée explicitement | 23 PHP / 244 assertions ; 16 parcours navigateur consolidés ; bilan ci-dessus | CI complète, fusion et déploiement distincts |
| L9 | A implémentée et vérifiée localement | Page 40 A validée explicitement | 7 PHP / 127 assertions ; 5 parcours navigateur consolidés ; bilan ci-dessus | CI complète, fusion et déploiement distincts |
| L10 | L10A/B/C/D/E A réalisés et vérifiés localement ; L10F A/B préparé | A choisi explicitement pour L10A/B/C/D/E ; choix L10F attendu | Bilan L10E : 34 PHP / 149 assertions, huit scénarios navigateur consolidés, 18 captures ; L10F pages 52/53, 64 cadres et 1 330 liens | Réalisation L10F puis autres rubriques administratives ; CI, fusion et déploiement distincts |
| L11 | Inventorié | Aucune nouvelle | Non engagé pour cette refonte | Examiner le wiki sans recommencer ses pages dédiées |
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
L7B page 29 est intégrée localement dans `81807df` ; L7C est enregistré dans `50f3965`, réalisé selon la
validation finale et la décision de géographie. L7D A est intégré dans `5523ac4`.
L7E page 36 A est validée explicitement, intégrée dans `557061e` et vérifiée localement ;
la messagerie et les classements existants restent la base de leur présentation.
L8 page 38 A est validée explicitement, réalisée et vérifiée localement ; bilan ci-dessus.
L9 page 40 A est validée explicitement, réalisée et vérifiée localement ; bilan ci-dessus.
Le plan reste à 18 ensembles : quinze sont réalisés, trois restent à traiter.
Vue générale avait été finalisée en avance ; L7A est désormais complet localement. Les sous-lots ne sont pas comptés comme des ensembles supplémentaires.
Les validations précédentes restent limitées à leurs lots.
Aucun push, PR, fusion ou déploiement n’est autorisé par le seul changement de
branche. La mission globale reste non terminée.
