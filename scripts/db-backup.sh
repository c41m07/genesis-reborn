#!/usr/bin/env bash
set -Eeuo pipefail
root_dir="$(cd "$(dirname "$0")/.." && pwd)"; cd "$root_dir"
compose_file="${GENESIS_COMPOSE_FILE:-compose.yaml}"; env_file="${GENESIS_ENV_FILE:-.env.local}"; project="${GENESIS_PROJECT:-genesis_reborn_v2}"
backup_dir="${BACKUP_DIR:-$root_dir/var/backups}"; retention="${BACKUP_RETENTION_DAYS:-14}"
[[ "$project" =~ ^[a-z0-9][a-z0-9_-]*$ && "$retention" =~ ^[1-9][0-9]*$ ]] || { echo 'Projet ou durée de conservation invalide.' >&2; exit 2; }
[[ -f "$compose_file" && -f "$env_file" ]] || { echo 'Fichier Compose ou environnement absent.' >&2; exit 2; }
umask 077; mkdir -p -- "$backup_dir"; chmod 700 "$backup_dir"
exec 9>"$backup_dir/.genesis-$project.lock"
flock -n 9 || { echo 'Une sauvegarde de ce projet est déjà en cours.' >&2; exit 4; }
tmp_archive=''; tmp_manifest=''
finish() {
    status=$?; trap - EXIT
    if (( status != 0 )); then
        python3 scripts/backup-tools.py record --directory "$backup_dir" --project "$project" --status failed || true
        printf 'Échec de sauvegarde du projet %s (code %s).\n' "$project" "$status" >&2
    fi
    [[ -z "$tmp_archive" ]] || rm -f -- "$tmp_archive"
    [[ -z "$tmp_manifest" ]] || rm -f -- "$tmp_manifest"
    exit "$status"
}
trap finish EXIT
trap 'exit 130' INT
trap 'exit 143' TERM
python3 scripts/backup-tools.py record --directory "$backup_dir" --project "$project" --status started
stamp="$(date -u +%Y%m%dT%H%M%SZ)"
tmp_archive="$(mktemp "$backup_dir/.genesis-$project-$stamp.XXXXXXXXXXXX")"
archive="$backup_dir/genesis-$project-$stamp-${tmp_archive##*.}.sql.gz"
[[ ! -e "$archive" && ! -e "$archive.sha256" ]] || { echo 'Une archive existe déjà ; écrasement refusé.' >&2; exit 2; }
docker compose -p "$project" --env-file "$env_file" -f "$compose_file" exec -T database sh -ec 'mariadb-dump --user=root --password="$MARIADB_ROOT_PASSWORD" --single-transaction --routines --events --triggers --add-drop-database --databases "$MARIADB_DATABASE"' | gzip -9 > "$tmp_archive"
gzip -t "$tmp_archive"; mv -- "$tmp_archive" "$archive"; tmp_archive=''
tmp_manifest="$(mktemp "$backup_dir/.genesis-manifest.XXXXXXXXXXXX")"
(cd "$backup_dir" && sha256sum "$(basename "$archive")") > "$tmp_manifest"
mv -- "$tmp_manifest" "$archive.sha256"; tmp_manifest=''
python3 scripts/backup-tools.py verify --archive "$archive"
python3 scripts/backup-tools.py record --directory "$backup_dir" --project "$project" --status ok --archive "$archive"
python3 scripts/backup-tools.py prune --directory "$backup_dir" --project "$project" --days "$retention" --archive "$archive"
printf 'Sauvegarde créée : %s\n' "$archive"
