import { test, expect } from './support/isolated-test.mjs';

test('Deux onglets sans guilde, recherche conservée et anciennes adresses compatibles', async ({ page }) => {
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
  await page.locator('article.universe-card').filter({ has: page.getByRole('heading', { name: /Omega/ }) }).getByRole('button').click();
  await page.goto('/diplomatie');
  await expect(page.locator('.guild-hub-hero')).toContainText('Un empire se construit ensemble');
  const sections = page.getByRole('navigation', { name: 'Sections de diplomatie' });
  await expect(sections.getByRole('link')).toHaveText(['Créer ou rejoindre une guilde','Relations commandants']);
  for (const [label, tab, heading] of [['Créer ou rejoindre une guilde', 'guilds', 'Un empire se construit ensemble'], ['Relations commandants', 'relations', 'Relations entre commandants']]) {
    await sections.getByRole('link', { name: label, exact: true }).click();
    await expect(page).toHaveURL(url => url.searchParams.get('tab') === tab);
    await expect(sections.getByRole('link', { name: label, exact: true })).toHaveAttribute('aria-current', 'page');
    await expect(page.getByRole('heading', { name: heading, exact: true })).toBeVisible();
  }
  await page.goto('/diplomatie?tab=diplomacy&q=introuvable');
  await expect(sections.getByRole('link')).toHaveCount(2);
  await expect(page.getByLabel('Rechercher une guilde', {exact:true})).toHaveValue('introuvable');
  await expect(page.getByRole('status')).toContainText('Aucune guilde');
  await page.goto('/diplomatie?tab=ranks');
  await expect(page.getByLabel('Nom du grade', {exact:true})).toHaveCount(0);
  await page.setViewportSize({ width: 390, height: 844 });
  await expect.poll(() => page.evaluate(() => document.documentElement.scrollWidth)).toBeLessThanOrEqual(390);
});
