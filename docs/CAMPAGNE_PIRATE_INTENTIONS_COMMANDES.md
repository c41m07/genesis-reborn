# Pilote Omega : contrat pur des formulaires

Aucune route, action, mutation ou configuration d’activation n’est ajoutée par ce lot. Le futur contrôleur choisit l’action d’après sa route, jamais d’un champ de requête. Il extrait seulement le jeton CSRF Symfony du formulaire effectivement soumis, sans passer ce jeton au parseur des choix. Un formulaire intact est représenté par null ; un vrai POST sans choix par un tableau vide. Ne pas instancier ou valider les autres formulaires simplement affichés dans le dossier.

- Reconnaissance Balise 17 : aucun choix POST. L’origine est la colonie active que le serveur a déjà validée. Le poste est dans son système déjà découvert, attesté par les données existantes ; aucune autre découverte exigée, aucun système arbitraire ou nouveau quand la preuve manque. Ne pas remplacer par une cible en cas d’erreur. La règle du budget AR perdu à la perte définitive de la sonde ne change pas.
- Attaque : origine active côté serveur ; l’utilisateur peut changer de colonie par la navigation existante avant d’ouvrir un devis. Le seul champ de choix est escort, avec ordinaux de coques/rangs actuellement autorisés dans ce hangar et quantités entières canoniques. Zéro ignore un choix connu, pas un inconnu. Vérifier chaque disponibilité puis la taille totale issue du devis natif ; valeurs négatives, ambiguës, imbriquées, inconnues ou dépassées refusées.
- Réessai de dépôt : aucun choix, toujours le droit privé et le même dépôt résolus du dossier historique. Choix de destination du convoi et repli des survivants : seul un ordinal de destination est soumis, jamais un ID de planète ou un système. Aucune donnée de coût, phase, tentative, ETA, version, propriétaire, univers, clé catalogue/mission ou jeton de worker n’est acceptée dans le payload. Les plafonds techniques bornent le parseur, sans remplacer les limites natives.

Le serveur garde l’instantané des options : escortes triées par coque/rang avec disponibilités et limite, colonies triées par leurs ID internes uniques. L’interface énumère exactement cet ordre sans afficher ces identifiants dans les valeurs de formulaire. L’ID *interne* du jeton Symfony lie compte, participation, origine active, numéro canonique, action, version/tentative visible et empreinte canonique des options. La version du devis est une empreinte de faits serveur actualisés (coût, état, règle et disponibilité selon l’action), ni un secret ni une valeur renvoyée par le client. Toute nouvelle option qui déplacerait un ordinal, perte de propriété, changement d’origine ou devis révisé invalide l’intention : demander de rouvrir le dossier, ne jamais appliquer l’ancien ordinal au nouveau modèle.

À raccorder seulement après les contrôles de provenance, côté serveur. Le même contexte et la même version doivent être relus sous les verrous natifs de la commande, à côté des permissions, du rapport/droit et du stock ; un jeton valide avant le verrou ne suffit pas. N’émettre ces jetons que pour un formulaire prêt et réellement affiché. Le CSRF Symfony normal n’est pas un reçu de mutation : réutiliser l’unicité du scout, la tentative attendue de l’escorte et la transition persistée/reçu durable du dépôt ; si une nouvelle commande a besoin d’un reçu, reprendre la primitive ActionGuard existante dans la même transaction, sans jeton aléatoire d’idempotence concurrent. Lever un refus neutre, sans loguer les jetons ni l’identité interne. Les tests purs ne prétendent pas couvrir la concurrence ou l’intégration HTTP future.

### Intention finale d’attaque

L’aperçu d’attaque résout les ordinaux dans la liste serveur d’escortes,
trie les familles et rangs, puis prépare la sélection exacte. Le futur
adaptateur ne transmet pas de slug ou de réserve choisie côté client.
La confirmation finale doit sceller cette composition dans le même
identifiant CSRF que le devis natif (durée, H₂ total, orbite, réserve
embarquée et complément du stock), l’origine et la tentative précédente.
Une seconde composition de coût et de durée identiques ne réutilise pas
le même jeton. Deux ordres de champs représentant la même sélection ne
doivent en revanche pas faire expirer un aperçu valide.

Le serveur devra resceller la preuve propriétaire, la position, le
rapport, l’état de progression et les profils natifs depuis les données
sous verrou avant paiement ; une empreinte envoyée par le client n’est
jamais cette preuve. L’empreinte ne dépend pas de l’heure d’affichage.
Aucune action, route, formulaire ou écriture n’est activé par le modèle
de sélection.
