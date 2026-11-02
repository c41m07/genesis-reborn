import { test, expect } from './support/isolated-test.mjs';

async function createAndJoin(page, prefix) {
  const id = crypto.randomUUID().slice(0, 8);
  const email = prefix.toLowerCase() + '-' + id + '@example.test';
  const password = 'Browser-test-2026!';
  await page.goto('/inscription');
  await page.getByLabel('Adresse email').fill(email);
  await page.getByLabel('Nom de commandant').fill(prefix + '-' + id);
  await page.getByLabel('Mot de passe (12 caractères minimum)', { exact: true }).fill(password);
  await page.getByLabel('Confirmer le mot de passe').fill(password);
  await page.getByRole('button', { name: 'Créer mon compte' }).click();
  await expect(page).toHaveURL(/connexion/);
  await page.getByLabel('Adresse email').fill(email);
  await page.getByLabel('Mot de passe', { exact: true }).fill(password);
  await page.getByRole('button', { name: /Connexion/ }).click();
  await page.locator('article.universe-card').filter({ has: page.getByRole('heading', { name: /Turbo/ }) }).getByRole('button').click();
  await expect(page).toHaveURL(/planetes\/\d+$/);
  return page.url();
}

test('Deux comptes rejoignent le même univers après un parcours UI', async ({ page, browser }) => {
  test.setTimeout(60000);
  const firstPlanet = await createAndJoin(page, 'Premier');
  for (const route of ['/carte', '/rapports', '/flottes', '/messages', '/diplomatie', '/classement', '/profil']) await page.goto(route);
  const second = await browser.newPage();
  const secondPlanet = await createAndJoin(second, 'Second');
  expect(secondPlanet).not.toBe(firstPlanet);
  await second.close();
});
