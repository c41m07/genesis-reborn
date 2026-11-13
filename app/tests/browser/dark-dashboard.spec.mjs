import { test, expect } from './support/isolated-test.mjs';

test('Thème sombre par défaut : dashboard structuré et navigation responsive', async ({ page }) => {
  const id = crypto.randomUUID().slice(0, 8), email = 'dark-ui-' + id + '@example.test', password = 'Browser-test-2026!';
  await page.goto('/inscription');
  await page.getByLabel('Adresse email').fill(email);
  await page.getByLabel('Nom de commandant').fill('Dark-' + id);
  await page.getByLabel('Mot de passe (12 caractères minimum)', { exact: true }).fill(password);
  await page.getByLabel('Confirmer le mot de passe').fill(password);
  await page.getByRole('button', { name: 'Créer mon compte' }).click();
  await expect(page).toHaveURL(/connexion/);
  await page.getByLabel('Adresse email').fill(email);
  await page.getByLabel('Mot de passe', { exact: true }).fill(password);
  await page.getByRole('button', { name: /Connexion/ }).click();
  await page.locator('article.universe-card').filter({ has: page.getByRole('heading', { name: /Turbo/ }) }).getByRole('button').click();
  await expect(page).toHaveURL(/planetes\/\d+$/);
  const colonyPath = new URL(page.url()).pathname;
  await expect(page.locator('body')).toHaveAttribute('data-theme', 'dark');
  await expect(page.locator('link[href*=wireframe-theme]')).toHaveCount(0);
  await expect.poll(() => page.locator('.dashboard-metrics').evaluate(el => getComputedStyle(el).display)).toBe('grid');
  await expect.poll(() => page.locator('.dashboard-layout').evaluate(el => getComputedStyle(el).display)).toBe('grid');
  const metrics = page.locator('.dashboard-metrics .metric-card');
  await expect(metrics).toHaveCount(4);
  await expect(metrics.nth(1)).toContainText('Classement');
  await expect(metrics.nth(1).locator('strong')).toHaveText(/^#\d+$/);
  await expect(metrics.nth(3)).toContainText('Guilde');
  await expect(metrics.nth(3)).toHaveAttribute('href', '/diplomatie');
  await page.setViewportSize({ width: 1440, height: 900 });

  await page.goto(colonyPath + '?section=buildings');
  await page.getByRole('button', { name: 'Production' }).click();
  await expect(page.locator('.catalog-card[data-category="economy"]:visible').first()).toBeVisible();
  await expect(page.locator('.catalog-card[data-category="infrastructure"]:visible')).toHaveCount(0);
  await page.goto(colonyPath);
  await page.setViewportSize({ width: 390, height: 844 });
  await expect(page.getByRole('navigation', { name: 'Navigation mobile' })).toBeVisible();
  await expect(page.locator('body')).not.toHaveClass(/nav-open/);
  await expect(page.locator('#command-navigation')).toHaveAttribute('aria-hidden', 'true');
  await expect.poll(() => page.locator('#command-navigation').evaluate(el => el.getBoundingClientRect().right)).toBeLessThanOrEqual(0);
  await expect.poll(() => page.evaluate(() => document.documentElement.scrollWidth)).toBeLessThanOrEqual(390);

});
