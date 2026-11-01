import { test, expect } from './support/isolated-test.mjs';

test('Les quatre sections de diplomatie restent accessibles sans guilde', async ({ page }) => {
  const id = crypto.randomUUID().slice(0, 8);
  const email = 'diplomacy-' + id + '@example.test';
  const password = 'Browser-test-2026!';
  await page.goto('/inscription');
  await page.getByLabel('Adresse email').fill(email);
  await page.getByLabel('Nom de commandant').fill('Diplomacy-' + id);
  await page.getByLabel('Mot de passe (12 caractères minimum)', { exact: true }).fill(password);
  await page.getByLabel('Confirmer le mot de passe').fill(password);
  await page.getByRole('button', { name: 'Créer mon compte' }).click();
  await expect(page).toHaveURL(/connexion/);
  await page.getByLabel('Adresse email').fill(email);
  await page.getByLabel('Mot de passe', { exact: true }).fill(password);
  await page.getByRole('button', { name: /Connexion/ }).click();
  await page.locator('article.universe-card').filter({ has: page.getByRole('heading', { name: /Turbo/ }) }).getByRole('button').click();
  await page.goto('/diplomatie');
  await expect(page.locator('#alliance-overview')).toContainText('Diplomatie directe');
  const sections = page.getByRole('navigation', { name: 'Sections des alliances' });
  for (const [label, id] of [['Vue d’ensemble', 'alliance-overview'], ['Membres', 'alliance-members'], ['Diplomatie', 'alliance-diplomacy'], ['Guildes', 'alliance-guilds']]) {
    await sections.getByRole('link', { name: label, exact: true }).click();
    await expect(page).toHaveURL(new RegExp('#' + id + '$'));
    await expect(page.locator('#' + id)).toBeVisible();
  }
  await page.setViewportSize({ width: 390, height: 844 });
  await expect.poll(() => page.evaluate(() => document.documentElement.scrollWidth)).toBeLessThanOrEqual(390);
});
