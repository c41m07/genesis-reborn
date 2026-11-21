# Dossier pilote : lire un droit de convoi sans le consommer

Cette projection séparée n’est pas raccordée au GET ni liée à une action. Elle ne planifie, ne relance ni ne crédite rien. Elle ne modifie jamais les preuves qui déterminent la progression. Sans reçu durable d’un droit réellement attribué, le dossier ne montre aucun paiement : aucun état ne se déduit d’une victoire ancienne ou d’un simple devis.

Le futur lecteur part du dossier auteur déjà résolu (participation sélectionnée, compte vérifié, univers ouvert). Il passe les clés serveur déjà consultables et gagnées à la requête bornée. Celle-ci vérifie ensemble compte, participation et univers dans la mission, le système et le droit durable, sans charger de planète. La projection de la mission sélectionnée reste distincte du repère « plus ancien convoi en attente », trié sur l’instant de victoire enregistré, puis sur le numéro en cas d’égalité. Un nouveau signal peut donc coexister avec le rappel d’un ancien droit, sans lui emprunter son montant ou sa preuve.

| Preuve lue | État à présenter |
| --- | --- |
| Droit durable sans destination | Destination non attribuée |
| Trajet protégé en cours | Arrivée prévue, non confirmée même quand l’heure est passée |
| Arrivée réellement confirmée sans reçu de crédit | Arrivé, versement non confirmé |
| Dernier dépôt attesté, sans place pour le lot entier | Lot entier en attente, stockage constaté plein lors de ce contrôle |
| Perte de destination attestée | Destination perdue ; aucun nouveau trajet supposé |
| Reçu de dépôt entier et chronologie concordants | Lot entier crédité, date réelle du reçu |

Les quantités sont les montants exacts attribués dans le droit, pas une réestimation du catalogue. La partie entière est rendue sans passer par un double et les demi-unités restent explicites. N’exposer ni identifiant, ni coordonnée, ni nom de colonie (même si le propriétaire a changé), ni jeton, numéro de génération ou calcul interne. Aucun CTA. Dans le futur assemblage conserver la validation du dossier puis appeler la projection par les mêmes identité et instant ; si un reçu change pendant la lecture, rendre seulement l’état durable effectivement validé ou aucune preuve. La recette SQL réelle et le rendu Twig doivent précéder tout raccordement.

Vérifications de cette tranche : compilation de la requête Doctrine sur la plateforme MariaDB, sans établir de connexion, puis fixtures de projection en PHP natif (portée, sélection et historique, demi-unités, échéance passée sans crédit, reçu incomplet et états terminaux). Ni SQL exécuté ni navigateur.
