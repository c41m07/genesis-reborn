# Profils des joueurs et présentations des guildes

## État du lot — 14 novembre 2026

Branche `feature/profils-joueurs-guildes`, issue de dev `4c13f20` après fusion
autorisée de la PR #60 et sa CI complète (925 PHP, 13 Python, 192 navigateur).
Complexités : profils 3/5 ; personnalisation et stockage des guildes 4/5.
La [PR #61](https://github.com/c41m07/genesis-reborn/pull/61) est fusionnée avec autorisation dans dev au commit 2170607. Cinq jobs quality réussis sur 6389774 : 952 PHP / 212 191 assertions, 13 Python et 194 parcours navigateur. Le propriétaire a classé GitGuardian comme identifiant de test (skipped), sans réécriture d’historique. Aucun déploiement Alpha.
La modification préexistante de `docs/PARCOURS_UI_UX.md` est conservée hors du lot.

## Informations et accès validés

- Joueurs connectés du même univers uniquement. Pseudo, guilde/tag et cinq scores/rangs déjà publics du classement quotidien ; aucune valeur recalculée en direct pour le profil. Un nouvel inscrit sans instantané reste non classé.
- Aucun email, état d’activité précis, coordonnées, effectif militaire ou inventaire. Titres et trophées reportés, aucun nouveau système créé.
- Liens depuis noms du classement, interlocuteur et auteurs de messages, équipage de la guilde. Les comptes détachés ou devenus empires oubliés n’ont pas de profil consultable.
- Pages de guildes du même univers lorsque la fonctionnalité est activée ; nom/tag, présentation, commandant et nombre de membres. Accès depuis classement, centre de guilde et équipage. Aucun nouvel accès au canal privé ou aux projets n’est accordé.

## Personnalisation

Commandant actuel uniquement, même si un autre grade possède toutes les
permissions déléguées. Le serveur contrôle propriété, univers, compte actif,
activation des guildes, CSRF et version de formulaire. Un transfert du
commandement ou une modification concurrente rend l’ancien formulaire inutilisable.

| Champ | Limites |
| --- | --- |
| Description | Texte simple, 2 000 caractères ; échappement HTML et retours à la ligne conservés |
| Logo | JPEG/PNG/WebP, 1 Mo, 512×512 maximum |
| Bannière | JPEG/PNG/WebP, 2 Mo, 1920×640 maximum |

Sans nouveau fichier, l’image existante est conservée. Les SVG téléversés sont
refusés ; aucun SVG ni logo d’identité existant n’est modifié. Le format réel,
les dimensions et le décodage sont contrôlés avant réencodage GD, qui retire
les métadonnées et les données supplémentaires du fichier d’origine. Le nom
fourni par le navigateur n’est jamais utilisé pour le stockage.

Les nouveaux fichiers sont retirés après échec ; les anciens ne sont nettoyés
qu’après validation de la transaction. La version et le verrou de guilde
empêchent un double enregistrement à partir d’un formulaire périmé.

## Schéma et stockage

`Version20261114133852` crée uniquement `guild_profile` (description, deux
références d’images et version), avec unicité par guilde et suppression en
cascade de la ligne. Aucune colonne ajoutée aux comptes ou guildes existants.
Migration préparée, **non appliquée à la base habituelle** ; les bases jetables
des tests sont distinctes. Une application sur base existante exige autorisation
explicite et sauvegarde vérifiée avant exécution.

GD est ajouté aux deux images Docker après accord explicite. Aucune version de
paquet Composer existante n’est modifiée ; le verrou déclare seulement `ext-gd`.
Les fichiers privés restent sous `var/guild-media`, permissions de fichier 0600.
Le service web de production monte le volume logique `guild_media`, conservé
lors des mises à jour. Les requêtes d’images revérifient l’univers et l’état de
la guilde, avec `nosniff`, CSP restrictive et `private, no-store`.

La sauvegarde SQL ne contient pas les images : voir [sauvegardes](SAUVEGARDES.md)
et [déploiement](DEPLOIEMENT.md). Après dissolution, la page et les images ne
sont plus accessibles ; des fichiers peuvent rester dans le volume. Aucune
purge générale automatisée des médias dissous n’est ajoutée à ce lot.

## Vérifications

Vérifications locales ciblées réussies :

- 79 tests PHP / 386 assertions : profils, permissions et isolation des univers, comptes oubliés/verrouillés, instantanés quotidiens, grades délégués et ancien commandant, CSRF, description et XSS, trois formats réencodés, métadonnées retirées, fichiers invalides, limites, remplacement et double soumission.
- 11 parcours navigateur distincts : neuf régressions classement/contacts/guildes réussies, puis deux nouveaux parcours réussis après correction du test (requêtes via le navigateur et attente du routeur après redémarrage). Profils et formulaires au clavier, thèmes clair/sombre, largeurs 320/390/1440 px sans débordement, contrôles axe ciblés sans violation. Captures réelles des deux pages examinées sur ordinateur et mobile. Le test isolé confirme la conservation du contenu et de l’empreinte de l’image après redémarrage du service web.
- `make validate`, `make assets`, Composer validate, lint Twig (159 templates), YAML (78 fichiers), conteneur, syntaxe PHP/JavaScript, JSON du wiki et `git diff --check` réussis.
- Mapping Doctrine valide avec `--skip-sync` : synchronisation de la base habituelle volontairement non exécutée, car la migration n’y est pas appliquée. Cela ne constitue pas un `make lint` complet.
- Images Docker de développement et de production reconstruites ; GD/JPEG/WebP présents en production. Stockage privé en mode 0700, accessible en écriture à l’utilisateur web, fichiers en 0600. Compose de production validé sans démarrage ni déploiement.

Les tests PHP utilisent la base `database-test` et un dossier média sous
`var/cache/test`, sans modifier les données habituelles. Les tests navigateur
utilisent leur projet et leurs volumes jetables ; le test de persistance
redémarre uniquement leur service web.

La suite locale complète n’a pas été relancée pour ce lot. Les dépendances
Composer existantes n’ont pas été mises à niveau ; les avis de sécurité signalés
par Composer restent à traiter séparément.

Les cinq jobs quality de la CI sur `03dcfbb` ont réussi : 952 tests PHP /
212 191 assertions, 13 tests Python, 194 parcours navigateur, lint et
sauvegarde/restauration isolées. GitGuardian a signalé le mot de passe fixe
d’une fixture ; remplacé par une valeur générée à chaque test, sans désactiver
le contrôle. Reprise locale : 14 tests du contrôleur / 84 assertions réussis.
Cette reprise a ensuite été validée dans la CI complète de 6389774 ; GitGuardian a été classé comme identifiant de test par le propriétaire avant la fusion autorisée.
Consulter la PR pour le statut courant de la CI sur sa dernière version.
Aucune migration de la base habituelle ou publication Alpha n’est effectuée.

Fichiers principaux : contrôleur `PublicProfileController`, services
`SocialProfileView`, `GuildProfileService`, `GuildImageStorage`, entité
`GuildProfile`, migration, trois nouveaux templates et styles des profils ; liens
des templates classement/messages/guilde. Tests `PublicProfilesTest`,
`GuildImageStorageTest`, `public-profiles.spec.mjs`. Dockerfiles, Compose de
production et persistance de test, déclaration Composer et documentation associée.
