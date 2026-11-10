import { test, expect } from './support/isolated-test.mjs';
import { execFileSync } from 'node:child_process';
import { queryIsolatedSql, runIsolatedSql } from './support/game-fixtures.mjs';
import AxeBuilder from '@axe-core/playwright';

async function universe(page) {
  const suffix = crypto.randomUUID().slice(0, 8);
  const email = `settings-${suffix}@example.test`;
  const password = 'Browser-test-2026!';
  await page.goto('/inscription');
  await page.getByLabel('Adresse email').fill(email);
  await page.getByLabel('Nom de commandant').fill(`Settings-${suffix}`);
  await page.getByLabel('Mot de passe (12 caractères minimum)', { exact: true }).fill(password);
  await page.getByLabel('Confirmer le mot de passe').fill(password);
  await page.getByRole('button', { name: 'Créer mon compte' }).click();
  await expect(page).toHaveURL(/connexion/);
  execFileSync('docker', ['compose', '--project-name', process.env.BROWSER_ROUTER, '--env-file', '.env.local', 'exec', '-T', 'app', 'php', 'bin/console', 'app:admin:grant', email], { stdio: ['ignore', 'pipe', 'pipe'] });
  await page.getByLabel('Adresse email').fill(email);
  await page.getByLabel('Mot de passe', { exact: true }).fill(password);
  await page.getByRole('button', { name: /Connexion/ }).click();
  await expect(page).toHaveURL(/univers$/);
  await page.goto('/admin#admin-universe-create');
  const creation = page.locator('#admin-universe-create');
  const slug = 'settings-' + suffix;
  await creation.getByLabel('Nom affiché').fill('Univers paramètres UI');
  await creation.getByLabel('Code unique').fill(slug);
  await creation.getByLabel('Nombre de galaxies').fill('1');
  await creation.getByLabel('Systèmes par galaxie').fill('1');
  await creation.getByRole('button', { name: 'Créer l’univers' }).click();
  const id = Number(queryIsolatedSql(process.env.BROWSER_ROUTER, `SELECT id FROM universe WHERE slug='${slug}'`));
  const card = page.locator('#admin-universe-' + id);
  await card.locator('.admin-universe-settings>summary').click();
  return { id, card, form: card.locator('form[action$="/settings"]') };
}

test('Paramètres : capture des vitesses affichées après sélection du profil', async ({ page }, testInfo) => {
  const { form } = await universe(page);
  await form.getByLabel('Profil de vitesse').selectOption('x2');
  console.log('Vitesses affichées après ×2 : ' + JSON.stringify(await form.locator('[data-admin-speed-target="multiplier"]').evaluateAll(inputs => inputs.map(input => ({ name: input.name, value: input.value })))));
  for (const width of [1440, 390]) {
    await page.setViewportSize({ width, height: 900 });
    await form.scrollIntoViewIfNeeded();
    await page.screenshot({ path: testInfo.outputPath(`universe-settings-${width}.png`), animations: 'disabled' });
  }
});

test('Paramètres : profils cohérents, erreur conservée et sauvegarde temporaire', async ({ page }, testInfo) => {
  const { id, card, form } = await universe(page);
  const project = process.env.BROWSER_ROUTER;
  runIsolatedSql(project, `UPDATE universe SET game_settings=JSON_SET(game_settings,'$.catalog_overrides',JSON_OBJECT('buildings',JSON_OBJECT('power_plant',JSON_OBJECT('max_level',7)))) WHERE id=${id}`);
  await page.reload();
  await card.locator('.admin-universe-settings>summary').click();
  await form.getByLabel('Profil de vitesse').selectOption('x2');
  for (const input of await form.locator('[data-admin-speed-target="multiplier"]').all()) await expect(input).toHaveValue('2');
  await form.getByRole('button', { name: 'Enregistrer les règles' }).click();
  await expect(page.locator('.flash.success')).toContainText('Paramètres de l’univers enregistrés');
  await expect(page).toHaveURL(new RegExp('#admin-universe-' + id + '$'));
  await expect(card.locator('.admin-universe-facts')).toContainText('x2');
  const load = () => JSON.parse(queryIsolatedSql(project, `SELECT game_settings FROM universe WHERE id=${id}`));
  const preset = load();
  expect(preset.multipliers.building_time).toBe(.5);
  expect(preset.multipliers.research_time).toBe(.5);
  expect(preset.multipliers.ship_build_time).toBe(.5);
  expect(preset.multipliers.travel_time).toBe(.5);
  expect(preset.multipliers.resource_production).toBe(2);
  await card.locator('.admin-universe-settings>summary').click();
  await expect(form.getByLabel('Profil de vitesse')).toHaveValue('x2');
  await form.getByLabel('Vitesse bâtiments (×)', { exact: true }).fill('0');
  await form.getByLabel('Colonies maximum', { exact: true }).fill('6');
  await form.getByLabel('Vaisseaux maximum par flotte', { exact: true }).fill('120');
  await form.getByLabel('Guildes autorisées', { exact: true }).uncheck();
  await form.evaluate(element => { element.noValidate = true; });
  await form.getByRole('button', { name: 'Enregistrer les règles' }).click();
  await expect(card.locator('.admin-universe-settings')).toHaveAttribute('open', '');
  await expect(form.locator('.admin-draft-notice')).toContainText('Chaque vitesse doit être comprise');
  await expect(form.getByLabel('Vitesse bâtiments (×)', { exact: true })).toHaveValue('0');
  await expect(form.getByLabel('Colonies maximum', { exact: true })).toHaveValue('6');
  await expect(form.getByLabel('Vaisseaux maximum par flotte', { exact: true })).toHaveValue('120');
  await expect(form.getByLabel('Guildes autorisées', { exact: true })).not.toBeChecked();
  expect(load()).toEqual(preset);
  await page.setViewportSize({ width: 390, height: 900 });
  await form.scrollIntoViewIfNeeded();
  await page.screenshot({ path: testInfo.outputPath('universe-settings-error-390.png'), animations: 'disabled' });
  await form.getByLabel('Vitesse bâtiments (×)', { exact: true }).fill('3');
  await expect(form.getByLabel('Profil de vitesse')).toHaveValue('custom');
  await form.getByRole('button', { name: 'Enregistrer les règles' }).click();
  const custom = load();
  expect(custom.multipliers.building_time).toBeCloseTo(1 / 3, 8);
  expect(custom.multipliers.resource_production).toBe(2);
  expect(custom.limits).toEqual({ max_planets: 6, max_fleet_size: 120 });
  expect(custom.features.guilds_enabled).toBe(false);
  expect(custom.features.planet_conquest).toBe(true);
  expect(custom.catalog_overrides).toEqual(preset.catalog_overrides);
  await expect(card.locator('.admin-universe-facts')).toContainText('Personnalisé');
  await card.locator('.admin-universe-settings>summary').click();
  await expect(form.getByLabel('Vitesse bâtiments (×)', { exact: true })).toHaveValue('3');
  for (const width of [1920, 1440, 1280, 768, 390]) {
    await page.setViewportSize({ width, height: 900 });
    expect(await page.evaluate(() => document.documentElement.scrollWidth)).toBeLessThanOrEqual(width);
    const toggles = await form.locator('input[type="checkbox"]').evaluateAll(inputs => inputs.map(input => ({ width: input.getBoundingClientRect().width, height: input.getBoundingClientRect().height })));
    for (const toggle of toggles) expect(toggle).toEqual({ width: 18, height: 18 });
  }
  const accessibility = await new AxeBuilder({ page }).include('#admin-universe-' + id).withTags(['wcag2a', 'wcag2aa', 'wcag21a', 'wcag21aa', 'wcag22aa']).analyze();
  await testInfo.attach('settings-accessibility.json', { body: JSON.stringify(accessibility.violations, null, 2), contentType: 'application/json' });
  expect(accessibility.violations).toEqual([]);
  await form.scrollIntoViewIfNeeded();
  await page.screenshot({ path: testInfo.outputPath('universe-settings-saved-390.png'), animations: 'disabled' });
});

test('Paramètres hérités : valeurs hors plage conservées, refus expliqué et profil explicite', async ({ page, browser }, testInfo) => {
  const { id, card, form } = await universe(page);
  const project = process.env.BROWSER_ROUTER;
  runIsolatedSql(project, `UPDATE universe SET game_settings=JSON_SET(game_settings,'$.multipliers.building_time',0.01,'$.multipliers.research_time',0.01,'$.multipliers.ship_build_time',0.01,'$.multipliers.travel_time',0.01) WHERE id=${id}`);
  const load = () => queryIsolatedSql(project, `SELECT game_settings FROM universe WHERE id=${id}`);
  const original = load();
  await page.reload();
  await card.locator('.admin-universe-settings>summary').click();
  await expect(form.getByLabel('Vitesse bâtiments (×)', { exact: true })).toHaveValue('100');
  await expect(form.locator('.admin-range-notice')).toContainText('Vitesses enregistrées hors plage');
  await expect(form.locator('.admin-range-notice')).toContainText('sans ajustement automatique');
  await expect(form.getByLabel('Vitesse bâtiments (×)', { exact: true })).toHaveAttribute('aria-describedby', 'settings-range-' + id);
  expect(await form.evaluate(element => element.checkValidity())).toBe(false);
  for (const width of [1920, 1440, 1280, 768, 390]) {
    await page.setViewportSize({ width, height: 900 });
    expect(await page.evaluate(() => document.documentElement.scrollWidth)).toBeLessThanOrEqual(width);
    if ([1440, 390].includes(width)) {
      await form.locator('.admin-range-notice').scrollIntoViewIfNeeded();
      await page.screenshot({ path: testInfo.outputPath(`settings-outside-range-${width}.png`), animations: 'disabled' });
    }
  }
  expect(load()).toBe(original);
  await form.getByRole('button', { name: 'Enregistrer les règles' }).click();
  await expect(form.getByLabel('Vitesse bâtiments (×)', { exact: true })).toBeFocused();
  expect(load()).toBe(original);
  await form.evaluate(element => { element.noValidate = true; });
  await form.getByRole('button', { name: 'Enregistrer les règles' }).click();
  await expect(form.locator('.admin-draft-notice')).toContainText('Chaque vitesse doit être comprise');
  await expect(form.getByLabel('Vitesse bâtiments (×)', { exact: true })).toHaveValue('100');
  expect(load()).toBe(original);
  await page.waitForLoadState('load');
  await form.getByLabel('Profil de vitesse').selectOption('alpha');
  for (const input of await form.locator('[data-admin-speed-target="multiplier"]').all()) await expect(input).toHaveValue('40');
  expect(load()).toBe(original);
  await form.getByRole('button', { name: 'Enregistrer les règles' }).click();
  await expect(page.locator('.flash.success')).toContainText('Paramètres de l’univers enregistrés');
  const saved = JSON.parse(load());
  expect(saved.multipliers.building_time).toBe(.025);
  expect(saved.multipliers.resource_production).toBe(40);
  await expect(card.locator('.admin-universe-facts')).toContainText('Alpha');
  await card.locator('.admin-universe-settings>summary').click();
  await expect(form.locator('.admin-range-notice')).toHaveCount(0);
  const context = await browser.newContext({ storageState: await page.context().storageState(), javaScriptEnabled: false, viewport: { width: 390, height: 900 } });
  const fallback = await context.newPage();
  await fallback.goto('/admin/univers#admin-universe-' + id);
  const fallbackCard = fallback.locator('#admin-universe-' + id);
  await fallbackCard.locator('.admin-universe-settings>summary').click();
  const fallbackForm = fallbackCard.locator('form[action$="/settings"]');
  const fallbackHelp = fallbackForm.locator('noscript p');
  await expect(fallbackHelp).toBeVisible();
  await expect(fallbackHelp).toContainText('Sans JavaScript, le profil ne remplit pas les champs');
  await fallbackHelp.scrollIntoViewIfNeeded();
  await fallback.screenshot({ path: testInfo.outputPath('settings-nojs-390.png'), animations: 'disabled' });
  await fallbackForm.getByLabel('Profil de vitesse').selectOption('x2');
  await expect(fallbackForm.getByLabel('Vitesse bâtiments (×)', { exact: true })).toHaveValue('40');
  for (const input of await fallbackForm.locator('[data-admin-speed-target="multiplier"]').all()) await input.fill('2');
  await fallbackForm.getByRole('button', { name: 'Enregistrer les règles' }).click();
  await expect(fallback.locator('.flash.success')).toContainText('Paramètres de l’univers enregistrés');
  expect(JSON.parse(load()).multipliers.building_time).toBe(.5);
  expect(JSON.parse(load()).multipliers.resource_production).toBe(2);
  await context.close();
});

test('Paramètres fermés : lecture clavier et sans JS, contrôles désactivés et refus serveur', async ({ page, browser }, testInfo) => {
  const { id, card, form } = await universe(page);
  const project = process.env.BROWSER_ROUTER;
  runIsolatedSql(project, `UPDATE universe SET status='closed' WHERE id=${id}`);
  const original = queryIsolatedSql(project, `SELECT game_settings FROM universe WHERE id=${id}`);
  await page.reload();
  const summary = card.locator('.admin-universe-settings>summary');
  await summary.focus();
  await page.keyboard.press('Enter');
  await expect(form).toBeVisible();
  await expect(form).toContainText('Cet univers est fermé');
  for (const control of await form.locator('input:not([type=hidden]), select, button').all()) await expect(control).toBeDisabled();
  for (const width of [1920, 1440, 1280, 768, 390]) {
    await page.setViewportSize({ width, height: 900 });
    expect(await page.evaluate(() => document.documentElement.scrollWidth)).toBeLessThanOrEqual(width);
    if ([1440, 390].includes(width)) {
      await form.scrollIntoViewIfNeeded();
      await page.screenshot({ path: testInfo.outputPath(`settings-closed-${width}.png`), animations: 'disabled' });
    }
  }
  const action = await form.getAttribute('action');
  const rejected = page.waitForResponse(response => response.url().endsWith(action) && response.request().method() === 'POST');
  await form.evaluate(element => {
    for (const fieldset of element.querySelectorAll('fieldset')) fieldset.disabled = false;
    element.requestSubmit();
  });
  expect((await rejected).status()).toBe(302);
  await expect(form.locator('.admin-draft-notice')).toContainText('Univers introuvable ou fermé');
  const csrfStatus = await page.evaluate(async path => (await fetch(path, { method: 'POST', body: new URLSearchParams({ _token: 'invalid' }) })).status, action);
  expect(csrfStatus).toBe(403);
  expect(queryIsolatedSql(project, `SELECT game_settings FROM universe WHERE id=${id}`)).toBe(original);
  expect(queryIsolatedSql(project, `SELECT status FROM universe WHERE id=${id}`)).toBe('closed');
  const context = await browser.newContext({ storageState: await page.context().storageState(), javaScriptEnabled: false, viewport: { width: 390, height: 900 } });
  const fallback = await context.newPage();
  await fallback.goto('/admin/univers#admin-universe-' + id);
  const fallbackCard = fallback.locator('#admin-universe-' + id);
  await fallbackCard.locator('.admin-universe-settings>summary').click();
  await expect(fallbackCard.getByText('Cet univers est fermé; ses paramètres ne peuvent plus être modifiés.')).toBeVisible();
  await expect(fallbackCard.getByRole('button', { name: 'Enregistrer les règles' })).toBeDisabled();
  await context.close();
});
