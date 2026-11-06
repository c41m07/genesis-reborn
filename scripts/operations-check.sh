#!/usr/bin/env bash
set -euo pipefail
# Exécuter depuis le dépôt déployé ; aucun redémarrage ni modification des files.
compose=(docker compose --env-file "${OPS_ENV_FILE:-.env.prod}" -f "${OPS_COMPOSE_FILE:-compose.prod.yaml}")
releve=$("${compose[@]}" ps --all --format json)
if [[ -z "$releve" ]]; then
    printf '%s\n' 'Supervision Docker vide ; aucun envoi ni rétablissement déduit.' >&2
    exit 2
fi
printf '%s\n' "$releve" | "${compose[@]}" exec -T --user www-data app php bin/console app:operations:check --processes-stdin "$@"
