# Suivi du nettoyage documentaire

## Réorganisation, nettoyage et SEO — 7 novembre 2026

Travail sur `codex/app-organisation-seo`. Après les vérifications, le propriétaire
autorise le commit et l’envoi vers dev. Le changement local préexistant du suivi
Alpha est conservé hors du commit. Le fichier fighter.yaml est déplacé
sans modification de son contenu. Aucune règle de jeu, donnée ou volume existant
n’est modifié ou supprimé.

### Étapes et complexité

- Organisation, 4/5 : inspection des points d’entrée, dépendances et chemins ;
  application regroupée dans `app/`, définitions Docker sous `docker/`.
  Makefile, scripts d’exploitation, CI et points d’entrée Compose restent à la racine.
- Nettoyage, 2/5 : doublons prouvés par empreinte, consolidation des décisions,
  retrait des exemples Encore désactivés et de la déclaration npm directe axe-core.
  Le moteur axe reste une dépendance de @axe-core/playwright ; seul le manifeste
  racine du verrou change, aucune version verrouillée ne change.
- Documentation, 2/5 : README, index, roadmap, wiki et déploiement mis à jour ;
  chemins actuels du guide des illustrations corrigés. Les audits restent datés.
- Accessibilité et SEO, 3/5 : accueil public, métadonnées centralisées,
  exploration, règles d’indexation et tests HTTP/navigateur. Réévaluation à 4/5
  pendant la correction des options Symfony et des chemins des outils.
  Les contrôles d’accès privés et les protections de formulaire restent actifs.

### Nettoyage justifié et conservation

Les doublons stricts des décisions Fighter et alertes ont été retirés une seule
fois. Les captures historiques PNG strictement identiques sont consolidées vers
un exemplaire de référence, avec mise à jour des mentions documentaires :
16 fichiers redondants retirés ; aucune image applicative supprimée.

- `lot20-after-generic-403-1440.png` → `lot13-after-error-403-1440.png`
- `lot20-before-generic-403-1440.png` → `lot13-after-error-403-1440.png`
- `lot20-after-generic-403-390.png` → `lot13-after-error-403-390.png`
- `lot20-after-generic-403-in-universe-390.png` → `lot13-after-error-403-390.png`
- `lot20-after-generic-403-nojs-390.png` → `lot13-after-error-403-390.png`
- `lot20-before-generic-403-390.png` → `lot13-after-error-403-390.png`
- `lot20-before-generic-403-in-universe-390.png` → `lot13-after-error-403-390.png`
- `lot17-after-validated-404-1440.png` → `lot13-after-error-404-1440.png`
- `lot17-after-validated-404-390.png` → `lot13-after-error-404-390.png`
- `lot18-after-login-1440.png` → `lot13-after-login-1440.png`
- `lot18-after-login-390.png` → `lot13-after-login-390.png`
- `lot19-after-universe-settings-390.png` → `lot17-after-universe-settings-390.png`
- `lot19-after-universe-settings-saved-390.png` → `lot17-after-universe-settings-saved-390.png`
- `lot18-after-login-throttled-390.png` → `lot18-after-login-suspended-390.png`
- `lot19-after-reset-error-390.png` → `lot18-after-reset-valid-error-390.png`
- `lot19-before-settings-closed-390.png` → `lot19-after-settings-closed-390.png`

Les services Symfony, routes, formulaires, commandes, contrôleurs Stimulus,
templates, migrations, tests et scripts d’exploitation suivent les conventions
de chargement : leur absence d’appel textuel ne démontre pas leur inutilité.
Les autres dépendances sont conservées, notamment Maker comme outil de console,
Babel/core-js et les paquets Symfony déclarés. Aucun test de non-régression retiré.
Les 150 WebP du catalogue sont chargés par chemins Twig dynamiques ; toutes les
images applicatives, logos et SVG sont préservés. Les PDF, extractions textuelles,
rapports d’équilibrage et audits conservent des décisions ou preuves uniques ;
aucune suppression documentaire supplémentaire n’est justifiée.

Références courantes : README et docs/README pour l’orientation, REGLES et DECISIONS
pour le métier, ROADMAP pour les objectifs, guides spécialisés pour l’exploitation,
SEO pour le domaine public et Search Console. Les trois documents Work nommés
dans AGENTS n’ont été trouvés ni localement ni dans les recherches Drive accessibles.

### Limites

Aucun domaine de production ni accès propriétaire Search Console vérifié.
Aucun déploiement, aucune réception Discord et aucune indexation Google attestés.
Les tests ciblés d’accessibilité ne constituent pas une conformité RGAA globale.
### Résultats finaux locaux

- `make validate` : réussite ; configuration production également validée avec
  `APP_ENV_FILE=.env.prod.example` et le modèle non secret.
- `make lint` : réussite complète, PHP, 65 templates Twig, YAML, conteneur et schéma.
- `make test` : réussite complète finale, 232 tests PHP / 205 688 assertions,
  puis 13 tests Python. Les fins de ligne CRLF de db-restore.sh ont été corrigées
  et les exécutables protégés par .gitattributes, sans modifier la configuration globale.
- `npm run build` depuis `app/` : réussite avec Node 24.21.0, image locale existante,
  hors réseau et sans installation de dépendances.
- `BROWSER_PERSISTENCE=1 make test-browser` : exécution complète de 98 cas,
  97 réussites et 1 échec expliqué, en 40,7 minutes. Le test du retour 404 attendait
  encore la connexion au lieu du nouvel accueil public. Il vérifie désormais
  l’accueil puis le lien vers la connexion, en conservant ses contrôles existants.
  Reprise de `public-validation-gates.spec.mjs` et `public-seo.spec.mjs` :
  3/3 réussis en 59,5 secondes. Les 98 cas sont couverts par des résultats
  consolidés ; il ne s’agit pas d’une unique exécution complète verte.
- `git diff --check` : réussite ; espaces des sources déplacées et nouvelles
  également contrôlés sans modifier l’index.

Les premiers essais ont détecté une syntaxe Encore incorrecte pendant le retrait
des commentaires et un nom d’option Symfony incorrect ; ces erreurs sont corrigées
avant les contrôles finaux. Les assertions et délais des tests n’ont pas été affaiblis.

Contrôles complémentaires : tous les liens Markdown locaux existent ; les deux
workflows ont une syntaxe YAML valide ; `make help` et `make console CMD=about`
réussissent ; configurateur vérifié sur une copie vierge et réexécuté sans écrasement.
651 fichiers suivis déplacés dans app/. Les 60 fichiers de catalogues et d’univers,
les 150 images WebP du catalogue et les points d’entrée exécutables sont préservés.

Les réponses HTTP locales de robots.txt, du sitemap et de l’image sont 200 avec
les types attendus ; XML analysé et limité aux deux URL publiques. Les pages
publiques ont été vues à 1440 et 390 px. Un packaging de production hors réseau,
basé sur l’image existante possédant le même verrou PHP, embarque correctement
l’application et les sources du wiki. Une sonde de cette image en APP_ENV=prod
vérifie les cinq routes publiques à 200, les métadonnées et l’absence de noindex
global. La reconstruction complète des couches d’installation, l’installation
vierge des dépendances, la CI GitHub et le déploiement ne sont pas exécutés.

Les cinq projets navigateur créés par cette mission ne laissent aucun conteneur
ni volume jetable restant. Les services et volumes du projet principal restent
présents.

Aucune déclaration Search Console, aucun envoi réel de mail ni aucune validation
du serveur Alpha. Les actions manuelles précises restent dans [SEO.md](../SEO.md).


## Audit et mise à jour du README

### Refonte finale — 7 novembre 2026

Audit effectué sur la branche issue de dev484537d après intégration de A–K. Le README est réorganisé en français pour servir de point d’entrée : présentation, périmètre réel, état, versions vérifiables, prérequis, installation, variables privées, démarrage, données, commandes, qualité, architecture, liens et contribution.

- Sections ajoutées : Technologies et versions, Prérequis locaux, Installation d’un nouveau développeur, Configuration et variables d’environnement, Base de données et données de développement, Tests et qualité, Contribution et conventions. Elles précisent notamment les contraintes/verrous/exécutions, les services externes, les mails, l’amorçage et l’isolation.
- Sections remplacées : Environnement local Debian/WSL est répartie entre prérequis/installation/configuration ; Commandes principales devient Démarrage et travail quotidien ; Structure du dépôt devient Architecture générale ; Documentation devient Documentation de référence. État du projet est conservée et actualisée. Les détails d’exploitation sont reliés à leurs guides ; la description des jauges et l’objectif de stratégie modérée sont conservés.
- Informations corrigées : PHP>=8.2 du manifeste ne résume pas les exigences réelles du verrouillage ; Node20 observé n’est pas une version supportée par Encore/Babel actuels ; PHP8.4/Node24 des images sont distingués des versions exécutées. L’infrastructure précède migration/import et les consommateurs démarrent ensuite. Les réseaux seuls ne fournissent pas Traefik/SMTP. Les nombres du lotH sont remplacés par une preuve datée K, sans prétendre valider l’Alpha.
- Duplication réduite : les détails de mail, sauvegarde, alertes, relais, mesures et déploiement restent dans leurs guides actifs. La description des jauges ajoutée par le propriétaire est conservée. Les audits historiques restent datés, sans réécriture de leurs constats.
- Instructions agents : règle de mise à jour du README dans AGENTS.md déjà livrée en A, vérifiée et conservée ; aucun fichier redondant créé. Aucune licence ajoutée : aucun fichier de licence n’est présent, Composer indique proprietary et npm UNLICENSED/private.
- Vérifications préparatoires réelles : lecture des manifestes/verrous et configurations ; make help et console about exécutés ; versions hôte/conteneur relevées ; séquence d’installation recoupée avec la CI PR13 qui a installé les dépendances, migré/importé et démarré les consommateurs. Aucune nouvelle installation du checkout propriétaire ni migration sur sa base n’est exécutée par cet audit.
- Commandes et liens vérifiés :49 liens locaux du README,1 du rapport et6 dans AGENTS, aucun introuvable ;16 dossiers sources supplémentaires présents ; seuls les deux modèles .env sont versionnés. make help, doctor, validate et console about exécutés ; recettes Make examinées en simulation sans exécuter les installations/migrations/arrêts. npm run, npm run dev et npm run build exécutés avec Node24.21.0/npm11.19.0 dans une image existante hors réseau, dépendances existantes en lecture seule. validate/lint et229 PHP/205648 assertions+13 Python réussis pour ce lot. Les résultats des contrôles navigateur, de la CI et de la fusion sont suivis dans le document de reprise et la PR.
- Restent à documenter ou vérifier côté serveur : URL/version/digest de l’Alpha, configuration SMTP et réception, jobs et destination privée des alertes, sauvegardes planifiées/distantes et reprise complète. Les accès et mesures nécessaires ne sont pas inventés ; aucune préproduction n’est disponible selon le propriétaire.

Vérification finale — dépendance de test identifiée : `operations-alerts.spec.mjs` reconstruisait sa commande Compose sans le complément de persistance, tandis que le lanceur et la remise à zéro utilisaient ce complément. Les configurations effectives diffèrent bien (tmpfs contre volumes pour MariaDB/RabbitMQ) et un échec de préparation montre leur recréation puis un worker indisponible. Correction limitée à une ligne conditionnelle reprenant le même fichier ; assertions de conservation des volumes maintenues. Un dépassement du délai global de l’inventaire est aussi observé sous quatre projets concurrents (charge20,68 pour12CPU, état attendu100 bonus/2 en attente présent). La première exécution complète donne94 réussites/3 échecs sur97 cas. Le protocole de reprise couvre les3 fichiers concernés(5cas) avec une charge réduite ; aucun délai ni assertion n’est affaibli. Le bilan local consolidé et la CI complète sont distingués dans le suivi et la PR.

### Historique des mises à jour de la mission

Audit initial du 6 novembre 2026 : comparaison avec les manifestes Composer/npm, le Makefile, Compose et les documents de référence. Le README distingue insuffisamment les contraintes déclarées et les versions exécutées ; sa refonte complète et la vérification de tous ses liens restent à réaliser au fil de la mission. Le propriétaire confirme que l’Alpha est hébergée ; les détails de cet hébergement restent à vérifier (chantier I).

Chantier A : ajout de la description de la confirmation d’adresse, de ses variables de configuration et d’un lien vers `docs/ACCOUNT_EMAIL.md`. Aucune section supprimée. La règle de maintenance du README est ajoutée à `AGENTS.md`. Les commandes de contrôle et leurs résultats sont suivis dans [le suivi Alpha](alpha-operations-follow-up.md). Il ne s’agit pas encore de la validation finale du README sur `dev`.

Les 12 liens locaux du README et le lien local de ce rapport ont été vérifiés : tous leurs fichiers existent. Les contrôles Makefile, la compilation et les tests ont été exécutés dans un environnement local isolé, avec résultats consolidés pour les navigateurs. Les limites serveur et CI restent distinctes de ces preuves.

Chantier B : ajout de la durée et de l’usage unique du lien de réinitialisation, de l’invalidation lors d’une nouvelle demande et du renvoi vers la documentation partagée des mails. Aucune commande, dépendance ou section existante supprimée. Aucun résultat de réception en production annoncé.

Après cet ajout, les 13 liens locaux du README ont été vérifiés sans fichier manquant. Les résultats du chantier B sont consignés dans le suivi Alpha : 208 tests PHP réussis sur une exécution complète et 88 scénarios navigateur validés avec reprises ciblées.

Chantier C : ajout de l’observation des signaux des processus, du partage du runtime en production et de la fréquence de lecture dans l’admin, avec renvoi au nouveau guide de diagnostic. Aucune section supprimée ; l’accès au serveur et son état effectif restent distingués de la configuration versionnée.

Chantier D : ajout du lien vers le diagnostic des relais et des limites des données. La collecte indisponible n’est plus présentée comme une file vide. Aucun paramètre d’équilibrage, commande d’installation ou dépendance changé. La documentation distingue retard après échéance, âge inconnu et tentatives du worker.

Les 15 liens locaux du README existent. Le guide des relais, l’index et la ROADMAP ont aussi leurs liens vérifiés. Contrôles D : 215 tests PHP sur une suite complète, 89 scénarios navigateur validés après reprises ciblées ; les limites de consultation des échecs AMQP et l’absence de vérification des messages réels de l’Alpha sont documentées.

Chantier E : README complété avec les alertes par courriel, leurs seuils, leur état désactivé et le guide central de configuration. Ajout de la commande `make ops-check` et des variables `OPS_ALERT_*`, sans destination personnelle ni secret versionné. La procédure serveur et la construction de production restant à vérifier sont distinguées des preuves locales.

Vérification E : 16 liens locaux du README, 34 de l’index, 8 de la ROADMAP et 2 du guide d’alertes existent. Commande du Makefile et syntaxe du script vérifiées ; simulation et arrêt réel d’un worker de test validés. La suite PHP finale et les deux lots navigateur complets réussissent, sans validation de réception ou d’activation sur le serveur.

Chantier F : README complété avec les garanties et limites des sauvegardes, la rétention par défaut, les prérequis des scripts et les commandes backup-status/test-backup. Le test Python est maintenant inclus dans make test et une restauration isolée est prévue dans le lot PHP de la CI. Le guide actif remplace la référence de déploiement à un runbook historique sans réécrire ce dernier.

Vérification F : 17 liens du README, 35 de l’index, 1 du guide de sauvegarde, 3 du guide d’alertes, 2 du déploiement et 9 de la ROADMAP existent. Les nouveaux caches Python et les archives générées sont exclus de Git et du contexte Docker. Les scripts sont validés par tests unitaires et une restauration effective sur deux projets isolés ; ce résultat n’est pas présenté comme un PRA de production.

Chantier G : ajout au README d’un lien vers la matrice active des scénarios serveur. Les preuves locales de progression sans client et de reprise des processus sont séparées des procédures préparées et des validations de l’Alpha restant à exécuter. Aucune commande, dépendance ou règle de jeu modifiée.

Vérification G : 18 liens du README, 36 de l’index, 10 de la ROADMAP et 1 du guide des scénarios existent. Le mode de test à volumes persistants est exécuté localement, avec nettoyage de ses propres volumes vérifié. Les 92 scénarios réussissent en deux lots complets sans reprise ciblée ; les résultats hébergés restent distincts.

Chantier H : README complété par le guide des mesures par univers. Les indicateurs existants, leur couverture et les durées historiques indisponibles sont distingués ; aucune cible de progression n’est déclarée atteinte. Nouvelle page admin en lecture seule, sans migration ni modification d’équilibrage. Documentation de référence conservée sans dupliquer les règles.

Vérification documentaire H : 19 liens locaux du README, 37 de l’index, 11 de la ROADMAP et 3 du guide des mesures existent. Les limites d’historique, les dénominateurs de productivité, la pagination et le traitement des univers fermés sont documentés sans annoncer de validation de l’Alpha.

Validation H : les six contrôles réussissent, avec 228 tests PHP/205638 assertions, 13 tests Python et 93 scénarios navigateur en deux lots complets sans reprise finale. Les corrections de contraste ciblées antérieures sont tracées ; aucun test n’a été affaibli. Observation mobile et contrôles automatiques ciblés réalisés, sans revendiquer un audit RGAA complet ni une validation de production.

Chantier I — Audit et mise à jour du README : section État du projet actualisée avec le fait d’hébergement fourni par le propriétaire, preuves locales/CI du lot H et limites serveur. Les anciens nombres 192/87 et la CI présentée comme non vérifiée ne décrivent plus l’état courant. Liens historiques réorientés vers le suivi et le guide actif sans réécrire docs/agent. Guide de déploiement complété : preuves, inconnues et installation séparée des outils hôte. Aucun déploiement exécuté. La refonte structurelle finale du README reste prévue après J/K.

Chantier J : README complété avec le wiki connecté, son contenu unique docs/wiki.json et les références embarquées. Aucune dépendance Markdown ajoutée ; trois fichiers documentaires explicitement inclus dans le contexte et l’image de production. Validation fonctionnelle et six contrôles finaux encore en cours.

Vérification J : 20 liens locaux README, 40 index et 12 ROADMAP existent ; le guide de maintenance n’ajoute aucun lien local. Trois sources exportées via Docker scratch identiques aux originaux. Validation locale consolidée après correction démontrée d’une course de navigation, aucun test affaibli ; contrôles ciblés mobile/clavier/axe et sources exactes réussis. Aucun déploiement ni validation de l’Alpha.

### Compte à rebours de fermeture (K)

README et index reliés à UNIVERSE_CLOSING.md : date serveur, seuil inclusif 24h, synchronisation et limites à l’échéance. Aucun changement de durée des univers. Vérifications et statut de publication consignés dans le suivi Alpha.

Vérification K : liens README22/index42/ROADMAP14/guide1 présents. Suite locale complète229 PHP,13 Python et97 scénarios navigateur réussie ; aucune règle ni dépendance modifiée. La refonte finale du README reste distincte, après intégration de K.
