# Audit de phase 07 — bâtiments et technologies

État : **terminée** — 31/10/2026
Avancement : **7/15 phases clôturées**; phase 08 active.

## Périmètre contrôlé

- Les prérequis, effets économiques, files de bâtiments et de recherche ainsi que les annulations étaient déjà couverts par les services et tests existants.
- Le catalogue déclarait Tir de précision avec un effet stat_bonus, cible all_ships, formule flat, de 5 points par niveau. La résolution de combat utilisait jusque-là les statistiques brutes du catalogue de vaisseaux.
- Les recherches de joueur sont lues par inscription d’univers au moment de la résolution. Chaque flotte reçoit uniquement les niveaux de sa propre inscription.
- Les niveaux de recherche définis dans le préréglage PNJ sont transmis au même calculateur. Les préréglages actuels ne donnent pas de niveau positif de Tir de précision.
- Les défenses statiques ne sont pas dans la cible all_ships. Le rapport conserve les statistiques effectives et les niveaux appliqués afin de permettre la reconstitution du combat.

## Validation

- CombatEventTest::testCombatSnapshotsApplyEachMembershipTechnologyWithoutChangingPlanetDefenses vérifie une précision de 80 pour l’attaquant (base 70, niveau 2), une précision de 75 pour le défenseur (niveau 1) et une tourelle inchangée à 65.
- make test : **72 tests / 363 assertions**.
- make lint : PHP valide, 62 fichiers YAML, 17 fichiers Twig, conteneur Symfony et schéma Doctrine valides.
- make validate : configuration Docker Compose valide.

## Limites maintenues

- Le catalogue ne déclare actuellement qu’un seul effet de combat stat_bonus. Les formules autres que flat ne sont pas prises en charge par le calculateur.
- Les valeurs marquées provisoires dans les catalogues restent à équilibrer. Cette clôture vérifie l’application de l’effet déclaré, pas son équilibrage final.
