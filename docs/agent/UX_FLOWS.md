# Genesis Reborn — parcours UX

Mise à jour : 31/10/2026. Les comportements décrits ici ne sont validés que contre les routes du dépôt et les règles existantes. Le prototype Figma utilise des données fictives et des actions simulées en mémoire.

| ID | Parcours cible | Étapes et contrôles attendus | État |
|---|---|---|---|
| FLOW-01 | Accès et inscription | Inscription → validation/erreur → connexion → sélection univers → vue générale ; récupération du mot de passe à intégrer | PARTIEL : inscription/connexion existent, récupération manque ; écran Figma d’accès non accessible |
| FLOW-02 | Développement planète | Vue générale → planète → bâtiment → coûts/prérequis → confirmation → file → annulation ou fin | App : contrôles navigateur ciblés réussis ; prototype : écran et actions simulées |
| FLOW-03 | Recherche | Vue générale → technologie → prérequis → lancement → progression → rapport/notification | Écran Figma observé ; E2E complet à rejouer |
| FLOW-04 | Colonisation vierge | Carte → planète vierge → préconditions → composition vaisseau colonie → estimation → mission → résultat et nouvelle colonie | Backend audité ; parcours browser complet non validé |
| FLOW-05 | Conquête PNJ JcE | Carte → PNJ et force → espionnage/préparation → mission → combat → victoire/conditions vaisseau colonie → capture en gardant stocks/bâtiments/développement | Règle et moteur phase 09 ; parcours visuel et résultat à compléter |
| FLOW-06 | Espionnage | Carte/cible → sondes → coût/risque → envoi → détection ou rapport privé → informations permises | Backend testé ; parcours UX complet non validé |
| FLOW-07 | Flotte / transport / exploration | Chantier → produire unités → composer → destination → carburant/durée → confirmation → états aller/retour/rapport | Mission E2E intégrale non validée dans cette session |
| FLOW-08 | Orbite et renfort | Flotte → planète alliée → permission → durée/carburant réservé → stationnement → participation ou départ → résultat | Implémentation historique auditée ; scénarios de frontière à rejouer |
| FLOW-09 | Alliance et diplomatie | Commander → demande → accepter → membres/permissions → messages et notifications | Backend protégé ; test navigateur historique, Figma non exécuté jusqu’au bout |
| FLOW-10 | Classement et messages | Classement joueur/alliance → profil/contact → message privé/canal → états envoyé/erreur | Écrans observés ; E2E à confirmer |
| FLOW-11 | Administration | Connexion rôle requis → comptes/modération/univers → modification du catalogue → journal d’audit | Contrôle de rôle présent ; réglages de catalogue manquent, prototype admin non interactif |
| FLOW-12 | Rapports/notifications | Notification reçue → rapport correspondant → autorisation → détails/retour | Abonnement Mercure ciblé validé après alignement du sous-domaine E2E |

Aucune absence de réponse utilisateur ne valide une règle de gameplay indéterminée. Les décisions nécessaires restent listées dans DECISIONS.md et BLOCKERS.md.
