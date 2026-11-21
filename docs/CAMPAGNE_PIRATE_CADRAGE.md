# Campagne pirate : socle isolé et intégration à venir

## Portée et complexité

- Socle local, 2–3/5 : identité du poste et données d’un adversaire figées par valeur. Aucun état, rapport, migration, route, bouton, outbox, récompense ou déblocage effectif. Rien n’est déclenché au chargement d’une page ou d’un ancien univers.
- Reconnaissance et mission persistante, 4/5 : frontière entre accès de jeu, verrouillage, dépense de sonde et exécution différée.
- Bataille privée avec pertes puis convoi protégé, 5/5 : ordonnancement, pertes réelles, impossibilité de jouer deux fois le même résultat, traitement d’une livraison bloquée. Hors socle.

## Points d’entrée vérifiés dans le moteur

| Fonction | Infrastructure réutilisable | À ne pas détourner |
| --- | --- | --- |
| Trajet | `FleetService::quote/send/arrive`, `MovementCalculator`, navigation figée et `FleetDispatch::schedule` (token + outbox transactionnelle), handler `FleetArrivedHandler` | Le catalogue ordinaire valide sa destination en tant que planète (hors exploration) et la rend selon ses périmètres. Aucun faux `Planet` pour le poste civil, aucune coordonnée colonisable ou visible dans Galaxie. |
| Renseignement | `EspionageService::resolve`, `EspionageReportReader::findVisible` comme exemple de périmètre | L’espionnage standard exige une vraie planète, décide la détection et les catégories, initialise le PNJ si nécessaire et peut prévenir le propriétaire. Le scénario ne détourne pas ces effets : il révèle seulement la flotte pirate propre à la quête. |
| Combat | `CombatSimulator` calcule un résultat sur des forces figées ; `CombatUnitFactory` prépare les unités et `CombatEventService::persistLosses` éclaire le contrat d’écriture | `CombatEventService::joinAttack/resolve` regroupe des attaquants par planète et fenêtre, lit des défenses, convoque les tiers et peut produire butin/conquête/récompenses. Ne pas l’utiliser comme rencontre privée sans l’isoler des effets partagés. |
| Convoi futur | `TransportService::deliver` et `ResourceService::deposit` comme références ; `DailyRewardService::claim` démontre un lot conservé lorsque le stockage est plein | Aucun retour d’excédent vers un poste appelé à disparaître, aucun changement au transport ordinaire. Aucune politique convoi n’est activée dans ce socle. |

`PirateDistressSite` retient uniquement les identifiants de l’empire actif,
du compte, de son univers et d’un système existant. Il ne crée ni planète ni
position d’orbite. Sa projection renvoie seulement le signal de détresse
et l’identification narrative d’un relais civil, pour le propriétaire. Une
adresse de système sous le brouillard ne fait pas partie de cette projection.
`PirateEncounterSnapshot` fige les familles/rangs/effectifs et les coûts de
construction fournis par un futur générateur serveur. Ce modèle ne reçoit
rien d’un formulaire, ne choisit aucun prix et n’accorde aucune ressource.
Pour le combat réel, conserver également un instantané des données de combat
ou une version immuable correspondante : le seul trio famille/rang/effectif
ne fige pas les statistiques du catalogue.

### Pièges à exclure avant la reconnaissance persistante

- `FleetVisibility::visible` accepte `destinationSystem` comme repli si la
  destination n'est pas une planète. Un trajet natif sans planète et qui
  n'est pas un espionnage ordinaire peut alors être visible à un allié qui
  connaît ses deux systèmes. Aucune mission de campagne ne doit emprunter
  une attaque ou un transport normal avec `destination = null`. Si un
  discriminant natif est finalement retenu, filtrer le propriétaire **avant**
  les listes, la carte, les arrivées, les notifications et les agrégats.
- `MovementCalculator::quote` et `FleetNavigation::freeze` savent viser
  le système (`target[4] = null`), mais leurs calculs de départ utilisent
  `origin[4]`. Pour un départ du relais, formaliser une ancre géométrique
  privée ; ne pas utiliser une fausse case du catalogue de planètes.
  Examiner `FleetHold::departure` pour la source de carburant et préserver
  le verrouillage du hangar. `FleetDispatch` prend une entité `Fleet` ;
  une mission privée doit reprendre la garantie outbox/jeton/échéance avec
  son propre worker, sans inscrire de faux message de flotte standard.
- Le futur prix de référence doit séparer `GameCatalog::costs` (catalogue
  M/N/AM/H) de la facture d'une commande joueur produite par
  `OperationCalculator` (modulateurs et coût électrique lié à la durée).
  Figer le résultat du choix métier ; ne pas recalculer après la reconnaissance.

## Plan avant migration : information, trajets et états

1. Choisir le placement dans un système admissible ; le civil privé est une
   ancre distincte qui ne consomme aucune position. Faire valider les conditions
   de découverte avant d’exposer le nom, le numéro ou les coordonnées de ce
   système. Empires existants : aucune création automatique en lisant des
   anciennes données. Une éventuelle initialisation explicite est ajoutée
   plus tard, sans enrichir les listes globales.
2. Route dédiée : GET/lecture par participation active uniquement, POST avec
   protection de formulaire et reçu idempotent. Le serveur lie la destination
   au site civil privé de cette quête ; il n’accepte ni identifiant de victime
   ni composition pirate du client. Vérifier une vraie sonde disponible,
   l’origine possédée et la cohérence compte/empire/univers. Ne pas modifier
   les catégories, probabilités, notifications ni gains de l’espionnage habituel.
3. Trajet de reconnaissance privé : préférer un trajet/état séparé, utilisant
   le calculateur natif et les contrats éprouvés du worker/outbox, plutôt qu’un
   simple mode de la route d’espionnage normale. L’option alternative est
   une flotte native avec destination privée typée, devant prouver qu’elle
   n’apparaît ni dans les missions publiques ni dans les arrivées de tiers.
   Une même transaction vérifie le reçu de départ, verrouille l’empire,
   l’origine puis les données privées dans un ordre constant et réserve une
   seule sonde. On ne valide pas la quête au départ.
4. À l’échéance, vérifier token, état, origine et phases sous verrou
   conformément au contrat retenu. Confirmer la dépense réelle de l’unique
   sonde puis produire, dans la même transaction, un rapport privé exact
   sur la flotte pirate figée. Un même événement rejoué ne crée ni seconde
   sonde perdue, ni deuxième rapport. Une requête d’un autre joueur, du même
   compte dans un autre univers ou d’un ancien empire détaché ne renvoie
   aucune donnée. Le rapport n’est pas un gain du tuto « Premiers pas » :
   ici, pas de restitution annoncée.
5. Défaite future : réutiliser **le même adversaire et le renseignement déjà
   obtenu**, sans nouvelle sonde. Le joueur peut reconstruire avant de
   retenter. Le futur combat doit attester les pertes réelles une seule fois
   par tentative. Une victoire unique crée au plus un droit de convoi, lié au
   même empire ; sa somme est pré-affichée et dérivée d’un instantané de coût,
   jamais recalculée à l’arrivée ni à la lecture.

## Décisions réelles encore ouvertes

- Destinations admises et phases de découverte/d’arrivée (y compris le rayon
  de placement, l'acceptation de voyages sur système non découvert, ainsi que
  ce que devient une reconnaissance interrompue ou l’origine pendant le vol).
- Le futur générateur n’a encore ni barème de difficulté ni coût détaillé :
  vérifier la manière de chiffrer le coût de construction de l’ennemi,
  notamment l’électricité ajoutée par les règles d’une commande réelle et les
  arrondis de 50 % par ressource. Aucun montant n’est fixé dans ce lot.
- Convoi gagné, puis stockage plein ou colonie perdue : option proposée,
  **non validée**, conserver la dette et permettre la reprise sur une colonie
  autorisée du même empire, sans crédit partiel ni duplication.
  Empire définitivement indisponible : politique distincte encore ouverte.

## Vérifications

Les tests de ce sous-lot couvrent placement dans l’univers attendu,
isolation de la projection et invisibilité après détachement, ainsi que
l’indépendance de l’instantané face aux modifications de ses sources.
La future recette devra couvrir deux départs concurrents, rollback/rejeu
du worker et du reçu, interception impossible, plein, changement de
propriétaire et livraison unique sur une base synthétique et isolée.

## Socle persistant de reconnaissance : parcours uniquement interne

Complexité 4/5 : écriture d'inventaire, ordre des verrous, confidentialité,
horloge et asynchronisme. La migration additive crée une table vide, sans
reprise d'historique ni modification des hangars et des flottes existantes.
Aucune route, ouverture de campagne, génération de rencontre ou planification
automatique depuis une page, une connexion ou un ancien univers.

- Clé de mission préparée côté serveur, unique dans sa participation.
  Ce n'est ni une limite d'une seule campagne à vie ni une règle d'un compte
  pour plusieurs univers. Le compte propriétaire enregistré est contrôlé
  avec la participation et l'univers. Le poste est dans un système
  préexistant, découvert par cet empire ; la lecture n'expose pas sa
  destination, ses coordonnées, le jeton, les coûts ni l'ennemi en vol.
- Le départ explicite verrouille participation → univers → planète →
  hangar → stock précis, refuse une sonde d'un autre rang, et retire une
  seule sonde disponible en échange d'un vol privé. Un second départ de
  la même mission ne retire rien. Si la transaction échoue, aucun départ,
  retrait ou message isolé n'est conservé. Le vol ne restitue pas la sonde
  et ne déclenche jamais le remboursement de « Premiers pas ». La
  réservation la rend indisponible dès le départ ; le rapport atteste sa
  perte lorsque l'arrivée est exécutée.
- La durée est figée par le calculateur natif avec la Sonde basique,
  les bonus de mobilité de l'empire et le multiplicateur de l'univers.
  La destination privée est le niveau système, jamais une planète
  colonisable ; le calcul interne utilise 100 % de la vitesse comme
  référence sans ajouter de réglage joueur. Le traitement n'engage pas
  de débit de carburant dans cette fondation non publique : avant
  ouverture la facture et le parcours de confirmation seront explicités.
- L'outbox du départ utilise un nouveau type privé et le relais habituel.
  À réception le worker relit participation, univers puis mission sous
  verrou et vérifie le jeton, l'échéance et l'absence de rapport. Un
  message trop tôt ou obsolète n'écrit rien. Pour un jeton courant reçu
  trop tôt, le worker rend le délai restant à Messenger via
  RecoverableMessageHandlingException : DelayStamp diffère le même message
  et conserve son jeton, au moins une seconde, sans créer de nouvelle
  ligne d'outbox. Les jetons obsolètes ou consommés ne provoquent pas
  de rejeu. La date du rapport est la date réelle du traitement.
  La recette locale avec RabbitMQ et MariaDB privés envoie volontairement
  le bon message avant terme, sans relayer d’abord l’outbox originale :
  le worker fait un retry retardé et ne rend le rapport qu’à l’échéance.
  L’outbox d’origine est ensuite relayée comme doublon, sans deuxième
  rapport ni deuxième sonde débitée. Le circuit standard et ses messages de flotte ne sont pas modifiés. Après remise, le rapport est le même au
  rejeu. La composition figée est identique détecté, non détecté, ou
  lorsqu'aucune détection n'a été renseignée. La donnée de détection
  existe pour les scénarios internes, sans nouveau calcul ni jet global.
- Une participation détachée ou un univers fermé ne reçoit pas de
  rapport ni de projection. La migration rattache la mission au compte,
  à la participation et au système avec effacement référentiel. Le
  traitement ne relit ni ne réserve une planète à l'arrivée, y compris
  si la planète d'origine a été perdue. Il ne recrée jamais de hangar
  ni de monde. Aucune restitution/compensation n'est introduite ; avant
  ouverture, fixer le traitement d'une interruption puis réouverture
  d'univers ou d'un détachement suivi d'une réactivation.

Combat, gestion du nouvel essai après une défaite, convoi, économie et accès joueur
demeurent hors de cette tranche. Le catalogue et l'espionnage normal ne
sont pas modifiés. Les migrations ne sont vérifiées que sur des bases
synthétiques jetables.

## Attaque : modèle pur, avant le vrai inventaire et les workers

Complexité 3/5 pour le modèle sans effets ; 5/5 dès que la réservation,
les pertes et le retour touchent le jeu. Aucun chemin du modèle n'est
appelé par un contrôleur, l'outbox, le tutoriel ou la flotte standard.

- **Adversaire immuable.** `GameCatalog::getTierForUniverse` et
  `CombatUnitFactory::ships` préparent le catalogue et les modificateurs
  technologiques. À la génération de la rencontre, le serveur doit figer
  les profils complets pirates et les paramètres de résolution ensemble
  (famille, rang, effectif, stats appliquées, coefficients de contre et
  options du moteur). `PirateBattleScenario` refuse une composition
  différente de celle révélée ; ce contrôle d'effectifs ne suffit pas à
  reconstituer les anciennes statistiques. Les profils figés eux-mêmes
  devront être stockés, jamais régénérés à chaque retour ou nouvel essai.
  Les neuf classes, coûts et règles de jeu générales restent inchangés.
- **Une tentative, une flotte.** Sous les verrous participation, univers,
  origine et hangar, réserver les vaisseaux payés et prendre les profils
  joueur après application des technologies courantes au départ. Deux
  départs simultanés ne peuvent pas prendre la même réserve. Enregistrer
  un numéro monotone par rencontre, la graine serveur, la composition,
  l'identité du joueur d'origine et des jetons de phase. Le départ d'une
  nouvelle tentative permet une flotte reconstruite et des technologies
  progressées, mais ne repasse jamais par la case sonde.
- **Trajet privé.** Réutiliser le calcul natif depuis la colonie vers le
  système du poste civil ; ne pas créer de `Fleet` standard visible, de
  faux objet `Planet`, de case colonisable ou d'arrivée PvP. Pour le retour
  le calcul doit respecter la géométrie privée du relais (le calcul normal
  attend une position de départ, voir le piège au-dessus), les données
  figées et les règles de destination à valider. Ne pas détourner
  `FleetRecallPlanner` ni le mécanisme d'occupation.
- **Résultat à échéance.** `CombatSimulator::simulate` est pur et reproductible
  avec une graine et des entrées conservées. Le modèle lance exactement deux
  camps fermés, sans files/victimes planétaires, stationnaires ou renforts ;
  il utilise directement ses pertes et survivants sans réimplémenter le
  moteur. Amener par une horloge et un signal de phase l'échéance autorisée
  de résolution ; aucune publication à l'arrivée si le combat doit encore
  durer. L'argument d'échéance du modèle n'invente pas une cadence pour la
  campagne : le futur worker devra formaliser la cadence des rounds et la
  cohérence des clôtures anticipées avant de montrer un résultat.
- **Pertes et retour séparés.** La future transaction de résultat doit
  consommer le reçu de cette phase, persister le rapport, retrancher
  exactement les pertes des vaisseaux réservés, et pour les survivants
  programmer un retour dans la même transaction. Ne pas utiliser
  `CombatEventService::persistLosses` tel quel : il touche une planète
  réelle et ses défenses ; ne pas tenter d'installer prématurément une
  flotte. Même en cas de retard du worker, le retour ne peut pas être
  accepté avant son échéance ni avant le résultat effectivement enregistré.
  Il installe les survivants de manière idempotente après relecture de la destination et du
  propriétaire. L'échec ou le nul garde la reconnaissance et permet un
  nouvel essai après règlement de la tentative. La victoire est terminale
  pour le combat, même avec un retour en vol : elle n'accorde encore
  ni droit, ni convoi, ni versement.
- **Durabilité préalable à l'ouverture.** Le modèle en mémoire n'est jamais
  une preuve persistante. L'adaptateur devra lire un rapport réellement
  remis pour cette participation et ce scénario, verrouiller l'agrégat,
  et enregistrer le résultat natif d'un seul traitement avec les reçus
  et l'outbox. Vérifier le rattrapage des événements précoces sur un vrai
  broker, les expirations/rejets de destination, le rejeu après rollback,
  et deux workers concurrents. Aucun secours ne rend une sonde ni ne
  notifie un autre joueur par défaut déjà dans ce modèle.

## Attaque persistée : orchestration interne, sans ouverture de campagne

Complexité 5/5. La migration ajoute une table vide attachée à la
reconnaissance, sans reprendre d’historique ni modifier les stocks
existants. Aucune route, politique universelle, prime ou convoi.

1. **Départ.** Vérifier en base, sous les verrous de participation, univers
   et colonie, une vraie reconnaissance remise au propriétaire. Le premier
   départ associe le scénario préparé par le serveur, dont les unités doivent
   correspondre exactement au renseignement figé. Les essais suivants
   relisent ce même scénario et doivent annoncer le numéro de tentative
   précédent : une requête obsolète ne peut pas démarrer un nouvel essai
   après une défaite. Verrouiller le hangar et ses rangs concernés, vérifier
   la composition exacte, figer les statistiques des vaisseaux payés avec
   leurs technologies, déduire ces effectifs et écrire le message de
   transaction au même commit. Aucun appel ne crée de flotte volante,
   n’envoie une sonde ou n’altère les stocks d’un autre joueur.
2. **Aller et combat.** Durée calculée par le moteur natif jusqu’à l’entrée du
   système du poste ; la résolution n'intervient qu'après cet aller et la
   fenêtre interne fournie par le scénario. Le délai utilise la borne max
   du scénario dans cette tranche ; il faut valider une vraie cadence et
   une éventuelle clôture anticipée avant mise en jeu. Le calcul de combat
   à échéance consomme la réservation survivante de la tentative et
   stocke les pertes/résultat dans la même transaction que l’outbox de
   retour ; le PvP et les combats planétaires ordinaires ne sont pas appelés.
   Les vaisseaux détruits ne reviennent pas. Sans survivant, il n’existe
   pas de trajet retour.
3. **Retour.** Les survivants ont une durée complète de trajet, égale à
   l’aller figé, à partir du résultat produit. Seule la phase de retour
   due les réinstalle sous verrou dans le hangar de la colonie d’origine
   encore possédée. Si elle a disparu ou changé de mains, conserver la
   réserve dans un état interne bloqué : ni remise à un tiers, ni
   disparition, ni redirection automatique. Avant ouverture, décider
   avec un cas de reprise explicite comment traiter ce blocage et les
   départs d’empire, fermetures et suppressions. Une victoire est
   terminale immédiatement pour cette rencontre ; un résultat défavorable
   permet de retenter après le retour éventuel, sans renouveler l’intel.
4. **Rejeu.** L'outbox attaque et retour transporte un jeton distinct par
   phase. Un jeton obsolète ou déjà consommé ne modifie rien. Un message
   valide d'attaque/retour reçu trop tôt demande à Messenger de rejouer
   le même jeton après le temps restant, sans résultat anticipé. La
   reconnaissance suit la même convention sans toucher aux messages Fleet.
   Tester aussi avec un vrai broker des séquences attaque/retour complètes
   et des horloges décalées avant toute ouverture.

La projection interne est réservée au compte propriétaire dans sa
participation accessible et ne renvoie ni coordonnées, jetons, graine, coût
ou profils d’ennemi avant résultat. L’agrégat conserve l’état courant et son
dernier rapport, pas encore un historique de toutes les défaites ni une
archive d’utilisateur. Les clés de tentative persistent assez pour clôturer
l’ordre actif ; prévoir un historique durable séparé si produit retenu.
Le carburant n’est pas débité par ce chemin interne ; le contrôle du fret du
hangar ne constitue pas un prix. Ne pas ouvrir le lancement aux joueurs avant
la validation des conditions de départ, de la durée publique, de la politique
de retour bloqué et de la génération/difficulté réelle du scénario.

Avant activation, auditer les scores et protections qui lisent
uniquement le hangar et les flottes ordinaires : compter correctement
les vaisseaux privés immobilisés (jusqu’à une perte réellement validée)
sans exposer leur destination ou présence dans les vues publiques.
Vérifier aussi le cycle de fermeture/suppression de l’univers et
l’interruption définitive du compte, faute de quoi la suppression en
cascade de la campagne pourrait effacer une réserve encore ouverte.

## Convoi protégé : devis exact et chronologie, sans paiement

Complexité 3/5 pour ce modèle pur, 5/5 pour la persistance, la livraison et
la gestion des interruptions. Rien ne crée une mission, une récompense
ou un crédit aujourd’hui. Le futur droit est lié à une seule rencontre
privée et à sa victoire effectivement enregistrée, pas à une tentative
défaite, un retour de flotte ou un clic de page.

### Prix et trajet examinés

1. Figer les coûts de la flotte pirate **avant la révélation du rapport**,
   pour pouvoir afficher le prix de la récompense dès le renseignement
   complet, avant un combat réel. Le futur générateur appelle une fois
   le lecteur pur `PirateConvoyQuote::captureCatalogue` sur sa composition
   serveur : pour chaque famille et rang, `GameCatalog::getTierForUniverse`
   puis `GameCatalog::costs(..., 1, effectif, 'ships')`. Additionner métal,
   nano, antimatière et hydrogène. Pas de consommation électrique,
   de remises de recherche, de bonus de coût d’opération, de conversion
   entre ressources ni de recalcul à la victoire, après défaite ou à la
   livraison. La composition et le prix figés vont dans le même rapport
   interne ; l’adaptateur doit transmettre cet instantané à la
   reconnaissance, il ne le fait pas encore ici.
2. Le devis calcule exactement **50 % de chaque ressource** : entier et
   reste d’une demi-unité. Par exemple, 83 métal catalogue donne 41,5 métal
   de devis, et 1 nano donne 0,5 nano. Aucun arrondi de paiement n’est
   appliqué. Les options sont de conserver les demi-unités (vérifier
   lisibilité et capacité dans l’UI), d’arrondir chaque ressource vers le
   bas (41 et 0) ou de l’arrondir vers le haut (42 et 1). Si un arrondi
   est retenu, l’appliquer une seule fois sur le total figé par ressource,
   pas par classe ou vaisseau, et annoncer le montant qui sera réellement
   délivré. À trancher avant l’ouverture.
3. Le convoi part du poste civil dans le système, jamais d’une planète
   pirate. En inversant le trajet, ne pas passer une position d’origine
   `null` directement à `MovementCalculator::quote` (qui modélise un départ
   planétaire). `FleetNavigation::point` transforme le système en point
   géométrique privé à la bordure, de position nominale 10.
   `FleetNavigation::distances(point, colonie)` puis
   `MovementCalculator::quoteDistances` donnent la même distance et durée
   que le trajet inverse vers le relais, à profils et vitesse égaux.
   Vérifié avec une autre case du même système et un système séparé.
   La durée effective du futur convoi sera calculée avec son profil
   validé, pas celle de l’escorte précédente ni un transport instantané.
   Aucun marqueur 10 n’est publié ni rendu colonisable. Un univers ou
   une galaxie incompatible ne donne pas de route de repli par défaut.

### Contrat du futur traitement transactionnel

- Une contrainte unique sur l’identifiant de rencontre et l’identité de
  la victoire garantit au plus un droit. Relire propriétaire, participation
  et univers sous verrou. Décider séparément s’il part automatiquement ou
  si le joueur choisit sa colonie de destination ; dans les deux cas
  enregistrer vrai départ/échéance et outbox privée dans la même transaction.
  Aucun accès autre joueur, flotte publique à intercepter ou interaction
  avec le transport classique.
- La preuve d’arrivée vérifie le jeton courant, le délai et le même
  propriétaire ; pas de crédit avant. Le modèle laisse explicitement l’état
  « arrivé, droit non consommé » tant que rien n’est crédité. Quand la
  politique de livraison est choisie, verrouiller la colonie et ses stocks
  actuels, créditer et marquer le droit consommé **dans le même commit**.
  `ResourceService::deposit(..., allOrNothing: true)` offre une primitive
  de capacité indivisible, à utiliser uniquement si ce mode est retenu.
- **Stock plein :** proposition à arbitrer, conserver le convoi entier en
  attente et montrer la capacité manquante par ressource. Un dépôt partiel
  exigerait un registre de reliquat et davantage de décisions ; ne rien
  perdre, ne rien déplacer, ne pas clore le droit par défaut.
- **Colonie perdue :** en attente privée sans dépôt sur une ancienne
  propriété. Option à valider : choisir explicitement une colonie du même
  empire encore autorisée, avec un nouveau calcul d’itinéraire et un
  délai si nécessaire, sans recréer le droit ni démarrer un second convoi.
- **Empire parti / univers clos :** ne pas créditer un autre empire,
  univers ou joueur. Décider séparément de la conservation, de la reprise
  possible et de la fin de droit, ainsi que du sort en cas de suppression
  de compte/univers ; ne pas traiter l’effacement en cascade comme une
  livraison. Aucun retour d’excédent vers un relais volatil n’est choisi.

Le modèle couvre victoire, départ unique, token obsolète, arrivée à
échéance et consommation unique après preuve de crédit future. Recette
requise plus tard : deux workers vraiment concurrents, rollback après
débit/crédit conditionnel, démarrage et rattrapage via RabbitMQ, détection
d’un ancien jeton, valeur exacte d’un cas impair, stockage à capacité
exacte, colonie transférée, fermeture puis rejeu. Carburant, cadence
publique, difficulté et nombre de missions restent distincts.

## V1 : vingt vraies rencontres préparées, après l’exercice distinct de Premiers pas

Le catalogue privé prépare vingt rencontres éditoriales réparties en
cinq chapitres, sans modifier l’exercice antérieur. Les trois premières
définitions et leurs formations restent inchangées. Aucun nombre de
chapitres ou de niveaux ne suffit à autoriser un lancement.
`PirateCampaignCatalogue::prepareForServer` assemble un instantané prix/
effectifs et un scénario de combat assorti à l'aide de la fabrique native.
Il reprend les contres, la recharge et le nombre de rounds du catalogue,
sans bonus pirates supplémentaire. Cela ne fixe aucune cadence publique,
ne choisit aucun système et ne crée aucune mission ou vague automatique.

| Ordre narratif uniquement | Signal civil | Formation pirate Basique (serveur) | Référence de test, pas un prérequis joueur |
| --- | --- | --- | --- |
| Le silence de Balise 17 | Le poste bloqué émet encore avec sa balise de secours. | 1 chasseur de patrouille | 2 chasseurs contre 1 constituent l’ouverture d’apprentissage. |
| L’escorte interceptée | La route de ravitaillement reste coupée et l’escorte ne répond plus. | 2 chasseurs de patrouille, 1 intercepteur | 4 chasseurs : premiers risques de pertes même si le combat est gagné. |
| La dernière liaison | Le dernier relais civil est brouillé et les escortes ne se coordonnent plus. | 1 chasseur lourd, 1 bombardier | Les intercepteurs sont un contre utile ; ne pas envoyer des patrouilleurs seuls à l’aveugle. |

Le lancement interne explicite conserve le scénario préparé au départ
de la reconnaissance, dans la même transaction que la sonde et l’outbox.
Toute tentative relit exclusivement ce profil, même si les prix, les
statistiques ou les coefficients du catalogue changent ensuite. La composition, son coût
catalogue et les profils figés ne sont pas sérialisés par la préparation.
Le projecteur interne associe la narration au rapport de reconnaissance
réellement remis, puis au résultat effectivement enregistré d'une attaque
rattachée à cette même reconnaissance. Il ne montre avant remise aucun
effectif, et le récit ne transporte ni prix ni échéance inventée. Un
ancien rapport sans profil reste lisible via sa projection d'origine,
mais ne peut plus lancer d'attaque ; la migration ne reconstitue rien.
Le futur écran utilisera la lecture autorisée et les dates déterminées
par la mission plutôt qu'une phase provenant du client.
Le conseil sur les intercepteurs n'apparaît qu'après remise de l'intel.
Les phrases de défaite/nul ne déduisent pas qu'un vaisseau a survécu :
le retour a sa propre phrase et exige un trajet de survivants réel.
Victoire et livraison restent deux preuves distinctes, sans montant,
date ni crédit inventé. La phase d’entraînement antérieure reste à part :
ni mission réelle, ni équivalent de la première rencontre.

Aucun déclencheur, emplacement, règle de déblocage, qualification de
l'univers, génération adaptative ou procédure de refus de convoi
n'est ajouté. Le catalogue et ses textes sont préparés, pas jouables.

### Chapitres de la V1 et tests tactiques

| Chapitre | Clés dans l’ordre | Fil civil |
| --- | --- | --- |
| Les appels interrompus | 1–3 | La balise de secours appelle à l’aide, l’escorte perd le contact, puis le dernier relais local est libéré. |
| Les routes trompeuses | 4–8 | Identifiants volés, faux relevés et routes falsifiées isolent des pilotes et des réfugiés. |
| La guerre des relais | 9–13 | Secours en tenaille et états-majors pirates coupent une chaîne de liaisons civiles. |
| L’arsenal des exilés | 14–16 | Remonter jusqu’à l’arsenal et rendre aux navettes leurs codes de route authentiques. |
| La nuit sur le réseau | 17–20 | Quatre écrans différents protègent des capitaux pirates ; le récit se ferme quand le réseau civil répond à nouveau. |

Les formations locales sont celles du tableau de calibrage : toutes les
onze familles de combat Basique sont présentes, avec deux capitaux à
la finale. Les noms du tableau qui révèlent une famille ou un nombre
(la passe, le verrou, le premier capital, ses écrans) ne sont montrés
qu’après l’intel : avant, titres et signaux restent neutres. Le dernier
chapitre porte aussi un titre public neutre ; « Les Capitaux noirs »
était un intitulé de travail, pas un indice en jeu. L’interface future
ne doit pas recommander une liste exacte de vaisseaux ni afficher un
taux de victoire issu du benchmark. Les conseils parlent de rôles
et de contres après le vrai rapport.

Le test de difficulté utilise le moteur R14 et le catalogue en Omega ×1,
des flottes et technologies synthétiques et des lots fixes de 64 graines.
Les chiffres suivants sont des choix candidats de test pour le cadrage,
pas des paramètres d’activation ni des garanties :

- **#6, #7, #10 :** comparaison prudente à quatre lourds et deux
  patrouilleurs, quatre corvettes et deux intercepteurs, puis deux
  destroyers et un lourd respectivement. Chaque cas a gagné 64/64,
  sans destruction totale observée. Les profils plus justes du tableau
  sont volontairement plus risqués ; l’indice reste qualitatif, et
  attendre de s’équiper reste un choix.
- **#17 et #18 :** les mélanges de destroyers, lourds et corvettes **sans
  bombardier** gagnent aussi 64/64 aux budgets métal 217 384 et
  224 488, proches des exemples à bombardiers (217 965 et 225 069).
  Sur #17, une formation sans destroyer peut gagner avec davantage
  d’attrition. Sur #20, une variante sans bombardier et à budget plus
  bas a gagné 63/64 avec une destruction totale : la composition
  compte, mais aucun écran n’est une assurance de victoire.
- Les tests gardent la valeur catalogue figée et **50 % exactement**
  par ressource sans plafond ni électricité. Le devis final de
  148 470 métal + 80 422 nano n’est pas un paiement : au stockage
  de base, même à vide, il ne tient pas ; un Dépôt de minerais
  Basique 2 et un stockage nano Basique 1 suffisent à vide.
  L’interface de combat ne doit pas faire de ce seuil de réception
  un prérequis. Dépôt, politique de refus et arrondi restent à décider.

Les délais de déblocage des itinéraires avancés n’ont pas été simulés.
Aucun point de passage ne rend obligatoire de construire son propre
capital. On n’ajoute ni calendrier, ni sélection automatique,
ni politique de remise de convoyage par ce catalogue.

## Dossier privé : présentation non publiée

Complexité 3/5 : projection et gabarit sans écriture, avec une séparation
stricte entre texte du catalogue et preuves privées. La future campagne
méritera une page dédiée : on y prépare un trajet et on consulte ses
rapports. Ce lot prépare seulement la fiche de la mission sélectionnée
côté serveur et un sommaire dépliable des cinq chapitres. Le sommaire
indique un ordre narratif, pas un état de déblocage ou de progression.
Aucun lien, formulaire, contrôleur, route ni inclusion dans un écran actif.

- Avant reconnaissance : texte civil et avertissement qu’une seule sonde
  basique sera perdue. Ni nom ni coordonnées du système, ni formation,
  ni devis ou indice tactique issu du catalogue. Cette première fiche
  reste privée à la participation, au compte et à l’univers accessibles.
- Après remise réelle du rapport : la formation figée, les conseils
  qualitatifs et le devis nominal exact (50 %, y compris un demi, sans
  électricité) deviennent lisibles. Pas de déduction numérique d’un taux
  de réussite ou d’une flotte idéale. Réserve de coques, électricité
  pour reconstruire et capacité du stockage servent de conseils, pas
  de conditions pour combattre.
- Les dates persistées distinguent départ, échéance prévue et traitement
  confirmé, sans transformer un retard en résultat. Le résultat de
  combat et les pertes de l’escorte ne sont visibles qu’après traitement.
  Défaite/nul conservent le renseignement ; un retour non confirmé ne
  prétend pas avoir rempli le hangar. La victoire ne marque rien comme
  expédié ou versé. Une future fiche doit lire la nouvelle preuve effective
  de convoi plutôt que prolonger ce texte si un crédit a eu lieu.

Le test rend le fragment Twig avec des variables strictes sur des preuves
synthétiques : signal des vingt missions, isolement compte/participation/
univers et tentative disjointe, dates et retards, rapport, défaites/nul/
victoire, pertes réelles projetées et devis. Ce test n’est pas une recette
d’un écran actif : l’intégration, l’accessibilité visuelle et le parcours
mobile restent à vérifier lors d’un montage autorisé.

## Ordre canonique privé et plan de raccordement

Complexité 2/5 pour la lecture pure livrée ici. Le catalogue fixe les
vingt clés. Le serveur ne lit comme courante que la première mission
sans victoire établie, et n’annonce son signal que si sa reconnaissance
n’existe pas encore. Les fiches déjà reconnues de la suite contiguë
restent consultables, y compris après la dernière victoire. Une victoire
est établie par le rapport de combat résolu d’une tentative persistée
liée à la reconnaissance du même empire : un départ, une échéance
dépassée, un nul ou une défaite ne suffisent pas. Ni retour en cours ni
convoi ne retardent la lecture du prochain signal. Une preuve plus loin
ne fait pas sauter d’étape ; deux lignes propriétaires portant la même
définition rendent l’ensemble ambigu, sans choisir arbitrairement.

La lecture n’autorise aucun départ. Au raccordement, employer les clés
de définition canonique aussi comme clés de mission pour le parcours
normal et charger leurs preuves propriétaires par une seule lecture
bornée de la participation active, puis revérifier l’ordre sous le
verrou de participation avant chaque écriture. Aucun compteur à
synchroniser, aucune table de progression supplémentaire. Les fixtures
vérifient le préfixe, les retards, le retour non terminé, le refus de
preuve étrangère et la consultation des vingt fiches après la finale.

### Étapes à exécuter après les décisions de mise en jeu

1. **Balise 17, proposition de placement.** Partir du système de la
   colonie active, à condition qu’il soit déjà découvert par cet empire :
   l’inscription normale enregistre déjà la découverte du système natal
   (`UniverseService`). Poser le poste à l’entrée du système via l’ancre
   privée existante, sans `Planet`, sans case et sans mutation au GET.
   Avant un POST, choisir et revérifier cette ancre côté serveur. Le
   joueur ne découvre pas artificiellement le système et n’a pas à
   dépenser une autre sonde avant le premier rapport. S’il n’existe pas
   de système natal utilisable, le repli proposé est un autre système
   découvert et joignable dans la galaxie de la colonie de départ ;
   sans candidat, informer sans fabriquer de découverte. Le placement
   des autres sites reste une décision, pas une règle codée.
2. **Entrée et commandes.** Proposer l’accès depuis le compte rendu de
   l’exercice « Premiers pas », sans en faire une condition : c’est un
   entraînement fictif, la campagne a des pertes réelles. Page privée
   authentifiée `UniverseContext`, sans cache partagé. Deux POST
   distincts, reconnaissance / attaque, avec CSRF lié à l’action, à la
   participation, à la mission et à l’origine ; redirection 303 vers le
   dossier. Ne pas accepter de cible, de flotte ennemie, de cadence ou de
   phase venant du client. L’attaque prend seulement les choix valides
   du hangar et un numéro de tentative précédente revérifié sous verrou.
   Ne pas publier de trajet, coordonnées, gains ou messages dans les
   listes des flottes, la carte ou les événements de tiers.
3. **Portes avant publication.** Arrêter et annoncer le vrai coût H₂
   (les services privés actuels ne le débitent pas) : piste aller seul
   de la sonde, aller-retour de l’escorte, débit atomique et réserve à
   clarifier avec les pertes. Réconcilier la règle native de 60 secondes
   par round et clôture anticipée avec la résolution actuelle du socle
   à sa borne calculée. Choisir quoi faire si la colonie d’origine
   disparaît, si l’empire sort, et vérifier l’effet des réserves privées
   sur abandon, protection et visibilité. Le convoi (destination, stock,
   reprise, disparition, arrondi et crédit) est un lot de décision
   distinct ; ne pas remplacer une preuve de livraison par un texte.
4. **Recette d’ouverture.** Vérifier les refus d’une autre participation
   ou d’un ancien contexte, sans effets GET ni après échec CSRF, le
   double POST concurrent, une tentative périmée et les vaisseaux survivants
   restitués au retour. Tester worker et relais réels pour attaque et
   retour, échéance et rejeu, puis le dossier sans JS, clavier et mobile.

Ordre de grandeur : 1–2 jours pour raccorder et recetter le parcours
privé hors décisions, puis 2–4 jours indicatifs après décisions pour
carburant, chronologie/retour et vérifications d'ouverture selon les
options. Le convoi reste à estimer séparément. Ce plan n’active rien.

## Devis comparatif H₂ et calendrier théorique (sans effets)

Complexité 2/5. Deux petits calculateurs internes, sans lecture de stock,
transaction, nouvelle règle ni raccordement. Pour comparer des trajets,
le profil réel des coques et de leurs recherches, la politique de
carburant courante et le multiplicateur de l’univers doivent être fournis
par le serveur. L’origine est une véritable position 1–9. Le poste est
l’entrée du système, notée cible sans position dans le moteur et traitée
comme l’ancre géométrique 10. C’est une distance interne, pas une 10e case.
Le moteur garde les limites natives : pas de trajet inter-galaxies,
arrondi à la seconde supérieure, minimum 30 s pour sonde seule dans son
système et 60 s dans les autres cas.

Référence catalogue Omega ×1, Basique sans recherche, vitesse 100 %, même
système, aucun stationnement sauf la colonne d’illustration :

| Départ et lot | Durée aller | H₂ aller seul | H₂ aller-retour | H₂ AR avec 10 min d’orbite |
| --- | ---: | ---: | ---: | ---: |
| Orbite 9, une Sonde | 30 s | 0,1 | 0,2 | non envisagé |
| Orbite 5, une Sonde | 38 s | 0,5 | 1 | non envisagé |
| Orbite 5, deux Chasseurs de patrouille | 19 min 45 s | 5 | 10 | 10,01 |

Le premier exemple est le plus proche dans ce catalogue à vitesse 100 %,
pas un minimum de H₂ imposé au jeu. Le coût natif n’est pas arrondi à
l’unité ; la sonde coûte donc bien une fraction au départ, selon l’option
retenue. Le devis reprend l’aller natif, deux jambes symétriques et le
supplément éventuel d’orbite en séparant leurs montants. La capacité de
réservoir + soute est informative, pas un débit ou une vérification de
stock. En comportement normal, le hangar peut fournir une part du
carburant et le stock paie le solde (FleetHold / ResourceService). Le
calculateur ne tranche pas entre l’aller seul et la réserve aller-retour
pour la sonde, ni le stationnement, le carburant non utilisé et
les pertes de la flotte d’attaque. Aucun changement aux flottes normales.

Le calendrier de combat reprend seulement un résultat natif terminal
déjà calculé **après l’arrivée** : le premier round échoit à +60 s, le
dernier à +60 s × nombre de rounds joués, borné à +600 s. Pour un résultat
en trois rounds, +60, +120 puis +180 s ; un résultat en dix rounds clôt
à la borne +600 s. Le calcul ne décide ni du vainqueur ni de l’heure
effectivement traitée par un worker. Les échéances restent internes :
aucune date d’issue anticipée, aucun résultat visible au joueur avant
l’arrivée, ni dans ce lot à aucun moment. Le moteur pirate continue de
fonctionner à sa borne actuelle ; ni les jetons, ni le traitement des
rounds et retours, ni la chronologie des combats ordinaires ne sont changés.

## Recette du trajet et du retour avec un vrai broker

Sur la branche locale, la reconnaissance, la résolution de combat et le
retour privés ont été exécutés par le transport `async` de Messenger,
alimenté par `OutboxRelay`, sur MariaDB et RabbitMQ jetables. Le réseau
Compose était interne, sans port ni publication de messagerie ; 71
migrations sur base vide et la validation du schéma ont réussi. Les deux
missions utilisaient un adversaire synthétique, un aller et un retour
de 60 s (plancher natif) et un round de 3 s **de fixture**, pas une
proposition de cadence normale.

- Deux reconnaissances privées réellement remises et une seule sonde
  retirée par mission. Les durées ont été observées sur l’horloge réelle.
- À chacune des deux phases de combat et de retour, réception précoce
  du bon jeton, replanification Messenger, aucune écriture anticipée ;
  le relais a également transmis la copie de l’outbox. Les copies
  obsolètes et les doublons n’ont pas réécrit le rapport ni le stock.
  18 réceptions, dont 4 reprises différées et 14 acquittements.
- Chaque rapport de défaite atteste la perte d’un des deux chasseurs
  partis. Le hangar valide passe de 3 à 1 au départ puis à 2 au retour.
  Pour l’autre mission, l’origine rendue invalide pendant le retour
  conserve un survivant réservé, ne remplit aucun hangar et ne
  redirige rien. Aucune flotte active, aucun combat ou rapport
  d’espionnage standard, aucune notification et aucun convoi.
- Cette recette couvre l’origine invalide sur le vrai broker, pas une
  panne RabbitMQ ou un rollback injecté sur le worker ; les tests
  MariaDB avec faute injectée et concurrence restent complémentaires.

Point de conception avant une clôture au vrai nombre de rounds :
`PirateAttackService` enregistre une arrivée **prévue**, mais la seule
première échéance worker est actuellement la borne de résolution.
Déclencher plus tôt nécessite d’abord une preuve d’arrivée et un jeton
de phase, puis un choix : effectuer des rounds privés au fil de l’eau
comme le moteur natif, ou geler un calcul interne après l’arrivée et
ne l’appliquer/révéler qu’à l’échéance du dernier round. Dans les deux
cas, prévoir reprise après retard sans avancer l’horloge, enregistrement
atomique du rapport/pertes et du retour, et aucune lecture anticipant
l’issue par sa durée. Le test broker est vert sur le comportement
actuel ; aucun horaire ni migration de production n’est changé.

## Contrat préparé : arrivée privée et calcul opaque

Complexité 2/5 pour le contrat pur, 4/5 pour raccorder persistance et
worker. `PiratePendingBattle` exige l’heure effective d’une arrivée
attestée par l’appelant ; une simple ETA passée ne suffit pas. Avec
les forces, le profil et la graine déjà figés, il utilise le simulateur
natif puis garde seulement les totaux de pertes, le résultat et les
survivants d’escorte regroupés. Pas d’historique par coque/round. Le
dernier round effectivement joué fixe une échéance worker privée
(60 s par round, plafond 10), distincte de l’heure d’application.
Avant cette échéance, l’objet ne donne rien à appliquer. Retard du
worker : l’heure effective fournie ne change pas, mais aucune
application n’est antidatée. Ce contrat ne tranche pas quelle preuve
d’arrivée conserver lors d’un retard de traitement de l’arrivée.

Le futur câblage, encore absent, devra :

1. Créer un ordre d’arrivée avec le jeton de phase lors du vrai départ.
   Sous verrou empire → univers → tentative et vérification d’échéance,
   recueillir la preuve d’arrivée, déposer le résultat privé et son
   ordre différé **dans une seule transaction**. Relire les tentatives
   déjà lancées avec leur contrat actuel. Étudier une clé JSON interne
   versionnée dans `timeline` : aucune nouvelle colonne n’est utilisée
   dans le modèle pur ni promise pour le raccordement.
2. À l’ultime échéance, vérifier le jeton courant, le numéro de tentative
   et la réservation, puis appliquer le résultat, son marqueur consommé,
   les pertes et l’ordre de retour dans la même transaction. Un rollback
   doit rendre le checkpoint non consommé et une copie déjà acquittée
   ne doit jamais réappliquer les pertes. Tester les deux phases en
   broker réel, en concurrence et avec faute injectée avant activation.
3. Avant application, ne lire que « Combat en cours » et, au besoin,
   la borne maximale indépendante du résultat, sans promettre l’heure
   d’issue. Retirer de toute lecture publique ou propriétaire la vraie
   valeur de `next_at`/du checkpoint pendant ce calcul : même sans
   rapport, sa date pourrait révéler le nombre de rounds. Aucun rapport,
   victoire, perte publiée, départ de retour ou nouvelle mission avant
   l’écriture effective. Les lectures utilisent les champs durables
   ordinaires, jamais le checkpoint privé ou le jeton.

Les tests purs vérifient l’absence de preuve et l’arrivée précoce,
l’issue en deux rounds et le plafond dix, le retard, la relecture du même
JSON lors d’un rollback simulé, le refus d’un numéro ou d’un checkpoint
invalide et la consommation unique du résultat. Ils ne prouvent aucune
nouvelle intégration DB, RabbitMQ ni modification du moteur : les vrais
workers, échéances et lectures fonctionnent encore comme précédemment.

## Raccordement privé V2 : arrivée et dernier round

Le nouveau point d’entrée interne est explicite ; aucune route ne l’appelle.
Un départ V2 planifie d’abord l’arrivée, tout en figeant profil, graine et
cadence native (60 secondes, au plus dix rounds). Les tentatives V1
gardent leur échéance et leur fonctionnement au retour. Il est impossible
de changer de mode ou cadence lors d’une nouvelle tentative du même combat.

Le worker atteste l’arrivée à l’horloge réelle de traitement. L’ETA
persistée reste l’origine du calendrier **nominal**, avec un jeton et un
checkpoint opaques atomiquement créés sous verrou. Un dernier round déjà
dû est rattrapé sans antidater le rapport : même si l’arrivée est reçue
tard, le résultat ne devient durable qu’avec la seconde transaction,
et le retour est programmé à partir de cette application effective.
Le `next_at` privé n’est pas exposé tant que le rapport est absent ;
le dossier n’affiche que le constat d’arrivée et « Combat en cours »,
sans date de résultat, perte, retour ou indice issu du checkpoint.

Quand le second jeton est dû, le worker relit le même checkpoint,
applique pertes/rapport/ordre de retour et retire la donnée opaque
dans la même transaction. En cas d’échec, rien n’est publié et le
checkpoint reste disponible sous le même jeton. L’identifiant en
vigueur change à chaque phase; une redélivrance ancienne est sans effet.
Rien n’est ajouté au schéma : la donnée transitoire est incorporée au JSON
de la tentative V2 et éliminée lors de la publication. Aucun changement
aux flottes publiques, carburant, convoi, droits ni autres règles.

Recette synthétique sur ce code : deux campagnes V2 à deux rounds,
dont une livraison d’arrivée précoce reprise et une retardée ; ancienne
campagne V1 comme témoin. Avec le vrai transport RabbitMQ, 20 réceptions,
3 reprises, 17 acquittements et trois retours uniques ; aucun effet
public et aucun changement de réserve avant application. MariaDB isolée :
132 tests, dont clôture à dix rounds à +600 s et échec injecté du nouvel
ordre de retour après une arrivée traitée tardivement ; le rapport,
le stock et le checkpoint initial sont restés intacts avant rejeu.
La borne de dix rounds et l’échec d’écriture sont testés sur MariaDB
avec une horloge contrôlée, pas en temps réel sur RabbitMQ.

## Espace joueur prévu : une page dédiée, deux accès cohérents

Complexité 2/5 pour la projection, 4/5 avant ouverture des commandes.
La campagne est permanente et se poursuit sur vingt missions ; elle
n’est ni un événement qui expire, ni une étape à cocher du tutoriel.
Proposition : page dédiée reliée par une entrée secondaire sous
« Événements », placée hors des listes « en cours / à venir » et du
tiroir horaire. Le joueur régulier peut y reprendre une mission sans
rouvrir le tutoriel. Un second lien depuis le résultat de l’exercice
« Premiers pas » fait découvrir le parcours, en rappelant que les
vaisseaux y sont réels et peuvent être perdus. Ne pas demander la
validation de l’exercice pour ouvrir la campagne. Ne pas ajouter un
lien au menu principal ou changer la coque des pages actuelles.

La projection pure `PirateCampaignWorkspace` reçoit le contexte
courant et au plus vingt lignes du serveur. Elle donne la mission
courante, la sélection par numéro et les dossiers antérieurs
consultables. Un numéro futur ou inconnu est refusé sans distinction.
Le signal non encore matérialisé reste un état neutre sans cible,
briefing mensonger, composition, crédit ni autorisation de lancer.
Une fiche déjà reconnue est rendue par le présentateur privé existant.
Après la finale, la dernière fiche s’ouvre par défaut et les vingt
restent consultables. Aucun contrôleur ou fragment actif ne consomme
la projection ; aucune route, entrée ni action n’est enregistrée.

### Raccordement à ouvrir seulement après arbitrages

- GET privé d’une page dédiée, par exemple sous `/evenements`,
  éventuellement avec numéro de mission. Partir de `UniverseContext` ;
  relire en base le propriétaire courant, le même univers ouvert et la
  participation non détachée. Charger par lot les reconnaissances des
  vingt clés, puis les tentatives rattachées à ces seules reconnaissances.
  Ne pas interroger par carte ni lire l’outbox de la page. Répondre sans
  cache partagé ; aucun GET ne débite, ne crée une reconnaissance ou
  ne génère un site. Sans contexte ou pour une fiche non autorisée,
  utiliser les réponses neutres du jeu, sans préciser le propriétaire.
- Afficher une mission à la fois, avec une liste historique légère et
  le bouton Retour du navigateur naturel. Préserver la navigation sans
  JavaScript, les libellés accessibles et l’état du numéro de mission.
  En recette : autre compte / allié / ancien univers, sortie de l’empire,
  lien direct vers mission future, historique et retour arrière, clavier
  et petite largeur, aucune fuite d’horaires calculés ou de composition.
- Plus tard uniquement : les deux POST distincts, CSRF et relecture
  sous verrou prévus dans le plan précédent. Afficher au joueur la sonde
  vraiment perdue, le coût exact, la durée et les pertes possibles
  avant l’envoi ; pas de faux bouton ou de prix inventé d’ici là.

### Ce qui est cadré et ce qu’il faut choisir

Cadré : vingt missions privées, exercice fictif séparé, une vraie
sonde perdue par reconnaissance, même renseignement après échec et
pertes réelles des attaques, résultat réel avant le signal suivant,
sans attendre une livraison. La base du devis du convoi vaut 50 %
des coûts catalogue figés, hors électricité. Le trajet et l’issue ne
sont pas publics. La cadence native et la preuve d’arrivée sont déjà
testées en V2 privée ; elles ne suffisent pas à ouvrir des commandes.

À arrêter avec Kévin avant ouverture : (1) quels univers/participations
admettent le pilote et comment placer les signaux après Balise 17
(système natal déjà connu proposé pour la première, repli si aucun
système joignable) ; (2) H₂ de la sonde aller seul ou aller-retour,
réservation aller-retour de l’escorte avec ou sans orbite, restitution
du reliquat après destruction et source du carburant ; (3) traitement
des survivants si la colonie d’origine ou l’empire ne peuvent plus les
recevoir ; (4) destination et reprise du convoi, stock plein ou
capacité totale trop petite, disparition de l’origine/empire,
arrondi éventuel et moment du crédit. Présenter ces options séparément :
le coût ou le plein ne constituent pas un nouveau verrou de progression.

## Source authentifiée non routée et noyau du convoi autorisé

Le lecteur part de l’identité authentifiée et de sa sélection courante.
Il relit en base la participation exacte, son propriétaire actif et
l’univers accessible ; un changement de sélection ou un détachement
n’attend pas la fin d’une ancienne session. Les vingt clés catalogues
bornent le batch reconnaissance + combat, avec ancre filtrée dans le
même univers. Pas d’accès direct par compte, pas de scan des joueurs,
pas de suivi d’activité, pas d’écriture. Le test MariaDB compare la
lecture à froid de 1 et 20 preuves (budget inférieur ou égal à six
requêtes, croissance au plus une), avec uniquement des SELECT et pas
de fuite d’issue opaque. Aucun endpoint n’appelle ce lecteur.

Décision du pilote qui supersède les anciennes pistes : convoi protégé
à 50 % des coûts déjà figés, crédit automatique à l’arrivée si le lot
entier tient ; sinon attente du lot complet, sans crédit partiel.
Lorsque la destination reste possédée, même si elle est pleine, on
réessaie sur cette colonie sans autre voyage. Lorsqu’elle est perdue,
seule une autre colonie possédée du même empire peut être choisie ;
aucun transfert à un tiers ou à un nouvel empire.

Complexité 2–3/5 pour le noyau pur : `PirateConvoyTimeline` porte droit,
participation, univers, jeton et position attestée. La perte signalée
pendant le vol ne redéfinit ni l’ETA ni la position du convoi. Il faut
l’attestation de l’arrivée avant de faire un autre trajet, et celui-ci
part du point attesté, pas d’un retour fictif à l’ancre civile. Sans
preuve initiale ou de départ, le droit reste en attente sans émission.
Un dépôt plein n’autorise pas de changer de destination. La perte
n’est constatée qu’après relecture effective des droits, le dépôt
n’est marqué réglé qu’après le crédit intégral. `PirateConvoyQuote`
préserve exactement les demi-unités, et refuse une représentation
imprécise dans le type DOUBLE plutôt que d’arrondir le lot sans le dire.

Raccordement 4–5/5, pas encore réalisé : prévoir une table privée,
une contrainte de règlement unique par bataille gagnée et la cascade
de la même participation. L’adaptateur devra créer un UUID de phase
neuf à chaque trajet, lire une vraie victoire et sa preuve de destination,
et ne rien créer rétroactivement au GET. Repère/vitesse du transport
civil à figer au départ avec le calculateur natif, sans réutiliser la
réserve H₂ des survivants ni une Fleet publique. Avant tout branchement,
relire l’ordre commun des verrous en concurrence : empire → univers →
droit → planète → stocks, réévaluer la réutilisation dans la transaction
de fin de combat et ne pas effectuer d’écriture de ressource à ce moment.
À l’arrivée, `ResourceService::deposit(..., allOrNothing: true)` et le
droit consommé partagent la même transaction ; échec outbox/dépôt doit
laisser le droit ou le prochain trajet rejouable. Restent à recetter :
réaffectation en vol, origine supprimée ou perdue, ancien propriétaire,
stock juste sous/au seuil et deux opérations concurrentes. Le contrat
pur ne crée ni trajet réel, ni dépôt, ni commande ou route.

### Premier raccordement durable du convoi (interne)

La table additive conserve un droit par progression de combat, pas par
numéro d’essai. La preuve de victoire V2, l’identité d’origine, le lot
exact, le trajet versionné et le reçu appartiennent au même droit.
Une phase en cours a un UUID neuf lié à sa génération ; un ancien
message ne peut ni déposer ni changer le trajet. Aucune ancienne
victoire n’est parcourue ou recréée lors de la migration.

L’entrée interne distincte pour le pilote Omega n’a aucune route
appelante aujourd’hui. Le service de victoire crée droit et événement
privé dans la transaction du vrai rapport, sans dépôt. Le premier
trajet suit la géométrie native et la vitesse d’un cargo Basique,
sans inscrire de Fleet ni facturer un transport civil au joueur.
Une destination initiale non attestée ou injoignable laisse le droit
en attente, sans départ fabriqué. À l’arrivée, le worker vérifie
possession et participation actuelles et tente le dépôt entier.
S’il échoue faute de place, le même propriétaire peut redemander
le dépôt sur cette même colonie, sans nouveau trajet. Si celle-ci
est perdue, le lot reste entier : aucun transfert au nouveau
propriétaire. La mise en route d’une seconde destination n’est pas
implémentée dans ce sous-lot.

Après Balise 17, la mission 2 devient courante dès la victoire ;
le lot de la mission 1 reste attaché à son droit historique et ne
conditionne aucune progression. Avant raccordement de la page, la
fiche historique devra rendre l’état réel du convoi et ne plus
afficher uniquement le devis lorsqu’un reçu existe. Pas de choix
de destination ou de bouton de reprise actuellement visible.

Recette MariaDB synthétique sur ce lot : 82 tests / 635 assertions,
72 migrations sur base vide, validation schéma et conteneur.
Vérifiés : absence de rapport et de convoi avant publication, un seul droit,
échéance précoce rejouée et arrivée tardive, réception en mémoire par
Messenger issue de l’outbox, lot 41,5 M / 0,5 N / 3,5 AM exact,
stock court d’un demi sans autre dépôt, libération exacte de la place,
rollback du reçu et du stock puis reprise unique, destination passée
à un autre propriétaire, régressions des flottes, et dossier Balise 17
toujours consultable alors que la suite est ouverte. Pas de recette
RabbitMQ réelle de ce nouveau message à ce stade.
