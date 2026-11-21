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
  message trop tôt ou obsolète n'écrit rien ; un message arrivé trop tôt
  n'est pas replanifié dans ce socle et le relais normal porte le délai.
  Avant mise en jeu, un événement valide reçu trop tôt doit être replanifié
  ou rattrapable à l'échéance avec le même jeton, sans remise anticipée :
  vérifier transport et worker sur horloges décalées. La date du rapport est la date réelle du traitement. Après remise, le rapport est le même au
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
