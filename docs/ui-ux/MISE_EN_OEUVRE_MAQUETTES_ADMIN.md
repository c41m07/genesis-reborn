# Mise en œuvre des maquettes Admin

Base de travail : dev après le calendrier de l’Avent (#134). Les planches de référence sont celles du dossier 05 de l’archive validée. Elles utilisent des valeurs fictives ; la console conserve ses objets, valeurs, états et groupes de navigation réels. Le lot ne change ni contrôleur, droits, périmètre d’univers, routage, données, ni règle. Conserver à la fois le menu Animation et l’aperçu Avent.

| Planches | Destination | Mise en œuvre ou écart volontaire |
| --- | --- | --- |
| 00, 01, 02 | Univers → Catalogue, quatre familles, recherche | Table A à quatre colonnes, noms et rangs réels, navigation et filtre natifs. En étroit, chaque ligne se lit verticalement avec la même action. Le rail de consultation de 02, introduit par le correctif détaillé ci-dessous, conserve la navigation native vers la fiche. |
| 03, 04, 12 | Fiche catalogue, devis, historique, restauration | Comparaison Actuel/Proposé et confirmation en regard sur grand écran, dans l’ordre naturel en étroit ; trois panneaux de rang, champs réels, motif et contrôles du serveur. La référence 12 est simplifiée : les champs inactifs/variables, la révision, l’aperçu indépendant, et la seconde décision de restauration restent distincts en jeu. |
| 05, 06 | Accueil, joueurs, guildes, modération, feedback, dossiers | Entête et contexte partagés. Les vues d’annuaire, de filtre et de détail restent natives ; pas de commandes ajoutées ou répliquées dans un autre écran. |
| 07, 08 | Univers, mesures, économie, surveillance, missions, transports et rapports | Entête commun, lisibilité des tableaux existants ; ne pas fusionner des données distinctes ni remplacer les signaux et l’UTC par des états déduits. Les cinq rubriques actuelles restent identifiables dans la navigation. |
| 09 | Événements et aperçus, Avent, défis, modèles de guilde/rotations, patchnotes | Repérage du shell ; les écrans de prévisualisation et de publication restent séparés et n’ajoutent pas de droit ou de mutation. |
| 10 | Exploitation, sécurité, journal, permissions | En-têtes et alternance lisible des lignes des tables existantes ; le diagnostic demeure consultatif. Un rôle partiel aperçu sur une maquette ne serait pas un nouveau droit. |
| 11, 13 | Confirmation de suppression, restauration, mobile et clair | Confirmation de patchnote : retour non destructif en premier, conséquence et commande irréversible dans le même panneau. Hors de cette page et de la restauration, aucune variante n’ajoute de confirmation à un flux qui en a déjà une. Le refus d’accès reste indépendant du shell administrateur. |

## Recette reproductible

Captures d’application séparées des planches statiques. Dimensions CSS attendues : 1920 × 1080 (capture du viewport, échelle CSS 1), 1440 × 900, 768 × 900 et 390 × 900. Vérifier le thème sombre et le clair, les titres et actions visibles, les tableaux sémantiques, l’absence de débordement horizontal de la page et le parcours au clavier. Les recettes navigateur du catalogue et des patchnotes ajoutent ces contrôles sans remplacer les validations existantes (refus, annulation, vrais enregistrements sur base jetable, sauvegarde et retour natifs sans JS). Sur la restauration, l’annulation doit laisser les réglages intacts. Les autres écrans sont couverts par leurs parcours existants ou revus structurellement ; ne pas présenter cette liste de conception comme une exécution exhaustive de toutes les routes et variantes de rôles.

Les priorités suivantes ne sont pas des fonctionnalités livrées : reclassification des destinations métier, simulation d’autorisation partielle ou relance des opérations depuis un tableau. Les points qui modifient les commandes ou droits nécessitent un brief indépendant. Aucun export d’administration, compte réel, publication ni déploiement n’est requis pour la recette.

## Corrections après comparaison des 14 planches

La présentation du premier lot est précisée ci-dessous. Les modifications restent réservées à l’interface : même périmètre administrateur, aucun nouveau rôle, paiement, route, journal ou changement d’état. Les deux sélecteurs d’affichage utilisent des liens natifs, sans JavaScript obligatoire.

| Référence | Correction et écart conservé |
| --- | --- |
| A00–A01 | La planche A00 reste une référence, pas un écran à reproduire. Le menu reçoit les repères visuels Poste de contrôle, Personnes, Plateforme, Contenu et Exploitation sans changer les liens existants. La liste garde les deux niveaux de navigation réellement nécessaires et réduit les espacements au-dessus de la table. Les familles et rangs affichent leurs vrais libellés. |
| A02 | Le nom de fiche ouvre un rail en lecture seule ; Modifier reste un lien direct. Aperçu, historique et fermeture gardent l’univers courant. Le rail est limité aux résultats filtrés déjà chargés ; aucune lecture des champs/brouillons supplémentaires. |
| A03–A04 | La même confirmation propose une présentation linéaire et une côte à côte. Devis et champs historiques sont dans un tableau unique à groupes, sans retirer de valeur. Nom de fiche réel et badge « Aperçu non publié ». Sur mobile, la décision vient après la comparaison dans les deux variantes. |
| A05 | Bandeau de l’accueil et de la fiche joueur, état du compte, compteur existant sur liste, vrai contexte de guilde en trois repères et lecture seule explicite. Aucune commande de guilde ni nouveau niveau d’accès. |
| A06 | Les deux familles de signalements sont en regard au large et dans le flux en étroit ; le statut d’un dossier et l’absence de capture d’un feedback sont visibles. La note, l’archivage et les décisions conservent leurs flux. |
| A07 | Liste et règle de fermeture, identité/carte à la création, repère horodaté des mesures et séparation de la zone sensible ; les états fermés, hors plage ou indisponibles reprennent leurs données existantes, sans déduire des mesures manquantes. |
| A08 | L’historique distingue trace complète et ancienne trace consultative. Les limites des boîtes de ressources et des observations de mission sont des repères visibles. Transports et rapports combat/renseignement en consultation admin reprennent leur contexte ; aucune donnée de départ n’est reconstituée. |
| A09 | Aperçu événement et confirmation distincts, badge non publié, modèle de rotation versus occurrence, palier côté projet, écriture de patchnote séparée de la publication. Avent, déjà livré, conserve son menu et ses commandes. |
| A10 | Lecture seule explicitée sur opérations/relais/permissions et repères propres pour incidents et audit. Les valeurs d’état et filtres demeurent ceux du serveur ; un diagnostic ne donne pas le droit de relancer. |
| A11/A13 | Zone de suppression univers et retrait d’événement isolés visuellement ; univers ciblé et sortie native avant action irréversible. Les confirmations natives et sécurités existantes restent la source de vérité. L’aperçu légal conserve sa lecture privée et son avertissement. L’écran de refus se trouve dans le lot public. |
| A12 | Rangs avant propriétés générales. Accès direct au bloc de validation sans soumettre ; un enregistrement complet ne tient pas toujours dans le premier écran car les champs réels dépassent ceux de l’exemple. Aucun coût, délai par rang ou cas fictif ajouté. |

Les 14 références sont des scénarios visuels. Aucun compte, rapport, signalement ou relevé de production ne doit servir de fixture ; les cas destructifs vérifient la présence de la vraie validation et le retour non destructif. Une comparaison graphique des états dépendants de données ou d’un rôle doit rester « non vérifiée » lorsqu’elle n’a pas été rejouée sur données synthétiques.
