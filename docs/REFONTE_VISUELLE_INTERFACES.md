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
| L7A | Vue générale, colonies/comparaison et abandon | 3/5 · 4/5 | Conserver la référence approuvée ; examiner les autres états et confirmations. |
| L7B | Bâtiments, Recherche, Chantier, Défenses, Guide | 3/5 · 4/5 | Filtres, prérequis, files et préparation ciblée. |
| L7C | Cartes classique/V2 et simulateur | 4/5 · 4/5 | Découvertes, confidentialité, clavier et parcours préparation/résultats. |
| L7D | Rapports, archives et détails | 3/5 · 4/5 | Participation, confidentialité, pagination et ancres utiles. |
| L7E | Messages/recherche, classements et profils | 4/5 · 4/5 | Canaux, non-lus, saisie, destinataires et informations publiques. |
| L8 | Accueil, accès, récupération et univers joueur | 4/5 · 4/5 | Sous-lots ; validations, erreurs, redirections et SEO. |
| L9 | Feedback, patchnotes et documents complémentaires | 2–3/5 · 3/5 | Pièces jointes, informations publiées et formulaires. |
| L10 | Toutes les interfaces administratives recensées | 4/5 · 4/5 | Sous-lots par groupe ; conserver organisation, droits, recherches et formulaires dédiés. |
| L11 | Wiki : accueil, chapitre, recherche et références | 3/5 · 3/5 | Pages déjà séparées, anciens fragments et formules dépliables. |
| L12 | Cohérence, erreurs et composants communs restants | 4/5 · 4/5 | Revue transversale ; adaptations communes rattachées au lot consommateur. |

Le tableau comporte **18 lots prévus** : trois lots réalisés localement (L1A/L1B/L1C),
15 restants. Certains ensembles complexes seront subdivisés pendant leur analyse ;
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

## Reprise et états de couverture

| Périmètre | État | Validation | Réalisation / vérifications | Limite restante |
| --- | --- | --- | --- | --- |
| Vue générale Figcraft | Conception de référence conservée | Choix explicite de l’utilisateur | Page inchangée, comparaison effectuée | Ne valide pas automatiquement l’implémentation web ni les autres écrans |
| L1A | B implémentée et vérifiée localement | B choisie explicitement | Trois pages, PHP ciblé, recette responsive et captures ; bilan ci-dessus | CI complète, intégration et déploiement distincts |
| L1B | A implémentée et vérifiée localement | A choisie explicitement | Trois pages et brouillon, PHP ciblé, recette responsive et captures | CI complète et intégration distinctes |
| L1C | A implémentée et vérifiée localement | A choisie explicitement | Quatre pages, confirmations natives, 83 PHP et recette responsive ; captures | CI complète et intégration distinctes |
| L2 à L11 | Inventorié | Aucune nouvelle | Non engagés pour cette refonte | Examiner chaque lot sans recommencer les navigations déjà livrées |
| L12 | Inventorié ; socle partiellement analysé | Aucune validation globale | Revue transversale non exécutée | Inventorié ne signifie pas vérifié |

Les commits restent sur `feature/refonte-visuelle` ; L1B A est enregistré dans
`1e390a9`. L1C A est réalisé et vérifié localement. Prochain périmètre :
L2, à découper autour du parcours de flotte avant deux propositions Figcraft.
Les validations précédentes restent limitées à leurs lots.
Aucun push, PR, fusion ou déploiement n’est autorisé par le seul changement de
branche. La mission globale reste non terminée.
