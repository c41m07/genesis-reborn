# Couverture UI/UX des espaces joueur

Source : sections 2 à 4 de l’audit UI/UX détaillé de Genesis Reborn. Le socle 6da2983 comprend déjà les écrans principaux harmonisés. Ce lot complète les vues privées. Il ne modifie ni les droits ni les routes, règles, valeurs, CSRF ou décisions métier. Tests fonctionnels sur fixtures uniquement ; pas de compte ni de colonie réels.

**Retouché** : changement ciblé dans ce lot. **Conservé** : comportement relu dans le code et conservé, généralement après le chantier principal. Cette matrice documente une couverture du code, pas une visite authentifiée exhaustive ni un nouveau relevé de performances.

## Empire, développement, flottes

| Écran | Statut | Couverture |
| --- | --- | --- |
| Choix d’univers | Retouché | Statut et date absolue réunis ; zone de commande ou de compte à rebours au pied de chaque carte. |
| Vue générale | Conservé | Poste de commandement validé, colonie et prochaines activités ; aucun rappel Premiers pas ajouté. |
| Premiers pas | Retouché | Prochain objectif et accès métier avant les retraits ; remboursement avec montant et colonie, retrait et autres étapes visuellement secondaires. |
| Exercice et rapport d’exercice | Retouché | Narration conservée ; survivants et pertes côte à côte pour Escorte et Pirates ; détail des manches maintenu. |
| Récompense quotidienne | Retouché | Métal et nanos sur lignes distinctes, destination et dépôt entier associés au bouton natif. |
| Cartes de planètes | Conservé | Renommage dépliable, colonne d’action cohérente sur desktop, commandes disponibles selon le même contexte. |
| Comparaison de planètes | Retouché | Une seule barre de contrôles en largeur suffisante, quantités alignées à droite, parcours natif et choix de ressource mobile. |
| Abandon de colonie | Conservé | Bilan et confirmation dédiés, colonne de confirmation et rappel du nom ; aucune action réelle. |
| Bâtiments et Recherches | Conservés | Alignements et hiérarchie du socle : gain, prérequis éventuel, coûts et action ; modes et catégories intactes. |
| Chantier et Défenses | Conservés | Quantité, Max, coût du lot et temps associés ; aucun recalcul local de coût. |
| Guide technologique | Retouché | Même repère doré pour le nœud sélectionné et le titre du panneau ; statuts textuels, onglets, défilement et liens natifs inchangés. |
| Annulation de file | Retouché | Nombre de commandes et bilan par ressource avant le détail dépliable. Les montants proviennent des lignes existantes, sans recalcul métier. |
| Carte classique | Conservé | Colonne de commandes et défilement horizontal sur petit écran ; recherche et missions natives inchangées. |
| Galaxie interactive | Conservé | Sélection et entrée distinctes, même système côté carte et inspecteur, repli classique existant. |
| Simulateur | Retouché | Issue puis engagés, survivants et pertes pour le même camp ; détail par rang conservé. |
| Missions de flotte / Préparation | Conservées | En vol et entrantes distinctes. Colonnes d’identité/route visibles, ETA native et rappel distinct de la composition ; devis mis en avant dans sa propre zone. Pas de troisième colonne obligatoire pour toutes les tailles. |
| Rappel / Reprise | Retouchés | Conséquence du rappel associée au bouton ; destination et devis affichés près de la confirmation de reprise. |
| Orbites / Quai | Retouchés | Échéances explicitement étiquetées et chiffres stables ; unités de la colonie distinguées des réserves et stationnements de l’empire. |
| Compositions, liste | Retouché | Charger reste en premier ; éditer et supprimer regroupés comme gestion du modèle. |
| Compositions, création, édition et chargement | Conservés | Ordre Basique → Intermédiaire → Supérieur, même tableau de saisie, résultats Demandé / Chargeable / Manquant. |

## Rapports, récompenses et vie sociale

| Écran | Statut | Couverture |
| --- | --- | --- |
| Centre de rapports et archives | Conservés | Portée, filtres et sélection distincts dans le socle. |
| Rapports de combat et de transport | Conservés | Bilan par camp et issue explicite ; statut de livraison associé au trajet et quantités rattachées au fret. |
| Rapport d’espionnage | Retouché | Catégories non révélées étiquetées comme inconnues ; un zéro reste une valeur numérique. |
| Boosts / Boîtes de ressources | Retouché / Conservé | Effet et disponibilité du boost près de l’action ; condition d’ouverture en regard de la destination et de l’action des boîtes dans le socle. |
| Récompenses en attente | Retouché | Rang FIFO conservé par page, avec retrait ou attente indiqué en texte, sans promouvoir un cadeau hors de son ordre. |
| Événements / Liste et fiche des défis | Conservés | Effet et période alignés, groupes des défis distincts et participation isolée du descriptif ; aucun état simulé dans l’interface. |
| Campagne pirate | Retouché | Gain potentiel, en convoi, à déposer, livré ou empêché distingués. Aucune promesse de crédit avant la preuve existante. |
| Classements joueurs et guildes | Retouché | Rang, points et variations alignés sans changer le classement ni les filtres. |
| Profil de commandant / Signalement | Conservé / Retouché | Profil et contact prioritaires ; cible répétée près du motif de signalement et envoi explicite. |
| Messagerie privée, globale et de guilde | Retouché | Audience visible à l’endroit de la rédaction ; label de champ et formulaires conservés. |
| Recherche de messages | Retouché | Intitulé de conversation, extrait puis date (lecture stable). |
| Découverte, profil, création, Ma guilde | Conservés | Entrées et champs natifs, bannière de la fiche et accès direct au canal du socle. |
| Membres, grades, gestion et personnalisation | Conservés | Colonnes et actions, grade actif nommé, conséquences des décisions sensibles et visuels associés à leurs champs. |
| Traités / Relations entre commandants | Retouchés | Relation actuelle et proposition séparées ; commandant en tête de ligne et portée personnelle maintenue. |
| Projets disponibles / Fiche projet | Conservés | Conditions, récompense, progression et don près de l’action ; aucun chiffre de stock ajouté quand la page ne le fournit pas. |
| Projets en cours / Historique | Retouchés | Financement collectif et contribution personnelle nommés ; état clos et date de fin si connue, sinon date de lancement explicitement nommée. |

## Compte, information et aide

| Écran | Statut | Couverture |
| --- | --- | --- |
| Identifiants / Vacances | Conservés | Confirmation finale et changement de mot de passe repliable ; univers et état de vacances avant la transition. |
| Préférences | Retouché | Portées rappelées aux groupes pertinents et enregistrement unique intact. |
| Suppression du compte | Retouché | Portée tous univers répétée au bloc de confirmation ; aucune opération réalisée. |
| Feedback | Conservé | Description au large et qualification compacte ; restitution native des erreurs. |
| Patchnotes | Retouché | Repères version/date préservés et date exposée via une balise temporelle native. |
| Wiki : accueil, chapitre, recherche, références | Retouché / Conservé | Recherche avant navigation secondaire lorsque le formulaire est présent ; largeur de lecture bornée. Titres des chapitres et des sources, extraits et requête conservés. |

Les états rares cités dans l’audit (campagne achevée et convoi perdu, changement réel de contrôle, flux de guilde complet ou ancien privilège, compte fermé) sont vérifiés au niveau des branches de template quand présentes, pas rejoués sur un compte réel. Les écarts de rendu, contrastes, ordre clavier et hauteurs de composition nécessitent une recette sur fixtures ; ils ne sont pas déduits du lint.
