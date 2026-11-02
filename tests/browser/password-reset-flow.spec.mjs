import { test, expect } from './support/isolated-test.mjs';
import { execFileSync } from 'node:child_process';

async function register(page) {
  const id = crypto.randomUUID().slice(0, 8);
  const email = 'reset-ui-' + id + '@example.test';
  const username = 'Reset-' + id;
  const password = 'Original-Password-2026!';
  await page.goto('/inscription');
  await page.getByLabel('Adresse email').fill(email);
  await page.getByLabel('Nom de commandant').fill(username);
  await page.getByLabel('Mot de passe (12 caractères minimum)', { exact: true }).fill(password);
  await page.getByLabel('Confirmer le mot de passe').fill(password);
  await page.getByRole('button', { name: 'Créer mon compte' }).click();
  await expect(page).toHaveURL(/connexion/);
  return { email, username, password };
}

function sql(project, query) {
  execFileSync('docker', ['compose', '--project-name', project, '--env-file', '.env.local', 'exec', '-T', 'app', 'php', 'bin/console', 'dbal:run-sql', query], { encoding: 'utf8', stdio: ['ignore', 'pipe', 'pipe'] });
}

test('Récupération du compte : confirmation neutre, nouveau mot de passe et jeton à usage unique', async ({ page }) => {
  test.setTimeout(90000);
  const account = await register(page);
  await page.goto('/mot-de-passe/oublie');
  await page.getByLabel('Adresse email').fill('unknown-' + crypto.randomUUID().slice(0, 8) + '@example.test');
  await page.getByRole('button', { name: 'Envoyer le lien' }).click();
  await expect(page).toHaveURL(/mot-de-passe\/oublie$/);
  const confirmation = page.getByRole('status');
  await expect(confirmation).toContainText('Si cette adresse correspond à un compte');

  const project = process.env.BROWSER_ROUTER;
  expect(project).toMatch(/^genesis-browser-test-[0-9]+-[0-9]+$/);
  const token = 'BrowserResetToken_0123456789abcdef0123456789';
  const addToken = "INSERT INTO player_password_reset_token (player_id, token_hash, created_at, expires_at) SELECT id, SHA2('" + token + "', 256), UTC_TIMESTAMP(), UTC_TIMESTAMP()+INTERVAL 1 HOUR FROM player WHERE email='" + account.email + "'";
  sql(project, addToken);

  const invalid = await page.goto('/mot-de-passe/reinitialiser/invalid-token');
  expect(invalid?.status()).toBe(400);
  expect(invalid.headers()['referrer-policy']).toBe('no-referrer');
  expect(invalid.headers()['cache-control']).toContain('no-store');
  await expect(page.getByRole('heading', { name: 'Lien expiré ou invalide', exact: true })).toBeVisible();

  const validUrl = '/mot-de-passe/reinitialiser/' + token;
  const valid = await page.goto(validUrl);
  expect(valid?.status()).toBe(200);
  expect(valid.headers()['referrer-policy']).toBe('no-referrer');
  await page.getByLabel('Nouveau mot de passe (12 caractères minimum)').fill('Replacement-Password-2026!');
  await page.getByLabel('Confirmer le nouveau mot de passe').fill('Replacement-Password-2026!');
  await page.getByRole('button', { name: 'Modifier le mot de passe' }).click();
  await expect(page).toHaveURL(/connexion$/);
  await expect(page.getByRole('status')).toContainText('Mot de passe modifié');

  const reused = await page.goto(validUrl);
  expect(reused?.status()).toBe(400);
  await expect(page.getByRole('heading', { name: 'Lien expiré ou invalide', exact: true })).toBeVisible();

  await page.goto('/connexion');
  await page.getByLabel('Adresse email').fill(account.email);
  await page.getByLabel('Mot de passe', { exact: true }).fill('Replacement-Password-2026!');
  await page.getByRole('button', { name: /Connexion/ }).click();
  await expect(page.locator('article.universe-card').first()).toBeVisible();
});
