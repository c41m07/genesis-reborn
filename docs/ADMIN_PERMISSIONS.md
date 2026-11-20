# Capacités administratives — premier périmètre

## Périmètre et analyse de risques

Complexité 4/5. Branche indépendante `feature/admin-permissions` depuis dev
`8be1fd9` (PR #99). Les décisions de jeu, les rôles stockés des comptes et les
protections du système ne changent pas. Aucune migration n’est créée/appliquée.
Les sources Work nommées dans AGENTS.md restent absentes du dépôt.

Risque principal : ouvrir une route à un rôle partiel alors que la page ou un
service suppose encore un administrateur complet. L’incrément conserve donc
les attributs ROLE_ADMIN, les droits existants et le contrat de connexion.
Aucun rôle support/modérateur/opérateur n’est introduit ni attribué.

## Architecture livrée

- `AdminCapability` : noms explicites PLAYER_VIEW, RESOURCES_ADJUST, SHIPS_GRANT, FLEET_INTERVENE.
- `AdminCapabilityPolicy` : liste fermée, refus par défaut, administrateur
  non suspendu seulement. Une capacité inconnue, une préférence JSON ou une
  chaîne ajoutée aux rôles ne confère jamais une autorisation.
- `AdminCapabilityVoter` : intégration Symfony sans voter sur ROLE_ADMIN ;
  refuse les noms inconnus de son namespace et les sujets non définis.
- Fiche joueur, ses deux POST et intervention sur une flotte : capacité respective ajoutée au contrôle
  ROLE_ADMIN existant. L’authentification, la confirmation d’adresse, le CSRF,
  les confirmations d’intervention et l’audit restent en place.
- `AdminInterventionService` : vérifie la capacité correspondante dans la
  transaction, après verrouillage/rafraîchissement de l’acteur. Aucun cache
  d’une décision préalable ; ordre des autres verrous et contrôles inchangé.

La politique conserve le comportement des services existants (rôle actif),
sans y inventer une nouvelle validation d’adresse ; celle-ci appartient au
parcours d’authentification existant. La capacité n’est pas une validation de
propriété, d’univers ou de paramètres : ces contrôles restent dans le service.

## Limites volontaires et prochaine décision

Il s’agit d’un socle raccordé à quatre actions, pas d’une délégation granulaire
utilisable par des comptes secondaires. Le reste des contrôleurs demeure
protégé par ROLE_ADMIN sans conversion automatique. Aucun écran d’attribution,
hiérarchie de rôles, permission persistée ou accès partiel n’est exposé.

Avant activation de profils délégués :
1. Valider profils et matrice lecture/écriture, portée globale ou par univers.
2. Définir le stockage et l’audit des changements (pas les préférences joueur),
   la révocation et la protection du dernier administrateur.
3. Raccorder chaque route ET service aux capacités, réduire les données
   préparées/rendues aux droits réellement accordés, adapter la navigation.
4. Recette d’accès et révocation concurrente, état périmé et dernier admin ;
   aucune garantie de concurrence nouvelle revendiquée par les tests unitaires.
5. Migration préparée séparément si nécessaire, sauvegarde vérifiée et
   autorisation avant application sur une base existante.

## Vérifications

Tests unitaires sur objets synthétiques sans base : 2 tests sur la matrice de capacités.
Ils couvrent admin existant, joueur, absence d’acteur, suspension, capacité
inconnue, sujet non défini, rôles/préférences non reconnus et révocation sur
l’objet courant malgré un token historique. Conteneur Symfony et Compose validés.
Recette finale HTTP, politique/voter, interventions et pages dédiées sur base
jetable : **30 tests / 311 assertions réussis**. Contrôles PHP des six fichiers
applicatifs, injection Symfony et `git diff --check` réussis. Tests exécutés
séquentiellement dans `genesis-admin-permissions-test`, base MariaDB tmpfs
et réseau privés retirés après recette ; transport sans envoi réel.
Aucun test navigateur ni CI complète revendiqué.
La première reprise a révélé un appel commun de flotte non raccordé ; il
a été ajouté explicitement (aucune permission implicite de repli).
Aucune recette réelle, migration, attribution, push, fusion ou publication.

## Finition : matrice consultable sans délégation

Complexité 3/5. La page dédiée GET `/admin/permissions`, dans Exploitation,
décrit les quatre capacités raccordées, leurs effets, leurs cibles et leurs
garanties. Une page stable est préférable à un formulaire d'attribution fictif :
consultation directe et rechargement restent possibles sans JavaScript.
La liste fermée utilisée par la politique fournit aussi les lignes de la matrice.
Aucun compte ni univers n'est chargé par ce contrôleur ; les compteurs communs
du gabarit admin conservent leur fonctionnement existant.

Les deux contrôles ROLE_ADMIN et PLAYER_VIEW sont maintenus. La page n'a aucune
route d'écriture, ne permet aucune attribution et n'annonce pas de séparation
par univers. Les profils partiels restent non activés : ouvrir seulement une
fiche sans réduire ses stocks, formulaires et participations serait trompeur.
La consultation de la fiche joueur est présentée comme journalisée, pas comme
une opération sans effet de bord.

Les tests unitaires vérifient désormais toute la liste, les descriptions et
l'absence de fuite de décision entre deux acteurs successifs. Un rôle historique
dans le token ne remplace pas les rôles courants. Suspension et révocation sont
rejouées pour chaque capacité. Tout sujet non nul, y compris un identifiant ou
un tableau d'univers, est refusé ; ceci ne prouve pas une isolation par univers
déléguée, qui n'est pas implémentée.

Vérifications de cet incrément :
- Compose validé via `make validate`.
- 4 tests unitaires / 84 assertions réussis, conteneur sans réseau.
- Syntaxe PHP des quatre fichiers concernés, trois Twig et conteneur Symfony validés.
- Tests HTTP étendus pour accès anonyme/joueur/admin, lecture répétée et refus POST :
  1 test / 37 assertions réussis sur une MariaDB tmpfs privée. La réponse 405
  attendue au POST apparaît dans le journal de test, sans constituer un échec.
  Le blocage initial de pool réseau Docker a été résolu en attendant la fin
  d'une autre recette ; aucun réseau existant n'a été modifié.
- Base et réseau `genesis-permissions-review` retirés après recette.
- `git diff --check` réussi. Les validations sont ciblées, pas une suite complète.
- Aucun réseau existant modifié, aucun droit attribué à un vrai compte.
- Pas de recette navigateur, de CI complète, de fusion ni de déploiement.

## Aperçu fictif de délégation (aucune délégation réelle)

Branche feature/admin-apercu-delegation, depuis 78763ba. Complexité 3/5 :
projection pure, GET uniquement, sans changement de politique ni de schéma.
La matrice existante reste intacte ; un lien ouvre un aperçu séparé pour des
administrateurs déjà autorisés par ROLE_ADMIN et PLAYER_VIEW.

Deux échantillons en code, A (Atlas) et B (Boréal), sans dépôt ni entité.
La projection autorise seulement le nom fictif du périmètre et trois cartes
contenant chacune un libellé, un exemple et la limite de ce qui est montré.
Aucun identifiant d’univers réel n’est accepté et seule la fiche sélectionnée
est rendue ; les liens A/B sont des repères, pas des données d’un autre univers.

| Profil illustratif | A montre | B montre | Aucun des deux ne montre |
| --- | --- | --- | --- |
| Support | SUP-A17, en attente | SUP-B62, résolu | Adresse, identité, lien d’activation, secret ou état de sécurité du compte |
| Modération | MOD-A23, à examiner | MOD-B73, classé | Auteur, message, preuve, décision ou sanction |
| Exploitation | OPS-A31, échéance dépassée | OPS-B89, échéance future | Donnée joueur, jeton, charge utile, détail réseau ou reprise |

Ces champs sont pédagogiques, pas une matrice validée de données réelles.
Reste à décider : délégants, bénéficiaires, champs et finalités, portée/durée,
révocation, conflits d’intérêts et audit. Aucun bouton d’attribution, aucun
nouveau rôle, aucun voter ni contrôle métier modifié.

Recette : 6 tests et 178 assertions, dont GET refusé aux visiteurs/joueurs,
GET admin, sélection aller/retour A/B sans fuite, portée invalide 404,
POST 405, absence de formulaire et état des rôles/journal inchangé sur les GET
autorisés. La projection pure teste aussi les valeurs hors liste et la forme
fermée. Compose, lint PHP (4 fichiers), Twig admin (61), conteneur, diff
validés. MariaDB synthétique privée et réseau supprimés.
Pas de recette navigateur, CI complète, intégration, push ou déploiement.

## Aperçu interactif de l’observateur support

Branche feature/admin-apercu-observateur depuis V7 5848437. Complexité 3/5.
Le GET d’aperçu existant propose quatre simulations par univers fictif :
actif, expiré, révoqué et hors périmètre. Le scénario actif affiche 15 min
restantes sur une horloge d’exemple et un seul statut fermé. Les refus
affichent une raison, une durée accessible nulle et aucun statut. Aucune
information de l’univers non sélectionné, même en cas de refus. Portée et
scénario sont des listes fermées ; une valeur inconnue est refusée.

Les trois cartes historiques servent à expliquer les métiers, séparément
de la projection de l’observateur. L’interface précise que ni le compteur,
ni l’accès ne sont réels. Ce lot reprend seulement les invariants d’univers
et de fraîcheur de la preuve bf29982, pas un acteur, un stockage ou une
politique d’accès en production. Toujours le GET protégé par ROLE_ADMIN
et PLAYER_VIEW ; aucun routeur nouveau, formulaire, action de délégation,
écriture, lecture de données réelles, migration ou vote modifié.

Décisions avant un vrai raccordement (valeurs prudentes à confirmer) :
1. Gouvernance : délégant explicitement nommé par le propriétaire, un
   bénéficiaire distinct, aucun droit de retransmettre ou d’auto-attribuer.
2. Données : un seul univers, statut fermé agrégé seulement, exclure
   identifiants et champs libres ; masquer les résultats à faible effectif
   tant que le seuil de divulgation n’a pas été décidé.
3. Durée et contrôle : fenêtre courte sans renouvellement automatique,
   révocation immédiate et preuve de fraîcheur à chaque lecture ; journal
   minimal sans contenu sensible. Les 15 min de la fixture ne fixent pas
   une durée de gouvernance.

7 tests / 142 assertions, dont états des deux univers et politique actuelle
inchangée. Lint PHP, Twig, conteneur, Compose et diff réussis. Tests en
conteneur sans réseau ; aucune DB ou recette HTTP/Chromium utilisée.
Pas d’intégration à V7, de push, fusion, CI ou déploiement.
