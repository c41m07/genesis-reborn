#!/usr/bin/env bash
set -Eeuo pipefail
root_dir="$(cd "$(dirname "$0")/.." && pwd)"; cd "$root_dir"
compose_file="${GENESIS_COMPOSE_FILE:-compose.yaml}"; env_file="${GENESIS_ENV_FILE:-.env.local}"; project="${GENESIS_PROJECT:-genesis_reborn_v2}"
backup_dir="${BACKUP_DIR:-$root_dir/var/backups}"
[[ -f "$compose_file" && -f "$env_file" ]] || { echo "Compose or environment file missing" >&2; exit 2; }
mkdir -p -- "$backup_dir"; chmod 700 "$backup_dir"; umask 077
stamp="$(date -u +%Y%m%dT%H%M%SZ)"; archive="$backup_dir/genesis-$project-$stamp.sql.gz"
tmp_archive="$(mktemp "$backup_dir/.genesis-backup.XXXXXX")"; trap 'rm -f -- "$tmp_archive"' EXIT
docker compose -p "$project" --env-file "$env_file" -f "$compose_file" exec -T database sh -ec 'mariadb-dump --user=root --password="$MARIADB_ROOT_PASSWORD" --single-transaction --routines --events --triggers --add-drop-database --databases "$MARIADB_DATABASE"' | gzip -9 > "$tmp_archive"
gzip -t "$tmp_archive"; mv -- "$tmp_archive" "$archive"; trap - EXIT
chmod 600 "$archive"; (cd "$backup_dir" && sha256sum "$(basename "$archive")" > "$(basename "$archive").sha256"); chmod 600 "$archive.sha256"
printf "Backup created: %s\n" "$archive"
