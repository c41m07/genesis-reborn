# Consultation administrative des guildes

Premier incrément du lot guildes/modération, branche
feature/admin-guildes-consultation depuis dev 8be1fd9 après PR99.
Complexité 3/5 : lectures limitées, confidentialité et isolation des univers.

## Existant et périmètre

L'administration gérait les modèles de projets de guilde, mais pas une
consultation dédiée des guildes en activité et de leurs projets lancés.
Les services de diplomatie et de projets portent des effets métier ; ils ne
sont pas appelés pour afficher ces pages.

Une entrée « Guildes actives » est ajoutée à Joueurs et modération.
La liste filtre par univers et nom/tag. Les fiches possèdent trois URL
distinctes : membres/direction, diplomatie et projets lancés. Une seule
section est chargée à la fois ; chaque liste utilise 25 résultats et une
pagination par identifiant stable. Retour au début et page suivante gardent
les filtres ou le contexte de la fiche.

Les projets montrent le nom conservé dans leur snapshot, l'état enregistré,
les paliers terminés, la date de départ et l'existence d'une livraison de
récompenses enregistrée. Le modèle courant ne remplace pas leur historique.
Les rangs indiquent leur nom seulement ; aucun éditeur de droits n'est ajouté.

## Garanties et limites

- ROLE_ADMIN requis côté serveur sur les deux routes, GET uniquement.
- Chaque fiche est adressée par univers et guilde. Les lectures de membres,
  rangs, pairs diplomatiques et projets revérifient ce rattachement.
- Guildes archivées exclues de cet écran ; pas de restauration ou suppression.
- Projections scalaires limitées : pas de mail, mot de passe, message privé,
  jeton de récompense, liste de bénéficiaires ou contributions individuelles.
- Aucun transfert de direction, changement de membre/rang, traité, sanction,
  récompense, progression ou autre écriture métier.
- Les relations non enregistrées ne sont pas inventées. Cette vue n'est pas
  l'historique détaillé des traités.
- Les paramètres d'univers ne sont pas des permissions granulaires : ce lot
  conserve le rôle admin existant. Leur articulation avec un futur périmètre
  administrateur par univers devra être revue à l'intégration.
- Pas de mesure de présence de la guilde ou de conclusion automatique sur
  l'activité des joueurs. « Actives » signifie non archivées.
- Sources Work externes citées dans AGENTS absentes du worktree.

## Validation

make configure et make validate réussis dans le worktree.
Deux tests de parcours, 186 assertions réussies sur MariaDB jetable :
AdminGuildDirectoryTest et AdminDedicatedPagesTest.
Couverture : accès anonyme/joueur refusé, admin autorisé, pagination 25+1,
recherche et univers, archives exclues/404, membres/relations/projets
incohérents d'un autre univers exclus, confidentialité, nom historique du
projet et tables guildes/membres/relations/projets inchangées après lectures.

Lint des 60 templates admin et injection du conteneur réussis ; diff vérifié.
La base et le réseau propres au lot ont été supprimés après la recette.
Assets/dépendances partagés uniquement en lecture seule.
Aucune recette visuelle mobile/clavier réelle ni CI complète effectuée.
Aucun push, fusion, déploiement ou modification des règles de jeu.

## Suites distinctes

Historique détaillé des traités, recherche avancée des membres, historique
des contributions, corrections administratives sécurisées et sanctions
restent hors de cet incrément. Ne pas présenter ce lot de consultation comme
une gestion complète des guildes.
