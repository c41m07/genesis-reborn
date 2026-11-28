# Éditer le jeu depuis la console

## Animation → Contenus du jeu

Choisir **Titre de commandant**, **Bannière de profil**, **Bandeau de page** ou **Étape du guide**. Enregistrer le brouillon pour voir son aperçu. **Publier cette version** la rend disponible immédiatement, ou à la date UTC saisie. L’historique donne l’auteur et la portée de chaque version. Ouvrir une ancienne version, la reprendre en brouillon puis la publier pour restaurer un texte ou une illustration.

Les titres et bannières de profil entrent dans le catalogue des comptes. Le texte d’obtention décrit la récompense. L’attribution se fait depuis la fiche du joueur ou un défi. Les bannières acceptent un visuel du jeu ou un PNG, JPEG ou WebP (5 Mo et 8 mégapixels maximum), avec des positions de cadrage ordinateur et mobile. Les images importées sont réencodées en WebP et sauvegardées en base.

Les bandeaux de la Bourse et du marché des joueurs et les textes de chaque étape de Premiers pas sont propres à l’univers choisi. La date effective de publication détermine la version affichée. Le fonctionnement et les conditions de validation du guide restent définis par les actions du jeu.

## Joueurs → Fiche joueur

- **Gérer les titres et bannières** permet d’attribuer ou retirer plusieurs éléments du compte ensemble. Le joueur les équipe dans son profil, avec deux titres et une bannière au maximum.
- **Activité et conversion de l’empire** indique la dernière visite et les jours d’absence hors vacances pour la participation choisie. Le marquage manuel applique les effets habituels de l’inactivité sur les protections, est visible dans le classement, hors vacances, et disparaît à la reprise ou sur retrait administratif. Il ne falsifie pas la dernière visite et ne déclenche pas la conversion automatique de J30.
- La conversion manuelle nécessite un motif et la confirmation explicite de la perte de contrôle. Le panneau présente les colonies, flottes, opérations et liens sociaux avant la décision. Une modification de cet état impose de relire l’aperçu. La conversion doit être activée dans l’environnement. Un combat en cours ou les vacances la bloquent.

## Animation → Événements → Assistant

Pour un défi collectif, choisir une récompense de boost, de boîte ou un lot de titres et bannières publiés. Fixer l’objectif collectif et la contribution individuelle minimale. Le traitement de clôture accorde la récompense aux participants éligibles du même univers. Le profil permet ensuite d’équiper les éléments.

## Univers → Gérer

- **Créer une mission pirate** reprend un modèle de campagne. Modifier le signal, les effectifs par vaisseau et par rang, l’illustration et la conclusion. Chaque vague renseignée devient une rencontre successive, avec sa reconnaissance, son combat et son convoi. Utiliser le barème du catalogue ou fixer les quantités par vague. La préparation vérifie la formation avec le moteur de l’univers. Publier dans un univers de recette permet d’y jouer la mission. Les missions ajoutées suivent la campagne existante et les dates bornent les nouveaux départs. Une reconnaissance engagée conserve ses données de combat et de récompense.
- **Bourse de ressources** regroupe les cibles de stock, le pourcentage de réassort du marchand, la taxe des offres, leur durée et le plafond de dépôt par empire. Simuler les réglages avant d’enregistrer. Le plafond comprend les offres et les transports réservés. Zéro signifie une capacité illimitée. Une diminution réserve les restrictions aux nouveaux apports.

Une offre conserve la date d’expiration annoncée à sa publication. Le vendeur choisit la colonie de retour. À l’échéance, le relais restitue les invendus dans la limite de sa capacité de stockage ; l’excédent est perdu. Si cette colonie a changé de propriétaire ou a disparu, le reliquat revient au dépôt du vendeur. Les offres historiques sans échéance gardent leurs conditions. Une annulation manuelle avant l’échéance rend les invendus au dépôt, comme précédemment.

## Exploitation

La migration `Version20261129100000` ajoute l’historique éditorial, les images, les reçus des récompenses cosmétiques, le marquage manuel et l’échéance des offres. Les publications datées sont résolues à la lecture. Le relais d’événements traite les offres échues par lots. Les images importées font partie des sauvegardes SQL. Appliquer la procédure de [sauvegarde](SAUVEGARDES.md) et obtenir un accord propre à chaque environnement existant avant la migration.
