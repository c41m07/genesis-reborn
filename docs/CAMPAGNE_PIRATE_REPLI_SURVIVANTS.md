# Campagne pirate : contrat privé de repli des survivants

Ce lot local ne rend aucun trajet disponible. Les essais de campagne actuels réservent des vaisseaux, pas du carburant, et le drapeau « retour bloqué » ne persiste ni position ni réserve. Les tentatives anciennes doivent rester en attente, sans repli automatique, sans crédit au propriétaire actuel de l’ancienne colonie et sans reconstitution supposée du trajet.

Le futur lancement devra conserver les coordonnées complètes de sa vraie colonie d’origine avant qu’elle puisse disparaître. Une fois le vrai retour traité, le worker pourra attester sa position à ces coordonnées uniquement si son trajet et ses données de navigation le prouvent. Une arrivée seulement prévue, ou le site du combat, ne suffit pas. Il faudra également persister la réserve effective de H₂ qui reste sur les porteurs survivants et sa révision ; ne jamais prendre le fret, le stock de la destination ni la récompense du convoi pour du carburant.

Le calculateur privé exige cette attestation, liée à l’identifiant de campagne, à la tentative close, à l’univers, à l’origine et à la révision actuelle du lot. Il exige une vraie destination différente, possédée au moment du devis par le même empire actif du même univers et dans la même galaxie. Il compare un coût natif aller simple à la réserve effective, sans arrondi favorable. Le point de départ est une vraie case ; on ne fait pas passer un emplacement inconnu ou une ancre de système pour une planète. Durée et arrivée sont des secondes réellement écoulées ; le devis n’écrit rien.

Pour une intégration ultérieure : conserver le barème et les réglages natifs du trajet à partir de données serveur autorisées, relire et revalider la preuve, les porteurs et les droits sous les verrous du domaine lors du clic, puis réserver une seule fois et programmer l’arrivée réelle. Recontrôler le propriétaire avant toute livraison. Si une donnée manque ou change, laisser les survivants privés en attente. Ce document ne définit ni schéma, ni action, ni nouvelle politique de trajet.

## Préparer la réserve et distinguer les deux arrêts possibles

- **Au départ**, utiliser `PirateFlightQuote` et le même profil natif de vol que la commande, avec la fenêtre `combat_window_seconds` explicite pour l'expédition, pas l'attente du worker. La sonde sans retour réserve quand même l'aller-retour, sans orbite ; son rapport entérine la perte de la sonde et de tout ce budget. L'expédition réserve deux tronçons et l'orbite entière. Le moteur natif consomme la fenêtre orbitale allouée au combat terminé, même s'il se termine avant le dernier round possible. Figés sous le verrou de lancement, les montants transitent seulement comme besoin de `FleetHold::departure` : prendre la réserve du hangar utilisable, puis le complément du stock d'origine avec `ResourceService`. Vérifier ensemble les vaisseaux restants, leur fret et leur réserve, et enregistrer débit, instantané et outbox dans la même transaction. Le code de ce lot n'effectue aucun de ces débits.
- **Après combat**, le budget réservait déjà l'aller et l'orbite. Il ne reste que la composante du retour. La borner par la capacité réellement disponible des vaisseaux survivants, calculée depuis leurs effectifs exacts et profils figés ; la campagne n’embarque pas de fret. Le jeu ne dispose pas d'un historique de carburant par coque : la borne est une preuve de capacité du lot survivant, pas une distribution de l'ancien chargement. Si même le retour ne peut plus être financé, ne pas enregistrer de retour effectivement parti ; ce lot n'invente pas la suite dans le cas où l’origine reste valide.
- **Origine perdue avant le départ retour**, au moment où le combat est effectivement terminé : ne pas lancer un trajet déjà impossible. Le worker peut attester le maintien à l'ancre du vrai système du combat, avec la réserve du retour encore portée. Le devis d'un trajet volontaire réutilise les distances natives symétriques d'une colonie vers cette ancre, sans inventer de case, de point intermédiaire ni de mouvement déjà accompli.
- **Origine perdue après le départ retour** : une fois le tronçon intégral effectivement traité, attester l'ancienne case figée même si l'entité planète a disparu. Le trajet a consommé le budget retour, le solde attesté est zéro. La disparition de la colonie ne remet pas ce carburant dans les réservoirs. Une simple échéance échue, un changement d'hôte constaté hors traitement, ou un ancien `returnBlocked` ne suffisent pour aucun des deux certificats.
- **À dire avant départ** : l'estimation montre l'aller-retour, l'orbite, la réserve et le complément au sol séparément. « Le repli ne sera proposé que si vos survivants sont encore localisables et portent assez de H₂. Si le retour est déjà parcouru, son carburant est dépensé. » Le moteur natif ne propose pas de réserve volontaire supplémentaire : la part de H₂ demandée au-delà de la réserve de navigation est du fret, pas un moyen de financer le repli. Ne pas afficher dans la future UI une possibilité garantie de récupérer toute flotte dont la colonie disparaît.
- **À raccorder plus tard** : sceller campagne et tentative avec les deux extrémités et le budget de lancement ; après pertes, reconstituer depuis ce lot les seuls profils survivants, enregistrer la révision et la phase effectivement consommée au même commit que le traitement réel ; seul cet écrivain peut hydrater l'attestation. Les futures commandes relisent la même preuve, la propriété et la réserve sous verrou avant le départ et à l'arrivée. Aucun autre service, stock, message, route, schéma ou règle d'escale n'est modifié par le présent lot.

## Modèle d’une première étape de repli volontaire

Le modèle pur de trajet ne crée ni commande joueur ni outbox. Un futur service
doit consommer la preuve initiale et sa révision sous verrou, puis ajouter
atomiquement un ordre avec un nouveau jeton et une génération supérieure,
une destination possédée figée et la durée du devis natif. Les événements
précoces, étrangers à la tentative, déjà joués ou dont le jeton est périmé
n’installent aucun vaisseau.

À l’arrivée observée, trois résultats sont distincts : installation si la
colonie est toujours au même empire et si l’ensemble du lot est installable ;
destination perdue ; capacité de portage insuffisante. Le carburant non consommé
par un trajet plus court est le vrai reliquat du lot. À l’installation il doit
rejoindre uniquement la réserve du hangar avec les vaisseaux, jamais le stock
ou le fret, dans la même transaction. Après un échec d’installation, conserver
vaisseaux et reliquat au dernier point atteint. Ne jamais réutiliser l’ancienne
preuve pour imaginer une seconde commande : elle est consommée et tout futur
nouveau départ exige une attestation distincte du worker. Le présent incrément
ne crée ni cette commande, ni cette attestation renouvelable.

## Raccordement interne au worker

Le service du pilote Omega calcule un devis privé sous verrou, puis le recalcule
au départ et n’accepte que le digest approuvé. La source reste l’attestation du
vrai retour V2 : mêmes compte, participation, monde, tentative et révision,
profils mouvement V2 et vaisseaux survivants exacts. Aucun prix, date, profil,
solde ou coordonnée client ne peut autoriser l’action. L’heure de départ utilise
le premier instant UTC entier qui n’est pas antérieur à l’heure réelle sous
verrou. L’intégralité de la durée reste à parcourir.

Un checkpoint prive l’ancienne preuve de toute nouvelle commande et écrit
simultanément l’outbox du type privé. La prise en charge anticipée reprend le
même jeton, et tous les doublons après résolution sont sans effet. À l’issue,
la même participation doit posséder la colonie et celle-ci doit correspondre
au point réellement parcouru. La capacité actualisée du hangar est vérifiée
avant toute écriture de vaisseaux ou de reliquat. Aucun stock planétaire, fret,
convoi, joueur tiers ni flotte publique n’est modifié. Si le hangar ne peut
pas recevoir le lot entier, ou si la colonie est devenue indisponible pendant
le vol, le lot et le solde demeurent au point attesté par le trajet ; il n’y a
ni redirection automatique ni deuxième commande attachée à l’ancienne preuve.

Ce raccordement ne crée aucune route, commande joueur, activation d’univers,
navigation ou migration. Le traitement production du pilote n’est pas ouvert.
