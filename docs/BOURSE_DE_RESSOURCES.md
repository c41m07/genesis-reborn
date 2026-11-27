# Bourse de ressources

## Parcours et ouverture

La station commerciale A:0:0 appartient à l’univers, pas à un joueur. Elle
n’ajoute ni système explorable, ni planète, ni position attaquable. Lorsqu’elle
est ouverte, son repère au centre de la galaxie A et le lien dans la légende
ouvrent directement la Bourse. La page Bourse, aussi accessible depuis le
menu Opérations, juxtapose le marché et le devis
(maquette A) ; sur mobile ces deux sections s’empilent. Le suivi est propre à
l’empire qui a envoyé le convoi. Le panneau **Flottes en vol** compte ses
convois et donne accès à leur suivi. Dans **Galaxie**, le journal et la
carte suivent l’aller vers A:0:0, puis le retour vers la colonie. Un rappel
repart du point déjà atteint. Si la colonie de retour change, la carte
repère la nouvelle destination. Le suivi continue si la Bourse ferme.

L’administrateur ouvre la station dans **Univers → Gérer → Bourse de ressources**
et précise la cible de chacun des quatre matériaux. Le stock initial est cette
cible, une seule fois. La cible limite le réassort des marchands, pas le
stock reçu des joueurs : un échange peut porter la réserve au-delà de la
cible, même si celle-ci vaut un milliard. La station ne remet que le stock
réel disponible après déduction des réservations. Une modification ultérieure
de cible ne crée et ne détruit aucun stock. La station n’est pas ouverte automatiquement dans un
univers. La fermeture empêche les nouveaux départs et le réassort ; les
missions déjà engagées continuent. À la réouverture, le prochain vendredi
reprend le calendrier sans distribuer rétrospectivement les semaines fermées.

## Cours, devis et trajet

Métal, nano, hydrogène et antimatière sont échangeables directement, sans
monnaie persistante. L’étalon initial est 8 H = 4 M = 2 N = 1 AM. Les frais
de 2 % sont prélevés sur la valeur du lot cédé avant arrondi au nombre entier
d’unités reçues. L’électricité n’est pas cotée. Le prix marginal monte avec
les achats et baisse avec les ventes exécutés. La courbe de lancement est
bornée à 50–150 % de l’étalon et l’échelle dépend de la cible. Elle est
intégrée sur tout le lot ; un devis tient aussi compte des réservations en
vol, sans les confondre avec le cours publié ou les volumes exécutés.

Le devis ne réserve rien et expire en deux minutes. À la confirmation, le
serveur compare les conditions recalculées, débite le fret et le carburant,
retire les cargos du hangar, réserve le montant à la station et programme
une arrivée. Conditions modifiées : rien ne part sur l’ancien devis.
L’hydrogène de vol reste distinct de l’hydrogène qui peut servir de fret.
Deux compositions identiques, avec les mêmes bonus, paient le même carburant
quelle que soit la colonie : le pilote utilise le coût standard d’un trajet
de référence de 100 unités locales dans chaque sens. La durée est fixée à
30 min par trajet, y compris dans un univers accéléré. Le convoi n’est pas
une cible de combat ; les missions ordinaires ne changent pas. Les cargos
en transit restent comptés dans le classement et la protection.

À A:0:0, le reçu d’échange débite la station et y crédite le fret initial.
Le joueur ne touche pas encore au lot reçu. Au retour réel, les cargos
rentrent et le fret est déchargé jusqu’à la capacité de la colonie ; le
surplus reste en soute. Avant l’échéance de l’aller, un rappel annule la
réservation et rend le fret initial après son trajet de retour. Si la
colonie de retour est perdue, le convoi attend une nouvelle colonie du même
empire puis reprend un trajet de 30 min. Il ne crédite jamais le conquérant.
Une réponse tardive du relais ne donne pas les ressources plus tôt ; la
deuxième jambe commence après l’échange constaté.

## Convoi de marchands

Vendredi à 9 h en Europe/Paris, le relais recalcule pour chaque univers
ouvert et chaque matériau :

> apport = min(⌊unités réellement vendues par la station aux joueurs / 2⌋,
> max(0, cible − stock physique))

La semaine est fermée à 9 h : la borne de début est incluse, la borne de
fin exclue. Le stock physique comprend les lots réservés qui n’ont pas
encore quitté la station. Un devis, un lancement ou un rappel ne compte
pas. Aucune distribution en l’absence de vente, aucun retrait au-dessus
de la cible. Le journal des livraisons PNJ ne modifie ni cours ni volume
joueur. Le relais reprend une occurrence par univers à chaque passe
de rattrapage, sans pouvoir la compter deux fois.

## Exploitation et vérification

Les états et réservations du marché sont persistés. Toutes leurs écritures
se partagent le verrou d’univers. Le règlement et le retour passent par
l’outbox et la file asynchrone déjà déployées ; chaque changement de jambe
renouvelle le jeton. Les livraisons marchandes utilisent une clé unique
univers/date civile et sont traitées par le relais d’événements, pas par
le notificateur. Le suivi des échecs se fait dans les journaux du relais
et la file asynchrone. Le relais sonde les univers concernés au plus une
fois par 30 secondes. Aucun cron ni secret supplémentaires.

Critère de fin : un joueur confirme un prix affiché, perd le fret d’aller
et les cargos au départ, puis reçoit le lot convenu une seule fois au
retour, y compris après rejeu. Un autre joueur ne peut pas prendre un lot
déjà réservé. Le vendredi n’ajoute qu’un apport attesté, sans changer
l’indice. Work doit exécuter la CI complète sur la branche finale avant
fusion ; l’ouverture d’un univers et toute migration de sa base restent
des actions d’exploitation distinctes.
