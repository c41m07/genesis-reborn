# Compte à rebours de fermeture

La date `Universe.endsAt` enregistrée en base fait autorité. Le composant apparaît lorsque le statut est actif et qu’il reste **au maximum 24 heures**, sur la carte de l’univers et dans les pages du joueur ayant cet univers sélectionné. Sans date, ou avec une date à plus de 24 heures, il est masqué. Il peut apparaître sans rechargement lorsque le seuil est franchi.

Le rendu initial utilise `ClockInterface`. Le navigateur progresse avec son horloge monotone (`performance.now`), indépendamment de son heure civile et de son fuseau. Les dates échangées portent leur décalage UTC ; l’échéance lisible est explicitement affichée en UTC. Une lecture authentifiée, sans écriture et sans cache, de `/univers/{id}/fermeture` resynchronise au démarrage, chaque minute et au retour dans l’onglet. Un changement de calendrier peut donc prendre jusqu’à une minute à apparaître dans un onglet visible, hors délai réseau. Une suspension du navigateur est rattrapée à son retour. La précision reste limitée par la latence réseau.

À zéro, le temps ne devient pas négatif : « Échéance de fermeture atteinte » est affiché. Ce texte ne prétend pas que le worker a déjà persisté le statut fermé. La fermeture et les contrôles d’accès restent intégralement serveur ; aucune durée ni règle ne change. Un statut serveur non actif masque le compte à rebours. En cas d’échec de lecture, la dernière échéance connue continue de défiler avec une mention d’actualisation indisponible. Sans JavaScript, la valeur initiale reste consultable et un rechargement manuel est indiqué.

Le compteur n’est pas une région vocale actualisée chaque seconde (`role=timer`, `aria-live=off`). Les textes sont explicites et la mise en page s’adapte au mobile. Ces contrôles ciblés ne constituent pas un audit RGAA complet.

## Vérifications

Tests de seuil exact et de dépassement, échéance, absence de date, statut fermé et date avec décalage horaire ; scénario navigateur avec poste désynchronisé et fuseau Honolulu, vues univers/jeu, actualisation du calendrier, échéance, erreur réseau et présentation mobile. Les résultats exécutés et les limites de livraison sont consignés dans le [suivi Alpha](audits/alpha-operations-follow-up.md). Aucun résultat local ne valide l’Alpha hébergée.
