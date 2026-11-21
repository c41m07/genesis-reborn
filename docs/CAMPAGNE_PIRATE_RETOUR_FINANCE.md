# Campagne pirate : retour financé de l’escorte

Seulement la tentative V2 privée du pilote Omega avec preuve de paiement du départ
utilise ces états. Une tentative historique ne produit jamais une preuve de
localisation ou de carburant à partir de son seul indicateur de retour bloqué.

À l’application effective du dernier round, les pertes sont définitives. Le coût
aller et le budget d’orbite ont été réservés pour l’expédition ; seul le tronçon
retour reste disponible, borné par la capacité des coques encore vivantes et leurs
profils figés, sans appoint de fret, de convoi ou de stock.

- Origine valide et réserve suffisante : vrai retour de la durée figée, depuis
  l’heure de traitement effective après verrou. La remise des survivants se fait
  une seule fois, après le terme. Le carburant du tronçon est épuisé à l’arrivée.
- Origine indisponible avant le départ : aucun ordre de retour, preuve du site
  privé effectivement atteint et du solde encore porté.
- Origine perdue après le départ : attente de la vraie échéance ; preuve de
  l’arrivée au point initial figé, réserve à zéro, et aucune remise à autrui.
- Réserve insuffisante à l’issue du combat avec origine valide : arrêt distinct,
  aucun ordre, ni arrivée, ni solde ajouté. Le lot de survivants n’est pas
  écrasable par un nouveau départ. Cette tranche n’ouvre aucune action pour ce cas.

La preuve est privée, versionnée, scopiée par compte, participation, univers,
tentative et révision de porteurs. L’écriture et l’ordre suivent le contrôle
du jeton et les verrous. La clôture du retour et la remise au hangar sont
atomiques ; l’affichage ne projette ni les preuves, ni leurs coordonnées et
montants. Un retour bloqué n’est jamais affiché comme remis. Le résultat du
combat et le convoi conservent leurs propres états, indépendants de ce retour.

Aucun formulaire, autorisation d’accès, trajet de repli manuel, migration ou
exposition du pilote n’est ouvert par cette tranche. La future commande de repli
devra relire une preuve courante du worker et des profils de mouvement
authentifiés sous verrou, sans reconstituer des données historiques.
