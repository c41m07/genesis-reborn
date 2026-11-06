# Mails de compte : création et réinitialisation

Les nouveaux comptes reçoivent deux messages : bienvenue et confirmation. Seul le lien de confirmation permet d’activer l’accès ; il ouvre une page demandant une confirmation explicite par formulaire POST protégé contre les CSRF. L’ouverture automatique par un scanner de mails ne valide donc pas le compte.

Les jetons aléatoires de 256 bits ne sont stockés que sous forme de hachage SHA-256. Ils expirent après 24 heures, sont à usage unique et un renvoi invalide le précédent. Le formulaire de renvoi utilise une réponse neutre et une limite de cinq demandes par heure et par IP. Le compte reste non vérifié si le transport échoue ; une trace sans adresse ni jeton est journalisée, et le renvoi permet de réessayer.

## Configuration

- `MAILER_DSN` : transport Symfony Mailer. La valeur locale de `.env.example` cible MailHog ; elle ne prouve aucun transport en production.
- `MAILER_FROM` : expéditeur autorisé par le fournisseur. La valeur de repli `no-reply@genesis-reborn.local` est réservée au développement.
- `DEFAULT_URI` : URL publique complète du site, en HTTPS sur le serveur. Les liens de confirmation utilisent cette valeur pour éviter de reprendre un nom d’hôte fourni par une requête.

Le fournisseur, l’expéditeur réel, la délivrabilité et les URL de l’Alpha restent à vérifier sur le serveur. Aucun secret n’est versionné et aucun envoi externe n’a été déclenché par les tests.

Le compte artificiel créé par `make seed-demo`, commande réservée au développement local, est marqué vérifié sans envoi de mail. Cette préparation ne concerne pas les inscriptions par l’interface.

## Migration et retour arrière

La migration `Version20261106130000` ajoute `player.email_verified_at` et la table de jetons. Elle marque les comptes déjà présents comme vérifiés afin de conserver leur accès. Les nouveaux comptes ont une date nulle jusqu’à confirmation. La migration a été autorisée pour les bases locales de test ; son application serveur reste une opération à planifier.

Le retour arrière supprime la table des jetons et la colonne. Il perd les statuts de confirmation et doit être accompagné du retour à la version précédente du code. Ne pas appliquer ce retour sur une base active sans sauvegarde et décision d’exploitation.

## Réinitialisation du mot de passe

Le formulaire `/mot-de-passe/oublie` retourne le même message et la même redirection pour une adresse connue ou inconnue. Les demandes sont limitées à cinq par heure et par IP. Pour une adresse connue, un jeton aléatoire de 256 bits est créé ; seul son hachage SHA-256 est conservé, avec expiration après une heure. Une nouvelle demande invalide tous les anciens liens du compte.

Le lien utilise `DEFAULT_URI`, indépendamment de l’hôte de la requête, et l’expéditeur utilise `MAILER_FROM`. Un lien incorrect, expiré ou déjà consommé conduit à l’écran de lien invalide. Le mot de passe n’est changé qu’après validation du formulaire et du jeton, puis tous les jetons du compte sont supprimés. La réinitialisation ne confirme pas l’adresse email d’un nouveau compte.

La demande et la consommation prennent les verrous dans le même ordre : joueur puis jetons. Après verrouillage du joueur, une lecture verrouillée du jeton vérifie son état courant, y compris s’il a été invalidé par une autre connexion depuis la première lecture. Cette lecture ne réutilise pas un ancien instantané de transaction.

Si le transport mail échoue, l’erreur est journalisée sans adresse ni jeton ; le mot de passe reste inchangé et la réponse publique reste neutre. Une nouvelle demande permet de réessayer. Les tests vérifient les réponses HTTP et le contenu des messages, sans prétendre mesurer les délais ni la délivrabilité du serveur Alpha. La configuration SMTP, l’expéditeur et l’URL HTTPS réels restent à vérifier sur ce serveur. Aucune migration supplémentaire n’est nécessaire pour ce parcours.
