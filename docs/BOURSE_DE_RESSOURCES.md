# Bourse de ressources

## Parcours et ouverture

La station commerciale A:0:0 appartient à l’univers, pas à un joueur. Elle
n’ajoute ni système explorable, ni planète, ni position attaquable. Lorsqu’elle
est ouverte, son repère au centre de la galaxie A et le lien dans la légende
ouvrent directement la Bourse. La page Bourse, aussi accessible depuis le
menu Opérations, affiche les cours puis le formulaire de convoi. Le bandeau du comptoir montre les cargos aux quais de la station. Celui du marché des joueurs montre deux commandants devant la baie vitrée. Le récapitulatif se place à côté de la saisie sur grand écran et en dessous sur mobile. Le suivi est propre à
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

Le comptoir échange métal, nano, hydrogène et antimatière par troc ou en crédits. Les cours initiaux sont 2, 4, 1 et 8 crédits par unité. Le cours marginal dépend de l’écart entre réserve cible et stock physique de la station. La courbe est bornée à 50–150 % de l’étalon à une distance de la cible égale à la profondeur (la cible bornée entre 100 et 1 000 000 unités). À cible inchangée, le réassort du vendredi compte dans la variation sur 24 heures. Le dépôt privé et les offres des joueurs sont séparés du stock public.

Le devis intègre la courbe sur tout le lot. Les réservations du même côté réduisent les conditions encore disponibles ; une livraison promise et rappelable ne peut améliorer un prix avant son arrivée. Le troc déduit 2 % de la valeur cédée. L’achat en crédits coûte la valeur du lot majorée de 2 %, arrondie au centime supérieur. La vente rapporte la valeur du lot diminuée de 2 %, arrondie au centime inférieur. Les transactions en crédits utilisent des centimes entiers.

Dans **Composition**, choisissez la colonie de départ et indiquez le nombre de cargos de chaque rang. Le bouton **Max** tient compte du stock local et de la place restante dans la limite de flotte. Dans **Mission**, choisissez le troc, la vente, l’achat, le dépôt ou le rapatriement, puis renseignez les ressources et la quantité. **Destination** indique la station A:0:0 et la colonie de retour. Le récapitulatif actualise la composition, le lot, les capacités des deux trajets et le carburant. La confirmation présente le devis complet avant le départ. Sans JavaScript, les trois étapes du formulaire sont affichées ensemble.

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

Lors d’un troc à A:0:0, le reçu d’échange débite la station et y crédite le fret initial.
Le joueur ne touche pas encore au lot reçu. Au retour réel, les cargos
rentrent et le fret est déchargé jusqu’à la capacité de la colonie ; le
surplus arrive dans une **Boîte de surplus de la Bourse**, dans l’inventaire
du joueur de cet univers. Un entrepôt plein envoie le lot entier dans la boîte.
Si l’inventaire est plein, la boîte rejoint les récompenses en attente.
Le joueur dispose de 24 heures dès la réception pour ouvrir la boîte sur une colonie ayant assez de place. Le délai court aussi en attente et pendant les vacances. Cette règle s’applique aussi au fret rendu après un rappel. Avant l’échéance de l’aller, un rappel annule la
réservation et rend le fret initial après son trajet de retour. Si la
colonie de retour est perdue, le convoi attend une nouvelle colonie du même
empire puis reprend un trajet de 30 min. Il ne crédite jamais le conquérant.
Une réponse tardive du relais ne donne pas les ressources plus tôt ; la
deuxième jambe commence après l’échange constaté.

## Dépôt privé et marché des joueurs

Depuis le comptoir, choisir **Déposer à la station** pour y acheminer des ressources. Le dépôt privé est crédité à l’arrivée. Pour **Rapatrier du dépôt**, les marchandises sont réservées au départ des cargos vides et livrées sur la colonie au retour. Un rappel rend les marchandises réservées au dépôt. Lors d’un achat à la station, les crédits sont débités au départ et restitués en cas de rappel. Une vente à la station crédite le vendeur à l’arrivée du fret. Les marchandises publiques vendues contre crédits comptent pour le réassort hebdomadaire.

L’onglet **Marché des joueurs** filtre les offres de l’univers par matériau, puis les présente du prix le plus bas au plus élevé. Il affiche le prix conseillé (indice du comptoir), le dernier prix payé entre joueurs et le dépôt personnel. Le prix se saisit au centime. Une offre porte sur 1 à 1 000 000 unités déjà déposées. Avant de confirmer, le vendeur voit le produit potentiel de la vente et la taxe immédiatement exigible. Pour 100 unités à 3,50 crédits, il doit avancer 7 crédits. Un achat de 30 unités lui rapporte ensuite exactement 105 crédits. Retirer le reste rend 70 unités à son dépôt. Les 7 crédits sont définitivement retirés de la circulation.

Les échanges entre joueurs déplacent des crédits déjà émis. La station crée des crédits lorsqu’elle rachète un lot et retire de la circulation les crédits utilisés pour acheter ses ressources. Les taxes de publication constituent une destruction supplémentaire. Un nouveau compte démarre à zéro et peut financer sa première annonce en vendant au comptoir. Le classement « Crédits » suit le solde disponible aux relevés de minuit et midi, heure de Paris.

## Convoi de marchands

Vendredi à 9 h en Europe/Paris, le relais recalcule pour chaque univers
ouvert et chaque matériau :

> apport = min(⌊unités réellement vendues par la station aux joueurs / 2⌋,
> max(0, cible − stock physique))

La semaine est fermée à 9 h : la borne de début est incluse, la borne de
fin exclue. Le stock physique comprend les lots réservés qui n’ont pas
encore quitté la station. Un devis, un lancement ou un rappel ne compte
pas. Aucune distribution en l’absence de vente, aucun retrait au-dessus
de la cible. Chaque livraison rapproche le stock de sa cible et réduit sa rareté. Le journal PNJ conserve ces apports séparément des transactions des joueurs. Le relais reprend une occurrence par univers à chaque passe
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
déjà réservé. Chaque apport du vendredi augmente le stock physique une seule fois et rapproche son cours du cours de référence.

La migration `Version20261128160000` conserve les convois existants comme trocs avec leurs montants réservés et ajoute les comptes et offres. Les devis antérieurs encore en session sont à renouveler. Pour une base existante, vérifier la sauvegarde et obtenir l’autorisation d’application. Une fois les échanges commencés, un retour à l’ancienne version nécessite de préserver ou solder les engagements commerciaux et les comptes.
