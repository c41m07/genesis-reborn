# Architecture et parcours applicatifs

## Organisation et environnement

Le code Symfony, les templates, assets, migrations, tests et dépendances sont
dans `app/`. Docker est sous `docker/`, les outils du dépôt sous `scripts/`.
Make et les points d’entrée Compose restent à la racine ; npm s’exécute dans app.

Les fichiers locaux `.env`, `.env.local`, `.env.dev` et `.env.test` restent à la
racine. Leurs homonymes dans app sont des liens vers ces fichiers, pas des copies.
Modifier un lien modifie le même fichier. Ne jamais publier les secrets.
`make configure` prépare les liens .env et .env.local ; Compose monte ces fichiers
dans /app en lecture seule. Les modèles .env.example et .env.prod.example sont
des exemples, pas la preuve d’une configuration serveur.

L’architecture suit contrôleur → service métier → manager → repository.
Catalogues YAML/JSON et cartes JSON restent hors base ; l’import est explicite.
Les possessions et recherches sont isolées par participation à un univers.
Les événements persistés dans l’outbox permettent la progression sans navigateur.
Voir [l’exploitation](EXPLOITATION.md) et [les règles](REGLES.md).

## Comptes et courriels

Les nouveaux comptes reçoivent deux messages : bienvenue et confirmation. Seul le lien de confirmation permet d’activer l’accès ; il ouvre une page demandant une confirmation explicite par formulaire POST protégé contre les CSRF. L’ouverture automatique par un scanner de mails ne valide donc pas le compte.

Les jetons aléatoires de 256 bits ne sont stockés que sous forme de hachage SHA-256. Ils expirent après 24 heures, sont à usage unique et un renvoi invalide le précédent. Le formulaire de renvoi utilise une réponse neutre et une limite de cinq demandes par heure et par IP. Le compte reste non vérifié si le transport échoue ; une trace sans adresse ni jeton est journalisée, et le renvoi permet de réessayer.

Les emails de compte disposent de trois tentatives synchrones au total après
exception de transport. L’échec final déclenche une trace critical sans données
sensibles. À l’inscription, un échec de confirmation conserve le compte et
redirige vers le formulaire de renvoi avec un avertissement. Les états de
transport sont journalisés ; aucune file durable ni preuve de réception n’est
fournie par ce mécanisme. Limites et diagnostic : [DIAGNOSTIC_EMAILS.md](DIAGNOSTIC_EMAILS.md).

### Configuration

- `MAILER_DSN` : transport Symfony Mailer. La valeur locale de `.env.example` cible MailHog ; elle ne prouve aucun transport en production.
- `MAILER_FROM` : expéditeur autorisé par le fournisseur. La valeur de repli `no-reply@genesis-reborn.local` est réservée au développement.
- `DEFAULT_URI` : URL publique complète du site, en HTTPS sur le serveur. Les liens de confirmation utilisent cette valeur pour éviter de reprendre un nom d’hôte fourni par une requête.

Le fournisseur, l’expéditeur réel, la délivrabilité et les URL de l’Alpha restent à vérifier sur le serveur. Aucun secret n’est versionné et aucun envoi externe n’a été déclenché par les tests.

Le compte artificiel créé par `make seed-demo`, commande réservée au développement local, est marqué vérifié sans envoi de mail. Cette préparation ne concerne pas les inscriptions par l’interface.
## Migration des comptes et réinitialisation

La migration `Version20261106130000` ajoute `player.email_verified_at` et la table de jetons. Elle marque les comptes déjà présents comme vérifiés afin de conserver leur accès. Les nouveaux comptes ont une date nulle jusqu’à confirmation. La migration a été autorisée pour les bases locales de test ; son application serveur reste une opération à planifier.

Le retour arrière supprime la table des jetons et la colonne. Il perd les statuts de confirmation et doit être accompagné du retour à la version précédente du code. Ne pas appliquer ce retour sur une base active sans sauvegarde et décision d’exploitation.

### Réinitialisation du mot de passe

Le formulaire `/mot-de-passe/oublie` retourne le même message et la même redirection pour une adresse connue ou inconnue. Les demandes sont limitées à cinq par heure et par IP. Pour une adresse connue, un jeton aléatoire de 256 bits est créé ; seul son hachage SHA-256 est conservé, avec expiration après une heure. Une nouvelle demande invalide tous les anciens liens du compte.

Le lien utilise `DEFAULT_URI`, indépendamment de l’hôte de la requête, et l’expéditeur utilise `MAILER_FROM`. Un lien incorrect, expiré ou déjà consommé conduit à l’écran de lien invalide. Le mot de passe n’est changé qu’après validation du formulaire et du jeton, puis tous les jetons du compte sont supprimés. La réinitialisation ne confirme pas l’adresse email d’un nouveau compte.

La demande et la consommation prennent les verrous dans le même ordre : joueur puis jetons. Après verrouillage du joueur, une lecture verrouillée du jeton vérifie son état courant, y compris s’il a été invalidé par une autre connexion depuis la première lecture. Cette lecture ne réutilise pas un ancien instantané de transaction.

Si le transport mail échoue, l’erreur est journalisée sans adresse ni jeton ; le mot de passe reste inchangé et la réponse publique reste neutre. Une nouvelle demande permet de réessayer. Les tests vérifient les réponses HTTP et le contenu des messages, sans prétendre mesurer les délais ni la délivrabilité du serveur Alpha. La configuration SMTP, l’expéditeur et l’URL HTTPS réels restent à vérifier sur ce serveur. Aucune migration supplémentaire n’est nécessaire pour ce parcours.
## Wiki et sources embarquées

Le guide est accessible aux comptes connectés sur `/wiki`, sans participation à
un univers obligatoire. Les liens sont présents près de Feedback et sur la vue
générale de la colonie, destination de l’accueil du jeu.

`docs/wiki.json` est l’unique texte explicatif du guide. Il explique les
mécanismes sans recopier les tableaux économiques, les prix ou les statistiques.
Les références détaillées proviennent directement de `docs/REGLES.md` et
`docs/DECISIONS.md`, consultables sous `/wiki/references/regles` et
`/wiki/references/decisions`. Elles sont affichées comme texte brut échappé ;
aucun HTML ou Markdown actif n’est interprété. Les identifiants de référence
sont limités à ces deux valeurs : aucun chemin fourni par le visiteur n’est lu.

Les références conservent des paragraphes historiques remplacés par des décisions
ultérieures (bonus, courbes et prix communs). Le guide signale ces écarts sans
additionner les anciennes règles aux nouvelles. Les valeurs propres à un univers
restent celles affichées par le jeu ; le wiki ne change aucun équilibrage.

Après une décision de gameplay, relire les rubriques explicatives touchées et les
sources de référence dans le même lot. Ne pas créer une deuxième copie chiffrée
des règles dans une vue Twig. Les paragraphes du guide sont rendus avec
l’échappement normal de Twig ; ne pas ajouter `raw`.

Le contrôleur lit `../docs/` depuis `app/` : montage `/docs` en développement
et copie `/docs` en production. L’image de production copie seulement les trois fichiers de contenu du wiki ;
les exceptions de `.dockerignore` doivent être conservées. Les autres documents
restent exclus du contexte. Une compilation d’image et son déploiement restent
distincts des tests locaux du wiki.

Vérifications : scénario `app/tests/browser/wiki.spec.mjs` (accès anonyme, liens,
sommaire clavier, contenu des sources, référence inconnue, trois largeurs et deux
thèmes), puis six contrôles obligatoires avant publication. Ces contrôles ciblés
ne constituent pas un audit RGAA complet. Aucun déploiement n’est effectué par
l’ajout de cette page.
## Compte à rebours de fermeture

La date `Universe.endsAt` enregistrée en base fait autorité. Le composant apparaît lorsque le statut est actif et qu’il reste **au maximum 24 heures**, sur la carte de l’univers et dans les pages du joueur ayant cet univers sélectionné. Sans date, ou avec une date à plus de 24 heures, il est masqué. Il peut apparaître sans rechargement lorsque le seuil est franchi.

Le rendu initial utilise `ClockInterface`. Le navigateur progresse avec son horloge monotone (`performance.now`), indépendamment de son heure civile et de son fuseau. Les dates échangées portent leur décalage UTC ; l’échéance lisible est explicitement affichée en UTC. Une lecture authentifiée, sans écriture et sans cache, de `/univers/{id}/fermeture` resynchronise au démarrage, chaque minute et au retour dans l’onglet. Un changement de calendrier peut donc prendre jusqu’à une minute à apparaître dans un onglet visible, hors délai réseau. Une suspension du navigateur est rattrapée à son retour. La précision reste limitée par la latence réseau.

À zéro, le temps ne devient pas négatif : « Échéance de fermeture atteinte » est affiché. Ce texte ne prétend pas que le worker a déjà persisté le statut fermé. La fermeture et les contrôles d’accès restent intégralement serveur ; aucune durée ni règle ne change. Un statut serveur non actif masque le compte à rebours. En cas d’échec de lecture, la dernière échéance connue continue de défiler avec une mention d’actualisation indisponible. Sans JavaScript, la valeur initiale reste consultable et un rechargement manuel est indiqué.

Le compteur n’est pas une région vocale actualisée chaque seconde (`role=timer`, `aria-live=off`). Les textes sont explicites et la mise en page s’adapte au mobile. Ces contrôles ciblés ne constituent pas un audit RGAA complet.

## Navigation, files et cartes

Le sélecteur conserve la colonie choisie par participation et la catégorie ouverte.
La vue générale présente les vols de toutes les colonies de l’univers sélectionné.
Bâtiments, recherches et vaisseaux/défenses occupent trois files indépendantes :
progression de l’opération active, nombre de commandes suivantes et durée totale.
L’annulation en cascade suit le remboursement défini dans REGLES et DECISIONS ;
l’ancien guide indiquant une absence de remboursement est obsolète.

Les ressources restent visibles sous la barre de contexte. Sur mobile, leur
défilement horizontal est conservé pendant les actualisations. Les dates viennent
du serveur ; les animations Stimulus ne décident ni des durées ni des résultats.

Les cartes /carte et /carte-v2 utilisent les coordonnées persistées et ne révèlent
que les planètes des systèmes découverts. Les flottes affichées sont celles du joueur.
Textures, nébuleuses, anneaux et lunes de la V2 sont cosmétiques et déterministes.
Les missions rapides réutilisent les contrôles serveur existants. La V2 permet
recherche, zoom, déplacement, mini-carte et transitions ; elle arrête ses animations
hors écran et respecte la réduction des mouvements. La carte classique reste le
repli sans canvas/JavaScript. L’arbre technologique conserve les éléments verrouillés
pour expliquer leurs prérequis, indépendamment du masquage du catalogue.

Les conventions de composants et d’accessibilité sont dans
[le guide UI](ui-ux/GUIDE_INTERFACE.md). Les limites encore ouvertes sont dans
[FEUILLE_DE_ROUTE.md](FEUILLE_DE_ROUTE.md) ; un test automatisé ne clôt pas un audit RGAA complet.

## Vérification ciblée

Depuis la racine :
```sh
make test-browser BROWSER_ARGS="wiki.spec.mjs public-seo.spec.mjs"
make test-browser BROWSER_ARGS="technology-maps-ui.spec.mjs universe-closing.spec.mjs"
```

Les tests utilisent uniquement les comptes et bases de leurs projets Docker
jetables. SMTP, livraison Internet des liens et configuration Alpha demandent
une vérification séparée sur un serveur autorisé.

Le tableau documentaire `docs/CATALOGUE_LABELS.csv` conserve les 150 libellés,
descriptions et utilités de jeu. Les valeurs d’exécution restent celles du catalogue
applicatif ; ce fichier documentaire n’est pas importé dans la base.

## Colonies et journal d’absence — lot C

Planet.acquiredAt est nullable : les quatre transferts de propriété existants
appellent acquireBy dans leurs transactions et gardes de rejeu. Une attribution
inchangée ne redéfinit pas la date. GameRepository::planets trie les seuls résultats
filtrés par propriétaire par acquiredAt puis id (NULL en premier dans MariaDB).
Les autres listes gardent leur ordre existant.

JournalService verrouille PlayerUniverse pour ouvrir ou consulter son journal.
journalStartedAt initialise la reprise sans inventer de visite ; overviewVisitedAt
n’est actualisée que par l’ouverture de la vue générale. Le client colony-live
envoie X-Genesis-Live: 1 afin que sa récupération HTML ne constitue pas une visite.
NotificationService reçoit une catégorie optionnelle : queue, arrival, combat,
transport, spy ou conquest. La catégorie est enregistrée
à la création de la notification. À la première ouverture, JournalService retire
cette seule catégorie des notifications déjà présentes et non disponibles, sans
les supprimer ni définir consultedAt. Les nouvelles notifications alimentent
ensuite les absences. Cette reprise évite une lecture d’état de suivi périmée
dans une transaction métier ancienne et n’ajoute aucun verrou à ces traitements.
Les autres catégories restent conservées hors du journal.

À l’ouverture, les notifications suivies deviennent disponibles dans le journal ;
journalAvailableAt les maintient lors des visites suivantes. L’état consultedAt
n’est défini qu’au POST protégé par CSRF et participation, après vérification
des identifiants disponibles du destinataire. UPDATE conditionnel, verrou de
participation et sélection bornée à 50 rendent les répétitions sans double effet.
Les événements non affichés ne sont pas consultés. Le témoin mobile repose sur
cet état serveur et ne valide plus le journal par un stockage local automatique.

EmpireDetails ne produit que les données des planètes et flottes du propriétaire
dans son univers. Les snapshots de stocks restent calculés en lecture seule.
La migration Version20261112010000 ajoute six colonnes nullables et deux index ;
elle n’effectue aucune datation ni reprise de notification historique.

## Catalogue des destinations — lot D

DestinationCatalogue regroupe les planètes connues de la participation active
selon la diplomatie et la mission. Une relation de propriétaire appartenant à un
autre univers est rejetée, même si le monde se trouve dans l’univers courant.
canPrefill conserve l’accès en lecture aux liens de cibles connues ; allows filtre
les menus et suggestions selon les droits de mission. Le propriétaire actuel
détermine la catégorie d’une ancienne planète PNJ capturée.

GET /flottes/destinations accepte mission et q (facultatif, pseudonyme). Réponse
JSON privée sans cache : groupes friendly/enemies et suggestions limitées à 20.
Recherche littérale insensible à la casse, au moins deux caractères, limitée à
40 caractères. Les planètes inconnues et les autres univers sont filtrés avant
construction des données. Seuls identifiant, nom, propriétaire, coordonnées et
libellé utiles sont transmis ; aucun stock ou détail militaire supplémentaire.

Preview et send passent par resolve : destination_mode=custom conserve le contrat
coordinates historique (mode par défaut pour les anciens clients sans paramètre).
friendly/enemies utilisent leurs identifiants de planète, revérifiés en base avec
catégorie, univers, découverte et mission ; une exploration utilise le système
de la planète sélectionnée. FleetService::quote/send reste l’autorité métier,
avec transactions, reçus, carburant et contrôles existants.

Le contrôleur fleet-destination gère radio-boutons, listes et combobox clavier/
tactile. Il désactive les champs des modes inactifs et ignore les réponses de
recherche devenues obsolètes. Une ancienne liste ne peut pas être rouverte après
changement de mission. Le wizard et l’estimation utilisent le champ actif.
Sans JavaScript, les champs restent accessibles et le mode envoyé est validé
côté serveur. Aucun nouveau schéma ni dépendance.

canAttack autorise maintenant les PNJ sans propriétaire dans le même univers,
avec contrôles de participation, vacances et occupation existants. joinAttack
revérifie l’autorisation sous verrou et initialise la garnison PNJ une seule fois.
capturePlanet continue à ne considérer que les missions conquer ; collectLoot
continue à exclure les planètes sans propriétaire. Aucun coût ou durée modifié.
