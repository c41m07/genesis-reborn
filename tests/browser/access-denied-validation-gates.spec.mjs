import { test, expect } from './support/isolated-test.mjs';
import { execFileSync } from 'node:child_process';
import { queryIsolatedSql, runIsolatedSql } from './support/game-fixtures.mjs';
import AxeBuilder from '@axe-core/playwright';

async function signIn(page, admin = false, join = true) {
  const suffix = crypto.randomUUID().slice(0, 8);
  const email = `denied-${suffix}@example.test`;
  const password = 'Browser-test-2026!';
  await page.goto('/inscription');
  await page.getByLabel('Adresse email').fill(email);
  await page.getByLabel('Nom de commandant').fill('Denied-' + suffix);
  await page.getByLabel('Mot de passe (12 caractères minimum)', { exact: true }).fill(password);
  await page.getByLabel('Confirmer le mot de passe').fill(password);
  await page.getByRole('button', { name: 'Créer mon compte' }).click();
  await expect(page).toHaveURL(/connexion$/);
  if (admin) execFileSync('docker', ['compose', '--project-name', process.env.BROWSER_ROUTER, '--env-file', '.env.local', 'exec', '-T', 'app', 'php', 'bin/console', 'app:admin:grant', email], { stdio: ['ignore', 'pipe', 'pipe'] });
  await page.getByLabel('Adresse email').fill(email);
  await page.getByLabel('Mot de passe', { exact: true }).fill(password);
  await page.getByRole('button', { name: /Connexion/ }).click();
  await expect(page).toHaveURL(/univers$/);
  const card = page.locator('article.universe-card').filter({ has: page.getByRole('button') }).first();
  const action = await card.locator('form').getAttribute('action');
  if (join) {
    await card.getByRole('button').click();
    await expect(page).toHaveURL(/planetes\/\d+$/);
  }
  return { email, action, gameUrl: page.url() };
}

async function inspect(page, testInfo, name, actionName) {
  for (const width of [1920, 1440, 1280, 768, 390]) {
    await page.setViewportSize({ width, height: 900 });
    expect(await page.evaluate(() => document.documentElement.scrollWidth)).toBeLessThanOrEqual(width);
    const action = page.locator('main').getByRole('link', { name: actionName, exact: true });
    expect((await action.boundingBox()).height).toBeGreaterThanOrEqual(44);
    if ([1440, 390].includes(width)) await page.screenshot({ path: testInfo.outputPath(`${name}-${width}.png`), fullPage: true, animations: 'disabled' });
    if ([1440, 768, 390].includes(width)) {
      const report = await new AxeBuilder({ page }).withTags(['wcag2a', 'wcag2aa', 'wcag21a', 'wcag21aa', 'wcag22aa', 'best-practice']).analyze();
      expect(report.violations).toEqual([]);
    }
  }
}

async function keyboardReturn(page, name, autonomous = false) {
  await page.keyboard.press('Tab');
  await expect(page.getByRole('link', { name: autonomous ? 'Aller au contenu principal' : 'Aller au contenu', exact: true })).toBeFocused();
  await page.keyboard.press('Enter');
  await page.keyboard.press('Tab');
  const action = page.locator('main').getByRole('link', { name, exact: true });
  await expect(action).toBeFocused();
  expect(await action.evaluate(element => getComputedStyle(element).outlineStyle)).toBe('solid');
  await page.keyboard.press('Enter');
}

test('Refus joueur : console protégée, cinq tailles et retour clavier à sa colonie', async ({ page }, testInfo) => {
  test.setTimeout(90000);
  const account = await signIn(page);
  expect((await page.goto('/admin')).status()).toBe(403);
  await expect(page).toHaveURL(/acces-refuse$/);
  await expect(page.getByRole('heading', { name: 'Accès refusé', exact: true })).toBeVisible();
  await expect(page.locator('.access-denied-panel')).toContainText('réservée aux comptes administrateurs');
  await expect(page.locator('.flash.error')).toContainText('La tentative a été consignée');
  await inspect(page, testInfo, 'player-denied', 'Retourner au jeu');
  await keyboardReturn(page, 'Retourner au jeu');
  await expect(page).toHaveURL(account.gameUrl);
  expect(JSON.parse(queryIsolatedSql(process.env.BROWSER_ROUTER, `SELECT roles FROM player WHERE email='${account.email}'`))).not.toContain('ROLE_ADMIN');
  runIsolatedSql(process.env.BROWSER_ROUTER, `UPDATE player SET preferences=JSON_OBJECT('theme','light') WHERE email='${account.email}'`);
  expect((await page.goto('/acces-refuse')).status()).toBe(403);
  await expect(page.locator('body')).toHaveAttribute('data-theme', 'light');
  await page.screenshot({ path: testInfo.outputPath('player-denied-light-390.png'), fullPage: true, animations: 'disabled' });
  expect((await new AxeBuilder({ page }).withTags(['wcag2a', 'wcag2aa', 'wcag21a', 'wcag21aa', 'wcag22aa']).analyze()).violations).toEqual([]);
  await page.locator('main').getByRole('link', { name: 'Retourner au jeu' }).click();
  await expect(page).toHaveURL(account.gameUrl);
});

test('403 générique : formulaire invalide, retour avant et après entrée en univers', async ({ page, browser }, testInfo) => {
  test.setTimeout(90000);
  const account = await signIn(page, false, false);
  const rejected = page.waitForResponse(response => response.url().endsWith(account.action) && response.request().method() === 'POST');
  await page.locator(`form[action="${account.action}"] input[name=_token]`).evaluate(input => { input.value = 'invalid'; });
  await page.locator(`form[action="${account.action}"] button`).click();
  expect((await rejected).status()).toBe(403);
  await expect(page.locator('main')).toContainText('votre session n’est plus valide');
  await inspect(page, testInfo, 'generic-403', 'Retour à l’accueil');
  await keyboardReturn(page, 'Retour à l’accueil', true);
  await expect(page).toHaveURL(/univers$/);
  await page.locator(`form[action="${account.action}"] button`).click();
  await expect(page).toHaveURL(/planetes\/\d+$/);
  const gameUrl = page.url();
  const secondRefusal = page.waitForResponse(response => response.url().endsWith(account.action) && response.request().method() === 'POST');
  await page.evaluate(action => {
    const form = document.createElement('form');
    form.method = 'post'; form.action = action;
    const token = document.createElement('input');
    token.name = '_token'; token.value = 'invalid';
    form.append(token); document.body.append(form); form.submit();
  }, account.action);
  expect((await secondRefusal).status()).toBe(403);
  await expect(page.getByRole('heading', { name: 'Accès refusé', exact: true })).toBeVisible();
  await page.screenshot({ path: testInfo.outputPath('generic-403-in-universe-390.png'), animations: 'disabled' });
  await keyboardReturn(page, 'Retour à l’accueil', true);
  await expect(page).toHaveURL(gameUrl);
  const context = await browser.newContext({ storageState: await page.context().storageState(), javaScriptEnabled: false, viewport: { width: 390, height: 900 } });
  const fallback = await context.newPage();
  await fallback.goto('/univers');
  await fallback.locator(`form[action="${account.action}"] input[name=_token]`).evaluate(input => { input.value = 'invalid'; });
  await fallback.locator(`form[action="${account.action}"] button`).click();
  await expect(fallback.getByRole('heading', { name: 'Accès refusé', exact: true })).toBeVisible();
  await fallback.screenshot({ path: testInfo.outputPath('generic-403-nojs-390.png'), animations: 'disabled' });
  await fallback.getByRole('link', { name: 'Retour à l’accueil' }).click();
  await expect(fallback).toHaveURL(gameUrl);
  await context.close();
});

test('Refus administrateur : jeton invalide expliqué et retour à la console', async ({ page }, testInfo) => {
  test.setTimeout(90000);
  await signIn(page, true);
  await expect(page.getByRole('link', { name: 'Admin : ouvrir la console', exact: true })).toBeVisible();
  expect((await new AxeBuilder({ page }).include('.topbar').withTags(['wcag2a', 'wcag2aa', 'wcag21a', 'wcag21aa', 'wcag22aa']).analyze()).violations).toEqual([]);
  await page.goto('/admin#admin-universes');
  const card = page.locator('.admin-universe-card').first();
  const id = Number((await card.getAttribute('id')).replace('admin-universe-', ''));
  const original = queryIsolatedSql(process.env.BROWSER_ROUTER, `SELECT game_settings FROM universe WHERE id=${id}`);
  await card.locator('.admin-universe-settings>summary').click();
  const form = card.locator('form[action$="/settings"]');
  await form.evaluate(element => { element.noValidate = true; element.querySelector('input[name=_token]').value = 'invalid'; });
  await form.getByRole('button', { name: 'Enregistrer les règles' }).click();
  await expect(page).toHaveURL(/acces-refuse$/);
  await page.setViewportSize({ width: 390, height: 900 });
  await page.screenshot({ path: testInfo.outputPath('admin-denied-390.png'), fullPage: true, animations: 'disabled' });
  expect(queryIsolatedSql(process.env.BROWSER_ROUTER, `SELECT game_settings FROM universe WHERE id=${id}`)).toBe(original);
  await expect(page.locator('.access-denied-panel')).toContainText('formulaire');
  await inspect(page, testInfo, 'admin-denied', 'Retour à la console');
  await keyboardReturn(page, 'Retour à la console');
  await expect(page).toHaveURL(/admin$/);
  await expect(page.locator('.admin-console')).toBeVisible();
});

test('Refus anonyme : connexion explicite et console toujours protégée sans JS', async ({ page, browser }, testInfo) => {
  test.setTimeout(90000);
  expect((await page.goto('/acces-refuse')).status()).toBe(403);
  await expect(page.locator('.access-denied-panel')).toContainText('Connectez-vous avec le compte approprié');
  await inspect(page, testInfo, 'anonymous-denied', 'Se connecter');
  await keyboardReturn(page, 'Se connecter');
  await expect(page).toHaveURL(/connexion$/);
  const context = await browser.newContext({ javaScriptEnabled: false, viewport: { width: 390, height: 900 } });
  const fallback = await context.newPage();
  await fallback.goto('/admin');
  await expect(fallback).toHaveURL(/connexion$/);
  await expect(fallback.locator('.admin-console')).toHaveCount(0);
  expect((await fallback.goto('/acces-refuse')).status()).toBe(403);
  await fallback.getByRole('link', { name: 'Se connecter', exact: true }).click();
  await expect(fallback.getByRole('heading', { name: 'Reprendre le commandement' })).toBeVisible();
  await context.close();
});
