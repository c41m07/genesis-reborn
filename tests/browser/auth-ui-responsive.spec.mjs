import { test, expect } from './support/isolated-test.mjs';
import { runIsolatedSql } from './support/game-fixtures.mjs';

async function account(page) {
  const suffix = crypto.randomUUID().slice(0, 8);
  const email = `auth-ui-${suffix}@example.test`;
  const password = 'Original-Password-2026!';
  await page.goto('/inscription');
  await page.getByLabel('Adresse email').fill(email);
  await page.getByLabel('Nom de commandant').fill(`Auth-${suffix}`);
  await page.getByLabel('Mot de passe (12 caractères minimum)', { exact: true }).fill(password);
  await page.getByLabel('Confirmer le mot de passe').fill(password);
  await page.getByRole('button', { name: 'Créer mon compte' }).click();
  await expect(page).toHaveURL(/connexion$/);
  return { email, password };
}

test('Accès public : captures des formulaires et états du lien', async ({ page }, testInfo) => {
  const { email } = await account(page);
  const token = 'ResponsiveReset_0123456789abcdef0123456789abc';
  runIsolatedSql(process.env.BROWSER_ROUTER, `INSERT INTO player_password_reset_token (player_id, token_hash, created_at, expires_at)
    SELECT id,SHA2('${token}',256),UTC_TIMESTAMP(),DATE_ADD(UTC_TIMESTAMP(),INTERVAL 1 HOUR) FROM player WHERE email='${email}'`);
  for (const [route, name] of [
    ['/connexion', 'login'], ['/inscription', 'register'], ['/mot-de-passe/oublie', 'reset-request'],
    [`/mot-de-passe/reinitialiser/${token}`, 'reset-valid'], ['/mot-de-passe/reinitialiser/invalid', 'reset-invalid'],
    ['/acces-refuse', 'access-denied'],
  ]) {
    await page.goto(route);
    await expect(page.locator('main h1')).toBeVisible();
    for (const width of [1920, 1440, 1280, 768, 390]) {
      await page.setViewportSize({ width, height: 900 });
      expect(await page.evaluate(() => document.documentElement.scrollWidth)).toBeLessThanOrEqual(width);
      for (const action of await page.locator('.auth-card .primary').all()) {
        expect(await action.evaluate(element => element.getBoundingClientRect().height)).toBeGreaterThanOrEqual(44);
      }
      if ([1440, 390].includes(width)) await page.screenshot({ path: testInfo.outputPath(`${name}-${width}.png`), fullPage: true, animations: 'disabled' });
    }
  }
});

async function describedErrors(page) {
  const fields = page.locator('input[aria-invalid="true"]');
  expect(await fields.count()).toBeGreaterThan(0);
  for (const field of await fields.all()) {
    const ids = (await field.getAttribute('aria-describedby') ?? '').split(/\s+/).filter(Boolean);
    expect(ids.length).toBeGreaterThan(0);
    for (const id of ids) await expect(page.locator(`[id="${id}"]`)).toBeVisible();
  }
}

test('Erreurs de formulaire : messages liés aux champs et navigation clavier', async ({ page }, testInfo) => {
  await page.goto('/connexion');
  const email = page.getByLabel('Adresse email');
  await expect(email).toBeFocused();
  await page.keyboard.press('Tab');
  await expect(page.getByLabel('Mot de passe', { exact: true })).toBeFocused();
  await email.fill(`unknown-${crypto.randomUUID().slice(0, 8)}@example.test`);
  await page.getByLabel('Mot de passe', { exact: true }).fill('Incorrect-Password-2026!');
  await page.getByRole('button', { name: /Connexion/ }).click();
  await expect(page.getByRole('alert')).toContainText('Connexion impossible');
  await describedErrors(page);
  await page.setViewportSize({ width: 390, height: 900 });
  await page.screenshot({ path: testInfo.outputPath('login-error-390.png'), fullPage: true, animations: 'disabled' });

  await page.goto('/inscription');
  await page.locator('form').evaluate(form => { form.noValidate = true; });
  await page.getByLabel('Adresse email').fill('adresse-invalide');
  await page.getByLabel('Nom de commandant').fill('!!');
  await page.getByLabel('Mot de passe (12 caractères minimum)', { exact: true }).fill('Premier-2026!');
  await page.getByLabel('Confirmer le mot de passe').fill('Different-2026!');
  const registration = page.waitForResponse(response => response.url().endsWith('/inscription') && response.request().method() === 'POST');
  await page.getByRole('button', { name: 'Créer mon compte' }).click();
  expect((await registration).status()).toBe(422);
  await expect(page.getByRole('alert').filter({ hasText: 'Les mots de passe ne correspondent pas.' })).toBeVisible();
  await describedErrors(page);
  await expect(page.getByLabel('Adresse email')).toHaveValue('adresse-invalide');
  await expect(page.getByLabel('Nom de commandant')).toHaveValue('!!');
  expect(await page.evaluate(() => document.documentElement.scrollWidth)).toBeLessThanOrEqual(390);
  await page.screenshot({ path: testInfo.outputPath('register-error-390.png'), fullPage: true, animations: 'disabled' });

  await page.goto('/mot-de-passe/oublie');
  await page.getByLabel('Adresse email').fill('email-invalide');
  await page.locator('form').evaluate(form => { form.noValidate = true; });
  await page.getByRole('button', { name: 'Envoyer le lien' }).click();
  await expect(page.getByRole('alert')).toBeVisible();
  await describedErrors(page);
  await page.screenshot({ path: testInfo.outputPath('reset-request-error-390.png'), fullPage: true, animations: 'disabled' });
});

test('Réinitialisation : erreur liée au champ, lien expiré et formulaire sans JS', async ({ page, browser }, testInfo) => {
  const { email } = await account(page);
  const token = 'MismatchReset_0123456789abcdef0123456789abcd';
  runIsolatedSql(process.env.BROWSER_ROUTER, `INSERT INTO player_password_reset_token (player_id, token_hash, created_at, expires_at)
    SELECT id,SHA2('${token}',256),UTC_TIMESTAMP(),DATE_ADD(UTC_TIMESTAMP(),INTERVAL 1 HOUR) FROM player WHERE email='${email}'`);
  const context = await browser.newContext({ javaScriptEnabled: false, viewport: { width: 390, height: 900 } });
  const fallback = await context.newPage();
  await fallback.goto(`/mot-de-passe/reinitialiser/${token}`);
  await fallback.getByLabel('Nouveau mot de passe (12 caractères minimum)').fill('Replacement-Password-2026!');
  await fallback.getByLabel('Confirmer le nouveau mot de passe').fill('Different-Password-2026!');
  await fallback.getByRole('button', { name: 'Modifier le mot de passe' }).click();
  await expect(fallback.getByRole('alert')).toContainText('Les mots de passe ne correspondent pas.');
  await expect(fallback.getByRole('form', { name: 'Formulaire à corriger' })).toBeFocused();
  await describedErrors(fallback);
  await fallback.screenshot({ path: testInfo.outputPath('reset-valid-error-390.png'), fullPage: true });
  await context.close();
  runIsolatedSql(process.env.BROWSER_ROUTER, `UPDATE player_password_reset_token SET expires_at=DATE_SUB(UTC_TIMESTAMP(),INTERVAL 1 HOUR) WHERE token_hash=SHA2('${token}',256)`);
  const expired = await page.goto(`/mot-de-passe/reinitialiser/${token}`);
  expect(expired.status()).toBe(400);
  expect(expired.headers()['cache-control']).toContain('no-store');
  await expect(page.getByRole('heading', { name: 'Lien expiré ou invalide' })).toBeVisible();
  await page.getByRole('link', { name: 'Demander un nouveau lien' }).click();
  await expect(page).toHaveURL(/mot-de-passe\/oublie$/);
});

test('Erreurs 403 et 404 réelles, aperçu 500 et retour clavier', async ({ page }, testInfo) => {
  const { email, password } = await account(page);
  await page.getByLabel('Adresse email').fill(email);
  await page.getByLabel('Mot de passe', { exact: true }).fill(password);
  await page.getByRole('button', { name: /Connexion/ }).click();
  const card = page.locator('.universe-card').filter({ has: page.getByRole('button') }).first();
  const action = await card.locator('form').getAttribute('action');
  await card.locator('input[name="_token"]').evaluate(input => { input.value = 'invalid'; });
  const refused = page.waitForResponse(response => response.url().endsWith(action) && response.request().method() === 'POST');
  await card.getByRole('button').click();
  expect((await refused).status()).toBe(403);
  await expect(page.getByRole('heading', { name: 'Accès refusé' })).toBeVisible();
  for (const [route, code, title] of [[null, 403, 'Accès refusé'], ['/page-ui-inconnue', 404, 'Page introuvable'], ['/_error/500', 500, 'Une erreur est survenue']]) {
    if (route) expect((await page.goto(route)).status()).toBe(code);
    await expect(page.getByRole('heading', { name: title, exact: true })).toBeVisible();
    await expect(page.locator('body')).not.toContainText('This is a sample exception');
    for (const width of [1920, 1440, 1280, 768, 390]) {
      await page.setViewportSize({ width, height: 900 });
      expect(await page.evaluate(() => document.documentElement.scrollWidth)).toBeLessThanOrEqual(width);
      if ([1440, 390].includes(width)) await page.screenshot({ path: testInfo.outputPath(`error-${code}-${width}.png`), fullPage: true, animations: 'disabled' });
    }
    await page.getByRole('link', { name: 'Aller au contenu principal' }).focus();
    await page.keyboard.press('Enter');
    await expect(page.locator('main')).toBeFocused();
    await page.keyboard.press('Tab');
    await expect(page.getByRole('link', { name: 'Retour à l’accueil' })).toBeFocused();
  }
  await page.keyboard.press('Enter');
  await expect(page).toHaveURL(/univers$/);
});
