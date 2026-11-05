#!/usr/bin/env bash
set -euo pipefail
cd /opt/docker-compose/genesis-reborn
exec 9>.deploy.lock
flock -n 9 || { echo 'Un déploiement est déjà en cours.' >&2; exit 1; }
image=${1:?Une image GHCR avec digest est requise}
[[ "$image" =~ ^ghcr\.io/[a-z0-9._/-]+@sha256:[a-f0-9]{64}$ ]]
export APP_IMAGE="$image"
dc=(docker compose --env-file .env.prod -f compose.prod.yaml)
"${dc[@]}" config --quiet
"${dc[@]}" pull
"${dc[@]}" up -d --wait database rabbitmq mercure
# Éviter des consommateurs anciens pendant les migrations (courte maintenance).
"${dc[@]}" stop app worker relay notifier
"${dc[@]}" run --rm --no-deps --user www-data app php bin/console doctrine:migrations:migrate --no-interaction
"${dc[@]}" up -d --wait --wait-timeout 180
printf '%s\n' "$image" > deployed-image.txt
"${dc[@]}" ps
