import { test, expect } from './support/isolated-test.mjs';

test('Les erreurs de connexion et d’inscription restent visibles', async ({ page }) => {
  await page.goto('/connexion');
  await page.getByLabel('Adresse email').fill('inconnu@example.test');
  await page.getByLabel('Mot de passe', { exact: true }).fill('Incorrect-Password-2026!');
  await page.getByRole('button', { name: /Connexion/ }).click();
  const loginError = page.getByRole('alert');
  await expect(loginError).toContainText('Connexion impossible. Vérifiez vos identifiants');

  await page.goto('/inscription');
  await page.getByLabel('Adresse email').fill('mismatch@example.test');
  await page.getByLabel('Nom de commandant').fill('Mismatch');
  await page.getByLabel('Mot de passe (12 caractères minimum)', { exact: true }).fill('Registration-2026!');
  await page.getByLabel('Confirmer le mot de passe').fill('Different-2026!');
  const responsePromise = page.waitForResponse(response => response.url().includes('/inscription') && response.request().method() === 'POST');
  await page.getByRole('button', { name: 'Créer mon compte' }).click();
  const response = await responsePromise;
  expect(response.status()).toBe(422);
  await expect(page.getByText('Les mots de passe ne correspondent pas.')).toBeVisible();
  await expect(page.getByRole('alert').filter({ hasText: 'Les mots de passe ne correspondent pas.' })).toBeVisible();
  await page.screenshot({ path: 'docs/validation/inscription-erreur-mots-de-passe-20261101.png', fullPage: true });
});
