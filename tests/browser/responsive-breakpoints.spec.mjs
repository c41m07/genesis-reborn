import { test, expect } from './support/isolated-test.mjs';

async function signIn(page) {
  const id = crypto.randomUUID().slice(0, 8);
  const email = `responsive-${id}@example.test`;
  const password = 'Browser-test-2026!';
  await page.goto('/inscription');
  await page.getByLabel('Adresse email').fill(email);
  await page.getByLabel('Nom de commandant').fill(`Responsive-${id}`);
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

test('Application sans débordement aux breakpoints desktop, tablette et mobile', async ({ page }) => {
  await signIn(page);
  await page.screenshot({ path: 'test-results/genesis-emblem-preview.png' });
  await expect.poll(() => page.locator('.brand-mark img').evaluate(image => image.naturalWidth)).toBeGreaterThan(0);
  await page.goto('/planetes');
  await expect.poll(() => page.locator('.planet-index-art img').first().evaluate(image => image.naturalWidth)).toBeGreaterThan(0);

  for (const width of [1440, 768]) {
    await page.setViewportSize({ width, height: 900 });
    await expect.poll(() => page.evaluate(() => document.documentElement.scrollWidth)).toBeLessThanOrEqual(width);
    await expect(page.locator('.sidebar')).toBeVisible();
  }

  await page.setViewportSize({ width: 390, height: 844 });
  await expect.poll(() => page.evaluate(() => document.documentElement.scrollWidth)).toBeLessThanOrEqual(390);
  const toggle = page.getByRole('navigation', { name: 'Navigation mobile' }).getByRole('button', { name: 'Plus' });
  await expect(toggle).toBeVisible();
  await expect(toggle).toHaveAttribute('aria-expanded', 'false');
  await toggle.click();
  await expect(toggle).toHaveAttribute('aria-expanded', 'true');
  await expect(page.locator('#command-navigation').getByRole('link', { name: 'Galaxie', exact: true })).toBeVisible();
  await page.keyboard.press('Escape');
  await expect(toggle).toHaveAttribute('aria-expanded', 'false');
});
