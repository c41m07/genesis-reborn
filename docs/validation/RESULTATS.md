# Résultats — finalisation des domaines et refonte UI

21 octobre 2026. Bilan détaillé et limites : [LIVRAISON-DOMAINES-UI.md](../LIVRAISON-DOMAINES-UI.md).

- PHPUnit : **47 tests / 168 assertions réussis**, MariaDB isolée.
- Chromium : **5 parcours réussis**, y compris missions complètes et diplomatie à deux comptes. Navigation latérale native, retrait des onglets/inventaire et ressources fixes au défilement vérifiés sur ordinateur et mobile.
- Build Docker, validation Compose, démarrage et healthchecks : réussis.
- Lint PHP, 59 YAML, 12 Twig, conteneur et schéma : réussis.
- Composer strict et compilation Encore : réussis.
- Installation vierge isolée, migrations et persistance après arrêt/redémarrage : réussies.
- MariaDB hôte 3309 authentifiée : SELECT 1 réussi ; 4 migrations appliquées.
- HTTP Traefik 200 ; fichiers privés .env/.git refusés.
- Captures ordinateur/mobile inspectées ; document à 390 px sans débordement horizontal.

Aucun test de combat, de conquête ou de fermeture asynchrone revendiqué : ces fonctionnalités restent à développer. Capacité des réservoirs et autres paramètres `provisional` restent des valeurs de démonstration.

Évolution multi-colonies : [détails](../UI-MULTI-COLONIES.md). Sélecteur, mémorisation, restriction de propriété, file complète et confidentialité des vols testés en HTTP. File +1, progression et annulation validées dans Chromium.
