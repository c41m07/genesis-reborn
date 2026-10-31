# Phase 04 — comptes et sécurité

État : PARTIELLE au regard du prompt initial; les parcours présents sont techniquement vérifiés, mais la récupération de compte et les préférences utilisateur restent absentes.

## Contrôles revus

- Accès : toutes les routes Symfony sont protégées par ROLE_USER, à l’exception de la connexion, de l’inscription et du contrôle de santé. Les tests couvrent la redirection des visiteurs non authentifiés et l’isolation entre joueurs.
- Authentification : fournisseur Doctrine sur adresse email, hachage de mot de passe configuré avec l’algorithme recommandé par Symfony, formulaire de connexion protégé par CSRF, déconnexion POST avec CSRF, limitation à cinq tentatives par minute et message d’échec générique.
- Inscription : validation côté serveur, courriel normalisé en minuscules, contraintes d’unicité en base et de 12 à 128 caractères pour les mots de passe. L’échec d’un jeton CSRF manquant empêche la création du compte.
- Modification de profil : saisie obligatoire du mot de passe actuel, vérification avant modification, transaction/verrou sur le compte et déconnexion après sauvegarde; tests de mauvais mot de passe et de sauvegarde.
- Session : cookies configurés HttpOnly, SameSite=Lax et Secure=auto; Symfony gère la session de sécurité et la déconnexion invalide la session applicative.

## Validation

- Ajout de tests qui vérifient le rejet d’un jeton CSRF de connexion invalide et l’absence de création de compte lorsque le jeton d’inscription manque.
- make test réussi : 69 tests / 339 assertions (PHPUnit 13.4.3, PHP 8.4.26).
- make lint réussi : PHP, 62 fichiers YAML, 17 templates Twig, conteneur Symfony et schéma Doctrine.
- make validate réussi : configuration Docker Compose valide.

## Limites de périmètre

Le prompt de mission (§5.1) demande explicitement la récupération de mot de passe et les préférences utilisateur; aucun flux de récupération ni écran/service de préférences n’a été trouvé lors du contrôle croisé du 31/10. La phase 04 reste donc partielle jusqu’à conception et implémentation testée de ces fonctions. Vérification d’adresse email et MFA ne figurent pas comme exigences explicites du prompt et ne sont pas incluses dans ce constat.
