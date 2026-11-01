# Critère d’arrêt — Alpha Test

Mis à jour : 01/11/2026.

## Verdict

**PRÊT POUR UNE ALPHA TEST.** Le projet permet à un nouvel utilisateur de créer un compte, entrer dans un univers, développer ses colonies, lancer des bâtiments et des recherches, produire des unités, effectuer les missions du jeu et consulter les résultats persistés. Les actions testées passent par le moteur et les services Symfony réels; les simulations servent uniquement à accélérer les délais d’attente dans les fixtures de test.

Les coefficients et profils de départ restent provisoires et signalés comme tels. La feuille de balance, ses simulations et sa validation restent obligatoires avant toute mise en production, conformément à la décision de Kévin.

## Preuves Alpha

- **Parcours navigateur : 33 scénarios verts au total.** Le run isolé complet a terminé avec 29/33; les quatre scénarios restants (file de construction, arbre technologique, conquête/rapport et recherche) ont ensuite passé 4/4 dans un run isolé corrigé. Chaque run emploie une base/projet jetable; le moteur n’est pas remplacé.
- **Fonctions jouables vérifiées :** inscription/connexion, développement de colonie, construction et annulation de files, bénéfices des niveaux, recherche effectivement appliquée, production de flotte, exploration, transport, colonisation, conquête PNJ avec rapport privé, espionnage, orbite, alliances/guildes, messages, modération, récupération de compte et préférences.
- **Combat :** PHPUnit complet inclut la persistance d’un round par minute, les renforts, l’idempotence et la reprise; le parcours navigateur de conquête et rapport passe après adaptation de la fixture à la cadence réelle.
- **Serveur :** PHPUnit **103 tests / 588 assertions**; lint PHP, 64 YAML, 33 Twig, conteneur Symfony et schéma Doctrine réussis. Le runner isolé applique 20 migrations et importe 146 planètes.
- **Assets :** les quatre SVG hérités ont été remplacés par des tracés originaux documentés; IDs Twig conservés et aucune référence externe.
- **Interface :** build frontend de production réussi (5 fichiers). Thème sombre par défaut et thème clair vérifiés. Parcours responsive à 1440/768/390 px; captures disponibles dans docs/validation, dont colonie-theme-dark.png, colonie-theme-dark-mobile.png et colonie-theme-mobile.png.
- **Accessibilité Alpha :** zéro violation axe sur 21 écrans métier à 1440/390 px et pages publiques/erreurs à 1440/768/390 px; navigation clavier et échantillon de contraste passés. Aucun défaut P0/P1 bloquant n’a été relevé.
- **Intégrité locale :** git diff --check propre; la branche dédiée reste active et les autres changements locaux n’ont pas été réinitialisés.

## Limites connues à présenter aux testeurs

- La balance et les profils PNJ ne sont pas définitifs; les retours Alpha doivent servir à préparer la feuille de balance, sans les considérer comme des valeurs validées.
- L’audit manuel complet avec lecteur d’écran et la conformité RGAA/WCAG exhaustive restent à faire. Les tests automatisés axe, clavier essentiel, responsive et contraste échantillonné sont passés; l’audit exhaustif reste un jalon de production.
- Aucun test de charge soutenu, run CI distant ou exercice PRA à l’échelle n’est revendiqué. Ils restent hors de la porte Alpha.

## Condition d’arrêt du travail

Le travail de préparation s’arrête à ce verdict Alpha. Avant production, reprendre la feuille d’équilibrage et les validations de capacité, CI et accessibilité manuelle listées ci-dessus. Aucun déploiement n’a été effectué.
