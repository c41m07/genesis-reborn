import { test, expect } from './support/isolated-test.mjs';
import { execFileSync } from 'node:child_process';

async function registerAndJoin(page, prefix) {
  const id = crypto.randomUUID().slice(0, 8);
  const email = prefix.toLowerCase() + '-' + id + '@example.test';
  const username = prefix + '-' + id;
  const password = 'Browser-test-2026!';
  await page.goto('/inscription');
  await page.getByLabel('Adresse email').fill(email);
  await page.getByLabel('Nom de commandant').fill(username);
  await page.getByLabel('Mot de passe (12 caractères minimum)', { exact: true }).fill(password);
  await page.getByLabel('Confirmer le mot de passe').fill(password);
  await page.getByRole('button', { name: 'Créer mon compte' }).click();
  await expect(page).toHaveURL(/connexion/);
  await page.getByLabel('Adresse email').fill(email);
  await page.getByLabel('Mot de passe', { exact: true }).fill(password);
  await page.getByRole('button', { name: /Connexion/ }).click();
  await page.locator('article.universe-card').filter({ has: page.getByRole('heading', { name: /Turbo/ }) }).getByRole('button').click();
  await expect(page).toHaveURL(/planetes\/\d+$/);
  return { email, username, password };
}

async function login(page, credentials) {
  await page.context().clearCookies();
  await page.goto('/connexion');
  await page.getByLabel('Adresse email').fill(credentials.email);
  await page.getByLabel('Mot de passe', { exact: true }).fill(credentials.password);
  await page.getByRole('button', { name: /Connexion/ }).click();
}

test('Modération : signaler un message, refuser un CSRF invalide et classer le dossier', async ({ page, browser }, testInfo) => {
  test.setTimeout(120000);
  const reporterPage = await browser.newPage();
  const sender = await registerAndJoin(page, 'Moderateur');
  const reporter = await registerAndJoin(reporterPage, 'Signalant');

  const body = 'Message de test à modérer ' + crypto.randomUUID();
  await page.goto('/messages');
  await page.getByLabel('Rechercher un joueur').fill(reporter.username);
  await page.getByRole('button', { name: 'Rechercher', exact: true }).click();
  await page.getByRole('region', { name: 'Résultats de recherche' }).getByRole('link', { name: new RegExp(reporter.username) }).click();
  await page.getByLabel('Votre message').fill(body);
  await page.getByRole('button', { name: 'Envoyer', exact: true }).click();
  await expect(page.getByRole('status')).toContainText('Message privé envoyé');

  await reporterPage.goto('/messages');
  await reporterPage.getByRole('link', { name: new RegExp(sender.username) }).click();
  const message = reporterPage.locator('.message-thread article').filter({ hasText: body });
  await expect(message).toBeVisible();
  await reporterPage.setViewportSize({ width: 390, height: 900 });
  expect(await reporterPage.evaluate(() => document.documentElement.scrollWidth)).toBeLessThanOrEqual(390);
  await expect(message.locator('.message-report')).not.toHaveAttribute('open', '');
  await reporterPage.screenshot({ path: testInfo.outputPath('messages-390.png'), fullPage: true, animations: 'disabled' });
  await message.locator('.message-report summary').focus();
  await reporterPage.keyboard.press('Enter');
  await expect(message.getByLabel('Motif du signalement')).toBeVisible();
  await message.getByLabel('Motif du signalement').fill('Vérification du parcours de modération');
  await message.getByRole('button', { name: 'Signaler', exact: true }).click();
  await expect(reporterPage.getByRole('status')).toContainText('Signalement transmis à la modération');

  const project = process.env.BROWSER_ROUTER;
  expect(project).toMatch(/^genesis-browser-test-[0-9]+-[0-9]+$/);
  execFileSync('docker', ['compose', '--project-name', project, '--env-file', '.env.local', 'exec', '-T', 'app', 'php', 'bin/console', 'app:admin:grant', sender.email], { encoding: 'utf8', stdio: ['ignore', 'pipe', 'pipe'] });
  await login(page, sender);
  await page.goto('/admin');
  await page.getByRole('tab', { name: 'Modération', exact: true }).click();
  await expect(page.getByRole('heading', { name: 'Signalements de messages', exact: true })).toBeVisible();
  const report = page.locator('article.panel').filter({ hasText: body }).last();
  await expect(report).toContainText('Vérification du parcours de modération');
  const action = await report.locator('form').getAttribute('action');
  const invalidCsrfStatus = await page.evaluate(async path => {
    const response = await fetch(path, { method: 'POST', headers: { 'Content-Type': 'application/x-www-form-urlencoded' }, body: new URLSearchParams({ _token: 'csrf-invalide', decision: 'dismiss' }) });
    return response.status;
  }, new URL(action, page.url()).pathname);
  expect(invalidCsrfStatus).toBe(403);
  const csrfToken = await report.locator('input[name="_token"]').inputValue();
  const invalidDecision = await page.evaluate(async ({ path, token }) => {
    const response = await fetch(path, { method: 'POST', headers: { 'Content-Type': 'application/x-www-form-urlencoded' }, body: new URLSearchParams({ _token: token, decision: 'unknown' }) });
    return { status: response.status, html: await response.text() };
  }, { path: new URL(action, page.url()).pathname, token: csrfToken });
  expect(invalidDecision.status).toBe(200);
  expect(invalidDecision.html).toContain('role="alert"');
  expect(invalidDecision.html).toContain('Décision de modération inconnue.');
  await report.getByRole('button', { name: 'Classer sans suite' }).click();
  await page.reload();
  await expect(page.locator('main')).toContainText('Aucun signalement à traiter.');

  await reporterPage.goto('/messages');
  await reporterPage.getByRole('link', { name: new RegExp(sender.username) }).click();
  const secondMessage = reporterPage.locator('.message-thread article').filter({ hasText: body });
  await secondMessage.locator('.message-report summary').click();
  await secondMessage.getByLabel('Motif du signalement').fill('Vérification du masquage après modération');
  await secondMessage.getByRole('button', { name: 'Signaler', exact: true }).click();
  await expect(reporterPage.getByRole('status')).toContainText('Signalement transmis à la modération');
  await page.reload();
  await page.getByRole('tab', { name: 'Modération', exact: true }).click();
  const secondReport = page.locator('article.panel').filter({ hasText: body }).last();
  await expect(secondReport).toContainText('Vérification du masquage après modération');
  await secondReport.getByRole('button', { name: 'Masquer le message' }).click();
  await page.reload();
  await expect(page.locator('main')).toContainText('Aucun signalement à traiter.');

  await reporterPage.reload();
  await expect(reporterPage.locator('.message-thread')).toContainText('[Message masqué après modération]');
  await expect(reporterPage.getByLabel('Motif du signalement')).toHaveCount(0);
  await reporterPage.close();
});
