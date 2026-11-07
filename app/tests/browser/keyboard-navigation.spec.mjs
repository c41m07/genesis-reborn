import { test, expect } from './support/isolated-test.mjs';

async function signIn(page) {
  const id = crypto.randomUUID().slice(0, 8);
  const email = 'keyboard-' + id + '@example.test';
  const password = 'Browser-test-2026!';
  await page.goto('/inscription');
  await page.getByLabel('Adresse email').fill(email);
  await page.getByLabel('Nom de commandant').fill('Keyboard-' + id);
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

test('Lien d’évitement et focus clavier visibles sur toutes les routes principales', async ({ page }) => {
  await signIn(page);
  const routes = await page.locator('.sidebar nav a').evaluateAll(links =>
    [...new Set(links.map(link => new URL(link.href).pathname + new URL(link.href).search))]
  );
  for (const width of [1440, 390]) {
    await page.setViewportSize({ width, height: 900 });
    for (const route of routes) {
      await page.goto(route);
      await page.keyboard.press('Tab');
      const skip = page.locator('a.skip');
      await expect(skip, route + ' : lien d’évitement premier au clavier').toBeFocused();
      const focus = await skip.evaluate(element => ({
        visible: element.getBoundingClientRect().top >= 0,
        keyboard: element.matches(':focus-visible'),
        outline: getComputedStyle(element).outlineStyle,
        target: element.getAttribute('href')
      }));
      expect(focus, route).toEqual({ visible: true, keyboard: true, outline: 'solid', target: '#main' });
      await page.keyboard.press('Enter');
      await expect(page).toHaveURL(/#main$/);
    }
  }
});
