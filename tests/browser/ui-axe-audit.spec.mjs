import { test, expect } from './support/isolated-test.mjs';
import AxeBuilder from '@axe-core/playwright';

async function signIn(page) {
  const id = crypto.randomUUID().slice(0, 8);
  const email = 'axe-' + id + '@example.test';
  const password = 'Browser-test-2026!';
  await page.goto('/inscription');
  await page.getByLabel('Adresse email').fill(email);
  await page.getByLabel('Nom de commandant').fill('Axe-' + id);
  await page.getByLabel('Mot de passe (12 caractères minimum)', { exact: true }).fill(password);
  await page.getByLabel('Confirmer le mot de passe').fill(password);
  await page.getByRole('button', { name: 'Créer mon compte' }).click();
  await expect(page).toHaveURL(/connexion/);
  await page.getByLabel('Adresse email').fill(email);
  await page.getByLabel('Mot de passe', { exact: true }).fill(password);
  await page.getByRole('button', { name: /Connexion/ }).click();
  await page.locator('article.universe-card').filter({ has: page.getByRole('heading', { name: /Turbo/ }) }).getByRole('button').click();
  await expect(page).toHaveURL(/planetes\/\d+$/);
  return Number(new URL(page.url()).pathname.split('/').at(-1));
}

test('Audit axe des écrans applicatifs à 1440 et 390 px', async ({ page }) => {
  test.setTimeout(300000);
  const violations = [];
  const publicRoutes = ['/connexion', '/inscription', '/mot-de-passe/oublie', '/mot-de-passe/reinitialiser/invalid-token'];
  for (const width of [1440, 390]) {
    await page.setViewportSize({ width, height: 900 });
    for (const route of publicRoutes) {
      await page.goto(route, { waitUntil: 'domcontentloaded' });
      const result = await new AxeBuilder({ page })
        .withTags(['wcag2a', 'wcag2aa', 'wcag21a', 'wcag21aa', 'wcag22aa', 'best-practice'])
        .analyze();
      violations.push(...result.violations.map(item => ({ route, width, id: item.id, impact: item.impact, help: item.help, nodes: item.nodes.map(node => ({ target: node.target, summary: node.failureSummary })) })));
    }
  }
  const planetId = await signIn(page);
  const routes = [
    '/', '/planetes', '/planetes/' + planetId,
    '/planetes/' + planetId + '?section=buildings',
    '/planetes/' + planetId + '?section=technologies',
    '/planetes/' + planetId + '?section=ships',
    '/planetes/' + planetId + '?section=defenses',
    '/carte', '/flottes', '/rapports', '/diplomatie', '/messages',
    '/classement', '/univers', '/profil', '/profil/preferences', '/boosts'
  ];
  for (const width of [1440, 390]) {
    await page.setViewportSize({ width, height: 900 });
    for (const route of routes) {
      await page.goto(route, { waitUntil: 'domcontentloaded' });
      const result = await new AxeBuilder({ page })
        .withTags(['wcag2a', 'wcag2aa', 'wcag21a', 'wcag21aa', 'wcag22aa', 'best-practice'])
        .analyze();
      violations.push(...result.violations.map(item => ({ route, width, id: item.id, impact: item.impact, help: item.help, nodes: item.nodes.map(node => ({ target: node.target, summary: node.failureSummary })) })));
    }
  }
  expect(violations, JSON.stringify(violations, null, 2)).toEqual([]);
});
