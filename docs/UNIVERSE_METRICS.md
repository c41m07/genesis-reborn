# Mesures par univers

L’administration propose « Observer les mesures de cet univers » dans chaque
carte d’univers. La page `/admin/universes/{id}/mesures` est réservée au rôle admin.
Elle ne modifie ni les règles, ni les ressources, ni les files. Aucune migration
ou donnée de télémétrie supplémentaire n’est introduite.

## Définitions

| Domaine | Valeur calculée | Population et limites |
| --- | --- | --- |
| Progression | Nombre de participations ; ancienneté médiane en secondes depuis `joined_at` | Toutes les participations de cet univers, y compris les comptes suspendus. Les dates futures sont comptées à part et exclues des durées. Ancienneté calendaire, pas temps de jeu actif. |
| Niveaux | Sommes des niveaux actuels de bâtiments des colonies possédées et des recherches des participations | Tous les rangs sont additionnés. Aucun équivalent de puissance, score ou effort réel n’est supposé. Les destructions et pertes de colonies peuvent réduire ces totaux. |
| Attente non démarrée | Médiane et maximum de `début du relevé − queued_at` | Uniquement les commandes actuelles dont `started_at` est nul. Il s’agit de l’attente déjà écoulée, pas d’une estimation du prochain démarrage. |
| Attente avant démarrage | Médiane de `started_at − queued_at` | Seulement les commandes encore actives ; ce n’est pas une statistique de toutes les commandes terminées. |
| Temps restant | Maximum de `max(0, started_at + duration_seconds − début du relevé)` | Commandes actives actuelles. Les files parallèles ne sont pas additionnées. Une échéance atteinte est un signal à examiner, pas une preuve de panne. |
| Stocks | Médiane de `100 × stock / capacité` et nombre de colonies à stock nul | Uniquement les colonies exploitables de la page de ressources. Le stock nul ne démontre pas qu’une commande a été refusée. |
| Production | Nombre de colonies à débit net négatif ; nombre à productivité inférieure à 100 % | Les dénominateurs sont affichés séparément. La productivité n’est définie que pour les ressources produites par des usines présentes ; l’électricité n’a pas cet indicateur. |
| Après les pertes | Participations actuellement protégées et participations avec une dernière activation conservée | La protection peut suivre de fortes pertes dans une victoire. Ce n’est ni le total des défaites ni le total historique des activations. |
| Combats | Nombre de rapports par verdict | Tous les rapports conservés dont la planète de combat appartient à cet univers, indépendamment de son propriétaire actuel. Ce n’est pas un taux de défaite par joueur. |

Les médianes utilisent les valeurs triées ; pour un effectif pair, la moyenne des
deux valeurs centrales est retenue. L’interface arrondit les secondes à l’entier
et les pourcentages à une décimale. Un effectif nul donne une durée « Non
disponible », distincte d’une attente réellement nulle. Les commandes datées dans
le futur, démarrées avant leur ajout ou avec une durée non positive sont exclues
des calculs temporels et comptées séparément.

## Ressources : couverture et lecture seule

La page traite au maximum **25 colonies**, ordonnées par identifiant ; les liens
de pagination permettent de consulter les suivantes. Elle affiche le nombre
observé, le nombre total et les données non exploitables. Ce choix borne le coût
des projections ; il ne constitue pas un échantillonnage aléatoire ou une
statistique globale représentative de l’univers. Ne pas additionner des médianes
de pages pour obtenir une médiane globale.

Une colonie est exclue si une ressource configurée manque, si sa dernière mise à
jour est postérieure au début du relevé ou si sa propriété a changé pendant la
lecture. Les absences ne sont pas transformées en stocks nuls.

Pour les univers ouverts, le service réutilise la
[projection du moteur de ressources](../src/Service/Colony/ResourceService.php),
avec les stocks, capacités, consommations et bonus connus. Il n’appelle pas
`materialize` et n’enregistre pas les stocks calculés. Un débit négatif indique
une consommation nette ; une productivité réduite indique une limitation de
production calculée par le moteur. Aucune demande refusée faute de moyens n’est
enregistrée, donc aucun taux de blocage des commandes n’est calculé.

Pour un univers **fermé**, seuls les stocks conservés et leurs capacités sont
utilisés. Aucune production postérieure à la fermeture n’est simulée. Débits et
productivités sont « Non applicable ». Les dates des files restantes sont encore
consultables, mais leur présence ne signifie pas que des processus devraient
continuer à les exécuter.

L’heure affichée en UTC est le début du relevé. Les lectures ne bloquent pas les
traitements : des opérations peuvent évoluer entre deux requêtes. Il ne s’agit
pas d’un instantané transactionnel de l’ensemble de l’univers. Actualiser pour
obtenir un nouveau relevé.

## Données qui manquent

- Les déblocages conservés sont des booléens sans date. Les commandes terminées
  ou annulées disparaissent des files. Le temps pour atteindre un niveau, une
  première colonie ou une flotte spécialisée n’est pas reconstitué à partir de
  leur état actuel.
- Le temps de jeu actif, les sessions et les commandes refusées pour manque de
  ressources ne sont pas historisés pour ces mesures.
- La durée de reconstitution des forces après une défaite n’est pas calculable
  sans historique des inventaires et des productions. `Fleet.departureAt` est
  réécrit au retour ou dans certains traitements de combat : il ne permet pas
  d’identifier fiablement une nouvelle activité après la défaite.
- La dernière protection ne conserve pas toutes les activations ni la courbe de
  récupération. Les verdicts des rapports ne suffisent pas à établir cette
  courbe.

Une future collecte de jalons et de pertes/reconstitutions devra définir ses
événements, sa conservation et sa couverture avant de servir à une décision.
Ces mesures éclairent l’observation de l’Alpha ; elles ne valident pas les
objectifs de durée décrits dans les [décisions](DECISIONS.md) et les
[règles](REGLES.md), et n’autorisent aucun changement d’équilibrage.
