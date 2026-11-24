# Transposition des planches joueur

Référence : archive approuvée Genesis Reborn, planches P01 à P22 du dossier
02_JOUEUR. Base fonctionnelle de ce lot : dev après intégration de l’Avent.
Les vues autonomes et leurs liens serveur n’ont pas été réunis pour simuler
une planche comparant plusieurs états. Les contrôles et valeurs viennent des
services existants. Aucun code PHP, contrôleur Stimulus ou modèle de données
n’a changé.

| Planche | Transposition réelle | Limite et raison |
| --- | --- | --- |
| P01/P16 | /rapports : navigation de portée et type avant contenu, liste en colonnes et aide à droite ; transport et archives reliés aux formulaires natifs. | Les transports restent dans le filtre économique. Ne pas inventer un filtre ou un événement Collecte. L’état vide remplace la liste, il n’est pas affiché en même temps. |
| P02/P03 | Rapports combat et espionnage : bilan et pertes à gauche, rounds à droite ; renseignements observés à gauche, inconnus et entrée simulateur à droite. | Si les sondes sont détectées, le code n’affiche pas des ressources inventées. Les vues historiques et les accès de surveillance utilisent leurs données et la présentation antérieure. |
| P04/P18 | Événements : période courante à gauche, prochaines dates à droite, liens vers les défis et raccourcis natifs ; jours Quotidienne affichés avec un repère sur le jour actif. | Les défis gardent leur vraie page et leur progression ; pas de progression d’événement ajoutée sans données. Le lien Avent reste disponible, y compris pour des lots réservés par le joueur. |
| P05/P22 | Campagne : nom/état dans le bandeau natif, index des missions à gauche, prochain geste au centre et chronologie native à droite. | Formation, fret, dépôt, repli et simulateur conservent leurs conditions. Aucune fausse ETA ou garantie de récompense. |
| P06/P07/P17 | Identifiants : formulaire complet à gauche, portée et zone sensible à droite. Préférences : mêmes champs et sauvegarde, guide et vacances dans le rail. | Un vrai mode vacances et une vraie confirmation de suppression restent deux pages. Cosmétiques Avent conservés après le formulaire, sans petits changements de sécurité. |
| P08/P09/P21 | Premiers pas : prochaine action illustrée, étapes dépliables, remboursements et accès au réglage à droite. Exercice : briefing et résultat séparés, bilan par camp. | Pas de choix fictif de colonie sur le formulaire ; le changement de colonie natif reste expliqué. Le panneau existant et le retour de focus restent communs au shell. |
| P10/P19 | Cartes Planètes avec cote illustrée et commandes inchangées. En comparaison : titre, contrôle de ressource et tableau des stocks/activités existant. | Cartes et tableau restent deux présentations de la même destination ; leurs calculs ne sont pas déclenchés en double. Abandon et annulation conservent leurs pages séparées. |
| P11 | Inventaire : stock et capacité à gauche, aperçu/choix opération natif à droite ; raccourcis vers boîtes et file d’attente. | Onglets distincts et devis natif. Ne pas fabriquer une durée avant/après côté Twig. |
| P12 | Guide : liste et détail plus contrastés, rail conditionné à la sélection réelle, lien et filtres natifs. | Le panneau ne s’ouvre pas automatiquement et les domaines du jeu sont conservés. |
| P13/P21 | Simulateur : panneaux des deux camps, hypothèses près de la graine et de la commande, résultats sous le scénario. | Pas d’option, de valeur ou de résultat simulé artificiellement dans une recette visuelle. |
| P14 | Classement : filtre GET et rail d’aide, tri par les en-têtes toujours possible. | Rang, score, variations, protection, pagination et actions inchangés. Signalement et contact relèvent du lot social. |
| P15 | Wiki : sommaire visible à gauche sur grand écran, recherche alignée dans le rail de lecture. | Chapitres, résultats, règles et références restent des destinations serveur. |
| P20 | Patchnotes : version, titre et date séparés ; détail natif conservé. | Feedback relève du lot social. |

Vérifications à effectuer sur fixtures : CSS 1920×1080 à l’échelle 1, 390×844
sur les ponts de mise en page, thème clair/sombre, navigation native, nouveaux
messages/appels et refus existants. Les silhouettes de démonstration de
l’archive ne donnent aucune valeur à injecter dans le jeu.

## Recette vérifiée

Recette Chromium avec compte synthétique, base MariaDB éphémère et SMTP désactivé.
Dimensions CSS et PNG vérifiées, échelle 1 : 1920 × 1080 et 390 × 844.
Les captures sont des états réels de l’application, non recadrés ; les
maquettes demeurent des références, pas des captures de la version précédente.

- 253 gabarits Twig valides et compilation Encore de production réussie.
- `player-board-layout.spec.mjs` : 16 vues sur les deux formats ; colonnes
  contrôlées en FHD, empilement mobile et largeur du document contrôlés.
- `event-workspace.spec.mjs` : annonces, défis, classement et récompenses
  dans les états de la fixture ; deux thèmes sur 1440/768/390/320 et Axe
  sur le format mobile.
- `advent-calendar.spec.mjs` (cas de reprise) : depuis Événements, le
  cadeau réservé reste récupérable après clôture sans JavaScript.
- `report-workspace.spec.mjs` : scénario échoué initialement sur le
  contraste du rapport Transport clair ; relancé sans changer Axe et réussi
  après correction. Transport, retour filtré, réinitialisation du filtre
  de planète et lecture des rapports sans JavaScript, espionnage détecté
  inclus. La commande d’archivage sans JavaScript est vérifiée visible.
- Vérification statique des formulaires et CSRF comparés à la base :
  seule nouvelle action de formulaire, la recherche GET Classement.

| Planche | Couverture au navigateur ou statique | Limite de la preuve |
| --- | --- | --- |
| P01 | FHD + mobile, parcours et captures | Centre avec transport et espionnage détecté. |
| P02 | Statique : Twig et styles | Rapport de combat complet non rejoué. |
| P03 | Navigateur : espionnage détecté sans JavaScript ; détail positif statique | Pas de capture d’espionnage réussi. |
| P04 | FHD + mobile, captures ; états d’annonces et de défis natifs | La progression d’un défi reste sur sa propre page. |
| P05 | FHD + mobile, captures | Première mission sans sonde, verrouillages visibles. |
| P06 | FHD + mobile, captures | Nouveau profil ; formulaire de cosmétiques possédés vérifié statiquement. |
| P07 | FHD + mobile, captures | Affichage des préférences, pas de changement en production. |
| P08 | FHD + mobile, captures | Nouveau compte, chapitre Mine et aucun remboursement disponible. |
| P09 | Statique : aperçu non modal existant, diapos et commandes intactes | Pas de preuve de la galerie et du retour focus dans cette recette. |
| P10 | FHD + mobile, captures | Une seule colonie de départ créée, pas de reproduction fictive des trois colonies de la maquette. |
| P11 | FHD + mobile, captures | Stock vide ; le devis d’utilisation active reste natif et n’a pas été rejoué. |
| P12 | FHD + mobile, captures | Entrée du guide ; pas de preuve de tous les déblocages. |
| P13 | FHD + mobile, captures | Écran de saisie ; aucun calcul de combat déclenché pour cette capture. |
| P14 | FHD + mobile, captures | Classement joueurs, tri et mode guildes conservés. |
| P15 | FHD + mobile, captures | Accueil Wiki et barre de recherche ; pas tous les chapitres. |
| P16 | FHD + capture, mobiles testés par Rapports | Transport complet avec données et pertes synthétiques ; Axe clair/sombre. |
| P17 | FHD + mobile, captures | Confirmation sans supprimer le compte ; mode vacances préservé sur sa route. |
| P18 | FHD + mobile, captures de la dotation ; parcours Défis + Avent | La dotation visible ne prétend pas prouver un versement quotidien. |
| P19 | FHD + mobile, captures | Stock, capacité, production et trois types d’activités affichés pour une colonie. |
| P20 | FHD + mobile, captures | Patchnotes seules ; Feedback relève du lot 03. |
| P21 | FHD + mobile, captures de l’exercice avant résultat ; résultat statique | Le résultat n’est pas fabriqué dans la page de revue. |
| P22 | FHD + mobile sur le dossier verrouillé ; autres états statiques | Escorte, convoi et repli n’ont pas été rejoués bout en bout. |

À titre d’amélioration distincte, on pourrait montrer les éléments classés par
type de rapport dans un nouveau filtre, ajouter un comparatif chiffré de durée
des boosts à partir du devis serveur, ou ajouter un exemple de chronologie dans
l’aide de la campagne. Ces idées nécessitent des données ou un besoin joueur
explicite ; elles n’ont pas été simulées avec des chiffres de maquette.
