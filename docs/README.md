# Documentation Genesis Reborn


[Recette manuelle des évolutions A à K](RECETTE_EVOLUTIONS_INTERFACES.md) — parcours à essayer sur dev avant les retouches finales.

Lire le README du dépôt pour installer et démarrer le jeu. Cet index donne
une seule référence par sujet ; les anciens bilans ne décrivent pas l’état courant.

| Besoin | Référence |
| --- | --- |
| Comprendre les règles actuelles | [REGLES](REGLES.md) |
| Comprendre la détection des sondes et les essais Alpha | [Espionnage](REGLES.md#espionnage) |
| Retrouver un arbitrage produit | [DECISIONS](DECISIONS.md) |
| Utiliser les guildes, les relations personnelles, les traités et les projets collectifs | [Guide guildes et alliances](GUIDE_GUILDES_ALLIANCES.md) |
| Retrouver la revue, la fusion et les validations du chantier guildes | [Suivi guildes et alliances](GESTION_GUILDES_ALLIANCES.md) |
| Préparer la refonte de la console et ses récompenses | [Cadrage de l’administration, brouillon](REFONTE_ADMINISTRATION.md) |
| Suivre les évolutions des interfaces et flottes | [Lots et décisions ouvertes](EVOLUTIONS_INTERFACES_FLOTTES.md) |
| Consulter le lot parcours UI/UX et ses validations locales | [Suivi des parcours](PARCOURS_UI_UX.md) |
| Consulter les profils et personnaliser une guilde | [Profils et présentations](PROFILS_JOUEURS_GUILDES.md) |
| Utiliser le simulateur par dix combats, les compositions et la recherche avancée | [Cadrage des outils stratégiques](CADRAGE_OUTILS_STRATEGIQUES.md) |
| Voir les objectifs et vérifications restantes | [ROADMAP](FEUILLE_DE_ROUTE.md) |
| Comprendre les dossiers, .env, comptes, wiki et interfaces | [ARCHITECTURE](ARCHITECTURE.md) |
| Installer et livrer sur le serveur | [DEPLOIEMENT](DEPLOIEMENT.md) |
| Sauvegarder, contrôler et restaurer | [BACKUPS](SAUVEGARDES.md) |
| Diagnostiquer processus, outbox, alertes et mesures | [EXPLOITATION](EXPLOITATION.md) |
| Comprendre le worker, le notifier et les garanties de livraison | [Worker et notifier](WORKER_NOTIFIER.md) |
| Régler le référencement et Search Console | [SEO](REFERENCEMENT.md) |
| Consulter les profils de combat, production et vitesse | [EQUILIBRAGE](EQUILIBRAGE.md) |
| Consulter les 150 profils de coûts | [Grille commune](COUTS.md) |
| Comprendre les déblocages par rang | [Déblocages initiaux](DEBLOCAGES.md) |
| Modifier l’interface | [Guide UI](ui-ux/GUIDE_INTERFACE.md) |
| Recréer une illustration | [Prompts](ILLUSTRATIONS.md) |
| Consulter une preuve ou décision historique | [Archives et liste de consolidation](archives/README.md) |

`wiki.json` reste le contenu unique du wiki. `CATALOGUE_LABELS.csv` conserve les
150 libellés, descriptions et utilités de jeu ; la copie Markdown a été retirée.
Les spécifications textuelles et PDF d’origine restent dans specifications/ et technic/.

Les regroupements et retraits de l’ancienne documentation sont expliqués dans
l’index des archives. Le guide du worker et du notifier complète les procédures
d’exploitation par une description technique de la chaîne asynchrone.


## Noms des documents

Utiliser des noms français en MAJUSCULES_AVEC_UNDERSCORES.md, sans accents
ni espaces. Les guides actifs portent un nom stable sans date ; les archives
conservent les dates déjà présentes dans leurs noms. README.md et AGENTS.md gardent leur nom
standard. REGLES.md et DECISIONS.md conservent les noms chargés par le wiki.
Le suivi des chantiers Alpha porte le nom SUIVI_ALPHA.md ; ses notes locales
sont conservées.

- [Étude du coût électrique des opérations](ETUDE_COUT_ELECTRIQUE.md) : coût de 100 E par minute entamée validé, hypothèses et effets des lots, rangs et vitesses.
