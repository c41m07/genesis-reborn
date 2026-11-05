import { test as base, expect } from '@playwright/test';
import { execFileSync } from 'node:child_process';

function resetGameDatabase() {
  const project = process.env.BROWSER_ROUTER;
  if (!/^genesis-browser-test-[0-9]+-[0-9]+$/.test(project ?? '')) {
    throw new Error('La remise à zéro Playwright est autorisée uniquement dans le projet Docker de test isolé.');
  }
  const compose = ['compose', '--project-name', project, '--env-file', '.env.local', '-f', 'compose.yaml', '-f', 'compose.browser-test.yaml'];
  const run = (service, args) => execFileSync('docker', [...compose, 'exec', '-T', service, ...args], { encoding: 'utf8', stdio: ['ignore', 'pipe', 'pipe'] });
  run('database', ['sh', '-lc', 'mariadb -uroot -p"$MARIADB_ROOT_PASSWORD" -e "DROP DATABASE IF EXISTS genesis_v2; CREATE DATABASE genesis_v2 CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci; GRANT ALL PRIVILEGES ON genesis_v2.* TO \'genesis\'@\'%\';"']);
  run('app', ['php', 'bin/console', 'doctrine:migrations:migrate', '--no-interaction']);
  run('app', ['php', 'bin/console', 'app:universe:import']);
  execFileSync('docker', [...compose, 'restart', '--timeout', '3', 'worker', 'relay', 'notifier'], { encoding: 'utf8', stdio: ['ignore', 'pipe', 'pipe'] });
  execFileSync('docker', [...compose, 'up', '-d', '--wait', '--wait-timeout', '45', 'worker', 'relay', 'notifier'], { encoding: 'utf8', stdio: ['ignore', 'pipe', 'pipe'] });
}

export const test = base.extend({
  isolatedDatabase: [async ({ baseURL }, use) => {
    const project = process.env.BROWSER_ROUTER;
    if (!/^genesis-browser-test-[0-9]+-[0-9]+$/.test(project ?? '') || new URL(baseURL).hostname !== `${project}.localhost`) {
      throw new Error('Utilisez make test-browser ou scripts/test-browser-isolated.sh : ces scénarios écrivent uniquement dans une base de test temporaire.');
    }
    if (process.env.PLAYWRIGHT_RESET_DB === '1') resetGameDatabase();
    await use();
  }, { auto: true }],
});

export { expect };
