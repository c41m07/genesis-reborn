# Architecture et parcours applicatifs

## Unités économiques et livraisons en défense

`EconomicUnitCapacity` dérive les stocks et réservations à partir des FleetShip,
des files locales et de `Fleet.navigation.economic_slots`. La requête ciblée
`GameRepository.economicUnitStocks` charge seulement les unités civiles présentes
ou attendues de la participation sur la planète. Aucun stock d’affectation dupliqué
ni changement SQL. `CatalogueProductionContext` conserve cette lecture brute pour
les cartes et les segments de ressources ; les modificateurs sont recalculés à
chaque frontière d’événement ou de vacances.

`EconomicUnitDeployment` réserve ou libère sous les verrous de jeu existants,
après matérialisation de l’ancien état. `ResourceService` ajoute les deux sources
civiles sans les soumettre à l’entretien ni au rendement électrique des usines.
Les sources restent identifiables dans le bandeau ; les surplus existants sont
conservés. Les pertes sont matérialisées puis appliquées à la clôture du combat.

`LocalGarrison` raccorde les livraisons du chantier et du Stationnement à
`CombatEventService.joinOpenDefense`. Toute la flotte locale disponible participe,
carburant et fret conservés, sans copie d’unités ni trajet fictif. La date payée du
chantier est prise en compte ; un renfort ajouté après des rounds déjà résolus
ne peut pas réécrire ces rounds. Une fermeture différée du service de combat
évite le cycle entre résolution de combat et livraison des files.
Voir les [transitions, contrôles et limites](UNITES_ECONOMIQUES.md).

## Organisation et environnement

Le code Symfony, les templates, assets, migrations, tests et dépendances sont
dans `app/`. Docker est sous `docker/`, les outils du dépôt sous `scripts/`.
Make et les points d’entrée Compose restent à la racine ; npm s’exécute dans app.

Les fichiers locaux `.env`, `.env.local`, `.env.dev` et `.env.test` restent à la
racine. Leurs homonymes dans app sont des liens vers ces fichiers, pas des copies.
Modifier un lien modifie le même fichier. Ne jamais publier les secrets.
`make configure` prépare les liens .env et .env.local ; Compose monte ces fichiers
dans /app en lecture seule. Les modèles .env.example et .env.prod.example sont
des exemples, pas la preuve d’une configuration serveur.

L’architecture suit contrôleur → service métier → manager → repository.
Catalogues YAML/JSON et cartes JSON restent hors base ; l’import est explicite.
Les possessions et recherches sont isolées par participation à un univers.
Les événements persistés dans l’outbox permettent la progression sans navigateur.
Voir [l’exploitation](EXPLOITATION.md) et [les règles](REGLES.md).

## Registre des prérequis — version 2

`CatalogAccess` lit uniquement les clés `v2:` du registre JSON et contrôle son
marqueur de version. Les nouveaux empires partent du registre courant ; une
participation existante reste à requalifier explicitement. La synchronisation
ne bascule pas ce registre et laisse se résoudre les commandes déjà payées.
La commande `app:catalog:preserve-unlocks` est en lecture seule par défaut ;
`--apply` reprend les accès sous verrous participation puis univers, après
contrôle des surcharges. Aucun changement SQL pour ce lot. L’interface et
les commandes utilisent le même service d’accès ; les conditions complètes
sont rangées par rang, y compris Basique, sans héritage d’un autre rang.
Le lancement d’une recherche vérifie aussi le niveau du laboratoire du rang
demandé sur la colonie choisie, à partir des niveaux déjà lus. Le contrôle ne modifie pas
le registre des déblocages et s’applique uniquement aux nouvelles commandes.
La reconstruction des empires oubliés conserve son chemin particulier et ne
crée pas un accès joueur par la seule livraison d’une unité.
Voir la [grille et la bascule](DEBLOCAGES.md) et le [suivi](PROGRESSION_CARBURANT_UNITES.md).

## Durées et carburant des opérations

`OperationCalculator` reste le calculateur commun des devis, maxima et paiements.
La réduction est soustraite en pourcentage avant division, pour conserver
l’arrondi supérieur sans créer une seconde artificielle au plafond de 70 %.
Les durées et coûts des QueueItem acceptés restent la référence de leur résolution.

`FleetNavigation.freeze` ajoute les coefficients de carburant réellement utilisés
au départ dans `fuel_profiles`, par slug et rang, sans changer la version de route.
`FleetFuel` restaure ces coefficients pour FleetRecallPlanner et FleetStationing,
sans modifier quantités, vitesses ni réserve payée. Si le profil est présent mais
incomplet, le nouveau trajet est refusé ; aucune tarification courante de repli.
Pour les missions antérieures, la référence de `config/legacy/` est combinée aux
surcharges persistées. Une ancienne personnalisation modifiée depuis son départ
n’est pas reconstruite avec certitude : voir la [procédure et ses limites](DUREES_CARBURANT.md#commandes-payées-et-missions-historiques).

`app:balance:compare-operations` produit une comparaison JSON sans lecture de compte
ni écriture en base, avec les calculateurs réels et un univers transitoire ×1.
Aucune migration SQL pour ces compléments. Déploiement cohérent du web et des
workers requis avant toute activation ; aucun déploiement Alpha dans cette mission.

## Études économiques hors base

`app:balance:compare-economy` orchestre `Balance/ProgressionEconomyStudy` et
`MilitaryEconomyStudy`. Le candidat de prix crée uniquement des surcharges sur
un `Universe` transitoire. `FundedProgressionSimulation` construit un graphe
d’objectifs et prérequis, paie avec les stocks disponibles au départ, conserve
le devis et avance aux fins d’opérations et frontières de flux.

`ResourceService.project` exige un contexte du même objet planète, un inventaire
explicite et un multiplicateur d’événement fini. Cette entrée sans lecture de compte
réutilise la simulation normale ; `FactoryEconomy` peut retourner les intégrales
par segment avec une option désactivée par défaut. Les appels de jeu conservent
leur résultat et leurs effets existants. Les tests vérifient cette équivalence,
les frontières, le bilan des ressources et l’absence d’accès aux dépôts de données.
Les [scénarios et limites](SIMULATIONS_PROGRESSION_ECONOMIE.md) sont distincts des
règles de jeu ; aucune simulation n’écrit un accès, un paiement ou un résultat de combat.

## Comptes et courriels

Les nouveaux comptes reçoivent deux messages : bienvenue et confirmation. Seul le lien de confirmation permet d’activer l’accès ; il ouvre une page demandant une confirmation explicite par formulaire POST protégé contre les CSRF. L’ouverture automatique par un scanner de mails ne valide donc pas le compte.

Les jetons aléatoires de 256 bits ne sont stockés que sous forme de hachage SHA-256. Ils expirent après 24 heures, sont à usage unique et un renvoi invalide le précédent. Le formulaire de renvoi utilise une réponse neutre et une limite de cinq demandes par heure et par IP. Le compte reste non vérifié si le transport échoue ; une trace sans adresse ni jeton est journalisée, et le renvoi permet de réessayer.

Les emails de compte disposent de trois tentatives synchrones au total après
exception de transport. L’échec final déclenche une trace critical sans données
sensibles. À l’inscription, un échec de confirmation conserve le compte et
redirige vers le formulaire de renvoi avec un avertissement. Les états de
transport sont journalisés ; aucune file durable ni preuve de réception n’est
fournie par ce mécanisme. Limites et diagnostic : [DIAGNOSTIC_EMAILS.md](DIAGNOSTIC_EMAILS.md).

### Configuration

- `MAILER_DSN` : transport Symfony Mailer. La valeur locale de `.env.example` cible MailHog ; elle ne prouve aucun transport en production.
- `MAILER_FROM` : expéditeur autorisé par le fournisseur. La valeur de repli `no-reply@genesis-reborn.local` est réservée au développement.
- `DEFAULT_URI` : URL publique complète du site, en HTTPS sur le serveur. Les liens de confirmation utilisent cette valeur pour éviter de reprendre un nom d’hôte fourni par une requête.

Le fournisseur, l’expéditeur réel, la délivrabilité et les URL de l’Alpha restent à vérifier sur le serveur. Aucun secret n’est versionné et aucun envoi externe n’a été déclenché par les tests.

Le compte artificiel créé par `make seed-demo`, commande réservée au développement local, est marqué vérifié sans envoi de mail. Cette préparation ne concerne pas les inscriptions par l’interface.
## Migration des comptes et réinitialisation

La migration `Version20261106130000` ajoute `player.email_verified_at` et la table de jetons. Elle marque les comptes déjà présents comme vérifiés afin de conserver leur accès. Les nouveaux comptes ont une date nulle jusqu’à confirmation. La migration a été autorisée pour les bases locales de test ; son application serveur reste une opération à planifier.

Le retour arrière supprime la table des jetons et la colonne. Il perd les statuts de confirmation et doit être accompagné du retour à la version précédente du code. Ne pas appliquer ce retour sur une base active sans sauvegarde et décision d’exploitation.

### Réinitialisation du mot de passe

Le formulaire `/mot-de-passe/oublie` retourne le même message et la même redirection pour une adresse connue ou inconnue. Les demandes sont limitées à cinq par heure et par IP. Pour une adresse connue, un jeton aléatoire de 256 bits est créé ; seul son hachage SHA-256 est conservé, avec expiration après une heure. Une nouvelle demande invalide tous les anciens liens du compte.

Le lien utilise `DEFAULT_URI`, indépendamment de l’hôte de la requête, et l’expéditeur utilise `MAILER_FROM`. Un lien incorrect, expiré ou déjà consommé conduit à l’écran de lien invalide. Le mot de passe n’est changé qu’après validation du formulaire et du jeton, puis tous les jetons du compte sont supprimés. La réinitialisation ne confirme pas l’adresse email d’un nouveau compte.

La demande et la consommation prennent les verrous dans le même ordre : joueur puis jetons. Après verrouillage du joueur, une lecture verrouillée du jeton vérifie son état courant, y compris s’il a été invalidé par une autre connexion depuis la première lecture. Cette lecture ne réutilise pas un ancien instantané de transaction.

Si le transport mail échoue, l’erreur est journalisée sans adresse ni jeton ; le mot de passe reste inchangé et la réponse publique reste neutre. Une nouvelle demande permet de réessayer. Les tests vérifient les réponses HTTP et le contenu des messages, sans prétendre mesurer les délais ni la délivrabilité du serveur Alpha. La configuration SMTP, l’expéditeur et l’URL HTTPS réels restent à vérifier sur ce serveur. Aucune migration supplémentaire n’est nécessaire pour ce parcours.
## Wiki et sources embarquées

Le guide est accessible aux comptes connectés, même sans participation à un
univers. L’accès Wiki reste dans l’en-tête, près de Feedback. `/wiki` est l’accueil,
`/wiki/chapitres/{chapter}` affiche un chapitre et `/wiki/recherche?q=…` affiche des
résultats avec extraits et liens de lecture. Chaque destination est rendue par le
serveur, avec un titre propre et le même sommaire. Aucune section de manuel n’est
masquée pour simuler une page. La recherche reste utilisable sans JavaScript.

`WikiManual` lit les sources uniques `docs/wiki.json`, `docs/REGLES.md` et
`docs/DECISIONS.md`. Le JSON fini du manuel est décodé une fois par préparation ;
seules les métadonnées du sommaire et le contenu demandé vont au template.
L’accueil ne transmet aucun corps complet : `chapters(true)` ajoute seulement
un aperçu du premier paragraphe, borné à 160 caractères plus une éventuelle
ellipse. Les autres pages gardent `chapters()` sans aperçu pour leur sommaire.
Une fiche ne transmet qu’un corps de chapitre ; la recherche parcourt le document
de référence et ne transmet que des extraits
bornés à 240 caractères. Aucun chargement de données de jeu, schéma ou dépendance
n’est ajouté. Aucun gain de performance mesuré n’est revendiqué.

Les formules et exemples utilisent les éléments natifs `details` et `summary`.
Les références, conservées sous `/wiki/references/regles` et
`/wiki/references/decisions`, restent du texte brut échappé et sont présentées par
sections repliables. Les identifiants de référence se limitent à ces deux valeurs :
aucun chemin fourni par le visiteur n’est lu. Ne pas ajouter `raw` aux paragraphes
ou références. Les sources gardent leurs paragraphes historiques ; le guide
n’additionne pas leurs anciens réglages aux règles récentes. Les valeurs propres
à un univers restent celles affichées par le jeu. Aucune règle ou valeur du
manuel n’est changée par cette navigation.

Les GET historiques `?chapter=` et `?q=` redirigent vers les pages dédiées.
Le serveur ne reçoit pas le fragment d’une URL. `wiki_chapters` traduit donc
les anciens `#wiki-{id}` et `#wiki-title-{id}` au chargement et au changement de
fragment, en conservant la recherche et en remplaçant l’entrée d’historique pour
éviter une boucle au retour arrière. La liste des destinations est produite par
le routeur depuis les seuls chapitres connus. Sans JavaScript, les cartes de
l’accueil portent ces mêmes identifiants et donnent un lien de lecture ; les
résultats de recherche gardent aussi leurs repères. Le sélecteur mobile soumet
un GET natif ; JavaScript peut seulement déclencher cette soumission à la sélection.
Le sommaire automatique est désactivé sur ces pages, les ancres utiles au sommaire
et à la lecture restent disponibles.

Le contrôleur lit `../docs/` depuis `app/` : montage `/docs` en développement et
copie `/docs` en production. L’image de production copie seulement ces trois
fichiers de contenu du wiki ; conserver les exceptions de `.dockerignore`.
Après une décision de gameplay, relire les rubriques touchées dans le même lot,
sans recopier les règles chiffrées dans les templates.

Validation : tests HTTP `WikiPagesTest`, parcours `wiki.spec.mjs` et parcours
transversal `ui-journey.spec.mjs`. Le bilan de navigation distingue les contrôles
locaux, la CI, l’intégration et l’activation locale. Les contrôles ciblés ne
constituent pas un audit RGAA complet. Aucun déploiement n’est effectué par l’ajout
de ces pages.

## Compte à rebours de fermeture

La date `Universe.endsAt` enregistrée en base fait autorité. Le composant apparaît lorsque le statut est actif et qu’il reste **au maximum 24 heures**, sur la carte de l’univers et dans les pages du joueur ayant cet univers sélectionné. Sans date, ou avec une date à plus de 24 heures, il est masqué. Il peut apparaître sans rechargement lorsque le seuil est franchi.

Le rendu initial utilise `ClockInterface`. Le navigateur progresse avec son horloge monotone (`performance.now`), indépendamment de son heure civile et de son fuseau. Les dates échangées portent leur décalage UTC ; l’échéance lisible est explicitement affichée en UTC. Une lecture authentifiée, sans écriture et sans cache, de `/univers/{id}/fermeture` resynchronise au démarrage, chaque minute et au retour dans l’onglet. Un changement de calendrier peut donc prendre jusqu’à une minute à apparaître dans un onglet visible, hors délai réseau. Une suspension du navigateur est rattrapée à son retour. La précision reste limitée par la latence réseau.

À zéro, le temps ne devient pas négatif : « Échéance de fermeture atteinte » est affiché. Ce texte ne prétend pas que le worker a déjà persisté le statut fermé. La fermeture et les contrôles d’accès restent intégralement serveur ; aucune durée ni règle ne change. Un statut serveur non actif masque le compte à rebours. En cas d’échec de lecture, la dernière échéance connue continue de défiler avec une mention d’actualisation indisponible. Sans JavaScript, la valeur initiale reste consultable et un rechargement manuel est indiqué.

Le compteur n’est pas une région vocale actualisée chaque seconde (`role=timer`, `aria-live=off`). Les textes sont explicites et la mise en page s’adapte au mobile. Ces contrôles ciblés ne constituent pas un audit RGAA complet.

## Navigation, files et cartes

`ColonyView::data` reçoit la section déjà validée par `ColonyController`.
Après la synchronisation existante des files, il lit les niveaux servant aux
prérequis, puis prépare seulement le catalogue demandé. La vue générale ne
construit aucune carte. La section interne `tree` prépare uniquement le guide
technologique, sans cartes ni flottes. Les files de la colonie et la recherche
commune restent disponibles dans les pages de colonie ; les quantités du
catalogue sont chargées par groupe, sans lecture SQL par carte. Ces données
restent locales à l’appel et ne servent jamais d’autorisation d’écriture.
Le calcul et l’endpoint de la barre de ressources restent inchangés.
Les quatre catalogues utilisent `catalogue-workspace.css` pour la grille de quatre
cartes et sa disposition en lignes, avec les mêmes panneaux de rang et formulaires.
Les trois files, vols et arrivées occupent un bandeau fixe sous les ressources ;
leurs détails natifs réutilisent les données et liens d’annulation existants.
`catalog-sticky` gère l’ouverture exclusive, Échap et la hauteur du bandeau, puis
nettoie ses observateurs et écouteurs. `colony-live` préserve les panneaux ouverts
et restaure leur défilement après insertion, en plus des protections de saisie.
Le contrôleur de quantité couvre le rang complet pour actualiser le prix et la
durée affichés. Le devis conserve ses champs exécutables et ajoute `displayCosts`,
`displayDuration` et `missingResources` pour expliquer une unité indisponible.
Ces données de présentation n’autorisent jamais une commande ; la validation et
les calculs serveur existants restent requis. Les lectures de `ColonyView`, la
barre de ressources et ses actualisations sont conservées.
Les cartes Bâtiments et Vaisseaux transmettent un contexte économique immuable, préparé après
la synchronisation des files, aux calculs nominaux de ResourceService. Ce contexte
regroupe les lectures d’événements et de vacances pour cette préparation seulement ;
les appels existants et les snapshots de la barre ne l’utilisent pas.
`ResourceService::economicUnitRate` fournit le rendement nominal local d’une unité
civile active aux cadres et fiches, avec ce même contexte, sans relire les effectifs
ou les événements par carte. Les capacités et le crédit effectif des stocks restent
gérés par la simulation existante.
La liste des combats utilise ReportSummaryRepository pour éviter les rounds et
snapshots complets. Le lecteur détaillé et les archives conservent les entités
entières. Les profils consultent la ligne du snapshot quotidien sans enrichir
tous les scores à chaque affichage ; le recalcul quotidien reste partagé.
AdminReadRepository borne les listes de joueurs, d’audit, de feedbacks et
d’événements à 50 lignes. Les filtres sont appliqués avant LIMIT ; les compteurs
et curseurs de consultation portent sur la sélection complète. Les patchnotes
administratives sont paginées par leur repository. L’export complet du journal
lit 100 lignes à la fois, avec un identifiant plafond et neutralisation des
formules CSV. Les recherches immédiates Joueurs/Journal rendent leurs fragments
partagés et préservent le formulaire ; les réponses obsolètes sont ignorées.

L’espace Flottes utilise `FleetPageView` : `/flottes` prépare une page de 30
missions de la colonie, `/flottes/preparer` les unités et destinations de l’envoi
avec trois missions résumées, `/flottes/orbites` les demandes de la colonie,
`/flottes/quai` ses réserves et les stationnements en attente de tout l’empire.
Les compositions ont liste, création, modification et chargement distincts.
Les services métier restent communs ; les anciens paramètres et fragments sont
maintenus par les transitions décrites dans [le suivi](NAVIGATION_PAGES_DEDIEES.md).
Le paramètre `fleet` situe un vol autorisé avant la lecture bornée des détails.

L2A A ajoute `fleet-workspace.css`, limité à `.fleet-redesign` sur Missions,
Préparer et Rappel. Les vols gardent leur composant partagé et leurs données :
`fleet_page == missions` distingue le bouton de détails du titre, y compris dans
les fragments actualisés. Le rendu de Vue générale et des catalogues est conservé.
La navigation réutilise `ui/space_navigation` et son menu natif pour ces trois
destinations. `fleet-wizard` déplace le focus vers le titre uniquement après un
changement explicite d’étape ; la connexion du contrôleur ne prend pas le focus.
Le devis reste sous `fleet-preview`, avec son blocage d’envoi existant. Aucune
lecture, validation ou transaction de Flottes n’est changée.

L2B étend `.fleet-redesign` aux orbites, au quai et à la reprise ;
`fleet-harbour.css` limite leur composition à `.fleet-harbour`. La réserve reste
distincte des unités à quai et des stationnements de tout l’empire, sans lecture
supplémentaire ni agrégation métier. Les formulaires GET de devis et POST de
confirmation gardent leurs contrats. Les demandes ne sont plus dans un dépliable ;
`orbit-requests` actualise leur compteur indépendamment du target `details`, qui
reste accepté pour les anciennes vues. Les garde-fous de focus, d’échéance et
de contexte restent ceux du contrôleur et des services existants.

L2C étend le menu et `.fleet-redesign` aux compositions. `fleet-compositions.css`
reste limité à `.fleet-compositions` : lignes pour les modèles, tableau d’édition
et choix/disponibilités côte à côte lorsque la largeur suffit. Chaque champ est
rendu une seule fois ; les lignes du tableau deviennent des groupes de trois
rangs sur mobile, avec les rôles et en-têtes accessibles conservés. Aucun nouveau
contrôleur JavaScript, aucune lecture ou écriture supplémentaire : formulaires,
versions, empreinte de disponibilité et contrôle exact de l’envoi restent existants.

L3 A réorganise les fragments existants de l’Inventaire. `inventory-workspace.css`
est limité à `.inventory-redesign` : stock/utilisation, contenu/destination et
attente chronologique. `ui/space_navigation` accepte un compteur optionnel par
lien pour reprendre la navigation mobile commune ; sans compteur, son rendu est
conservé. `inventory-review` garde ses targets, formulaires et gestion du focus ;
le sélecteur reste masqué sans JavaScript et tous les formulaires sont alors visibles.
Les données de `InventoryPageView`, les POST, CSRF et clés de requête restent inchangés.

L4 A utilise le même menu natif dans `ui/account_navigation`. Le style
`account-workspace.css`, limité à `.account-redesign`, dispose information et
confirmation côte à côte sur grand écran, puis empilées. Profil et préférences
conservent chacun un formulaire Symfony unique ; les panneaux ne constituent
pas des étapes séparées. Le dépliable de mot de passe reste natif et s’ouvre
après erreur. Les quatre URL, ancres historiques, POST, CSRF, identifiants de
participation, champs et contrôles métier restent existants. Aucun nouveau
contrôleur JavaScript ou chargement métier n’est ajouté.

Les actualisations des colonies et de Flottes demandent JSON avec `X-Genesis-Live: 1` et
`Accept: application/json`. La route conserve ses contrôles, synchronisations
et préparation GameShell, puis renvoie les fragments privés sans cache.
LiveRegionResponse produit du JSON compact et le compresse en gzip si accepté,
avec Vary adapté. Cette compression est propre aux réponses de fragments ;
aucune configuration serveur ni traitement de l’endpoint ressources n’est modifié.
Le sélecteur, le catalogue et le journal sont partagés entre rendu initial et
actualisation ; ressources, files et vols réutilisent leurs templates existants.
Le client ignore les réponses d’une génération ou d’une URL devenue obsolète.
Le focus, les saisies modifiées et le journal non consulté gardent leurs protections.

La console utilise `AdminReadRepository` pour ses compteurs et aperçus bornés ;
les écrans détaillés conservent leurs droits, leur audit et leurs formulaires.
`GameRepository::discoveredPlanets` filtre découverte et univers en base avant
préparation cartographique. Les compositions d’EmpireDetails sont chargées après
filtrage du propriétaire. Aucun cache partagé ni évolution de schéma n’est ajouté.

Le sélecteur conserve la colonie choisie par participation et la catégorie ouverte.
La vue générale présente les vols de toutes les colonies de l’univers sélectionné.
Bâtiments, recherches et vaisseaux/défenses occupent trois files indépendantes :
progression de l’opération active, nombre de commandes suivantes et durée totale.
L’annulation en cascade suit le remboursement défini dans REGLES et DECISIONS ;
l’ancien guide indiquant une absence de remboursement est obsolète.

Les ressources restent visibles sous la barre de contexte. Sur mobile, leur
défilement horizontal est conservé pendant les actualisations. Les dates viennent
du serveur ; les animations Stimulus ne décident ni des durées ni des résultats.

Les cartes /carte et /carte-v2 gardent les identifiants de coordonnées persistés et
ne révèlent que les planètes des systèmes découverts. `GalaxyGeometry` prépare les
positions physiques de la spirale ; `MovementCalculator::distances` prépare la
distance jusqu’à l’entrée du système depuis la colonie de départ. Le JSON V2 expose
`physical`, `distanceFromOrigin` et la version de géométrie sans nouvelle lecture
par système. Les règles de visibilité existantes des flottes restent appliquées
avant sérialisation.
Textures, nébuleuses, anneaux et lunes de la V2 sont cosmétiques et déterministes.
Les missions rapides réutilisent les contrôles serveur existants. La V2 permet
recherche, zoom, déplacement, mini-carte et transitions. Ses dispositions Spirale
réelle et Grille de repérage partagent les distances serveur ; le choix d’affichage
est conservé localement par participation. Les fiches restent dans le flux de la
page, hors du canvas ; le pincement agit aux trois niveaux et le globe se tourne
par glissement. Elle arrête ses animations
hors écran et respecte la réduction des mouvements. La carte classique reste le
repli sans canvas/JavaScript. L’arbre technologique conserve les éléments verrouillés
pour expliquer leurs prérequis, indépendamment du masquage du catalogue.

Les conventions de composants et d’accessibilité sont dans
[le guide UI](ui-ux/GUIDE_INTERFACE.md). Les limites encore ouvertes sont dans
[FEUILLE_DE_ROUTE.md](FEUILLE_DE_ROUTE.md) ; un test automatisé ne clôt pas un audit RGAA complet.

## Vérification ciblée

Depuis la racine :
```sh
make test-browser BROWSER_ARGS="wiki.spec.mjs public-seo.spec.mjs"
make test-browser BROWSER_ARGS="technology-maps-ui.spec.mjs universe-closing.spec.mjs"
```

Les tests utilisent uniquement les comptes et bases de leurs projets Docker
jetables. SMTP, livraison Internet des liens et configuration Alpha demandent
une vérification séparée sur un serveur autorisé.

Le tableau documentaire `docs/CATALOGUE_LABELS.csv` conserve les 156 libellés,
descriptions et utilités de jeu. Les valeurs d’exécution restent celles du catalogue
applicatif ; ce fichier documentaire n’est pas importé dans la base.

## Colonies et journal d’absence — lot C

La Vue générale finalisée reprend la maquette conservée : alertes avant la bannière,
points et quatre raccourcis dans celle-ci, activité locale puis arrivées et vols de
l’empire, journal en pleine largeur. `empire-overview.css` est limité à
`.overview-page` sur cette destination ; les catalogues gardent leur disposition.
Les liens des files mènent aux pages existantes et sont rendus aussi dans le fragment
`queues`. `_overview_attention.html.twig` réutilise les ressources déjà préparées :
sa région, présente même vide, est incluse seulement dans la réponse d’actualisation
de la Vue générale. Le contrôleur existant diffère son remplacement pendant le focus.
La barre de ressources conserve son rendu, ses calculs et son actualisation.

Planet.acquiredAt est nullable : les quatre transferts de propriété existants
appellent acquireBy dans leurs transactions et gardes de rejeu. Une attribution
inchangée ne redéfinit pas la date. GameRepository::planets trie les seuls résultats
filtrés par propriétaire par acquiredAt puis id (NULL en premier dans MariaDB).
Les autres listes gardent leur ordre existant.

JournalService verrouille PlayerUniverse pour ouvrir ou consulter son journal.
journalStartedAt initialise la reprise sans inventer de visite ; overviewVisitedAt
n’est actualisée que par l’ouverture de la vue générale. Le client colony-live
envoie X-Genesis-Live: 1 afin que sa récupération HTML ne constitue pas une visite.
NotificationService reçoit une catégorie optionnelle : queue, arrival, combat,
transport, spy ou conquest. La catégorie est enregistrée
à la création de la notification. À la première ouverture, JournalService retire
cette seule catégorie des notifications déjà présentes et non disponibles, sans
les supprimer ni définir consultedAt. Les nouvelles notifications alimentent
ensuite les absences. Cette reprise évite une lecture d’état de suivi périmée
dans une transaction métier ancienne et n’ajoute aucun verrou à ces traitements.
Les autres catégories restent conservées hors du journal.

À l’ouverture, les notifications suivies deviennent disponibles dans le journal ;
journalAvailableAt les maintient lors des visites suivantes. L’état consultedAt
n’est défini qu’au POST protégé par CSRF et participation, après vérification
des identifiants disponibles du destinataire. UPDATE conditionnel, verrou de
participation et sélection bornée à 50 rendent les répétitions sans double effet.
Les événements non affichés ne sont pas consultés. Le témoin mobile repose sur
cet état serveur et ne valide plus le journal par un stockage local automatique.

EmpireDetails ne produit que les données des planètes et flottes du propriétaire
dans son univers. Les snapshots de stocks restent calculés en lecture seule.
La migration Version20261112010000 ajoute six colonnes nullables et deux index ;
elle n’effectue aucune datation ni reprise de notification historique.

## Catalogue des destinations — lot D

DestinationCatalogue regroupe les planètes connues de la participation active
selon la diplomatie et la mission. Une relation de propriétaire appartenant à un
autre univers est rejetée, même si le monde se trouve dans l’univers courant.
canPrefill conserve l’accès en lecture aux liens de cibles connues ; allows filtre
les menus et suggestions selon les droits de mission. Le propriétaire actuel
détermine la catégorie d’une ancienne planète PNJ capturée.

GET /flottes/destinations accepte mission et q (facultatif, pseudonyme). Réponse
JSON privée sans cache : groupes friendly/enemies et suggestions limitées à 20.
Recherche littérale insensible à la casse, au moins deux caractères, limitée à
40 caractères. Les planètes inconnues et les autres univers sont filtrés avant
construction des données. Seuls identifiant, nom, propriétaire, coordonnées et
libellé utiles sont transmis ; aucun stock ou détail militaire supplémentaire.

Preview et send passent par resolve : destination_mode=custom conserve le contrat
coordinates historique (mode par défaut pour les anciens clients sans paramètre).
friendly/enemies utilisent leurs identifiants de planète, revérifiés en base avec
catégorie, univers, découverte et mission ; une exploration utilise le système
de la planète sélectionnée. FleetService::quote/send reste l’autorité métier,
avec transactions, reçus, carburant et contrôles existants.

Le contrôleur fleet-destination gère radio-boutons, listes et combobox clavier/
tactile. Il désactive les champs des modes inactifs et ignore les réponses de
recherche devenues obsolètes. Une ancienne liste ne peut pas être rouverte après
changement de mission. Le wizard et l’estimation utilisent le champ actif.
Sans JavaScript, les champs restent accessibles et le mode envoyé est validé
côté serveur. Aucun nouveau schéma ni dépendance.

canAttack autorise maintenant les PNJ sans propriétaire dans le même univers,
avec contrôles de participation, vacances et occupation existants. joinAttack
revérifie l’autorisation sous verrou et initialise la garnison PNJ une seule fois.
capturePlanet continue à ne considérer que les missions conquer ; collectLoot
continue à exclure les planètes sans propriétaire. Aucun coût ou durée modifié.

## Archives des rapports et attribution de vaisseaux (lot E)

ReportArchiveService compose les trois sources autorisées (UNION ALL DBAL avec
paramètres liés) et filtre les états personnels avant COUNT/tri/pagination.
L’ordre commun utilise date décroissante, identifiant décroissant, puis type.
Les mutations vérifient tous les éléments avant écriture, dans une transaction
sérialisée sur la participation ; archive/restauration rejouées sont idempotentes.
POST /rapports/archives est protégé par session, CSRF et permissions de lecture.
La table report_archive est unique sur participation/type/id ; archived_at nullable
conserve l’état restauré. Aucun rapport source supprimé ou modifié. Les vues
administratives et preuves restent indépendantes de ce filtrage personnel.

Depuis L7D A, `CombatReportPresenter` enrichit sa présentation avec `rows_by_side`,
les totaux engagés/perdus/restants par camp et les décomptes de tirs/pertes du round.
Ces lectures utilisent le même instantané, sans rejouer le combat ni nouvelle requête.
Le total inclut les renforts enregistrés et reste calculé avant reconstruction.
Les fragments Twig `_report_units`, `_report_round` et `_report_reconstruction`
conservent rangs, flottes et statistiques. Les rounds sont des `details` natifs ;
le premier est ouvert. Les tableaux s’adaptent en lignes sur mobile avec leurs
libellés et leur sémantique, et restent dans la page du rapport.

`report_selection_controller` sélectionne uniquement les cases déjà présentes
dans la page de rapports. Il affiche le nombre de sélections, gère l’état partiel
et désactive la commande collective vide. Les actions individuelles, le formulaire
sans JavaScript, les jetons et la validation transactionnelle restent ceux existants.
Le lien d’effacement retire les critères de recherche en conservant type et archives.
Les styles sont isolés dans `report-workspace.css` ; aucune migration ni dépendance.

AdminInterventionService::ships utilise rôle actif, verrous administrateur puis
participation/univers/planète, reçu ActionGuard et catalogue. Le stock idle est
créé si nécessaire ; chaque quantité reste dans l’entier SQL signé. L’audit
admin_ships_grant est enregistré dans la même transaction (motif, acteur,
joueur/univers/planète/flotte, type/rang, quantité, avant/après).
La route d’attribution n’accepte qu’un POST CSRF avec confirmation explicite.
Pour les unités économiques, les commandes échues sont d’abord synchronisées,
puis l’ancien effectif matérialisé avant l’ajout et la production actualisée.
Ce séquencement conserve la production due entre une livraison payée et la correction. Un dépassement des places reste conservé inactif,
comme pour les autres corrections de stock autorisées. Les livraisons administratives
utilisent aussi `LocalGarrison` pour rejoindre le prochain round non résolu,
sans duplication ni retrait du carburant local. Droits, reçu et audit restent
les contrôles existants ; aucune attribution réelle n’est exécutée par les tests.

Version20261112020000 est additive. Accord explicite reçu ; sauvegarde locale
dans var/backups/lot-e-20261112, archive/manifeste vérifiés deux fois avant make
migrate. Comptages des huit tables suivies inchangés ; aucun serveur Alpha touché.

## Tags et signalements joueurs (lot F)

Guild.tag est nullable pour les anciennes guildes, avec unicité (universe_id, tag).
DiplomacyService normalise/valide tout nouveau tag et sérialise choix/création
avec les verrous participation/univers existants. setTag rafraîchit la guilde
sous verrou et contrôle canCommand. LeaderboardController enrichit seulement
la présentation HTML avec le tag courant ; ni pseudonyme ni score modifiés.

PlayerReportService est distinct de la modération des messages. Le formulaire
conserve en session un contexte serveur (20 derniers formulaires maximum) avec
participation/cible/univers, catégorie/rang et date du calcul. Aucun champ de rang
du POST n’est accepté. Le pseudonyme courant et celui consulté sont conservés.
POST /classement/signaler/{id} vérifie CSRF et le contexte de la session ; les
participations sont verrouillées par identifiant croissant, puis les droits et
l’univers revérifiés. L’unicité auteur/request_key protège contre les doublons.
player_report conserve motif, commentaire, contexte, statut et dates/relecteur ;
liens auteur/cible/relecteur nullables, contexte conservé comme preuve.

La rubrique Modération expose les joueurs dans une section séparée, avec audit
de lecture et traitement. La route POST admin vérifie ROLE_ADMIN/CSRF et le
service revérifie le rôle actif sous verrou. Le traitement est idempotent sous
verrou du signalement, sans sanction ni suppression du contexte.
AdminNotificationService conserve un curseur player_reports séparé du curseur
messages, puis agrège seulement le badge reports : aucun conflit d’identifiants.

Version20261112030000 est appliquée sur la seule base locale après accord
explicite et sauvegarde vérifiée deux fois (var/backups/lot-f-20261112).
Les comptages des onze tables suivies sont inchangés ; l’unique ancienne guilde
garde son tag NULL, aucun signalement inventé. make lint confirme aussi le schéma
local migré. Aucun serveur Alpha n’est concerné.

## Navigation et rappel volontaire — lot G

Depuis L7C, `GalaxyGeometry` est la source commune des distances hyperspatiales des
devis et replis. Les tableaux de coordonnées des trajets figés restent historiques ;
`FleetNavigation` projette leurs extrémités avant d’interpoler le point atteint.
Les secondes et carburants déjà enregistrés ne sont pas recalculés. Les nouveaux
points physiques portent `geometry: spiral-v1`, afin de ne pas les confondre avec
une ancienne position de grille. Un ancien point entre systèmes sans géométrie
identifiable est refusé pour le repli, plutôt que converti arbitrairement.

Le simulateur prépare ses bilans d’affichage dans `CombatSimulationView`, après
les dix calculs existants. Les survivants sont comptés par camp, unité et rang ;
leurs points de structure ne deviennent jamais des effectifs. Twig affiche les
engagés, pertes et survivants, puis les rounds et structures en détails secondaires.
Les résultats bruts, paramètres et contrôles serveur restent conservés.

FleetNavigation fige au départ les coordonnées, portions système/hyperespace,
durées et carburant du devis existant. Son checkpoint active est enregistré avant
tout changement d’échéance (boost ou intervention administrative) ; chaque nouvelle
phase repart de zéro. Fleet.navigation reste nullable pour les anciens trajets.

FleetRecallService calcule un aperçu sans mutation, puis revérifie sous transaction
les droits, l’univers, la propriété, le combat, l’échéance, la version du dispatch
et le carburant. ActionGuard verrouille la participation, puis les planètes et
la flotte ; un reçu idempotent protège la confirmation. FleetService programme
le retour avec un nouveau jeton via l’outbox existante. Un ancien message est
ignoré avant tout traitement de dépôt, y compris après la nouvelle échéance.

Fleet.returnPlanet est une destination de retour facultative distincte de
homePlanet, qui reste l’origine historique. L’arrivée choisit returnPlanet pour
un retour rappelé, puis applique les verrous/permissions et le dépôt existants.
Aucun prélèvement distant ni modification des coûts de mission.

GET/POST /flottes/{id}/rappel : propriétaire et univers vérifiés côté serveur,
CSRF et version de dispatch à la confirmation. En cas de perte de l’origine,
le choix de repli est explicite et revérifié ; aucun choix automatique.
Le JSON cartographique expose seulement le checkpoint et la route de rappel
autorisés, jamais les paramètres techniques internes du trajet.

Version20261112040000 est appliquée sur la seule base locale après accord explicite,
sauvegarde gzip/manifeste vérifiée deux fois dans var/backups/lot-g-20261112.
Les onze comptages suivis et l’empreinte des valeurs historiques de flotte sont
inchangés ; navigation et return_planet_id des anciennes flottes restent NULL.
Aucun serveur Alpha concerné.

## Surveillance et détection — lot H

AttackDetection sépare la somme simple pour le préavis des effets pondérés
d’EspionageService, laissé inchangé. Le seuil est calculé à la lecture depuis
departureAt/arrivalAt et les recherches du défenseur ; aucune migration, nouvelle
notification persistante ou modification des missions. warningSeconds borne
à 7200 s et à la moitié du trajet en secondes entières. maxLevels réutilise la
résolution du catalogue de l’univers, y compris effect.tier_max_levels.

FleetVisibility centralise les autorisations et les DTO cartographiques avant
sérialisation : participation contrôlée, univers accessible, propriétaire,
relations, découvertes, sonde privée et seuil de l’attaque ciblant le lecteur.
Le JSON contient uniquement les données autorisées ; les colonnes d’origine
inconnue et les données techniques des attaques entrantes sont nulles.
Le calcul des points conserve LeaderboardService::fleetPoints.
Le repli non découvert du lot G conserve sa protection.

GameRepository::incomingAttackFleets limite la requête aux attaques/conquêtes
en trajet visant une planète du lecteur dans son univers. GameShell produit
l’affichage initial. GET /flottes/surveillance utilise exclusivement la session
courante ; une galaxie optionnelle est revérifiée dans cet univers avant de
renvoyer les vols autorisés et leur journal HTML. Réponse JSON private, no-store :
aucun accès anonyme ni choix de lecteur par paramètre. Aucun crédit, consommation,
résolution, journal consulté ou activité d’empire modifiés par cette lecture.

incoming-attacks interroge ce point toutes les 5 s, suspend la surveillance dans
un onglet masqué, borne les requêtes à 10 s et conserve le contenu en cas d’erreur.
Les délais utilisent serverNow, sans dépendre de l’heure du poste.
La carte reçoit seulement les DTO filtrés ; elle actualise vols et journal sans
recréer la caméra, la sélection ou le zoom. Les annonces accessibles portent
sur les changements de menaces, pas sur chaque seconde du compte à rebours.


### Orbites : demandes au départ
Le lot I utilise le sous-objet privé versionné `Fleet.navigation.orbit_request`
(clé de demande, destinataire et planète initiaux, dates et décision). Aucune
migration ni reprise des trajets anciens n’est nécessaire. `OrbitPolicy`
sépare les permissions des nouveaux départs du processus historique conservé.
`OrbitRequestState` contrôle les réponses périmées et conserve l’état final.
`FleetRecallPlanner` partage le calcul du retour partiel avec le rappel G,
sans utiliser le fret d’hydrogène ni prélever de stock à distance.
Le refus se traite dans la transaction verrouillant la participation de l’hôte,
l’univers, la destination, l’origine et la flotte ; un nouveau jeton rend l’ancien message
d’arrivée obsolète. L’accord ne modifie pas la date ni le jeton du trajet.
À l’arrivée, une demande nouvelle ne passe jamais en attente indéfinie :
accord revérifié et orbite/défense, ou retour.
`OrbitRequestView` et la requête `orbitRequestsFor` limitent les données aux
parties concernées dans l’univers actif ; une origine non découverte est omise.
La surveillance privée existante actualise ces cartes toutes les cinq secondes
en premier plan, avec horloge serveur et report du remplacement lorsqu’un
contrôle possède le focus. Les clés privées ne sont pas ajoutées à la carte.


### Abandon : transition transactionnelle sans migration
L7A A compose uniquement les templates de liste/comparaison et de bilan avec
`colony-workspace.css`, limité à `.colony-redesign`. Colonies en lignes, tableau
avec filtre existant et confirmation à côté des pertes sur grand écran, puis
empilés sur format étroit. Les contrôleurs, préparations groupées et services
restent inchangés ; en particulier, consulter le bilan conserve la synchronisation
sous verrou décrite ci-dessous. Les formulaires serveur et la Vue générale
restent ceux de leurs domaines.

ColonyAbandonmentService verrouille participation, univers et planète, puis
synchronise les files avant le bilan. planetHasActiveFleet contrôle toutes les
références d’origine, destination et repli sans filtrage de visibilité ; seul un
motif générique est transmis. Les combats et occupations actifs bloquent aussi.
L’analyse récursive des dépendances interdit l’abandon si une commande d’une
autre colonie est concernée. Les files locales sont annulées sans remboursement
via le traitement existant cancelForCaptureLocked ; les autres files démarrent
normalement. Tous les états de reconstruction de la planète, y compris ceux des anciens
propriétaires, conservent leur référence historique mais perdent commande,
échéance et jeton actifs avant la suppression des files.
Une empreinte des actifs discrets et commandes empêche une confirmation périmée ;
les stocks variables sont exclus de cette empreinte mais enregistrés à leur valeur
matérialisée avant la perte. La confirmation est liée au nom, à la participation,
au CSRF et à un reçu de requête.
Les lignes d’actifs sont retirées, les flottes inactives du propriétaire vidées
et terminées ; Planet et les historiques restent en place. Un AdminAuditLog
player_colony_abandoned conserve propriétaire, univers, planète, pertes et
commandes annulées, sans attribuer de droits administrateur au joueur. Ce journal
sert déjà à tracer la suppression d’un compte par son propriétaire.
Les messages visant les commandes retirées sont obsolètes ; aucune ressource ne
peut être déposée à distance et une nouvelle colonisation réutilise les règles
existantes. La colonie active en session est conservée si elle est encore détenue,
sinon remplacée par une colonie restante.

ColonizationKitPolicy utilise la trace durable player_colony_abandoned pour le
couple compte joueur / planète. L’identifiant de planète est global et son univers
est vérifié. Une ancienne participation ou un changement de pseudonyme ne permet
pas de réclamer de nouveau le kit du même compte. Les traces d’abandon doivent
donc être conservées : aucune suppression automatique de ce journal n’est présente
dans les services ou scripts examinés. Le kit configuré et le multiplicateur de
rang sont conservés pour un nouveau propriétaire ; seuls les stocks initiaux
de la recolonisation par l’auteur de l’abandon sont à zéro. Une version de règle
dans l’empreinte du bilan invalide une confirmation antérieure à cette décision.
Le reçu de requête reste dans ActionReceipt et n’est pas recopié dans le journal
d’audit. La page indique le besoin de ravitaillement avant confirmation.

### Présentation des pages joueur — lot K

Les pages Diplomatie, Profil et Inventaire utilisent les styles limités
à .page-refinement dans player-pages.css et les couleurs du thème existant.
Les onglets de diplomatie restent des liens GET : trois sans guilde, cinq avec guilde.
Le contrôleur normalise les anciens onglets indisponibles vers la création et les anciennes adresses de recherche vers Rejoindre une guilde,
conserve les recherches et redirige les enregistrements vers le panneau concerné.
Les attributions sont dans Membres, l’édition dans Grades et permissions, le tag et
le commandement dans Ma guilde. Les services métier, CSRF et versions restent identiques ;
l’aperçu du canal reste en lecture. Les styles complémentaires sont dans diplomacy-tabs.css.

GuildPresence lit uniquement les PlayerActivity de la guilde actuelle dans l’univers
consulté. Une sous-requête EXISTS revérifie l’appartenance du lecteur lors de la lecture.
Le booléen de présence réutilise le seuil existant de PlayerActivityService (< cinq minutes) ;
aucune agrégation par compte entre univers, ni heure ou durée, n’est transmise à la vue.
La page est private, no-store. Pas de nouvelle requête périodique, table ou migration.

Le profil utilise des détails natifs, y compris sans JavaScript. Les erreurs
de modification rouvrent le changement de mot de passe ; une erreur de suppression
redirige avec delete=1 pour garder sa section ouverte. Ce paramètre ne donne
aucun droit et ne change ni les validations ni la confirmation serveur.

inventory_review_controller sélectionne la carte de l’opération et intercepte
uniquement la présentation de sa soumission pour ouvrir un dialogue natif.
L’annulation ne lance aucune requête métier. La confirmation resoumet le formulaire
existant avec son jeton CSRF et sa clé de requête ; les services revérifient
propriété, univers, vacances, opération active et stockage. Le contenu du dialogue
est écrit avec textContent. Sans JavaScript ou sans showModal, les formulaires
restent visibles et fonctionnent selon leur comportement serveur habituel.
Aucune migration, dépendance ou modification des valeurs d’équilibrage.

### Bulles de détails de l’empire

hover_details_controller pilote maintenant des popovers natifs, ouverts depuis
les noms des colonies ou l’encadré de flotte. La couche supérieure du navigateur
évite la découpe par les cartes ; le placement tient compte de la fenêtre et du
défilement. Un déplacement du pointeur vers la bulle conserve sa lecture ; clic,
focus, toucher et Échap sont gérés. Sans croix : sortie du pointeur de l’ancre et
de la bulle (délai de passage 180 ms), sortie du focus ou toucher extérieur ferment
la bulle. Un clic souris ne bloque pas la fermeture automatique.
Les données viennent des mêmes vues propriétaires qu’avant : aucun chargement
supplémentaire, aucune extension des permissions ni exposition de flottes tierces.

Les classements utilisent des liens GET dans les cinq en-têtes, avec aria-sort,
sans changer les calculs, rangs ou pagination côté serveur. Les libellés des
cases d’archivage restent dans le nom accessible, hors de l’affichage visuel.

Le lot L7E A conserve les gabarits et lectures de messagerie, classements et profils.
`social-workspace.css` limite la nouvelle présentation aux cinq pages du lot, sans
modifier les profils de guilde L1B. Les scores du profil public sont présentés en
lignes ; le tableau des guildes garde toutes ses colonnes dans une région défilante.
La protection déjà fournie au classement est consultable dans un `details` natif,
sans nouvelle lecture ni exposition d’information. Les liens de la recherche vers
la messagerie gardent leur interlocuteur ou canal. Le menu reste actif sur les
sous-pages de recherche, de profil public et de signalement.

Le seuil de 1 050 px du contrôleur `message-thread` suit l’empilement des deux
panneaux. L’historique conserve son défilement et la rédaction son contrôleur
anti-doublon ; aucun service métier, requête, jeton ou format de message n’est changé.

L’aperçu de guilde appelle guildConversation(guild, 3), qui limite les résultats
en base aux trois plus récents (date puis identifiant), affichés dans leur ordre
chronologique. Il ne marque pas le canal lu. Les boutons de départ/dissolution
sont visibles dans Ma guilde avec leurs services et jetons CSRF existants.

### Accueil, accès au compte et univers — L8 A

`security/layout.html.twig` rassemble la présentation des formulaires de connexion,
inscription, récupération et confirmation ; chaque tâche conserve sa route et son
gabarit. Le contexte illustré est masqué à 1 050 px et moins, pour placer directement
le formulaire sur les petits écrans. Les refus d’accès conservent leur gabarit et
leur retour prioritaire selon le rôle, sans contexte illustré avant cette action.

`entry-workspace.css` limite les styles aux conteneurs de ces pages et à leur
navigation publique. Les cartes d’univers conservent les neuf paramètres préparés
par `UniverseController` et `GameCatalog` ; aucune lecture supplémentaire n’est
ajoutée. Le bandeau d’absence d’empire utilise les participations actives déjà
fournies. Les limites de flotte sont écrites en entier, sans abréviation à déplier.

Les formulaires, champs, contrôleurs de visibilité du mot de passe, thèmes d’erreur,
CSRF, réponses neutres, redirections et services restent ceux de l’existant. Les
messages de résultat sont affichés par le socle commun au-dessus du contenu. Les
calendriers UTC et le bandeau de fermeture à 24 heures gardent leurs contrôleurs,
leur horloge serveur et leur actualisation. Les métadonnées publiques et les
réponses techniques ne sont pas modifiées.

### Feedback, patchnotes et documents — L9 A

`information-workspace.css` complète les styles d’accès communs dans les seuls
conteneurs L9. Le feedback garde un formulaire POST multipart unique : champs du
retour, contexte et capture, puis action d’envoi dans l’ordre du document. Sur
ordinateur, contexte et capture occupent la colonne de droite ; l’affichage se
réunit à 1 150 px et moins. Les contrôleurs et lectures serveur ne sont pas modifiés.

`feedback-attachment` prépare seulement un aperçu local : URL temporaire du fichier,
décodage de l’image et agrandissement dans un dialogue natif. Le type annoncé et le
budget de décodage limitent l’aperçu, sans remplacer la validation serveur. Une
génération écarte les décodages périmés ; retrait, remplacement et déconnexion ferment
le dialogue et révoquent l’URL. Une image non décodable reste sélectionnée pour que
le serveur puisse décider de son acceptation. Sans JavaScript, le champ fichier et
l’envoi restent disponibles. Aucun aperçu n’est téléversé avant la soumission.

Les patchnotes conservent leur `details` natif, le contenu échappé avec sauts de ligne,
le filtrage des publications et l’effet de lecture du contrôleur. Les documents
publics gardent leur contenu et leurs métadonnées ; seules six ancres sont ajoutées
à la confidentialité. Le socle affiche sur ces pages la navigation publique L8
pour les visiteurs et conserve le contexte habituel des comptes connectés.

## Présentations de guildes

`GuildProfile` conserve description, références d’images et version dans une table dédiée, rattachée à la guilde avec suppression en cascade de la ligne. Les lectures des guildes existantes n’ajoutent pas de jointure à cette table. Les profils de joueurs ne transmettent aux templates qu’une liste explicite des informations publiques, avec les scores du classement quotidien.

`GuildImageStorage` vérifie taille, type réel, dimensions et décodage GD ; il réencode les pixels sous un nom aléatoire, sans conserver les métadonnées. Les fichiers sont hors de `public/`, en `var/guild-media`, servis par une route authentifiée et limitée à l’univers, avec `nosniff`, CSP restrictive et absence de cache partagé. `GuildProfileService` vérifie commandant actuel et version sous verrou ; un échec retire les nouveaux fichiers et les anciennes images ne sont nettoyées qu’après validation de la transaction. [Limites et vérifications](PROFILS_JOUEURS_GUILDES.md).


## Panneau des arrivées étrangères — 16 novembre 2026

FleetVisibility::incoming reçoit les seuls candidats de GameRepository::incomingFleets,
filtrés par destinataire propriétaire, univers, mission et état. Il applique le même
masque que la carte avant de lire les FleetShip des flottes visibles en groupe.
La sérialisation tierce conserve les effectifs/points et masque composition, fret
et origines non découvertes. Les déplacements personnels restent exclus de ce panneau.
Le lecteur de surveillance reste sans écriture ; URL, clé de réponse HTML, contrôleur
Stimulus et attributs d’actualisation sont conservés pour les pages déjà ouvertes.

Le paramètre interne incoming_panel distingue la surveillance commune de son
rendu. Les six vues de colonie/flottes affichent le panneau sous les vols ; la
carte et les autres vues gardent le contrôleur sans cadre ajouté. Dans ce dernier
cas, le HTML autorisé est lu hors du DOM affiché pour calculer les annonces, puis
les événements cartographiques existants sont transmis. Les droits, lectures,
URL et clés de réponse restent communs aux deux présentations.


## Espaces Guildes et Diplomatie

Lot 1 publié dans la [PR #75](https://github.com/c41m07/genesis-reborn/pull/75) : espaces Guildes et Diplomatie séparés, pages dédiées de membres,
grades, gestion et création ; liens directs, anciennes adresses conservées et
lectures adaptées à chaque page. Voir le [suivi des lots](NAVIGATION_PAGES_DEDIEES.md) pour les
choix, vérifications et limites. CI, fusion et déploiement restent distincts.

Le lot visuel L1A validé en B réutilise `DiplomacyPageView` et les mêmes POST,
sans changement de préparation des données. `ui/space_navigation.html.twig`
reçoit les destinations autorisées et leur état actif ; les consommateurs de
guilde passent toujours par `ui/guild_navigation.html.twig`. Le mode compact
est activé seulement sur Ma guilde, Membres et Relations entre commandants
(dans ce dernier cas, si deux destinations sont disponibles).

Le HTML contient un `details` ouvert : les liens serveur restent accessibles
sans JavaScript. `space_navigation_controller.js` adapte son ouverture au seuil
de 600 px, ferme sur Échap et replace le focus lorsque le format masque son
élément. Son écouteur de changement de format est retiré à la déconnexion.
`guild-workspace.css`, chargé après les styles existants, limite les compositions
et couleurs aux trois pages L1A. Le socle commun et la barre de ressources sont
conservés. [Suivi de réalisation locale](REFONTE_VISUELLE_INTERFACES.md).

L1B, validé en A, ajoute `guild-discovery.css` limité à la recherche/création sans
guilde et à la présentation consultée. Les résultats gardent leur fragment commun
et leurs POST ; les adaptations d’adhésion sont conditionnées à l’absence de
guilde. La partie traités du fragment ne change pas. Les liens secondaires restent
visibles sur mobile, sans contrôleur supplémentaire ni nouvelle route.

`DiplomacyController` conserve en session le nom/tag d’une création refusée,
dans une clé liée à la participation et à l’univers. Les valeurs sont bornées à
120/10 caractères, au-delà des maxima valides 60/5, pour ne pas rendre valide une
valeur trop longue après troncature. Les champs Twig restent échappés ; le brouillon
survit aux GET et n’est pas un état métier. Une création réussie le retire. Le
contrôle CSRF précède son écriture ; services, validations et transactions restent
inchangés. Les tests couvrent rechargement, isolement, CSRF et bornes.

L1C, validé en A, réutilise les données préparées pour grades, gestion,
personnalisation et traités. `guild-control.css` limite leurs compositions à
`.guild-control`. Le style du menu natif est extrait de `guild-workspace.css`
vers `space-navigation-menu.css` pour les consommateurs L1A/L1C ; le contrôleur
Stimulus et le HTML ouvert sans JavaScript restent communs. Le contrôleur garde
la trace d’une perte de focus causée par le masquage CSS du résumé : selon l’ordre
des événements navigateur, elle peut précéder le signal de changement de format.
Le focus rejoint alors la page active sur ordinateur, sans modifier celui d’un
champ situé hors du menu.

Tag et transfert sont des formulaires distincts. Les confirmations dépliables de
transfert, départ et dissolution ne changent ni l’URL, ni les champs d’action,
ni le CSRF : elles précèdent uniquement l’envoi. Les services vérifient toujours
le commandant actuel, la cible, l’univers, les versions et leurs verrous. Les
formulaires de traités conservent `relation_id`, `relation_version`, `status` et
`treaty_action`, avec les mêmes conditions d’affichage. Aucun nouveau chargement
de données ni traitement serveur n’est introduit par ce lot.


### Inventaire par destination

`InventoryPageView` prépare `/boosts`, `/inventaire/boites` ou
`/inventaire/recompenses`. Une agrégation privée compte les objets disponibles
et en attente sans hydrater l’historique utilisé. Les boîtes disponibles sont
lues séparément, dans la capacité maximale existante de 100, puis regroupées
par contenu identique. La file d’attente est lue par pages de 25 dans l’ordre
`earnedAt, id`. Les opérations de tout l’empire sont préparées sur Boosts,
uniquement si un boost est disponible et utilisable hors vacances.

`BoostController` conserve la synchronisation préalable et les POST publics.
`BoostUseService`, `ResourceBoxUseService`, les reçus, verrous, transactions et
la promotion automatique restent inchangés. Les ouvertures reviennent à la
page Boîtes ; les utilisations de boost à Boosts. Les anciens fragments sont
orientés par le contrôleur de navigation, à l’ouverture et au changement de
fragment. Les liens serveur fonctionnent sans JavaScript.
Voir [le suivi](NAVIGATION_PAGES_DEDIEES.md) pour les contrôles et limites.


### Compte commun et mode vacances

Le menu Mon compte ouvre `/profil` (Mes identifiants). La navigation commune
relie `/profil/preferences`, `/profil/vacances` et `/profil/supprimer`.
`PreferencesController` prépare uniquement le formulaire des préférences communes.
`VacationController` prépare l’état de l’empire sélectionné et reprend les POST
historiques `/profil/preferences/vacances/activer` et `/terminer`, avec les mêmes
noms de routes, jetons, identifiants et services métier. Le contrôle de sélection,
propriété, participation et univers reste préalable à chaque action de vacances.
Les comptes sans empire conservent les identifiants, préférences et suppression ;
la page Vacances présente l’information d’indisponibilité, sans nouvelles permissions.

La lecture d’affichage de `VacationEconomyService.settings` ne sélectionne que la
dernière période (début puis identifiant décroissants), y compris terminée pour
le délai de réactivation. Les calculs d’historique et les écritures sous verrou
restent inchangés. `ProfileService` et `AccountDeletionService` sont réutilisés,
avec fermeture de session après modification et protections de suppression.

Le GET de suppression ne prépare aucun formulaire des identifiants et ne supprime
rien. Les anciens `?delete=1` redirigent côté serveur ; les fragments des vacances
et de suppression sont orientés côté navigateur. Les nouvelles pages désactivent
le sommaire automatique et conservent leurs liens et formulaires serveur sans JS.
Voir [le suivi de navigation](NAVIGATION_PAGES_DEDIEES.md) pour la recette.


## Pages des projets collectifs — 17 novembre 2026

`GuildProjectController` expose les listes En cours, Disponibles et Historique
sur des GET distincts ; les routes de fiche, lancement et contribution restent
compatibles. Les actions délèguent toujours aux mêmes services métier.
`GuildProjectPageView` prépare la destination demandée, après validation de
la participation, de la guilde et de la fonctionnalité dans l’univers.

Les listes lisent 26 lignes au plus pour en afficher 25 et calculer le curseur
suivant (`before`). Les modèles disponibles sont sélectionnés par `NOT EXISTS`
sur tous les projets de la guilde, y compris terminés ou abandonnés, sans charger
ces projets. Les contributions personnelles et les clés de récompenses des seules
lignes affichées sont regroupées. La clé compte/modèle reste celle de
`BoostGrantService`, sans filtrer les boosts utilisés ou les anciens empires.
La fiche pagine aussi les contributeurs (`before_contribution`) ; la contribution
personnelle est lue séparément et reste complète. Les identités sont limitées aux
25 contributeurs visibles, dans le même univers. Seule une fiche active prépare
les colonies nécessaires au don ; les données communes du bandeau restent celles
de `GameShell`. Aucune actualisation périodique ni nouvelle écriture de lecture.

L5 A conserve cette préparation et réorganise seulement les templates.
`guild-project-workspace.css` est limité à `.project-redesign` : listes en lignes,
modèles avec minimums visibles et fiche active avec don à droite à partir de
1201 px, puis une colonne. Les formulaires gardent leurs méthodes, actions,
champs, CSRF et versions ; les maximums existants sont aussi indiqués en texte.
Les deux menus réutilisent `ui/space_navigation`, ouvert sans JavaScript. Son
option `currentAriaValue` conserve `location` sur une fiche de projet et reste
`page` par défaut pour les autres espaces. Aucun nouveau service, contrôleur
JavaScript, chargement ou calcul métier n’est ajouté.

Les deux navigations gardent l’espace guilde et la destination projet actifs.
`page_sections` ne crée pas de sommaire sur ces pages. Il oriente les anciens
fragments de liste à l’ouverture et au changement de fragment, en conservant la
recherche ; les liens serveur restent disponibles sans JavaScript. Les ancres
internes d’une fiche restent utilisables. Aucune migration ou mesure comparative
de performance ; voir le [bilan du lot 5](NAVIGATION_PAGES_DEDIEES.md).


## Pages Événements et Défis — 17 novembre 2026

`GameEventsController` sépare `/evenements` et `/evenements/defis`. La première
page prépare les annonces et bonus en cours ou à venir ; la seconde utilise
`ChallengeQueryService::playerPage`. Les fiches de `ChallengeController` gardent
leurs routes, contrôles d’univers, progression, classement et récompenses.

Les critères de lecture communs sont dans `GameRepository`. Les prédicats JSON
équivalents à `!empty()` et le préfixe hebdomadaire sensible à la casse sont
appliqués avant comptage et pagination, sans charger des occurrences hors du
parcours pour les rejeter ensuite. Le mapping natif Doctrine conserve dates,
JSON et associations sur la même requête filtrée. Les tests comparent les valeurs
JSON nulles, fausses, nulles numériquement, vides et non vides à leur lecture PHP.
Aucune expression SQL ne vient d’une saisie utilisateur.

Les deux groupes d’annonces sont paginés séparément par 25, avec `current_page`
et `upcoming_page` conservés dans les liens. Les défis utilisent `page` et un
ordre date/identifiant stable. Les lignes closes ou annulées restent consultables
sur les pages suivantes. Les exclusions d’archivage restent celles des listes
actuelles ; une ancienne fiche archivée reste accessible selon ses droits existants.
`ChallengeQueryService::view` garde les calculs métier et lectures personnelles.

L6 A réorganise les trois templates de pages et leur navigation. Le style
`event-workspace.css` reste limité à `.event-redesign` : annonces avec dates à
droite, défis en lignes et fiche avec participation à droite à partir de 1201 px,
puis une colonne. Le menu natif partagé conserve `page` sur les listes et
`location` sur la fiche, et reste ouvert sans JavaScript. Le résumé
`events/_challenge_summary` partagé avec l’administration n’est pas modifié.
Les liens de participation gardent leurs conditions et destinations ; un objet
déjà attribué reste présenté avant le cas d’annulation. Aucun nouveau calcul,
contrôleur JavaScript, chargement ou traitement métier n’est ajouté.

`EventPanelService` réutilise les mêmes critères, sans changer son périmètre
commun : annonces/bonus applicables et occurrences en cours ou programmées.
Il conserve toutes ses informations, son compteur fermé et ses comptes à rebours ;
il ne charge aucun historique de défis. Les pages n’ajoutent pas de sondage.
L’ancien fragment `#page-section-0` du sommaire des défis est orienté côté navigateur
vers la nouvelle liste. `#events-current` et `#events-upcoming` restent sur le
document des annonces ; les nouveaux écrans désactivent le sommaire automatique.
Voir [la recette et les limites](NAVIGATION_PAGES_DEDIEES.md).

## Pages administratives dédiées — 17 novembre 2026

Le sous-lot visuel L10A ajoute `admin-workspace.css`, limité par la classe de
corps aux routes d’accueil, de rubrique et de liste des joueurs. Ces pages
désactivent le sommaire automatique, tout en conservant les redirections des
anciens fragments. L’accueil étend les missions et événements sur toute la
largeur ; les informations secondaires des dernières actions restent dépliables.
La table des comptes garde ses six colonnes HTML et ses en-têtes ; sous 1050 px,
les cellules sont présentées par compte avec leurs libellés visibles. Le formulaire
GET et le fragment de résultats gardent `admin-list`, sa pagination serveur,
ses protections contre les réponses obsolètes et son repli sans JavaScript.
Aucun service, requête, contrat d’action ou règle de rôle n’est modifié.

L10B étend ce cadre à `app_admin_player` et ajoute `admin-player-workspace.css`,
limité à `.admin-player-profile`. Les six données d’activité de chaque participation
restent préparées par le contrôleur actuel, sans nouvelle agrégation. Les colonies
conservent leurs deux `<details>` natifs et leurs identifiants de fragments ; les
champs de ressources sont regroupés par deux sur écran large, par un sur mobile.
Le stock et la capacité sont reliés au champ par `aria-describedby`. Motif, case de
confirmation, POST, CSRF et clé de demande restent dans chaque formulaire.
Les redirections après intervention, audits de consultation/écriture et protections
transactionnelles restent inchangés. Aucune actualisation ou logique JavaScript
n’est ajoutée à la fiche ; une erreur serveur garde le comportement de redirection
existant, sans garantie nouvelle de conservation de saisie.

L10C ajoute `admin-moderation-workspace.css`, limité aux trois routes de modération,
liste et détail des feedbacks. La classe `admin-moderation-workspace` active ces
styles ; ces pages désactivent aussi le sommaire automatique. Le filtre local
`admin-filter` reste attaché aux seuls signalements de messages. Les signalements
de joueurs conservent leur section et leur contexte serveur. Les feedbacks gardent
leurs filtres GET, lectures et pagination dans `AdminReadRepository` ; le lien
d’archives change uniquement `status` et remet `page` à 1. La fiche garde le même
POST et la même capture autorisée, avec le suivi en colonne sur écran large.
Les couleurs d’état/priorité sont adaptées au fond blanc du thème clair. Aucun
service, contrôle d’accès, audit ou contrat de modération n’est modifié.

L10D ajoute `admin-case-workspace.css`, activé par `admin-case-workspace` sur
les trois GET de liste, création et détail des dossiers. Ces pages désactivent
le sommaire automatique et conservent leurs URL distinctes. La liste garde une
seule destination par résultat et ses filtres GET, y compris les portées de
participation/flotte. La fiche place observations et notes dans une colonne,
le formulaire dans une seconde sur grand écran ; l’ordre de lecture devient
linéaire sous 1100 px. Les aides de longueur sont reliées aux champs ; les textes
libres gardent leurs sauts de ligne et l’échappement Twig. Brouillon de création,
POST, CSRF, clés de demande et état attendu restent ceux d’`AdminCaseController`
et d’`AdminCaseService`, sans modification de leurs lectures ni de leurs écritures.

L10E ajoute `admin-surveillance-workspace.css`, activé par la classe de corps
`admin-surveillance-workspace` sur alertes, liste et fiche des missions. Ces trois
pages dédiées désactivent le sommaire automatique. La table de huit colonnes garde
ses en-têtes et rôles HTML ; sous 1100 px, les lignes deviennent des fiches avec
leurs sept valeurs libellées. Les trois colonnes de composition restent visibles
ensemble. Les seuils et interventions utilisent `<details>` sans contrôleur
supplémentaire ; valeurs de seuil, état courant et avertissements restent visibles.
Situation, départ conservé, unités, cargaison et rapports précèdent l’intervention.
`admin-mission-monitor` conserve son fragment, sa période de vingt secondes et ses
protections de consultation/erreur. Aucun contrôleur PHP, service, requête,
autorisation, jeton, version attendue, transaction ou audit n’est modifié.

L10F B ajoute `admin-universe-workspace.css`, limité par `admin-universe-workspace`
aux GET de liste, création et gestion des univers. Les fiches de liste sont
regroupées par deux sur grand écran ; la création place ses repères auprès du
formulaire, et la gestion place calendrier et configuration dans deux colonnes.
Sous 1200 px, ces ensembles reviennent sur une colonne. Les fragments existants
de calendrier et de règles, les cibles `admin-speed`, les identifiants d’ancres,
les POST et la confirmation de suppression gardent leurs contrats.
Le fragment de création reste utilisé sur sa seule page dédiée. Aucun service,
chargement, réglage de jeu ou comportement JavaScript n’est modifié.

La console et ses groupes existants restent en place. `AdminController` expose
les GET de création et de gestion d’un univers ; les POST historiques gardent
leurs services, jetons et contrôles. Les brouillons d’erreur sont consommés sur
la seule fiche concernée. `AdminReadRepository` pagine les univers et les modèles
de projets par 50 ; les filtres d’univers s’appliquent avant la pagination.
Les formulaires ne sont plus préparés dans les listes. Les pages d’édition ne
chargent ni la liste des modèles ni les autres univers pour leur configuration.

`CatalogBalanceService::entries` conserve son contrat par défaut ; ses paramètres
facultatifs limitent famille, entrée et préparation des champs. La recherche
transversale porte sur le catalogue de configuration fini de l’univers ; une
liste ne construit aucun champ d’édition. Les écritures d’équilibrage réutilisent
les transactions, matérialisations et validations existantes.

L10G B ajoute `admin-balance-workspace.css`, limité aux mesures et catalogues
par la classe de page `admin-balance-workspace`. Progression et attentes sont
rapprochées ; chaque ressource garde ses quatre indicateurs et ses dénominateurs
dans une liste de définitions. Les entrées restent des fiches par deux. Le
fragment `_catalogue_form` regroupe les champs existants de `cost_profiles` par
rang, sans changer noms, valeurs, bornes, pas ou jetons. Des `<fieldset>` nomment
les trois rangs et les bornes sont associées aux champs par `aria-describedby`.
Sous 1200 px, les colonnes reviennent sur une seule. Les quatre ancres des mesures
parcourent le même relevé ; famille, recherche et édition gardent leurs URL.
Les contrôleurs, services, lectures et écritures ne sont pas modifiés.

L10H A ajoute `admin-boxes-workspace.css`, limité à `app_admin_resource_boxes`.
Le formulaire conserve ses vingt champs numériques, son motif, sa confirmation
et son jeton. Cinq plafonds précèdent trois `<fieldset>` de formats, puis le
motif et la confirmation. Les trois ancres parcourent cette même page. Sous
1200 px, les formats reviennent sur une colonne ; les plafonds passent par deux
colonnes puis une sur téléphone. `admin-editors` ne replie plus ce formulaire
et le sommaire automatique est désactivé sur cette page. Une réponse de refus
conserve aussi la case soumise ; le serveur continue de contrôler sa valeur.
`AdminResourceBoxController` et `ResourceBoxPolicy` ne sont pas modifiés.

L10I A ajoute `admin-events-workspace.css`, limité aux cinq routes GET des
événements : liste, assistant, aperçu, création et édition avancées. Liste et
choix de modèle restent sur toute la largeur. Les champs se placent par deux
sur ordinateur, avec nom guidé et descriptions sur toute la largeur ; sous
1200 px, ils reviennent sur une colonne. L’aperçu garde une lecture continue.
`<details>` replie seulement le formulaire d’annulation guidée ; ses conséquences
restent visibles et une ouverture/fermeture conserve le motif saisi. Le retrait
commun garde son fragment et ses contrôles. Le sommaire automatique est désactivé
sur ces pages. Les types et statuts ont des libellés français, sans changer les
valeurs soumises. Les noms de champs, contraintes, jetons et URL restent les mêmes.
Le libellé Description avancée est associé explicitement à son champ, même après
une erreur. Aucun contrôleur, service, chargement ni comportement JavaScript
n’est modifié ; les formulaires restent utilisables sans JavaScript.

L10J A ajoute `admin-projects-workspace.css`, limité aux trois routes des modèles
de projets de guilde. Liste, création et édition conservent leurs URL et leurs
lectures ciblées. Un formulaire contient identité, paliers et minimums dans le
fil ; chaque palier reste un `<fieldset>` nommé. Les cinq ressources s’affichent
sur cinq colonnes, puis deux sous 1200 px et une sous 760 px. Les libellés sont
associés explicitement aux champs ; le focus d’ajout/retrait est conservé.
`AdminGuildProjectController` accepte le choix d’univers vide comme contexte non
encore choisi pendant la préparation du brouillon, afin que `formnovalidate`
permette l’ajout/retrait avant cette saisie. L’enregistrement reste soumis à la
validation d’un univers existant, des paliers, minimums, version et permissions.
Aucune écriture n’est ajoutée aux étapes de préparation. Les noms des champs,
jetons, limites et services métier conservent leurs contrats.

L10K A ajoute `admin-rotations-workspace.css`, limité aux trois routes des modèles
hebdomadaires. La liste conserve son filtre d’univers et l’ordre des modèles.
Création et édition utilisent un formulaire avec trois sections nommées :
identité, objectif/attribution, durée/enregistrement. Les champs facultatifs
restent visibles ; valeurs, contraintes, jetons et empreinte sont conservés.
L’activation rapide garde un formulaire distinct dans un `<details>` natif ; ses
conséquences restent visibles et son motif survit à l’ouverture/fermeture.
La navigation commune et l’absence de sommaire automatique sont préservées.
Aucun contrôleur, service, chargement ou comportement JavaScript n’est modifié.
Les occurrences existantes gardent leur définition et leurs récompenses figées.

L10L A ajoute `admin-challenges-workspace.css`, limité à la liste et à la fiche
des défis. Les filtres GET et les deux paginations de cinquante gardent leurs
contrats. Le tableau s’affiche au-dessus de 1200 px ; les fiches individuelles
prennent le relais sur tablette et téléphone. Les deux présentations réutilisent
les mêmes lignes déjà préparées ; la présentation masquée est retirée de l’arbre
d’accessibilité par `display: none`. Ce double balisage est borné à cinquante
participants, sans nouvelle lecture. Le libellé de récompense est partagé par
une macro locale. Le fragment de suivi partagé avec le jeu reste inchangé.
Les annulations guidées et hebdomadaires se déplient nativement, avec conséquences
visibles, motifs, confirmations et jetons conservés. Le sommaire automatique est
désactivé. Aucun contrôleur, service ou traitement JavaScript n’est modifié ;
la consultation conserve sa journalisation administrative existante.

L10M A sépare les patchnotes administratives : liste, création, édition et
confirmation de suppression. Les anciennes adresses POST et leurs jetons restent
acceptés ; les nouvelles destinations GET ont une navigation active et un fil
d’Ariane. Liste et formulaires chargent seulement les données de leur page.
`PatchNoteEditor` prépare le brouillon et valide tous les champs avant de modifier
l’entité gérée par Doctrine : champs requis, limites 160/40/500, statut et date
UTC stricte (minutes ou secondes). Une date impossible ou mal formée est refusée
avec une réponse 422, sans mutation ni audit de réussite ; le formulaire conserve
ses valeurs. Brouillon sans date, publication immédiate si date vide, visibilité
à l’échéance si date future : contrats existants conservés. La confirmation GET
n’efface rien ; son bouton soumet l’ancien POST protégé par rôle et CSRF.
Les écritures et actions d’audit conservent leur ordre ; aucune garantie nouvelle
de concurrence ou d’idempotence n’est annoncée. `admin-patchnotes-workspace.css`
reste limité à ces routes, sans repli automatique du formulaire ni sommaire ajouté.
Les champs HTML natifs restent utilisables sans JavaScript ; l’affichage joueur,
le texte échappé et l’enregistrement de lecture restent inchangés.

L10N A ajoute `admin-ops-workspace.css` aux quatre routes d’exploitation.
Un seul tableau par liste conserve ses lignes et ses rôles explicites ; sous
1200 px, le même balisage prend la présentation en fiches. Recherche, comptage
et export ne dupliquent donc aucune entrée selon la taille d’écran. Les détails
d’audit utilisent un `<details>` natif ; `data-csv-value` conserve le JSON complet
pour l’export de la page, indépendamment de son état replié. Le contrôleur commun
`admin_filter` lit cet attribut facultatif et conserve le comportement précédent
pour les autres cellules. La neutralisation des formules CSV reste appliquée.
Les outils purement locaux de sécurité et l’export visible ne sont révélés que
par leur contrôleur ; liste, examen et export serveur restent disponibles sans
JavaScript. Les limites de collecte et l’audit de consultation restent inchangés.
Les indicateurs occupent quatre, deux puis une colonne. Les brouillons gardent
leur texte et leurs tableaux défilants natifs ; quatre noms de régions de la
confidentialité sont distingués pour l’accessibilité. Contrôleurs, services,
permissions, jetons, transactions et en-têtes privés ne sont pas modifiés.
Les rapports administratifs de combat/espionnage conservent les lecteurs partagés
validés en L7D/L10E. L’ancienne liste de transports redirige toujours vers les
missions filtrées ; les notifications restent un endpoint JSON privé.

Les contrôleurs Événements, Projets et Rotations séparent les GET de liste et de
formulaire. Les anciens POST restent acceptés. L’assistant et les interdictions
de réécriture des événements guidés restent inchangés. L’édition de rotation
réutilise les validations de création dans `WeeklyRotationService` ; elle vérifie
une empreinte du modèle sous le verrou existant administrateur puis univers,
conserve sa clé et sa position et journalise avant/après. Les configurations des
occurrences et les récompenses acquises ne sont pas réécrites.

`admin/_page_navigation` relie les destinations ; le menu de groupe et le fil
d’Ariane reconnaissent les pages profondes. Les nouvelles pages désactivent le
sommaire automatique et ne sont plus repliées par `admin_editors`.
`page_sections` traduit les anciens fragments univers/catalogue dans le navigateur
avec remplacement de l’entrée d’historique ; sans JavaScript, listes et liens
serveur permettent d’atteindre les mêmes fiches. Aucun changement de schéma,
dépendance ou chargement global de données de jeu n’est ajouté.
