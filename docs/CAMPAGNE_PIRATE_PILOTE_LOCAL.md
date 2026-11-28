# Campagne pirate : admission et recette

## Disponibilité

- Le dossier est accessible aux joueurs vérifiés possédant une participation active dans leur
  univers, en mode applicatif dev ou prod. Le numéro d’une mission ne sélectionne ni le compte,
  ni la participation, ni l’univers.
- La case **Administration → Univers → Gérer → Règles de jeu → Fonctionnalités → Campagne pirate**
  est activée par défaut, y compris pour un univers historique sans ce réglage. Les réglages explicites
  sont conservés. Le nom d’affichage ou le slug de l’univers ne change pas l’admission.
- Décocher cette case et enregistrer bloque les nouveaux ordres et masque l’accès dans Événements.
  Cela ne purge rien : les opérations déjà engagées continuent avec leur preuve durable et le joueur
  retrouve son historique lors d’une réactivation. Ce réglage ne nécessite pas de reconstruire ni
  redéployer l’application après la mise en service de cette version.
- Les Guildes sont également activées par défaut. Un réglage contraire enregistré pour un univers reste prioritaire.
- L’environnement technique Symfony alpha n’enregistre pas ces routes. Si un environnement de
  préproduction est délibérément déployé en APP_ENV=prod avec cette version, ses univers appliquent
  les mêmes valeurs par défaut dans leur propre base. Ne pas confondre le mode Symfony et la cible
  de déploiement.

## Campagnes successives

Le dossier s’ouvre sur la mission courante de l’empire. Chaque campagne compte vingt missions. La victoire à la vingtième ouvre le signal suivant, même si un convoi ou des survivants sont encore en vol. Les archives, les rapports et les anciens liens restent consultables. Une reconnaissance déjà lancée conserve ses effectifs, ses rangs, son scénario et son devis. Les compositions validées sont les suivantes.

| Chapitre | Nouvelle classe et rang dans la première campagne | Effectifs successifs de cette classe |
| --- | --- | --- |
| 1 · Missions 1–3 | Légers T1 (chasseur de patrouille, intercepteur, chasseur furtif) | 12/10/8 → 24/20/16 → 120/100/80 |
| 2 · Missions 4–8 | Bombardiers T1 | 20 → 30 → 40 → 50 → 60 |
| 3 · Missions 9–13 | Escortes T2 (corvette, frégate, destroyer) | 5/10/6 → 8/15/9 → 10/20/12 → 13/25/15 → 15/30/18 |
| 4 · Missions 14–16 | Lourds T2 (chasseur lourd, croiseur, cuirassé) | 20/4/3 → 40/8/6 → 60/12/9 |
| 5 · Missions 17–20 | Capitaux T3 | 2 → 6 → 10 → 20 |

Chaque chapitre garde les effectifs atteints dans les chapitres précédents. La deuxième campagne reprend ces compositions entièrement en T2 et la troisième entièrement en T3. Chaque finale réunit 524 vaisseaux. Les titres respectifs sont « Chasseur de Pirate amateur », « Chasseur de pirate confirmé » et « Chasseur de pirate sénior ». Le fret des nouvelles rencontres suit le coût catalogue des vaisseaux au rang rencontré. Les reconnaissances déjà engagées conservent leur composition et leur prix figés.

## Renseignement et simulation

- La reconnaissance propose la sonde de plus bas rang disponible sur la colonie active (T1, puis T2, puis T3). Si ce rang change avant confirmation, le joueur doit confirmer le devis actualisé.
- À partir de la remise effective par le worker, ouvrir **Ouvrir le rapport d’espionnage** dans le
  dossier. La sonde reste perdue, qu’elle ait été repérée ou non. Le rapport reste privé à la
  participation active : un autre commandant ou un univers désactivé ne peut pas le consulter.
- **Reprendre ces renseignements dans le simulateur** charge exclusivement la défense pirate
  figée pour le combat réel : effectifs par rang, profils, modificateurs, nombre de rounds et
  recharge des boucliers. Il n’y a ni défense fixe, ni forteresse, ni recherche indépendante
  côté pirate. Les vaisseaux partent au premier round. Le joueur conserve la saisie de l’escorte ;
  les essais n’envoient aucun vaisseau et ne prétendent pas prévoir l’issue réelle.
- Un relevé historique sans scénario figé affiche seulement la composition connue, sans import
  approximatif. Le rapport est une lecture de la reconnaissance existante, sans nouveau
  journal d’espionnage de planète, et sans second envoi de sonde. Le dossier pirate et la rubrique Espionnage des Rapports ouvrent la même reconnaissance.
- Chaque tentative résolue publie le bilan des deux camps dans Rapports → Combats. Le joueur
  peut y consulter les effectifs, pertes et rounds enregistrés et retrouver le rapport depuis le dossier.
- Chaque trajet du convoi dispose d’un rapport de transport dans Rapports → Économie. À la livraison,
  le joueur reçoit dans l’inventaire une boîte contenant exactement la cargaison. Les entrepôts
  sont vérifiés à l’ouverture sur la colonie choisie. Un inventaire plein place la boîte en attente.
  Les anciens convois bloqués par le stockage peuvent être retirés depuis le dossier. Les anciennes
  livraisons déjà créditées conservent leur reçu. L’historique reprend les preuves encore conservées.

## Choix de l’escorte

- Le dossier d’une reconnaissance terminée reprend le tableau de préparation de Flottes :
  une ligne par type admissible, trois rangs et le stock réel de la colonie active. Un rang
  sans unité mobilisable est affiché à zéro et non saisissable ; les types non admis à l’attaque
  ne sont pas proposés.
- Les quantités restent saisissables sans JavaScript. Avec JavaScript, Max tient compte du stock
  et de la limite totale après les unités déjà choisies dans les autres cases.
- L’aperçu puis la confirmation sont inchangés : le formulaire ne transmet que les choix liés au
  devis, et le serveur les revalide au départ. Afficher ou vérifier le devis ne lance pas d’attaque.

## Suivre le trajet et recevoir le titre final

- Depuis **Flottes** ou **Vue générale**, le joueur voit le temps restant et la progression
  depuis le départ constaté. La composition reprend les vaisseaux partis ou les survivants
  réservés. Le convoi affiche le fret de son droit, jamais un nombre de vaisseaux inventé.
- Dans **Galaxie**, le journal suit l’échéance ; la carte relie deux systèmes seulement
  lorsqu’ils sont connus du propriétaire. Si elle ne dispose que d’une ancre (notamment la
  sonde avant son rapport), elle marque cette ancre sans révéler de direction. Ni la guilde
  ni les autres commandants ne reçoivent ces trajets. L’échéance dépassée ne prouve pas l’arrivée.
- La victoire de **La nuit se brise** attribue le titre de la campagne terminée une fois par compte. Le joueur choisit de l’équiper dans son profil. Les titres sont **Chasseur de Pirate amateur**, **Chasseur de pirate confirmé** et **Chasseur de pirate sénior**, dans l’ordre des campagnes.

## Pour l'exploitation lors d'un déploiement décidé par l'opérateur

1. Lancer le processus habituel de validation et de migrations du dépôt avant l’ouverture.
2. Vérifier app, relay et worker Messenger sur la cible voulue. La case ne peut pas compenser un
   service asynchrone absent. Aucun drapeau ni identifiant d’univers supplémentaire n’est requis.
3. Contrôler les données d’un univers via son administration et la présence de la campagne dans
   Événements pour un joueur vérifié. Les routes de dossier sont privées et non mises en cache.
4. En cas de pause, décocher la case pour le seul univers concerné. Ne pas arrêter les workers :
   ils doivent vider les ordres déjà acceptés. Contrôler l’état durable après l’échéance ; n’utiliser
   ni modification manuelle des reçus ni « rattrapage » par une relance générale.

Le catalogue de développement a employé Omega comme témoin et les recettes accélérées modifient
uniquement leur univers et horloge jetables. Aucun trajet de 300 secondes n’est forcé en jeu.
Le cadrage du déroulement est décrit dans [Campagne et Balise 17](CAMPAGNE_PIRATE_BALISE_PILOTE.md).
