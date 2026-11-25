# Observatoire : Performance

## Décision

Retenir la maquette A : classement et diagnostic côte à côte, évolution en
dessous. Réutiliser le shell, les filtres et les permissions de l’Observatoire.
Ce premier lot ne modifie pas les règles du jeu et ne déploie rien sur la
production.

## Mesures à distinguer

| Signal | Mesure prévue | Ce qu’il ne prouve pas |
| --- | --- | --- |
| Application HTTP | Durée PHP, phase SQL et requêtes comptées pour la même requête seulement là où elles sont instrumentées. Phase Twig optionnelle. | Le temps passé en dehors de PHP, ou la responsabilité d’une requête SQL particulière. |
| Navigateur | Premier octet, transfert HTML, affichage du contenu principal (LCP), type mobile/ordinateur et nombre d’observations. | Le TTFB n’est pas du temps serveur pur. Le LCP n’est pas du temps de rendu pur. |
| Relais et RabbitMQ | Retard du relais après échéance, durée de publication et délai jusqu’à la prise en charge du travail éligible. | Le délai ne doit pas compter l’attente volontaire. Sans compteur natif, ne pas annoncer une taille de file déduite. |
| Workers | Durée de traitement, redélivrances et échecs par famille de messages. | Un traitement lent n’explique pas nécessairement un chargement HTTP lent. |
| Mercure | Réussites, erreurs et latence de publication ; signal de réception navigateur distinct seulement lorsqu’il est disponible. | Une publication reçue par le hub ne prouve pas qu’un joueur l’a reçue. |

## Garde-fous

- Échantillonner les accès admissibles de tous les joueurs, sans sélection
  par compte. Agréger par route, fenêtre UTC et appareil. Ne conserver ni URL
  brute, ni adresse IP, ni identifiant de joueur, ni texte des requêtes SQL,
  ni sujet Mercure, ni en-tête privé dans les métriques.
- Garder la collecte hors du temps mesuré, bornée, désactivable. Tester le
  parcours en local et sur dev avant toute décision d’activation en production.
- Calculer les quantiles sur les effectifs cumulés, pas en moyennant des
  percentiles. Masquer la médiane en dessous de 30 observations et le p95
  en dessous de 100. Signaler les métriques non disponibles et ne pas remplir
  les périodes non mesurées.
- Pour orienter une investigation, comparer les phases PHP / SQL / Twig
  mesurées dans les mêmes requêtes, pas deux percentiles. Le repère de
  version aide à chercher : ce n’est pas une explication.
- Chaque alerte doit donner la mesure qui la déclenche et une prochaine
  vérification. Exemple : si le SQL est la phase dominante de la Vue générale,
  commencer par chercher les lectures répétées avant de changer le frontend.

## Vérifier le lot sur dev

- La collecte est désactivée par défaut (`APP_PERFORMANCE_SAMPLE_PERCENT=0`).
  Sur une instance de test, appliquer la migration et configurer la variable
  sur l’application, le relais et les workers. Utiliser 100 uniquement pour
  une recette à faible trafic ; revenir à 0 en fin de recette.
- Visiter la Vue générale et quelques pages du catalogue, sur un petit écran
  puis sur ordinateur. Laisser la page ouverte dix secondes ou changer
  d’onglet pour émettre le relevé du navigateur. Le contenu du relevé ne contient
  ni chaîne agente ni URL ; l’envoi désactive le référent et les cookies. Un jeton court désigne uniquement
  la route canonique. Cinquante comptes ne sont pas nécessaires : le nombre
  affiché est un nombre de chargements de l’échantillon, pas de visiteurs.
- La colonne LCP peut rester vide sur un navigateur qui ne l’expose pas, ou
  si l’affichage n’a pas été observé dans la fenêtre de dix secondes. Les
  temps hors PHP (routeur, TLS, etc.) ne sont pas calculés par soustraction.
  Le temps SQL mesure l’exécution côté application, pas tous les transferts
  de résultat. Twig peut inclure du SQL ; ne pas additionner les deux.
- Déclencher un traitement inoffensif et une notification sur l’instance de
  test. Vérifier que les étapes relais, publication, prise en charge et
  traitement se remplissent ; comparer avec les journaux en cas d’échec.
  La mesure de publication ne donne ni la profondeur de file RabbitMQ ni
  le délai jusqu’à réception de la notification côté joueur.
- Le relais purge par lots les reçus anonymes expirés et les agrégats de plus
  de 35 jours. Les reçus empêchent qu’un envoi répété augmente le compteur,
  sans conserver de lien entre visites.
- L’onglet est global au site ; ne pas l’interpréter par univers. Les repères
  de mise en production et les traces de réception Mercure ne sont pas encore
  instrumentés. Aucune mesure n’est reconstruite pour le passé.

## Comparer une mise à jour

La console lit les notes publiques existantes (ni les brouillons ni les publications futures).
Le repère est l’heure de publication de la note, pas la télémétrie du déploiement.
Comparer jusqu’à sept jours de part et d’autre sur la même page et le même filtre appareil ;
les fenêtres ont le même nombre d’heures complètes. Écarter l’heure de publication si elle
mélange deux périodes, sans en déduire que la mise à jour explique la différence.
Les p95 sont calculés à partir des histogrammes, pas par moyenne des p95.

« Démarrer une période à zéro » enregistre et journalise une limite de suivi commune au
site et aux univers, effective à la prochaine heure pleine, avec conservation du suivi
précédent dans les limites de rétention habituelles. Le début de la période affichée peut
aussi servir de repère avant/après, indépendamment des notes publiées. Cette action ne supprime ni mesures
ni données de jeu ; le relevé de stocks courant ne dépend pas de la période. Les flux
économiques historiques ne peuvent pas être comparés tant qu’ils ne sont pas enregistrés.
