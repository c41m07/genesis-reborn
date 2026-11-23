# Maquettes validées : reprise et recette

Le dossier de référence est `GENESIS_REBORN_MAQUETTES_AUDIT.rar`. Les sept originaux 01 font foi pour les proportions et l’ordre ; les groupes 02 à 07 précisent les sous-vues et les états. Ils ne fournissent ni chiffres de jeu, ni permissions, ni nouvelles routes. Le point de départ du code est `dev cca8c6b`, après la livraison de l’Avent, resté désactivé par défaut.

## Contrat de comparaison

- Les captures FHD utilisent un viewport CSS **1920 × 1080**, échelle CSS, sans capture de page entière. Servir l’application compilée, avec compte, univers et états synthétiques comparables ; ne pas comparer une page vide à une page remplie. Les noms, coordonnées, heures et chiffres fictifs de référence ne sont pas le critère.
- Évaluer la disposition, l’ordre/hauteur des panneaux, l’action principale et les états sur le vrai front. Rejouer à **360 et 390 px**, en sombre et en clair ; ajouter tablette et zoom si le risque est démontré. Les comparaisons ne constituent pas un audit RGAA exhaustif.
- Garder les repli natifs, focus, titres et sorties d’erreur, le défilement local aux tableaux, la sélection sans zoom de la galaxie et les contrôles du parcours du jeu. Aucun jeu sur une base habituelle, aucun courrier ni déploiement.
- Ne pas versionner les exports et captures de recette. Pour une réexécution courante, utiliser la recette navigateur isolée du dépôt et les scénarios concernés, puis les vérifications complètes de la PR sur le hash final.

## Références de vérification

| Références | Cas à comparer sur l’application |
|---|---|
| 01 Vue générale, 07 États | Bandeau Planètes / Points / Classement / Guilde, détail au clavier, échéance relative avec date, stockage et scope empire, flottes entrantes/en vol en colonnes, journal et liens stables ; annulation d’une file, abandon bloqué ou vérifiable. |
| 01 et 06 Flottes | Composition et quantités par rang, récap et seule action d’étape, destination et devis natifs ; aperçu entrant autorisé, missions/rappel, orbites et stationnements sur leurs routes ; modèle personnel sans émission de flotte. |
| 01 et 06 Galaxie | Sélection de système sans zoom, retour carte/liste, données connues seules, mini-carte et journal hors de la surface interactive, inspecteur au-dessus sur mobile. |
| 01 Catalogues et 07 | Bâtiments, Recherche, Chantier et Défenses, file commune, coût total distinct, gain effectif et prérequis ; Max n’achète pas, états vide/rang maximal/erreur distincts. |
| 02 Joueur, 03 Social, 04 Public, 05 Admin | Les guides des lots identifient les pages et les états réellement rejoués, les vérifications statiques et les limites d’accès. |
| 08 Avent | Pas de nouvelle intégration de l’annexe dans ce lot. Vérifier le lien Événements, les portes et la réserve du code arrivé sur dev depuis la maquette, ainsi que la préservation des écrans d’édition et des cosmétiques. |

## Idées de maquette conservées autrement ou différées

| Intention utile de la planche | Application retenue et raison | Piste si besoin confirmé |
|---|---|---|
| Détail des points et compteur de flotte dans le bandeau du poste de commandement | Les quatre repères principaux restent sur une ligne au large ; ventilation des points et évolution sous « Détail des points ». Le compteur reste dans le titre de « Flottes en vol », avec la même actualisation, plutôt que dans deux panneaux. | Mesurer l’usage du détail avant d’ajouter un accès de plus. |
| « Mise à jour » datée dans la bibliothèque de compositions | Aucun horodatage ne fait partie du modèle actuel. Nom, total réel et détails par rang restent disponibles ; pas de date déduite de l’identifiant ou de la révision. | Ajouter une date avec une vraie politique pour les modèles anciens. |
| Boutons de sélection de mission dessinés comme des cartes et second devis imprimé à gauche | Le sélecteur natif garde tous les types et leurs validations. Le devis réel apparaît dans le rail qu’il alimente ; le bouton d’envoi original est déplacé seulement avec JavaScript et demeure à sa place native sans JavaScript. | Tester un groupe radio accessible si la recherche confirme que le sélecteur est difficile à parcourir. |
| Tous les états de missions, d’orbite, de rappel et de composition visibles en même temps | Les planches montrent des alternatives. Chaque rappel, composition et déclaration de quai garde sa route, ses restrictions et sa confirmation ; les arrivées autorisées ont leur rail sur la liste. | Regrouper des raccourcis uniquement si cela ne duplique ni actions ni fret privé. |
| Tous les systèmes et les détails de toutes leurs planètes affichés en cartes | La liste présente les systèmes d’un coup d’œil et sélectionne une fiche ; les actions réellement possibles restent dans cette fiche, avec le repli natif. Une planète non révélée ne reçoit aucune information de plus. | Permettre d’épingler un système ou comparer deux systèmes dans une demande distincte. |
| Annotations de démonstration, nombres de progression et échantillons d’alertes | Ces éléments ont servi à comparer les maquettes, pas à définir un état réel. La page utilise les textes, prix, statuts et échéances que le jeu connaît. | Ne pas introduire de valeurs ou d’alertes fictives en dehors d’un mode de démonstration explicitement dédié. |
