# Phase 02 — critères consolidés pour missions et combat

Date : 31/10/2026. Sources : docs/specifications/1 - Conception batiment vaisseau etc.txt §1.12; doc 2 §§1–2 et 3.5–3.8; doc 3 §§2–4; docs/REGLES.md; docs/DECISIONS.md.

## Règles explicites à préserver

### Missions et arrivée
- Le trajet, le carburant et la capacité sont recalculés côté serveur; le navigateur ne choisit pas le résultat ni les dates d'arrivée.
- Une attaque sur une planète déclenche à la première arrivée une fenêtre fixe. Le paramètre actuel est combat_window_seconds = 600 dans config/game/rules.yaml, marqué provisoire.
- Une flotte attaque ou défend peut rejoindre pendant la fenêtre; l'heure d'arrivée doit être enregistrée.
- Une flotte en défense sans attaque repart à l'expiration de la fenêtre. Une arrivée après résolution retourne directement au propriétaire.
- Une flotte en orbite est distincte de DEFENSE. Elle est acceptée manuellement, sauf contrat d'alliance direct où l'acceptation est automatique; elle peut aider à défendre pendant sa durée stationnée.
- Les trajets en orbite réservent le carburant aller + stationnement prévu + retour au départ. Les trajets inter-galaxies restent interdits faute de règle.

### Alliances et camps
- Relation alliée si contrat direct accepté, même guilde, ou guildes alliées; une hostilité entre guildes empêche un contrat personnel de maintenir l'alliance.
- Les attaquants alliés combattent dans un camp commun; les attaquants non alliés restent en FFA.
- Les défenses statiques de la planète combattent tous les attaquants, même en FFA.
- Une flotte de défense extérieure est incluse dans le camp défendeur pour le calcul de combat; les défenses statiques de la planète ne lui infligent pas de dégâts.
- Une unité ne cible que des unités ennemies vivantes. Les défenses statiques ciblent les rôles attaquants.

### Simulation
- Résolution intégralement côté serveur à la fin du timer; affichage ultérieur par rejeu du journal.
- Maximum actuel : 10 rounds dans rules.yaml, valeur de démonstration à confirmer. Au-delà, les attaquants survivants battent en retraite.
- Chaque vaisseau et défense est simulé individuellement; les HP ne persistent pas entre combats et les survivants reviennent à leurs HP maximum.
- À chaque round, chaque unité vivante choisit une cible parmi les ennemis; une unité détruite avant son tour ne tire pas. Nombre de tirs aléatoire inclusif entre shots_min et shots_max.
- Chance de toucher = clamp(15, 95, précision + tracking - esquive_cible + signature_cible / 5 - 15).
- Dégâts = attaque de base × multiplicateur de matchup. Valeur de matchup non spécifiée pour un couple : multiplicateur neutre 1, conformément au catalogue existant.
- Les arrivées échelonnées entrent au round correspondant à leur arrivée relative dans la fenêtre; avant cela elles n'agissent ni ne subissent de dégâts.
- Résolution reproductible : générer et persister une graine avant résolution; conserver dans le rapport les actions suffisantes pour le rejeu sans recalculer le RNG côté client.

### Rapport, carburant et notification
- Persister l'événement, ses participants et les rounds; outcomes documentés : attaquant gagne, défenseur gagne, égalité.
- Les pertes de cargos réduisent la soute de retour et le butin; l'hydrogène de retour réservé au départ doit rester disponible, donc pas de panne sèche.
- Notification d'attaque : membres de la guilde du défenseur et alliés directs du défenseur; les guildes seulement alliées ne sont pas notifiées; les alliés de l'attaquant ne sont pas notifiés.
- Identité de l'attaquant masquée si le défenseur n'a pas découvert son système d'origine; le système d'origine reste visible.
- Renfort entrant : même politique de visibilité; le défenseur est notifié.

## Critères d'acceptation techniques
1. Deux arrivées simultanées au premier attaquant ne créent qu'un événement de combat par planète et fenêtre.
2. L'événement démarre seulement à la première arrivée ATTAQUE; les flottes DEFENSE seules ne déclenchent pas de combat.
3. L'arrivée, le rôle, la flotte et le round d'entrée sont persistés et idempotents.
4. Une même graine + mêmes snapshots produit un journal byte-for-byte identique; une résolution rejouée ne modifie pas deux fois stocks, unités ou notifications.
5. Une unité éliminée ne tire pas; une unité arrivée plus tard n'intervient pas avant son round; les limites de touches et multiplicateurs sont bornés et validés.
6. Les camps et droits de ciblage respectent les relations et les exceptions des défenses statiques.
7. Les retours des survivants, pertes, butin, carburant réservé et soutes sont atomiques et testés sur MariaDB isolée.
8. L'accès aux rapports est limité aux participants du combat; les notifications ne divulguent pas d'identité non découverte.
9. Les handlers/outbox tolèrent doublons, messages périmés, panne de Mercure et reprise après redémarrage.
10. Aucun bouton d'attaque/défense/orbite ne s'affiche avant que le parcours correspondant soit réellement câblé et protégé par CSRF, propriété et contrôles d'univers.

## Points explicitement provisoires ou incomplets
- Fenêtre 600 secondes et maximum 10 rounds sont déjà paramétrés, mais le catalogue global porte provisional=true. Ils peuvent être utilisés en dev et ne qualifient pas l'équilibrage de production.
- Plusieurs statistiques et la matrice de matchup sont incomplètes; ne pas traiter les valeurs provisoires comme validées.
- L'algorithme exact de choix de cible, l'ordre d'initiative, la distribution du nombre de tirs, l'arrondi des dégâts/HP et la règle de coalition transitive ne sont pas complètement définis dans les textes. Une règle déterministe simple doit être documentée avant d'être codée.
- En FFA, les camps survivants et l'outcome global exigent une règle unique; l'enum du document ne permet pas d'exprimer un vainqueur parmi plusieurs camps attaquants.
- Les ressources de butin, la quantité/ratio récupérable et les bornes du butin ne sont pas précisées.

## État du dépôt

Les fichiers Fleet/FleetShip ne portent actuellement ni mission d'attaque, ni destination de flotte en cours, ni HP, ni lien CombatParticipant. PlanetDefense ne porte que le type et la quantité. FleetService n'autorise que explore, colonize et transport. La phase d'implémentation doit ajouter une extension persistée et migrée, sans réécrire ni invalider les flux existants.
