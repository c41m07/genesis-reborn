# Calendrier de l’Avent

## Joueur

Événements donne accès à la page dédiée « Calendrier de l’Avent » quand une édition est annoncée ou qu’un lot reste réservé sur le compte. L’édition est commune à tous les univers accessibles. Le compte obtient au plus un lot par porte et par édition, quel que soit l’univers utilisé.

Une porte s’ouvre chaque jour civil à minuit à Paris, 24 jours consécutifs. Par défaut, la première s’ouvre le 1er décembre, la vingt-quatrième le 24 et le 31 décembre est le dernier jour de rattrapage inclus. On peut récupérer n’importe quelle porte déjà ouverte sans avoir joué les jours précédents. Les dates d’une édition particulière sont affichées sur sa fiche.

Avant de confirmer, le joueur choisit un seul lot parmi les choix de la porte et une colonie qu’il contrôle dans son univers actif. Le dépôt est intégral ou nul. Si le stockage ne suffit pas, la porte est ouverte, le lot choisi reste réservé et rien n’est crédité. Il est possible de retirer ce même lot plus tard, même après la clôture, dans la colonie initiale ou une autre colonie éligible contrôlée sur le même compte. La destination doit être confirmée de nouveau. Il n’y a ni transfert automatique, ni crédit supplémentaire si le cadeau a déjà été versé. Les portes jamais ouvertes expirent à la clôture.

## Administration et pilote local

- Aucun calendrier ni barème n’est créé à l’installation. La fonctionnalité est désactivée par défaut (`ADVENT_ENABLED=0`). Elle ne requiert ni worker, ni e-mail ni publication externe.
- Après migration sur une base de recette jetable, définir `ADVENT_ENABLED=1` dans le fichier local de la recette. Ouvrir **Administration → Événements → Préparer le calendrier de l’Avent** avec le rôle administrateur existant.
- Saisir la première date et le dernier jour inclus, en heure de Paris, et un à trois lots par porte. Les dates doivent permettre les 24 ouvertures. Le formulaire ne fournit aucun montant : utiliser des valeurs synthétiques en recette ou l’équilibrage explicitement validé pour la version.
- L’aperçu sauvegardé dans la session ne publie rien. La confirmation enregistre une édition annuelle, son calendrier et ses lots figés, et rédige une ligne d’audit. Une édition déjà publiée n’est pas réécrite par le formulaire. Le MVP ne gère ni jetons saisonniers, ni cosmétiques, ni ciblage d’une partie des univers.
- Ne pas appliquer la migration ou le pilote à une base existante sans le circuit habituel d’autorisation et de sauvegarde. Le retour du code doit préserver les droits déjà ouverts.

## Persistance

Les instants d’ouverture, la clôture exclusive et les actions sont stockés en UTC. Les jours civils sont calculés en heure de Paris uniquement pour ce mode. Les défis, rotatifs et récompenses quotidiennes conservent leurs règles propres.

Une contrainte unique protège le triplet compte/édition/porte. Le reçu conserve uniquement le choix, la quantité, les instants et les identifiants de destination initiale et finale. Le compte, la participation active et la colonie sont revalidés sous les verrous du jeu avant de toucher au droit ou aux stocks. Le versement et son reçu partagent la même transaction. Le reçu est effacé lors de l’effacement du compte. La fiche d’une édition close n’est accessible qu’aux comptes ayant un reçu pour cette édition ; le lien dans Événements disparaît lorsque le dernier lot réservé est versé.
