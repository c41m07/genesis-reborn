# Audit de phase 08 — flottes et missions

> Instantané historique du 31 octobre 2026 : les compteurs de phase et validations datés ci-dessous décrivent cet audit. L’état courant est dans [TEST_STATUS.md](TEST_STATUS.md) et [ALPHA_READINESS.md](ALPHA_READINESS.md).

État à la date du rapport : **terminée**. Avancement historique : **8/15 phases clôturées**; phase 09 alors active.

## Parcours vérifiés dans le code et les tests

- Calcul de distance, durée, vitesse et consommation aller-retour : MovementCalculatorTest couvre l’exemple de référence, la symétrie, les deux politiques de carburant, l’effet de la vitesse, les distances intergalactiques rejetées, les réservoirs dédiés et la limite entre carburant et fret.
- Envoi transactionnel : FleetService réserve les ressources et vaisseaux, applique une clé d’idempotence et planifie une arrivée via l’outbox.
- Exploration : une sonde découvre tout le système; consommation de la sonde, réservation du carburant, absence de double exécution et idempotence de l’envoi sont couvertes.
- Colonisation : validation du monde vierge, livraison du kit initial et consommation du vaisseau colonie sont vérifiées. Les planètes PNJ sont réservées à la conquête.
- Transport : fret livré à une colonie du même joueur, hydrogène de retour réservé et retour à domicile sans double débit.
- Espionnage : règles de composition et de cible, consommation des sondes, confidentialité, détection, alertes et garnison PNJ sont couverts.
- Attaque, conquête, défense et orbite : CombatEventTest vérifie la fenêtre de combat commune, l’arrivée des renforts, les retours après stationnement, la capture PNJ et la conservation des pertes après échec; les parcours d’orbite avec acceptation et décision manuelle sont également vérifiés.
- Les tokens périmés et les doubles appels d’arrivée n’exécutent pas deux fois les missions; les tests associés vérifient la découverte, les rapports et les mouvements concernés.

## Validation

- La suite complète de phase 07 couvre aussi l’ensemble des tests de missions : make test a réussi avec **72 tests / 363 assertions**.
- make lint et make validate sont réussis après les derniers changements de code.

## Limites maintenues

- Les voyages intergalactiques sont refusés, car leur règle n’est pas spécifiée dans les sources.
- Les capacités de réservoir, coefficients de carburant et durées de voyage signalés provisoires ne sont pas déclarés équilibrés.
- Les écrans navigateur d’espionnage n’ont pas été relancés depuis son ajout; cette limite est conservée dans TEST_STATUS.md.

## Réaudit Work V4 — 31/10/2026

- Réexamen du calcul de distance, durée, vitesse, carburant/fret, envoi idempotent, réservations, retours, missions et règles de cible. Les missions intergalactiques restent explicitement refusées tant que leur règle n’est pas définie.
- Tests ciblés relancés : MovementCalculatorTest 6 / 20; CombatEventTest 7 / 110; huit tests d’intégration exploration/transport/colonisation/espionnage 8 / 58. Tous passent. GamePersistenceTest (41 / 187) et la suite globale (84 / 448) ont également passé.
- Les scénarios navigateur restent à exécuter avec la campagne UI des phases 12–13, particulièrement le parcours espionnage. Paramètres de carburant, capacité et durées restent provisoires selon catalogues/règles.
