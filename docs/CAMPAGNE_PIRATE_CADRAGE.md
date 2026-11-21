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
