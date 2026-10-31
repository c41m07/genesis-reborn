# Navigation multi-colonies, files et vols

La carte Commandant du menu a été remplacée par la liste des colonies du joueur dans l’univers courant. Le choix est conservé pendant la navigation, y compris en revenant à Vue générale. Le menu comporte trois groupes : Colonie (Bâtiments, Chantier spatial, Défenses), Empire (Vue générale, Recherche, Carte stellaire, Flottes, Diplomatie), Commandement (Univers, Profil). Les liens ouvrent directement leur catégorie : les anciens onglets « Gestion de la colonie » et le bloc inventaire ont été supprimés. Les quantités restent visibles sur les cartes des vaisseaux et défenses. Changer de colonie conserve la section ouverte.

La scène regroupe trois files : bâtiment, recherche, vaisseaux/défenses. Chaque ligne affiche la commande active, sa progression et son temps restant, ainsi que la durée restante pour terminer toutes les commandes de la file. `+x` compte les commandes supplémentaires, pas le nombre d’unités du lot. « Gérer la file » expose les commandes et leur annulation ; les dépendants sont toujours supprimés sans remboursement, conformément à la décision documentée.

Le panneau Flottes en vol, situé au-dessus de la scène, montre les flottes de toutes les colonies du joueur dans l’univers actif. Les phases aller et retour sont distinguées, avec route, progression et temps avant la prochaine arrivée. Le même suivi est disponible sur la page Flottes ; les missions terminées restent dans le journal.

Les dates et opérations restent calculées par Symfony. Les barres visuelles et les comptes à rebours sont actualisés par Stimulus ; à l’échéance, l’état serveur est relu. Les traitements métier existants sont conservés.

## Vérifications

- PHPUnit : **47 tests / 168 assertions réussis**, dont deux nouveaux scénarios HTTP pour sélection/propriété, file complète et confidentialité des vols issus de plusieurs colonies.
- Navigateur : navigation responsive et notification réelle relancées ; scénario de file avec deux niveaux, `+1`, progression et annulation en cascade réussi.
- Le parcours complet de missions vérifie deux colonies obtenues par colonisation, changement de colonie en conservant Recherche, retour à Vue générale, et vol réel visible sur cette vue avec une barre qui avance.
- Compilation Encore, lint PHP/YAML/Twig/conteneur et schéma Doctrine réussis. Aucune migration nécessaire.

Captures : [Vue générale](validation/colonie-apercu.png), [mobile](validation/mobile-apercu.png), [file active](validation/files-integrees.png), [plusieurs colonies et vol actif](validation/colonie-multicolonies-vol.png).

## Ressources toujours visibles

Le bandeau de ressources reste fixé sous la barre de contexte pendant le défilement, sur ordinateur et mobile. Sur petit écran, les cinq ressources restent accessibles par défilement horizontal du bandeau, dont la position est préservée lors des actualisations automatiques. Les catégories utilisent des liens Symfony natifs et restent accessibles sans JavaScript.

Vérification navigateur ajoutée : défilement réel d’une page longue, bandeau à 68 px du haut sur ordinateur et 60 px sur mobile, absence des anciens onglets et du bloc inventaire, affichage d’une seule catégorie à la fois. La vue générale conserve uniquement le suivi des vols et la scène avec ses files.
