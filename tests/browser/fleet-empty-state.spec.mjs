import { test, expect } from './support/isolated-test.mjs';

test('L’état Espionnage sans flotte conduit au chantier réel', async ({ page }) => {
  const id = crypto.randomUUID().slice(0, 8);
  const email = 'fleet-empty-' + id + '@example.test';
  const password = 'Browser-test-2026!';

  await page.goto('/inscription');
  await page.getByLabel('Adresse email').fill(email);
  await page.getByLabel('Nom de commandant').fill('Empty-' + id);
  await page.getByLabel('Mot de passe (12 caractères minimum)', { exact: true }).fill(password);
  await page.getByLabel('Confirmer le mot de passe').fill(password);
  await page.getByRole('button', { name: 'Créer mon compte' }).click();
  await expect(page).toHaveURL(/connexion/);
  await page.getByLabel('Adresse email').fill(email);
  await page.getByLabel('Mot de passe', { exact: true }).fill(password);
  await page.getByRole('button', { name: /Connexion/ }).click();
  await page.locator('article.universe-card').filter({ has: page.getByRole('heading', { name: /Turbo/ }) }).getByRole('button').click();

  await page.goto('/flottes?mission=spy');
  await expect(page.getByRole('heading', { name: 'Espionnage', level: 1 })).toBeVisible();
  await expect(page.getByRole('heading', { name: 'Aucune sonde exploratrice disponible' })).toBeVisible();
  await page.screenshot({ path: 'docs/validation/espionnage-sans-flotte-20261101.png', fullPage: true });
  await page.setViewportSize({ width: 390, height: 844 });
  await expect.poll(() => page.locator('.sidebar').evaluate(element => element.getBoundingClientRect().right)).toBeLessThanOrEqual(0);
  await expect(page.locator('.sidebar')).toHaveAttribute('aria-hidden', 'true');
  await expect.poll(() => page.evaluate(() => document.documentElement.scrollWidth)).toBeLessThanOrEqual(390);
  await page.screenshot({ path: 'docs/validation/espionnage-sans-flotte-mobile-20261101.png', fullPage: true });
  const shipyard = page.getByRole('link', { name: /Accéder au chantier spatial/ });
  await expect(shipyard).toHaveAttribute('href', /planetes\/\d+\?section=ships/);
  let shipyardFocused = false;
  for (let index = 0; index < 50; index++) {
    await page.keyboard.press('Tab');
    if (await page.evaluate(() => document.activeElement?.getAttribute('href')) === await shipyard.getAttribute('href')) {
      shipyardFocused = true;
      break;
    }
  }
  expect(shipyardFocused).toBe(true);
  await expect(shipyard).toBeFocused();
  await expect.poll(() => shipyard.evaluate(element => element.matches(':focus-visible'))).toBe(true);
  await page.screenshot({ path: 'docs/validation/espionnage-chantier-focus-mobile-20261101.png', fullPage: true });
  await page.keyboard.press('Enter');
  await expect(page).toHaveURL(/planetes\/\d+\?section=ships/);
  await expect(page.getByRole('heading', { name: /Chantier spatial/ })).toBeVisible();

  const menuToggle = page.getByRole('button', { name: 'Plus' });
  await menuToggle.click();
  await expect(menuToggle).toHaveAttribute('aria-expanded', 'true');
  await expect(page.locator('.sidebar')).toHaveAttribute('aria-hidden', 'false');
  await expect.poll(() => page.locator('.sidebar').evaluate(element => element.getBoundingClientRect().left)).toBe(0);
  await page.screenshot({ path: 'docs/validation/espionnage-menu-plus-mobile-20261101.png', fullPage: false });
  await page.keyboard.press('Escape');
  await expect(menuToggle).toHaveAttribute('aria-expanded', 'false');
  await expect(page.locator('.sidebar')).toHaveAttribute('aria-hidden', 'true');
  await expect.poll(() => page.locator('.sidebar').evaluate(element => element.getBoundingClientRect().right)).toBeLessThanOrEqual(0);
  await expect(menuToggle).toBeFocused();
});
