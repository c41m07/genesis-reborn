import { test, expect } from './support/isolated-test.mjs';
import AxeBuilder from '@axe-core/playwright';
import { runIsolatedSql, queryIsolatedSql } from './support/game-fixtures.mjs';

const widths = [1920, 1440, 1280, 768, 390];
const tags = ['wcag2a', 'wcag2aa', 'wcag21a', 'wcag21aa', 'wcag22aa', 'best-practice'];

async function layout(page, testInfo, name) {
  for (const width of widths) {
    await page.setViewportSize({ width, height: 900 });
    expect(await page.evaluate(() => document.documentElement.scrollWidth), name).toBeLessThanOrEqual(width);
    for (const control of await page.locator('main input:not([type=hidden]), main button, .auth-back a, .auth-note a').all()) {
      const box = await control.boundingBox();
      expect(box.height, await control.getAttribute('name')).toBeGreaterThanOrEqual(44);
      expect(box.x).toBeGreaterThanOrEqual(0);
      expect(box.x + box.width).toBeLessThanOrEqual(width);
    }
    if ([1440, 390].includes(width)) await page.screenshot({ path: testInfo.outputPath(`${name}-${width}.png`), fullPage: true, animations: 'disabled' });
    if ([1440, 768, 390].includes(width)) {
      const report = await new AxeBuilder({ page }).withTags(tags).analyze();
      expect(report.violations, name + ' à ' + width).toEqual([]);
    }
  }
}

async function linkedErrors(page) {
  const invalid = page.locator('input[aria-invalid=true]');
  expect(await invalid.count()).toBeGreaterThan(0);
  for (const field of await invalid.all()) {
    const ids = (await field.getAttribute('aria-describedby') ?? '').split(/\s+/).filter(Boolean);
    expect(ids.length).toBeGreaterThan(0);
    for (const id of ids) await expect(page.locator(`[id="${id}"]`)).toBeVisible();
  }
}

async function signup(page, email, username, password, confirmation = password) {
  await page.getByLabel('Adresse email').fill(email);
  await page.keyboard.press('Tab');
  await expect(page.getByLabel('Nom de commandant')).toBeFocused();
  await page.keyboard.press('ControlOrMeta+A');
  await page.keyboard.type(username);
  await page.keyboard.press('Tab');
  await expect(page.getByLabel('Mot de passe (12 caractères minimum)', { exact: true })).toBeFocused();
  await page.keyboard.press('ControlOrMeta+A');
  await page.keyboard.type(password);
  await page.keyboard.press('Tab');
  const passwordToggle = page.locator('[aria-controls="registration_plainPassword_first"]');
  if (await passwordToggle.isVisible()) {
    await expect(passwordToggle).toBeFocused();
    await page.keyboard.press('Space');
    await expect(page.getByLabel('Mot de passe (12 caractères minimum)', { exact: true })).toHaveAttribute('type', 'text');
    await expect(passwordToggle.locator('.password-toggle-slash')).toBeHidden();
    await page.keyboard.press('Space');
    await expect(page.getByLabel('Mot de passe (12 caractères minimum)', { exact: true })).toHaveAttribute('type', 'password');
    await expect(passwordToggle.locator('.password-toggle-slash')).toBeVisible();
    await page.keyboard.press('Tab');
  }
  await expect(page.getByLabel('Confirmer le mot de passe')).toBeFocused();
  await page.keyboard.press('ControlOrMeta+A');
  await page.keyboard.type(confirmation);
  await page.keyboard.press('Tab');
  const confirmationToggle = page.locator('[aria-controls="registration_plainPassword_second"]');
  if (await confirmationToggle.isVisible()) {
    await expect(confirmationToggle).toBeFocused();
    await page.keyboard.press('Tab');
  }
  await expect(page.getByRole('button', { name: 'Créer mon compte' })).toBeFocused();
  await page.keyboard.press('Enter');
}

test('Formulaires publics : cinq tailles, cibles et audit accessible', async ({ page }, testInfo) => {
  test.setTimeout(120000);
  for (const [route, name] of [['/connexion', 'login'], ['/inscription', 'register'], ['/mot-de-passe/oublie', 'request']]) {
    expect((await page.goto(route)).status()).toBe(200);
    await layout(page, testInfo, name);
  }
});

async function correctionFocus(page) {
  const form = page.getByRole('form', { name: 'Formulaire à corriger' });
  await expect(form).toBeFocused();
  expect(await form.evaluate(element => getComputedStyle(element).outlineStyle)).toBe('solid');
  await page.keyboard.press('Tab');
  await expect(page.getByLabel('Adresse email')).toBeFocused();
}

test('Inscription et connexion au clavier : erreur, correction, doublon et sans JS', async ({ browser }, testInfo) => {
  test.setTimeout(120000);
  const context = await browser.newContext({ javaScriptEnabled: false, viewport: { width: 390, height: 900 } });
  const page = await context.newPage();
  const suffix = crypto.randomUUID().slice(0, 8);
  const email = `auth-gates-${suffix}@example.test`;
  const username = `Commandant-${suffix}`;
  const password = 'Browser-Keyboard-2026!';
  await page.goto('/inscription');
  const invalidResponse = page.waitForResponse(response => response.url().endsWith('/inscription') && response.request().method() === 'POST');
  await signup(page, email, username, password, 'Different-Password-2026!');
  expect((await invalidResponse).status()).toBe(422);
  await expect(page.getByRole('alert')).toContainText('Les mots de passe ne correspondent pas.');
  await linkedErrors(page);
  await expect(page.getByLabel('Adresse email')).toHaveValue(email);
  await expect(page.getByLabel('Nom de commandant')).toHaveValue(username);
  await expect(page.getByLabel('Mot de passe (12 caractères minimum)', { exact: true })).toHaveValue('');
  await page.screenshot({ path: testInfo.outputPath('register-error-nojs-390.png'), fullPage: true });
  await correctionFocus(page);
  await signup(page, email, username, password);
  await expect(page).toHaveURL(/connexion$/);
  await expect(page.getByRole('status')).toContainText('Compte créé');
  await page.goto('/inscription');
  await signup(page, email, username, password);
  await expect(page.getByRole('alert')).toContainText('Cette adresse email ou ce pseudo est déjà utilisé.');
  await correctionFocus(page);
  await page.screenshot({ path: testInfo.outputPath('register-duplicate-nojs-390.png'), fullPage: true });
  await page.getByRole('link', { name: 'Retour à la connexion' }).click();
  await expect(page.getByLabel('Adresse email')).toBeFocused();
  await page.keyboard.type(email);
  await page.keyboard.press('Tab');
  await expect(page.getByLabel('Mot de passe', { exact: true })).toBeFocused();
  await page.keyboard.type('Incorrect-Password-2026!');
  await page.keyboard.press('Enter');
  await expect(page.getByRole('alert')).toContainText('Connexion impossible');
  await linkedErrors(page);
  await expect(page.getByLabel('Adresse email')).toHaveValue(email);
  await expect(page.getByLabel('Adresse email')).toBeFocused();
  await page.screenshot({ path: testInfo.outputPath('login-error-nojs-390.png'), fullPage: true });
  await page.keyboard.press('Tab');
  await page.keyboard.type(password);
  await page.keyboard.press('Enter');
  await expect(page).toHaveURL(/univers$/);
  await expect(page.locator('article.universe-card').first()).toBeVisible();
  await context.close();
});

test('Demande de récupération : erreur serveur liée, correction et confirmation neutre sans JS', async ({ browser }, testInfo) => {
  test.setTimeout(90000);
  const context = await browser.newContext({ javaScriptEnabled: false, viewport: { width: 390, height: 900 } });
  const page = await context.newPage();
  const suffix = crypto.randomUUID().slice(0, 8);
  const knownEmail = `request-gates-${suffix}@example.test`;
  await page.goto('/inscription');
  await signup(page, knownEmail, `Request-${suffix}`, 'Browser-Keyboard-2026!');
  await expect(page).toHaveURL(/connexion$/);
  await page.goto('/mot-de-passe/oublie');
  await page.locator('form').evaluate(form => { form.noValidate = true; });
  await page.getByLabel('Adresse email').fill('adresse-invalide');
  const response = page.waitForResponse(response => response.url().endsWith('/mot-de-passe/oublie') && response.request().method() === 'POST');
  await page.keyboard.press('Enter');
  expect((await response).status()).toBe(422);
  await expect(page.getByRole('alert')).toContainText('Adresse email invalide.');
  await linkedErrors(page);
  await page.screenshot({ path: testInfo.outputPath('request-error-nojs-390.png'), fullPage: true });
  await correctionFocus(page);
  await page.getByLabel('Adresse email').fill(`unknown-${crypto.randomUUID().slice(0, 8)}@example.test`);
  await page.keyboard.press('Tab');
  await expect(page.getByRole('button', { name: 'Envoyer le lien' })).toBeFocused();
  await page.keyboard.press('Enter');
  await expect(page).toHaveURL(/mot-de-passe\/oublie$/);
  await expect(page.getByRole('status')).toHaveText('Si cette adresse correspond à un compte, un lien de réinitialisation va être envoyé.');
  const neutralMessage = await page.getByRole('status').textContent();
  await page.getByLabel('Adresse email').fill(knownEmail);
  await page.keyboard.press('Enter');
  await expect(page.getByRole('status')).toHaveText(neutralMessage);
  for (let request = 3; request <= 5; request++) {
    await page.getByLabel('Adresse email').fill(knownEmail);
    await page.keyboard.press('Enter');
    await expect(page.getByRole('status')).toHaveText(neutralMessage);
  }
  const tokenQuery = `SELECT token_hash FROM player_password_reset_token t JOIN player p ON p.id=t.player_id WHERE p.email='${knownEmail}'`;
  const previousToken = queryIsolatedSql(process.env.BROWSER_ROUTER, tokenQuery);
  expect(previousToken.length).toBe(64);
  await page.getByLabel('Adresse email').fill(knownEmail);
  await page.keyboard.press('Enter');
  await expect(page.getByRole('status')).toHaveText(neutralMessage);
  expect(queryIsolatedSql(process.env.BROWSER_ROUTER, tokenQuery) === previousToken).toBe(true);
  await page.screenshot({ path: testInfo.outputPath('request-confirmation-nojs-390.png'), fullPage: true });
  await page.getByRole('link', { name: 'Retour à la connexion' }).click();
  await expect(page.getByRole('heading', { name: 'Reprendre le commandement' })).toBeVisible();
  await page.getByRole('link', { name: 'Mot de passe oublié ?' }).click();
  await expect(page.getByRole('heading', { name: 'Mot de passe oublié', exact: true })).toBeVisible();
  await page.getByRole('link', { name: 'Retour à la connexion' }).click();
  await page.getByRole('link', { name: 'Créer un compte', exact: true }).click();
  await expect(page.getByRole('heading', { name: 'Rejoignez les étoiles.' })).toBeVisible();
  await context.close();
});

test('Connexion refusée : suspension et limitation gardent un retour neutre utilisable', async ({ page }, testInfo) => {
  test.setTimeout(90000);
  const suffix = crypto.randomUUID().slice(0, 8);
  const email = `locked-gates-${suffix}@example.test`;
  const password = 'Browser-Keyboard-2026!';
  await page.goto('/inscription');
  await signup(page, email, `Locked-${suffix}`, password);
  await expect(page).toHaveURL(/connexion$/);
  runIsolatedSql(process.env.BROWSER_ROUTER, `UPDATE player SET locked_at=UTC_TIMESTAMP() WHERE email='${email}'`);
  await page.getByLabel('Adresse email').fill(email);
  await page.getByLabel('Mot de passe', { exact: true }).fill(password);
  await page.getByRole('button', { name: /Connexion/ }).click();
  await expect(page).toHaveURL(/connexion$/);
  const message = 'Connexion impossible. Vérifiez vos identifiants ou réessayez dans une minute.';
  await expect(page.getByRole('alert')).toHaveText(message);
  await expect(page.getByLabel('Adresse email')).toBeFocused();
  await linkedErrors(page);
  await page.setViewportSize({ width: 390, height: 900 });
  await page.screenshot({ path: testInfo.outputPath('login-suspended-390.png'), fullPage: true });
  runIsolatedSql(process.env.BROWSER_ROUTER, `UPDATE player SET locked_at=NULL WHERE email='${email}'`);
  for (let attempt = 0; attempt < 6; attempt++) {
    await page.getByLabel('Mot de passe', { exact: true }).fill('Incorrect-Password-2026!');
    await page.getByRole('button', { name: /Connexion/ }).click();
    await expect(page.getByRole('alert')).toHaveText(message);
  }
  await page.getByLabel('Mot de passe', { exact: true }).fill(password);
  await page.getByRole('button', { name: /Connexion/ }).click();
  await expect(page).toHaveURL(/connexion$/);
  await expect(page.getByRole('alert')).toHaveText(message);
  await expect(page.getByLabel('Adresse email')).toBeFocused();
  await page.screenshot({ path: testInfo.outputPath('login-throttled-390.png'), fullPage: true });
  await page.getByRole('link', { name: 'Mot de passe oublié ?' }).click();
  await expect(page.getByRole('heading', { name: 'Mot de passe oublié', exact: true })).toBeVisible();
});
