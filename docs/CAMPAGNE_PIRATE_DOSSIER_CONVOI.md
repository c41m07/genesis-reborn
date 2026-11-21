# Dossier pilote : lire un droit de convoi sans le consommer

Cette projection est raccordée au seul GET du pilote local, désactivé par défaut. Elle n’est liée à aucune action. Elle ne planifie, ne relance ni ne crédite rien. Elle ne modifie jamais les preuves qui déterminent la progression. Sans reçu durable d’un droit réellement attribué, le dossier ne montre aucun paiement : aucun état ne se déduit d’une victoire ancienne ou d’un simple devis.

Le futur lecteur part du dossier auteur déjà résolu (participation sélectionnée, compte vérifié, univers ouvert). Il passe les clés serveur déjà consultables et gagnées à la requête bornée. Celle-ci vérifie ensemble compte, participation et univers dans la mission, le système et le droit durable, sans charger de planète. La projection de la mission sélectionnée reste distincte du repère « plus ancien convoi en attente », trié sur l’instant de victoire enregistré, puis sur le numéro en cas d’égalité. Un nouveau signal peut donc coexister avec le rappel d’un ancien droit, sans lui emprunter son montant ou sa preuve.

| Preuve lue | État à présenter |
| --- | --- |
| Droit durable sans destination | Destination non attribuée |
| Trajet protégé en cours | Arrivée prévue, non confirmée même quand l’heure est passée |
| Arrivée réellement confirmée sans reçu de crédit | Arrivé, versement non confirmé |
| Dernier dépôt attesté, sans place pour le lot entier | Lot entier en attente, stockage constaté plein lors de ce contrôle |
| Perte de destination attestée | Destination perdue ; aucun nouveau trajet supposé |
| Reçu de dépôt entier et chronologie concordants | Lot entier crédité, date réelle du reçu |

Les quantités sont les montants exacts attribués dans le droit, pas une réestimation du catalogue. La partie entière est rendue sans passer par un double et les demi-unités restent explicites. N’exposer ni identifiant, ni coordonnée, ni nom de colonie (même si le propriétaire a changé), ni jeton, numéro de génération ou calcul interne. Aucun CTA. Dans le futur assemblage conserver la validation du dossier puis appeler la projection par les mêmes identité et instant ; si un reçu change pendant la lecture, rendre seulement l’état durable effectivement validé ou aucune preuve. Ce raccordement local ne change ni la portée du pilote, ni les autres écrans.

Vérifications de cette tranche : compilation de la requête Doctrine sur la plateforme MariaDB, sans établir de connexion, puis fixtures de projection en PHP natif (portée, sélection et historique, demi-unités, échéance passée sans crédit, reçu incomplet et états terminaux). Ni SQL exécuté ni navigateur.

## Recette HTTP locale

Avec un seul projet MariaDB/RabbitMQ jetable, le compte synthétique a obtenu une vraie session via le formulaire Symfony, son jeton CSRF de connexion et une requête de même origine. Les échanges HTTP ont eu lieu par la boucle du conteneur Apache, sans publication de port, worker, notification ou SMTP. Hors opt-in le GET propriétaire répond 404 private, no-store. Sous opt-in de la seule recette : la mission 2 ouverte garde un lien vers l’ancien droit de la mission 1 ; montants 41,5 et 0,5 exacts ; échéance dépassée sans crédit ; stockage plein puis reçu entier synthétique durable lisibles dans le même dossier. L’état « crédité » est lu, pas payé dans cette recette. Deux GET ne modifient ni le droit, ni le nombre de lignes outbox, ni le dernier accès en base. Un autre compte n’accède pas aux états privés via identifiants forgés ; un numéro futur et la sélection devenue hors Omega sous le même cookie répondent 404 privé. La page n’affiche aucun nom de destination ni formulaire. Les fixtures et leurs réseaux/volumes ont été supprimés.
