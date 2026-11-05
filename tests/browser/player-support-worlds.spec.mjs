import { test, expect } from './support/isolated-test.mjs';
import { execFileSync } from 'node:child_process';
import { runIsolatedSql, queryIsolatedSql } from './support/game-fixtures.mjs';

const quote = value => "'" + String(value).replaceAll("'", "''") + "'";

async function enter(page) {
  const id = crypto.randomUUID().slice(0, 8);
  const email = `support-${id}@example.test`;
  const password = 'Browser-test-2026!';
  await page.goto('/inscription');
  await page.getByLabel('Adresse email').fill(email);
  await page.getByLabel('Nom de commandant').fill(`Support-${id}`);
  await page.getByLabel('Mot de passe (12 caractères minimum)', { exact: true }).fill(password);
  await page.getByLabel('Confirmer le mot de passe').fill(password);
  await page.getByRole('button', { name: 'Créer mon compte' }).click();
  await expect(page).toHaveURL(/connexion/);
  await page.getByLabel('Adresse email').fill(email);
  await page.getByLabel('Mot de passe', { exact: true }).fill(password);
  await page.getByRole('button', { name: /Connexion/ }).click();
  await expect(page).toHaveURL(/univers$/);
  return { email, password };
}

async function join(page) {
  await page.locator('article.universe-card').filter({ has: page.getByRole('button') }).first().getByRole('button').click();
  await expect(page).toHaveURL(/planetes\/\d+$/);
}

test('Écrans joueur : captures univers, événements et retour', async ({ page }, testInfo) => {
  await enter(page);
  for (const [route, name] of [['/univers', 'universes'], ['/evenements', 'events'], ['/feedback', 'feedback']]) {
    if (route === '/evenements') await join(page);
    await page.goto(route);
    await expect(page.locator('main h1')).toBeVisible();
    for (const width of [1920, 1440, 1280, 768, 390]) {
      await page.setViewportSize({ width, height: 900 });
      expect(await page.evaluate(() => document.documentElement.scrollWidth)).toBeLessThanOrEqual(width);
      if ([1440, 390].includes(width)) await page.screenshot({ path: testInfo.outputPath(`${name}-${width}.png`), fullPage: true, animations: 'disabled' });
    }
  }
});

test('Événements : périodes, portée et contenu visible au joueur', async ({ page }, testInfo) => {
  const { email } = await enter(page);
  await join(page);
  await page.goto('/evenements');
  await expect(page.getByRole('heading', { name: 'Aucun événement annoncé' })).toBeVisible();
  const project = process.env.BROWSER_ROUTER;
  const universeId = Number(queryIsolatedSql(project, `SELECT pu.universe_id FROM player_universe pu JOIN player p ON p.id=pu.player_id WHERE p.email=${quote(email)}`));
  const otherId = Number(queryIsolatedSql(project, `SELECT id FROM universe WHERE id<>${universeId} LIMIT 1`));
  const title = 'Production renforcée ' + 'interstellaire'.repeat(6);
  const description = 'Première ligne de présentation.\nDeuxième ligne : ' + 'Trajectoire'.repeat(35);
  for (const [name, status, scope, visible, start, end, type] of [
    [title, 'active', 'NULL', 1, '-1 HOUR', '2 DAY', 'production_bonus'],
    ['Rencontre prochaine', 'planned', universeId, 1, '1 DAY', '3 DAY', 'announcement'],
    ['Événement masqué', 'active', universeId, 0, '-1 HOUR', '2 DAY', 'announcement'],
    ['Événement terminé', 'active', universeId, 1, '-2 DAY', '-1 DAY', 'announcement'],
    ['Événement brouillon', 'draft', universeId, 1, '-1 HOUR', '2 DAY', 'announcement'],
    ['Événement autre univers', 'active', otherId, 1, '-1 HOUR', '2 DAY', 'announcement'],
  ]) {
    runIsolatedSql(project, `INSERT INTO game_event (universe_id,name,description,type,status,starts_at,ends_at,parameters,visible_to_players)
      VALUES (${scope},${quote(name)},${quote(description)},${quote(type)},${quote(status)},DATE_ADD(UTC_TIMESTAMP(), INTERVAL ${start}),DATE_ADD(UTC_TIMESTAMP(), INTERVAL ${end}),'{"percentage":25}',${visible})`);
  }
  await page.reload();
  await expect(page.locator('.event-card')).toHaveCount(2);
  const active = page.getByRole('article', { name: title });
  await expect(active).toContainText('En cours');
  await expect(active).toContainText('Tous les univers');
  await expect(active).toContainText('+25 %');
  await expect(active.locator('time')).toHaveCount(2);
  await expect(page.getByRole('article', { name: 'Rencontre prochaine' })).toContainText('À venir');
  for (const width of [1920, 1440, 1280, 768, 390]) {
    await page.setViewportSize({ width, height: 900 });
    expect(await page.evaluate(() => document.documentElement.scrollWidth)).toBeLessThanOrEqual(width);
  }
  await page.evaluate(() => window.scrollTo(0, 0));
  await page.screenshot({ path: testInfo.outputPath('events-populated-390.png'), fullPage: true, animations: 'disabled' });
});

test('Retour joueur : erreur, saisies conservées, pièce jointe et suivi admin', async ({ page }, testInfo) => {
  const { email, password } = await enter(page);
  await join(page);
  const sourceUrl = page.url();
  await page.getByRole('link', { name: 'Envoyer un feedback', exact: true }).click();
  await expect(page.getByLabel('Lien de la page concernée')).toHaveValue(sourceUrl);
  const contextUrl = new URL('/galaxie?system=2', sourceUrl).href;
  await page.getByLabel('Lien de la page concernée').fill(contextUrl);
  const form = page.locator('.feedback-form');
  await page.getByLabel('Type', { exact: true }).focus();
  await page.keyboard.press('Tab');
  await expect(page.getByLabel('Priorité ressentie')).toBeFocused();
  await page.getByLabel('Type', { exact: true }).selectOption('suggestion');
  await page.getByLabel('Priorité ressentie').selectOption('high');
  await page.getByLabel('Description détaillée').fill('Comparer plus facilement les trajectoires de flotte.');
  await form.evaluate(element => { element.noValidate = true; });
  await page.getByRole('button', { name: 'Envoyer', exact: true }).click();
  await expect(page.getByRole('alert')).toContainText('Le titre est obligatoire');
  await expect(page.getByLabel('Lien de la page concernée')).toHaveValue(contextUrl);
  await expect(page.getByLabel('Type', { exact: true })).toHaveValue('suggestion');
  await expect(page.getByLabel('Priorité ressentie')).toHaveValue('high');
  await expect(page.getByLabel('Description détaillée')).toHaveValue('Comparer plus facilement les trajectoires de flotte.');
  await page.setViewportSize({ width: 390, height: 900 });
  await page.screenshot({ path: testInfo.outputPath('feedback-error-390.png'), fullPage: true, animations: 'disabled' });
  const title = 'Trajectoires UI ' + crypto.randomUUID().slice(0, 8);
  await page.getByLabel('Titre', { exact: true }).fill(title);
  await page.getByLabel('Joindre une capture').setInputFiles({ name: 'capture.txt', mimeType: 'text/plain', buffer: Buffer.from('format refusé') });
  await page.getByRole('button', { name: 'Envoyer', exact: true }).click();
  await expect(page.getByRole('alert')).toContainText('Format refusé');
  await expect(page.getByLabel('Titre', { exact: true })).toHaveValue(title);
  await page.getByLabel('Joindre une capture').setInputFiles({ name: 'capture.png', mimeType: 'image/png', buffer: Buffer.from('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+j0ioAAAAASUVORK5CYII=', 'base64') });
  await page.getByRole('button', { name: 'Envoyer', exact: true }).click();
  await expect(page.locator('.flash.success')).toContainText('a bien été envoyé');
  const project = process.env.BROWSER_ROUTER;
  const id = Number(queryIsolatedSql(project, `SELECT id FROM user_feedback WHERE title=${quote(title)}`));
  expect(queryIsolatedSql(project, `SELECT context_url FROM user_feedback WHERE id=${id}`)).toBe(contextUrl);
  expect(await page.evaluate(async path => (await fetch(path)).status, `/feedback/${id}/capture`)).toBe(200);
  execFileSync('docker', ['compose', '--project-name', project, '--env-file', '.env.local', 'exec', '-T', 'app', 'php', 'bin/console', 'app:admin:grant', email], { stdio: ['ignore', 'pipe', 'pipe'] });
  await page.context().clearCookies();
  await page.goto('/connexion');
  await page.getByLabel('Adresse email').fill(email);
  await page.getByLabel('Mot de passe', { exact: true }).fill(password);
  await page.getByRole('button', { name: /Connexion/ }).click();
  await page.goto(`/admin/feedbacks/${id}`);
  await expect(page.locator('main')).toContainText(title);
  await expect(page.locator('main')).toContainText('Comparer plus facilement les trajectoires de flotte.');
  expect(queryIsolatedSql(project, `SELECT CONCAT(type,':',priority) FROM user_feedback WHERE id=${id}`)).toBe('suggestion:high');
});

test('Univers : sélection actuelle, ouverture datée sans JS et accès fermé', async ({ page, browser }, testInfo) => {
  const { email } = await enter(page);
  await join(page);
  const project = process.env.BROWSER_ROUTER;
  const currentId = Number(queryIsolatedSql(project, `SELECT pu.universe_id FROM player_universe pu JOIN player p ON p.id=pu.player_id WHERE p.email=${quote(email)}`));
  const otherId = Number(queryIsolatedSql(project, `SELECT id FROM universe WHERE id<>${currentId} LIMIT 1`));
  runIsolatedSql(project, `UPDATE universe SET starts_at=DATE_ADD(UTC_TIMESTAMP(),INTERVAL 2 DAY),status='active' WHERE id=${otherId}`);
  await page.goto('/univers');
  await expect(page.locator('.universe-card--current')).toContainText('Univers actuel');
  await expect(page.locator('.universe-card--current').getByRole('button')).toContainText('Continuer');
  const planned = page.locator('.universe-card').filter({ has: page.getByRole('timer') });
  await expect(planned).toContainText('OUVERTURE PROGRAMMÉE');
  await expect(planned.locator('time')).toContainText('UTC');
  await expect(planned.getByRole('button')).toHaveCount(0);
  await expect(page.getByRole('timer')).not.toContainText('Calcul');
  await page.setViewportSize({ width: 390, height: 900 });
  await page.screenshot({ path: testInfo.outputPath('universes-planned-390.png'), fullPage: true, animations: 'disabled' });
  const context = await browser.newContext({ javaScriptEnabled: false, storageState: await page.context().storageState() });
  const fallback = await context.newPage();
  await fallback.goto('/univers');
  await expect(fallback.getByRole('timer')).toContainText('UTC');
  await context.close();
  runIsolatedSql(project, `UPDATE universe SET status='closed',starts_at=NULL WHERE id=${otherId}`);
  await page.reload();
  await expect(page.getByText('Cet univers n’est pas accessible actuellement.')).toBeVisible();
  await expect(page.locator('.universe-card').filter({ hasText: 'INACCESSIBLE' }).getByRole('button')).toHaveCount(0);
  await page.locator('.universe-card--current input[name="_token"]').evaluate(input => { input.value = 'invalid'; });
  const deniedResponse = page.waitForResponse(response => response.url().endsWith(`/univers/${currentId}/rejoindre`) && response.request().method() === 'POST');
  await page.locator('.universe-card--current').getByRole('button').click();
  expect((await deniedResponse).status()).toBe(403);
  await expect(page.getByRole('heading', { name: 'Accès refusé' })).toBeVisible();
  expect(queryIsolatedSql(project, `SELECT COUNT(*) FROM player_universe pu JOIN player p ON p.id=pu.player_id WHERE p.email=${quote(email)}`)).toBe('1');
});

test('Écrans joueur : thème clair compact', async ({ page }, testInfo) => {
  const { email } = await enter(page);
  await join(page);
  runIsolatedSql(process.env.BROWSER_ROUTER, `UPDATE player SET preferences=JSON_SET(CASE WHEN JSON_TYPE(preferences)='OBJECT' THEN preferences ELSE JSON_OBJECT() END,'$.theme','light') WHERE email=${quote(email)}`);
  await page.setViewportSize({ width: 390, height: 900 });
  for (const [route, name] of [['/feedback', 'feedback'], ['/evenements', 'events'], ['/univers', 'universes']]) {
    await page.goto(route);
    await expect(page.locator('body')).toHaveAttribute('data-theme', 'light');
    expect(await page.evaluate(() => document.documentElement.scrollWidth)).toBeLessThanOrEqual(390);
    await page.screenshot({ path: testInfo.outputPath(`${name}-light-390.png`), fullPage: true, animations: 'disabled' });
  }
});
