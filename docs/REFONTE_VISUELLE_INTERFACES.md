# Refonte visuelle des interfaces — suivi de mission

## État du 17 novembre 2026

Départ depuis `dev` à `e7acc17`, après récupération GitHub autorisée ; arbre
initial propre, `dev` identique à `origin/dev`. Branche de mission :
`feature/refonte-visuelle-guildes`. Les modifications du dépôt sont documentaires.

**Reprise de conception demandée par l’utilisateur : seule la page Figcraft
« Vue générale » est conservée et approuvée comme référence. Les anciennes
propositions ont été retirées par l’utilisateur.** Les deux nouvelles propositions
L1A sont préparées et attendent un choix explicite. Aucun changement d’interface
du jeu n’est implémenté par cette mission. Aucune migration, dépendance, PR,
fusion ou livraison n’est réalisée pour ce lot.

L’[inventaire de couverture](INVENTAIRE_INTERFACES.md) recense 170 routes du projet,
199 gabarits Twig et 52 contrôleurs Stimulus, avec les paramètres et états qui ne
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

Le document ne comporte que trois pages :

1. **01 · Vue générale — Validée** : conservée sans modification ;
2. **02 · Guildes · A — Horizon partagé** : première proposition nouvelle ;
3. **03 · Guildes · B — Échanges et gestion** : deuxième proposition nouvelle.

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

Les complexités seront réévaluées avant chaque sous-tâche. Les lots dépendants
attendront l’intégration de leur prédécesseur et la CI complète ; les volumes de
routes incluent actions et fragments et ne fixent pas la taille des sous-lots.

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

**Recommandation : A**, pour sa continuité avec la Vue générale approuvée et la
lisibilité des listes. Aucun choix des nouvelles propositions n’est encore validé.
Une combinaison substantielle devra être consolidée puis présentée avant code.

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

## Vérifications et prochaine réalisation

Contrôles de cette préparation :

- `make status` et lecture du routeur via le Makefile ; correspondance des 170
  routes du projet et recensement des gabarits/contrôleurs d’interaction.
- Enregistrement de l’état laissé par l’utilisateur puis création via le MCP
  de bureau ; structures avant mise en couleur, composants et liens simulés.
- Comparaison du contenu complet de la page Vue générale avant/après : inchangée.
  Le document final compte trois pages.
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

Après validation : réutiliser routes, formulaires et `DiplomacyPageView`, limiter
les styles aux trois pages approuvées et vérifier les autres consommateurs de
tout fragment commun touché. Conserver les permissions, CSRF, versions,
transactions, isolation des univers, confirmations, saisies et actualisations.
Performance : non mesurée. Le thème clair n’est pas redessiné ; son fonctionnement
et ses usages communs seront contrôlés après intégration.

Contrôles à adapter au diff réel : `make validate`, `make lint-static`, `make assets`,
PHP ciblé et scénarios navigateur de guilde/grades/rivalités/navigation/profils,
`git diff --check`, puis CI complète avant fusion. Références existantes :
`GuildTagPlayerReportTest.php`, `DiplomacyAccessPolicyTest.php`, `GuildRankTest.php`,
`PersonalDiplomacyTest.php`, `guild-ranks.spec.mjs`, `personal-rivalries.spec.mjs`,
`diplomacy-empty-state.spec.mjs` et `navigation-final.spec.mjs`.

La recette devra couvrir accès direct/rechargement/retour arrière, navigation
active, recherche/curseurs, retours après action, refus et univers distincts,
présence privée, aperçu non lu, actualisations sans perte de saisie, rôles,
clavier/focus, titres/libellés, images et contrastes. Captures réelles attendues à
1440/768/390 px, avec contrôle à 320 px et du thème clair. Les essais avec écriture
utiliseront des données et une pile jetables. La base de test partagée signalée
non saine par `make status` n’a pas été réparée ou utilisée par cette préparation.

## Reprise et états de couverture

| Périmètre | État | Validation | Réalisation / vérifications | Limite restante |
| --- | --- | --- | --- | --- |
| Vue générale Figcraft | Conception de référence conservée | Choix explicite de l’utilisateur | Page inchangée, comparaison effectuée | Ne valide pas automatiquement l’implémentation web ni les autres écrans |
| L1A | Propositions préparées, en attente de validation | Aucune pour A/B | Conception et contrôles du document ; aucun code du jeu | Choix explicite avant implémentation |
| L1B à L11 | Inventorié | Aucune nouvelle | Non engagés pour cette refonte | Examiner chaque lot sans recommencer les navigations déjà livrées |
| L12 | Inventorié ; socle partiellement analysé | Aucune validation globale | Revue transversale non exécutée | Inventorié ne signifie pas vérifié |

Prochaine action : choix A ou B et ajustements du lot L1A. Après validation,
implémenter localement ce lot et présenter des captures réelles. CI, fusion et
déploiement restent distincts. Aucun push ni déploiement n’est autorisé par la
seule récupération GitHub précédente. La mission globale reste non terminée.
