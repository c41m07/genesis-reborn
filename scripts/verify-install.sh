#!/usr/bin/env bash
set -euo pipefail
source_dir="$(cd "$(dirname "$0")/.." && pwd)"
verify_dir="$(mktemp -d /tmp/genesis-verify.XXXXXX)"
verify_project="genesis_verify_$(date +%s)_$RANDOM"
case "$verify_project" in genesis_verify_*) ;; *) exit 1;; esac
compose_verify() { docker compose -p "$verify_project" --project-directory "$verify_dir" --env-file "$verify_dir/.env.local" -f "$verify_dir/compose.yaml" -f "$verify_dir/isolation.yaml" "$@"; }
cleanup() {
    if [ -f "$verify_dir/.env.local" ]; then compose_verify down --volumes --remove-orphans >/dev/null 2>&1 || true; fi
    rm -rf -- "$verify_dir"
}
trap cleanup EXIT
python3 - "$source_dir" "$verify_dir" <<'PY'
import sys,shutil
from pathlib import Path
source,target=map(Path,sys.argv[1:])
ignore=shutil.ignore_patterns('vendor','node_modules','.env.local','.git','var','test-results','playwright-report','.phpunit.cache','build')
shutil.copytree(source,target,ignore=ignore,dirs_exist_ok=True)
PY
cat > "$verify_dir/isolation.yaml" <<'YAML'
services:
  app:
    labels:
      traefik.enable: 'false'
    networks: !override [backend]
  mercure:
    labels:
      traefik.enable: 'false'
    networks: !override [backend]
  database:
    ports: !reset []
YAML
cd "$verify_dir"
python3 scripts/configure.py
compose_verify config --quiet
compose_verify run --rm --no-deps --user "$(id -u):$(id -g)" app composer install --no-interaction
npm --prefix app ci --no-audit
npm --prefix app run build
compose_verify up -d app database rabbitmq mercure
compose_verify exec -T --user "$(id -u):$(id -g)" app php bin/console doctrine:migrations:migrate --no-interaction
compose_verify exec -T --user "$(id -u):$(id -g)" app php bin/console app:universe:import
compose_verify exec -T --user "$(id -u):$(id -g)" app php bin/console app:seed-demo
compose_verify up -d --wait
compose_verify exec -T app php -r 'exit(file_get_contents("http://127.0.0.1/health")==="ok"?0:1);'
compose_verify exec -T --user "$(id -u):$(id -g)" app php bin/console doctrine:schema:validate
compose_verify stop
compose_verify up -d --wait
compose_verify exec -T --user "$(id -u):$(id -g)" app php bin/console app:seed-demo
compose_verify exec -T --user "$(id -u):$(id -g)" app php bin/console app:universe:import
printf '%s\n' 'INSTALLATION VIERGE ET CONSERVATION APRES REDEMARRAGE : OK'
