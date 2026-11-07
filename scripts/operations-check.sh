#!/usr/bin/env bash
set -euo pipefail
# Exécuter depuis le dépôt déployé ; aucun redémarrage ni modification des files.
compose=(docker compose --env-file "${OPS_ENV_FILE:-.env.prod}" -f "${OPS_COMPOSE_FILE:-compose.prod.yaml}")
releve=$("${compose[@]}" ps --all --format json)
if [[ -z "$releve" ]]; then
    printf '%s\n' 'Supervision Docker vide ; aucun envoi ni rétablissement déduit.' >&2
    exit 2
fi
backup_args=()
if [[ -n "${BACKUP_DIR:-}" ]]; then
    : "${GENESIS_PROJECT:?Renseigner GENESIS_PROJECT pour le contrôle des sauvegardes}"
    if python3 scripts/backup-tools.py check >/dev/null; then
        backup_state=ok
    else
        code=$?; backup_state=unknown
        [[ "$code" != 1 ]] || backup_state=failed
        [[ "$code" != 3 ]] || backup_state=running
    fi
    backup_args=("--backup-status=$backup_state")
fi
printf '%s\n' "$releve" | "${compose[@]}" exec -T --user www-data app php bin/console app:operations:check --processes-stdin "${backup_args[@]}" "$@"
