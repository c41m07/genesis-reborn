# Recette de fidélité : Vue générale, Flottes, Galaxie

Point de départ : fusion `411f699f` et dossier de référence `GENESIS_REBORN_MAQUETTES_AUDIT.rar`. Les quantités, les coordonnées, les titres synthétiques et les noms de démonstration ne sont pas des valeurs à introduire dans le jeu.

| Planche | Décision de présentation et état à comparer |
|---|---|
| 01 Flottes · Console de navigation | Garder la table commune aux trois rangs, les stocks, Max et le récapitulatif sans envoi. Vérifier une composition remplie, le repli natif et la saisie au toucher. |
| 01 Galaxie · Table cartographique | Conserver la fiche, la mini-carte et le journal. Les commandes restent hors de la surface interactive ; c'est intentionnel. Vérifier carte avec deux points choisis successivement, fiche, entrée distincte et légende. |
| 01 Vue générale · Poste de commandement | Densifier les lignes en bureau sans retirer propriété, trajet, état, barre du temps ni rappel. Le Journal conserve un vrai titre avec accès aux rapports, ses notifications et son comportement de consultation. Vérifier avec activité et mouvements ; si la liste est longue, on fait défiler sans promettre que tout tiendra. |
| 06-01 · Destination | Pas de cartes de mission : le sélecteur natif reste l'unique source pour les missions, validations et devis. Les modes de destination gardent leurs contrôles réels ; une colonie possédée et des coordonnées manuelles ne sont pas le même état. |
| 06-02 · Confirmation | Présenter les quantités par unité et rang, le fret sous forme de tuiles des champs réellement transmis et le devis dans le rail. Ne pas répéter dans le rail la composition et le trajet : le bouton d'envoi doit rester visible en 1080 sur l'exemple comparable. L'envoi natif, la possibilité de réviser et l'estimation de secours demeurent. |
| 06-03 · Missions, orbites, quai et rappel | La planche juxtapose des parcours. Conserver des routes et autorisations indépendantes, sans ajouter de fret visible à un tiers. Rapprocher le raccourci de préparation du suivi des missions, en dehors de son fragment actualisé. |
| 06-04 · Compositions | Liste et états créer/modifier/charger/supprimer séparés. Le modèle n'a pas de date de mise à jour consultable ; pas de colonne fictive, ni de date déduite de la révision. Le chargement reste distinct de l'envoi ; garder le total et les vraies quantités disponibles/manquantes. |
| 06-05 · Galaxie, liste et système | Filtre de liste dans le même bandeau que les sélecteurs, avec la table native utilisable sans JavaScript. Ouvrir la fiche sur la carte ne signifie ni entrer dans le système ni zoomer. Les états connus/inconnus et le Hall soumis à disponibilité restent distincts. |
| 07-01 · Navigation et retours | Garder l'accès aux univers sur sa page native : on ne duplique pas les choix et leurs états dans le chrome. Les notifications/délais n'apparaissent que quand ils existent ; le cas d'usage se vérifie avec un événement et un objectif vrais de fixture, puis en contexte natif. |
| 07-03 · Confirmations et vides | Comparer sur les vraies routes distinctes d'annulation et d'abandon, jamais sur une page de synthèse fictive. La dernière planète reste non abandonnable. Les cas catalogue relèvent de leur recette. |
| 07-04 · Mobile | Le récapitulatif Flottes et la fiche Galaxie précèdent le contenu, sans commande au-dessus d'un repère actif. À 390, garder le code coordonnée lisible, et les cinq commandes sur une ligne avec cibles tactiles. Vérifier largeur, gestes, absence de recouvrement et de zoom automatique. |

## Preuves

- Captures de travail sur données synthétiques : Chromium en viewport CSS 1920 × 1080, 390 × 844 et 360 × 800, sombre et clair. Les PNG ne sont pas versés au dépôt. Les comparaisons reprennent le même cas thématique ; le rendu évolue avec les durées, le contexte et le nombre de lignes.
- Premier passage `91ee1c6` : 22 images et geste Max sans envoi. À titre intermédiaire, comparées à `867b695`, la carte mobile 390 démarrait à y=666 au lieu de 744, et le Journal à y=1074 au lieu de 1170. La coordonnée mobile et le Journal demandaient encore un affinage ; ne pas faire passer ces mesures intermédiaires pour les valeurs finales.
- La CI finale valide les parcours mais ne transforme pas les variantes non photographiées en preuves visuelles. Noter séparément les captures correspondantes, les intégrations réelles et les états non rejoués.
