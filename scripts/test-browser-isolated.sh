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
# La référence contient uniquement le schéma et les cartes du commit, avant tout
# démarrage des consommateurs. Elle appartient à ce lancement (jamais à la base locale).
prepare_reference() {
    compose up -d --wait database
    compose run --rm --no-deps -T --user "$(id -u):$(id -g)" app php bin/console doctrine:migrations:migrate --no-interaction
    compose run --rm --no-deps -T --user "$(id -u):$(id -g)" app php bin/console app:universe:import
    compose exec -T database sh -ec 'mariadb-dump -uroot -p"$MARIADB_ROOT_PASSWORD" --single-transaction --databases "$MARIADB_DATABASE" --add-drop-database > /tmp/genesis-browser-reference.sql'
}

if [[ "${1:-}" == "--prepare-reference" ]]; then
    [[ $# == 2 && ! -e "$2" ]] || { echo "Un nouveau dossier de destination est requis." >&2; exit 1; }
    prepare_reference
    mkdir -m 700 -- "$2"
    compose cp database:/tmp/genesis-browser-reference.sql "$2/reference.sql"
    (cd "$2" && sha256sum reference.sql > reference.sha256)
    exit 0
fi

if [[ -n "${BROWSER_REFERENCE_DIR:-}" ]]; then
    (cd "$BROWSER_REFERENCE_DIR" && sha256sum --check reference.sha256)
    compose up -d --wait database
    compose cp "$BROWSER_REFERENCE_DIR/reference.sql" database:/tmp/genesis-browser-reference.sql
    compose exec -T database sh -ec 'mariadb -uroot -p"$MARIADB_ROOT_PASSWORD" < /tmp/genesis-browser-reference.sql'
else
    prepare_reference
fi

compose up -d --wait app database rabbitmq mercure
compose up -d --wait worker relay notifier

PLAYWRIGHT_RESET_DB=1 PLAYWRIGHT_WORKERS=1 PLAYWRIGHT_BASE_URL="http://$BROWSER_HOST" app/node_modules/.bin/playwright test --config app/playwright.config.mjs "$@" --workers=1
