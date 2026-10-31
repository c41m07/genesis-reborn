# Phase 04 — comptes et sécurité

État : TERMINÉE le 31/10/2026.

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

L’audit valide les parcours spécifiés et les contrôles présents. Il n’ajoute pas de vérification d’adresse email, récupération de mot de passe ou authentification multifacteur, qui ne sont pas définies dans les spécifications actuelles. L’audit final sécurité, accessibilité et charge demeure en phase 13/15.
