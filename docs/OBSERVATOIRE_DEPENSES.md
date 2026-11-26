# Relevé des dépenses de catalogue

Complexité : **4/5**. Critère de fin : une commande payée, puis terminée ou
annulée, contribue une seule fois au total par joueur de son univers et sa
catégorie. L’administration présente le prix réellement débité, les
remboursements réellement crédités et leur différence.

## Première tranche

Le journal de catalogue conserve bâtiments, recherches, vaisseaux et défenses
séparément, même quand les deux dernières catégories partagent la même file.
Le débit est relevé après les remises, dans la transaction de la commande.
L’annulation relève le crédit exact de stock, qui peut être inférieur au
remboursement théorique si l’entrepôt est plein. Les commandes historiques sans
reçu n’engendrent pas de montant estimé. Une file perdue à la capture reste une
dépense sans remboursement. Les dates sont conservées en UTC.

Le suivi du carburant nécessite sa propre tranche : la réserve de navigation
n’est pas la consommation. Un crédit de fret ou un changement de colonie ne
doit pas apparaître en dépense. Les récompenses, dons et transferts ne sont pas
des achats. L’électricité reste distincte de la valeur matérielle pondérée.

## Affichage prévu, à valider

Observatoire, onglet Économie : total payé, remboursé et net par catégorie
et ressource, période et joueur de l’univers courant, visibles uniquement en
administration. Montrer la date de début de collecte et distinguer absence
d’historique et dépense nulle. La date de remboursement peut être hors de la
période d’achat ; le net est un flux de la période, pas un solde personnel.
Ne pas exposer de données personnelles hors du rôle d’administration.

## Lecture et période

Les bornes UTC sont inclusives au début et exclusives à la fin. Deux lectures
agrégées utilisent les index date/participation : l’une à la date du débit,
l’autre à la date du crédit. Elles partagent le filtre univers et participation
et ne chargent pas les journaux en mémoire. L’équivalent en hydrogène est
calculé avec les poids du catalogue, hors électricité.

Le repère `expense_collection` conserve la date de disponibilité du journal,
indépendamment du premier achat. Appliquer la migration avec le code qui
enregistre les dépenses dans la même fenêtre de bascule ; un intervalle
d’exploitation avec l’ancien code n’est pas couvert. La disponibilité du
carburant aura une date distincte.

## Carburant et écran

Le journal de carburant enregistre seulement l’hydrogène déduit du fret/réserve
à l’arrivée d’un trajet, au rappel ou à la résolution de l’orbite. Il reste
dans la transaction de mission et distingue chaque trajet par le jeton de
traitement avant reprogrammation et la phase métier. Un différé redélivré
déjà traité ne débite pas une deuxième fois. La prise de réserve, l’hydrogène
transporté puis livré ou récupéré, et le carburant inutilisé effacé avec une
sonde consommée n’entrent pas dans la consommation. Les débits fractionnaires
sont conservés jusqu’à leur affichage.

Dans Observatoire → Économie, sélectionner l’univers et la période, puis un
commandant. La recherche accepte un préfixe de pseudonyme et limite la liste ;
l’identifiant du commandant est aussi contrôlé dans l’univers courant. Les
totaux pondérés et le graphique utilisent la maquette B ; les montants bruts
restent accessibles pour chaque ressource et poste. La couverture est
indiquée séparément pour le catalogue et le carburant. Un zéro collecté
n’est pas une estimation des opérations passées. L’écran auxiliaire est en
lecture seule et ne refacture aucune commande.
