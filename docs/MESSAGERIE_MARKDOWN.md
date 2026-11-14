# Messagerie : raccourci et Markdown

## État du lot — 14 novembre 2026

Lot 4 sur feature/messagerie-markdown, depuis dev 2170607 après fusion autorisée
de la [PR #61](https://github.com/c41m07/genesis-reborn/pull/61).
Ses cinq jobs quality réussissent sur 6389774 : 952 tests PHP, 13 Python,
194 parcours navigateur ; GitGuardian est classé par le propriétaire comme
identifiant de test (skipped), sans réécriture d’historique. La protection de
dev impose PR et cinq jobs quality, y compris aux administrateurs.

Complexités : raccourci et doublons 3/5, Markdown sécurisé 4/5, historique et
migration 3/5. Développement local uniquement : aucune publication/PR du lot,
aucune application des migrations à la base habituelle, aucun déploiement Alpha.
Les modifications préexistantes de docs/PARCOURS_UI_UX.md et l’état de
app/assets/controllers.json restent hors du lot.

## Comportement validé

Messages privés, guilde et Global : Ctrl + Entrée envoie, Entrée ajoute une
nouvelle ligne et le bouton Envoyer fonctionne aussi sans JavaScript.
Composition IME et répétition de touche ne déclenchent pas d’envoi. La validation
native reste exécutée ; le bouton est bloqué pendant l’envoi et réactivé au
retour d’une page conservée par le navigateur.

Seuls les nouveaux messages du service utilisent Markdown : gras, italique,
listes, citations, code et liens HTTP/HTTPS absolus. Titres, tableaux et autres
extensions ne sont pas activés. Le HTML reste du texte échappé et les images
Markdown se réduisent à leur libellé, sans chargement distant. Les autres
protocoles et destinations relatives ne créent aucun lien. Les liens sont
signalés au navigateur comme contenu utilisateur et sans référent.

Les anciens messages conservent caractères Markdown, espaces et sauts de ligne
en texte simple. Le format est décidé par le serveur. Les aperçus de guilde
et de recherche restent du texte échappé, avec leurs limites existantes ; la
lecture complète repliable d’un résultat long utilise le même rendu sécurisé
que la conversation. La modération montre le texte source échappé et la
recherche porte sur cette source.

## Sécurité et données

Ajout de league/commonmark autorisé explicitement : version verrouillée 2.10.7,
plancher ^2.10.7 ; cinq nouveaux paquets, aucun paquet préexistant mis à niveau.
Parseurs et renderers explicitement limités au sous-ensemble autorisé, HTML
échappé, liens dangereux refusés, imbrication limitée à 20 niveaux et délimiteurs
à 100 par ligne. La limite existante de 2 000 caractères est conservée et un
texte UTF-8 invalide est refusé avant rendu. Voir les
[consignes de CommonMark](https://commonmark.thephpleague.com/2.x/security/).

Chaque formulaire porte un UUID non secret. Sous les verrous et transactions
existants, le serveur revérifie les droits puis reconnaît une répétition
identique du même expéditeur : aucun nouveau message, notification ou
consommation de limite d’envoi. Réutiliser le formulaire pour une autre
destination, un autre canal ou contenu est refusé. Deux formulaires distincts
peuvent envoyer volontairement le même texte. CSRF, isolation des univers,
droits des guildes, compteurs et notifications existants sont conservés.

Version20261114151245 ajoute à social_message le format (plain par défaut),
l’identifiant nullable et un index unique par expéditeur/soumission, sans
réécrire le contenu historique. Migration créée seulement : sur base existante,
application après autorisation explicite et sauvegarde vérifiée. Code et
schéma doivent être livrés ensemble : les requêtes ORM des messages exigent les
nouvelles colonnes. La migration du lot 3 reste aussi non appliquée à la base
habituelle.

## Vérifications et reprise

Vérifications locales ciblées réussies :

- 134 tests PHP élargis / 1 586 assertions, puis 14 tests du contrôleur / 58 assertions après compléments : 137 tests distincts. Rendu et protocoles, UTF-8 invalide, CSRF, univers, soumissions répétées et changées, notifications, limites, recherche, identités oubliées et persistance.
- 10 parcours navigateur réussis après reprises : privé/guilde/Global, bouton sans JavaScript, modération, recherche/non-lus, simulateur et compositions.
- Clavier, composition IME, répétition de touche, nouvelle ligne, blocage pendant envoi et nombre exact de messages vérifiés. Contrôles axe ciblés en clair/sombre et à 320/390/1440 px. Deux contrastes de messagerie corrigés en clair (dates et conversation active) ; les assertions sont conservées.
- Compilation des assets, validation Compose, lint Twig (159 templates), lint du conteneur, syntaxe PHP/JavaScript, JSON du wiki et Composer validate réussis.
- Mapping Doctrine validé avec --skip-sync : la base habituelle n’est pas synchronisée. Le test de migration vérifie sur base isolée qu’un contenu antérieur reste intact et plain, puis que le schéma obtenu correspond aux entités. Ce bilan ne constitue pas un make lint complet sur la base habituelle.

Le premier scénario de double envoi retenait une réponse réseau tout en attendant
sa navigation. Cette simulation a été remplacée par un vrai Ctrl + Entrée puis
des tentatives simultanées dans le navigateur ; tous les contrôles de contenu,
d’état du bouton et de quantité restent présents. La suite locale complète et
la CI GitHub du nouveau lot ne sont pas revendiquées.

PHP utilise uniquement database-test/genesis_v2_test ; le navigateur utilise son
projet jetable. Le test de migration part d’une table antérieure avec un message
existant et contrôle l’intégrité du contenu et son format texte.

Les captures réelles en clair/sombre, à 320 et 1440 px, ont été examinées.
Le parcours privé complémentaire repasse, avec l’aide Markdown ouverte/fermée au
clavier et contrôlée par axe, sans débordement. Ces vérifications ciblées ne
constituent pas un audit de conformité RGAA.

Publication et PR demandent encore un accord du propriétaire. Avant fusion :
CI complète réussie sur la dernière version, puis accord explicite.

## Fichiers principaux

- Contrôleur SocialMessageController, service SocialMessageService et entité SocialMessage.
- MessageMarkdownExtension, MessageLinkRenderer et filtre Twig SocialMessageExtension.
- Contrôleur navigateur message_compose, templates messages/message_search et styles messaging.
- Migration Version20261114151245, Composer et son verrou.
- Tests SocialMessageSubmissionTest, SocialMessageExtensionTest et message-markdown.spec.mjs.
- README, règles, décisions, index, feuille de route, guide de déploiement et wiki ; suivi du lot 3 actualisé après sa fusion.
