#!/usr/bin/env bash
set -Eeuo pipefail
root_dir="$(cd "$(dirname "$0")/.." && pwd)"; cd "$root_dir"
compose_file="${GENESIS_COMPOSE_FILE:-compose.yaml}"; env_file="${GENESIS_ENV_FILE:-.env.local}"; project="${GENESIS_PROJECT:-genesis_reborn_v2}"; archive="${BACKUP_FILE:-}"
[[ -n "$archive" && -f "$archive" ]] || { echo "Set BACKUP_FILE to an existing .sql.gz archive." >&2; exit 2; }
[[ -f "$compose_file" && -f "$env_file" ]] || { echo "Compose or environment file missing." >&2; exit 2; }
[[ "${RESTORE_CONFIRM:-}" == "$project" ]] || { printf "Restore refused. Set RESTORE_CONFIRM=%s after checking the target.\n" "$project" >&2; exit 3; }
case "$archive" in *.sql.gz) ;; *) echo "Refusing non .sql.gz backup." >&2; exit 2;; esac
gzip -t "$archive"
[[ -f "$archive.sha256" ]] || { echo "Backup checksum file is required." >&2; exit 2; }
python3 scripts/backup-tools.py verify --archive "$archive"
gzip -dc -- "$archive" | docker compose -p "$project" --env-file "$env_file" -f "$compose_file" exec -T database sh -ec 'mariadb --user=root --password="$MARIADB_ROOT_PASSWORD"'
printf "Restore completed for Compose project %s from %s\n" "$project" "$archive"
