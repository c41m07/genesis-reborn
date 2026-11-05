import { test, expect } from './support/isolated-test.mjs';
import { runIsolatedSql, queryIsolatedSql } from './support/game-fixtures.mjs';
import AxeBuilder from '@axe-core/playwright';

async function layout(page, testInfo, state) {
  for (const width of [1920, 1440, 1280, 768, 390]) {
    await page.setViewportSize({ width, height: 900 });
    expect(await page.evaluate(() => document.documentElement.scrollWidth)).toBeLessThanOrEqual(width);
    for (const control of await page.locator('.auth-card input:not([type=hidden]), .auth-card button, .auth-back a').all()) {
      const box = await control.boundingBox();
      expect(box.height).toBeGreaterThanOrEqual(44);
      expect(box.x).toBeGreaterThanOrEqual(0);
      expect(box.x + box.width).toBeLessThanOrEqual(width);
    }
    if ([1440, 390].includes(width)) await page.screenshot({ path: testInfo.outputPath(`reset-${state}-${width}.png`), fullPage: true, animations: 'disabled' });
    if ([1440, 768, 390].includes(width)) {
      const report = await new AxeBuilder({ page }).withTags(['wcag2a', 'wcag2aa', 'wcag21a', 'wcag21aa', 'wcag22aa', 'best-practice']).analyze();
      expect(report.violations).toEqual([]);
    }
  }
}

test('Nouveau mot de passe : grille responsive, erreurs, clavier sans JS et effet réel', async ({ page, browser }, testInfo) => {
  test.setTimeout(120000);
  const suffix = crypto.randomUUID().slice(0, 8);
  const email = `reset-gate-${suffix}@example.test`;
  const oldPassword = 'Original-Password-2026!';
  const newPassword = 'Replacement-Password-2026!';
  await page.goto('/inscription');
  await page.getByLabel('Adresse email').fill(email);
  await page.getByLabel('Nom de commandant').fill('ResetGate-' + suffix);
  await page.getByLabel('Mot de passe (12 caractères minimum)', { exact: true }).fill(oldPassword);
  await page.getByLabel('Confirmer le mot de passe').fill(oldPassword);
  await page.getByRole('button', { name: 'Créer mon compte' }).click();
  await expect(page).toHaveURL(/connexion$/);
  const tokens = ['ResetGateValid_0123456789abcdef0123456789abc', 'ResetGateOther_0123456789abcdef0123456789abc', 'ResetGateExpired_0123456789abcdef0123456789'];
  for (const [index, token] of tokens.entries()) {
    runIsolatedSql(process.env.BROWSER_ROUTER, `INSERT INTO player_password_reset_token (player_id,token_hash,created_at,expires_at) SELECT id,SHA2('${token}',256),UTC_TIMESTAMP(),DATE_ADD(UTC_TIMESTAMP(),INTERVAL ${index === 2 ? '-1' : '1'} HOUR) FROM player WHERE email='${email}'`);
  }
  const route = '/mot-de-passe/reinitialiser/' + tokens[0];
  const response = await page.goto(route);
  expect(response.status()).toBe(200);
  expect(response.headers()['referrer-policy']).toBe('no-referrer');
  expect(response.headers()['cache-control']).toContain('no-store');
  const first = page.getByLabel('Nouveau mot de passe (12 caractères minimum)');
  const second = page.getByLabel('Confirmer le nouveau mot de passe');
  for (const field of [first, second]) {
    await expect(field).toHaveAttribute('type', 'password');
    await expect(field).toHaveAttribute('autocomplete', 'new-password');
  }
  await layout(page, testInfo, 'valid');
  await page.getByRole('button', { name: 'Modifier le mot de passe' }).click();
  await expect(first).toBeFocused();
  expect(await first.evaluate(element => element.validity.valueMissing)).toBe(true);
  await first.fill(newPassword);
  await second.fill('Different-Password-2026!');
  await page.getByRole('button', { name: 'Modifier le mot de passe' }).click();
  await expect(page.getByRole('alert')).toContainText('Les mots de passe ne correspondent pas.');
  await expect(page.getByRole('form', { name: 'Formulaire à corriger' })).toBeFocused();
  for (const field of await page.locator('input[aria-invalid=true]').all()) {
    const ids = (await field.getAttribute('aria-describedby')).split(/\s+/);
    for (const id of ids) await expect(page.locator(`[id="${id}"]`)).toBeVisible();
  }
  await expect(first).toHaveValue('');
  await expect(second).toHaveValue('');
  await layout(page, testInfo, 'error');
  for (const value of ['court', 'a'.repeat(129)]) {
    await first.fill(value);
    await second.fill(value);
    await page.locator('form').evaluate(form => { form.noValidate = true; });
    const submitted = page.waitForResponse(response => response.url().includes('/mot-de-passe/reinitialiser/') && response.request().method() === 'POST');
    await page.getByRole('button', { name: 'Modifier le mot de passe' }).click();
    expect((await submitted).status()).toBe(422);
    await expect(page.getByRole('alert')).toContainText(value === 'court' ? '12 caractères' : '128');
    await expect(page.getByRole('form', { name: 'Formulaire à corriger' })).toBeFocused();
    await expect(first).toHaveValue('');
  }
  await page.getByRole('link', { name: 'Demander un nouveau lien' }).click();
  await expect(page).toHaveURL(/mot-de-passe\/oublie$/);
  expect((await page.goto('/mot-de-passe/reinitialiser/' + tokens[2])).status()).toBe(400);
  const context = await browser.newContext({ javaScriptEnabled: false, viewport: { width: 390, height: 900 } });
  const fallback = await context.newPage();
  await fallback.goto(route);
  await fallback.getByLabel('Nouveau mot de passe (12 caractères minimum)').focus();
  await fallback.keyboard.type(newPassword);
  await fallback.keyboard.press('Tab');
  await expect(fallback.getByLabel('Confirmer le nouveau mot de passe')).toBeFocused();
  await fallback.keyboard.type(newPassword);
  await fallback.keyboard.press('Tab');
  await expect(fallback.getByRole('button', { name: 'Modifier le mot de passe' })).toBeFocused();
  await fallback.keyboard.press('Enter');
  await expect(fallback).toHaveURL(/connexion$/);
  await expect(fallback.getByRole('status')).toContainText('Mot de passe modifié');
  await fallback.getByLabel('Adresse email').fill(email);
  await fallback.getByLabel('Mot de passe', { exact: true }).fill(oldPassword);
  await fallback.keyboard.press('Enter');
  await expect(fallback.getByRole('alert')).toContainText('Connexion impossible');
  await fallback.getByLabel('Mot de passe', { exact: true }).fill(newPassword);
  await fallback.keyboard.press('Enter');
  await expect(fallback).toHaveURL(/univers$/);
  await expect(fallback.locator('article.universe-card').first()).toBeVisible();
  for (const token of tokens.slice(0, 2)) expect((await page.goto('/mot-de-passe/reinitialiser/' + token)).status()).toBe(400);
  expect(queryIsolatedSql(process.env.BROWSER_ROUTER, `SELECT COUNT(*) FROM player_password_reset_token t JOIN player p ON p.id=t.player_id WHERE p.email='${email}'`)).toBe('0');
  await context.close();
});
