import { test, expect } from './support/isolated-test.mjs';
import AxeBuilder from '@axe-core/playwright';

async function signIn(page) {
  const id = crypto.randomUUID().slice(0, 8);
  const email = 'axe-public-' + id + '@example.test';
  const password = 'Browser-test-2026!';
  await page.goto('/inscription');
  await page.getByLabel('Adresse email').fill(email);
  await page.getByLabel('Nom de commandant').fill('AxePublic-' + id);
  await page.getByLabel('Mot de passe (12 caractères minimum)', { exact: true }).fill(password);
  await page.getByLabel('Confirmer le mot de passe').fill(password);
  await page.getByRole('button', { name: 'Créer mon compte' }).click();
  await expect(page).toHaveURL(/connexion/);
  await page.getByLabel('Adresse email').fill(email);
  await page.getByLabel('Mot de passe', { exact: true }).fill(password);
  await page.getByRole('button', { name: /Connexion/ }).click();
  await page.locator('article.universe-card').filter({ has: page.getByRole('heading', { name: /Turbo/ }) }).getByRole('button').click();
  await expect(page).toHaveURL(/planetes\/\d+$/);
}

test('Audit axe des pages publiques et d’erreur à 1440, 768 et 390 px', async ({ page }) => {
  test.setTimeout(180000);
  const violations = [];
  const scan = async (route, width, expectedStatus) => {
    await page.setViewportSize({ width, height: 900 });
    const response = await page.goto(route, { waitUntil: 'domcontentloaded' });
    await page.waitForLoadState('load');
    expect(response?.status(), route + ' à ' + width + ' px').toBe(expectedStatus);
    const result = await new AxeBuilder({ page })
      .withTags(['wcag2a', 'wcag2aa', 'wcag21a', 'wcag21aa', 'wcag22aa', 'best-practice'])
      .analyze();
    violations.push(...result.violations.map(item => ({ route, width, id: item.id, impact: item.impact, help: item.help, nodes: item.nodes.map(node => ({ target: node.target, summary: node.failureSummary })) })));
  };

  for (const width of [1440, 768, 390]) {
    for (const route of [
      ['/connexion', 200],
      ['/inscription', 200],
      ['/mot-de-passe/oublie', 200],
      ['/mot-de-passe/reinitialiser/jeton-invalide', 400]
    ]) await scan(route[0], width, route[1]);
  }

  for (const width of [1440, 768, 390]) {
    await page.setViewportSize({ width, height: 900 });
    await page.goto('/connexion');
    await page.getByLabel('Adresse email').fill('absent-' + width + '@example.test');
    await page.getByLabel('Mot de passe', { exact: true }).fill('Incorrect-Password-2026!');
    await page.getByRole('button', { name: /Connexion/ }).click();
    await expect(page.getByRole('alert')).toContainText('Connexion impossible');
    await page.waitForLoadState('load');
    let result = await new AxeBuilder({ page }).withTags(['wcag2a', 'wcag2aa', 'wcag21a', 'wcag21aa', 'wcag22aa', 'best-practice']).analyze();
    violations.push(...result.violations.map(item => ({ route: '/connexion?invalid', width, id: item.id, impact: item.impact, help: item.help, nodes: item.nodes.map(node => ({ target: node.target, summary: node.failureSummary })) })));

    await page.goto('/inscription');
    await page.getByLabel('Adresse email').fill('mismatch-' + width + '@example.test');
    await page.getByLabel('Nom de commandant').fill('Mismatch-' + width);
    await page.getByLabel('Mot de passe (12 caractères minimum)', { exact: true }).fill('Registration-2026!');
    await page.getByLabel('Confirmer le mot de passe').fill('Different-2026!');
    const responsePromise = page.waitForResponse(response => response.url().includes('/inscription') && response.request().method() === 'POST');
    await page.getByRole('button', { name: 'Créer mon compte' }).click();
    const response = await responsePromise;
    expect(response.status()).toBe(422);
    await expect(page.getByRole('alert').filter({ hasText: 'Les mots de passe ne correspondent pas.' })).toBeVisible();
    await page.waitForLoadState('load');
    result = await new AxeBuilder({ page }).withTags(['wcag2a', 'wcag2aa', 'wcag21a', 'wcag21aa', 'wcag22aa', 'best-practice']).analyze();
    violations.push(...result.violations.map(item => ({ route: '/inscription?invalid-password', width, id: item.id, impact: item.impact, help: item.help, nodes: item.nodes.map(node => ({ target: node.target, summary: node.failureSummary })) })));
  }

  await signIn(page);
  for (const width of [1440, 768, 390]) {
    await scan('/admin', width, 403);
    await scan('/rapports/combat/99999999', width, 404);
  }

  expect(violations, JSON.stringify(violations, null, 2)).toEqual([]);
});
