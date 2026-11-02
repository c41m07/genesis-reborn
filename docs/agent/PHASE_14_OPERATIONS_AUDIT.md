# Phase 14 — Exploitation et documentation

État : terminée et validée le 01/11/2026.

## Livrables

- Workflow GitHub Actions `.github/workflows/ci.yml` pour pushes, pull requests et déclenchement manuel. Jeton limité à `contents: read`; action checkout épinglée sur un SHA. Le job valide Compose, construit, installe depuis les verrous, compile, migre et importe le jeu, puis lance les tests PHP, lint et browser.
- `scripts/db-backup.sh` produit une archive gzip horodatée, vérifie son intégrité et son SHA-256, et applique des permissions restrictives.
- `scripts/db-restore.sh` valide archive/checksum et exige une confirmation explicite du nom Compose.
- Cibles `make backup` et `make restore`; procédures dans `docs/agent/RUNBOOK_OPERATIONS.md`.

## Preuves de clôture

- Le workflow a passé le parsing YAML local; le job et `permissions.contents: read` sont contrôlés. Le workflow n’a pas été lancé sur GitHub puisque la branche n’a pas été publiée.
- `bash -n`, `docker compose --env-file .env.local config --quiet`, `make lint` et `git diff --check` réussissent.
- Round-trip MariaDB jetable : table/ligne de marqueur restaurées après suppression; archive gzip et SHA-256 validés. Projet temporaire détruit.
- Une tentative sans `RESTORE_CONFIRM` a été refusée (code 3) avant connexion de restauration.
- Base du site local intacte.
