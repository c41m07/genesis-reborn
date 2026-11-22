import { test as base, expect } from '@playwright/test';
import { execFile } from 'node:child_process';
import { promisify } from 'node:util';

const executeFile = promisify(execFile);
const docker = args => executeFile('docker', args, {
  encoding: 'utf8', timeout: 45_000, maxBuffer: 4 * 1024 * 1024,
});

async function resetGameDatabase() {
  const project = process.env.BROWSER_ROUTER;
  if (!/^genesis-browser-test-[0-9]+-[0-9]+$/.test(project ?? '')) {
    throw new Error('La remise à zéro Playwright est autorisée uniquement dans le projet Docker de test isolé.');
  }
  const compose = ['compose', '--project-name', project, '--env-file', '.env.local', '-f', 'compose.yaml', '-f', 'compose.browser-test.yaml'];
  if (process.env.BROWSER_PERSISTENCE === '1') compose.push('-f', 'compose.browser-persistence.yaml');
  const run = (service, args) => docker([...compose, 'exec', '-T', service, ...args]);
  const timings = [];
  const stage = async (name, action) => {
    const started = performance.now();
    await action();
    timings.push({ name, duration_ms: Math.round(performance.now() - started) });
  };
  // Aucun consommateur ne doit lire ou écrire pendant la reconstruction du schéma.
  await stage('stop_consumers', () => docker([...compose, 'stop', '--timeout', '3', 'worker', 'relay', 'notifier']));
  await stage('reset_database', () => run('database', ['sh', '-lc', 'mariadb -uroot -p"$MARIADB_ROOT_PASSWORD" -e "DROP DATABASE IF EXISTS genesis_v2; CREATE DATABASE genesis_v2 CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci; GRANT ALL PRIVILEGES ON genesis_v2.* TO \'genesis\'@\'%\';"']));
  await stage('migrate', () => run('app', ['php', 'bin/console', 'doctrine:migrations:migrate', '--no-interaction']));
  await stage('import', () => run('app', ['php', 'bin/console', 'app:universe:import']));
  await stage('start_consumers', () => docker([...compose, 'up', '-d', '--wait', '--wait-timeout', '45', 'worker', 'relay', 'notifier']));
  return timings;
}

export const test = base.extend({
  verifyRegisteredAccounts: [true, { option: true }],
  keepDailyRewardPrompt: [false, { option: true }],
  verifiedRegistrationContexts: [async ({ browser, verifyRegisteredAccounts, keepDailyRewardPrompt, isolatedDatabase }, use) => {
    const originalNewContext = browser.newContext;
    // Les scénarios métier utilisent des comptes de test déjà vérifiés.
    // Le parcours de confirmation possède ses propres scénarios sans cette préparation.
    const prepare = async context => context.route('**/inscription', async route => {
      if (route.request().method() !== 'POST') return route.continue();
      const url = new URL(route.request().url());
      const response = await route.fetch({ url: `http://127.0.0.1${url.pathname}`, headers: { ...route.request().headers(), host: url.host }, maxRedirects: 0 });
      if (response.status() === 302) {
        const email = new URLSearchParams(route.request().postData()).get('registration[email]');
        if (email) {
          const query = "UPDATE player SET email_verified_at=UTC_TIMESTAMP() WHERE email='" + email.replaceAll("'", "''") + "'";
          await docker(['compose', '-p', process.env.BROWSER_ROUTER, '--env-file', '.env.local', 'exec', '-T', 'app', 'php', 'bin/console', 'dbal:run-sql', query]);
        }
      }
      await route.fulfill({ response });
    });
    browser.newContext = async (...args) => {
      const context = await originalNewContext.apply(browser, args);
      if (verifyRegisteredAccounts) await prepare(context);
      if (!keepDailyRewardPrompt) {
        const originalNewPage = context.newPage;
        context.newPage = async (...pageArgs) => {
          const page = await originalNewPage.apply(context, pageArgs);
          // Les scénarios sans rapport avec la dotation continuent sans prendre le lot.
          const dailyPrompt = page.locator('#daily-reward-dialog[open]').getByRole('button', { name: 'Plus tard', exact: true });
          await page.addLocatorHandler(dailyPrompt, button => button.click());
          return page;
        };
      }
      return context;
    };
    try { await use(); } finally { browser.newContext = originalNewContext; }
  }, { auto: true }],
  isolatedDatabase: [async ({ baseURL }, use, testInfo) => {
    const project = process.env.BROWSER_ROUTER;
    if (!/^genesis-browser-test-[0-9]+-[0-9]+$/.test(project ?? '') || new URL(baseURL).hostname !== `${project}.localhost`) {
      throw new Error('Utilisez make test-browser ou scripts/test-browser-isolated.sh : ces scénarios écrivent uniquement dans une base de test temporaire.');
    }
    if (process.env.PLAYWRIGHT_RESET_DB === '1') {
      const stages = await resetGameDatabase();
      await testInfo.attach('isolated-database-setup', {
        body: Buffer.from(JSON.stringify({ stages })), contentType: 'application/json',
      });
    }
    await use();
  // Budget de préparation distinct : les 60 secondes du parcours restent réservées à ses actions.
  }, { auto: true, timeout: 60_000 }],
});

export { expect };
