import { execFile } from 'node:child_process';
import { promisify } from 'node:util';
import { test, expect } from './support/isolated-test.mjs';
import { queryIsolatedSql, runIsolatedSql } from './support/game-fixtures.mjs';

const executeFile = promisify(execFile);

test('Observatoire : le relais et le worker mesurent RabbitMQ, le notifier mesure Mercure', async ({ page }) => {
  test.setTimeout(90_000);
  const project = process.env.BROWSER_ROUTER;
  expect(project).toMatch(/^genesis-browser-test-[0-9]+-[0-9]+$/);
  const compose = ['compose', '-p', project, '--env-file', '.env.local', '-f', 'compose.yaml', '-f', 'compose.browser-test.yaml'];
  if (process.env.BROWSER_PERSISTENCE === '1') compose.push('-f', 'compose.browser-persistence.yaml');
  const docker = (...args) => executeFile('docker', [...compose, ...args], { timeout: 35_000 });
  const sample = (...args) => docker('exec', '-T', '-e', 'APP_PERFORMANCE_SAMPLE_PERCENT=100', 'app',
    'php', 'bin/console', ...args, '--no-interaction');

  const email = `observation-${crypto.randomUUID().slice(0, 8)}@example.test`;
  const password = 'Browser-test-2026!';
  await page.goto('/inscription');
  await page.getByLabel('Adresse email').fill(email);
  await page.getByLabel('Nom de commandant').fill('Recette-Transport');
  await page.getByLabel('Mot de passe (12 caractères minimum)', { exact: true }).fill(password);
  await page.getByLabel('Confirmer le mot de passe').fill(password);
  await page.getByRole('button', { name: 'Créer mon compte' }).click();
  await expect(page).toHaveURL(/connexion/);
  await page.getByLabel('Adresse email').fill(email);
  await page.getByLabel('Mot de passe', { exact: true }).fill(password);
  await page.getByRole('button', { name: /Connexion/ }).click();
  await expect(page).not.toHaveURL(/connexion/);
  await page.locator('article.universe-card').first().getByRole('button').click();
  await expect(page).toHaveURL(/planetes\/\d+$/);

  const member = Number(queryIsolatedSql(project, `SELECT pu.id FROM player_universe pu JOIN player p ON p.id=pu.player_id WHERE p.email='${email}'`));
  expect(member).toBeGreaterThan(0);
  // Ne mesurer que les deux envois contrôlés. Le broker et le hub ne sont ni arrêtés ni remplacés.
  await docker('stop', '--timeout', '3', 'worker', 'relay', 'notifier');
  try {
    const token = crypto.randomUUID();
    runIsolatedSql(project, `INSERT INTO notification (recipient_id, message, created_at)
      VALUES (${member}, 'Recette Mercure', UTC_TIMESTAMP())`);
    runIsolatedSql(project, `INSERT INTO outbox_message (kind, target_id, token, due_at)
      SELECT 'notification', MAX(id), '${token}', UTC_TIMESTAMP() FROM notification
      WHERE recipient_id=${member} AND message='Recette Mercure'`);
    runIsolatedSql(project, `INSERT INTO outbox_message (kind, target_id, token, due_at)
      VALUES ('queue', 2147483647, '${token}', UTC_TIMESTAMP() - INTERVAL 2 SECOND)`);
    // Un item inexistant ne change pas le jeu : ce test vérifie la livraison réelle, pas la construction.
    await sample('app:outbox:relay', '--kind=events');
    await sample('messenger:consume', 'async', '--limit=1', '--time-limit=12');
    await sample('app:outbox:relay', '--kind=notifications');

    const observed = queryIsolatedSql(project, `
      SELECT CONCAT(scope, ':', metric) FROM performance_bucket
      WHERE scope IN ('rabbitmq', 'worker', 'mercure') GROUP BY scope, metric ORDER BY scope, metric
    `).split('\n');
    expect(observed).toEqual([
      'mercure:mercure_publish',
      'rabbitmq:broker_publish',
      'rabbitmq:outbox_ready',
      'worker:worker_execute',
      'worker:worker_wait',
    ]);
    expect(queryIsolatedSql(project, `SELECT COUNT(*) FROM outbox_message WHERE token='${token}'`)).toBe('0');
  } finally {
    await docker('up', '-d', 'worker', 'relay', 'notifier');
  }
});
