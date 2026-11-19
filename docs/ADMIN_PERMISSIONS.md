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
- Tests HTTP étendus pour accès anonyme/joueur/admin, lecture répétée et refus POST.
  Exécution en attente : création du réseau de recette refusée par Docker
  (`all predefined address pools have been fully subnetted`).
- Aucun réseau existant modifié, aucun droit attribué à un vrai compte.
- Pas de recette navigateur, de CI complète, de fusion ni de déploiement.
