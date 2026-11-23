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

## Preuves locales et limites

- La recette intégrée se joue sur une instance locale de test seule, des univers et comptes synthétiques, une base jetable, sans courrier. Les migrations existantes sont jouées sans en ajouter. Le build Encore, la validation Twig et le conteneur sont vérifiés.
- Sur la Vue générale, les cartes de tête sont alignées, avec les quatre repères dans l’illustration. Les lignes d’activité et des flottes conservent liens, progressions, détail, rappel et autres contrôles natifs ; leur hauteur réelle et le journal ne sont pas rigides. Le premier écran n’affiche donc pas toujours le Journal de bord, contrairement à la planche où les lignes sont plus courtes.
- Sur Flottes, le tableau et le récap débutent au même niveau en bureau ; les états Destination et Confirmation utilisent le devis réel. Sur mobile, toucher Max ne lance pas de flotte. Les données débordant de la ligne du tableau restent dans la vue, sans masquer les stocks et les rangs.
- Sur Galaxie, les tailles et positions réelles des systèmes viennent toujours du moteur. Les commandes sont dans une bande distincte au-dessus du canevas, pas au-dessus d’un repère, y compris à 360 et 390 px ; la fiche se lit sans changer le zoom, et l’entrée reste distincte. Cette séparation ajoute une rangée par rapport à la composition dessinée. Le tactile, le pincement et le retour ont été vérifiés.
- Les tests ciblés et les captures ne sont pas une validation pixel à pixel, une mesure de latence ni un audit exhaustif du code ou de l’accessibilité. Le lot ne réactive pas l’Avent et n’ajoute aucun accès d’administration. La vérification complète reste celle du candidat GitHub.

### Comparatif représentatif

22 images du Dev de référence `cca8c6b` et 22 du candidat code `867b695` : 3 vues × 3 formats × 2 thèmes, plus Destination et Confirmation en Full HD pour les deux thèmes. Les 18 dispositions de base sont mesurées pour chacun des deux commits, sur Chromium sans défilement horizontal de la page. La fixture crée une colonie active et une seconde, des ressources, un chantier et des mouvements, choisit le même état de sélection cartographique, et prépare dix vaisseaux répartis sur trois rangs sans les envoyer.

| Vue sombre, 1920 × 1080 | Dev de référence | Candidat |
|---|---:|---:|
| Vue générale · haut du bandeau illustré | y 265 px | y 235 px |
| Vue générale · largeur Activité / Flottes | 957 / 617 px | 766 / 814 px |
| Flottes · décalage vertical récap / panneau d’étape | 129 px | 0 px |
| Flottes · hauteur du tableau et de son panneau de préparation | 828 px | 680 px |
| Galaxie · canevas | 1 268 × 560 px | 1 170 × 710 px |
| Galaxie · largeur de la fiche du système | 308 px | 386 px |

Le changement de taille du canevas est entre versions, non au clic. La colonne conserve sa largeur sur la version nouvelle, même avant sélection. Les données obligent à défiler pour des listes longues ; une première vue ne prétend pas afficher tous les rapports, missions ou planètes en même temps.

### Détail par famille

- [Catalogues et annulation](FIDELITE_CATALOGUES.md) : lecture côte à côte du coût et du gain, contrôle du rang et quatre sections en Full HD/mobile.
- [Parcours joueur](MAQUETTES_JOUEUR_RECETTE.md) : matrice P01 à P22, y compris les états non joués de bout en bout.
- [Communauté](RECETTE_MAQUETTES_SOCIAL.md) : guildes, messagerie, diplomatie et feedback ; ni compteurs fictifs ni informations privées ajoutés à l’annuaire.
- [Accès et site public](RECETTE_MAQUETTES_04_PUBLIC.md) : états d’accès séparés, notices inchangées, sommaire juridique et comparaison des trois états d’univers. Sur le candidat final, le sommaire FHD conserve sa propre colonne et le panneau de texte dépasse 1 150 px ; empilement à 390 px.
- [Administration](MISE_EN_OEUVRE_MAQUETTES_ADMIN.md) : table de référence et comparaisons de devis, permissions et diagnostics de l’application inchangés. Les autres tableaux natifs sont conservés sans fusion de leurs données.

### Contrôles de non-régression

- Groupe cœur : premier passage 35 scénarios réussis sur 40. Les cinq cas corrigés font partie du retest resserré qui repasse 11/11 sur `eb79da8`. Les corrections concernent un nom de repère Axe, les contrôles hors canevas, la fiche globe et l’assertion de disposition des flottes entrantes. Pas de modification de la logique ou des seuils de surveillance.
- Catalogues/Public/Communauté : 14/15 sur `eb79da8`, puis les deux cas juridiques exacts 2/2 sur `867b695` après ajustement de la marge du premier sujet et de son sélecteur. Le changement n’altère pas les clauses. La messagerie des trois canaux, l’envoi sans JavaScript et l’aperçu de feedback gardent leurs parcours.
- Avent activé uniquement dans l’environnement de recette : 2/2 sur `eb79da8` (réserve retrouvée après clôture sans JavaScript, puis consultation du palier acquis, choix de l’univers et versement au clavier). Le seul delta produit de `867b695` après ces tests vise les titres d’article des pages juridiques.
- Validation finale du code : Encore, 255 Twig, conteneur, contrôles du mapping et 78 migrations existantes sur base neuve. Les recettes ne remplacent pas tous les groupes de CI.
