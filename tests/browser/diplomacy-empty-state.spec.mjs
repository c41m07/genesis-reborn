import { test, expect } from './support/isolated-test.mjs';

test('La liste vide de guildes explique l’action possible', async ({ page }) => {
  const id = crypto.randomUUID().slice(0, 8);
  const email = 'guild-empty-' + id + '@example.test';
  const password = 'Guild-empty-2026!';

  await page.goto('/inscription');
  await page.getByLabel('Adresse email').fill(email);
  await page.getByLabel('Nom de commandant').fill('Guild-' + id);
  await page.getByLabel('Mot de passe (12 caractères minimum)', { exact: true }).fill(password);
  await page.getByLabel('Confirmer le mot de passe').fill(password);
  await page.getByRole('button', { name: 'Créer mon compte' }).click();
  await expect(page).toHaveURL(/connexion/);
  await page.getByLabel('Adresse email').fill(email);
  await page.getByLabel('Mot de passe', { exact: true }).fill(password);
  await page.getByRole('button', { name: /Connexion/ }).click();
  await page.locator('article.universe-card').filter({ has: page.getByRole('heading', { name: /Omega/ }) }).getByRole('button').click();

  await page.goto('/diplomatie#alliance-guilds');
  await expect(page.locator('.guild-card')).toHaveCount(0);
  await expect(page.getByRole('status').filter({ hasText: 'Aucune guilde ne figure encore dans cet univers.' })).toBeVisible();
  await expect(page.getByLabel('Nom de votre nouvelle guilde')).toBeVisible();
  await expect(page.getByRole('button', { name: 'Fonder une guilde' })).toBeEnabled();

  await page.setViewportSize({ width: 390, height: 844 });
  await expect.poll(() => page.evaluate(() => document.documentElement.scrollWidth)).toBeLessThanOrEqual(390);
});
