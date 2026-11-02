# Phase 04 — comptes et sécurité

État : VALIDÉE le 31/10/2026 après clôture des parcours d’authentification, récupération et préférences.

## Contrôles et fonctionnalités

- Accès : routes authentifiées protégées par ROLE_USER, routes publiques limitées à connexion, inscription, récupération/réinitialisation et santé. Tests de redirection visiteur, permissions et isolation des joueurs.
- Authentification : fournisseur Doctrine par email, hachage Symfony, CSRF connexion/déconnexion POST, limite à cinq tentatives par minute, messages génériques.
- Inscription : validation serveur, email normalisé, unicité en base, mots de passe de 12 à 128 caractères; jeton CSRF invalide ou absent bloque la création.
- Profil : mot de passe actuel vérifié; transaction/verrou, unicité et déconnexion après modification des identifiants.
- Session : cookies HttpOnly, SameSite=Lax, Secure=auto; session invalidée à la déconnexion.
- Récupération : réponse identique pour email connu/inconnu, limite dédiée de cinq demandes par heure et IP, jeton aléatoire à usage unique conservé en SHA-256, expiration à une heure, invalidation après réinitialisation, réponse no-store/no-referrer et erreurs email non divulguées.
- Préférences persistées par compte : fuseau IANA de l’horloge, réduction des animations et activation/désactivation des alertes temps réel. Désactiver le flux live ne supprime pas les notifications persistées. Page /profil/preferences accessible depuis la navigation et le profil. Les effets de mouvement et notifications sont rendus côté serveur; l’heure initiale utilise le fuseau demandé.

## Preuves et validation

- tests/Controller/PasswordResetTest.php : jeton hashé/expiration/usage unique, mise à jour du mot de passe, réponse non énumérante, formulaire HTTP et en-têtes.
- tests/Controller/PreferencesTest.php : persistance, effets d’interface rendus et rejet du fuseau invalide.
- PHPUnit complet le 31/10/2026 : 84 tests / 448 assertions, succès.
- lint:container, YAML (63 fichiers), Twig (24 fichiers), validation Doctrine et git diff --check réussis.
- Migration Version20261101100000 appliquée; 17/17 migrations exécutées.
- npm run build impossible : l’image PHP applicative ne contient pas npm et aucun node_modules/.bin n’est présent dans le workspace. Les sources Stimulus/CSS modifiées seront intégrées au prochain build frontend.

## Limites

Exigences de phase 04 satisfaites au niveau compte et sécurité. Build frontend et vérification navigateur globale reportés aux contrôles transverses; aucun déploiement. Vérification email et MFA ne sont pas exigés par les références de mission.
