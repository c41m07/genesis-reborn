#!/usr/bin/env bash
set -Eeuo pipefail

root_dir="$(cd "$(dirname "$0")/.." && pwd)"
cd "$root_dir"

if [[ ! -f .env.local ]]; then
    printf '%s\n' 'Configuration locale absente : lancez make configure avant les tests navigateur.' >&2
    exit 1
fi

project_name="genesis-browser-test-$(date +%s)-$$"
export BROWSER_ROUTER="$project_name"
export BROWSER_HOST="$project_name.localhost"
if [[ ! "$project_name" =~ ^genesis-browser-test-[0-9]+-[0-9]+$ ]]; then
    printf '%s\n' 'Nom de projet de test invalide; arrêt sans toucher aux conteneurs.' >&2
    exit 1
fi

compose() {
    local files=(-f compose.yaml -f compose.browser-test.yaml)
    if [[ "${BROWSER_PERSISTENCE:-0}" == "1" ]]; then
        files+=(-f compose.browser-persistence.yaml)
    fi
    docker compose -p "$project_name" --env-file .env.local "${files[@]}" "$@"
}

if [[ -n "$(compose ps -q 2>/dev/null || true)" ]]; then
    printf '%s\n' 'Le projet de test isolé existe déjà; arrêt pour préserver ses données.' >&2
    exit 1
fi

cleanup() {
    compose down --volumes --remove-orphans >/dev/null 2>&1 || true
}
trap cleanup EXIT INT TERM

compose config --quiet
compose up -d --wait app database rabbitmq mercure
compose exec -T --user "$(id -u):$(id -g)" app php bin/console doctrine:migrations:migrate --no-interaction
compose exec -T --user "$(id -u):$(id -g)" app php bin/console app:universe:import
compose up -d --wait worker relay notifier

PLAYWRIGHT_RESET_DB=1 PLAYWRIGHT_WORKERS=1 PLAYWRIGHT_BASE_URL="http://$BROWSER_HOST" npx playwright test "$@" --workers=1
