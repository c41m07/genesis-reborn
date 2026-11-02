import { test, expect } from './support/isolated-test.mjs';

test('Les réponses 403 et 404 affichent un secours responsive et accessible', async ({ page }) => {
  const id = crypto.randomUUID().slice(0, 8);
  const email = 'error-pages-' + id + '@example.test';
  const password = 'Browser-test-2026!';
  await page.goto('/inscription');
  await page.getByLabel('Adresse email').fill(email);
  await page.getByLabel('Nom de commandant').fill('Error-' + id);
  await page.getByLabel('Mot de passe (12 caractères minimum)', { exact: true }).fill(password);
  await page.getByLabel('Confirmer le mot de passe').fill(password);
  await page.getByRole('button', { name: 'Créer mon compte' }).click();
  await expect(page).toHaveURL(/connexion/);
  await page.getByLabel('Adresse email').fill(email);
  await page.getByLabel('Mot de passe', { exact: true }).fill(password);
  await page.getByRole('button', { name: /Connexion/ }).click();
  await page.locator('article.universe-card').filter({ has: page.getByRole('heading', { name: /Turbo/ }) }).getByRole('button').click();

  const errorRoutes = [
    { path: '/admin', status: 403, heading: 'Accès refusé' },
    { path: '/rapports/combat/99999999', status: 404, heading: 'Page introuvable' }
  ];
  for (const width of [1440, 390]) {
    await page.setViewportSize({ width, height: 900 });
    for (const route of errorRoutes) {
      const response = await page.goto(route.path, { waitUntil: 'domcontentloaded' });
      expect(response?.status(), route.path).toBe(route.status);
      await expect(page.locator('main#main')).toBeVisible();
      await expect(page.getByRole('heading', { level: 1, name: route.heading })).toBeVisible();
      await expect(page.getByRole('link', { name: 'Retour à l’accueil' })).toBeVisible();
      await page.keyboard.press('Tab');
      await expect(page.getByRole('link', { name: 'Aller au contenu principal' })).toBeFocused();
      expect(await page.locator('main#main').evaluate(node => node.getAttribute('aria-labelledby'))).toBe('error-title');
    }
  }
});
