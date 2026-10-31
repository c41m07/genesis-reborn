# Audit de phase 09 — combat, pertes et rapports

État : **terminée** — 31/10/2026
Avancement : **9/15 phases clôturées**; phase 10 active.

## Critères vérifiés

- Simulation déterministe : un même instantané et une même graine produisent les mêmes rounds, tirs, dégâts, survivants et pertes.
- Résolution persistée : événements et participants sont créés par fenêtre; les arrivées, camps et rounds d’entrée sont figés. Une seconde résolution ne rejoue pas les pertes, butins ni notifications.
- Règles de round : unités arrivées tardivement, destruction avant le tour, limites de chance de toucher et ciblage des défenses statiques sont couverts par CombatSimulatorTest.
- Pertes : les quantités survivantes sont enregistrées pour les flottes, les garnisons PNJ et les défenses. Les pertes PNJ demeurent après une conquête échouée.
- Butin : victoire des attaquants requise; protection de 10 % de stockage par ressource, partage selon les soutes encore libres, réservation du carburant de retour et dépôt soumis aux capacités vérifiés.
- Conquête JcE : victoire avec un vaisseau colonie survivant; le vaisseau est consommé et les bâtiments/ressources acquis restent sur place. La conquête JcJ reste exclue selon la décision de Kévin.
- Rapports : journal des rounds, instantané effectif, pertes, butin et capture persistés. Lecture réservée aux participants et au propriétaire de la planète; un spectateur non autorisé est refusé.
- Notifications : attaque signalée à la cible; notification de fin envoyée aux participants une seule fois. La panne de publication Mercure ne bloque pas la transaction de jeu.
- Les bonus technologiques appliqués en phase 07 sont conservés dans l’instantané de combat.

## Validation

- CombatSimulatorTest : déterminisme, arrivées tardives, unité détruite avant son tour, bornes de précision, ciblage des défenses et statistiques invalides.
- CombatEventTest : fenêtre commune, résolution répétée, accès aux rapports, notifications de fin exactement une fois, butin et conquêtes PNJ.
- GamePersistenceTest : outbox de notification, panne de Mercure isolée et missions d’espionnage PNJ.
- make test : **72 tests / 365 assertions**.
- make lint : PHP, 62 YAML, 17 Twig, conteneur Symfony et schéma Doctrine valides.
- make validate : configuration Docker Compose valide.

## Limites conservées

Les compositions PNJ, statistiques et coefficients signalés provisoires ne sont pas équilibrés pour la production. La phase clôt les comportements techniques vérifiés; l’équilibrage et les valeurs finales restent des critères bloquants pour l’audit de production.
