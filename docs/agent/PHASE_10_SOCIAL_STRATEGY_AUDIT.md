# Phase 10 — audit social et stratégique

État au 31/10/2026 : terminée techniquement. Le classement, la messagerie privée et le canal de guilde sont implémentés; guildes, alliances et notifications existantes réauditées. Phase 11 active.

## Classement
- Périmètre isolé par univers; pages HTML et API JSON.
- Total = points de recherche + flotte + bâtiments. Chaque sous-total vaut la somme des coûts catalogue des entités actuellement détenues, divisée par 100 (partie entière).
- Les pertes font baisser le score. Vaisseaux et défenses fixes sont comptés dans la catégorie flotte, avec leurs décomptes exposés séparément. Les défenses ne sont pas des bâtiments.
- Les points représentent la valeur catalogue des actifs présents; ce calcul ne constitue pas un historique comptable exact des ressources effectivement dépensées ou récupérées.

## Communications et protections
- Messages privés limités aux joueurs du même univers; seuls l’expéditeur et le destinataire peuvent consulter le fil. Les messages reçus sont marqués comme lus et déclenchent une notification.
- Canal de guilde accessible aux membres de la guilde dans le même univers; notifications adressées aux autres membres.
- Limite de 10 envois par minute et par compte, contenu limité à 2 000 caractères, formulaires protégés par CSRF, rendu échappé par Twig.
- Contrôles couvrant score et perte d’actifs, isolement des conversations, lecture, adhésion à la guilde, notification, réponses HTTP/JSON et refus d’accès.

## Validation
- make test : 75 tests, 384 assertions.
- make lint : PHP, YAML, Twig, conteneur Symfony et schéma Doctrine valides.
- make validate : configuration Compose valide.
- Migration Version20261031210000 appliquée; schéma synchronisé.

## Limites
- La valeur catalogue reflète les actifs encore détenus et non les dépenses historiques réelles.
- Aucune fonction de blocage, suppression ou modération des messages n’est incluse; ces contrôles relèvent de la phase administration.
- Aucune déclaration de conformité RGAA/WCAG ou de préparation à la production n’est faite.

## Réaudit Work V4 — 31/10/2026

- Revue actuelle du classement (scores par univers et valeur des actifs détenus), relations guildes/alliances et contrôles de messagerie privée/chat de guilde. Limites d’accès et permissions vérifiées dans les services et routes; écriture HTML échappée par Twig, CSRF et limite de débit en place.
- Exécution ciblée GamePersistenceTest pour classement, messages privés, chat, modération et alliances/guildes : 11 tests / 44 assertions, succès. Suite complète actuelle : 84 tests / 448 assertions.
- Les messages restent strictement entre joueurs du même univers; chat limité aux membres et notifications aux autres membres. Le classement est une estimation de valeur catalogue des actifs détenus, pas une comptabilité historique.
- Les contrôles de suppression/modération plus larges relèvent de la phase 11. Aucun verdict de conformité accessibilité ou de préparation production n’est émis ici.
